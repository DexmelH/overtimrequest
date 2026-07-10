<?php
namespace App\Controller;

use App\Repository\GroupApproverRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\OvertimeRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use PDO;

class OvertimeController
{
    private OvertimeRepository $overtimeRepo;
    private UserRepository $userRepo;
    private GroupApproverRepository $groupApproverRepo;
    private HolidayRepository $holidayRepo;
    private LeaveRepository $leaveRepo;
    private ActivityLogger $logger;

    public function __construct(PDO $overtimePDO, PDO $userPDO, PDO $formsPDO, ActivityLogger $logger)
    {
        $this->overtimeRepo = new OvertimeRepository($overtimePDO);
        $this->userRepo = new UserRepository($userPDO);
        $this->groupApproverRepo = new GroupApproverRepository($overtimePDO);
        $this->holidayRepo = new HolidayRepository($userPDO);
        $this->leaveRepo = new LeaveRepository($formsPDO);
        $this->logger = $logger;
    }

    public function getHolidays(): array
    {
        $user = $this->currentUser();
        $from = trim((string) ($_GET['from'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d');
        }

        return [
            'success' => true,
            'data' => $this->holidayRepo->findFromDate($from),
            'leave_weeks' => $this->leaveRepo->findAcceptedLeaveWeekRanges((int) $user['id'], $from),
        ];
    }

    public function getUserHistory(): array
    {
        $user = $this->currentUser();
        return $this->overtimeRepo->findHistoryByUserId($user['id']);
    }

    public function addOvertime(): array
    {
        $user = $this->currentUser();
        $userID = $user['id'];
        $groupID = isset($_POST['group']) ? $_POST['group'] : 0;
        $locationID = isset($_POST['location']) ? $_POST['location'] : 0;
        $projectID = isset($_POST['project']) ? $_POST['project'] : 0;
        $itemOfWorkID = isset($_POST['item']) ? $_POST['item'] : 0;
        $jobDescriptionID = isset($_POST['jobdesc']) ? $_POST['jobdesc'] : 0;
        $workID = isset($_POST['work']) ? $_POST['work'] : 0;
        $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
        $duration = isset($_POST['hours']) ? $_POST['hours'] : 0;
        $requestDate = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

        $dateError = $this->validateRequestDate($requestDate, (int) $userID);
        if ($dateError !== null) {
            return ['success' => false, 'message' => $dateError];
        }

        $payload = [
            "user_id" => $userID,
            "group_id" => $groupID,
            "location_id" => $locationID,
            "project_id" => $projectID,
            "item_id" => $itemOfWorkID,
            "job_id" => $jobDescriptionID,
            "work_id" => $workID,
            "remarks" => $remarks,
            "duration" => $duration,
            "request_date" => $requestDate
        ];

        $pdo = $this->overtimeRepo->getPdo();

        try {
            $pdo->beginTransaction();

            $id = $this->overtimeRepo->addOvertime($payload);
            $approver = $this->resolveApprovers((int) $user["group_id"], $user["abbreviation"] ?? '', (int) $userID);
            foreach ($approver as $app) {
                $emailPayload = [
                    'email_to' => $app['email'],
                    'approver_name' => $app['surname'],
                    'overtime_id' => $id,
                    'email_type' => 'new_request',
                ];
                $this->overtimeRepo->insertEmailQueue($emailPayload);
                $this->overtimeRepo->addAcceptance($id, $app['id']);
            }

            $pdo->commit();

            $this->logger->log(
                'request.submit',
                (int) $userID,
                $user['surname'] ?? null,
                'overtime_request',
                (int) $id,
                [
                    'group_id' => $groupID,
                    'hours' => $duration,
                    'request_date' => $requestDate,
                ]
            );

            return ["success" => true, "id" => $id];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add overtime failed: ' . $e->getMessage());
            return ["success" => false, "message" => "Failed to add overtime request. Please try again."];
        }
    }

    public function cancelOvertime(): array
    {
        $overtimeID = (int) ($_POST['overtimeID'] ?? 0);
        $user = $this->currentUser();
        $userID = (int) $user['id'];

        if ($overtimeID <= 0) {
            return ['success' => false, 'message' => 'Invalid request ID.'];
        }

        $request = $this->overtimeRepo->findOwnedPendingRequest($overtimeID, $userID);
        if (!$request) {
            return ['success' => false, 'message' => 'Request not found.'];
        }
        if ($request['status'] !== null) {
            return ['success' => false, 'message' => 'Only pending requests can be cancelled.'];
        }

        $pdo = $this->overtimeRepo->getPdo();
        try {
            $pdo->beginTransaction();

            if (!$this->overtimeRepo->cancelRequest($overtimeID, $userID)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Unable to cancel this request.'];
            }

            $pics = $this->overtimeRepo->findPicsForOvertime($overtimeID);
            foreach ($pics as $pic) {
                $email = trim((string) ($pic['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $this->overtimeRepo->insertEmailQueue([
                    'email_to' => $email,
                    'approver_name' => $pic['surname'] ?? 'PIC',
                    'overtime_id' => $overtimeID,
                    'email_type' => 'request_cancelled',
                    'actor_name' => $user['surname'] ?? 'Employee',
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Cancel overtime failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cancel request. Please try again.'];
        }

        $this->logger->log(
            'request.cancel',
            $userID,
            $user['surname'] ?? null,
            'overtime_request',
            $overtimeID,
            ['group' => $request['abbreviation'] ?? null]
        );

        return ['success' => true, 'message' => 'Request cancelled successfully.'];
    }

    public function getOvertimeToApprove(): array
    {
        $user = $this->currentUser();
        $approverID = $user['id'];
        $overtimeToApprove = $this->overtimeRepo->findOvertimeToApprove($approverID);

        foreach ($overtimeToApprove as &$request) {
            $request['is_approved'] = $this->overtimeRepo->checkIfAlreadyApproved($request['id'], $approverID);
            $request['approver_details'] = $this->overtimeRepo->findApproverDetails($request['id']);
        }

        return ["success" => true, "data" => $overtimeToApprove];
    }

    public function approveOvertime(): array
    {
        $overtimeID = isset($_POST['overtimeID']) ? $_POST['overtimeID'] : 0;
        $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
        $approved = isset($_POST['status']) ? $_POST['status'] : NULL;

        $user = $this->currentUser();
        $approverID = $user['id'];
        $ifApproved = $this->overtimeRepo->checkIfFullyApproved($overtimeID);
        if ($ifApproved) {
            return ['success' => false, 'message' => "This request has already been finalized."];
        }
        $this->overtimeRepo->approveRequest($overtimeID, $approverID, $remarks, $approved);

        $confirmApproval = $this->overtimeRepo->checkIfForApproval($overtimeID, $approved);
        if ($confirmApproval) {
            $this->overtimeRepo->updateOvertimeStatus($overtimeID, $approved);
            $this->queueRequestorStatusEmail(
                (int) $overtimeID,
                (int) $approved,
                (string) ($user['surname'] ?? 'Approver')
            );
        }

        $action = ((int) $approved === 1) ? 'request.approve' : 'request.reject';
        $this->logger->log(
            $action,
            (int) $approverID,
            $user['surname'] ?? null,
            'overtime_request',
            (int) $overtimeID,
            ['remarks' => $remarks, 'finalized' => $confirmApproval]
        );

        return ['success' => true, 'message' => "Overtime request updated successfully."];
    }

    private function queueRequestorStatusEmail(int $overtimeID, int $decision, string $actorName): void
    {
        $requestor = $this->overtimeRepo->findRequestorByOvertimeId($overtimeID);
        $email = trim((string) ($requestor['email'] ?? ''));

        if ($email === '') {
            error_log("Overtime {$overtimeID}: no requestor email; status notification skipped.");
            return;
        }

        $this->overtimeRepo->insertEmailQueue([
            'email_to' => $email,
            'approver_name' => $requestor['surname'] ?? 'Employee',
            'overtime_id' => $overtimeID,
            'email_type' => 'status_update',
            'decision' => $decision,
            'actor_name' => $actorName,
        ]);
    }

    /**
     * @return array<int, array{id: int, surname: string, email: string}>
     */
    private function resolveApprovers(int $groupId, string $groupAbbrev, int $userId): array
    {
        if ($groupId > 0) {
            $configured = $this->groupApproverRepo->findApproversByGroupId($groupId, $userId);
            if (!empty($configured)) {
                return $configured;
            }
        }

        if ($groupAbbrev !== '') {
            return $this->userRepo->findApprover($groupAbbrev, (string) $userId);
        }

        return [];
    }

    private function currentUser(): array
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        return $this->userRepo->findIdByHash($userHash);
    }

    private function validateRequestDate(string $date, int $employeeId): ?string
    {
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Invalid request date.';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return 'Invalid request date.';
        }

        $today = new \DateTime('today');
        if ($dt < $today) {
            return 'Past dates are not allowed.';
        }

        $dayOfWeek = (int) $dt->format('N');
        $isWeekend = $dayOfWeek >= 6;
        $isHoliday = $this->holidayRepo->isBlockedDate($date);

        if ($isWeekend || $isHoliday) {
            [$todayWeekStart] = LeaveRepository::workWeekBoundsForDate($today->format('Y-m-d'));
            [$dateWeekStart, $weekEnd] = LeaveRepository::workWeekBoundsForDate($date);

            if ($dateWeekStart !== $todayWeekStart) {
                return $isHoliday
                    ? 'Only holidays in the current week can be selected.'
                    : 'Only weekends in the current week can be selected.';
            }

            if ($this->leaveRepo->hasAcceptedLeaveInWeek($employeeId, $dateWeekStart, $weekEnd)) {
                if ($isHoliday) {
                    $name = $this->holidayRepo->findHolidayName($date);
                    return $name
                        ? "You have approved leave this week, so {$name} cannot be selected."
                        : 'You have approved leave this week, so this holiday cannot be selected.';
                }

                return 'You have approved leave this week, so weekend overtime cannot be requested.';
            }

            return null;
        }

        return null;
    }
}
