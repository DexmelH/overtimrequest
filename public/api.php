<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../src/config.php';

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

header('Content-Type: application/json; charset=utf-8');

// --- normalize request URI relative to app base ---
$basePath = $config['app']['base_path'] ?? '/overtime';
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = preg_replace('#\?.*$#', '', $rawUri);

// remove basePath if present
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
if ($uri === '') {
    $uri = '/';
}
// ensure leading slash
if ($uri[0] !== '/') {
    $uri = '/' . $uri;
}

// --- build dispatcher ---
$dispatcher = simpleDispatcher(function(RouteCollector $r) {
    $r->addRoute('GET', '/api/session', ['App\Controller\UserController', 'getSession']);
    $r->addRoute('GET', '/api/groups', ['App\Controller\GroupController', 'getGroupsByUserId']);
    $r->addRoute('GET', '/api/locations', ['App\Controller\LocationController', 'getLocations']);
    $r->addRoute('GET', '/api/projects', ['App\Controller\ProjectController', 'getProjects']);
    $r->addRoute('GET', '/api/items', ['App\Controller\ItemController', 'getItems']);
    $r->addRoute('GET', '/api/jobs', ['App\Controller\JobController', 'getJobs']);
    $r->addRoute('GET', '/api/works', ['App\Controller\WorkController', 'getWorks']);
    $r->addRoute('GET', '/api/holidays', ['App\Controller\OvertimeController', 'getHolidays']);
    $r->addRoute('GET', '/api/approve/employee-groups', ['App\Controller\OvertimeController', 'getEmployeeGroups']);
    $r->addRoute('GET', '/api/approve/approver-groups', ['App\Controller\OvertimeController', 'getApproverGroups']);
    $r->addRoute('GET', '/api/approve/managed-groups', ['App\Controller\OvertimeController', 'getApproverGroups']);
    $r->addRoute('GET', '/api/approve/employees', ['App\Controller\OvertimeController', 'searchApproverEmployees']);
    $r->addRoute('POST', '/api/approve/addovertime', ['App\Controller\OvertimeController', 'addOvertimeOnBehalf']);
    $r->addRoute('GET', '/api/overtimehistory', ['App\Controller\OvertimeController', 'getUserHistory']);
    $r->addRoute('POST', '/api/addovertime', ['App\Controller\OvertimeController', 'addOvertime']);
    $r->addRoute('GET', '/api/overtimetoapprove', ['App\Controller\OvertimeController', 'getOvertimeToApprove']);
    $r->addRoute('POST', '/api/approveovertime', ['App\Controller\OvertimeController', 'approveOvertime']);
    $r->addRoute('POST', '/api/cancelovertime', ['App\Controller\OvertimeController', 'cancelOvertime']);
    $r->addRoute('GET', '/api/admin/session', ['App\Controller\AdminController', 'getSession']);
    $r->addRoute('GET', '/api/admin/logs', ['App\Controller\AdminController', 'getActivityLogs']);
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
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

// --- Database manager (instantiate before controller factory) ---
$dbManager = new \App\Database($config['connections'] ?? $config);

// central response helper
function jsonResponse(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

switch ($routeInfo[0]) {
    case \FastRoute\Dispatcher::NOT_FOUND:
        jsonResponse(404, ['success' => false, 'errors' => ['Not found']]);
        break;

    case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        jsonResponse(405, ['success' => false, 'errors' => ['Method not allowed']]);
        break;

    case \FastRoute\Dispatcher::FOUND:
        [$class, $method] = $routeInfo[1];
        $vars = $routeInfo[2];

        try {
            // controller factory: map controllers to the PDO they need
            $webjmrPdo = $dbManager->getConnection('webjmr');
            $kdtphPdo = $dbManager->getConnection('kdtph');
            $kdtphNewPdo = $dbManager->getConnection('kdtphnew');
            $formsPdo = $dbManager->getConnection('forms');

            $activityLogger = new \App\Service\ActivityLogger(
                new \App\Repository\ActivityLogRepository($webjmrPdo)
            );

            $controllerFactory = [
                'App\Controller\UserController' => function() use ($kdtphPdo, $webjmrPdo, $kdtphNewPdo, $config) {
                    $adminAccess = new \App\Service\AdminAccessService(
                        new \App\Repository\AdminMemberRepository($webjmrPdo),
                        new \App\Repository\EmployeeRepository($kdtphNewPdo),
                        $config['app']['admin_group_abbrs'] ?? []
                    );
                    return new \App\Controller\UserController(
                        new \App\Repository\UserRepository($kdtphPdo),
                        new \App\Repository\GroupApproverRepository($webjmrPdo),
                        $adminAccess
                    );
                },
                'App\Controller\GroupController' => function() use ($kdtphNewPdo, $kdtphPdo) {
                    return new \App\Controller\GroupController($kdtphNewPdo, $kdtphPdo);
                },
                'App\Controller\OvertimeController' => function() use ($webjmrPdo, $kdtphPdo, $formsPdo, $kdtphNewPdo, $activityLogger) {
                    return new \App\Controller\OvertimeController($webjmrPdo, $kdtphPdo, $formsPdo, $kdtphNewPdo, $activityLogger);
                },
                'App\Controller\AdminController' => function() use ($webjmrPdo, $kdtphPdo, $kdtphNewPdo, $activityLogger, $config) {
                    $adminMemberRepo = new \App\Repository\AdminMemberRepository($webjmrPdo);
                    $employeeRepo = new \App\Repository\EmployeeRepository($kdtphNewPdo);
                    $adminAccess = new \App\Service\AdminAccessService(
                        $adminMemberRepo,
                        $employeeRepo,
                        $config['app']['admin_group_abbrs'] ?? []
                    );
                    return new \App\Controller\AdminController(
                        new \App\Repository\ActivityLogRepository($webjmrPdo),
                        new \App\Repository\UserRepository($kdtphPdo),
                        $employeeRepo,
                        new \App\Repository\GroupApproverRepository($webjmrPdo),
                        $adminMemberRepo,
                        $adminAccess,
                        $activityLogger
                    );
                },
                'App\Controller\LocationController' => function() use ($webjmrPdo) {
                    return new \App\Controller\LocationController($webjmrPdo);
                },
                'App\Controller\ProjectController' => function() use ($webjmrPdo) {
                    return new \App\Controller\ProjectController($webjmrPdo);
                },
                'App\Controller\ItemController' => function() use ($webjmrPdo) {
                    return new \App\Controller\ItemController($webjmrPdo);
                },
                'App\Controller\JobController' => function() use ($webjmrPdo) {
                    return new \App\Controller\JobController($webjmrPdo);
                },
                'App\Controller\WorkController' => function() use ($webjmrPdo) {
                    return new \App\Controller\WorkController($webjmrPdo);
                },
            ];

            if (isset($controllerFactory[$class])) {
                $controller = $controllerFactory[$class]();
            } else {
                // fallback: instantiate controller with default PDO
                $defaultPdo = $dbManager->getDefault();
                $controller = new $class($defaultPdo);
            }

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Handler method {$method} not found on controller {$class}");
            }

            $result = call_user_func_array([$controller, $method], $vars);
            jsonResponse(200, $result);

        } catch (Throwable $e) {
            error_log($e->getMessage() . "\n" . $e->getTraceAsString());
            jsonResponse(500, ['success' => false, 'errors' => $e->getMessage()]);
        }
        break;
}
