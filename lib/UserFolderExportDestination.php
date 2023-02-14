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

class UserFolderExportDestination extends ExportDestination {
	private File $file;

	/**
	 * @var resource|closed-resource
	 */
	private $resource;

	public function __construct(Folder $userFolder) {
		$path = static::EXPORT_FILENAME;
		try {
			// TODO Avoid version creation if possible
			$file = $userFolder->get($path);
			if (!$file instanceof File) {
				$file->delete();
				$file = $userFolder->newFile($path);
			}
		} catch (NotFoundException $e) {
			$file = $userFolder->newFile($path);
		}
		$this->file = $file;
		$this->resource = $file->fopen('w');
		parent::__construct($this->resource, $path);
	}

	public function close(): void {
		// FIXME: $file->fopen() is not triggering the "postWrite" hook on fclose

		// workaround to force refresh the size/mtime:
		parent::close();
		if (is_resource($this->resource)) {
			fclose($this->resource);
		}
		$this->file->touch();
	}
}
