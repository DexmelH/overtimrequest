<?php
namespace App\Controller;

use App\Repository\GroupRepository;
use App\Repository\UserRepository;

class GroupController
{
    private GroupRepository $groupRepo;
    private UserRepository $userRepo;

    public function __construct(GroupRepository $groupRepo, UserRepository $userRepo)
    {
        $this->groupRepo = $groupRepo;
        $this->userRepo = $userRepo;
    }

    public function getGroupsByUserId(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';

        $user = $this->userRepo->findIdByHash($userHash);
        $userID = $user['id'];

        return $this->groupRepo->findByUserId($userID);
    }
}
