<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Côme Chilliet <come.chilliet@nextcloud.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * @method void setSourceUser(string $uid)
 * @method string getSourceUser()
 * @method void setMigrators(string $uid)
 * @method string getMigrators()
 * @method void setStatus(string $status)
 * @method string getStatus()
 */
class UserExport extends Entity {
	public const STATUS_WAITING = 'waiting';
	public const STATUS_STARTED = 'started';

	/** @var string */
	protected $source_user;
	/** @var string JSON encoded array */
	protected $migrators;
	/** @var string */
	protected $status;

	public function __construct() {
		$this->addType('source_user', 'string');
		$this->addType('migrators', 'string');
		$this->addType('status', 'string');
	}

	/**
	 * Returns the migrators in an associative array
	 */
	public function getMigratorsArray(): ?array {
		return json_decode($this->migrators, true);
	}

	/**
	 * Set the migrators
	 */
	public function setMigratorsArray(?array $migrators): void {
		$this->setMigrators(json_encode($migrators));
	}
}
