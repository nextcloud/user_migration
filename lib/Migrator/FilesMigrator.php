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
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ITagManager;
use OCP\IUser;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OCP\UserMigration\UserMigrationException;
use Symfony\Component\Console\Output\OutputInterface;

class FilesMigrator implements IMigrator {
	use TMigratorBasicVersionHandling;

	protected const PATH_FILES = Application::APP_ID.'/files';
	protected const PATH_VERSIONS = Application::APP_ID.'/files_versions';
	protected const PATH_TAGS = Application::APP_ID.'/tags.json';
	protected const PATH_SYSTEMTAGS = Application::APP_ID.'/systemtags.json';
	protected const PATH_COMMENTS = Application::APP_ID.'/comments.json';

	protected IRootFolder $root;

	protected ITagManager $tagManager;

	protected ISystemTagManager $systemTagManager;

	protected ISystemTagObjectMapper $systemTagMapper;

	protected ICommentsManager $commentsManager;

	protected IL10N $l10n;

	public function __construct(
		IRootFolder $rootFolder,
		ITagManager $tagManager,
		ISystemTagManager $systemTagManager,
		ISystemTagObjectMapper $systemTagMapper,
		ICommentsManager $commentsManager,
		IL10N $l10n
	) {
		$this->root = $rootFolder;
		$this->tagManager = $tagManager;
		$this->systemTagManager = $systemTagManager;
		$this->systemTagMapper = $systemTagMapper;
		$this->commentsManager = $commentsManager;
		$this->l10n = $l10n;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output
	): void {
		$output->writeln("Exporting files…");

		$uid = $user->getUID();
		$userFolder = $this->root->getUserFolder($uid);

		if ($exportDestination->copyFolder($userFolder, static::PATH_FILES) === false) {
			throw new UserMigrationException("Could not export files.");
		}

		try {
			$versionsFolder = $this->root->get('/'.$uid.'/'.FilesVersionsStorage::VERSIONS_ROOT);
			$output->writeln("Exporting file versions…");
			if ($exportDestination->copyFolder($versionsFolder, static::PATH_VERSIONS) === false) {
				throw new UserMigrationException("Could not export files versions.");
			}
		} catch (NotFoundException $e) {
			$output->writeln("No file versions to export…");
		}

		$objectIds = $this->collectIds($userFolder, $userFolder->getPath());

		$output->writeln("Exporting file tags…");

		$tagger = $this->tagManager->load(Application::APP_ID, [], false, $uid);
		$tags = $tagger->getTagsForObjects(array_values($objectIds));
		$taggedFiles = array_filter(array_map(fn ($id) => $tags[$id] ?? [], $objectIds));
		if ($exportDestination->addFileContents(static::PATH_TAGS, json_encode($taggedFiles)) === false) {
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
		if ($exportDestination->addFileContents(static::PATH_SYSTEMTAGS, json_encode($systemTaggedFiles)) === false) {
			throw new UserMigrationException("Could not export systemtagged files information.");
		}

		$output->writeln("Exporting file comments…");

		$comments = [];
		foreach ($objectIds as $path => $objectId) {
			$fileComments = $this->commentsManager->getForObject('files', $objectId);
			if (!empty($fileComments)) {
				$comments[$path] = array_map(
					function (IComment $comment): array {
						return [
							'message' => $comment->getMessage(),
							'verb' => $comment->getVerb(),
							'actorType' => $comment->getActorType(),
							'actorId' => $comment->getActorId(),
							'creationDateTime' => $comment->getCreationDateTime()->format(\DateTime::ISO8601),
						];
					},
					$fileComments
				);
			}
		}
		if ($exportDestination->addFileContents(static::PATH_COMMENTS, json_encode($comments)) === false) {
			throw new UserMigrationException("Could not export file comments.");
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
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln("No version for ".$this->getId().", skipping import…");
			return;
		}
		$output->writeln("Importing files…");

		$uid = $user->getUID();

		if ($importSource->copyToFolder($this->root->getUserFolder($uid), static::PATH_FILES) === false) {
			throw new UserMigrationException("Could not import files.");
		}

		$userFolder = $this->root->getUserFolder($uid);

		if ($importSource->pathExists(static::PATH_VERSIONS)) {
			try {
				$versionsFolder = $this->root->get('/'.$uid.'/'.FilesVersionsStorage::VERSIONS_ROOT);
			} catch (NotFoundException $e) {
				$versionsFolder = $this->root->newFolder('/'.$uid.'/'.FilesVersionsStorage::VERSIONS_ROOT);
			}
			$output->writeln("Importing file versions…");
			if ($importSource->copyToFolder($versionsFolder, static::PATH_VERSIONS) === false) {
				throw new UserMigrationException("Could not import files versions.");
			}
		} else {
			$output->writeln("No file versions to import…");
		}

		$output->writeln("Importing file tags…");

		$taggedFiles = json_decode($importSource->getFileContents(static::PATH_TAGS), true, 512, JSON_THROW_ON_ERROR);
		$tagger = $this->tagManager->load(Application::APP_ID, [], false, $uid);
		foreach ($taggedFiles as $path => $tags) {
			foreach ($tags as $tag) {
				if ($tagger->tagAs($userFolder->get($path)->getId(), $tag) === false) {
					throw new UserMigrationException("Failed to import tag $tag for path $path");
				}
			}
		}

		$output->writeln("Importing file systemtags…");

		$systemTaggedFiles = json_decode($importSource->getFileContents(static::PATH_SYSTEMTAGS), true, 512, JSON_THROW_ON_ERROR);
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

		$output->writeln("Importing file comments…");

		$comments = json_decode($importSource->getFileContents(static::PATH_COMMENTS), true, 512, JSON_THROW_ON_ERROR);
		foreach ($comments as $path => $fileComments) {
			foreach ($fileComments as $fileComment) {
				if (($fileComment['actorType'] === 'users') || ($fileComment['actorType'] === ICommentsManager::DELETED_USER)) {
					$actorId = $fileComment['actorId'];
					$actorType = $fileComment['actorType'];
					if (($fileComment['actorType'] === 'users') && ($actorId === $importSource->getOriginalUid())) {
						/* Only import comments from imported user, and update the uid */
						$actorId = $uid;
					} else {
						$actorId = ICommentsManager::DELETED_USER;
						$actorType = ICommentsManager::DELETED_USER;
					}
					$commentObject = $this->commentsManager->create($actorType, $actorId, 'files', (string)$userFolder->get($path)->getId());
					$commentObject->setMessage($fileComment['message']);
					$commentObject->setVerb($fileComment['verb']);
					$commentObject->setCreationDateTime(new \DateTime($fileComment['creationDateTime']));
					if ($this->commentsManager->save($commentObject) === false) {
						throw new UserMigrationException("Failed to import comment on path $path");
					}
				}
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): string {
		return 'files';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Files');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string {
		// TODO handle migrator dependency resoluton as TrashbinMigrator is dependent on FilesMigrator
		return $this->l10n->t('Files including deleted files, versions, comments, collaborative tags, and favorites');
	}
}
