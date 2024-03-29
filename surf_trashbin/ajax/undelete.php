<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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

OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
\OC::$server->getSession()->close();

$files = $_POST['files'] ?? null;
$dir = '/';


$groupName = $_POST['group'];

$userSession = \OC::$server->getUserSession();
$userManager = \OC::$server->getUserManager();
$groupManager = \OC::$server->getGroupManager();
$mountManager = \OC::$server->getMountManager();
$mountConfigManager = \OC::$server->getMountProviderCollection();
$realUser = \OCP\User::getUser();

if (isset($_POST['dir'])) {
	$dir = \rtrim((string)$_POST['dir'], '/'). '/';
}
$allFiles = false;
if (isset($_POST['allfiles']) && (string)$_POST['allfiles'] === 'true') {
	$allFiles = true;
	$list = [];
	$dirListing = true;
	if ($dir === '' || $dir === '/') {
		$dirListing = false;
	}
	foreach (OCA\SURF_Trashbin\Helper::getTrashFiles($groupName, $dir, \OCP\User::getUser(), '', false, false) as $file) {
		$fileName = $file['name'];
		if (!$dirListing) {
			$fileName .= '.d' . $file['mtime'];
		}
		$list[] = $fileName;
	}
} else {
	$list = \json_decode($files);
}

$fUserName = 'f_'.$groupName;
$fUser = $userManager->get($fUserName);
$userSession->setUser($fUser);

$fUserMount = $mountConfigManager->getHomeMountForUser($fUser);
$mountManager->addMount($fUserMount);

$error = [];
$success = [];

$i = 0;
foreach ($list as $file) {
	$file = \ltrim($file, '/');
	$filename = $dir . $file;  // dir already contains a trailing "/"

	// "restore" will require the whole path inside the trashbin including
	// the deletion timestamp in the filename, such as "/file.txt.d12345"
	// or "/folder.d12345/file.txt"
	if (!OCA\SURF_Trashbin\Trashbin::restore($filename)) {
		$error[] = $filename;
		\OCP\Util::writeLog('surf_trashbin', 'can\'t restore ' . $filename, \OCP\Util::DEBUG);
	} else {
		$success[$i]['filename'] = $file;
		$i++;
	}
}

if ($error) {
	$filelist = '';
	foreach ($error as $e) {
		$filelist .= $e.', ';
	}
	$l = OC::$server->getL10N('files_trashbin');
	$message = $l->t("Couldn't restore %s", [\rtrim($filelist, ', ')]);
	OCP\JSON::error(["data" => ["message" => $message,
										  "success" => $success, "error" => $error]]);
} else {
	OCP\JSON::success(["data" => ["success" => $success]]);
}
