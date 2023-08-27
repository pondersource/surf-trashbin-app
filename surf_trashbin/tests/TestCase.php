<?php
/**
 * @author Piotr Mrowczynski piotr@owncloud.com
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
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

use OC\Files\Cache\Watcher;
use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Files_Sharing\AppInfo\Application;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Trashbin;

/**
 * Class TrashbinTestCase
 *
 * @group DB
 */
abstract class TestCase extends \Test\TestCase {
	public const TEST_GROUP = 'test-group';
	public const TEST_RANDOM_GROUP = 'test-random-group';
	public const TEST_FUNCTIONAL_USER = 'f_'.self::TEST_GROUP;
	public const TEST_OWNER_USER = 'test_owner-user';
	public const TEST_SHARED_FOLDER = 'shared';

	protected $trashRoot1;
	protected $trashRoot2;

	/**
	 * @var View
	 */
	protected $rootView;

	private static $rememberRetentionObligation;

	/**
	 * @var bool
	 */
	private static $trashBinStatus;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$appManager = \OC::$server->getAppManager();
		self::$trashBinStatus = $appManager->isEnabledForUser('files_trashbin');

		// clear share hooks
		\OC_Hook::clear('OCP\\Share');
		\OC::registerShareHooks();
		$application = new Application();
		$application->registerMountProviders();

		//disable encryption
		\OC_App::disable('encryption');

		$config = \OC::$server->getConfig();
		//configure trashbin
		self::$rememberRetentionObligation = $config->getSystemValue('trashbin_retention_obligation', Expiration::DEFAULT_RETENTION_OBLIGATION);
		$config->setSystemValue('trashbin_retention_obligation', 'auto, 2');

		// register hooks
		Trashbin::registerHooks();

		// create test users
		self::loginHelper(self::TEST_OWNER_USER, true);
		self::loginHelper(self::TEST_FUNCTIONAL_USER, true);

		// create test group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP);
		$groupBackend->createGroup(self::TEST_RANDOM_GROUP);
		$groupBackend->addToGroup(self::TEST_FUNCTIONAL_USER, self::TEST_GROUP);
		$groupBackend->addToGroup(self::TEST_OWNER_USER, self::TEST_GROUP);
		\OC::$server->getGroupManager()->addBackend($groupBackend);

		// Make functional user admin of the group
		$connection = \OC::$server->getDatabaseConnection();
		$connection->executeStatement('INSERT INTO `*PREFIX*group_admin` VALUES (?, ?)', [self::TEST_GROUP, self::TEST_FUNCTIONAL_USER]);

		// Share a folder from functional user with the owner user
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder(self::TEST_SHARED_FOLDER);

		$permission = \OCP\Constants::PERMISSION_ALL;
		$share = self::share(
			\OCP\Share::SHARE_TYPE_USER,
			$folder,
			self::TEST_FUNCTIONAL_USER,
			self::TEST_OWNER_USER,
			$permission
		);
	}

	/**
	 * @param int $type The share type
	 * @param string $path The path to share relative to $initiators root
	 * @param string $initiator
	 * @param string $recipient
	 * @param int $permissions
	 * @return \OCP\Share\IShare
	 */
	protected static function share($type, $path, $initiator, $recipient, $permissions) {
		$node = $path;

		$shareManager = \OC::$server->getShareManager();
		$share = $shareManager->newShare();
		$share->setShareType($type)
			->setSharedWith($recipient)
			->setSharedBy($initiator)
			->setNode($node)
			->setPermissions($permissions);
		$share = $shareManager->createShare($share);
		
		return $share;
	}

	public static function tearDownAfterClass(): void {
		// clean up test group
		$group = \OC::$server->getGroupManager()->get(self::TEST_GROUP);
		if ($group !== null) {
			$group->delete();
		}

		// remove admin
		$connection = \OC::$server->getDatabaseConnection();
		$connection->executeUpdate('DELETE FROM `*PREFIX*group_user` WHERE `gid`=? AND `uid`=?', [self::TEST_GROUP, self::TEST_FUNCTIONAL_USER]);

		// reset backend
		\OC::$server->getGroupManager()->clearBackends();
		\OC::$server->getGroupManager()->addBackend(new \OC\Group\Database());

		// cleanup test user
		$user = \OC::$server->getUserManager()->get(self::TEST_FUNCTIONAL_USER);
		if ($user !== null) {
			$user->delete();
		}
		$user = \OC::$server->getUserManager()->get(self::TEST_OWNER_USER);
		if ($user !== null) {
			$user->delete();
		}

		\OC::$server->getConfig()->setSystemValue('trashbin_retention_obligation', self::$rememberRetentionObligation);

		\OC_Hook::clear();

		Filesystem::getLoader()->removeStorageWrapper('oc_trashbin');

		if (self::$trashBinStatus) {
			\OC::$server->getAppManager()->enableApp('files_trashbin');
		}

		$query = \OCP\DB::prepare('DELETE FROM `*PREFIX*share`');
		$query->execute();

		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		parent::setUp();

		\OC::$server->getAppManager()->enableApp('files_trashbin');
		$config = \OC::$server->getConfig();
		$mockConfig = $this->createMock('\OCP\IConfig');
		$mockConfig->expects($this->any())
			->method('getSystemValue')
			->will($this->returnCallback(function ($key, $default) use ($config) {
				if ($key === 'filesystem_check_changes') {
					return Watcher::CHECK_ONCE;
				} else {
					return $config->getSystemValue($key, $default);
				}
			}));
		$this->overwriteService('AllConfig', $mockConfig);

		$this->trashRoot1 = '/' . self::TEST_FUNCTIONAL_USER . '/files_trashbin';
		$this->trashRoot2 = '/' . self::TEST_OWNER_USER . '/files_trashbin';
		$this->rootView = new View();
	}

	protected function tearDown(): void {
		$this->restoreService('AllConfig');
		// disable trashbin to be able to properly clean up
		\OC::$server->getAppManager()->disableApp('files_trashbin');

		$this->rootView->deleteAll('/' . self::TEST_FUNCTIONAL_USER . '/files');
		$this->rootView->deleteAll('/' . self::TEST_OWNER_USER . '/files');
		$this->rootView->deleteAll($this->trashRoot1);
		$this->rootView->deleteAll($this->trashRoot2);

		// clear trash table
		$connection = \OC::$server->getDatabaseConnection();
		$connection->executeUpdate('DELETE FROM `*PREFIX*files_trash`');

		parent::tearDown();
	}

	/**
	 * @param string $user
	 * @param bool $create
	 */
	protected static function loginHelper($user, $create = false) {
		if ($create) {
			try {
				\OC::$server->getUserManager()->createUser($user, $user);
			} catch (\Exception $e) { // catch username is already being used from previous aborted runs
			}
		}

		\OC_Util::tearDownFS();
		\OC_User::setUserId('');
		Filesystem::tearDown();
		\OC_User::setUserId($user);
		\OC_Util::setupFS($user);
		\OC::$server->getUserFolder($user);
	}
}
