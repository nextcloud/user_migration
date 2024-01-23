<?php

declare(strict_types=1);

/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
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

namespace OCA\UserMigration\Tests\Unit;

use OCA\UserMigration\Migrator\FilesMigrator;
use OCP\Comments\ICommentsManager;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ITagManager;
use OCP\IUser;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class FilesMigratorTest extends TestCase {
	private IRootFolder $rootFolder;
	private Folder $userFolder;
	private ITagManager $tagManager;
	private ISystemTagManager $systemTagManager;
	private ISystemTagObjectMapper $systemTagMapper;
	private ICommentsManager $commentsManager;
	private IL10N $l10n;
	private FilesMigrator $filesMigrator;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->tagManager = $this->createMock(ITagManager::class);
		$this->systemTagManager = $this->createMock(ISystemTagManager::class);
		$this->systemTagMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->userFolder = $this->createMock(Folder::class);

		$this->rootFolder
			->method('getUserFolder')
			->willReturn($this->userFolder);

		$this->filesMigrator = new FilesMigrator(
			$this->rootFolder,
			$this->tagManager,
			$this->systemTagManager,
			$this->systemTagMapper,
			$this->commentsManager,
			$this->l10n,
		);
	}

	public function testGetEstimatedExportSize(): void {
		$user = $this->createMock(IUser::class);
		$this->assertEquals(
			100,
			$this->filesMigrator->getEstimatedExportSize($user)
		);
	}

	public function testExport(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('testuser');
		$exportDestination = $this->createMock(IExportDestination::class);
		$output = $this->createMock(OutputInterface::class);
		$this->filesMigrator->export($user, $exportDestination, $output);
	}

	public function testImport(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('testuser');
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(OutputInterface::class);
		$this->filesMigrator->import($user, $importSource, $output);
	}
}
