<?php
namespace App\Controller;

use App\Repository\JobRepository;
use PDO;

class JobController
{
    private JobRepository $jobRepo;

    public function __construct(PDO $pdo)
    {
        $this->jobRepo = new JobRepository($pdo);
    }

    public function getJobs(): array
    {
        $item = isset($_GET['item']) ? $_GET['item'] : 0;

        $jobs = $this->jobRepo->findJobByItemId($item);

        return $jobs;
    }
}