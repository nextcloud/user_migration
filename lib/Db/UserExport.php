<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
