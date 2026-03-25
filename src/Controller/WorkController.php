<?php
namespace App\Controller;

use App\Repository\WorkRepository;
use PDO;

class WorkController
{
    private WorkRepository $workRepo;

    public function __construct(PDO $pdo)
    {
        $this->workRepo = new WorkRepository($pdo);
    }

    public function getWorks(): array
    {
        $works = $this->workRepo->findWork();

        return $works;
    }
}