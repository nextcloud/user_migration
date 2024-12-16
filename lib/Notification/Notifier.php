<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration\Notification;

use OCA\UserMigration\AppInfo\Application;
use OCA\UserMigration\ExportDestination;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	protected IFactory $l10nFactory;
	protected IURLGenerator $urlGenerator;
	private IUserManager $userManager;
	private IRootFolder $root;

	public function __construct(IFactory $l10nFactory,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		IRootFolder $root) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
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

		if ($notification->getSubject() === 'importDone') {
			return $this->handleImportDone($notification, $languageCode);
		}
		if ($notification->getSubject() === 'importFailed') {
			return $this->handleImportFailed($notification, $languageCode);
		}

		throw new \InvalidArgumentException('Unhandled subject');
	}

	public function handleExportFailed(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$notification->setRichSubject($l->t('User export failed'))
			->setRichMessage(
				$l->t('Your export of {user} failed.'),
				[
					'user' => $this->getUserRichObject($l, $param['sourceUser']),
				]);
		return $notification;
	}

	public function handleExportDone(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$richObjects = [];
		try {
			$sourceUser = $this->getUser($param['sourceUser']);
			$richObjects['user'] = $this->userToRichObject($sourceUser);
		} catch (\InvalidArgumentException $e) {
			$richObjects['user'] = $this->missingUserToRichObject($l, $param['sourceUser']);
		}
		if (isset($sourceUser)) {
			try {
				$exportFile = $this->getExportFile($sourceUser);
				$path = rtrim($exportFile->getPath(), '/');
				if (strpos($path, '/' . $notification->getUser() . '/files/') === 0) {
					// Remove /user/files/...
					$fullPath = $path;
					[,,, $path] = explode('/', $fullPath, 4);
				}
				$richObjects['file'] = $this->fileToRichObject($exportFile, $path);
			} catch (\InvalidArgumentException|NotFoundException $e) {
				$richObjects['file'] = $this->missingFileToRichObject($l, ExportDestination::EXPORT_FILENAME);
			}
		} else {
			$richObjects['file'] = $this->missingFileToRichObject($l, ExportDestination::EXPORT_FILENAME);
		}

		$notification->setRichSubject($l->t('User export done'))
			->setRichMessage(
				$l->t('Your export of {user} has completed: {file}'),
				$richObjects
			);

		return $notification;
	}

	public function handleImportFailed(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$notification->setRichSubject($l->t('User import failed'))
			->setRichMessage(
				$l->t('Your import to {user} failed.'),
				[
					'user' => $this->getUserRichObject($l, $param['targetUser']),
				]);
		return $notification;
	}

	public function handleImportDone(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$path = $param['path'];
		try {
			$author = $this->getUser($param['author']);
			$importFile = $this->getImportFile($author, $path);
			$fileRichObject = $this->fileToRichObject($importFile, $path);
		} catch (\InvalidArgumentException|NotFoundException $e) {
			$fileRichObject = $this->missingFileToRichObject($l, $path);
		}

		$notification->setRichSubject($l->t('User import done'))
			->setRichMessage(
				$l->t('Your import of {file} into {user} has completed.'),
				[
					'user' => $this->getUserRichObject($l, $param['targetUser']),
					'file' => $fileRichObject,
				]);

		return $notification;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	protected function getUser(string $userId): IUser {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			return $user;
		}
		throw new \InvalidArgumentException('User not found');
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws NotFoundException
	 */
	protected function getExportFile(IUser $user): File {
		$userFolder = $this->root->getUserFolder($user->getUID());
		$file = $userFolder->get(ExportDestination::EXPORT_FILENAME);
		if (!$file instanceof File) {
			throw new \InvalidArgumentException('User export is not a file');
		}
		return $file;
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws NotFoundException
	 */
	protected function getImportFile(IUser $user, string $path): File {
		$userFolder = $this->root->getUserFolder($user->getUID());
		$file = $userFolder->get($path);
		if (!$file instanceof File) {
			throw new \InvalidArgumentException("Import file \"$path\" is not a file");
		}
		return $file;
	}

	/**
	 * @return array{type: 'user'}
	 */
	protected function getUserRichObject(IL10N $l, string $userId): array {
		try {
			$user = $this->getUser($userId);
			return $this->userToRichObject($user);
		} catch (\InvalidArgumentException $e) {
			return $this->missingUserToRichObject($l, $userId);
		}
	}

	private function userToRichObject(IUser $user): array {
		return [
			'type' => 'user',
			'id' => $user->getUID(),
			'name' => $user->getDisplayName(),
		];
	}

	private function missingUserToRichObject(IL10N $l, string $userId): array {
		return [
			'type' => 'user',
			'id' => $userId,
			'name' => $l->t('%s (missing)', [$userId]),
		];
	}

	private function fileToRichObject(File $file, string $path): array {
		return [
			'type' => 'file',
			'id' => $file->getId(),
			'name' => $file->getName(),
			'path' => $path,
			'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $file->getId()]),
		];
	}

	private function missingFileToRichObject(IL10N $l, string $path): array {
		return [
			'type' => 'highlight',
			'id' => basename($path),
			'name' => $l->t('%s (missing)', [$path]),
		];
	}
}
