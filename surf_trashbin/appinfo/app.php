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

if (\class_exists('OCA\Files\App')) {
	\OCA\Files\App::getNavigationManager()->add(function () {
		// TODO Get user groups that have functional users. Then create one row per group.
		return [
			'id' => 'trashbin',
			'appname' => 'surf_trashbin',
			'script' => 'list.php',
			'order' => 49,
			'name' => 'bioinformatics',
		];
	});

	$app = new \OCA\SURF_Trashbin\AppInfo\Application();
}
