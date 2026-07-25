<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method ?int getId()
 * @method void setId(int $value)
 * @method string getEventUid()
 * @method void setEventUid(string $value)
 * @method int getProjectId()
 * @method void setProjectId(int $value)
 */
class ProjectEventEntry extends Entity {
	protected string $eventUid = '';
	protected int $projectId = 0;
}
