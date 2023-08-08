<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bastien Ho <bastienho@urbancube.fr>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Florin Peter <github@florin-peter.de>
 * @author Georg Ehrke <georg@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Qingping Hou <dave2008713@gmail.com>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Sjors van der Pluijm <sjors@desjors.nl>
 * @author Steven Bühner <buehner@me.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\SURF_Trashbin;

use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Files_Versions\MetaStorage;
use OCP\Encryption\Keys\IStorage;
use OCP\Files\ForbiddenException;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;
use OCP\Lock\LockedException;
use OCP\User;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Trashbin {
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var EventDispatcher
	 */
	private $eventDispatcher;

	public function __construct(
		IRootFolder $rootFolder,
		IUrlGenerator $urlGenerator,
		EventDispatcher $eventDispatcher
	) {
		$this->rootFolder = $rootFolder;
		$this->urlGenerator = $urlGenerator;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * Whether versions have already be rescanned during this PHP request
	 *
	 * @var bool
	 */
	private static $scannedVersions = false;

	/**
	 * Ensure we don't need to scan the file during the move to trash
	 * by triggering the scan in the pre-hook
	 *
	 * @param array $params
	 */
	public static function ensureFileScannedHook($params) {
		try {
			self::getUidAndFilename($params['path']);
		} catch (NotFoundException $e) {
			// nothing to scan for non existing files
		}
	}

	/**
	 * get the UID of the owner of the file and the path to the file relative to
	 * owners files folder
	 *
	 * @param string $filename
	 * @return array
	 * @throws \OC\User\NoUserException
	 */
	public static function getUidAndFilename($filename) {
		//$uid = Filesystem::getOwner($filename);
		// $uid = (new View('/'))->getOwner($filename);
		$userManager = \OC::$server->getUserManager();
		// if the user with the UID doesn't exists, e.g. because the UID points
		// to a remote user with a federated cloud ID we use the current logged-in
		// user. We need a valid local user to move the file to the right trash bin
		// if (!$userManager->userExists($uid)) {
			$uid = User::getUser();
		// }
		// if (!$uid) {
		// 	// no owner, usually because of share link from ext storage
		// 	return [null, null];
		// }
		Filesystem::initMountPoints($uid);
		if ($uid != User::getUser()) {
			$info = Filesystem::getFileInfo($filename);
			$ownerView = new View('/' . $uid . '/files');
			try {
				$filename = $ownerView->getPath($info['fileid']);
			} catch (NotFoundException $e) {
				$filename = null;
			}
		}
		return [$uid, $filename];
	}

	/**
	 * get original location of files for user
	 *
	 * @param string $user
	 * @return array (filename => array (timestamp => original location))
	 */
	public static function getLocations($user) {
		$query = \OC_DB::prepare('SELECT `id`, `timestamp`, `location`'
			. ' FROM `*PREFIX*files_trash` WHERE `user`=?');
		$result = $query->execute([$user]);
		$array = [];
		while ($row = $result->fetchRow()) {
			if (isset($array[$row['id']])) {
				$array[$row['id']][$row['timestamp']] = $row['location'];
			} else {
				$array[$row['id']] = [$row['timestamp'] => $row['location']];
			}
		}
		return $array;
	}

	/**
	 * get original location of file
	 *
	 * @param string $user
	 * @param string $filename
	 * @param string $timestamp
	 * @return string original location
	 */
	public static function getLocation($user, $filename, $timestamp) {
		$query = \OC_DB::prepare('SELECT `location` FROM `*PREFIX*files_trash`'
			. ' WHERE `user`=? AND `id`=? AND `timestamp`=?');
		$result = $query->execute([$user, $filename, $timestamp])->fetchAll();
		if (isset($result[0]['location'])) {
			return $result[0]['location'];
		} else {
			return false;
		}
	}

	/**
	 * Sets up the trashbin for the given user
	 *
	 * @param string $user user id
	 * @return bool true if trashbin is setup and usable, false otherwise
	 */
	private static function setUpTrash($user) {
		$view = new View('/' . $user);
		if (!$view->is_dir('files_trashbin')) {
			$view->mkdir('files_trashbin');
		}

		if (!$view->isUpdatable('files_trashbin')) {
			// no trashbin access or denied
			return false;
		}

		if (!$view->is_dir('files_trashbin/files')) {
			$view->mkdir('files_trashbin/files');
		}
		if (!$view->is_dir('files_trashbin/versions')) {
			$view->mkdir('files_trashbin/versions');
		}
		if (!$view->is_dir('files_trashbin/keys')) {
			$view->mkdir('files_trashbin/keys');
		}

		return true;
	}

	/**
	 *
	 */
	public static function insertTrashEntry($user, $targetFilename, $targetLocation, $timestamp) {
		$query = \OC_DB::prepare("INSERT INTO `*PREFIX*files_trash` (`id`,`timestamp`,`location`,`user`) VALUES (?,?,?,?)");
		$result = $query->execute([$targetFilename, $timestamp, $targetLocation, $user]);
		if (!$result) {
			\OCP\Util::writeLog('files_trashbin', 'trash bin database couldn\'t be updated for the files owner', \OCP\Util::ERROR);
		}
	}

	/**
	 * Move a file or folder on storage level
	 *
	 * @param View $view
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	private static function move(View $view, $source, $target) {
		/** @var \OC\Files\Storage\Storage $sourceStorage */
		list($sourceStorage, $sourceInternalPath) = $view->resolvePath($source);
		/** @var \OC\Files\Storage\Storage $targetStorage */
		list($targetStorage, $targetInternalPath) = $view->resolvePath($target);
		/** @var \OC\Files\Storage\Storage $ownerTrashStorage */

		$result = $targetStorage->moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		if ($result) {
			$targetStorage->getUpdater()->renameFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		}
		return $result;
	}

	/**
	 * Copy a file or folder on storage level
	 *
	 * @param View $view
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	private static function copy(View $view, $source, $target) {
		/** @var \OC\Files\Storage\Storage $sourceStorage */
		list($sourceStorage, $sourceInternalPath) = $view->resolvePath($source);
		/** @var \OC\Files\Storage\Storage $targetStorage */
		list($targetStorage, $targetInternalPath) = $view->resolvePath($target);
		/** @var \OC\Files\Storage\Storage $ownerTrashStorage */

		$result = $targetStorage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		if ($result) {
			$targetStorage->getUpdater()->update($targetInternalPath);
		}
		return $result;
	}

	/**
	 * Restore a file or folder from trash bin
	 *
	 * @param string $filename full path to the file inside the trashbin,
	 * starting from the trashbin's root ("files_trashbin/files/"), including
	 * the deletion timestamp. For example, "file.txt.d123456" or
	 * "folder1.d123456/folder2/file.txt"
	 * @param string|null $targetLocation the place where the restored file
	 * will be. For example, $targetLocation = "folder1/restored.txt" will
	 * put the restored file ("file.txt.d123456") there. This is basically a rename.
	 * In particular, restoring "bigFolder.d12345" to the target location
	 * "largeContent/toobig" WON'T create "largeContent/toobig/bigFolder".
	 * If null is provided, we'll try to put the file in it's previous location
	 * if it still exists, otherwise the file will be restored in the root folder
	 * (with an unique name if needed) as fallback. Note that the previous location
	 * is only known for the top level directory in the root's trashbin, such as
	 * "file1.txt.d12345" or "topFolder.d98765", but not "folder.d12345/file.txt"
	 * or "folder.d98765/folder2"
	 *
	 * @return bool true on success, false otherwise
	 * @throws \OC\DatabaseException
	 * @throws ForbiddenException
	 * @throws LockedException
	 * @throws StorageNotAvailableException
	 */
	public static function restore($filename, $targetLocation = null) {
		$user = User::getUser();
		$view = new View('/' . $user);
		$nameOfFile = \basename($filename);
		$dirOfFile = \dirname($filename);

		$timestamp = null;
		$nameOfFileWithoutTimestamp = $nameOfFile;
		if ($dirOfFile === '/' || $dirOfFile === '.') {
			$delimiter = \strrpos($filename, '.d');
			$timestamp = \substr($filename, $delimiter+2);
			$filenameWithoutTimestamp = \substr($filename, 0, $delimiter);
			$nameOfFileWithoutTimestamp = ltrim($filenameWithoutTimestamp, '/');
		}

		if ($targetLocation === null) {
			$location = '';
			if ($timestamp) {
				$location = self::getLocation($user, $nameOfFileWithoutTimestamp, $timestamp);
				if ($location === false) {
					\OCP\Util::writeLog('files_trashbin', 'Original location of file ' . $filename .
						' not found in database, hence restoring into user\'s root instead', \OCP\Util::DEBUG);
				} else {
					// if location no longer exists, restore file in the root directory
					if ($location !== '/' &&
						(!$view->is_dir('files/' . $location) ||
							!$view->isCreatable('files/' . $location))
					) {
						$location = '';
					}
				}
			}

			// we need a  extension in case a file/dir with the same name already exists
			$uniqueFilename = self::getUniqueFilename($location, $nameOfFileWithoutTimestamp, $view);
			$targetLocation = $location . '/' . $uniqueFilename;
		}

		$source = Filesystem::normalizePath('files_trashbin/files/' . $filename);
		$target = Filesystem::normalizePath('files/' . $targetLocation);
		if (!$view->file_exists($source)) {
			return false;
		}
		$mtime = $view->filemtime($source);

		// restore file
		$restoreResult = $view->rename($source, $target);

		// handle the restore result
		if ($restoreResult) {
			$fakeRoot = $view->getRoot();
			$view->chroot('/' . $user . '/files');
			$view->touch('/' . $targetLocation, $mtime);
			$view->chroot($fakeRoot);
			\OCP\Util::emitHook('\OCA\Files_Trashbin\Trashbin', 'post_restore', ['filePath' => Filesystem::normalizePath('/' . $targetLocation),
				'trashPath' => Filesystem::normalizePath($filename)]);

			self::restoreVersionsFromTrashbin($view, $filename, $targetLocation);

			if ($timestamp) {
				$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_trash` WHERE `user`=? AND `id`=? AND `timestamp`=?');
				$query->execute([$user, $nameOfFileWithoutTimestamp, $timestamp]);
			}

			return true;
		}

		return false;
	}

	private static function recursiveMkdir($view, $targetDir) {
		$checkingDir = $targetDir;
		$toBeCreated = [];
		while (!$view->file_exists($checkingDir) && $checkingDir !== '/' && $checkingDir !== '.' && $checkingDir !== '') {
			$toBeCreated[] = $checkingDir;
			$checkingDir = \dirname($checkingDir);
		}

		// need to reverse the array
		$toBeCreated = \array_reverse($toBeCreated);
		foreach ($toBeCreated as $creatingDir) {
			$view->mkdir($creatingDir);
		}
	}

	/**
	 * restore all versions for the filename from trashbin in the target location
	 *
	 * @param View $view file view
	 * @param string $filename complete path to file in the trashbin, including the
	 * deletion timestamp, such as "folder1.d98765/folder2/file.txt"
	 * @param string $location location where the file will be restored
	 * @return false|null
	 */
	private static function restoreVersionsFromTrashbin(View $view, $filename, $targetLocation) {
		if (\OCP\App::isEnabled('files_versions')) {
			$user = User::getUser();
			$rootView = new View('/');

			$target = Filesystem::normalizePath('/' . $targetLocation);
			error_log('TARGET IS: '.$target);

			list($owner, $ownerPath) = self::getUidAndFilename($target);

			// file has been deleted in between
			if (empty($ownerPath)) {
				return false;
			}

			// Temporary
			$config = \OC::$server->getConfig();
			$metaEnabled = ($config->getSystemValue('file_storage.save_version_metadata', false) === true);
			/** @var MetaStorage|null  $metaStorage */
			$metaStorage = null;

			if ($metaEnabled) {
				$metaStorage = \OC::$server->query(MetaStorage::class);
			}

			if ($view->is_dir('/files_trashbin/versions/' . $filename)) {
				self::recursiveMkdir($rootView, "{$owner}/files_versions/" . \dirname($ownerPath));
				$rootView->rename(Filesystem::normalizePath($user . '/files_trashbin/versions/' . $filename), Filesystem::normalizePath($owner . '/files_versions/' . $ownerPath));
			} else {
				$dir = \dirname($filename);
				$filenameOnly = \basename($filename);

				if ($dir === '/' || $dir === '.') {
					$delimiter = \strrpos($filenameOnly, '.d');
					$timestamp = \substr($filenameOnly, $delimiter+2);
					$filenameOnlyWithoutTimestamp = \substr($filenameOnly, 0, $delimiter);
					$dirAndFilename = $filenameOnlyWithoutTimestamp;
				} else {
					$timestamp = null;
					$filenameOnlyWithoutTimestamp = $filenameOnly;
					$dirAndFilename = "{$dir}/{$filenameOnly}";
				}
				
				if ($metaEnabled && $timestamp) {
					$src = '/files_trashbin/versions/' . $dirAndFilename . MetaStorage::CURRENT_FILE_PREFIX . '.d' . $timestamp;
					$dst = '/files_versions/' . $ownerPath . MetaStorage::CURRENT_FILE_PREFIX;
					$metaStorage->renameOrCopy('rename', $src . MetaStorage::VERSION_FILE_EXT, $user, $dst . MetaStorage::VERSION_FILE_EXT, $owner);
				}

				$versions = self::getVersionsFromTrash($filenameOnlyWithoutTimestamp, $timestamp, $user);
				foreach ($versions as $v) {
					if ($timestamp) {
						$src = '/files_trashbin/versions/' . $dirAndFilename . '.v' . $v . '.d' . $timestamp;
						$dst = '/files_versions/' . $ownerPath . '.v' . $v;
						self::recursiveMkdir($rootView, $owner . \dirname($dst));
						$rootView->rename("$user$src", "$owner$dst");
						if ($metaEnabled) {
							$metaStorage->renameOrCopy('rename', $src . MetaStorage::VERSION_FILE_EXT, $user, $dst . MetaStorage::VERSION_FILE_EXT, $owner);
						}
					} else {
						$src = '/files_trashbin/versions/' . $dirAndFilename . '.v' . $v;
						$dst = '/files_versions/' . $ownerPath . '.v' . $v;
						self::recursiveMkdir($rootView, $owner . \dirname($dst));
						$rootView->rename("$user$src", "$owner$dst");
						if ($metaEnabled) {
							$metaStorage->renameOrCopy('rename', $src . MetaStorage::VERSION_FILE_EXT, $user, $dst . MetaStorage::VERSION_FILE_EXT, $owner);
						}
					}
				}
			}
		}
	}

	/**
	 * delete all files from the trash
	 */
	public static function deleteAll() {
		$user = User::getUser();
		$view = new View('/' . $user);
		$fileInfos = $view->getDirectoryContent('files_trashbin/files');

		// Array to store the relative path in (after the file is deleted, the view won't be able to relativise the path anymore)
		$filePaths = [];
		foreach ($fileInfos as $fileInfo) {
			$filePaths[] = $view->getRelativePath($fileInfo->getPath());
		}
		unset($fileInfos); // save memory

		// Bulk PreDelete-Hook
		\OC_Hook::emit('\OCP\Trashbin', 'preDeleteAll', ['paths' => $filePaths]);

		// Single-File Hooks
		foreach ($filePaths as $path) {
			self::emitTrashbinPreDelete($user, $path);
		}

		// actual file deletion
		$view->deleteAll('files_trashbin');
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_trash` WHERE `user`=?');
		$query->execute([$user]);

		// Bulk PostDelete-Hook
		\OC_Hook::emit('\OCP\Trashbin', 'deleteAll', ['paths' => $filePaths]);

		// Single-File Hooks
		foreach ($filePaths as $path) {
			self::emitTrashbinPostDelete($user, $path);
		}

		$view->mkdir('files_trashbin');
		$view->mkdir('files_trashbin/files');

		return true;
	}

	/**
	 * wrapper function to emit the 'preDelete' hook of \OCP\Trashbin before a file is deleted
	 *
	 * @param string $uid
	 * @param string $path
	 */
	protected static function emitTrashbinPreDelete($uid, $path) {
		\OC_Hook::emit(
			'\OCP\Trashbin',
			'preDelete',
			['path' => $path, 'user' => $uid]
		);
	}

	/**
	 * wrapper function to emit the 'delete' hook of \OCP\Trashbin after a file has been deleted
	 *
	 * @param string $uid
	 * @param string $path
	 */
	protected static function emitTrashbinPostDelete($uid, $path) {
		\OC_Hook::emit(
			'\OCP\Trashbin',
			'delete',
			['path' => $path, 'user' => $uid]
		);
	}

	/**
	 * delete file from trash bin permanently
	 *
	 * @param string $filename full path to the file inside the trashbin,
	 * starting from the trashbin's root ("files_trashbin/files/"), including
	 * the deletion timestamp. For example, "file.txt.d123456" or
	 * "folder1.d123456/folder2/file.txt"
	 * @param string $user the user owning the trashbin
	 *
	 * @return int size of deleted files
	 * @throws \OC\DatabaseException
	 * @throws ForbiddenException
	 * @throws LockedException
	 * @throws StorageNotAvailableException
	 */
	public static function delete($filename, $user) {
		$view = new View('/' . $user);
		$size = 0;

		$dirOfFile = \dirname($filename);
		$timestamp = null;

		if ($dirOfFile === '/' || $dirOfFile === '.') {
			$delimiter = \strrpos($filename, '.d');
			$filenameWithoutTimestamp = ltrim(\substr($filename, 0, $delimiter), '/');
			$timestamp = \substr($filename, $delimiter+2);
			$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_trash` WHERE `user`=? AND `id`=? AND `timestamp`=?');
			$query->execute([$user, $filenameWithoutTimestamp, $timestamp]);
		}

		$size += self::deleteVersions($view, $filename, $user);

		if ($view->is_dir('/files_trashbin/files/' . $filename)) {
			$size += self::calculateSize(new View('/' . $user . '/files_trashbin/files/' . $filename));
		} else {
			$size += $view->filesize('/files_trashbin/files/' . $filename);
		}
		self::emitTrashbinPreDelete($user, "/files_trashbin/files/$filename");
		$view->unlink('/files_trashbin/files/' . $filename);
		self::emitTrashbinPostDelete($user, "/files_trashbin/files/$filename");

		return $size;
	}

	/**
	 * Permanently delete the versions of the file from the trashbin
	 * @param View $view
	 * @param string $file complete path to file in the trashbin, including the
	 * deletion timestamp, such as "folder1.d98765/folder2/file.txt"
	 * @param string $user the user owning the trashbin
	 * @return int
	 */
	private static function deleteVersions(View $view, $file, $user) {
		$size = 0;
		if (\OCP\App::isEnabled('files_versions')) {
			if ($view->is_dir('files_trashbin/versions/' . $file)) {
				$size += self::calculateSize(new View('/' . $user . '/files_trashbin/versions/' . $file));
				$view->unlink('files_trashbin/versions/' . $file);
			} else {
				$dir = \dirname($file);
				$filenameOnly = \basename($file);

				if ($dir === '/' || $dir === '.') {
					$delimiter = \strrpos($filenameOnly, '.d');
					$timestamp = \substr($filenameOnly, $delimiter+2);
					$filenameOnlyWithoutTimestamp = \substr($filenameOnly, 0, $delimiter);
					$dirAndFilename = $filenameOnlyWithoutTimestamp;
				} else {
					$timestamp = null;
					$filenameOnlyWithoutTimestamp = $filenameOnly;
					$dirAndFilename = "{$dir}/{$filenameOnly}";
				}
				$versions = self::getVersionsFromTrash($filenameOnlyWithoutTimestamp, $timestamp, $user);
				foreach ($versions as $v) {
					if ($timestamp) {
						$size += $view->filesize('/files_trashbin/versions/' . $dirAndFilename . '.v' . $v . '.d' . $timestamp);
						$view->unlink('/files_trashbin/versions/' . $dirAndFilename . '.v' . $v . '.d' . $timestamp);
					} else {
						$size += $view->filesize('/files_trashbin/versions/' . $dirAndFilename . '.v' . $v);
						$view->unlink('/files_trashbin/versions/' . $dirAndFilename . '.v' . $v);
					}
				}
			}
		}
		return $size;
	}

	/**
	 * check to see whether a file exists in trashbin
	 *
	 * @param string $filename complete path to file in the trashbin, including the
	 * deletion timestamp, such as "folder1.d98765/folder2/file.txt"
	 * @param int $timestamp of deletion time
	 * @return bool true if file exists, otherwise false
	 */
	public static function file_exists($filename) {
		$user = User::getUser();
		$view = new View('/' . $user);

		$target = Filesystem::normalizePath('files_trashbin/files/' . $filename);
		return $view->file_exists($target);
	}

	/**
	 * deletes used space for trash bin in db if user was deleted
	 *
	 * @param string $uid id of deleted user
	 * @return bool result of db delete operation
	 */
	public static function deleteUser($uid) {
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_trash` WHERE `user`=?');
		return $query->execute([$uid]);
	}

	/**
	 * find all versions which belong to the file we want to restore
	 *
	 * @param string $filename name of the file which should be restored
	 * @param int $timestamp timestamp when the file was deleted
	 * @return array
	 */
	private static function getVersionsFromTrash($filename, $timestamp, $user) {
		$view = new View('/' . $user . '/files_trashbin/versions');
		$versions = [];

		//force rescan of versions, local storage may not have updated the cache
		if (!self::$scannedVersions) {
			/** @var \OC\Files\Storage\Storage $storage */
			list($storage, ) = $view->resolvePath('/');
			$storage->getScanner()->scan('files_trashbin/versions');
			self::$scannedVersions = true;
		}

		if ($timestamp) {
			// fetch for old versions
			$matches = $view->searchRaw($filename . '.v%.d' . $timestamp);
			$offset = -\strlen($timestamp) - 2;
		} else {
			$matches = $view->searchRaw($filename . '.v%');
		}

		if (\is_array($matches)) {
			foreach ($matches as $ma) {
				if ($timestamp) {
					$parts = \explode('.v', \substr($ma['path'], 0, $offset));
					$versions[] = (\end($parts));
				} else {
					$parts = \explode('.v', $ma['path']);
					$versions[] = (\end($parts));
				}
			}
		}
		return $versions;
	}

	/**
	 * find unique extension for restored file if a file with the same name already exists
	 *
	 * @param string $location where the file should be restored
	 * @param string $filename name of the file
	 * @param View $view filesystem view relative to users root directory
	 * @return string with unique extension
	 */
	private static function getUniqueFilename($location, $filename, View $view) {
		$ext = \pathinfo($filename, PATHINFO_EXTENSION);
		$name = \pathinfo($filename, PATHINFO_FILENAME);
		$l = \OC::$server->getL10N('files_trashbin');

		$location = '/' . \trim($location, '/');

		// if extension is not empty we set a dot in front of it
		if ($ext !== '') {
			$ext = '.' . $ext;
		}

		if ($view->file_exists('files' . $location . '/' . $filename)) {
			$i = 2;
			$uniqueName = $name . " (" . $l->t("restored") . ")" . $ext;
			while ($view->file_exists('files' . $location . '/' . $uniqueName)) {
				$uniqueName = $name . " (" . $l->t("restored") . " " . $i . ")" . $ext;
				$i++;
			}

			return $uniqueName;
		}

		return $filename;
	}

	/**
	 * get the size from a given root folder
	 *
	 * @param View $view file view on the root folder
	 * @return integer size of the folder
	 */
	private static function calculateSize($view) {
		$root = \OC::$server->getConfig()->getSystemValue('datadirectory') . $view->getAbsolutePath('');
		if (!\file_exists($root)) {
			return 0;
		}
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root), \RecursiveIteratorIterator::CHILD_FIRST);
		$size = 0;

		/**
		 * RecursiveDirectoryIterator on an NFS path isn't iterable with foreach
		 * This bug is fixed in PHP 5.5.9 or before
		 * See #8376
		 */
		$iterator->rewind();
		while ($iterator->valid()) {
			$path = $iterator->current();
			$relpath = \substr($path, \strlen($root) - 1);
			if (!$view->is_dir($relpath)) {
				$size += $view->filesize($relpath);
			}
			$iterator->next();
		}
		return $size;
	}

	/**
	 * get current size of trash bin from a given user
	 *
	 * @param string $user user who owns the trash bin
	 * @return integer trash bin size
	 */
	public static function getTrashbinSize($user) {
		$view = new View('/' . $user);
		$fileInfo = $view->getFileInfo('/files_trashbin');
		return isset($fileInfo['size']) ? $fileInfo['size'] : 0;
	}

	/**
	 * Resolves web URL that points to the trashbin view of the given file
	 *
	 * @param string $uid user id
	 * @param string $fileId file id
	 * @return string|null view URL or null if the file is not found or not accessible
	 */
	public function resolvePrivateLink($uid, $fileId) {
		if ($this->rootFolder->nodeExists($uid . '/files_trashbin/files/')) {
			$baseFolder = $this->rootFolder->get($uid . '/files_trashbin/files/');
			'@phan-var \OCP\Files\Folder $baseFolder';
			$files = $baseFolder->getById($fileId);
			if (!empty($files)) {
				$params['view'] = 'trashbin';
				$file = \current($files);
				if ($file instanceof Folder) {
					// set the full path to enter the folder
					$params['dir'] = $baseFolder->getRelativePath($file->getPath());
				} else {
					// set parent path as dir
					$params['dir'] = $baseFolder->getRelativePath($file->getParent()->getPath());
					// and scroll to the entry
					$params['scrollto'] = $file->getName();
				}
				return $this->urlGenerator->linkToRoute('files.view.index', $params);
			}
		}

		return null;
	}

	/**
	 * check if trash bin is empty for a given user
	 *
	 * @param string $user
	 * @return bool
	 */
	public static function isEmpty($user) {
		$view = new View('/' . $user . '/files_trashbin');
		if ($view->is_dir('/files') && $dh = $view->opendir('/files')) {
			while ($file = \readdir($dh)) {
				if (!Filesystem::isIgnoredDir($file)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param $path
	 * @return string
	 */
	public static function preview_icon($path) {
		return \OCP\Util::linkToRoute('core_ajax_trashbin_preview', ['x' => 32, 'y' => 32, 'file' => $path]);
	}
}
