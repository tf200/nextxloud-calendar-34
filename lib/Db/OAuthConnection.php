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
 * @method string getAccessToken()
 * @method void setAccessToken(string $accessToken)
 * @method string getRefreshToken()
 * @method void setRefreshToken(string $refreshToken)
 * @method int getTokenExpiry()
 * @method void setTokenExpiry(int $tokenExpiry)
 * @method string|null getProviderCalendarId()
 * @method void setProviderCalendarId(?string $providerCalendarId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class OAuthConnection extends Entity {
	/** @var string */
	protected $userId;

	/** @var string */
	protected $provider;

	/** @var string */
	protected $accessToken;

	/** @var string */
	protected $refreshToken;

	/** @var int */
	protected $tokenExpiry;

	/** @var string|null */
	protected $providerCalendarId;

	/** @var int */
	protected $createdAt;

	/** @var int */
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('tokenExpiry', Types::INTEGER);
		$this->addType('createdAt', Types::INTEGER);
		$this->addType('updatedAt', Types::INTEGER);
	}
}
