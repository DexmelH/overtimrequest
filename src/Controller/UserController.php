<?php
namespace App\Controller;

use App\Repository\GroupApproverRepository;
use App\Repository\UserRepository;

class UserController
{
    private UserRepository $userRepo;
    private GroupApproverRepository $groupApproverRepo;
    /** @var int[] */
    private array $adminUserIds;

    public function __construct(
        UserRepository $userRepo,
        GroupApproverRepository $groupApproverRepo,
        array $adminUserIds = []
    ) {
        $this->userRepo = $userRepo;
        $this->groupApproverRepo = $groupApproverRepo;
        $this->adminUserIds = array_map('intval', $adminUserIds);
    }

    public function getSession(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        $user = $this->userRepo->findIdByHash($userHash);
        $userId = (int) ($user['id'] ?? 0);

        return [
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $this->formatDisplayName($user),
            ],
            'is_admin' => in_array($userId, $this->adminUserIds, true),
            'is_approver' => $this->isApprover($userId),
        ];
    }

    private function isApprover(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->groupApproverRepo->isAssignedApprover($userId)
            || $this->userRepo->isFormPicApprover($userId);
    }

    private function formatDisplayName(array $user): string
    {
        $first = trim((string) ($user['firstname'] ?? ''));
        $last = trim((string) ($user['surname'] ?? ''));

        if ($first !== '' && $last !== '') {
            return "{$first} {$last}";
        }

        return $first !== '' ? $first : $last;
    }
}
