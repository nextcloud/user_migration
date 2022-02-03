<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UserMigration\Command;

use OCA\UserMigration\Service\UserMigrationService;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends Command {

	/** @var IUserManager */
	private $userManager;

	/** @var UserMigrationService */
	private $migrationService;

	public function __construct(IUserManager $userManager,
								UserMigrationService $migrationService
								) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
	}

	protected function configure() {
		$this
			->setName('user:export')
			->setDescription('Export a user.')
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'user to export'
			)
			->addArgument(
				'folder',
				InputArgument::REQUIRED,
				'folder to export into'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userObject = $this->userManager->get($input->getArgument('user'));

		if (!$userObject instanceof IUser) {
			$output->writeln("<error>Unknown user " . $input->getArgument('user') . "</error>");
			return 1;
		}

		try {
			$path = $this->migrationService->export($userObject, $output);
			$exportName = $userObject->getUID().'_'.date('Y-m-d_H-i-s');
			$folder = realpath($input->getArgument('folder'));
			if (rename($path, $folder.'/'.$exportName.'.zip') === false) {
				throw new \Exception("Failed to move $path to $folder/$exportName.zip");
			}
			$output->writeln("Moved the export to $folder/$exportName.zip");
		} catch (\Exception $e) {
			$output->writeln("<error>" . $e->getMessage() . "</error>");
			return $e->getCode() !== 0 ? (int)$e->getCode() : 1;
		}

		return 0;
	}
}
