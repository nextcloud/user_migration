<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
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

use OCA\UserMigration\ImportSource;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Import extends Command {
	private UserMigrationService $migrationService;

	private IUserManager $userManager;

	public function __construct(
		UserMigrationService $migrationService,
		IUserManager $userManager
	) {
		parent::__construct();
		$this->migrationService = $migrationService;
		$this->userManager = $userManager;
	}

	protected function configure(): void {
		$this
			->setName('user:import')
			->setDescription('Import a user.')
			->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED,
				'uid of user to overwrite with the imported data'
			)
			->addArgument(
				'archive',
				InputArgument::REQUIRED,
				'local path of the export archive to import'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);

		try {
			$uid = $input->getOption('user');
			if (!empty($uid)) {
				$user = $this->userManager->get($uid);
				if ($user === null) {
					$io->error("User <$uid> does not exist");
					return 1;
				} else {
					$io->warning('A user with this uid already exists!');
					if (!$io->confirm('Do you really want to overwrite this user with the imported data?', false)) {
						$io->writeln('aborted.');
						return 1;
					}
				}
			} else {
				$user = null;
			}
			$path = $input->getArgument('archive');
			$io->writeln("Importing from ${path}…");
			$importSource = new ImportSource($path);
			$this->migrationService->import($importSource, $user, $io);
			$io->writeln("Successfully imported from ${path}");
		} catch (\Exception $e) {
			if ($io->isDebug()) {
				$io->error("$e");
			} else {
				$io->error($e->getMessage());
			}
			return $e->getCode() !== 0 ? (int)$e->getCode() : 1;
		}

		return 0;
	}
}
