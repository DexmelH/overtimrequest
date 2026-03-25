<?php
namespace App\Controller;

use App\Repository\ItemRepository;
use PDO;

class ItemController
{
    private ItemRepository $itemRepo;

    public function __construct(PDO $itemPdo)
    {
        $this->itemRepo = new ItemRepository($itemPdo);
    }

    public function getItems(): array
    {
        $project = isset($_GET['project']) ? $_GET['project'] : 0;

        $items = $this->itemRepo->findItemByProjectID($project);

        return $items;
    }
}