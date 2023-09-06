<?php
namespace OCA\SURF_Trashbin;

use OC\User\User;

class SurfHelper {

    private $userManager;
    private $dbConnection;

    public function __construct() {
        $this->userManager = \OC::$server->getUserManager();
        $this->dbConnection = \OC::$server->getDatabaseConnection();
    }

    public function getGroupsUserOwns(User $user) {
        $query = 'SELECT * FROM `*PREFIX*share` WHERE `share_type`=0 AND `share_with`=? AND `uid_owner` LIKE \'f_%\' AND `uid_initiator`=`uid_owner`';
        $parameters = [$user->getUID()];

        $statement = $this->dbConnection->prepare($query);
		$statement->execute($parameters);
        $shares = $statement->fetchAll();

        $groups = \array_map(function($share) {
            return [
                'gid' => \substr($share['uid_owner'], 2),
                'name' => $this->userManager->get($share['uid_owner'])->getDisplayName()
            ];
        }, $shares);

        return $groups;
    }

    public function isUserOwnerOfGroup(string $uid, string $gid) {
        $fuid = 'f_'.$gid;
        
        if (!$this->userManager->userExists($fuid)) {
            return false;
        }

        $query = 'SELECT * FROM `*PREFIX*share` WHERE `share_with`=? AND `uid_owner`=? AND `share_type`=0 AND `uid_initiator`=`uid_owner`';
        $parameters = [$uid, $fuid];

        $statement = $this->dbConnection->prepare($query);
		$statement->execute($parameters);
        return $statement->fetchOne();
    }

}