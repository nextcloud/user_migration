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
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Import extends Command {
	private UserMigrationService $migrationService;

	private IUserManager $userManager;

	private QuestionHelper $questionHelper;

	public function __construct(
		UserMigrationService $migrationService,
		IUserManager $userManager,
		QuestionHelper $questionHelper
	) {
		parent::__construct();
		$this->migrationService = $migrationService;
		$this->userManager = $userManager;
		$this->questionHelper = $questionHelper;
	}

	protected function configure(): void {
		$this
			->setName('user:import')
			->setDescription('Import a user.')
			->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED,
				'uid of a user to override with the imported data'
			)
			->addArgument(
				'archive',
				InputArgument::REQUIRED,
				'path of the export archive to import'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$uid = $input->getOption('user');
			if (!empty($uid)) {
				$user = $this->userManager->get($uid);
				if ($user === null) {
					throw new \Exception("User $uid does not exists");
				} else {
					$question = new ConfirmationQuestion(
						'Warning: A user already exists with this uid!'."\n"
						. 'Do you really want to override this user with imported data? (y/n) ', false);
					if (!$this->questionHelper->ask($input, $output, $question)) {
						$output->writeln('aborted.');
						return 1;
					}
				}
			} else {
				$user = null;
			}
			$this->migrationService->import($input->getArgument('archive'), $output, $user);
		} catch (\Exception $e) {
			$output->writeln("$e");
			$output->writeln("<error>" . $e->getMessage() . "</error>");
			return $e->getCode() !== 0 ? (int)$e->getCode() : 1;
		}

		return 0;
	}
}
