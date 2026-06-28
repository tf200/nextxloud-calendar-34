<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\BackgroundJob;

use OCA\Calendar\Db\OAuthConnectionMapper;
use OCA\Calendar\Db\OAuthSyncMapMapper;
use OCA\Calendar\Service\Sync\CalendarSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SyncCalendarsCronJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private OAuthConnectionMapper $connectionMapper,
		private OAuthSyncMapMapper $syncMapMapper,
		private CalendarSyncService $syncService,
		private LoggerInterface $logger
	) {
		parent::__construct($time);
		// Run every 30 minutes
		$this->setInterval(30 * 60);
		if (method_exists($this, 'setTimeSensitivity')) {
			$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		}
	}

	#[\Override]
	protected function run($argument): void {
		$connections = $this->connectionMapper->findAllConnections();

		foreach ($connections as $connection) {
			$userId = $connection->getUserId();
			$provider = $connection->getProvider();

			// 1. Periodic Initial Sync / Change Detection for this connected user
			try {
				$this->logger->info("Cron: Running periodic sync for user {$userId} and provider {$provider}");
				$this->syncService->runInitialSync($userId, $provider);
			} catch (\Exception $e) {
				$this->logger->error("Cron: Failed periodic sync for user {$userId} and provider {$provider}: " . $e->getMessage());
			}

			// 2. Retry failed sync mappings (where last_error is set)
			try {
				$defaultCalendar = $this->syncService->getDefaultCalendar($userId);
				if ($defaultCalendar === null) {
					continue;
				}
				$defaultCalendarId = (int)$defaultCalendar->getId();

				$failedMappings = $this->syncMapMapper->findFailedMappings($provider, $userId);
				if (!empty($failedMappings)) {
					$this->logger->info("Cron: Retrying " . count($failedMappings) . " failed mappings for user {$userId} and provider {$provider}");
				}

				foreach ($failedMappings as $mapping) {
					$localEventId = $mapping->getLocalEventId();
					$objectData = $this->syncService->getLocalEventById($localEventId);

					if ($objectData === null) {
						// Clean up local orphaned sync maps if local event no longer exists
						$this->logger->info("Cron: Cleanup failed mapping for non-existent local event {$localEventId}");
						$this->syncMapMapper->deleteMapping($provider, $localEventId);
						continue;
					}

					$this->syncService->syncEvent($userId, $provider, $objectData, $defaultCalendarId);
				}
			} catch (\Exception $e) {
				$this->logger->error("Cron: Failed retrying failed mappings for user {$userId} and provider {$provider}: " . $e->getMessage());
			}
		}
	}
}
