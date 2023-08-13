<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OC\Files\FileInfo;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;

class Helper {
	/**
	 * Retrieves the contents of a trash bin directory.
	 *
	 * @param string $groupName Name of the group to retrieve the trash for
	 * @param string $dir path to the directory inside the trashbin
	 * or empty to retrieve the root of the trashbin
	 * @param string $user
	 * @param string $sortAttribute attribute to sort on or empty to disable sorting
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @param bool $addExtraData if true, file info will include original path
	 * @return \OCP\Files\FileInfo[]
	 */
	public static function getTrashFiles($groupName, $dir, $user, $sortAttribute = '', $sortDescending = false, $addExtraData = true) {
		$userSession = \OC::$server->getUserSession();
		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();
		$mountManager = \OC::$server->getMountManager();
		$mountConfigManager = \OC::$server->getMountProviderCollection();

		$userName = 'f_'.$groupName;

		if (!$groupManager->isInGroup($user, $groupName) || !$userManager->userExists($userName)) {
			return [];
		}

		$fUser = $userManager->get($userName);
		$userSession->setUser($fUser);

		$fUserMount = $mountConfigManager->getHomeMountForUser($fUser);
		$mountManager->addMount($fUserMount);

		$result = \OCA\Files_Trashbin\Helper::getTrashFiles($dir, $userName, $sortAttribute, $sortDescending);

		$realUser = $userManager->get($user);
		$userSession->setUser($realUser);

		return $result;
	}

	/**
	 * Format file infos for JSON
	 *
	 * @param \OCP\Files\FileInfo[] $fileInfos file infos
	 */
	public static function formatFileInfos($fileInfos) {
		$files = [];
		$id = 0;
		foreach ($fileInfos as $i) {
			$entry = \OCA\Files\Helper::formatFileInfo($i);
			$entry['id'] = $id++;
			$entry['etag'] = $entry['mtime']; // add fake etag, it is only needed to identify the preview image
			$entry['permissions'] = \OCP\Constants::PERMISSION_READ;
			if ($entry['mimetype'] !== 'httpd/unix-directory') {
				$entry['mimetype'] = \OC::$server->getMimeTypeDetector()->detectPath($entry['name']);
			}
			$files[] = $entry;
		}
		return $files;
	}
}
