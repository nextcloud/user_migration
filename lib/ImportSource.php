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

use OC\Archive\ZIP;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\UserMigrationException;

class ImportSource implements IImportSource {
	private ZIP $archive;

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
	 * @psalm-suppress MethodSignatureMustProvideReturnType false-positive
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
	public function copyToFolder(Folder $destination, string $sourcePath): void {
		$sourcePath = rtrim($sourcePath, '/').'/';
		$files = $this->archive->getFolder($sourcePath);

		try {
			foreach ($files as $path) {
				$stat = $this->archive->getStat($sourcePath . $path);
				if ($stat === null) {
					throw new UserMigrationException("Failed to get stat information from archive for \"" . $sourcePath . $path . "\"");
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
					$this->copyToFolder($folder, $sourcePath.$path);
					$folder->touch($stat['mtime']);
				} else {
					$stream = $this->archive->getStream($sourcePath.$path, 'r');
					if ($stream === false) {
						throw new UserMigrationException("Failed to get \"" . $sourcePath . $path . "\" from archive");
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
			throw new UserMigrationException("Could not import files due to permission issue", 0, $e);
		} catch (\Throwable $e) {
			throw new UserMigrationException("Could not import files", 0, $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMigratorVersions(): array {
		try {
			if ($this->migratorVersions === null) {
				$this->migratorVersions = json_decode($this->getFileContents("migrator_versions.json"), true, 512, JSON_THROW_ON_ERROR);
			}
			return $this->migratorVersions;
		} catch (\Exception $e) {
			throw new UserMigrationException("Failed to get migrators versions", 0, $e);
		}
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
		try {
			$data = json_decode($this->getFileContents(static::PATH_USER), true, 512, JSON_THROW_ON_ERROR);

			if (isset($data['uid'])) {
				return $data['uid'];
			} else {
				throw new UserMigrationException('No uid found in '.static::PATH_USER);
			}
		} catch (\Exception $e) {
			throw new UserMigrationException("Failed to original uid", 0, $e);
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
