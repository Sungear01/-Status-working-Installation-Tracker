<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/helpers.php';

$composerAutoload = base_path('vendor/autoload.php');
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

App\Core\Env::load(base_path('.env'));
$app = config('app');
date_default_timezone_set((string) $app['timezone']);
ini_set('display_errors', $app['debug'] ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', base_path('storage/logs/php-error.log'));

session_name((string) $app['session_name']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
