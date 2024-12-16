<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<UserImport>
 */
class UserImportMapper extends QBMapper {
	public const TABLE_NAME = 'user_import_jobs';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, static::TABLE_NAME, UserImport::class);
	}

	public function getById(int $id): UserImport {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id))
			);

		return $this->findEntity($qb);
	}

	public function getByAuthor(string $userId): UserImport {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('author', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}

	public function getByTargetUser(string $userId): UserImport {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('target_user', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}
}
