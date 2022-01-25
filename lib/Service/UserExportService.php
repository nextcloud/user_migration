<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
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

use OCA\UserMigration\Exception\UserExportException;
use OCA\UserMigration\ExportDestination;
use OCA\UserMigration\IExportDestination;
use OCA\UserMigration\IImportSource;
use OCA\UserMigration\ImportSource;
use OC\Files\AppData;
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class UserExportService {
	protected IAccountManager $accountManager;

	protected ITempManager $tempManager;

	protected IUserManager $userManager;

	protected IConfig $config;

	protected IRootFolder $root;

	protected AppData\Factory $appDataFactory;

	public function __construct(
		IRootFolder $rootFolder,
		IConfig $config,
		IAccountManager $accountManager,
		ITempManager $tempManager,
		IUserManager $userManager,
		AppData\Factory $appDataFactory
	) {
		$this->root = $rootFolder;
		$this->config = $config;
		$this->accountManager = $accountManager;
		$this->tempManager = $tempManager;
		$this->userManager = $userManager;
		$this->appDataFactory = $appDataFactory;
	}

	/**
	 * @throws UserExportException
	 * @throws \OC\User\NoUserException
	 */
	public function export(IUser $user, ?OutputInterface $output = null): void {
		$output = $output ?? new NullOutput();
		$uid = $user->getUID();

		// setup filesystem
		// Requesting the user folder will set it up if the user hasn't logged in before
		\OC::$server->getUserFolder($uid);
		Filesystem::initMountPoints($uid);

		$view = new View();

		$exportDestination = new ExportDestination($this->appDataFactory, $uid);

		// copy the files
		$this->copyFiles(
			$uid,
			$exportDestination,
			$view,
			$output
		);

		$this->exportUserInformation(
			$user,
			$exportDestination,
			$view,
			$output
		);

		$this->exportAccountInformation(
			$user,
			$exportDestination,
			$view,
			$output
		);

		$this->exportAppsSettings(
			$uid,
			$exportDestination,
			$view,
			$output
		);

		$this->exportVersions(
			$uid,
			$exportDestination,
			$view,
			$output
		);

		$exportDestination->close();
		$output->writeln("Export saved in ".$exportDestination->getPath());
	}

	public function import(string $path, ?OutputInterface $output = null): void {
		$output = $output ?? new NullOutput();

		$output->writeln("Importing from ${path}…");
		$importSource = new ImportSource($path);

		try {
			// TODO check versions

			$user = $this->importUser($importSource, $output);
			$this->importAccountInformation($user, $importSource, $output);
			$this->importAppsSettings($user, $importSource, $output);
			$this->importFiles($user, $importSource, $output);
			$uid = $user->getUID();
			$output->writeln("Successfully imported $uid from $path");
		} finally {
			$importSource->close();
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function copyFiles(string $uid,
									 IExportDestination $exportDestination,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Copying files…");

		if ($exportDestination->copyFromView($view, "$uid/files", "files") === false) {
			throw new UserExportException("Could not copy files.");
		}
		// TODO files metadata should be exported as well if relevant. Maybe move this to an export operation
	}

	/**
	 * @throws UserExportException
	 */
	protected function importFiles(IUser $user,
									 IImportSource $importSource,
									 OutputInterface $output): void {
		$output->writeln("Importing files…");

		$uid = $user->getUID();

		// setup filesystem
		// Requesting the user folder will set it up if the user hasn't logged in before
		\OC::$server->getUserFolder($uid);
		Filesystem::initMountPoints($uid);

		$view = new View();

		if ($importSource->copyToView($view, "files", "$uid/files") === false) {
			throw new UserExportException("Could not import files.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function exportUserInformation(IUser $user,
									 IExportDestination $exportDestination,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Exporting user information in user.json…");

		// TODO store backend? email? avatar? cloud id? quota?
		$userinfo = [
			'uid' => $user->getUID(),
			'displayName' => $user->getDisplayName(),
			'lastLogin' => $user->getLastLogin(),
			'home' => $user->getHome(),
			'enabled' => $user->isEnabled(),
		];

		if ($exportDestination->addFile("user.json", json_encode($userinfo)) === false) {
			throw new UserExportException("Could not export user information.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function importUser(IImportSource $importSource,
									OutputInterface $output): IUser {
		$output->writeln("Importing user information from user.json…");

		$data = json_decode($importSource->getFileContents("user.json"), true, 512, JSON_THROW_ON_ERROR);

		$user = $this->userManager->createUser($data['uid'], \OC::$server->getSecureRandom()->generate(10, ISecureRandom::CHAR_ALPHANUMERIC));

		if ($user === false) {
			throw new UserExportException("Failed to create user.");
		}

		$user->setEnabled($data['enabled']);
		$user->setDisplayName($data['displayName']);

		return $user;
	}

	/**
	 * @throws UserExportException
	 */
	protected function exportAccountInformation(IUser $user,
									 IExportDestination $exportDestination,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Exporting account information in account.json…");

		if ($exportDestination->addFile("account.json", json_encode($this->accountManager->getAccount($user))) === false) {
			throw new UserExportException("Could not export account information.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function importAccountInformation(IUser $user,
									 IImportSource $importSource,
									 OutputInterface $output): void {
		$output->writeln("Importing account information from account.json…?");

		// TODO
	}

	/**
	 * @throws UserExportException
	 */
	protected function exportVersions(string $uid,
									 IExportDestination $exportDestination,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Exporting versions in versions.json…");

		$versions = array_merge(
			['core' => $this->config->getSystemValue('version')],
			\OC_App::getAppVersions()
		);

		if ($exportDestination->addFile("versions.json", json_encode($versions)) === false) {
			throw new UserExportException("Could not export versions.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function exportAppsSettings(string $uid,
									 IExportDestination $exportDestination,
									 View $view,
									 OutputInterface $output): void {
		// TODO settings from core and some special fake appids like login/avatar are not exported
		$output->writeln("Exporting settings in settings.json…");
		$data = [];

		$apps = \OC_App::getEnabledApps(false, true);

		foreach ($apps as $app) {
			$keys = $this->config->getUserKeys($uid, $app);
			foreach ($keys as $key) {
				$data[$app][$key] = $this->config->getUserValue($uid, $app, $key);
			}
		}

		if ($exportDestination->addFile("settings.json", json_encode($data)) === false) {
			throw new UserExportException("Could not export settings.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function importAppsSettings(IUser $user,
									 IImportSource $importSource,
									 OutputInterface $output): void {
		$output->writeln("Importing settings from settings.json…");

		$data = json_decode($importSource->getFileContents("settings.json"), true, 512, JSON_THROW_ON_ERROR);
		foreach ($data as $add => $values) {
			foreach ($values as $key => $value) {
				$this->config->setUserValue($user->getUID(), $app, $key, $value);
			}
		}
	}
}
