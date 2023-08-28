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
	 * Test trashbin
	 */
	public function testTrashbin() {
		$userSession = \OC::$server->getUserSession();

		// Setup
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->get(self::TEST_SHARED_FOLDER);
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($folder->nodeExists('file1.txt'));

		$file->delete();

		$this->assertFalse($folder->nodeExists('file1.txt'));

		// Test list
		$filesInTrash = Helper::getTrashFiles(self::TEST_GROUP, '/', self::TEST_OWNER_USER, 'mtime', true);
		$this->assertCount(1, $filesInTrash);

		$deletedFileData = $filesInTrash[0]->getData();
		$path = $deletedFileData['name'].'.d'.$deletedFileData['mtime'];

		$filesInTrash = Helper::getTrashFiles(self::TEST_RANDOM_GROUP, '/', self::TEST_OWNER_USER, 'mtime', true);
		$this->assertCount(0, $filesInTrash);

		// Test restore
		$mountPoint = $this->setFunctionalUser();
		$this->assertTrue(Trashbin::restore($path));

		$this->setOwnerUser($mountPoint);

		error_log('111');
		$userFolder = \OC::$server->getUserFolder();
		error_log('222');
		$listing = $userFolder->getDirectoryListing();
		error_log('333');
		$listing2 = \array_filter($listing, function ($node) {
			error_log('444');
			$name = $node->getName();
			error_log('555> '.$name);
			return $name;
		});
		error_log('WHY???!!> '.sizeof($listing2));
		error_log('listing: '.var_export($listing2, true));

		$this->assertTrue($folder->nodeExists('file1.txt'));
		$file = $folder->get('file1.txt');

		// Reset state
		$file->delete();
		$filesInTrash = Helper::getTrashFiles(self::TEST_GROUP, '/', self::TEST_OWNER_USER, 'mtime', true);

		$deletedFileData = $filesInTrash[0]->getData();
		$path = $deletedFileData['name'].'.d'.$deletedFileData['mtime'];

		// Test permanent delete
		$mountPoint = $this->setFunctionalUser();
		$this->assertTrue(Trashbin::delete($path, \OCP\User::getUser()) > 0);

		$this->setOwnerUser($mountPoint);
		$filesInTrash = Helper::getTrashFiles(self::TEST_GROUP, '/', self::TEST_OWNER_USER, 'mtime', true);
		$this->assertCount(0, $filesInTrash);
	}

	private function setFunctionalUser() {
		$fUser = \OC::$server->getUserManager()->get(self::TEST_FUNCTIONAL_USER);
		\OC::$server->getUserSession()->setUser($fUser);

		$fUserMount = \OC::$server->getMountProviderCollection()->getHomeMountForUser($fUser);
		\OC::$server->getMountManager()->addMount($fUserMount);

		return $fUserMount->getMountPoint();

		// self::loginHelper(self::TEST_FUNCTIONAL_USER);
		// return '';
	}

	private function setOwnerUser(string $mountPoint) {
		// $fUser = \OC::$server->getUserManager()->get(self::TEST_OWNER_USER);
		// \OC::$server->getUserSession()->setUser($fUser);

		// \OC::$server->getMountManager()->removeMount($mountPoint);

		self::loginHelper(self::TEST_OWNER_USER);
	}
}
