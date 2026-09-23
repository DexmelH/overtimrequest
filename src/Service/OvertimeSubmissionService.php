<?php
namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\OvertimeRepository;
use App\Repository\ProjectNotifyRepository;
use App\Repository\WorkLookupRepository;

class OvertimeSubmissionService
{
    private OvertimeRepository $overtimeRepo;
    private EmployeeRepository $employeeRepo;
    private HolidayRepository $holidayRepo;
    private LeaveRepository $leaveRepo;
    private ApproverDirectoryService $approverDirectory;
    private ProjectNotifyRepository $projectNotifyRepo;
    private WorkLookupRepository $workLookup;
    private ActivityLogger $logger;
    private ApprovalCutoff $cutoff;

    private const WORK_2D3D_VALUES = ['2D', '3D', '2D3D', '3D2D'];
    private const DIM_EXCLUDED_GROUP_IDS = [10, 16];

    public function __construct(
        OvertimeRepository $overtimeRepo,
        EmployeeRepository $employeeRepo,
        HolidayRepository $holidayRepo,
        LeaveRepository $leaveRepo,
        ApproverDirectoryService $approverDirectory,
        ProjectNotifyRepository $projectNotifyRepo,
        WorkLookupRepository $workLookup,
        ActivityLogger $logger,
        string $approvalCutoffTime = '15:00'
    ) {
        $this->overtimeRepo = $overtimeRepo;
        $this->employeeRepo = $employeeRepo;
        $this->holidayRepo = $holidayRepo;
        $this->leaveRepo = $leaveRepo;
        $this->approverDirectory = $approverDirectory;
        $this->projectNotifyRepo = $projectNotifyRepo;
        $this->workLookup = $workLookup;
        $this->logger = $logger;
        $this->cutoff = new ApprovalCutoff($approvalCutoffTime);
    }

