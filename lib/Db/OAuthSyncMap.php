<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getProvider()
 * @method void setProvider(string $provider)
 * @method int getLocalEventId()
 * @method void setLocalEventId(int $localEventId)
 * @method string getProviderEventId()
 * @method void setProviderEventId(string $providerEventId)
 * @method string getEventHash()
 * @method void setEventHash(string $eventHash)
 * @method string|null getLastError()
 * @method void setLastError(?string $lastError)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class OAuthSyncMap extends Entity {
	/** @var string */
	protected $userId;

	/** @var string */
	protected $provider;

	/** @var int */
	protected $localEventId;

	/** @var string */
	protected $providerEventId;

	/** @var string */
	protected $eventHash;

	/** @var string|null */
	protected $lastError;

	/** @var int */
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('localEventId', Types::INTEGER);
		$this->addType('updatedAt', Types::INTEGER);
	}
}
