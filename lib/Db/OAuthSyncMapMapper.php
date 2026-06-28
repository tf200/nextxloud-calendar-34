<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<OAuthSyncMap>
 */
class OAuthSyncMapMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'calendar_oauth_sync_map');
	}

	/**
	 * Find a mapping by provider and local event ID.
	 *
	 * @param string $provider
	 * @param int $localEventId
	 * @return OAuthSyncMap
	 * @throws DoesNotExistException
	 */
	public function findMapping(string $provider, int $localEventId): OAuthSyncMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('provider', $qb->createNamedParameter($provider, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('local_event_id', $qb->createNamedParameter($localEventId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find all failed mappings (with error) for a given provider and user.
	 *
	 * @param string $provider
	 * @param string $userId
	 * @return OAuthSyncMap[]
	 */
	public function findFailedMappings(string $provider, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('provider', $qb->createNamedParameter($provider, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->isNotNull('last_error')
			);
		return $this->findEntities($qb);
	}

	/**
	 * Find all mappings for a user and provider.
	 *
	 * @param string $provider
	 * @param string $userId
	 * @return OAuthSyncMap[]
	 */
	public function findMappingsForUser(string $provider, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('provider', $qb->createNamedParameter($provider, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR)
			);
		return $this->findEntities($qb);
	}

	/**
	 * Delete a sync mapping by provider and local event ID.
	 *
	 * @param string $provider
	 * @param int $localEventId
	 * @return int
	 */
	public function deleteMapping(string $provider, int $localEventId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('provider', $qb->createNamedParameter($provider, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('local_event_id', $qb->createNamedParameter($localEventId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		return $qb->executeStatement();
	}

	/**
	 * Delete all sync mappings for a user.
	 *
	 * @param string $userId
	 * @param string $provider
	 * @return int
	 */
	public function deleteForUser(string $userId, string $provider): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('provider', $qb->createNamedParameter($provider, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR)
			);
		return $qb->executeStatement();
	}
}