    /**
     * @param array{
     *   group?: mixed, location?: mixed, remarks?: mixed, date?: mixed,
     *   project_id?: mixed, hours?: mixed, item_id?: mixed, job_id?: mixed,
     *   tow_id?: mixed, work_2d3d?: mixed, revision?: mixed
     * } $input
     */
    public function addOvertime(array $user, array $input): array
    {
        $userID = (int) $user['id'];
        $selfLevel = $this->approverDirectory->findHighestApprovalLevel($userID);
        $selfAutoApprove = $selfLevel >= 3;

        if (!$selfAutoApprove && $this->cutoff->isPastCutoff()) {
            return ['success' => false, 'message' => $this->cutoff->employeeLockMessage()];
        }
        
        $groupID = (int) ($input['group'] ?? 0);
        $locationID = (int) ($input['location'] ?? 0);
        $remarks = trim((string) ($input['remarks'] ?? ''));
        $requestDate = (string) ($input['date'] ?? date('Y-m-d'));

        $dateError = $this->validateRequestDate($requestDate, (int) $userID);
        if ($dateError !== null) {
            return ['success' => false, 'message' => $dateError];
        }

        if ($groupID <= 0 || $locationID <= 0) {
            return ['success' => false, 'message' => 'Please complete all required fields.'];
        }

        // Approvers come from the requestor's main group; the selected OT group is
        // only stored on the request record and used for project allocation.
        $mainGroupId = (int) ($user['group_id'] ?? 0);
        $mainGroupAbbrev = trim((string) ($user['abbreviation'] ?? ''));
        if ($mainGroupId <= 0) {
            return ['success' => false, 'message' => 'Your employee record has no main group assigned.'];
        }

        $group = $this->employeeRepo->findGroupById($groupID);
        $groupAbbrev = (string) ($group['abbreviation'] ?? '');
        [$work, $workError] = $this->parseWorkFields($input, $groupID, $groupAbbrev, (int) $userID);
        if ($workError !== null) {
            return ['success' => false, 'message' => $workError];
        }
        $projects = [['project_id' => $work['project_id'], 'hours' => $work['hours']]];
        $duration = $work['hours'];
        $durationMinutes = $work['minutes'];

        $payload = [
            "user_id" => $userID,
            "group_id" => $groupID,
            "location_id" => $locationID,
            "project_id" => $work['project_id'],
            "item_id" => $work['item_id'],
            "job_id" => $work['job_id'],
            "tow_id" => $work['tow_id'],
            "work_2d3d" => $work['work_2d3d'],
            "revision" => $work['revision'],
            "remarks" => $remarks,
            "duration" => $duration,
            "duration_minutes" => $durationMinutes,
            "request_date" => $requestDate
        ];

        $pdo = $this->overtimeRepo->getPdo();

        try {
            $pdo->beginTransaction();

            $id = (int) $this->overtimeRepo->addOvertime($payload);

            if ($selfAutoApprove) {
                // L3+ approvers do not enter an approval chain: accept immediately.
                $this->overtimeRepo->updateOvertimeStatus($id, '1');
                $this->overtimeRepo->addAcceptedRequestToDailyReport($id, $userID);
                $this->overtimeRepo->queueRequestorStatusEmail(
                    $id,
                    1,
                    (string) ($user['surname'] ?? 'System')
                );
            } else {
                $approver = $this->approverDirectory->resolveApprovers(
                    $mainGroupId,
                    $mainGroupAbbrev,
                    $userID
                );
                $queuedEmails = [];
                foreach ($approver as $app) {
                    $email = trim((string) ($app['email'] ?? ''));
                    if ($email !== '') {
                        $queuedEmails[strtolower($email)] = true;
                    }
                    $this->overtimeRepo->insertEmailQueue([
                        'email_to' => $app['email'],
                        'approver_name' => $app['surname'] ?? 'Approver',
                        'overtime_id' => $id,
                        'email_type' => 'new_request',
                    ]);
                    $this->overtimeRepo->addAcceptance(
                        $id,
                        (int) $app['id'],
                        $this->resolveApprovalLevel($app)
                    );
                }

                $this->queueProjectNotifyEmails(
                    $id,
                    [$work['project_id']],
                    $queuedEmails
                );
            }

            $pdo->commit();

            $this->logger->log(
                'request.submit',
                $userID,
                $user['surname'] ?? null,
                'overtime_request',
                $id,
                [
                    'group_id' => $groupID,
                    'hours' => $duration,
                    'minutes' => $durationMinutes,
                    'projects' => $projects,
                    'project_id' => $work['project_id'],
                    'item_id' => $work['item_id'],
                    'job_id' => $work['job_id'],
                    'tow_id' => $work['tow_id'],
                    'work_2d3d' => $work['work_2d3d'],
                    'revision' => $work['revision'],
                    'request_date' => $requestDate,
                    'auto_approved' => $selfAutoApprove,
                    'approval_level' => $selfAutoApprove ? $selfLevel : null,
                ]
            );

            if ($selfAutoApprove) {
                return [
                    'success' => true,
                    'id' => $id,
                    'message' => 'Your overtime request has been submitted and approved.',
                ];
            }

            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add overtime failed: ' . $e->getMessage());
            return ["success" => false, "message" => "Failed to add overtime request. Please try again."];
        }
    }

