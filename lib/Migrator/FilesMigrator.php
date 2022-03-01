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
use OCA\Files_Versions\Storage as FilesVersionsStorage;
use OCA\UserMigration\Exception\UserMigrationException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ITagManager;
use OCP\IUser;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
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
		$userFolder = $this->root->getUserFolder($uid);

		if ($exportDestination->copyFolder($userFolder, Application::APP_ID."/files") === false) {
			throw new UserMigrationException("Could not copy files.");
		}

		try {
			$versionsFolder = $this->root->get('/'.$uid.'/'.FilesVersionsStorage::VERSIONS_ROOT);
			$output->writeln("Copying file versions…");
			if ($exportDestination->copyFolder($versionsFolder, Application::APP_ID."/".FilesVersionsStorage::VERSIONS_ROOT) === false) {
				throw new UserMigrationException("Could not copy files versions.");
			}
		} catch (NotFoundException $e) {
			$output->writeln("No file versions to export…");
		}

		$objectIds = $this->collectIds($userFolder, $userFolder->getPath());

		$output->writeln("Exporting file tags…");

		$tagger = $this->tagManager->load(Application::APP_ID, [], false, $uid);
		$tags = $tagger->getTagsForObjects(array_values($objectIds));
		$taggedFiles = array_filter(array_map(fn ($id) => $tags[$id] ?? [], $objectIds));
		if ($exportDestination->addFileContents(Application::APP_ID."/tags.json", json_encode($taggedFiles)) === false) {
			throw new UserMigrationException("Could not export tagged files information.");
		}

		$output->writeln("Exporting file systemtags…");

		$systemTags = $this->systemTagMapper->getTagIdsForObjects(array_values($objectIds), 'files');
		$systemTags = array_map(
			fn ($tagIds) => array_map(
				fn ($tag) => $tag->getName(),
				$this->systemTagManager->getTagsByIds($tagIds)
			),
			$systemTags
		);
		$systemTaggedFiles = array_filter(array_map(fn ($id) => $systemTags[$id] ?? [], $objectIds));
		if ($exportDestination->addFileContents(Application::APP_ID."/systemtags.json", json_encode($systemTaggedFiles)) === false) {
			throw new UserMigrationException("Could not export systemtagged files information.");
		}

		// TODO other files metadata should be exported as well if relevant.
	}

	private function collectIds(Folder $folder, string $rootPath, array &$objectIds = []): array {
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			$objectIds[preg_replace('/^'.preg_quote($rootPath, '/').'/', '', $node->getPath())] = $node->getId();
			if ($node instanceof Folder) {
				$this->collectIds($node, $rootPath, $objectIds);
			} elseif (!($node instanceof File)) {
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

		$userFolder = $this->root->getUserFolder($uid);

		if (in_array('/'.FilesVersionsStorage::VERSIONS_ROOT, $importSource->getFolderListing(Application::APP_ID))) {
			try {
				$versionsFolder = $this->root->get('/'.$uid.'/'.FilesVersionsStorage::VERSIONS_ROOT);
			} catch (NotFoundException $e) {
				$versionsFolder = $this->root->newFolder('/'.$uid.'/'.FilesVersionsStorage::VERSIONS_ROOT);
			}
			$output->writeln("Importing file versions…");
			if ($importSource->copyToFolder($versionsFolder, Application::APP_ID."/".FilesVersionsStorage::VERSIONS_ROOT) === false) {
				throw new UserMigrationException("Could not copy files versions.");
			}
		} else {
			$output->writeln("No file versions to import…");
		}

		$output->writeln("Importing file tags…");

		$taggedFiles = json_decode($importSource->getFileContents(Application::APP_ID."/tags.json"), true, 512, JSON_THROW_ON_ERROR);
		$tagger = $this->tagManager->load(Application::APP_ID, [], false, $uid);
		foreach ($taggedFiles as $path => $tags) {
			foreach ($tags as $tag) {
				if ($tagger->tagAs($userFolder->get($path)->getId(), $tag) === false) {
					throw new UserMigrationException("Failed to import tag $tag for path $path");
				}
			}
		}

		$output->writeln("Importing file systemtags…");

		$systemTaggedFiles = json_decode($importSource->getFileContents(Application::APP_ID."/systemtags.json"), true, 512, JSON_THROW_ON_ERROR);
		foreach ($systemTaggedFiles as $path => $systemTags) {
			$systemTagIds = [];
			foreach ($systemTags as $systemTag) {
				try {
					$systemTagObject = $this->systemTagManager->getTag($systemTag, true, true);
				} catch (TagNotFoundException $e) {
					$systemTagObject = $this->systemTagManager->createTag($systemTag, true, true);
				}
				$systemTagIds[] = $systemTagObject->getId();
			}
			if ($this->systemTagMapper->assignTags((string)$userFolder->get($path)->getId(), 'files', $systemTagIds) === false) {
				throw new UserMigrationException("Failed to import system tags for path $path");
			}
		}
	}
}
