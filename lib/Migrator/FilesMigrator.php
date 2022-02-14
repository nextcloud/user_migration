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

namespace OCA\UserMigration\Migrator;

use OCA\UserMigration\Exception\UserMigrationException;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

class FilesMigrator implements IMigrator {
	use TMigratorBasicVersionHandling;

	protected IRootFolder $root;

	public function __construct(
		IRootFolder $rootFolder
	) {
		$this->root = $rootFolder;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output
	): void {
		$output->writeln("Copying files…");

		$uid = $user->getUID();

		if ($exportDestination->copyFolder($this->root->getUserFolder($uid), "files") === false) {
			throw new UserMigrationException("Could not copy files.");
		}
		// TODO files metadata should be exported as well if relevant.
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output
	): void {
		$output->writeln("Importing files…");

		$uid = $user->getUID();

		if ($importSource->copyToFolder($this->root->getUserFolder($uid), "files") === false) {
			throw new UserMigrationException("Could not import files.");
		}
	}
}
