<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserMigration\Migrator;


use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\RowCellDatetimeMapper;
use OCA\Tables\Db\RowCellNumberMapper;
use OCA\Tables\Db\RowCellSelectionMapper;
use OCA\Tables\Db\RowCellTextMapper;
use OCA\Tables\Db\RowCellUsergroupMapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\IL10N;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OCP\IUser;
use Symfony\Component\Console\Output\OutputInterface;

class TablesMigrator implements IMigrator, ISizeEstimationMigrator
{
	use TMigratorBasicVersionHandling;

	protected IL10N $l10n;
	protected TableMapper $tableMapper;
	protected ColumnMapper $columnMapper;
	protected RowSleeveMapper $rowSleeveMapper;
	protected ViewMapper $viewMapper;
	protected ContextMapper $contextMapper;
	protected ShareMapper $shareMapper;
	protected ContextNodeRelationMapper $contextNodeRelationMapper;
	protected FavoritesService $favoritesService;
	protected TableService $tableService;
	protected RowCellNumberMapper $rowCellNumberMapper;
	protected RowCellSelectionMapper $rowCellSelectionMapper;
	protected RowCellTextMapper $rowCellTextMapper;
	protected RowCellUsergroupMapper $rowCellUsergroupMapper;
	protected RowCellDatetimeMapper $rowCellDatetimeMapper;
	protected ViewService $viewService;
	protected ColumnService $columnService;
	protected RowService $rowService;
	private ContextService $contextService;
	private ShareService $shareService;

	public function __construct(
		IL10N $l10n,
		TableMapper $tableMapper,
		ColumnMapper $columnMapper,
		RowSleeveMapper $rowSleeveMapper,
		ViewMapper $viewMapper,
		ContextMapper $contextMapper,
		ShareMapper $shareMapper,
		ContextNodeRelationMapper $contextNodeRelationMapper,
		FavoritesService $favoritesService,
		TableService $tableService,
		RowCellNumberMapper $rowCellNumberMapper,
		RowCellSelectionMapper $rowCellSelectionMapper,
		RowCellTextMapper $rowCellTextMapper,
		RowCellUsergroupMapper $rowCellUsergroupMapper,
		RowCellDatetimeMapper $rowCellDatetimeMapper,
		ViewService $viewService,
		ColumnService $columnService,
		RowService $rowService,
		ContextService $contextService,
		ShareService $shareService,

	) {
		$this->l10n = $l10n;
		$this->tableMapper = $tableMapper;
		$this->columnMapper = $columnMapper;
		$this->rowSleeveMapper = $rowSleeveMapper;
		$this->viewMapper = $viewMapper;
		$this->contextMapper = $contextMapper;
		$this->shareMapper = $shareMapper;
		$this->contextNodeRelationMapper = $contextNodeRelationMapper;
		$this->favoritesService = $favoritesService;
		$this->tableService = $tableService;
		$this->rowCellNumberMapper = $rowCellNumberMapper;
		$this->rowCellSelectionMapper = $rowCellSelectionMapper;
		$this->rowCellTextMapper = $rowCellTextMapper;
		$this->rowCellUsergroupMapper = $rowCellUsergroupMapper;
		$this->rowCellDatetimeMapper = $rowCellDatetimeMapper;
		$this->viewService = $viewService;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->contextService = $contextService;
		$this->shareService = $shareService;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEstimatedExportSize(IUser $user): int|float
	{
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output,
	): void {
		try {
			$uid = $user->getUID();

			$favorites = $this->favoritesService->findAll($uid);
			$exportDestination->addFileContents('favorites.json', json_encode($favorites));

			// tables
			$tables = $this->tableMapper->findAll($uid);
			$exportDestination->addFileContents('tables.json', json_encode($tables));

			$tableIds = array_map(fn($t) => $t->getId(), $tables);
			$columns = $this->columnMapper->findAllByTableIds($tableIds);
			$exportDestination->addFileContents('columns.json', json_encode($columns));

			$rows = $this->rowSleeveMapper->findAllByTableIds($tableIds);
			$exportDestination->addFileContents('rows.json', json_encode($rows));

			$views = $this->getAllViewsForTableIds($tableIds);
			$exportDestination->addFileContents('views.json', json_encode($views));

			// contexts
			$contexts = $this->contextMapper->findAll($uid);
			$exportDestination->addFileContents('contexts.json', json_encode($contexts));

			$contextIds = array_map(fn($c) => $c->getId(), $contexts);

			// shares
			$shares = $this->shareMapper->findByNodeIdsAndTypes($tableIds, $contextIds);
			$exportDestination->addFileContents('shares.json', json_encode($shares));

			$rowIds = array_map(fn($c) => $c->getId(), $rows);
			$columnIds = array_map(fn($c) => $c->getId(), $columns);

			// tables cell
			$rowCellNumbers = $this->rowCellNumberMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents('row_cell_numbers.json', json_encode($rowCellNumbers));

			$rowCellSelection = $this->rowCellSelectionMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents('row_cell_selection.json', json_encode($rowCellSelection));

			$rowCellText = $this->rowCellTextMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents('row_cell_text.json', json_encode($rowCellText));

			$rowCellDateTime = $this->rowCellDatetimeMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents('row_cell_datetime.json', json_encode($rowCellDateTime));

			$rowCellUserGroup = $this->rowCellUsergroupMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents('row_cell_usergroup.json', json_encode($rowCellUserGroup));

		} catch (\Throwable $e) {
			file_put_contents('/tmp/tables_export_error.log', $e->getMessage() . "\n" . $e->getTraceAsString(), FILE_APPEND);
			throw $e;
		}

	}

	/**
	 * @param array $tableIds
	 * @return array
	 */
	private function getAllViewsForTableIds(array $tableIds): array {
		$views = [];
		foreach ($tableIds as $tableId) {
			$views = array_merge($views, $this->viewMapper->findAll($tableId));
		}
		return $views;
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output,
	): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No version for migrator ' . $this->getId() . ' (' . static::class . '), skipping import…');
			return;
		}
		$output->writeln('Importing tables, columns, rows, contexts, shares, and relations…');

