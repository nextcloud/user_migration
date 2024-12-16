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
 * @template-extends QBMapper<UserExport>
 */
class UserExportMapper extends QBMapper {
	public const TABLE_NAME = 'user_export_jobs';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, static::TABLE_NAME, UserExport::class);
	}

	public function getById(int $id): UserExport {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id))
			);

		return $this->findEntity($qb);
	}

	public function getBySourceUser(string $userId): UserExport {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('source_user', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}
}
