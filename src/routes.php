<?php
declare(strict_types=1);

use FastRoute\RouteCollector;

return function (RouteCollector $r): void {
    $r->addRoute('GET', '/api/csrf', ['App\Controller\SecurityController', 'csrfToken']);
    $r->addRoute('GET', '/api/session', ['App\Controller\UserController', 'getSession']);
    $r->addRoute('GET', '/api/groups', ['App\Controller\GroupController', 'getGroupsByUserId']);
    $r->addRoute('GET', '/api/locations', ['App\Controller\LocationController', 'getLocations']);
    $r->addRoute('GET', '/api/projects', ['App\Controller\ProjectController', 'getProjects']);
    $r->addRoute('GET', '/api/holidays', ['App\Controller\OvertimeController', 'getHolidays']);
    $r->addRoute('GET', '/api/approve/employee-groups', ['App\Controller\OvertimeController', 'getEmployeeGroups']);
    $r->addRoute('GET', '/api/approve/approver-groups', ['App\Controller\OvertimeController', 'getApproverGroups']);
    $r->addRoute('GET', '/api/approve/employees', ['App\Controller\OvertimeController', 'searchApproverEmployees']);
    $r->addRoute('POST', '/api/approve/addovertime', ['App\Controller\OvertimeController', 'addOvertimeOnBehalf']);
    $r->addRoute('POST', '/api/approve/followup', ['App\Controller\OvertimeController', 'followUpRequest']);
    $r->addRoute('GET', '/api/overtimehistory', ['App\Controller\OvertimeController', 'getUserHistory']);
    $r->addRoute('POST', '/api/addovertime', ['App\Controller\OvertimeController', 'addOvertime']);
    $r->addRoute('GET', '/api/overtimetoapprove', ['App\Controller\OvertimeController', 'getOvertimeToApprove']);
    $r->addRoute('GET', '/api/approve/dashboard', ['App\Controller\OvertimeController', 'getApproverDashboard']);
    $r->addRoute('POST', '/api/approveovertime', ['App\Controller\OvertimeController', 'approveOvertime']);
    $r->addRoute('POST', '/api/approve/bulk', ['App\Controller\OvertimeController', 'approveOvertimeBulk']);
    $r->addRoute('POST', '/api/cancelovertime', ['App\Controller\OvertimeController', 'cancelOvertime']);
    $r->addRoute('GET', '/api/admin/session', ['App\Controller\AdminController', 'getSession']);
    $r->addRoute('GET', '/api/admin/logs', ['App\Controller\AdminController', 'getActivityLogs']);
    $r->addRoute('GET', '/api/admin/reports/ot', ['App\Controller\AdminController', 'getOtReport']);
    $r->addRoute('GET', '/api/admin/groups', ['App\Controller\AdminController', 'getAdminGroups']);
    $r->addRoute('GET', '/api/admin/employees', ['App\Controller\AdminController', 'searchEmployees']);
    $r->addRoute('GET', '/api/admin/members', ['App\Controller\AdminController', 'getAdminMembers']);
    $r->addRoute('POST', '/api/admin/members', ['App\Controller\AdminController', 'addAdminMember']);
    $r->addRoute('POST', '/api/admin/members/update', ['App\Controller\AdminController', 'updateAdminMember']);
    $r->addRoute('POST', '/api/admin/members/remove', ['App\Controller\AdminController', 'removeAdminMember']);
    $r->addRoute('GET', '/api/admin/approvers', ['App\Controller\AdminController', 'getGroupApprovers']);
    $r->addRoute('POST', '/api/admin/approvers', ['App\Controller\AdminController', 'saveGroupApprovers']);
    $r->addRoute('POST', '/api/admin/approver-level', ['App\Controller\AdminController', 'saveGroupApproverLevel']);
    $r->addRoute('POST', '/api/admin/approver-logs', ['App\Controller\AdminController', 'logApproverAction']);
};
