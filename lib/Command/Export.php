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

use OC\Core\Command\Base;
use OCA\UserMigration\Service\UserMigrationService;
use OCA\UserMigration\TempExportDestination;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\UserMigration\IMigrator;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Formatter\WrappableOutputFormatterInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Export extends Base {
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
		/** @var WrappableOutputFormatterInterface $formatter */
		$formatter = $io->getFormatter();

		// Filter for only explicitly passed arguments
		$args = array_filter(
			$input->getArguments(),
			fn (?string $value, string $arg) => $arg === 'command' ? false : !empty($value),
			ARRAY_FILTER_USE_BOTH,
		);
		// Filter for only explicitly passed options
		$options = array_filter(
			$input->getOptions(),
			fn ($value) => $value !== false,
		);

		// Show help if no arguments or options are passed
		if (empty($args) && empty($options)) {
			$help = new HelpCommand();
			$help->setCommand($this);
			return $help->run($input, $io);
		}

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
			if ($types !== false) {
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
		}

		$uid = $input->getArgument('user');
		if (empty($uid)) {
			$io->error('Missing user argument');
			return 1;
		}

		$user = $this->userManager->get($uid);
		if (!$user instanceof IUser) {
			$io->error("Unknown user <$uid>");
			return 1;
		}

		$folder = $input->getArgument('folder');
		if (empty($folder)) {
			$io->error('Missing folder argument');
			return 1;
		}

		try {
			if (!is_writable($folder)) {
				$io->error("The target folder must exist and be writable by the web server user");
				return 1;
			}
			$folder = realpath($folder);
			$exportDestination = new TempExportDestination($this->tempManager);
			$this->migrationService->export($exportDestination, $user, $selectedMigrators, $io);
			$path = $exportDestination->getPath();
			$exportName = $user->getUID().'_'.date('Y-m-d_H-i-s');
			if (rename($path, $folder.'/'.$exportName.'.zip') === false) {
				throw new \Exception("Failed to move $path to $folder/$exportName.zip");
			}
			$io->writeln("Export saved in $folder/$exportName.zip");
		} catch (\Exception $e) {
			if ($io->isDebug()) {
				$io->error($e->getTrace());
			}
			$io->error($e->getMessage());
			return $e->getCode() !== 0 ? (int)$e->getCode() : 1;
		}

		return 0;
	}
}
