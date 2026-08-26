<?php
namespace App\Controller;

use App\Repository\LocationRepository;
use App\Repository\UserRepository;

class LocationController
{
    private LocationRepository $locRepo;
    private UserRepository $userRepo;

    public function __construct(LocationRepository $locRepo, UserRepository $userRepo)
    {
        $this->locRepo = $locRepo;
        $this->userRepo = $userRepo;
    }

    public function getLocations(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        $this->userRepo->findIdByHash($userHash);

        return $this->locRepo->findLocations();
    }
}