<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration\Command;

use OC\Core\Command\Base;
use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Manage extends Base {
	public function __construct(
		private IDBConnection $connection,
		private IUserManager $userManager,
		private UserMigrationService $migrationService,
		private IConfig $config,
		private ITimeFactory $timeFactory,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('user_migration:manage')
			->setDescription('List users exported by the admin, delete them by batch')
			->addOption(
				'limit',
				'l',
				InputOption::VALUE_REQUIRED,
				'Limit the number of listed users',
				100,
			)
			->addOption(
				'since',
				null,
				InputOption::VALUE_REQUIRED,
				'Filter by minimum export date',
			)
			->addOption(
				'delete',
				null,
				InputOption::VALUE_NONE,
				'Delete the exported users',
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ((string)$input->getOption('since') !== '') {
			$since = new \DateTime($input->getOption('since'));
			$output->writeln('<info>Since ' . $since->format(\DateTimeInterface::ATOM) . '</info>');
		} else {
			$since = null;
		}
		$values = iterator_to_array($this->queryUsers((int)$input->getOption('limit'), $since));
		$this->writeTableInOutputFormat($input, $output, $values);
		if ($input->getOption('delete')) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Please confirm to delete the above listed users [y/n]', !$input->isInteractive());

			if (!$helper->ask($input, $output, $question)) {
				$output->writeln('<info>Deletion canceled</info>');
				return self::SUCCESS;
			}
			$errors = $this->deleteUsers(array_column($values, 'userid'), $output);
			if ($errors > 0) {
				return self::FAILURE;
			}
		}
		return self::SUCCESS;
	}

	private function queryUsers(int $limit, ?\DateTime $since): \Generator {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('userid', 'configvalue')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('lastExport')));

		if ($since !== null) {
			$qb->andWhere($qb->expr()->gte('configvalue', $qb->createNamedParameter($since->getTimestamp(), IQueryBuilder::PARAM_INT)));
		}

		$qb->orderBy('configvalue')
			->setMaxResults($limit);

		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			yield [
				'userid' => $row['userid'],
				'Last export' => date(\DateTimeInterface::ATOM, (int)$row['configvalue'])
			];
		}

		$result->closeCursor();
	}

	/**
	 * @param iterable<string> $uids
	 */
	private function deleteUsers(iterable $uids, OutputInterface $output): int {
		$errors = 0;
		foreach ($uids as $uid) {
			$user = $this->userManager->get($uid);
			if (is_null($user)) {
				$output->writeln('<error>User ' . $uid . ' does not exist</error>');
				$errors++;
				continue;
			}

			if ($user->delete()) {
				$output->writeln('<info>User "' . $uid . '" was deleted</info>');
			} else {
				$output->writeln('<error>User "' . $uid . '" could not be deleted. Please check the logs.</error>');
				$errors++;
			}
		}
		return $errors;
	}
}
