<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\UserMigration\Migration;

use Closure;
use OCA\UserMigration\Db\UserExportMapper;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version00001Date20220411114609 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->createTable(UserExportMapper::TABLE_NAME);
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('source_user', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('migrators', Types::STRING, [
			'notnull' => false,
			'length' => 4000,
		]);
		$table->addColumn('status', Types::SMALLINT, [
			'notnull' => true,
		]);
		$table->setPrimaryKey(['id']);

		return $schema;
	}
}
