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

use OCA\UserMigration\AppInfo\Application;
use OC\Files\AppData;
use OC\Files\View;
use ZipStreamer\COMPR;
use ZipStreamer\ZipStreamer;

class ExportDestination implements IExportDestination {
	private ZipStreamer $streamer;

	private string $path;

	public function __construct(AppData\Factory $appDataFactory, string $uid) {
		$exportName = $uid.'_'.date('Y-m-d_H-i-s');
		//~ $appDataRoot = $appDataFactory->get(Application::APP_ID);
		//~ try {
			//~ $folder = $appDataRoot->getFolder('export');
		//~ } catch (\OCP\Files\NotFoundException $e) {
			//~ $folder = $appDataRoot->newFolder('export');
		//~ }
		//~ $file = $folder->newFile($exportName.'.zip');
		// TODO For now, hardcoding /tmp for tests
		$this->path = '/tmp/'.$exportName.'.zip';
		$r = fopen($this->path, 'w');
		//~ $this->path = 'export/'.$file->getName();
		$this->streamer = new ZipStreamer(
			[
				//~ 'outstream' => $file->write(),
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
		$stream = fopen('php://temp','r+');
		fwrite($stream, $content);
		rewind($stream);
		$this->streamer->addFileFromStream($stream, $path);
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function copyFromView(View $view, string $sourcePath, string $destinationPath): bool {
		$this->streamer->addEmptyDir($destinationPath);
		$files = $view->getDirectoryContent($sourcePath);
		foreach ($files as $f) {
			switch ($f->getType()) {
				case \OCP\Files\FileInfo::TYPE_FILE:
					$read = $view->fopen($f->getPath(), 'rb');
					$this->streamer->addFileFromStream($read, $destinationPath.'/'.$f->getName());
				break;
				case \OCP\Files\FileInfo::TYPE_FOLDER:
					if ($this->copyFromView($view, $sourcePath.'/'.$f->getName(), $destinationPath.'/'.$f->getName()) === false) {
						return false;
					}
				break;
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
