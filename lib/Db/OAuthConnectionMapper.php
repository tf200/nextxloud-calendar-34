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
 * @template-extends QBMapper<OAuthConnection>
 */
class OAuthConnectionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'calendar_oauth_connections');
	}

	/**
	 * Find a connection for a specific user and provider.
	 *
	 * @param string $userId
	 * @param string $provider
	 * @return OAuthConnection
	 * @throws DoesNotExistException
	 */
	public function findConnection(string $userId, string $provider): OAuthConnection {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR),
				$qb->expr()->eq('provider', $qb->createNamedParameter($provider, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR)
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find all active connections across all users.
	 *
	 * @return OAuthConnection[]
	 */
	public function findAllConnections(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * Find all active connections for a given user.
	 *
	 * @param string $userId
	 * @return OAuthConnection[]
	 */
	public function findConnectionsForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR), IQueryBuilder::PARAM_STR));
		return $this->findEntities($qb);
	}
}
