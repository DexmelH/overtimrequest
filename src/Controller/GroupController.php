<?php
namespace App\Controller;

use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use PDO;

class GroupController
{
    private GroupRepository $groupRepo;
    private UserRepository $userRepo;

    public function __construct(PDO $groupPdo, PDO $authPdo)
    {
        $this->groupRepo = new GroupRepository($groupPdo);
        $this->userRepo  = new UserRepository($authPdo);
    }

    public function getGroupsByUserId(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';

        $userID = $this->userRepo->findIdByHash($userHash);

        return $this->groupRepo->findByUserId($userID);
    }
}
