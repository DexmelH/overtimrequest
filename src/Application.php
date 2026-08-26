<?php
declare(strict_types=1);

namespace App;

use FastRoute\Dispatcher;
use Throwable;
use function FastRoute\simpleDispatcher;

class Application
{
    private array $config;
    private Container $container;

    public function __construct(array $config, Container $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    public function run(): void
    {
        $this->startSession();

        $uri = $this->normalizeRequestUri();
        $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $routeInfo = $this->createDispatcher()->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $this->jsonResponse(404, ['success' => false, 'errors' => ['Not found']]);
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $this->jsonResponse(405, ['success' => false, 'errors' => ['Method not allowed']]);
                break;

            case Dispatcher::FOUND:
                [$class, $method] = $routeInfo[1];
                $vars = $routeInfo[2];

                if ($httpMethod === 'GET' && $class === 'App\Controller\SecurityController' && $method === 'csrfToken') {
                    $this->jsonResponse(200, ['success' => true, 'token' => $this->ensureCsrfToken()]);
                }

                if ($httpMethod === 'POST' && !$this->validateCsrfForPost()) {
                    $this->jsonResponse(403, ['success' => false, 'errors' => ['Forbidden']]);
                }

                try {
                    if (!$this->container->has($class)) {
                        throw new \RuntimeException("Controller {$class} is not registered in src/services.php");
                    }

                    $controller = $this->container->get($class);

                    if (!method_exists($controller, $method)) {
                        throw new \RuntimeException("Handler method {$method} not found on controller {$class}");
                    }

                    $result = call_user_func_array([$controller, $method], $vars);
                    $this->jsonResponse(200, $result);
                } catch (Throwable $e) {
                    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
                    $isLocal = ($this->config['app']['env'] ?? 'local') === 'local';
                    $this->jsonResponse(500, [
                        'success' => false,
                        'errors' => $isLocal ? $e->getMessage() : 'Internal server error',
                    ]);
                }
                break;
        }
    }

    private function createDispatcher(): Dispatcher
    {
        return simpleDispatcher(require __DIR__ . '/routes.php');
    }

    private function startSession(): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function normalizeRequestUri(): string
    {
        $basePath = $this->config['app']['base_path'] ?? '/overtime';
        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = preg_replace('#\?.*$#', '', $rawUri);

        if ($basePath !== '' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        if ($uri === '') {
            $uri = '/';
        }
        if ($uri[0] !== '/') {
            $uri = '/' . $uri;
        }

        return $uri;
    }

    private function jsonResponse(int $code, array $payload): void
    {
        http_response_code($code);
        echo json_encode($payload);
        exit;
    }

    private function ensureCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function getRequestHeader(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return trim((string) $_SERVER[$serverKey]);
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    private function validateCsrfForPost(): bool
    {
        $sessionToken = $this->ensureCsrfToken();
        $requestToken = $this->getRequestHeader('X-CSRF-Token');
        if ($requestToken === '') {
            $requestToken = trim((string) ($_POST['_csrf'] ?? ''));
        }
        if ($requestToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }
}
