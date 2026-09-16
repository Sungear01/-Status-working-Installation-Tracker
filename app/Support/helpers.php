<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

function base_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);
    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    static $items = [];
    [$file, $nested] = array_pad(explode('.', $key, 2), 2, null);
    if (!array_key_exists($file, $items)) {
        $path = base_path("config/{$file}.php");
        $items[$file] = is_file($path) ? require $path : [];
    }
    if ($nested === null) {
        return $items[$file] ?? $default;
    }
    $value = $items[$file] ?? [];
    foreach (explode('.', $nested) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url'), '/');
    // When using the built-in server, preserve the host/port the user opened.
    // This prevents APP_URL=localhost from redirecting 127.0.0.1:8000 back to XAMPP.
    $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (preg_match('/^(127\.0\.0\.1|localhost):\d+$/', $requestHost)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $requestHost;
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function date_th(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    try {
        $dateTime = new DateTimeImmutable($date);
        return $dateTime->format('d/m/') . ((int) $dateTime->format('Y') + 543);
    } catch (Throwable) {
        return '-';
    }
}

function status_class(string $status, ?string $dueDate = null): string
{
    $normalized = mb_strtoupper(trim($status));
    $statusColors = [
        'YES' => 'success',
        'NO' => 'danger',
        'รอ' => 'warning',
        'STATUS' => 'secondary',
    ];
    if (isset($statusColors[$normalized])) {
        return $statusColors[$normalized];
    }
    $completed = array_map('mb_strtoupper', config('customer.status.completed', []));
    if (in_array($normalized, $completed, true)) {
        return 'success';
    }
    if ($dueDate) {
        $days = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($dueDate))->format('%r%a');
        if ($days < 0) {
            return 'danger';
        }
        if ($days <= (int) config('customer.near_due_days', 7)) {
            return 'warning';
        }
    }
    return 'secondary';
}
