<?php

declare(strict_types=1);

/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
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

namespace OCA\UserMigration\BackgroundJob;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\Db\UserImport;
use OCA\UserMigration\Db\UserImportMapper;
use OCA\UserMigration\Service\UserMigrationService;
use OCA\UserMigration\UserFolderImportSource;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface;

class UserImportJob extends QueuedJob {
	private IUserManager $userManager;
	private UserMigrationService $migrationService;
	private LoggerInterface $logger;
	private NotificationManager $notificationManager;
	private UserImportMapper $mapper;
	private IConfig $config;
	private IRootFolder $root;

	public function __construct(
		ITimeFactory $timeFactory,
		IUserManager $userManager,
		UserMigrationService $migrationService,
		LoggerInterface $logger,
		NotificationManager $notificationManager,
		UserImportMapper $mapper,
		IConfig $config,
		IRootFolder $root
	) {
		parent::__construct($timeFactory);

		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
		$this->mapper = $mapper;
		$this->config = $config;
		$this->root = $root;
	}

	public function run($argument): void {
		$id = $argument['id'];

		$import = $this->mapper->getById($id);
		$author = $import->getAuthor();
		$targetUser = $import->getTargetUser();
		$path = $import->getPath();

		$authorObject = $this->userManager->get($author);
		$targetUserObject = $this->userManager->get($targetUser);

		if (!($authorObject instanceof IUser) || !($targetUserObject instanceof IUser)) {
			if (!($authorObject instanceof IUser)) {
				$this->logger->error('Could not import: Unknown author ' . $author);
			} elseif (!($targetUserObject instanceof IUser)) {
				$this->logger->error('Could not import: Unknown target user ' . $targetUser);
			}
			$this->failedNotication($import);
			$this->mapper->delete($import);
			return;
		}

		try {
			$import->setStatus(UserImport::STATUS_STARTED);
			$this->mapper->update($import);
			$importSource = new UserFolderImportSource($this->root->getUserFolder($author), $path);

			$this->migrationService->import($importSource, $targetUserObject);
			$this->successNotification($import);
		} catch (\Throwable $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			$this->failedNotication($import);
		} finally {
			$this->mapper->delete($import);
		}
	}

	private function failedNotication(UserImport $import): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($import->getAuthor())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('importFailed', [
				'author' => $import->getAuthor(),
				'targetUser' => $import->getTargetUser(),
				'path' => $import->getPath(),
			])
			->setObject('import', (string)$import->getId());
		$this->notificationManager->notify($notification);
	}

	private function successNotification(UserImport $import): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($import->getAuthor())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('importDone', [
				'author' => $import->getAuthor(),
				'targetUser' => $import->getTargetUser(),
				'path' => $import->getPath(),
			])
			->setObject('import', (string)$import->getId());
		$this->notificationManager->notify($notification);
	}
}
