<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration\Service;

use OC\AppFramework\Bootstrap\Coordinator;
use OCA\UserMigration\BackgroundJob\UserExportJob;
use OCA\UserMigration\BackgroundJob\UserImportJob;
use OCA\UserMigration\Db\UserExport;
use OCA\UserMigration\Db\UserExportMapper;
use OCA\UserMigration\Db\UserImport;
use OCA\UserMigration\Db\UserImportMapper;
use OCA\UserMigration\ExportDestination;
use OCA\UserMigration\NotExportableException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\Cache\CappedMemoryCache;
use OCP\Config\Exceptions\UnknownKeyException;
use OCP\Config\IUserConfig;
use OCP\Config\ValueType;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OCP\UserMigration\UserMigrationException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class UserMigrationService {
	use TMigratorBasicVersionHandling;

	protected CappedMemoryCache $internalCache;

	protected const ENTITY_JOB_MAP = [
		UserExport::class => UserExportJob::class,
		UserImport::class => UserImportJob::class,
	];

	// Allow use of the private Coordinator class here to get and run registered migrators
	public function __construct(
		private IRootFolder $rootFolder,
		private IConfig $config,
		private IUserManager $userManager,
		private ContainerInterface $container,
		private Coordinator $coordinator,
		private UserExportMapper $exportMapper,
		private UserImportMapper $importMapper,
		private IJobList $jobList,
		private IAppConfig $appConfig,
	) {
		$this->internalCache = new CappedMemoryCache();

		$this->mandatory = true;
	}

	/**
	 * @param ?string[] $filteredMigratorList If not null, only these migrators will run. If empty only the main account data will be exported.
	 *
	 * @return int|float Estimated size in KiB
	 *
	 * @throws UserMigrationException
	 */
	public function estimateExportSize(IUser $user, ?array $filteredMigratorList = null): int|float {
		// 1MiB for base user data
		$size = 1024;

		foreach ($this->getMigrators() as $migrator) {
			if ($filteredMigratorList !== null && !in_array($migrator->getId(), $filteredMigratorList)) {
				continue;
			}
			$cacheKey = $user->getUID() . '::' . $migrator->getId();
			if ($this->internalCache->hasKey($cacheKey)) {
				/** @var int|float $size */
				$size += $this->internalCache->get($cacheKey);
				continue;
			}
			if ($migrator instanceof ISizeEstimationMigrator) {
				try {
					$migratorSize = $migrator->getEstimatedExportSize($user);
				} catch (Throwable $e) {
					throw new UserMigrationException('Could not estimate export size for ' . $migrator->getDisplayName(), 0, $e);
				}
				$this->internalCache->set($cacheKey, $migratorSize);
				/** @var int|float $size */
				$size += $migratorSize;
			}
		}

		return $size;
	}

	/**
	 * @param ?string[] $filteredMigratorList If not null, only these migrators will run. If empty only the main account data will be exported.
	 *
	 * @throws NotExportableException
	 */
	public function checkExportability(IUser $user, ?array $filteredMigratorList = null): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			$freeSpace = ceil($userFolder->getFreeSpace() / 1024);
		} catch (Throwable $e) {
			throw new NotExportableException('Error calculating amount of free storage space available');
		}

		try {
			$exportFile = $userFolder->get(ExportDestination::EXPORT_FILENAME);
			if (!($exportFile instanceof File)) {
				throw new \InvalidArgumentException('User export is not a file');
			}
			// Add previous export file size to free space as it will be overwritten if existing
			$freeSpace += $exportFile->getSize() / 1024;
		} catch (NotFoundException $e) {
			// No size addition needed if export file doesn't exist
		}

		try {
			$exportSize = $this->estimateExportSize($user, $filteredMigratorList);
		} catch (UserMigrationException $e) {
			throw new NotExportableException('Error estimating export size');
		}

		$freeSpaceAfterExport = ($freeSpace - $exportSize) / 1024;
		if ($freeSpaceAfterExport < 0) {
			throw new NotExportableException('Insufficient storage space available, please free up ' . abs($freeSpaceAfterExport) . ' MiB or more to be able to export your data without running out of space');
		}
	}

	/**
	 * @param ?string[] $filteredMigratorList If not null, only these migrators will run. If empty only the main account data will be exported.
	 *
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
				$output->writeln('Skip non-selected migrator: ' . $migrator->getId(), OutputInterface::VERBOSITY_VERBOSE);
				continue;
			}
			$migrator->export($user, $exportDestination, $output);
			$migratorVersions[$migrator->getId()] = $migrator->getVersion();
		}
		try {
			$exportDestination->setMigratorVersions($migratorVersions);
		} catch (Throwable $e) {
			throw new UserMigrationException('Could not export user information.', 0, $e);
		}

		$exportDestination->close();
	}

	public function import(IImportSource $importSource, ?IUser $user = null, ?OutputInterface $output = null): void {
		$output = $output ?? new NullOutput();

		try {
			if (!$this->canImport($importSource)) {
				throw new UserMigrationException('Version ' . ($importSource->getMigratorVersion($this->getId()) ?? 'null') . ' for main class ' . static::class . ' is not compatible');
			}

			// Check versions
			foreach ($this->getMigrators() as $migrator) {
				if (!$migrator->canImport($importSource)) {
					throw new UserMigrationException('Version ' . ($importSource->getMigratorVersion($migrator->getId()) ?? 'null') . ' for migrator ' . get_class($migrator) . ' is not supported');
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
		$output->writeln('Exporting user information in ' . IImportSource::PATH_USER . '…');

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
			throw new UserMigrationException('Could not export user information.', 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function importUser(?IUser $user,
		IImportSource $importSource,
		OutputInterface $output): IUser {
		$output->writeln('Importing user information from ' . IImportSource::PATH_USER . '…');

		$data = json_decode($importSource->getFileContents(IImportSource::PATH_USER), true, 512, JSON_THROW_ON_ERROR);

		if ($user === null) {
			$user = $this->userManager->createUser(
				$data['uid'],
				\OC::$server->getSecureRandom()->generate(10, ISecureRandom::CHAR_ALPHANUMERIC)
			);
		}

		if (!($user instanceof IUser)) {
			throw new UserMigrationException('Failed to create user.');
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
		$output->writeln('Exporting versions in versions.json…');

		$versions = array_merge(
			['core' => $this->config->getSystemValue('version')],
			// TODO: When dropping support for Nextcloud <32, switch to $this->appConfig->getAppInstalledVersions()
			\OC_App::getAppVersions()
		);

		try {
			$exportDestination->addFileContents('versions.json', json_encode($versions));
		} catch (Throwable $e) {
			throw new UserMigrationException('Could not export versions.', 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function exportAppsSettings(string $uid,
		IExportDestination $exportDestination,
		OutputInterface $output): void {
		$output->writeln('Exporting settings in settings.json…');

		$data = $this->config->getAllUserValues($uid);

		try {
			$exportDestination->addFileContents('settings.json', json_encode($data));
		} catch (Throwable $e) {
			throw new UserMigrationException('Could not export settings.', 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function importAppsSettings(IUser $user,
		IImportSource $importSource,
		OutputInterface $output): void {
		$output->writeln('Importing settings from settings.json…');

		$data = json_decode($importSource->getFileContents('settings.json'), true, 512, JSON_THROW_ON_ERROR);

		if (interface_exists(IUserConfig::class)) {
			/*
			 * Starting with 32, we have to use the correct type
			 * When dropping support for <32, IUserConfig should be injected instead
			 */
			$userConfig = \OCP\Server::get(IUserConfig::class);
			$userId = $user->getUID();
			foreach ($data as $app => $values) {
				foreach ($values as $key => $value) {
					try {
						$type = $userConfig->getValueType($userId, $app, $key);
					} catch (UnknownKeyException) {
						/** If type is unknown, default to mixed */
						$type = ValueType::MIXED;
					}
					/** @psalm-suppress UndefinedClass ValueType only exists in 32 and higher, but in this if branch we know it exists */
					switch ($type) {
						default:
						case ValueType::MIXED:
							$this->config->setUserValue($userId, $app, $key, $value);
							break;
						case ValueType::STRING:
							$userConfig->setValueString($userId, $app, $key, $value);
							break;
						case ValueType::INT:
							$userConfig->setValueInt($userId, $app, $key, (int)$value);
							break;
						case ValueType::FLOAT:
							$userConfig->setValueFloat($userId, $app, $key, (float)$value);
							break;
						case ValueType::BOOL:
							if (is_string($value)) {
								$value = in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
							} elseif (!is_bool($value)) {
								$value = (bool)$value;
							}
							$userConfig->setValueBool($userId, $app, $key, $value);
							break;
						case ValueType::ARRAY:
							$userConfig->setValueArray($userId, $app, $key, $value);
							break;
					}
				}
			}
		} else {
			foreach ($data as $app => $values) {
				foreach ($values as $key => $value) {
					$this->config->setUserValue($user->getUID(), $app, $key, $value);
				}
			}
		}
	}

	/**
	 * @param UserExport|UserImport $job
	 *
	 * @throws UserMigrationException
	 */
	protected function deleteMapperJob($job): void {
		switch (true) {
			case $job instanceof UserExport:
				try {
					$this->exportMapper->delete($job);
				} catch (Throwable $e) {
					throw new UserMigrationException('Error deleting export job', 0, $e);
				}
				break;
			case $job instanceof UserImport:
				try {
					$this->importMapper->delete($job);
				} catch (Throwable $e) {
					throw new UserMigrationException('Error deleting import job', 0, $e);
				}
				break;
			default:
				throw new UserMigrationException('Error deleting user migration job');
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	public function queueExportJob(IUser $user, array $migrators): void {
		try {
			$userExport = new UserExport();
			$userExport->setSourceUser($user->getUID());
			$userExport->setMigratorsArray($migrators);
			$userExport->setStatus(UserExport::STATUS_WAITING);
			/** @var UserExport $userExport */
			$userExport = $this->exportMapper->insert($userExport);

			$this->jobList->add(UserExportJob::class, [
				'id' => $userExport->getId(),
			]);
		} catch (Throwable $e) {
			throw new UserMigrationException('Error queueing export job', 0, $e);
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	public function queueImportJob(IUser $author, IUser $targetUser, string $path): void {
		/** @var string[] $availableMigrators */
		$availableMigrators = array_map(
			fn (IMigrator $migrator) => $migrator->getId(),
			$this->getMigrators(),
		);

		try {
			$userImport = new UserImport();
			$userImport->setAuthor($author->getUID());
			$userImport->setTargetUser($targetUser->getUID());
			// Path is relative to the author folder
			$userImport->setPath($path);
			// All available migrators are added as migrator selection for import is not allowed for now
			$userImport->setMigratorsArray($availableMigrators);
			$userImport->setStatus(UserImport::STATUS_WAITING);
			/** @var UserImport $userImport */
			$userImport = $this->importMapper->insert($userImport);

			$this->jobList->add(UserImportJob::class, [
				'id' => $userImport->getId(),
			]);
		} catch (Throwable $e) {
			throw new UserMigrationException('Error queueing import job', 0, $e);
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

		$job = $exportJob ?? $importJob ?? null;

		if (empty($job)) {
			return null;
		}

		if (!$this->jobList->has(static::ENTITY_JOB_MAP[get_class($job)], ['id' => $job->getId()])) {
			$this->deleteMapperJob($job);
			throw new UserMigrationException('Expected "' . get_class($job) . '" job with id argument "' . $job->getId() . '" in `oc_jobs` database table');
		}

		return $job;
	}

	/**
	 * @param UserExport|UserImport $job
	 *
	 * @throws UserMigrationException
	 */
	public function cancelJob($job): void {
		if (!$this->jobList->has(static::ENTITY_JOB_MAP[get_class($job)], ['id' => $job->getId()])) {
			$this->deleteMapperJob($job);
			throw new UserMigrationException('Expected "' . get_class($job) . '" job with id argument "' . $job->getId() . '" in `oc_jobs` database table');
		}

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
				break;
			case $job instanceof UserImport:
				try {
					$this->jobList->remove(UserImportJob::class, [
						'id' => $job->getId(),
					]);
					$this->importMapper->delete($job);
				} catch (Throwable $e) {
					throw new UserMigrationException('Error cancelling import job', 0, $e);
				}
				break;
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
