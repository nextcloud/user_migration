<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\UserMigration\Command;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\ImportSource;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\IConfig;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Import extends Command {
	public function __construct(
		private IUserManager $userManager,
		private UserMigrationService $migrationService,
		private IConfig $config,
	) {
		parent::__construct();
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
			$path = $input->getArgument('archive');
			if (!file_exists($path)) {
				$io->error("File {$path} could not be found");
				$io->writeln('aborted.');
				return self::FAILURE;
			}
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
			$io->writeln("Importing from {$path}…");
			$importSource = new ImportSource($path);
			$this->migrationService->import($importSource, $user, $io);
			/* Reset exported state of user after import */
			$this->config->deleteUserValue($user->getUID(), Application::APP_ID, 'lastExport');
			$io->writeln("Successfully imported from {$path}");
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
