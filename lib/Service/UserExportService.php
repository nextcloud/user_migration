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

use OC\Files\Filesystem;
use OC\Files\View;
use OCP\IUser;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function count;
use function date;

class UserExportService {
	public function __construct() {
	}

	/**
	 * TODO Use our own exception class
	 * @throws \Exception
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

		// TODO config option?
		$exportFolder = "$uid/export/";
		$finalTarget = $exportFolder.date('Y-m-d H-i-s');

		if (count($view->getDirectoryContent($exportFolder)) > 0) {
			throw new \Exception("There is already an export for this user");
		}

		// copy the files
		$this->copyFiles(
			$uid,
			$finalTarget,
			$view,
			$output
		);
	}

	/**
	 * @throws \Exception
	 */
	protected function copyFiles(string $uid,
									 string $finalTarget,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Copying files to $finalTarget ...");

		$sourcePath = "$uid/files";
		if ($view->copy($sourcePath, $finalTarget, true) === false) {
			throw new \Exception("Could not copy files.");
		}
	}
}
