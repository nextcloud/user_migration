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
