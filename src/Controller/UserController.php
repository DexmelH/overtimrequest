<?php
namespace App\Controller;

use App\Repository\GroupApproverRepository;
use App\Repository\UserRepository;
use App\Service\AdminAccessService;
use App\Service\ApprovalCutoff;

class UserController
{
    private UserRepository $userRepo;
    private GroupApproverRepository $groupApproverRepo;
    private AdminAccessService $adminAccess;
    private ApprovalCutoff $approvalCutoff;

    public function __construct(
        UserRepository $userRepo,
        GroupApproverRepository $groupApproverRepo,
        AdminAccessService $adminAccess,
        string $approvalCutoffTime = '15:00'
    ) {
        $this->userRepo = $userRepo;
        $this->groupApproverRepo = $groupApproverRepo;
        $this->adminAccess = $adminAccess;
        $this->approvalCutoff = new ApprovalCutoff($approvalCutoffTime);
    }

    public function getSession(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        $user = $this->userRepo->findIdByHash($userHash);
        $userId = (int) ($user['id'] ?? 0);
        $locked = $this->approvalCutoff->isPastCutoff();

        return [
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $this->formatDisplayName($user),
            ],
            'is_admin' => $this->adminAccess->isAdmin($userId),
            'is_approver' => $this->isApprover($userId),
            'approval_cutoff_time' => $this->approvalCutoff->getCutoffTime(),
            'approval_cutoff_label' => $this->approvalCutoff->getCutoffLabel(),
            'request_locked' => $locked,
            'request_lock_message' => $locked ? $this->approvalCutoff->employeeLockMessage() : null,
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
