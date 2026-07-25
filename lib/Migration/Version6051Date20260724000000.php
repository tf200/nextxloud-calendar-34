<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version6051Date20260724000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('calendar_project_events')) {
			$table = $schema->createTable('calendar_project_events');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('event_uid', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('project_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['event_uid'], 'cal_project_event_uid');
			$table->addIndex(['project_id'], 'cal_project_event_pid');
		}

		if ($schema->hasTable('calendar_proposal_dts')) {
			$table = $schema->getTable('calendar_proposal_dts');
			if (!$table->hasIndex('cal_proposal_uid_pid')) {
				$table->addIndex(['uid', 'project_id'], 'cal_proposal_uid_pid');
			}
		}

		return $schema;
	}
}
