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

use OCA\UserMigration\Service\UserMigrationService;
use OCA\UserMigration\TempExportDestination;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\UserMigration\IMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Export extends Command {
	private IUserManager $userManager;
	private UserMigrationService $migrationService;
	private ITempManager $tempManager;

	public function __construct(
		IUserManager $userManager,
		UserMigrationService $migrationService,
		ITempManager $tempManager
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
		$this->tempManager = $tempManager;
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
				'types',
				't',
				InputOption::VALUE_REQUIRED,
				'Comma-separated list of data type ids, pass <comment>--types=none</comment> to only export base user data',
				false,
			)
			->addArgument(
				'user',
				InputArgument::OPTIONAL,
				'user to export',
			)
			->addArgument(
				'folder',
				InputArgument::OPTIONAL,
				'local folder to export into',
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);

		$args = array_filter($input->getArguments(), fn (?string $value, string $arg) => $arg === 'command' ? false : !empty($value), ARRAY_FILTER_USE_BOTH);
		$options = array_filter($input->getOptions(), fn ($value) => $value !== false);

		// Show help if no arguments or options are passed
		if (empty($args) && empty($options)) {
			$help = new HelpCommand();
			$help->setCommand($this);
			return $help->run($input, $io);
		}

		$migrators = $this->migrationService->getMigrators();

		$list = $input->getOption('list');
		if ($list !== false) {
			switch (true) {
				case $list === null:
					$io->writeln(array_map(fn (IMigrator $migrator) => $migrator->getId(), $migrators));
					return 0;
				case $list === 'full':
					$io->table(
						['Name', 'Id', 'Description'],
						array_map(
							fn (IMigrator $migrator) => [
								$migrator->getDisplayName(),
								$migrator->getId(),
								// Wrap long descriptions
								'<comment>' . implode("\n", mb_str_split($migrator->getDescription(), 80)) . '</comment>',
							],
							$migrators,
						)
					);
					return 0;
				default:
					$io->warning("Invalid list argument: \"$list\"");
					return 2;
			}
		}

		$selectedMigrators = null;
		$types = $input->getOption('types');
		if ($types !== false) {
			$types = explode(',', $types);
			if ($types !== false) {
				if (count($types) === 1 && reset($types) === 'none') {
					$selectedMigrators = [];
				} else {
					foreach ($types as $id) {
						if (!in_array($id, array_map(fn (IMigrator $migrator) => $migrator->getId(), $migrators), true)) {
							$io->warning("Invalid type: \"$id\"");
							return 2;
						}
					}
					$selectedMigrators = $types;
				}
			}
		}

		$user = $input->getArgument('user');
		if (empty($user)) {
			$io->warning('Missing user argument');
			return 2;
		}

		$userObject = $this->userManager->get($user);
		if (!$userObject instanceof IUser) {
			$io->error("Unknown user <" . $input->getArgument('user') . ">");
			return 1;
		}

		$folder = $input->getArgument('folder');
		if (empty($folder)) {
			$io->warning('Missing folder argument');
			return 2;
		}

		try {
			if (!is_writable($folder)) {
				$io->error("The target folder must exist and be writable by the web server user");
				return 1;
			}
			$folder = realpath($folder);
			$exportDestination = new TempExportDestination($this->tempManager);
			$this->migrationService->export($exportDestination, $userObject, $selectedMigrators, $io);
			$path = $exportDestination->getPath();
			$exportName = $userObject->getUID().'_'.date('Y-m-d_H-i-s');
			if (rename($path, $folder.'/'.$exportName.'.zip') === false) {
				throw new \Exception("Failed to move $path to $folder/$exportName.zip");
			}
			$io->writeln("Export saved in $folder/$exportName.zip");
		} catch (\Exception $e) {
			$io->error($e->getMessage());
			return $e->getCode() !== 0 ? (int)$e->getCode() : 1;
		}

		return 0;
	}
}
