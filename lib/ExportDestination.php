<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\UserMigrationException;
use ZipStreamer\COMPR;
use ZipStreamer\ZipStreamer;

class ExportDestination implements IExportDestination {
	public const EXPORT_FILENAME = 'user.nextcloud_export';

	protected ZipStreamer $streamer;

	protected string $path;

	/**
	 * @param resource $r resource to write the export into
	 */
	public function __construct($r, string $path) {
		$this->streamer = new ZipStreamer(
			[
				'outstream' => $r,
				'zip64' => true,
				'compress' => COMPR::STORE,
				'level' => COMPR::NONE,
			]
		);
		$this->path = $path;
	}

	/**
	 * {@inheritDoc}
	 */
	public function addFileContents(string $path, string $content): void {
		try {
			$stream = fopen('php://temp', 'r+');
			fwrite($stream, $content);
			rewind($stream);
			if ($this->streamer->addFileFromStream($stream, $path) !== true) {
				throw new UserMigrationException();
			}
		} catch (\Throwable $e) {
			throw new UserMigrationException("Failed to add content in $path in archive", 0, $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function addFileAsStream(string $path, $stream): void {
		try {
			if ($this->streamer->addFileFromStream($stream, $path) !== true) {
				throw new UserMigrationException();
			}
		} catch (\Throwable $e) {
			throw new UserMigrationException("Failed to add content from stream in $path in archive", 0, $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function copyFolder(Folder $folder, string $destinationPath, ?callable $nodeFilter = null): void {
		$success = $this->streamer->addEmptyDir($destinationPath, [
			'timestamp' => $folder->getMTime(),
		]);
		if (!$success) {
			throw new UserMigrationException("Failed to create folder $destinationPath in archive");
		}
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if (($nodeFilter !== null) && !$nodeFilter($node)) {
				continue;
			}
			if ($node instanceof File) {
				if ($node->getName() === static::EXPORT_FILENAME) {
					/* Skip previous user export file */
					// FIXME only ignore root one using getPath()
					continue;
				}
				$read = $node->fopen('rb');
				$success = $this->streamer->addFileFromStream($read, $destinationPath . '/' . $node->getName(), [
					'timestamp' => $node->getMTime(),
				]);
				if (!$success) {
					throw new UserMigrationException('Failed to copy file into ' . $destinationPath . '/' . $node->getName() . ' in archive');
				}
			} elseif ($node instanceof Folder) {
				$this->copyFolder($node, $destinationPath . '/' . $node->getName(), $nodeFilter);
			} else {
				// ignore unknown node type, shouldn't happen
				continue;
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function setMigratorVersions(array $versions): void {
		$this->addFileContents('migrator_versions.json', json_encode($versions));
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): void {
		$success = $this->streamer->finalize();
		if (!$success) {
			throw new UserMigrationException('Failed to close zip streamer');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPath(): string {
		return $this->path;
	}
}
