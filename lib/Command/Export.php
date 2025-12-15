<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\UserMigration\Command;

use OC\Core\Command\Base;
use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\ExportDestination;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\UserMigration\IMigrator;
use Symfony\Component\Console\Formatter\WrappableOutputFormatterInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Export extends Base {
	public function __construct(
		private IUserManager $userManager,
		private UserMigrationService $migrationService,
		private IConfig $config,
		private ITimeFactory $timeFactory,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('user:export')
			->setDescription('Export a user.')
			->addOption(
				'list',
				'l',
				InputOption::VALUE_OPTIONAL,
				'List the available data types, pass <comment>--list=full</comment> to show more information',
				false,
			)
			->addOption(
				'output',
				null,
				InputOption::VALUE_OPTIONAL,
				'Output format (plain, json or json_pretty, default is plain)',
				$this->defaultOutputFormat,
			)
			->addOption(
				'types',
				't',
				InputOption::VALUE_REQUIRED,
				'Comma-separated list of data type ids, pass <comment>--types=none</comment> to only export base user data',
				false,
			)
			->addOption(
				'disable',
				mode: InputOption::VALUE_NONE,
				description: 'Disable the user before starting the export.',
			)
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'user to export',
			)
			->addArgument(
				'folder',
				InputArgument::OPTIONAL,
				'local folder to export into',
				getcwd()
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		/** @var WrappableOutputFormatterInterface $formatter */
		$formatter = $io->getFormatter();

		$migrators = $this->migrationService->getMigrators();

		$list = $input->getOption('list');
		$outputOption = $input->getOption('output');
		if ($list !== false) {
			switch (true) {
				case $list === null:
					if ($outputOption !== static::OUTPUT_FORMAT_PLAIN) {
						$this->writeArrayInOutputFormat($input, $io, array_map(fn (IMigrator $migrator) => $migrator->getId(), $migrators), '');
						return 0;
					}
					$io->writeln(array_map(fn (IMigrator $migrator) => $migrator->getId(), $migrators));
					return 0;
				case $list === 'full':
					if ($outputOption !== static::OUTPUT_FORMAT_PLAIN) {
						$migratorMap = [];
						foreach ($migrators as $migrator) {
							$migratorMap[$migrator->getId()] = [
								'name' => $migrator->getDisplayName(),
								'description' => $migrator->getDescription(),
							];
						}
						$this->writeArrayInOutputFormat($input, $io, $migratorMap, '');
						return 0;
					}
					$io->table(
						['Name', 'Id', 'Description'],
						array_map(
							fn (IMigrator $migrator) => [
								$migrator->getDisplayName(),
								$migrator->getId(),
								'<comment>' . $formatter->formatAndWrap($migrator->getDescription(), 80) . '</comment>',
							],
							$migrators,
						)
					);
					return 0;
				default:
					$io->error("Invalid list argument: \"$list\"");
					return 1;
			}
		}

		$selectedMigrators = null;
		$types = $input->getOption('types');
		if ($types !== false) {
			$types = explode(',', $types);
			if (count($types) === 1 && reset($types) === 'none') {
				$selectedMigrators = [];
			} else {
				foreach ($types as $id) {
					if (!in_array($id, array_map(fn (IMigrator $migrator) => $migrator->getId(), $migrators), true)) {
						$io->error("Invalid type: \"$id\"");
						return 1;
					}
				}
				$selectedMigrators = $types;
			}
		}

		$uid = $input->getArgument('user');

		$user = $this->userManager->get($uid);
		if (!$user instanceof IUser) {
			$io->error("Unknown user <$uid>");
			return 1;
		}

		$folder = $input->getArgument('folder');

		try {
			if (!is_writable($folder)) {
				$io->error('The target folder (' . $folder . ') must exist and be writable by the web server user');
				return 1;
			}
			$folder = realpath($folder);

			$exportName = $user->getUID() . '_' . date('Y-m-d_H-i-s');
			$partSuffix = '.part';
			$exportPath = "$folder/$exportName.zip$partSuffix";

			if ($input->getOption('disable')) {
				$user->setEnabled(false);
				$io->writeln('User ' . $user->getUID() . ' was disabled');
			}

			$resource = fopen($exportPath, 'w');
			$exportDestination = new ExportDestination($resource, $exportPath);
			$this->migrationService->export($exportDestination, $user, $selectedMigrators, $io);

			$path = $exportDestination->getPath();
			$finalPath = substr($path, 0, -mb_strlen($partSuffix));
			if (rename($path, $finalPath) === false) {
				throw new \Exception('Failed to rename ' . basename($path) . ' to ' . basename($finalPath));
			}
			$this->config->setUserValue($user->getUID(), Application::APP_ID, 'lastExport', (string)$this->timeFactory->getTime());
			$io->writeln("Export saved in $finalPath");
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
