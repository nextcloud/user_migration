<?php

declare(strict_types=1);

/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserMigration\Settings\Personal;

use OCA\UserMigration\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Settings implements ISettings {
	/** @var IConfig */
	private $serverConfig;
	/** @var IInitialState */
	private $initialState;
	/** @var ICacheFactory */
	private $memcacheFactory;
	/** @var IUser */
	private $currentUser;
	/** @var IL10N */
	private $l10n;

	public function __construct(
		IConfig $serverConfig,
		IInitialState $initialState,
		ICacheFactory $memcacheFactory,
		IUserSession $userSession,
		IL10N $l10n,
	) {
		$this->serverConfig = $serverConfig;
		$this->initialState = $initialState;
		$this->memcacheFactory = $memcacheFactory;
		$this->currentUser = $userSession->getUser();
		$this->l10n = $l10n;
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/personal/settings');
	}

	public function getSection(): string {
		return 'migration';
	}

	public function getPriority(): int {
		return 20;
	}
}
