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
use OCP\DB\Types;

/**
 * @method void setSourceUser(string $uid)
 * @method string getSourceUser()
 * @method void setMigrators(string $migrators)
 * @method string getMigrators()
 * @method void setStatus(int $status)
 * @method int getStatus()
 */
class UserExport extends Entity {
	public const STATUS_WAITING = 0;
	public const STATUS_STARTED = 1;

	/** @var string */
	protected $sourceUser;
	/** @var string JSON encoded array */
	protected $migrators;
	/** @var int */
	protected $status;

	public function __construct() {
		$this->addType('sourceUser', Types::STRING);
		$this->addType('migrators', Types::STRING);
		$this->addType('status', Types::INTEGER);
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
