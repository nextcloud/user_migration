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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
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

	public function __construct(
		ITimeFactory $timeFactory,
		IUserManager $userManager,
		UserMigrationService $migrationService,
		LoggerInterface $logger,
		NotificationManager $notificationManager,
		UserImportMapper $mapper
	) {
		parent::__construct($timeFactory);

		$this->userManager = $userManager;
		$this->migrationService = $migrationService;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
		$this->mapper = $mapper;
	}

	public function run($argument): void {
		$id = $argument['id'];

		$import = $this->mapper->getById($id);
		$user = $import->getSourceUser();
		$path = $import->getPath();

		$userObject = $this->userManager->get($user);

		if (!$userObject instanceof IUser) {
			$this->logger->error('Could not import: Unknown user ' . $user);
			$this->failedNotication($import);
			$this->mapper->delete($import);
			return;
		}

		try {
			$import->setStatus(UserImport::STATUS_STARTED);
			$this->mapper->update($import);

			$this->migrationService->import($path, $userObject);
			$this->successNotification($import);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			$this->failedNotication($import);
		} finally {
			$this->mapper->delete($import);
		}
	}

	private function failedNotication(UserImport $import): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($import->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('importFailed', [
				'sourceUser' => $import->getSourceUser(),
			])
			->setObject('import', (string)$import->getId());
		$this->notificationManager->notify($notification);
	}

	private function successNotification(UserImport $import): void {
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($import->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('importDone', [
				'sourceUser' => $import->getSourceUser(),
			])
			->setObject('import', (string)$import->getId());
		$this->notificationManager->notify($notification);
	}
}
