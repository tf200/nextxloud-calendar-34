<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\OAuth;

use OCA\Calendar\Db\OAuthConnection;
use OCA\Calendar\Db\OAuthConnectionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class OAuthTokenService {
	public function __construct(
		private OAuthConnectionMapper $connectionMapper,
		private IConfig $config,
		private ICrypto $crypto,
		private IClientService $clientService,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Encrypt and save/update a connection.
	 *
	 * @param string $userId
	 * @param string $provider
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param int $expiresInSeconds
	 * @param string|null $providerCalendarId
	 * @return OAuthConnection
	 */
	public function saveConnection(
		string $userId,
		string $provider,
		string $accessToken,
		string $refreshToken,
		int $expiresInSeconds,
		?string $providerCalendarId = null
	): OAuthConnection {
		$encryptedAccess = $this->crypto->encrypt($accessToken);
		$encryptedRefresh = $this->crypto->encrypt($refreshToken);
		$expiryTime = time() + $expiresInSeconds;

		try {
			$connection = $this->connectionMapper->findConnection($userId, $provider);
			$connection->setAccessToken($encryptedAccess);
			$connection->setRefreshToken($encryptedRefresh);
			$connection->setTokenExpiry($expiryTime);
			if ($providerCalendarId !== null) {
				$connection->setProviderCalendarId($providerCalendarId);
			}
			$connection->setUpdatedAt(time());
			$this->connectionMapper->update($connection);
		} catch (DoesNotExistException $e) {
			$connection = new OAuthConnection();
			$connection->setUserId($userId);
			$connection->setProvider($provider);
			$connection->setAccessToken($encryptedAccess);
			$connection->setRefreshToken($encryptedRefresh);
			$connection->setTokenExpiry($expiryTime);
			$connection->setProviderCalendarId($providerCalendarId);
			$connection->setCreatedAt(time());
			$connection->setUpdatedAt(time());
			$this->connectionMapper->insert($connection);
		}

		return $connection;
	}

	/**
	 * Get plain text access token, refreshing if necessary (60s buffer).
	 *
	 * @param string $userId
	 * @param string $provider
	 * @return string
	 * @throws DoesNotExistException
	 * @throws \RuntimeException
	 */
	public function getAccessToken(string $userId, string $provider): string {
		$connection = $this->connectionMapper->findConnection($userId, $provider);
		$now = time();

		if (($connection->getTokenExpiry() - 60) <= $now) {
			$connection = $this->refreshAccessToken($connection);
		}

		return $this->crypto->decrypt($connection->getAccessToken());
	}

	/**
	 * Remove a connection.
	 *
	 * @param string $userId
	 * @param string $provider
	 * @throws DoesNotExistException
	 */
	public function removeConnection(string $userId, string $provider): void {
		$connection = $this->connectionMapper->findConnection($userId, $provider);
		$this->connectionMapper->delete($connection);
	}

	/**
	 * Check if a user has a connection for a provider.
	 *
	 * @param string $userId
	 * @param string $provider
	 * @return bool
	 */
	public function hasConnection(string $userId, string $provider): bool {
		try {
			$this->connectionMapper->findConnection($userId, $provider);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * Refresh an expired access token using the refresh token.
	 *
	 * @param OAuthConnection $connection
	 * @return OAuthConnection
	 * @throws \RuntimeException
	 */
	private function refreshAccessToken(OAuthConnection $connection): OAuthConnection {
		$provider = $connection->getProvider();
		$refreshToken = $this->crypto->decrypt($connection->getRefreshToken());

		$clientId = $this->config->getAppValue('calendar', $provider . '_client_id', '');
		$clientSecret = $this->config->getAppValue('calendar', $provider . '_client_secret', '');

		if (empty($clientId) || empty($clientSecret)) {
			throw new \RuntimeException("Missing Client ID or Client Secret configuration for provider: {$provider}");
		}

		$postFields = [
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'refresh_token' => $refreshToken,
			'grant_type' => 'refresh_token',
		];

		if ($provider === 'google') {
			$url = 'https://oauth2.googleapis.com/token';
		} elseif ($provider === 'microsoft') {
			$url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
			$postFields['scope'] = 'https://graph.microsoft.com/Calendars.ReadWrite offline_access';
		} else {
			throw new \RuntimeException("Unsupported provider: {$provider}");
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($url, [
				'body' => $postFields,
			]);

			$body = json_decode($response->getBody(), true);
			if (empty($body['access_token'])) {
				throw new \RuntimeException("Failed to refresh access token: " . ($body['error_description'] ?? 'unknown error'));
			}

			$connection->setAccessToken($this->crypto->encrypt($body['access_token']));
			if (!empty($body['refresh_token'])) {
				$connection->setRefreshToken($this->crypto->encrypt($body['refresh_token']));
			}
			$expiresIn = (int)($body['expires_in'] ?? 3600);
			$connection->setTokenExpiry(time() + $expiresIn);
			$connection->setUpdatedAt(time());

			$this->connectionMapper->update($connection);
			$this->logger->info("Successfully refreshed OAuth access token for user {$connection->getUserId()} and provider {$provider}");

			return $connection;
		} catch (\Exception $e) {
			$this->logger->error("Error refreshing OAuth token for {$connection->getUserId()} and {$provider}: " . $e->getMessage());
			throw new \RuntimeException("Failed to refresh token: " . $e->getMessage(), 0, $e);
		}
	}
}
