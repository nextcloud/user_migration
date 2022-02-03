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
use OCP\ITempManager;
use ZipStreamer\COMPR;
use ZipStreamer\ZipStreamer;

class ExportDestination implements IExportDestination {
	private ZipStreamer $streamer;

	private string $path;

	public function __construct(ITempManager $tempManager, string $uid) {
		$this->path = $tempManager->getTemporaryFile('.zip');
		$r = fopen($this->path, 'w');
		$this->streamer = new ZipStreamer(
			[
				'outstream' => $r,
				'zip64' => true,
				'compress' => COMPR::STORE,
				'level' => COMPR::NONE,
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function addFile(string $path, string $content): bool {
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);
		$this->streamer->addFileFromStream($stream, $path);
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function copyFolder(Folder $folder, string $destinationPath): bool {
		$this->streamer->addEmptyDir($destinationPath);
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if ($node instanceof File) {
				$read = $node->fopen('rb');
				$this->streamer->addFileFromStream($read, $destinationPath.'/'.$node->getName());
			} elseif ($node instanceof Folder) {
				$success = $this->copyFolder($node, $destinationPath.'/'.$node->getName());
				if ($success === false) {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): void {
		$this->streamer->finalize();
	}

	public function getPath(): string {
		return $this->path;
	}
}
