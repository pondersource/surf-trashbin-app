<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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

namespace OCA\SURF_Trashbin\AppInfo;

$application = new Application();

$this->create('surf_ajax_trashbin_preview', 'ajax/preview.php')
	->actionInclude('surf_trashbin/ajax/preview.php');
$this->create('surf_trashbin_ajax_delete', 'ajax/delete.php')
	->actionInclude('surf_trashbin/ajax/delete.php');
$this->create('surf_trashbin_ajax_list', 'ajax/list.php')
	->actionInclude('surf_trashbin/ajax/list.php');
$this->create('surf_trashbin_ajax_undelete', 'ajax/undelete.php')
	->actionInclude('surf_trashbin/ajax/undelete.php');
