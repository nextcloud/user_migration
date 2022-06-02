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
use OCA\UserMigration\Db\UserImport;
use OCA\UserMigration\NotExportableException;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\UserMigrationException;

class ApiController extends OCSController {
	private IUserSession $userSession;

	private UserMigrationService $migrationService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		UserMigrationService $migrationService
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userSession = $userSession;
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
	 * @return ?array{current: string, migrators: ?string[], status: string}
	 *
	 * @throws OCSException
	 */
	private function getCurrentJobData(IUser $user): ?array {
		try {
			$job = $this->migrationService->getCurrentJob($user);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error getting current user migration operation');
		}

		if (empty($job)) {
			return null;
		}

		switch (true) {
			case $job instanceof UserExport:
				$type = 'export';
				break;
			case $job instanceof UserImport:
				$type = 'import';
				break;
			default:
				throw new OCSException('Error getting current user migration operation');
		}

		$statusMap = [
			// TODO merge export and import entities?
			UserExport::STATUS_WAITING => 'waiting',
			UserExport::STATUS_STARTED => 'started',
		];

		return [
			'current' => $type,
			'migrators' => $job->getMigratorsArray(),
			'status' => $statusMap[$job->getStatus()],
		];
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * @throws OCSException
	 */
	public function status(): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSException('No user currently logged in');
		}

		return new DataResponse(
			$this->getCurrentJobData($user) ?? ['current' => null],
			Http::STATUS_OK,
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 *
	 * @throws OCSException
	 */
	public function cancel(): DataResponse {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSException('No user currently logged in');
		}

		try {
			$job = $this->migrationService->getCurrentJob($user);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error getting current user migration operation');
		}

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
	 * @throws OCSException
	 */
	private function checkMigrators(array $migrators): void {
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
	}

	/**
	 * @throws OCSException
	 */
	private function checkJobAndGetUser(): IUser {
		$user = $this->userSession->getUser();

		if (empty($user)) {
			throw new OCSException('No user currently logged in');
		}

		try {
			$job = $this->migrationService->getCurrentJob($user);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error getting current user migration operation');
		}

		if (!empty($job)) {
			throw new OCSException('User migration operation already queued');
		}

		return $user;
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * @throws OCSException
	 */
	public function estimate(?array $migrators): DataResponse {
		$user = $this->checkJobAndGetUser();

		if (!is_null($migrators)) {
			$this->checkMigrators($migrators);
		}

		try {
			$size = $this->migrationService->estimateExportSize($user, $migrators);
		} catch (UserMigrationException $e) {
			throw new OCSException($e->getMessage());
		}

		return new DataResponse(['size' => $size], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * @throws OCSException
	 */
	public function exportable(?array $migrators): DataResponse {
		$user = $this->checkJobAndGetUser();

		if (!is_null($migrators)) {
			$this->checkMigrators($migrators);
		}

		try {
			$this->migrationService->checkExportability($user, $migrators);
		} catch (NotExportableException $e) {
			throw new OCSException($e->getMessage());
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 *
	 * @throws OCSException
	 */
	public function export(array $migrators): DataResponse {
		$user = $this->checkJobAndGetUser();
		$this->checkMigrators($migrators);

		try {
			$this->migrationService->queueExportJob($user, $migrators);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error queueing export');
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 *
	 * @throws OCSException
	 */
	public function import(string $path): DataResponse {
		$author = $this->checkJobAndGetUser();
		// Set target user to the author as importing into another user's account is not allowed for now
		$targetUser = $author;

		try {
			$this->migrationService->queueImportJob($author, $targetUser, $path);
		} catch (UserMigrationException $e) {
			throw new OCSException('Error queueing import');
		}

		return new DataResponse([], Http::STATUS_OK);
	}
}
