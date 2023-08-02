<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Florin Peter <github@florin-peter.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

use OCP\User;

if (\class_exists('OCA\Files\App')) {
	$user = \OC::$server->getUserSession()->getUser();

	if (isset($user)) {
		$uid = $user->getUID();
		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();
		$groups = $groupManager->getUserGroups($user);
		$groups = \array_filter($groups, function($group) use ($userManager, $groupManager) {
			$gid = $group->getGID();
			$uid = 'f_'.$gid;
			if (!$userManager->userExists($uid)) {
				return false;
			}
			$subAdminOfGroups = $groupManager->getSubAdmin()->getSubAdminsGroups($userManager->get($uid));
			$subAdminOfGroups = \array_filter($subAdminOfGroups, function($group) use ($gid) {
				return $group->getGID() === $gid;
			});
			return \count($subAdminOfGroups) > 0;
		});
		$groups = \array_map(function($group) {
			return $group->getGID();
		}, $groups);
		$groups = \array_values($groups);

		for ($i = 0; $i < \count($groups); $i++) {
			$group = $groups[$i];
			\OCA\Files\App::getNavigationManager()->add(function () use ($group, $i) {
				return [
					'id' => $group,
					'appname' => 'surf_trashbin',
					'script' => 'list.php',
					'order' => 40 + $i,
					'name' => $group,
				];
			});
		}
	}

	$app = new \OCA\SURF_Trashbin\AppInfo\Application();
}
