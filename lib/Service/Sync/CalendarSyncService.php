<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Sync;

use OCA\Calendar\Db\OAuthConnection;
use OCA\Calendar\Db\OAuthConnectionMapper;
use OCA\Calendar\Db\OAuthSyncMap;
use OCA\Calendar\Db\OAuthSyncMapMapper;
use OCA\Calendar\Service\OAuth\OAuthTokenService;
use OCA\Calendar\Service\Sync\Client\GoogleCalendarClient;
use OCA\Calendar\Service\Sync\Client\MicrosoftCalendarClient;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Calendar\IManager as CalendarManager;
use OCP\Calendar\ICreateFromString;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

class CalendarSyncService {
	public function __construct(
		private OAuthConnectionMapper $connectionMapper,
		private OAuthSyncMapMapper $syncMapMapper,
		private OAuthTokenService $tokenService,
		private GoogleCalendarClient $googleClient,
		private MicrosoftCalendarClient $microsoftClient,
		private SyncEventFormatter $formatter,
		private CalendarManager $calendarManager,
		private IDBConnection $db,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Get local event object data by database ID.
	 *
	 * @param int $localEventId
	 * @return array|null
	 */
	public function getLocalEventById(int $localEventId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'calendarid', 'calendardata')
			->from('calendarobjects')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($localEventId, IQueryBuilder::PARAM_INT)));
		
		$result = $qb->executeQuery();
		$row = $result->fetch();
		if ($row === false) {
			return null;
		}
		return $row;
	}

	/**
	 * Get the resolved default calendar for the user.
	 */
	public function getDefaultCalendar(string $userId): ?\OCP\Calendar\ICalendar {
		$principalUri = "principals/users/" . $userId;
		$calendars = $this->calendarManager->getCalendarsForPrincipal($principalUri);

		// 1. Try to find the calendar with URI matching 'personal'
		foreach ($calendars as $calendar) {
			if ($calendar->isDeleted()) {
				continue;
			}
			if ($calendar->getUri() === 'personal') {
				return $calendar;
			}
		}

		// 2. Fallback to the first writable calendar
		foreach ($calendars as $calendar) {
			if ($calendar->isDeleted()) {
				continue;
			}
			if ($calendar instanceof ICreateFromString) {
				return $calendar;
			}
		}

		// 3. Fallback to any non-deleted calendar
		foreach ($calendars as $calendar) {
			if (!$calendar->isDeleted()) {
				return $calendar;
			}
		}

		return null;
	}

	/**
	 * Compute SHA-256 hash of serialized event data to detect change.
	 */
	public function calculateEventHash(array $objectData): string {
		try {
			$vCalendar = Reader::read($objectData['calendardata']);
			$vevent = $vCalendar->VEVENT;

			$normalized = [
				'summary' => $vevent->SUMMARY ? (string)$vevent->SUMMARY : '',
				'description' => $vevent->DESCRIPTION ? (string)$vevent->DESCRIPTION : '',
				'location' => $vevent->LOCATION ? (string)$vevent->LOCATION : '',
				'dtstart' => $vevent->DTSTART ? (string)$vevent->DTSTART : '',
				'dtend' => $vevent->DTEND ? (string)$vevent->DTEND : '',
				'rrule' => $vevent->RRULE ? (string)$vevent->RRULE : '',
				'recurrence_id' => $vevent->{'RECURRENCE-ID'} ? (string)$vevent->{'RECURRENCE-ID'} : '',
			];

			return hash('sha256', json_encode($normalized));
		} catch (\Exception $e) {
			return hash('sha256', $objectData['calendardata'] ?? '');
		}
	}

	/**
	 * Sync an event to the provider.
	 */
	public function syncEvent(string $userId, string $provider, array $objectData, int $defaultCalendarId): void {
		if ((int)$objectData['calendarid'] !== $defaultCalendarId) {
			return;
		}

		try {
			$connection = $this->connectionMapper->findConnection($userId, $provider);
		} catch (DoesNotExistException $e) {
			return;
		}

		$calendarId = $connection->getProviderCalendarId();
		if (empty($calendarId)) {
			return;
		}

		$localEventId = (int)$objectData['id'];
		$hash = $this->calculateEventHash($objectData);

		$existingMap = null;
		try {
			$existingMap = $this->syncMapMapper->findMapping($provider, $localEventId);
		} catch (DoesNotExistException $e) {
			// Mapping doesn't exist
		}

		// Skip if unchanged
		if ($existingMap !== null && $existingMap->getEventHash() === $hash && $existingMap->getLastError() === null) {
			return;
		}

		try {
			$payload = $this->formatter->format($provider, $objectData);

			if ($existingMap !== null) {
				if ($provider === 'google') {
					$this->googleClient->updateEvent($userId, $calendarId, $existingMap->getProviderEventId(), $payload);
				} else {
					$this->microsoftClient->updateEvent($userId, $calendarId, $existingMap->getProviderEventId(), $payload);
				}
				$existingMap->setEventHash($hash);
				$existingMap->setLastError(null);
				$existingMap->setUpdatedAt(time());
				$this->syncMapMapper->update($existingMap);
			} else {
				if ($provider === 'google') {
					$providerEventId = $this->googleClient->insertEvent($userId, $calendarId, $payload);
				} else {
					$providerEventId = $this->microsoftClient->insertEvent($userId, $calendarId, $payload);
				}

				$newMap = new OAuthSyncMap();
				$newMap->setUserId($userId);
				$newMap->setProvider($provider);
				$newMap->setLocalEventId($localEventId);
				$newMap->setProviderEventId($providerEventId);
				$newMap->setEventHash($hash);
				$newMap->setLastError(null);
				$newMap->setUpdatedAt(time());
				$this->syncMapMapper->insert($newMap);
			}
		} catch (\Exception $e) {
			$this->logger->error("OAuth Calendar Sync error for user {$userId}, event {$localEventId}: " . $e->getMessage());
			if ($existingMap !== null) {
				$existingMap->setLastError($e->getMessage());
				$existingMap->setUpdatedAt(time());
				$this->syncMapMapper->update($existingMap);
			} else {
				$failedMap = new OAuthSyncMap();
				$failedMap->setUserId($userId);
				$failedMap->setProvider($provider);
				$failedMap->setLocalEventId($localEventId);
				$failedMap->setProviderEventId('');
				$failedMap->setEventHash($hash);
				$failedMap->setLastError($e->getMessage());
				$failedMap->setUpdatedAt(time());
				$this->syncMapMapper->insert($failedMap);
			}
		}
	}

	/**
	 * Delete event on provider.
	 */
	public function deleteEvent(string $userId, string $provider, int $localEventId): void {
		try {
			$connection = $this->connectionMapper->findConnection($userId, $provider);
		} catch (DoesNotExistException $e) {
			return;
		}

		$calendarId = $connection->getProviderCalendarId();
		if (empty($calendarId)) {
			return;
		}

		try {
			$existingMap = $this->syncMapMapper->findMapping($provider, $localEventId);
			$providerEventId = $existingMap->getProviderEventId();

			if (!empty($providerEventId)) {
				if ($provider === 'google') {
					$this->googleClient->deleteEvent($userId, $calendarId, $providerEventId);
				} else {
					$this->microsoftClient->deleteEvent($userId, $calendarId, $providerEventId);
				}
			}

			$this->syncMapMapper->deleteMapping($provider, $localEventId);
		} catch (DoesNotExistException $e) {
			// Mapping not found, nothing to do
		} catch (\Exception $e) {
			$this->logger->error("Failed to delete remote event for {$userId}: " . $e->getMessage());
		}
	}

	/**
	 * Run initial synchronization for the user's default calendar.
	 */
	public function runInitialSync(string $userId, string $provider): void {
		$defaultCalendar = $this->getDefaultCalendar($userId);
		if ($defaultCalendar === null) {
			throw new \RuntimeException("No default calendar found for user: {$userId}");
		}

		$defaultCalendarId = (int)$defaultCalendar->getId();

		try {
			$connection = $this->connectionMapper->findConnection($userId, $provider);
		} catch (DoesNotExistException $e) {
			throw new \RuntimeException("No OAuth connection found for user: {$userId}");
		}

		// 1. Create remote calendar if not already created
		if (empty($connection->getProviderCalendarId())) {
			if ($provider === 'google') {
				$providerCalendarId = $this->googleClient->createCalendar($userId);
			} else {
				$providerCalendarId = $this->microsoftClient->createCalendar($userId);
			}
			$connection->setProviderCalendarId($providerCalendarId);
			$connection->setUpdatedAt(time());
			$this->connectionMapper->update($connection);
		}

		// 2. Fetch events in range [-30 days, +365 days]
		$start = (new \DateTimeImmutable())->modify('-30 days');
		$end = (new \DateTimeImmutable())->modify('+365 days');

		$options = [
			'timerange' => [
				'start' => $start,
				'end' => $end,
			]
		];

		// search(string $pattern, array $properties, array $options, ?int $limit = null)
		$searchResult = $defaultCalendar->search('', [], $options);

		$this->logger->info("Starting initial calendar sync for user {$userId} and provider {$provider}. Found " . count($searchResult) . " events.");

		$existingLocalIds = [];
		foreach ($searchResult as $eventGroup) {
			foreach ($eventGroup['objects'] as $object) {
				$localId = (int)($object['id'] ?? $eventGroup['id']);
				$existingLocalIds[] = $localId;

				// Format objects array to standard objectData row format
				$objectData = [
					'id' => $localId,
					'calendarid' => $defaultCalendarId,
					'calendardata' => $object['calendardata'] ?? $eventGroup['calendardata'],
				];
				$this->syncEvent($userId, $provider, $objectData, $defaultCalendarId);
			}
		}

		// Cleanup orphaned sync mappings
		$this->cleanupOrphanedMappings($userId, $provider, $existingLocalIds);

		$this->logger->info("Finished initial calendar sync for user {$userId} and provider {$provider}.");
	}

	/**
	 * Delete remote events for local events that no longer exist in Nextcloud.
	 *
	 * @param string $userId
	 * @param string $provider
	 * @param int[] $existingLocalIds
	 */
	private function cleanupOrphanedMappings(string $userId, string $provider, array $existingLocalIds): void {
		$mappings = $this->syncMapMapper->findMappingsForUser($provider, $userId);
		foreach ($mappings as $mapping) {
			if (!in_array($mapping->getLocalEventId(), $existingLocalIds, true)) {
				$this->logger->info("Cleaning up orphaned sync mapping for event {$mapping->getLocalEventId()} and provider {$provider}");
				$this->deleteEvent($userId, $provider, $mapping->getLocalEventId());
			}
		}
	}
}
