<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\SURF_Trashbin\Tests;

use OC\Files\Filesystem;
use OC\Files\Storage\Local;
use OCA\SURF_Trashbin\Helper;
use OCA\SURF_Trashbin\Trashbin;
use OCP\Files\File;
use OCP\Files\FileInfo;

/**
 * Class TrashbinTest
 *
 * Extend TrashbinTestCase as we need to use Trashbin class that
 * has static functions that cannot be mocked
 *
 * @group DB
 */
class TrashbinTest extends TestCase {
	/**
	 * Login OWNER USER for each test case
	 */
	protected function setUp(): void {
		parent::setUp();
		self::loginHelper(self::TEST_OWNER_USER);
	}

	/**
	 * Test restoring a file
	 */
	public function testRestoreFileInRoot() {
		$userSession = \OC::$server->getUserSession();
		error_log('user is> '.$userSession->getUser()->getUID());

		$userFolder = \OC::$server->getUserFolder();
		// $folder = $userFolder->get('shared');
		$folder = $userFolder;
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($folder->nodeExists('file1.txt'));

		$file->delete();

		$this->assertFalse($folder->nodeExists('file1.txt'));

		// $filesInTrash = Helper::getTrashFiles(self::TEST_GROUP, '/', self::TEST_OWNER_USER, 'mtime', true);
		$filesInTrash = \OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_OWNER_USER, 'mtime', true);
		$this->assertCount(1, $filesInTrash);
	}
}
