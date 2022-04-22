<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 * @author Côme Chilliet <come.chilliet@nextcloud.com>
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

namespace OCA\UserMigration\Service;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OCP\UserMigration\UserMigrationException;
use OC\AppFramework\Bootstrap\Coordinator;
use OCA\UserMigration\Db\UserExport;
use OCA\UserMigration\Db\UserExportMapper;
use OCA\UserMigration\Db\UserImport;
use OCA\UserMigration\Db\UserImportMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class UserMigrationService {
	use TMigratorBasicVersionHandling;

	protected IRootFolder $root;

	protected IConfig $config;

	protected IUserManager $userManager;

	protected ContainerInterface $container;

	// Allow use of the private Coordinator class here to get and run registered migrators
	protected Coordinator $coordinator;

	protected UserExportMapper $exportMapper;

	protected UserImportMapper $importMapper;

	public function __construct(
		IRootFolder $rootFolder,
		IConfig $config,
		IUserManager $userManager,
		ContainerInterface $container,
		Coordinator $coordinator,
		UserExportMapper $exportMapper,
		UserImportMapper $importMapper
	) {
		$this->root = $rootFolder;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->container = $container;
		$this->coordinator = $coordinator;
		$this->exportMapper = $exportMapper;
		$this->importMapper = $importMapper;

		$this->mandatory = true;
	}

	/**
	 * @param ?string[] $filteredMigratorList If not null, only these migrators will run. If empty only the main account data will be exported.
	 * @throws UserMigrationException
	 */
	public function export(IExportDestination $exportDestination, IUser $user, ?array $filteredMigratorList = null, ?OutputInterface $output = null): void {
		$output = $output ?? new NullOutput();
		$uid = $user->getUID();

		$this->exportUserInformation(
			$user,
			$exportDestination,
			$output
		);

		$this->exportAppsSettings(
			$uid,
			$exportDestination,
			$output
		);

		$this->exportVersions(
			$uid,
			$exportDestination,
			$output
		);

		// Run exports of registered migrators
		$migratorVersions = [
			$this->getId() => $this->getVersion(),
		];
		foreach ($this->getMigrators() as $migrator) {
			if ($filteredMigratorList !== null && !in_array($migrator->getId(), $filteredMigratorList)) {
				$output->writeln("Skip non-selected migrator: ".$migrator->getId(), OutputInterface::VERBOSITY_VERBOSE);
				continue;
			}
			$migrator->export($user, $exportDestination, $output);
			$migratorVersions[$migrator->getId()] = $migrator->getVersion();
		}
		try {
			$exportDestination->setMigratorVersions($migratorVersions);
		} catch (Throwable $e) {
			throw new UserMigrationException("Could not export user information.", 0, $e);
		}

		$exportDestination->close();
	}

	public function import(IImportSource $importSource, ?IUser $user = null, ?OutputInterface $output = null): void {
		$output = $output ?? new NullOutput();

		try {
			$migratorVersions = $importSource->getMigratorVersions();

			if (!$this->canImport($importSource)) {
				throw new UserMigrationException("Version ${$migratorVersions[$this->getId()]} for main class ".static::class." is not compatible");
			}

			// Check versions
			foreach ($this->getMigrators() as $migrator) {
				if (!$migrator->canImport($importSource)) {
					throw new UserMigrationException("Version ".($importSource->getMigratorVersion($migrator->getId()) ?? 'null')." for migrator ".get_class($migrator)." is not supported");
				}
			}

			$user = $this->importUser($user, $importSource, $output);
			$this->importAppsSettings($user, $importSource, $output);

			// Run imports of registered migrators
			foreach ($this->getMigrators() as $migrator) {
				$migrator->import($user, $importSource, $output);
			}

			$uid = $user->getUID();
			$output->writeln("Successfully imported $uid");
		} finally {
			$importSource->close();
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function exportUserInformation(IUser $user,
									 IExportDestination $exportDestination,
									 OutputInterface $output): void {
		$output->writeln("Exporting user information in ".IImportSource::PATH_USER."…");

		// TODO store backend? email? cloud id? quota?
		$userinfo = [
			'uid' => $user->getUID(),
			'displayName' => $user->getDisplayName(),
			'lastLogin' => $user->getLastLogin(),
			'enabled' => $user->isEnabled(),
		];

		try {
			$exportDestination->addFileContents(IImportSource::PATH_USER, json_encode($userinfo));
		} catch (Throwable $e) {
			throw new UserMigrationException("Could not export user information.", 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function importUser(?IUser $user,
								  IImportSource $importSource,
									OutputInterface $output): IUser {
		$output->writeln("Importing user information from ".IImportSource::PATH_USER."…");

		$data = json_decode($importSource->getFileContents(IImportSource::PATH_USER), true, 512, JSON_THROW_ON_ERROR);

		if ($user === null) {
			$user = $this->userManager->createUser(
				$data['uid'],
				\OC::$server->getSecureRandom()->generate(10, ISecureRandom::CHAR_ALPHANUMERIC)
			);
		}

		if (!($user instanceof IUser)) {
			throw new UserMigrationException("Failed to create user.");
		}

		$user->setEnabled($data['enabled']);
		$user->setDisplayName($data['displayName']);

		return $user;
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function exportVersions(string $uid,
									 IExportDestination $exportDestination,
									 OutputInterface $output): void {
		$output->writeln("Exporting versions in versions.json…");

		$versions = array_merge(
			['core' => $this->config->getSystemValue('version')],
			\OC_App::getAppVersions()
		);

		try {
			$exportDestination->addFileContents("versions.json", json_encode($versions));
		} catch (Throwable $e) {
			throw new UserMigrationException("Could not export versions.", 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function exportAppsSettings(string $uid,
									 IExportDestination $exportDestination,
									 OutputInterface $output): void {
		$output->writeln("Exporting settings in settings.json…");

		$data = $this->config->getAllUserValues($uid);

		try {
			$exportDestination->addFileContents("settings.json", json_encode($data));
		} catch (Throwable $e) {
			throw new UserMigrationException("Could not export settings.", 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function importAppsSettings(IUser $user,
									 IImportSource $importSource,
									 OutputInterface $output): void {
		$output->writeln("Importing settings from settings.json…");

		$data = json_decode($importSource->getFileContents("settings.json"), true, 512, JSON_THROW_ON_ERROR);
		foreach ($data as $app => $values) {
			foreach ($values as $key => $value) {
				$this->config->setUserValue($user->getUID(), $app, $key, $value);
			}
		}
	}

	/**
	 * @return IMigrator[]
	 *
	 * @throws UserMigrationException
	 */
	public function getMigrators(): array {
		$context = $this->coordinator->getRegistrationContext();
		if ($context === null) {
			throw new UserMigrationException('Failed to get context');
		}

		/** @var IMigrator[] $migrators */
		$migrators = [];
		foreach ($context->getUserMigrators() as $migratorRegistration) {
			/** @var IMigrator $migrator */
			$migrator = $this->container->get($migratorRegistration->getService());
			$migrators[] = $migrator;
		}

		return $migrators;
	}

	/**
	 * @return null|UserExport|UserImport
	 *
	 * @throws UserMigrationException
	 */
	public function getCurrentJob(IUser $user) {
		// TODO merge export and import entities?

		try {
			$exportJob = $this->exportMapper->getBySourceUser($user->getUID());
		} catch (DoesNotExistException $e) {
			// Allow this exception as this just means the user has no export jobs queued currently
		}

		try {
			$importJob = $this->importMapper->getByTargetUser($user->getUID());
		} catch (DoesNotExistException $e) {
			// Allow this exception as this just means the user has no import jobs queued currently
		}

		if (!empty($exportJob) && !empty($importJob)) {
			throw new UserMigrationException('A user export and import job cannot be queued or run at the same time');
		}

		$job = $exportJob ?? $importJob;

		if (empty($job)) {
			return null;
		}

		return $job;
	}

	/**
	 * @return array{current: ?string, migrators?: string[], status?: string}
	 *
	 * @throws UserMigrationException
	 */
	public function getCurrentJobData(IUser $user): array {
		$job = $this->getCurrentJob($user);

		if (empty($job)) {
			return ['current' => null];
		}

		switch (true) {
			case $job instanceof UserExport:
				$type = 'export';
				break;
			case $job instanceof UserImport:
				$type = 'import';
				break;
			default:
				throw new UserMigrationException('Class must be one of \OCA\UserMigration\Db\UserExport or \OCA\UserMigration\Db\UserImport');
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
	 * @param UserExport|UserImport $job
	 *
	 * @throws UserMigrationException
	 */
	public function cancelJob($job): void {
		switch (true) {
			case $job instanceof UserExport:
				try {
					$this->jobList->remove(UserExportJob::class, [
						'id' => $job->getId(),
					]);
					$this->exportMapper->delete($job);
				} catch (Throwable $e) {
					throw new UserMigrationException('Error cancelling export job', 0, $e);
				}
			case $job instanceof UserImport:
				try {
					$this->jobList->remove(UserImportJob::class, [
						'id' => $job->getId(),
					]);
					$this->importMapper->delete($job);
				} catch (Throwable $e) {
					throw new UserMigrationException('Error cancelling import job', 0, $e);
				}
			default:
				throw new UserMigrationException('Error cancelling user migration job');
		}
	}

	/**
	 * Returns the unique ID
	 */
	public function getId(): string {
		return 'usermigrationservice';
	}
}
