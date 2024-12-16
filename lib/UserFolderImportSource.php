<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\UserMigration\UserMigrationException;

class UserFolderImportSource extends ImportSource {
	private File $file;

	public function __construct(Folder $userFolder, string $path) {
		try {
			$file = $userFolder->get($path);
			if (!$file instanceof File) {
				throw new UserMigrationException("$path is not a valid file");
			}
			$this->file = $file;
		} catch (NotFoundException $e) {
			throw new UserMigrationException("$path not found", 0, $e);
		}
		$localPath = $this->file->getStorage()->getLocalFile($this->file->getInternalPath());
		parent::__construct($localPath);
	}
}
