<?php
namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AdminAccessService;
use App\Service\ApprovalCutoff;
use App\Service\ApproverDirectoryService;

class UserController
{
    private UserRepository $userRepo;
    private ApproverDirectoryService $approverDirectory;
    private AdminAccessService $adminAccess;
    private ApprovalCutoff $approvalCutoff;

    public function __construct(
        UserRepository $userRepo,
        ApproverDirectoryService $approverDirectory,
        AdminAccessService $adminAccess,
        string $approvalCutoffTime = '15:00'
    ) {
        $this->userRepo = $userRepo;
        $this->approverDirectory = $approverDirectory;
        $this->adminAccess = $adminAccess;
        $this->approvalCutoff = new ApprovalCutoff($approvalCutoffTime);
    }

    public function getSession(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        $user = $this->userRepo->findIdByHash($userHash);
        $userId = (int) ($user['id'] ?? 0);
        $seniorApprover = $this->approverDirectory->isSeniorApprover($userId);
        $locked = $this->approvalCutoff->isPastCutoff() && !$seniorApprover;

        return [
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $this->formatDisplayName($user),
            ],
            'is_admin' => $this->adminAccess->isAdmin($userId),
            'is_approver' => $this->approverDirectory->isApprover($userId),
            'approval_cutoff_time' => $this->approvalCutoff->getCutoffTime(),
            'approval_cutoff_label' => $this->approvalCutoff->getCutoffLabel(),
            'request_locked' => $locked,
            'request_lock_message' => $locked ? $this->approvalCutoff->employeeLockMessage() : null,
        ];
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
