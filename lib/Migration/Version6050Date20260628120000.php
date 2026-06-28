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

class Version6050Date20260628120000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('calendar_oauth_connections')) {
			$table = $schema->createTable('calendar_oauth_connections');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64
			]);
			$table->addColumn('provider', Types::STRING, [
				'notnull' => true,
				'length' => 32
			]);
			$table->addColumn('access_token', Types::TEXT, [
				'notnull' => true
			]);
			$table->addColumn('refresh_token', Types::TEXT, [
				'notnull' => true
			]);
			$table->addColumn('token_expiry', Types::BIGINT, [
				'notnull' => true
			]);
			$table->addColumn('provider_calendar_id', Types::STRING, [
				'notnull' => false,
				'length' => 255
			]);
			$table->addColumn('created_at', Types::INTEGER, [
				'notnull' => true
			]);
			$table->addColumn('updated_at', Types::INTEGER, [
				'notnull' => true
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'provider'], 'cal_oauth_conn_uniq');
			$table->addIndex(['user_id'], 'cal_oauth_conn_uid_idx');
		}

		if (!$schema->hasTable('calendar_oauth_sync_map')) {
			$table = $schema->createTable('calendar_oauth_sync_map');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64
			]);
			$table->addColumn('provider', Types::STRING, [
				'notnull' => true,
				'length' => 32
			]);
			$table->addColumn('local_event_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true
			]);
			$table->addColumn('provider_event_id', Types::STRING, [
				'notnull' => true,
				'length' => 255
			]);
			$table->addColumn('event_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64
			]);
			$table->addColumn('last_error', Types::TEXT, [
				'notnull' => false
			]);
			$table->addColumn('updated_at', Types::INTEGER, [
				'notnull' => true
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['provider', 'local_event_id'], 'cal_oauth_sync_uniq');
			$table->addIndex(['user_id'], 'cal_oauth_sync_uid_idx');
		}

		return $schema;
	}
}
