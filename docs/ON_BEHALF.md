# On-Behalf Overtime Submission

Approvers can submit and **auto-approve** overtime requests for employees they handle. This document describes the authorization rules and where they are enforced.

## Who may use on-behalf

The actor must pass `ApproverDirectoryService::isApprover()`:

- Assigned in `overtime_group_approvers`, **or**
- Listed as a Form PIC approver in `formspic`

Enforced in:

- `OvertimeSubmissionService::addOvertimeOnBehalf()` — rejects non-approvers
- `OvertimeController::searchApproverEmployees()` / `getEmployeeGroups()` — same gate for read APIs
- `ProjectController::canLoadProjectsForEmployee()` — when `employee_id` differs from the logged-in user

## Core rules

### 1. Approver-group check (employee **main** group only)

The approver may only submit for an employee whose **main group** (`employee_list.group_id`) is among the groups the approver approves for.

- **Main group** comes from `employee_list.group_id` / `group_abbr` (via `EmployeeRepository::findById()`).
- **Approver groups** come from `ApproverDirectoryService::getApproverGroupIds()`: OGA assignments plus every Form PIC group, including groups that also have OGA config. Being either kind of approver for the group is enough.

The **selected OT group** on the form is **not** used for this check.

**Enforced:** `OvertimeSubmissionService::addOvertimeOnBehalf()` before accept.

### 2. Selected OT group (any `employee_group` row)

The group chosen on the request must be one the employee is assigned to in `employee_group` (not necessarily their main group).

**Enforced:** `EmployeeRepository::isEmployeeInEmployeeGroup()` in `addOvertimeOnBehalf()`.

**UI:** `GET /api/approve/employee-groups?employee_id=` returns all groups from `employee_group` for that employee (`findGroupsByEmployeeId()`). The dropdown is populated in `public/approve/onBehalf.js`.

### 3. OGA / Form PIC approval chain (main group)

After insert, approvers are resolved from the employee’s **main group**, not the selected OT group:

```php
resolveApprovers($mainGroupId, $mainGroupAbbrev, $employeeId)
```

Configured OGA rows are used first; Form PIC fallback applies when no OGA is configured for that group.

**Enforced:** `OvertimeSubmissionService::addOvertimeOnBehalf()` — `resolveApprovers()` receives main group IDs/abbrs; payload `group_id` is only the OT group on the request record.

### 4. Projects API (`GET /api/projects`)

Query: `?group=<abbrev>&employee_id=<id>` (on-behalf).

| Param | Purpose |
|--------|---------|
| `group` | Group abbreviation — loads active projects for that group |
| `employee_id` | When set and ≠ session user, loads that employee’s **shared** projects (`project_share`) |

Response merges group projects + shared projects (deduped by project ID).

**Authorization:** If `employee_id` is another user, the approver must handle that employee’s **main group** (same rule as submit). Otherwise `403`.

**Enforced:** `ProjectController::canLoadProjectsForEmployee()`.

**Frontend:** `public/shared/js/projectAllocations.js` appends `employee_id` when `employeeIdSelector` is configured; on-behalf passes `#obEmployeeId` in `onBehalf.js`.

### 5. Date validation (relaxed on-behalf)

Server: `validateRequestDate($date, $employeeId, relaxed: true)` in `addOvertimeOnBehalf()`.

Relaxed mode allows:

- Past dates
- Any weekday/weekend/holiday (no current-week or leave-week rules)

Relaxed mode still rejects invalid or malformed dates.

Client: `configureRequestDate({ relaxed: true })` in `onBehalf.js` mirrors this for UX (no past-date block in UI either).

Self-service submit (`addOvertime()`) uses `relaxed: false` — stricter rules apply.

## Employee search scope

`GET /api/approve/employees?q=` returns employees who appear in `employee_group` for at least one of the approver’s group IDs. This narrows the picker; **submit still requires** the employee’s main group to be one the approver approves for (rule 1).

## Submit flow (auto-approve)

`POST /api/approve/addovertime` → `addOvertimeOnBehalf()`:

1. Verify approver (`isApprover()`)
2. Validate employee + relaxed date
3. Verify main group handled
4. Verify selected OT group ∈ `employee_group`
5. Validate projects belong to selected group (or shared to employee)
6. Insert request with `user_id` = employee, `group_id` = selected OT group
7. Resolve approvers from **main group**; pre-approve all acceptance rows
8. Set request status approved, daily report + status email

No approval cutoff lock on on-behalf (employees may be locked after cutoff; approvers are not).

## API reference

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/approve/approver-groups` | Approver flag + groups handled |
| GET | `/api/approve/employees?q=` | Search employees in the approver's groups |
| GET | `/api/approve/employee-groups?employee_id=` | OT group dropdown for employee |
| GET | `/api/projects?group=&employee_id=` | Projects for selected OT group + employee shares |
| POST | `/api/approve/addovertime` | Submit on-behalf (CSRF required) |

## Code map

| Rule | File | Location |
|------|------|----------|
| `isApprover()` | `OvertimeSubmissionService.php` | start of `addOvertimeOnBehalf()` |
| Main group handled | `OvertimeSubmissionService.php` | `$mainGroupId` vs `$approverGroupIds` |
| OT group membership | `OvertimeSubmissionService.php` | `isEmployeeInEmployeeGroup()` |
| OGA / Form PIC from main group | `OvertimeSubmissionService.php` | `resolveApprovers($mainGroupId, …)` |
| Approver directory | `ApproverDirectoryService.php` | `isApprover()`, `getApproverGroupIds()`, `resolveApprovers()` |
| Projects auth + merge | `ProjectController.php` | `getProjects()`, `canLoadProjectsForEmployee()` |
| On-behalf UI | `public/approve/onBehalf.js` | modal, employee search, relaxed date |
| Projects + `employee_id` | `public/shared/js/projectAllocations.js` | `loadProjects()` |

## Verification status

All rules above were verified against the current codebase. No rule changes were required for this documentation pass.