    /**
     * @param array{
     *   employee_id?: mixed, group?: mixed, location?: mixed, remarks?: mixed, date?: mixed,
     *   project_id?: mixed, hours?: mixed, item_id?: mixed, job_id?: mixed,
     *   tow_id?: mixed, work_2d3d?: mixed, revision?: mixed, origin_request_id?: mixed
     * } $input
     */
    public function addOvertimeOnBehalf(array $approver, array $input): array
    {
        $approverId = (int) $approver['id'];
        $employeeId = (int) ($input['employee_id'] ?? 0);
        $groupID = (int) ($input['group'] ?? 0);
        $locationID = (int) ($input['location'] ?? 0);
        $remarks = trim((string) ($input['remarks'] ?? ''));
        $requestDate = trim((string) ($input['date'] ?? date('Y-m-d')));

        if (!$this->approverDirectory->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to submit member overtime requests.'];
        }

        $approverGroupIds = $this->approverDirectory->getApproverGroupIds($approverId);
        if (!$approverGroupIds) {
            return ['success' => false, 'message' => 'You are not authorized to submit member overtime requests.'];
        }

        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Please select an employee.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        $dateError = $this->validateRequestDate($requestDate, $employeeId, true);
        if ($dateError !== null) {
            return ['success' => false, 'message' => $dateError];
        }
        // relaxed=true: format-only check; past dates and holiday/weekend rules are allowed on-behalf.

        // Approver-group check is against the employee's main group only.
        $mainGroupId = (int) ($employee['group_id'] ?? 0);
        $mainGroupAbbrev = trim((string) ($employee['group_abbr'] ?? ''));
        if ($mainGroupId <= 0) {
            return ['success' => false, 'message' => 'The selected employee has no main group assigned.'];
        }
        if (!in_array($mainGroupId, $approverGroupIds, true)) {
            return ['success' => false, 'message' => 'You can only submit for employees whose main group you approve for.'];
        }

        if ($groupID <= 0) {
            return ['success' => false, 'message' => 'Please select a group.'];
        }

        // Selected OT group must exist in employee_group for this employee (may differ from main group).
        if (!$this->employeeRepo->isEmployeeInEmployeeGroup($employeeId, $groupID)) {
            return ['success' => false, 'message' => 'The selected group is not assigned to this employee.'];
        }

        if ($locationID <= 0) {
            return ['success' => false, 'message' => 'Please complete all required fields.'];
        }

        $group = $this->employeeRepo->findGroupById($groupID);
        $groupAbbrev = (string) ($group['abbreviation'] ?? '');
        [$work, $workError] = $this->parseWorkFields($input, $groupID, $groupAbbrev, $employeeId);
        if ($workError !== null) {
            return ['success' => false, 'message' => $workError];
        }
        $projects = [['project_id' => $work['project_id'], 'hours' => $work['hours']]];
        $duration = $work['hours'];
        $durationMinutes = $work['minutes'];

        $originRequestId = (int) ($input['origin_request_id'] ?? 0);

        $payload = [
            'user_id' => $employeeId,
            'submitted_by' => $approverId,
            'origin_request_id' => $originRequestId > 0 ? $originRequestId : null,
            'group_id' => $groupID,
            'location_id' => $locationID,
            'project_id' => $work['project_id'],
            'item_id' => $work['item_id'],
            'job_id' => $work['job_id'],
            'tow_id' => $work['tow_id'],
            'work_2d3d' => $work['work_2d3d'],
            'revision' => $work['revision'],
            'remarks' => $remarks,
            'duration' => $duration,
            'duration_minutes' => $durationMinutes,
            'request_date' => $requestDate,
        ];

        $pdo = $this->overtimeRepo->getPdo();

        try {
            $pdo->beginTransaction();

            $id = (int) $this->overtimeRepo->addOvertime($payload);

            // Only the filing approver is recorded. The rest of the main group's
            // chain is resolved purely to snapshot this approver's own level.
            $approvers = $this->approverDirectory->resolveApprovers(
                $mainGroupId,
                $mainGroupAbbrev,
                $employeeId
            );
            $approverLevel = 1;
            foreach ($approvers as $app) {
                if ((int) $app['id'] === $approverId) {
                    $approverLevel = $this->resolveApprovalLevel($app);
                    break;
                }
            }

            $this->overtimeRepo->addAcceptance($id, $approverId, $approverLevel);
            $this->overtimeRepo->approveRequest(
                $id,
                $approverId,
                'Automatically approved upon submission',
                1
            );

            $this->overtimeRepo->updateOvertimeStatus($id, 1);
            $this->overtimeRepo->addAcceptedRequestToDailyReport($id, $approverId);
            $this->overtimeRepo->queueRequestorStatusEmail(
                $id,
                1,
                (string) ($approver['surname'] ?? 'Approver')
            );

            $pdo->commit();

            $this->logger->log(
                'request.submit.on_behalf',
                $approverId,
                $approver['surname'] ?? null,
                'overtime_request',
                $id,
                [
                    'employee_id' => $employeeId,
                    'employee_name' => trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? '')),
                    'group_id' => $groupID,
                    'group_abbr' => $groupAbbrev !== '' ? $groupAbbrev : null,
                    'main_group_id' => $mainGroupId > 0 ? $mainGroupId : null,
                    'main_group_abbr' => $mainGroupAbbrev !== '' ? $mainGroupAbbrev : null,
                    'hours' => $duration,
                    'minutes' => $durationMinutes,
                    'projects' => $projects,
                    'project_id' => $work['project_id'],
                    'item_id' => $work['item_id'],
                    'job_id' => $work['job_id'],
                    'tow_id' => $work['tow_id'],
                    'work_2d3d' => $work['work_2d3d'],
                    'revision' => $work['revision'],
                    'request_date' => $requestDate,
                    'auto_approved' => true,
                    'approval_level' => $approverLevel,
                    'origin_request_id' => $originRequestId > 0 ? $originRequestId : null,
                ]
            );

