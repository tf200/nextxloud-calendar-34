<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Sync;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

class SyncEventFormatter {
	/**
	 * Format event for the given provider.
	 *
	 * @param string $provider
	 * @param array $objectData
	 * @return array
	 */
	public function format(string $provider, array $objectData): array {
		$vCalendar = Reader::read($objectData['calendardata']);
		$vevent = $vCalendar->VEVENT;

		if ($provider === 'google') {
			return $this->formatForGoogle($vevent);
		} elseif ($provider === 'microsoft') {
			return $this->formatForMicrosoft($vevent);
		}

		throw new \InvalidArgumentException("Unsupported provider: {$provider}");
	}

	private function formatForGoogle(VEvent $vevent): array {
		$summary = $vevent->SUMMARY ? (string)$vevent->SUMMARY : '';
		$description = $vevent->DESCRIPTION ? (string)$vevent->DESCRIPTION : '';
		$location = $vevent->LOCATION ? (string)$vevent->LOCATION : '';

		$startProp = $vevent->DTSTART;
		$endProp = $vevent->DTEND;

		$start = [];
		$end = [];

		$isAllDay = $startProp->getValueType() === 'DATE';

		if ($isAllDay) {
			$start['date'] = $startProp->getDateTime()->format('Y-m-d');
			// Google calendar end date is exclusive for all-day events
			$end['date'] = $endProp->getDateTime()->format('Y-m-d');
		} else {
			$startDt = $startProp->getDateTime();
			$endDt = $endProp->getDateTime();

			$start['dateTime'] = $startDt->format(\DateTimeInterface::ATOM);
			$start['timeZone'] = $startDt->getTimezone()->getName();

			$end['dateTime'] = $endDt->format(\DateTimeInterface::ATOM);
			$end['timeZone'] = $endDt->getTimezone()->getName();
		}

		$event = [
			'summary' => $summary,
			'description' => $description,
			'location' => $location,
			'start' => $start,
			'end' => $end,
		];

		// Handle RRULE
		if (isset($vevent->RRULE)) {
			$rrule = (string)$vevent->RRULE;
			$event['recurrence'] = ['RRULE:' . $rrule];
		}

		return $event;
	}

	private function formatForMicrosoft(VEvent $vevent): array {
		$subject = $vevent->SUMMARY ? (string)$vevent->SUMMARY : '';
		$description = $vevent->DESCRIPTION ? (string)$vevent->DESCRIPTION : '';
		$location = $vevent->LOCATION ? (string)$vevent->LOCATION : '';

		$startProp = $vevent->DTSTART;
		$endProp = $vevent->DTEND;

		$isAllDay = $startProp->getValueType() === 'DATE';

		$startDt = $startProp->getDateTime();
		$endDt = $endProp->getDateTime();

		// Microsoft Graph requires dateTime to be string (YYYY-MM-DDTHH:MM:SS) and a timeZone key
		$start = [
			'dateTime' => $startDt->format('Y-m-d\TH:i:s'),
			'timeZone' => $startDt->getTimezone()->getName() === 'UTC' || $isAllDay ? 'UTC' : $startDt->getTimezone()->getName(),
		];

		$end = [
			'dateTime' => $endDt->format('Y-m-d\TH:i:s'),
			'timeZone' => $endDt->getTimezone()->getName() === 'UTC' || $isAllDay ? 'UTC' : $endDt->getTimezone()->getName(),
		];

		$event = [
			'subject' => $subject,
			'body' => [
				'contentType' => 'text',
				'content' => $description,
			],
			'location' => [
				'displayName' => $location,
			],
			'start' => $start,
			'end' => $end,
			'isAllDay' => $isAllDay,
		];

		// Handle RRULE
		if (isset($vevent->RRULE)) {
			$rrule = (string)$vevent->RRULE;
			$microsoftRecurrence = $this->parseRruleToMicrosoft($rrule, $startDt);
			if ($microsoftRecurrence !== null) {
				$event['recurrence'] = $microsoftRecurrence;
			}
		}

		return $event;
	}

	/**
	 * Convert a standard RRULE to Microsoft Graph recurrence format.
	 */
	private function parseRruleToMicrosoft(string $rrule, \DateTimeInterface $startDt): ?array {
		// Example: FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE,FR;UNTIL=20261231T235959Z
		$parts = [];
		foreach (explode(';', $rrule) as $part) {
			$kv = explode('=', $part);
			if (count($kv) === 2) {
				$parts[strtoupper($kv[0])] = strtoupper($kv[1]);
			}
		}

		if (empty($parts['FREQ'])) {
			return null;
		}

		$interval = isset($parts['INTERVAL']) ? (int)$parts['INTERVAL'] : 1;
		$pattern = ['interval' => $interval];
		$range = [
			'startDate' => $startDt->format('Y-m-d'),
		];

		// Map Frequency
		switch ($parts['FREQ']) {
			case 'DAILY':
				$pattern['type'] = 'daily';
				break;
			case 'WEEKLY':
				$pattern['type'] = 'weekly';
				if (isset($parts['BYDAY'])) {
					$days = [];
					$map = [
						'SU' => 'sunday', 'MO' => 'monday', 'TU' => 'tuesday',
						'WE' => 'wednesday', 'TH' => 'thursday', 'FR' => 'friday',
						'SA' => 'saturday'
					];
					foreach (explode(',', $parts['BYDAY']) as $dayCode) {
						if (isset($map[$dayCode])) {
							$days[] = $map[$dayCode];
						}
					}
					$pattern['daysOfWeek'] = $days;
				} else {
					// Fallback to start date's day of week
					$pattern['daysOfWeek'] = [strtolower($startDt->format('l'))];
				}
				break;
			case 'MONTHLY':
				$pattern['type'] = 'absoluteMonthly';
				$pattern['dayOfMonth'] = (int)$startDt->format('d');
				break;
			case 'YEARLY':
				$pattern['type'] = 'absoluteYearly';
				$pattern['dayOfMonth'] = (int)$startDt->format('d');
				$pattern['month'] = (int)$startDt->format('m');
				break;
			default:
				return null;
		}

		// Map Range (UNTIL, COUNT, or no end)
		if (isset($parts['UNTIL'])) {
			$range['type'] = 'endDate';
			$untilDt = new \DateTime($parts['UNTIL']);
			$range['endDate'] = $untilDt->format('Y-m-d');
		} elseif (isset($parts['COUNT'])) {
			$range['type'] = 'numbered';
			$range['numberOfOccurrences'] = (int)$parts['COUNT'];
		} else {
			$range['type'] = 'noEnd';
		}

		return [
			'pattern' => $pattern,
			'range' => $range,
		];
	}
}
