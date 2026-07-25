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
 * @template-extends QBMapper<ProjectEventEntry>
 */
class ProjectEventMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'calendar_project_events', ProjectEventEntry::class);
	}

	/**
	 * @return ProjectEventEntry[]
	 */
	public function fetchByProjectId(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}

	public function link(string $eventUid, int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('event_uid', $qb->createNamedParameter($eventUid, IQueryBuilder::PARAM_STR)));
		try {
			$existingEntry = $this->findEntity($qb);
			if ($existingEntry->getProjectId() !== $projectId) {
				throw new \InvalidArgumentException('Calendar event is already linked to another project');
			}
			return;
		} catch (DoesNotExistException) {
			// Create the relation below.
		}

		$entry = new ProjectEventEntry();
		$entry->setEventUid($eventUid);
		$entry->setProjectId($projectId);
		$this->insert($entry);
	}
}