            return [
                'success' => true,
                'id' => $id,
                'message' => 'The overtime request has been submitted and approved.',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add overtime on behalf failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to submit the overtime request. Please try again.'];
        }
    }

    /**
     * Re-file an auto-rejected request as a fresh on-behalf submission.
     *
     * The original request is never modified; the copy goes through the normal
     * on-behalf path so all authorization and validation rules still apply.
     */
    public function resubmitAsFollowUp(array $approver, int $overtimeId): array
    {
        if ($overtimeId <= 0) {
            return ['success' => false, 'message' => 'Invalid request ID.'];
        }

        $original = $this->overtimeRepo->findRequestWithDecisionCount($overtimeId);
        if ($original === null) {
            return ['success' => false, 'message' => 'Overtime request not found.'];
        }

        if ((string) ($original['status'] ?? '') !== '0') {
            return ['success' => false, 'message' => 'Only auto-rejected requests can be re-submitted.'];
        }

        if ((int) ($original['acted_count'] ?? 0) > 0) {
            return [
                'success' => false,
                'message' => 'This request was rejected by an approver, so it cannot be re-submitted this way.',
            ];
        }

        if ($this->overtimeRepo->hasFollowUp($overtimeId)) {
            return ['success' => false, 'message' => 'This request has already been re-submitted.'];
        }

        $hours = (int) ($original['duration'] ?? 0);
        $minutes = (int) ($original['duration_minutes'] ?? 0);
        $projectId = (int) ($original['project_id'] ?? 0);
        if ($projectId <= 0 || ($hours <= 0 && $minutes <= 0)) {
            return ['success' => false, 'message' => 'The original request is missing project or hours.'];
        }

        return $this->addOvertimeOnBehalf($approver, [
            'employee_id' => (int) $original['user_id'],
            'group' => (int) $original['group_id'],
            'location' => (int) $original['location_id'],
            'remarks' => (string) ($original['remarks'] ?? ''),
            'date' => (string) $original['request_date'],
            'project_id' => $projectId,
            'hours' => $hours,
            'minutes' => $minutes,
            'item_id' => (int) ($original['item_id'] ?? 0),
            'job_id' => (int) ($original['job_id'] ?? 0),
            'tow_id' => (int) ($original['tow_id'] ?? 0),
            'work_2d3d' => $original['work_2d3d'] ?? null,
            'revision' => (int) ($original['revision'] ?? 0),
            'origin_request_id' => $overtimeId,
        ]);
    }

    public function cancelOvertime(array $user, int $overtimeID): array
    {
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

    /** @param string|int $userId */
    public function getUserHistory($userId, array $filters = []): array
    {
        $requestId = (int) ($filters['id'] ?? 0);
        if ($requestId > 0) {
            $row = $this->overtimeRepo->findOwnedHistoryRequest((string) $userId, $requestId);
            return [
                'success' => true,
                'data' => $row ? [$row] : [],
                'from' => null,
                'to' => null,
                'pagination' => [
                    'page' => 1,
                    'limit' => 1,
                    'total' => $row ? 1 : 0,
                    'pages' => $row ? 1 : 0,
                ],
            ];
        }

        $query = \App\Support\ListQuery::normalize($filters);
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $allowedStatus = ['pending', 'approved', 'denied', 'cancelled'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = '';
        }

        $result = $this->overtimeRepo->findHistoryByUserId((string) $userId, [
            'from' => $query['from'],
            'to' => $query['to'],
            'page' => $query['page'],
            'limit' => $query['limit'],
            'offset' => $query['offset'],
            'status' => $status,
            'q' => trim((string) ($filters['q'] ?? '')),
        ]);

        return [
            'success' => true,
            'data' => $result['data'],
            'from' => $query['from'],
            'to' => $query['to'],
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * Queue project_notify emails for project notify-only watchers.
     * Does not create overtime_accept rows. Skips emails already queued to approvers.
     *
     * @param int[] $projectIds
     * @param array<string, true> $alreadyQueuedEmails lowercase email => true
     */
    private function queueProjectNotifyEmails(
        int $overtimeId,
        array $projectIds,
        array $alreadyQueuedEmails
    ): void {
        $recipients = $this->projectNotifyRepo->findRecipientsByProjectIds($projectIds);
        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '' || isset($alreadyQueuedEmails[$email])) {
                continue;
            }
            $alreadyQueuedEmails[$email] = true;
            $this->overtimeRepo->insertEmailQueue([
                'email_to' => $recipient['email'],
                'approver_name' => $recipient['surname'] ?? 'Notify',
                'overtime_id' => $overtimeId,
                'email_type' => 'project_notify',
            ]);
        }
    }

    /**
     * OGA rows expose `approval_level`; the legacy Form PIC fallback exposes `role`.
     *
     * @param array<string, mixed> $approver
     */
    private function resolveApprovalLevel(array $approver): int
    {
        foreach (['approval_level', 'role'] as $key) {
            if (isset($approver[$key]) && (int) $approver[$key] > 0) {
                return (int) $approver[$key];
            }
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array{
     *   project_id: int, hours: int, minutes: int, item_id: int, job_id: int, tow_id: int,
     *   work_2d3d: ?string, revision: int
     * }|null, 1: ?string}
     */
    private function parseWorkFields(array $input, int $groupId, string $groupAbbreviation, int $actorUserId = 0): array
    {
        $projectId = (int) ($input['project_id'] ?? 0);
        $hours = filter_var($input['hours'] ?? null, FILTER_VALIDATE_INT);
        $minutes = filter_var($input['minutes'] ?? 0, FILTER_VALIDATE_INT);
        $itemId = (int) ($input['item_id'] ?? 0);
        $jobId = (int) ($input['job_id'] ?? 0);
        $towId = (int) ($input['tow_id'] ?? 0);

        if ($projectId <= 0) {
            return [null, 'Please select a project.'];
        }
        if ($hours === false || $hours < 0) {
            return [null, 'Hours must be a whole number (0 or more).'];
        }
        if ($minutes === false || $minutes < 0 || $minutes > 59) {
            return [null, 'Minutes must be between 0 and 59.'];
        }
        if ($hours === 0 && $minutes === 0) {
            return [null, 'Enter at least 1 minute of overtime.'];
        }
        if ($itemId <= 0) {
            return [null, 'Please select an item of work.'];
        }
        if ($jobId <= 0) {
            return [null, 'Please select a job request description.'];
        }
        if ($towId <= 0) {
            return [null, 'Please select a type of work.'];
        }

        if (!$this->overtimeRepo->projectsBelongToGroup([$projectId], $groupAbbreviation, $actorUserId)) {
            return [null, 'The selected project does not belong to the selected group.'];
        }

        if (!$this->workLookup->itemBelongsToProject($itemId, $projectId, $groupAbbreviation)) {
            return [null, 'The selected item of work is not valid for this project.'];
        }

        if (!$this->workLookup->jobBelongsToProjectItem($jobId, $projectId, $itemId, $groupAbbreviation)) {
            return [null, 'The selected job request description is not valid for this item.'];
        }

        if (!$this->workLookup->towIsValidForProject($towId, $projectId)) {
            return [null, 'The selected type of work is not valid for this project.'];
        }

        $requiresDim = $this->requiresDimSection($projectId, $groupId);
        $work2d3dRaw = trim((string) ($input['work_2d3d'] ?? ''));
        $revisionRaw = $input['revision'] ?? 0;

        if ($requiresDim) {
            if (!in_array($work2d3dRaw, self::WORK_2D3D_VALUES, true)) {
                return [null, 'Please select a 2D/3D option.'];
            }
            $work2d3d = $work2d3dRaw;
            $revision = ((int) $revisionRaw) === 1 ? 1 : 0;
        } else {
            $work2d3d = null;
            $revision = 0;
        }

        return [[
            'project_id' => $projectId,
            'hours' => $hours,
            'minutes' => $minutes,
            'item_id' => $itemId,
            'job_id' => $jobId,
            'tow_id' => $towId,
            'work_2d3d' => $work2d3d,
            'revision' => $revision,
        ], null];
    }

    private function requiresDimSection(int $projectId, int $groupId): bool
    {
        if ($groupId <= 0 || in_array($groupId, self::DIM_EXCLUDED_GROUP_IDS, true)) {
            return false;
        }

        $direct = $this->workLookup->findProjectDirect($projectId);

        return $direct === 1;
    }

    private function validateRequestDate(string $date, int $employeeId, bool $relaxed = false): ?string
    {
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Invalid request date.';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return 'Invalid request date.';
        }

        if ($relaxed) {
            return null;
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
