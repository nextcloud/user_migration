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

namespace OC\Core\Controller;

use OCA\UserMigration\AppInfo\Application;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class ApiController extends OCSController {

	/** @var IUserManager */
	private $userManager;

	/** @var IUserSession */
	private $userSession;

	public function __construct(
		IRequest $request,
		IUserManager $userManager,
		IUserSession $userSession
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userManager = $userManager;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function status(): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSNotFoundException('User does not exist');
		}

		// TODO get status of user's background job

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function export(string $migrators): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSNotFoundException('User does not exist');
		}

		// TODO queue export job

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function import(string $path): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSNotFoundException('User does not exist');
		}

		// TODO queue import job

		return new DataResponse();
	}
}
