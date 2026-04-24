<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../src/config.php';

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

header('Content-Type: application/json; charset=utf-8');

// --- normalize request URI relative to app base ---
$basePath = '/overtime'; // set to '' if docroot is public/
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
    $r->addRoute('GET', '/api/groups', ['App\Controller\GroupController', 'getGroupsByUserId']);
    $r->addRoute('GET', '/api/locations', ['App\Controller\LocationController', 'getLocations']);
    $r->addRoute('GET', '/api/projects', ['App\Controller\ProjectController', 'getProjects']);
    $r->addRoute('GET', '/api/items', ['App\Controller\ItemController', 'getItems']);
    $r->addRoute('GET', '/api/jobs', ['App\Controller\JobController', 'getJobs']);
    $r->addRoute('GET', '/api/works', ['App\Controller\WorkController', 'getWorks']);
    $r->addRoute('GET', '/api/overtimehistory', ['App\Controller\OvertimeController', 'getUserHistory']);
    $r->addRoute('POST', '/api/addovertime', ['App\Controller\OvertimeController', 'addOvertime']);
    $r->addRoute('GET', '/api/overtimetoapprove', ['App\Controller\OvertimeController', 'getOvertimeToApprove']);
    $r->addRoute('POST', '/api/approveovertime', ['App\Controller\OvertimeController', 'approveOvertime']);
    // include other routes or require route files here
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
            $controllerFactory = [
                'App\Controller\GroupController' => function() use ($dbManager) {
                    $groupPDO = $dbManager->getConnection('kdtphnew');
                    $userPDO = $dbManager->getConnection('kdtph');
                    return new \App\Controller\GroupController($groupPDO, $userPDO);
                },
                'App\Controller\OvertimeController' => function() use ($dbManager) {
                    $overtimePDO = $dbManager->getConnection('webjmr');
                    $userPDO = $dbManager->getConnection('kdtph');
                    return new \App\Controller\OvertimeController($overtimePDO, $userPDO);
                },
                // add other controllers here...
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