		$tables = json_decode($importSource->getFileContents('tables.json'), true, 512, JSON_THROW_ON_ERROR);
		$contexts = json_decode($importSource->getFileContents('contexts.json'), true, 512, JSON_THROW_ON_ERROR);

		$tableIdMap = [];
		$contextIdMap = [];
		$connection = $this->tableMapper->getDBConnection();
		$connection->beginTransaction();
		try {
			foreach ($tables as $table) {
				$newTable = $this->tableService->importTable($table);

				/*$views = json_decode($importSource->getFileContents('views.json'), true, 512, JSON_THROW_ON_ERROR);
				foreach ($views as $view) {
					if ($table['id'] === $view['tableId']) {
						$this->viewService->importView($newTable, $view);
					}
				}

				$favorites = json_decode($importSource->getFileContents('favorites.json'), true, 512, JSON_THROW_ON_ERROR);
				foreach ($favorites as $favorite) {
					if ($table['id'] === $favorite['node_id']) {
						$this->favoritesService->importFavorite($favorite['node_type'], $newTable->getId(), $favorite['user_id']);
					}
				}

				$columns = json_decode($importSource->getFileContents('columns.json'), true, 512, JSON_THROW_ON_ERROR);
				foreach ($columns as $column) {
					if ($table['id'] === $column['tableId']) {
						$this->columnService->importColumn($newTable, $column);
					}
				}

				$rows = json_decode($importSource->getFileContents('rows.json'), true, 512, JSON_THROW_ON_ERROR);
				foreach ($rows as $row) {
					if ($table['id'] === $row['tableId']) {
						$this->rowService->importRow($newTable, $row);
					}
				}*/

				foreach ($contexts as $context) {
					$newContext = $this->contextService->importContext($newTable, $context, $table['id']);
					if ($newContext !== null) {
						$contextIdMap[$context['id']] = $newContext->getId();
					}
				}

				$tableIdMap[$table['id']] = $newTable->getId();
			}

			$shares = json_decode($importSource->getFileContents('shares.json'), true, 512, JSON_THROW_ON_ERROR);
			foreach ($shares as $share) {
				if ($share['nodeType'] === 'table' && isset($tableIdMap[$share['nodeId']])) {
					$this->shareService->importShare($tableIdMap[$share['nodeId']], $share);
				} elseif ($share['nodeType'] === 'context' && isset($contextIdMap[$share['nodeId']])) {
					$this->shareService->importShare($contextIdMap[$share['nodeId']], $share);
				}
			}

			$connection->commit();
		} catch (\Throwable $e) {
			$connection->rollBack();
			throw $e;
		}

		$output->writeln('Import completed.');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): string
	{
		return 'tables';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDisplayName(): string
	{
		return $this->l10n->t('Tables');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string
	{
		return $this->l10n->t('All your tables, columns, rows (data), contexts, and sharing information—including all tables you own or are shared with you, their structure, content, and related metadata.');
	}
}
