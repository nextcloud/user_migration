<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		UserMigrationService $migrationService,
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
	private function checkJobAndGetUser(bool $throwOnJobQueued = true): IUser {
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
			if ($throwOnJobQueued) {
				throw new OCSException('User migration operation already queued');
			}
		}

		return $user;
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * @throws OCSException
	 */
	public function exportable(?array $migrators): DataResponse {
		$user = $this->checkJobAndGetUser(false);

		if (!is_null($migrators)) {
			if (count($migrators) === 1 && reset($migrators) === '') {
				$migrators = [];
			}

			$this->checkMigrators($migrators);
		}

		try {
			$size = $this->migrationService->estimateExportSize($user, $migrators);
			// Round and convert to MiB
			$roundedSize = max(1, round($size / 1024));
		} catch (UserMigrationException $e) {
			throw new OCSException($e->getMessage());
		}

		try {
			$this->migrationService->checkExportability($user, $migrators);
		} catch (NotExportableException $e) {
			$warning = $e->getMessage();
		}

		return new DataResponse([
			'estimatedSize' => $roundedSize,
			'units' => 'MiB',
			'warning' => $warning ?? null,
		], Http::STATUS_OK);
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
