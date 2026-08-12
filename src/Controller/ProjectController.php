<?php
namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use PDO;

class ProjectController
{
    private ProjectRepository $projectRepo;
    private UserRepository $userRepo;

    public function __construct(PDO $projectRepo, PDO $userRepo)
    {
        $this->projectRepo = new ProjectRepository($projectRepo);
        $this->userRepo = new UserRepository($userRepo);
    }

    public function getProjects(): array
    {
        $group = isset($_GET['group']) ? $_GET['group'] : '';
        $userHash = $_COOKIE['userID'] ?? '';

        $user = $this->userRepo->findIdByHash($userHash);
        $userID = $user['id']; 

        $projects = $this->projectRepo->findProjectByGroupID($group);
        $userProjects = $this->projectRepo->findProjectByUserID($userID);

        $projects = array_merge($userProjects, $projects);

        return $projects;
    }
}