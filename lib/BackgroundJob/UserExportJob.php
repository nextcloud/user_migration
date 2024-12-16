<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\UserMigration\BackgroundJob;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\Db\UserExport;
use OCA\UserMigration\Db\UserExportMapper;
use OCA\UserMigration\Service\UserMigrationService;
use OCA\UserMigration\UserFolderExportDestination;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\IRootFolder;
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
		IRootFolder $root,
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
		$migrators = $export->getMigratorsArray();

		$userObject = $this->userManager->get($user);

		if (!$userObject instanceof IUser) {
			$this->logger->error('Could not export: Unknown user ' . $user);
			$this->failedNotication($export);
			$this->mapper->delete($export);
			return;
		}

		try {
			$export->setStatus(UserExport::STATUS_STARTED);
			$this->mapper->update($export);
			$userFolder = $this->root->getUserFolder($user);
			$exportDestination = new UserFolderExportDestination($userFolder);

			$this->migrationService->export($exportDestination, $userObject, $migrators);
			$this->successNotification($export);
		} catch (\Throwable $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			$this->failedNotication($export);
		} finally {
			$this->mapper->delete($export);
		}
	}

	private function failedNotication(UserExport $export): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($export->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('exportFailed', [
				'sourceUser' => $export->getSourceUser(),
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
				'sourceUser' => $export->getSourceUser(),
			])
			->setObject('export', (string)$export->getId());
		$this->notificationManager->notify($notification);
	}
}
