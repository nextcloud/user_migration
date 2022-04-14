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
use OCA\UserMigration\BackgroundJob\UserExportJob;
use OCA\UserMigration\BackgroundJob\UserImportJob;
use OCA\UserMigration\Db\UserExport;
use OCA\UserMigration\Db\UserExportMapper;
use OCA\UserMigration\Db\UserImport;
use OCA\UserMigration\Db\UserImportMapper;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\UserMigration\IMigrator;

class ApiController extends OCSController {
	private IUserSession $userSession;

	private IUserManager $userManager;

	private UserMigrationService $migrationService;

	private UserExportMapper $exportMapper;

	private UserImportMapper $importMapper;

	private IJobList $jobList;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		UserMigrationService $migrationService,
		UserExportMapper $exportMapper,
		UserImportMapper $importMapper,
		IJobList $jobList
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
		$this->exportMapper = $exportMapper;
		$this->importMapper = $importMapper;
		$this->jobList = $jobList;
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

		try {
			$userExport = $this->exportMapper->getBySourceUser($user->getUID());
		} catch (DoesNotExistException $e) {
			// Allow this exception as this just means the user has no export jobs queued currently
		}

		$exportStatusMap = [
			UserExport::STATUS_WAITING => 'waiting',
			UserExport::STATUS_STARTED => 'started',
		];

		if (!empty($userExport)) {
			return new DataResponse([
				'current' => 'export',
				'migrators' => $userExport->getMigratorsArray(),
				'status' => $exportStatusMap[$userExport->getStatus()],
			], Http::STATUS_OK);
		}

		try {
			$userImport = $this->importMapper->getBySourceUser($user->getUID());
		} catch (DoesNotExistException $e) {
			// Allow this exception as this just means the user has no import jobs queued currently
		}

		$importStatusMap = [
			UserImport::STATUS_WAITING => 'waiting',
			UserImport::STATUS_STARTED => 'started',
		];

		if (!empty($userImport)) {
			return new DataResponse([
				'current' => 'import',
				'migrators' => $userImport->getMigratorsArray(),
				'status' => $importStatusMap[$userImport->getStatus()],
			], Http::STATUS_OK);
		}

		return new DataResponse(['current' => null], Http::STATUS_OK);
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

		try {
			$userExport = $this->exportMapper->getBySourceUser($user->getUID());
			throw new OCSException('User export already queued');
		} catch (DoesNotExistException $e) {
			// Allow this exception to proceed with adding user export job
		}

		$userExport = new UserExport();
		$userExport->setSourceUser($user->getUID());
		$userExport->setMigratorsArray($migrators);
		$userExport->setStatus(UserExport::STATUS_WAITING);
		/** @var UserExport $userExport */
		$userExport = $this->exportMapper->insert($userExport);

		$this->jobList->add(UserExportJob::class, [
			'id' => $userExport->getId(),
		]);

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 */
	public function import(string $path, string $targetUserId): DataResponse {
		$sourceUser = $this->userSession->getUser();

		if (empty($sourceUser)) {
			throw new OCSException('No user currently logged in');
		}

		$targetUser = $this->userManager->get($targetUserId);
		if (empty($targetUser)) {
			throw new OCSException('Target user does not exist');
		}

		// Importing into another user's account is not allowed for now
		if ($sourceUser->getUID() !== $targetUser->getUID()) {
			throw new OCSException('Users may only import into their own account');
		}

		/** @var string[] $availableMigrators */
		$availableMigrators = array_map(
			fn (IMigrator $migrator) => $migrator->getId(),
			$this->migrationService->getMigrators(),
		);

		try {
			$userImport = $this->importMapper->getBySourceUser($sourceUser->getUID());
			throw new OCSException('User import already queued');
		} catch (DoesNotExistException $e) {
			// Allow this exception to proceed with adding user import job
		}

		$userImport = new UserImport();
		$userImport->setSourceUser($sourceUser->getUID());
		$userImport->setTargetUser($targetUser->getUID());
		// Path is relative to the source user folder
		$userImport->setPath($path);
		// All available migrators are added as migrator selection for import is not allowed for now
		$userImport->setMigratorsArray($availableMigrators);
		$userImport->setStatus(UserImport::STATUS_WAITING);
		/** @var UserImport $userImport */
		$userImport = $this->importMapper->insert($userImport);

		$this->jobList->add(UserImportJob::class, [
			'id' => $userImport->getId(),
		]);

		return new DataResponse([], Http::STATUS_OK);
	}
}
