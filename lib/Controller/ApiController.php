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

namespace OCA\UserMigration\Controller;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\Db\UserExport;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\UserMigrationException;

class ApiController extends OCSController {
	private IUserSession $userSession;

	private IUserManager $userManager;

	private UserMigrationService $migrationService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		UserMigrationService $migrationService
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 */
	public function migrators(): DataResponse {
		return new DataResponse(
			array_map(
				fn (IMigrator $migrator) => [
					'id' => $migrator->getId(),
					'displayName' => $migrator->getDisplayName(),
					'description' => $migrator->getDescription(),
				],
				$this->migrationService->getMigrators(),
			),
			Http::STATUS_OK,
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 */
	public function status(): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSException('No user currently logged in');
		}

		return new DataResponse(
			$this->migrationService->getCurrentJobData($user),
			Http::STATUS_OK,
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function cancel(): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSException('No user currently logged in');
		}

		$job = $this->migrationService->getCurrentJob($user);

		if (empty($job)) {
			throw new OCSException('No user migration operation to cancel');
		}

		// TODO merge export and import entities?
		if ($job->getStatus() === UserExport::STATUS_STARTED) {
			throw new OCSException('Cannot cancel a user migration operation that is in progress');
		}

		try {
			$this->migrationService->cancelJob($job);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error cancelling user migration operation');
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function export(array $migrators): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSException('No user currently logged in');
		}

		/** @var string[] $availableMigrators */
		$availableMigrators = array_map(
			fn (IMigrator $migrator) => $migrator->getId(),
			$this->migrationService->getMigrators(),
		);

		foreach ($migrators as $migrator) {
			if (!in_array($migrator, $availableMigrators, true)) {
				throw new OCSException("Requested migrator \"$migrator\" not available");
			}
		}

		$job = $this->migrationService->getCurrentJob($user);
		if (!empty($job)) {
			throw new OCSException('User migration operation already queued');
		}

		try {
			$this->migrationService->queueExport($user, $migrators);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error queueing export');
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function import(string $path, string $targetUserId): DataResponse {
		$author = $this->userSession->getUser();

		if (empty($author)) {
			throw new OCSException('No user currently logged in');
		}

		$targetUser = $this->userManager->get($targetUserId);
		if (empty($targetUser)) {
			throw new OCSException('Target user does not exist');
		}

		// Importing into another user's account is not allowed for now
		if ($author->getUID() !== $targetUser->getUID()) {
			throw new OCSException('Users may only import into their own account');
		}

		$job = $this->migrationService->getCurrentJob($targetUser);
		if (!empty($job)) {
			throw new OCSException('User migration operation already queued');
		}

		try {
			$this->migrationService->queueImport($author, $targetUser, $path);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error queueing import');
		}

		return new DataResponse([], Http::STATUS_OK);
	}
}
