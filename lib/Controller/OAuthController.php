<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Controller;

use OCA\Calendar\Db\OAuthSyncMapMapper;
use OCA\Calendar\Service\OAuth\OAuthTokenService;
use OCA\Calendar\Service\Sync\CalendarSyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class OAuthController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IClientService $clientService,
		private OAuthTokenService $tokenService,
		private CalendarSyncService $syncService,
		private OAuthSyncMapMapper $syncMapMapper,
		private ?string $userId,
		private LoggerInterface $logger
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Initiate OAuth connect. Redirects user to consent screen.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function initiate(string $provider): RedirectResponse {
		if ($this->userId === null) {
			return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.index'));
		}

		$clientId = $this->config->getAppValue($this->appName, $provider . '_client_id', '');
		if (empty($clientId)) {
			return new RedirectResponse($this->urlGenerator->linkToRoute('calendar.view.index'));
		}

		$redirectUri = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('calendar.oauth.callback', ['provider' => $provider])
		);

		$state = bin2hex(random_bytes(16));
		// In a real app we might store state in session, but since standard OAuth setup here is for single user, we proceed directly.

		if ($provider === 'google') {
			$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
				'client_id' => $clientId,
				'redirect_uri' => $redirectUri,
				'response_type' => 'code',
				'scope' => 'https://www.googleapis.com/auth/calendar',
				'access_type' => 'offline',
				'prompt' => 'consent',
				'state' => $state,
			]);
		} elseif ($provider === 'microsoft') {
			$url = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query([
				'client_id' => $clientId,
				'redirect_uri' => $redirectUri,
				'response_type' => 'code',
				'scope' => 'https://graph.microsoft.com/Calendars.ReadWrite offline_access',
				'response_mode' => 'query',
				'state' => $state,
			]);
		} else {
			return new RedirectResponse($this->urlGenerator->linkToRoute('calendar.view.index'));
		}

		return new RedirectResponse($url);
	}

	/**
	 * OAuth Redirect Callback. Exchanges auth code for tokens and starts sync.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function callback(string $provider, string $code, string $state = ''): RedirectResponse {
		if ($this->userId === null) {
			return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.index'));
		}

		$clientId = $this->config->getAppValue($this->appName, $provider . '_client_id', '');
		$clientSecret = $this->config->getAppValue($this->appName, $provider . '_client_secret', '');

		if (empty($clientId) || empty($clientSecret)) {
			$this->logger->error("OAuth Callback failed: Client credentials missing for {$provider}");
			return new RedirectResponse($this->urlGenerator->linkToRoute('calendar.view.index'));
		}

		$redirectUri = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('calendar.oauth.callback', ['provider' => $provider])
		);

		$postFields = [
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'code' => $code,
			'redirect_uri' => $redirectUri,
			'grant_type' => 'authorization_code',
		];

		if ($provider === 'google') {
			$url = 'https://oauth2.googleapis.com/token';
		} elseif ($provider === 'microsoft') {
			$url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
		} else {
			return new RedirectResponse($this->urlGenerator->linkToRoute('calendar.view.index'));
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($url, [
				'body' => $postFields,
			]);

			$body = json_decode($response->getBody(), true);
			if (empty($body['access_token']) || empty($body['refresh_token'])) {
				throw new \RuntimeException("Failed to retrieve access or refresh tokens from {$provider}");
			}

			$expiresIn = (int)($body['expires_in'] ?? 3600);

			// Save the credentials
			$this->tokenService->saveConnection(
				$this->userId,
				$provider,
				$body['access_token'],
				$body['refresh_token'],
				$expiresIn
			);

			// Immediately execute the initial sync to set up calendar and sync objects
			$this->syncService->runInitialSync($this->userId, $provider);

		} catch (\Exception $e) {
			$this->logger->error("OAuth callback exchange failed for {$provider}: " . $e->getMessage());
		}

		return new RedirectResponse($this->urlGenerator->linkToRoute('calendar.view.index'));
	}

	/**
	 * Disconnect calendar sync and delete connection.
	 */
	#[NoAdminRequired]
	public function disconnect(string $provider): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->tokenService->removeConnection($this->userId, $provider);
			$this->syncMapMapper->deleteForUser($this->userId, $provider);
			return new JSONResponse(['status' => 'success']);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'message' => 'Connection not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get OAuth connection status and configured providers.
	 */
	#[NoAdminRequired]
	public function status(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}

		$googleConfigured = !empty($this->config->getAppValue($this->appName, 'google_client_id', ''));
		$microsoftConfigured = !empty($this->config->getAppValue($this->appName, 'microsoft_client_id', ''));

		return new JSONResponse([
			'google' => [
				'configured' => $googleConfigured,
				'connected' => $this->tokenService->hasConnection($this->userId, 'google'),
			],
			'microsoft' => [
				'configured' => $microsoftConfigured,
				'connected' => $this->tokenService->hasConnection($this->userId, 'microsoft'),
			]
		]);
	}
}
