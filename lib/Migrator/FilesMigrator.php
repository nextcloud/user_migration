<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
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

namespace OCA\UserMigration\Migrator;

use OCA\Files\AppInfo\Application;
use OCA\UserMigration\Exception\UserMigrationException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\ITagManager;
use OCP\ITags;
use OCP\IUser;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

class FilesMigrator implements IMigrator {
	use TMigratorBasicVersionHandling;

	protected IRootFolder $root;

	protected ITagManager $tagManager;

	protected ISystemTagManager $systemTagManager;

	protected ISystemTagObjectMapper $systemTagMapper;

	public function __construct(
		IRootFolder $rootFolder,
		ITagManager $tagManager,
		ISystemTagManager $systemTagManager,
		ISystemTagObjectMapper $systemTagMapper
	) {
		$this->root = $rootFolder;
		$this->tagManager = $tagManager;
		$this->systemTagManager = $systemTagManager;
		$this->systemTagMapper = $systemTagMapper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output
	): void {
		$output->writeln("Copying files…");

		$uid = $user->getUID();

		if ($exportDestination->copyFolder($this->root->getUserFolder($uid), Application::APP_ID."/files") === false) {
			throw new UserMigrationException("Could not copy files.");
		}

		$objectIds = $this->collectIds($this->root->getUserFolder($uid));

		$output->writeln("Exporting file tags…");

		$tagger = $this->tagManager->load(Application::APP_ID, [], false, $uid);
		$tags = $tagger->getTagsForObjects(array_values($objectIds));
		$taggedFiles = array_filter(array_map(fn($id) => $tags[$id] ?? [], $objectIds));
		$output->writeln(print_r($taggedFiles, TRUE));
		if ($exportDestination->addFileContents(Application::APP_ID."/tags.json", json_encode($taggedFiles)) === false) {
			throw new UserMigrationException("Could not export tagged files information.");
		}

		$output->writeln("Exporting file systemtags…");

		$systemTags = $this->systemTagMapper->getTagIdsForObjects(array_values($objectIds), 'files');
		$systemTags = array_map(
			fn($tagIds) => array_map(
				fn($tag) => $tag->getName(),
				$this->systemTagManager->getTagsByIds($tagIds)
			),
			$systemTags
		);
		$systemTaggedFiles = array_filter(array_map(fn($id) => $systemTags[$id] ?? [], $objectIds));
		$output->writeln(print_r($systemTaggedFiles, TRUE));
		if ($exportDestination->addFileContents(Application::APP_ID."/systemtags.json", json_encode($systemTaggedFiles)) === false) {
			throw new UserMigrationException("Could not export systemtagged files information.");
		}

		// TODO other files metadata should be exported as well if relevant.
	}

	private function collectIds(Folder $folder, array &$objectIds = []): array
	{
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			$objectIds[$node->getPath()] = $node->getId();
			if ($node instanceof File) {
			} elseif ($node instanceof Folder) {
				$this->collectIds($node, $objectIds);
			} else {
				throw new UserMigrationException("Unsupported node type: ".get_class($node));
			}
		}

		return $objectIds;
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output
	): void {
		if ($importSource->getMigratorVersion(static::class) === null) {
			$output->writeln("No version for ".static::class.", skipping import…");
			return;
		}
		$output->writeln("Importing files…");

		$uid = $user->getUID();

		if ($importSource->copyToFolder($this->root->getUserFolder($uid), Application::APP_ID."/files") === false) {
			throw new UserMigrationException("Could not import files.");
		}

		//TODO import tags
	}
}
