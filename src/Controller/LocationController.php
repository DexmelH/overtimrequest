<?php
namespace App\Controller;

use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use PDO;

class LocationController
{
    private LocationRepository $locRepo;
    private UserRepository $userRepo;

    public function __construct(PDO $locPdo, PDO $authPdo)
    {
        $this->locRepo = new LocationRepository($locPdo);
        $this->userRepo = new UserRepository($authPdo);
    }

    public function getLocations(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        $this->userRepo->findIdByHash($userHash);

        return $this->locRepo->findLocations();
    }
}