<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class AdminConfigController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get stored Google and Microsoft OAuth credentials (excluding secrets for security).
	 */
	public function getCredentials(): JSONResponse {
		return new JSONResponse([
			'google_client_id' => $this->config->getAppValue($this->appName, 'google_client_id', ''),
			'google_client_secret_set' => !empty($this->config->getAppValue($this->appName, 'google_client_secret', '')),
			'microsoft_client_id' => $this->config->getAppValue($this->appName, 'microsoft_client_id', ''),
			'microsoft_client_secret_set' => !empty($this->config->getAppValue($this->appName, 'microsoft_client_secret', '')),
		]);
	}

	/**
	 * Set Google and Microsoft OAuth credentials.
	 */
	public function setCredentials(
		string $googleClientId = '',
		string $googleClientSecret = '',
		string $microsoftClientId = '',
		string $microsoftClientSecret = ''
	): JSONResponse {
		$this->config->setAppValue($this->appName, 'google_client_id', trim($googleClientId));
		if (!empty($googleClientSecret)) {
			$this->config->setAppValue($this->appName, 'google_client_secret', trim($googleClientSecret));
		}

		$this->config->setAppValue($this->appName, 'microsoft_client_id', trim($microsoftClientId));
		if (!empty($microsoftClientSecret)) {
			$this->config->setAppValue($this->appName, 'microsoft_client_secret', trim($microsoftClientSecret));
		}

		return new JSONResponse(['status' => 'success']);
	}
}
