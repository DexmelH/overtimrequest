<?php
namespace App\Controller;

use App\Repository\ProjectRepository;
use PDO;

class ProjectController
{
    private ProjectRepository $projectRepo;

    public function __construct(PDO $projectRepo)
    {
        $this->projectRepo = new ProjectRepository($projectRepo);
    }

    public function getProjects(): array
    {
        $group = isset($_GET['group']) ? $_GET['group'] : '';

        $projects = $this->projectRepo->findProjectByGroupID($group);

        return $projects;
    }
}