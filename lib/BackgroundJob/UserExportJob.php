<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
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
namespace OCA\UserMigration\BackgroundJob;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\Db\UserExportMapper;
use OCA\UserMigration\Db\UserExport;
use OCA\UserMigration\Service\UserMigrationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface;

class UserExportJob extends QueuedJob {
	private IUserManager $userManager;
	private UserMigrationService $migrationService;
	private LoggerInterface $logger;
	private NotificationManager $notificationManager;
	private UserExportMapper $mapper;
	private IRootFolder $root;

	public function __construct(
		ITimeFactory $timeFactory,
		IUserManager $userManager,
		UserMigrationService $migrationService,
		LoggerInterface $logger,
		NotificationManager $notificationManager,
		UserExportMapper $mapper,
		IRootFolder $root
	) {
		parent::__construct($timeFactory);

		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
		$this->mapper = $mapper;
		$this->root = $root;
	}

	public function run($argument): void {
		$id = $argument['id'];

		$export = $this->mapper->getById($id);
		$user = $export->getSourceUser();
		$migrators = $export->getMigratorArray();

		$userObject = $this->userManager->get($user);

		if (!$userObject instanceof IUser) {
			$this->logger->error('Could not export: Unknown user ' . $user);
			$this->failedNotication($export);
			$this->mapper->delete($export);
			return;
		}

		$export->setStatus(UserExport::STATUS_STARTED);
		$this->mapper->update($export);
		$userFolder = $this->root->getUserFolder($user);
		$exportDestination = new UserFolderExportDestination($userFolder);

		try {
			$this->migrationService->export($exportDestination, $userObject, $migrators);
			$this->successNotification($export);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			$this->failedNotication($export);
		}

		$this->mapper->delete($export);
	}

	private function failedNotication(UserExport $export): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($export->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('exportFailed', [
				'user' => $export->getSourceUser(),
			])
			->setObject('export', (string)$export->getId());
		$this->notificationManager->notify($notification);
	}

	private function successNotification(UserExport $export): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($export->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('exportDone', [
				'user' => $export->getSourceUser(),
			])
			->setObject('export', (string)$export->getId());
		$this->notificationManager->notify($notification);
	}
}
