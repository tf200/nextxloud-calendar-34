<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	/**
	 * @return TemplateResponse
	 */
	#[\Override]
	public function getForm(): TemplateResponse {
		return new TemplateResponse('calendar', 'admin-settings');
	}

	/**
	 * @return string
	 */
	#[\Override]
	public function getSection(): string {
		// Place under standard "Groupware" or "Additional settings" section
		return 'additional';
	}

	/**
	 * @return int
	 */
	#[\Override]
	public function getPriority(): int {
		return 50;
	}
}
