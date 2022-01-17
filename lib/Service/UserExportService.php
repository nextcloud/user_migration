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
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\IUser;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function count;
use function date;
use OCP\Accounts\IAccountManager;
use OCP\IConfig;
use OCP\ITempManager;

class UserExportService {
	protected IAccountManager $accountManager;

	protected ITempManager $tempManager;

	protected IConfig $config;

	public function __construct(IConfig $config, IAccountManager $accountManager, ITempManager $tempManager) {
		$this->config = $config;
		$this->accountManager = $accountManager;
		$this->tempManager = $tempManager;
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

		// TODO use a temp folder instead?
		//~ $exportFolder = $this->tempManager->getTemporaryFolder();
		$exportFolder = "$uid/export/";
		$exportName = $uid.'_'.date('Y-m-d_H-i-s');
		$finalTarget = $exportFolder.$exportName;

		if (count($view->getDirectoryContent($exportFolder)) > 0) {
			throw new UserExportException("There is already an export for this user $exportFolder");
		}

		// copy the files
		$this->copyFiles(
			$uid,
			$finalTarget,
			$view,
			$output
		);

		$this->exportAccountInformation(
			$user,
			$finalTarget,
			$view,
			$output
		);

		$this->exportAppsSettingsAndVersions(
			$uid,
			$finalTarget,
			$view,
			$output
		);

		// TODO zip/tar the result
		//~ \OC_Files::get($exportFolder, $exportName);
		//~ $archive = new \OC\Archive\TAR($exportFolder.$exportName.'.tar.gz');
		//~ $archive->addRecursive('a', $finalTarget);
		//~ $output->writeln(getcwd());
		//~ $output->writeln("Packing in ".$exportFolder.$exportName.'.tar.gz'."…");
	}

	/**
	 * @throws UserExportException
	 */
	protected function copyFiles(string $uid,
									 string $finalTarget,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Copying files to $finalTarget/files ...");

		if ($view->copy("$uid/files", "$finalTarget/files", true) === false) {
			throw new UserExportException("Could not copy files.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function exportAccountInformation(IUser $user,
									 string $finalTarget,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Exporting account information in $finalTarget/account.json ...");

		if ($view->file_put_contents("$finalTarget/account.json", json_encode($this->accountManager->getAccount($user))) === false) {
			throw new UserExportException("Could not export account information.");
		}
	}

	/**
	 * @throws UserExportException
	 */
	protected function exportAppsSettingsAndVersions(string $uid,
									 string $finalTarget,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Exporting versions in $finalTarget/versions.json ...");

		if ($view->file_put_contents("$finalTarget/versions.json", json_encode(\OC_App::getAppVersions())) === false) {
			throw new UserExportException("Could not export versions.");
		}

		// TODO settings from core and some special fake appids like login/avatar are not exported
		$output->writeln("Exporting settings in $finalTarget/settings.json ...");
		$data = [];

		$apps = \OC_App::getEnabledApps(false, true);

		foreach ($apps as $app) {
			$keys = $this->config->getUserKeys($uid, $app);
			foreach ($keys as $key) {
				$data[$app][$key] = $this->config->getUserValue($uid, $app, $key);
			}
		}

		if ($view->file_put_contents("$finalTarget/settings.json", json_encode($data)) === false) {
			throw new UserExportException("Could not export settings.");
		}
	}
}
