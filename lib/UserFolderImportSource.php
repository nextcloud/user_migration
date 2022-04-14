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

namespace OCA\UserMigration;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\UserMigration\UserMigrationException;

class UserFolderImportSource extends ImportSource {
	private File $file;

	public function __construct(Folder $userFolder, string $path) {
		try {
			$this->file = $userFolder->get($path);
			if (!$this->file instanceof File) {
				throw new UserMigrationException("$path is not a valid file");
			}
		} catch (NotFoundException $e) {
			throw new UserMigrationException("$path not found", 0, $e);
		}
		$localPath = $this->file->getStorage()->getLocalFile($this->file->getInternalPath());
		parent::__construct($localPath);
	}
}
