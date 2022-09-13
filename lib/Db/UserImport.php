<?php

declare(strict_types=1);

/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
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

namespace OCA\UserMigration\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setAuthor(string $uid)
 * @method string getAuthor()
 * @method void setTargetUser(string $uid)
 * @method string getTargetUser()
 * @method void setPath(string $path)
 * @method string getPath()
 * @method void setMigrators(string $migrators)
 * @method string getMigrators()
 * @method void setStatus(int $status)
 * @method int getStatus()
 */
class UserImport extends Entity {
	public const STATUS_WAITING = 0;
	public const STATUS_STARTED = 1;

	/** @var string */
	protected $author;
	/** @var string */
	protected $targetUser;
	/** @var string */
	protected $path;
	/** @var string JSON encoded array */
	protected $migrators;
	/** @var int */
	protected $status;

	public function __construct() {
		$this->addType('author', 'string');
		$this->addType('targetUser', 'string');
		$this->addType('path', 'string');
		$this->addType('migrators', 'string');
		$this->addType('status', 'int');
	}

	/**
	 * Returns the migrators in an array
	 * @return ?string[]
	 */
	public function getMigratorsArray(): ?array {
		return json_decode($this->migrators, true);
	}

	/**
	 * Set the migrators
	 * @param ?string[] $migrators
	 */
	public function setMigratorsArray(?array $migrators): void {
		$this->setMigrators(json_encode($migrators));
	}
}
