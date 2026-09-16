<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use Throwable;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = compact('method', 'path', 'pattern', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        $basePath = parse_url((string) config('app.url'), PHP_URL_PATH) ?: '';
        if ($basePath !== '' && str_starts_with($path, rtrim($basePath, '/'))) {
            $path = substr($path, strlen(rtrim($basePath, '/'))) ?: '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match('#^' . $route['pattern'] . '$#', $path, $matches)) {
                continue;
            }

            try {
                foreach ($route['middleware'] as $middleware) {
                    $middleware === 'auth' ? AuthMiddleware::handle() : PermissionMiddleware::handle($middleware);
                }
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $route['handler'];
                (new $class())->{$action}(...array_values($params));
            } catch (Throwable $exception) {
                $referenceId = 'ERR-' . strtoupper(bin2hex(random_bytes(4)));
                error_log(sprintf('[app][%s] %s in %s:%d', $referenceId, $exception->getMessage(), $exception->getFile(), $exception->getLine()));
                $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
                $authenticated = Auth::check();
                http_response_code(500);
                View::render('errors/500', [
                    'message' => $exception->getMessage(),
                    'referenceId' => $referenceId,
                    'backUrl' => $this->safeBackUrl($referer, $uri) ?: url($authenticated ? '/dashboard' : '/login'),
                    'homeUrl' => url($authenticated ? '/dashboard' : '/login'),
                    'homeLabel' => $authenticated ? 'กลับ Dashboard' : 'กลับหน้า Login',
                    'authenticated' => $authenticated,
                ], $authenticated ? 'layouts/app' : 'layouts/guest');
            }
            return;
        }

        http_response_code(404);
        echo '404 - ไม่พบหน้าที่ต้องการ';
    }

    /** Return a same-host referrer suitable for a recovery link. */
    private function safeBackUrl(string $referer, string $currentUri): ?string
    {
        if ($referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        if ($parts === false) {
            return null;
        }

        $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $requestParts = parse_url('http://' . $requestHost) ?: [];
        if (isset($parts['host']) && strcasecmp((string) $parts['host'], (string) ($requestParts['host'] ?? '')) !== 0) {
            return null;
        }
        if (isset($parts['port']) && (string) $parts['port'] !== (string) ($requestParts['port'] ?? '')) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        $currentPath = (string) (parse_url($currentUri, PHP_URL_PATH) ?: '/');
        if ($path === $currentPath || str_contains($path, '/errors')) {
            return null;
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
