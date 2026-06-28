<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Sync\Client;

use OCA\Calendar\Service\OAuth\OAuthTokenService;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class GoogleCalendarClient {
	private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

	public function __construct(
		private IClientService $clientService,
		private OAuthTokenService $tokenService,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Create a client pre-configured with OAuth header.
	 */
	private function getClient(string $userId): \OCP\Http\Client\IClient {
		$token = $this->tokenService->getAccessToken($userId, 'google');
		$client = $this->clientService->newClient();
		// In Nextcloud, Guzzle client headers are passed per request, but we can set them globally or build request parameters
		return $client;
	}

	private function getHeaders(string $userId): array {
		$token = $this->tokenService->getAccessToken($userId, 'google');
		return [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type' => 'application/json',
		];
	}

	public function createCalendar(string $userId): string {
		$client = $this->getClient($userId);
		$url = self::BASE_URL . '/calendars';
		$body = json_encode([
			'summary' => 'Nextcloud Calendar',
		]);

		$response = $client->post($url, [
			'headers' => $this->getHeaders($userId),
			'body' => $body,
		]);

		$data = json_decode($response->getBody(), true);
		if (empty($data['id'])) {
			throw new \RuntimeException('Failed to create Google calendar: ' . $response->getBody());
		}

		return $data['id'];
	}

	public function insertEvent(string $userId, string $calendarId, array $eventData): string {
		$client = $this->getClient($userId);
		$url = self::BASE_URL . "/calendars/" . urlencode($calendarId) . "/events";

		$response = $client->post($url, [
			'headers' => $this->getHeaders($userId),
			'body' => json_encode($eventData),
		]);

		$data = json_decode($response->getBody(), true);
		if (empty($data['id'])) {
			throw new \RuntimeException('Failed to insert event into Google Calendar: ' . $response->getBody());
		}

		return $data['id'];
	}

	public function updateEvent(string $userId, string $calendarId, string $providerEventId, array $eventData): void {
		$client = $this->getClient($userId);
		$url = self::BASE_URL . "/calendars/" . urlencode($calendarId) . "/events/" . urlencode($providerEventId);

		$response = $client->put($url, [
			'headers' => $this->getHeaders($userId),
			'body' => json_encode($eventData),
		]);

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			throw new \RuntimeException('Failed to update event in Google Calendar: ' . $response->getBody());
		}
	}

	public function deleteEvent(string $userId, string $calendarId, string $providerEventId): void {
		$client = $this->getClient($userId);
		$url = self::BASE_URL . "/calendars/" . urlencode($calendarId) . "/events/" . urlencode($providerEventId);

		try {
			$response = $client->delete($url, [
				'headers' => $this->getHeaders($userId),
			]);

			if ($response->getStatusCode() !== 204 && $response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
				throw new \RuntimeException('Failed to delete event from Google Calendar: ' . $response->getBody());
			}
		} catch (\Exception $e) {
			// If event is already deleted (404), count as success
			if (strpos($e->getMessage(), '404') === false) {
				throw $e;
			}
		}
	}
}
