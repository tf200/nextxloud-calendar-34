<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Listener;

use OCA\Calendar\Service\OAuth\OAuthTokenService;
use OCA\Calendar\Service\Sync\CalendarSyncService;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<CalendarObjectCreatedEvent|CalendarObjectUpdatedEvent|CalendarObjectDeletedEvent>
 */
class SyncCalendarObjectListener implements IEventListener {
	public function __construct(
		private OAuthTokenService $tokenService,
		private CalendarSyncService $syncService,
		private LoggerInterface $logger
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof CalendarObjectCreatedEvent)
			&& !($event instanceof CalendarObjectUpdatedEvent)
			&& !($event instanceof CalendarObjectDeletedEvent)
		) {
			return;
		}

		$calendarData = $event->getCalendarData();
		$principalUri = $calendarData['principaluri'] ?? '';
		if (empty($principalUri)) {
			return;
		}

		// Extract userId from principal URI (principals/users/username)
		$parts = explode('/', rtrim($principalUri, '/'));
		$userId = end($parts);

		if (empty($userId)) {
			return;
		}

		// Providers to sync
		$providers = ['google', 'microsoft'];
		$connectedProviders = [];

		foreach ($providers as $provider) {
			if ($this->tokenService->hasConnection($userId, $provider)) {
				$connectedProviders[] = $provider;
			}
		}

		if (empty($connectedProviders)) {
			return;
		}

		// Resolve the user's default calendar
		$defaultCalendar = $this->syncService->getDefaultCalendar($userId);
		if ($defaultCalendar === null) {
			return;
		}

		$defaultCalendarId = (int)$defaultCalendar->getId();

		// Check if the event belongs to the default calendar
		if ($event->getCalendarId() !== $defaultCalendarId) {
			return;
		}

		foreach ($connectedProviders as $provider) {
			if ($event instanceof CalendarObjectDeletedEvent) {
				$this->logger->info("Syncing deleted event {$event->getObjectData()['id']} to provider {$provider} for user {$userId}");
				$this->syncService->deleteEvent($userId, $provider, (int)$event->getObjectData()['id']);
			} else {
				$this->logger->info("Syncing created/updated event {$event->getObjectData()['id']} to provider {$provider} for user {$userId}");
				$this->syncService->syncEvent($userId, $provider, $event->getObjectData(), $defaultCalendarId);
			}
		}
	}
}
