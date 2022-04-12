<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
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

namespace OCA\UserMigration\Notification;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\ExportDestination;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	protected IFactory $l10nFactory;
	protected IURLGenerator $urlGenerator;
	private IManager $notificationManager;
	private IUserManager $userManager;
	private ITimeFactory $timeFactory;
	private IRootFolder $root;

	public function __construct(IFactory $l10nFactory,
								IURLGenerator $urlGenerator,
								IManager $notificationManager,
								IUserManager $userManager,
								ITimeFactory $timeFactory,
								IRootFolder $root) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->notificationManager = $notificationManager;
		$this->userManager = $userManager;
		$this->timeFactory = $timeFactory;
		$this->root = $root;
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('User migration');
	}

	/**
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Unhandled app');
		}

		if ($notification->getSubject() === 'exportDone') {
			return $this->handleExportDone($notification, $languageCode);
		}
		if ($notification->getSubject() === 'exportFailed') {
			return $this->handleExportFailed($notification, $languageCode);
		}

		throw new \InvalidArgumentException('Unhandled subject');
	}

	public function handleExportFailed(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$sourceUser = $this->getUser($param['sourceUser']);
		$notification->setRichSubject($l->t('User export failed'))
			->setParsedSubject($l->t('User export failed'))
			->setRichMessage(
				$l->t('Your export of {user} failed.'),
				[
					'user' => [
						'type' => 'user',
						'id' => $sourceUser->getUID(),
						'name' => $sourceUser->getDisplayName(),
					],
				])
			->setParsedMessage(
				str_replace(
					['{user}'],
					[$sourceUser->getDisplayName()],
					$l->t('Your export of {user} failed.')
				)
			);
		return $notification;
	}

	public function handleExportDone(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$sourceUser = $this->getUser($param['sourceUser']);
		$exportFile = $this->getExportFile($sourceUser);

		$path = rtrim($exportFile->getPath(), '/');
		if (strpos($path, '/' . $notification->getUser() . '/files/') === 0) {
			// Remove /user/files/...
			$fullPath = $path;
			[,,, $path] = explode('/', $fullPath, 4);
		}
		$notification->setRichSubject($l->t('User export done'))
			->setParsedSubject($l->t('User export done'))
			->setRichMessage(
				$l->t('Your export of {user} has completed: {file}'),
				[
					'user' => [
						'type' => 'user',
						'id' => $sourceUser->getUID(),
						'name' => $sourceUser->getDisplayName(),
					],
					'file' => [
						'type' => 'file',
						'id' => $exportFile->getId(),
						'name' => $exportFile->getName(),
						'path' => $path,
						'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $exportFile->getId()]),
					],
				])
			->setParsedMessage(
				str_replace(
					['{user}', '{file}'],
					[$sourceUser->getDisplayName(), $path],
					$l->t('Your export of {user} has completed: {link}')
				)
			);

		return $notification;
	}

	protected function getUser(string $userId): IUser {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			return $user;
		}
		throw new \InvalidArgumentException('User not found');
	}

	protected function getExportFile(IUser $user): File {
		$userFolder = $this->root->getUserFolder($user->getUID());
		$file = $userFolder->get(ExportDestination::EXPORT_FILENAME);
		if (!$file instanceof File) {
			throw new \InvalidArgumentException('User export is not a file');
		}
		return $file;
	}
}
