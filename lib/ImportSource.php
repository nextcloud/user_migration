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

use OC\Archive\Archive;
use OC\Archive\ZIP;
use OCP\Files\Folder;
use OCP\UserMigration\IImportSource;

class ImportSource implements IImportSource {
	private Archive $archive;

	private string $path;

	/**
	 * @var ?array<string, int>
	 */
	private ?array $migratorVersions = null;

	public function __construct(string $path) {
		$this->path = $path;
		$this->archive = new ZIP($this->path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFileContents(string $path): string {
		return $this->archive->getFile($path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFileAsStream(string $path) {
		// TODO error handling
		return $this->archive->getStream($path, 'r');
	}

	/**
	 * {@inheritDoc}
	 */
	public function copyToFolder(Folder $destination, string $sourcePath): bool {
		// TODO at the very least log errors
		$sourcePath = rtrim($sourcePath, '/').'/';
		$files = $this->archive->getFolder($sourcePath);

		try {
			foreach ($files as $path) {
				if (str_ends_with($path, '/')) {
					if ($this->copyToFolder($destination->newFolder($path), $sourcePath.$path) === false) {
						return false;
					}
				} else {
					$stream = $this->archive->getStream($sourcePath.$path, 'r');
					if ($stream === false) {
						return false;
					}
					$destination->newFile($path, $stream);
				}
			}
		} catch (\OCP\Files\NotPermittedException $e) {
			return false;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMigratorVersions(): array {
		if ($this->migratorVersions === null) {
			$this->migratorVersions = json_decode($this->getFileContents("migrator_versions.json"), true, 512, JSON_THROW_ON_ERROR);
		}
		return $this->migratorVersions;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMigratorVersion(string $migrator): ?int {
		$versions = $this->getMigratorVersions();
		return $versions[$migrator] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): void {
	}

	public function getPath(): string {
		return $this->path;
	}
}
