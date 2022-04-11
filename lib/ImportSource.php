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

use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\UserMigrationException;
use OC\Archive\Archive;
use OC\Archive\ZIP;

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
		$string = $this->archive->getFile($path);
		if (is_string($string)) {
			return $string;
		} else {
			throw new UserMigrationException('Failed to get '.$path.' from archive');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFileAsStream(string $path) {
		$stream = $this->archive->getStream($path, 'r');
		if ($stream !== false) {
			return $stream;
		} else {
			throw new UserMigrationException('Failed to get '.$path.' from archive');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFolderListing(string $path): array {
		return $this->archive->getFolder($path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function pathExists(string $path): bool {
		return $this->archive->fileExists($path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function copyToFolder(Folder $destination, string $sourcePath): bool {
		// TODO log errors to ease debugging
		$sourcePath = rtrim($sourcePath, '/').'/';
		$files = $this->archive->getFolder($sourcePath);

		try {
			foreach ($files as $path) {
				$stat = $this->archive->getStat($sourcePath . $path);
				if ($stat === null) {
					// TODO: use exception
					echo "Stat information not found for " . $sourcePath . $path . "\n";
					return false;
				}
				if (str_ends_with($path, '/')) {
					try {
						$folder = $destination->get($path);
						if (!($folder instanceof Folder)) {
							$folder->delete();
							$folder = $destination->newFolder($path);
						}
					} catch (NotFoundException $e) {
						$folder = $destination->newFolder($path);
					}
					if ($this->copyToFolder($folder, $sourcePath.$path) === false) {
						// TODO: use exception
						echo "copy to $sourcePath.$path failed\n";
						return false;
					}
					$folder->touch($stat['mtime']);
				} else {
					$stream = $this->archive->getStream($sourcePath.$path, 'r');
					if ($stream === false) {
						return false;
					}
					try {
						$file = $destination->get($path);
						if ($file instanceof File) {
							$file->putContent($stream);
						} else {
							$file->delete();
							$file = $destination->newFile($path, $stream);
						}
					} catch (NotFoundException $e) {
						$file = $destination->newFile($path, $stream);
					}
					$file->touch($stat['mtime']);
				}
			}
		} catch (NotPermittedException $e) {
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
	public function getOriginalUid(): string {
		$data = json_decode($this->getFileContents(static::PATH_USER), true, 512, JSON_THROW_ON_ERROR);

		if (isset($data['uid'])) {
			return $data['uid'];
		} else {
			throw new UserMigrationException('No uid found in '.static::PATH_USER);
		}
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
