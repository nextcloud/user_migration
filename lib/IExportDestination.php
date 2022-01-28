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

use OC\Files\View;

interface IExportDestination {
	/**
	 * Adds a file to the export
	 *
	 * @param string $path Full path to the file in the export archive. Parent directories will be created if needed.
	 * @param string $content The full content of the file.
	 * @return bool whether the file was successfully added.
	 */
	public function addFile(string $path, string $content): bool;

	/**
	 * Copy files from View
	 */
	public function copyFromView(View $view, string $sourcePath, string $destinationPath): bool;

	/**
	 * Called after export is complete
	 */
	public function close(): void;
}
