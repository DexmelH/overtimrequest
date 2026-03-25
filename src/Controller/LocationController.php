<?php
namespace App\Controller;

use App\Repository\LocationRepository;
use PDO;

class LocationController
{
    private LocationRepository $locRepo;

    public function __construct(PDO $locRepo)
    {
        $this->locRepo = new LocationRepository($locRepo);
    }

    public function getLocations(): array
    {
        $locations = $this->locRepo->findLocations();

        return $locations;
    }
}