<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration\Settings\Personal;

use OCA\UserMigration\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class Settings implements ISettings {

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-personalSettings');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-personalSettings');
		return new TemplateResponse(Application::APP_ID, 'settings/personal/settings');
	}

	public function getSection(): string {
		return 'migration';
	}

	public function getPriority(): int {
		return 20;
	}
}
