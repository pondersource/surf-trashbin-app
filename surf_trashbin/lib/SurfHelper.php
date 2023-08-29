<?php
namespace OCA\SURF_Trashbin;

use OCP\IGroup;
use OC\User\User;

class SurfHelper {

    private $groupManager;
    private $userManager;
    private $dbConnection;

    public function __construct() {
        $this->groupManager = \OC::$server->getGroupManager();
        $this->userManager = \OC::$server->getUserManager();
        $this->dbConnection = \OC::$server->getDatabaseConnection();
    }

    public function getGroupsUserOwns(User $user) {
        $groups = $this->groupManager->search('');

		$groups = \array_filter($groups, function($group) use ($user) {
            return $this->isUserOwnerOfGroup($user->getUID(), $group->getGID());
		});

		$groups = \array_map(function($group) {
			return $group->getGID();
		}, $groups);

		$groups = \array_values($groups);

        return $groups;
    }

    public function isUserOwnerOfGroup(string $uid, string $gid) {
        $fuid = 'f_'.$gid;
        
        if (!$this->userManager->userExists($fuid)) {
            return false;
        }

        $subAdminOfGroups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($this->userManager->get($fuid));
        $subAdminOfGroups = \array_filter($subAdminOfGroups, function($group) use ($gid) {
            return $group->getGID() === $gid;
        });

        if (\count($subAdminOfGroups) == 0) {
            return false;
        };

        // TODO add share type
        $query = 'SELECT * FROM `*PREFIX*share` WHERE `share_with`=? AND `uid_owner`=? AND `uid_initiator`=`uid_owner';
        $parameters = [$uid, $fuid];

        $statement = $this->dbConnection->prepare($query);
		$statement->execute($parameters);
        return $statement->fetchOne();
    }

}
