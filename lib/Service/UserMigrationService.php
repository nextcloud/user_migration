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

use OCA\UserMigration\Exception\UserMigrationException;
use OCA\UserMigration\ExportDestination;
use OCA\UserMigration\ImportSource;
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OC\AppFramework\Bootstrap\Coordinator;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class UserMigrationService {
	use TMigratorBasicVersionHandling;

	protected IRootFolder $root;

	protected IConfig $config;

	protected IAccountManager $accountManager;

	protected ITempManager $tempManager;

	protected IUserManager $userManager;

	protected ContainerInterface $container;

	// Allow use of the private Coordinator class here to get and run registered migrators
	protected Coordinator $coordinator;

	public function __construct(
		IRootFolder $rootFolder,
		IConfig $config,
		IAccountManager $accountManager,
		ITempManager $tempManager,
		IUserManager $userManager,
		ContainerInterface $container,
		Coordinator $coordinator
	) {
		$this->root = $rootFolder;
		$this->config = $config;
		$this->accountManager = $accountManager;
		$this->tempManager = $tempManager;
		$this->userManager = $userManager;
		$this->container = $container;
		$this->coordinator = $coordinator;

		$this->mandatory = true;
	}

	/**
	 * @throws UserMigrationException
	 * @return string path of the export
	 */
	public function export(IUser $user, ?OutputInterface $output = null): string {
		$output = $output ?? new NullOutput();
		$uid = $user->getUID();

		$context = $this->coordinator->getRegistrationContext();

		if ($context === null) {
			throw new UserMigrationException("Failed to get context");
		}

		$exportDestination = new ExportDestination($this->tempManager, $uid);

		$this->exportUserInformation(
			$user,
			$exportDestination,
			$output
		);

		$this->exportAccountInformation(
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
			static::class => $this->getVersion(),
		];
		foreach ($context->getUserMigrators() as $migratorRegistration) {
			/** @var IMigrator $migrator */
			$migrator = $this->container->get($migratorRegistration->getService());
			$migrator->export($user, $exportDestination, $output);
			$migratorVersions[get_class($migrator)] = $migrator->getVersion();
		}
		if ($exportDestination->setMigratorVersions($migratorVersions) === false) {
			throw new UserMigrationException("Could not export user information.");
		}

		$exportDestination->close();
		$output->writeln("Export saved in ".$exportDestination->getPath());
		return $exportDestination->getPath();
	}

	public function import(string $path, ?OutputInterface $output = null): void {
		$output = $output ?? new NullOutput();

		$output->writeln("Importing from ${path}…");
		$importSource = new ImportSource($path);

		$context = $this->coordinator->getRegistrationContext();

		try {
			if ($context === null) {
				throw new UserMigrationException("Failed to get context");
			}
			$migratorVersions = $importSource->getMigratorVersions();

			if (!$this->canImport($importSource)) {
				throw new UserMigrationException("Version ${$migratorVersions[static::class]} for main class ".static::class." is not compatible");
			}

			// Check versions
			foreach ($context->getUserMigrators() as $migratorRegistration) {
				/** @var IMigrator $migrator */
				$migrator = $this->container->get($migratorRegistration->getService());
				if (!$migrator->canImport($importSource)) {
					throw new UserMigrationException("Version ".($importSource->getMigratorVersion(get_class($migrator)) ?? 'null')." for migrator ".get_class($migrator)." is not supported");
				}
			}

			$user = $this->importUser($importSource, $output);
			$this->importAccountInformation($user, $importSource, $output);
			$this->importAppsSettings($user, $importSource, $output);

			// Run imports of registered migrators
			foreach ($context->getUserMigrators() as $migratorRegistration) {
				/** @var IMigrator $migrator */
				$migrator = $this->container->get($migratorRegistration->getService());
				$migrator->import($user, $importSource, $output);
			}

			$uid = $user->getUID();
			$output->writeln("Successfully imported $uid from $path");
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
		$output->writeln("Exporting user information in user.json…");

		// TODO store backend? email? cloud id? quota?
		$userinfo = [
			'uid' => $user->getUID(),
			'displayName' => $user->getDisplayName(),
			'lastLogin' => $user->getLastLogin(),
			'enabled' => $user->isEnabled(),
		];

		if ($exportDestination->addFileContents("user.json", json_encode($userinfo)) === false) {
			throw new UserMigrationException("Could not export user information.");
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function importUser(IImportSource $importSource,
									OutputInterface $output): IUser {
		$output->writeln("Importing user information from user.json…");

		$data = json_decode($importSource->getFileContents("user.json"), true, 512, JSON_THROW_ON_ERROR);

		$user = $this->userManager->createUser($data['uid'], \OC::$server->getSecureRandom()->generate(10, ISecureRandom::CHAR_ALPHANUMERIC));

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
	protected function exportAccountInformation(IUser $user,
									 IExportDestination $exportDestination,
									 OutputInterface $output): void {
		$output->writeln("Exporting account information in account.json…");

		if ($exportDestination->addFileContents("account.json", json_encode($this->accountManager->getAccount($user))) === false) {
			throw new UserMigrationException("Could not export account information.");
		}
	}

	/**
	 * @throws UserMigrationException
	 */
	protected function importAccountInformation(IUser $user,
									 IImportSource $importSource,
									 OutputInterface $output): void {
		$output->writeln("Importing account information from account.json…?");

		// TODO Import account information
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

		if ($exportDestination->addFileContents("versions.json", json_encode($versions)) === false) {
			throw new UserMigrationException("Could not export versions.");
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

		if ($exportDestination->addFileContents("settings.json", json_encode($data)) === false) {
			throw new UserMigrationException("Could not export settings.");
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
}
