<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['auth']['id']);
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['auth']['id'] : null;
    }

    public static function user(): ?array
    {
        return $_SESSION['auth'] ?? null;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        return $user !== null && ($user['role_slug'] === 'admin' || in_array($permission, $user['permissions'] ?? [], true));
    }

    /** Return the first application page available to the signed-in user. */
    public static function landingPath(): string
    {
        if (self::can('view_dashboard')) {
            return '/dashboard';
        }
        if (self::can('view_customer')) {
            return '/customers';
        }
        if (self::can('view_activity_log')) {
            return '/admin/logs';
        }

        return '/profile';
    }

    public static function canEditCustomerField(string $field): bool
    {
        $user = self::user();
        if ($user === null || !self::can('edit_customer')) {
            return false;
        }
        if ($user['role_slug'] === 'admin') {
            return true;
        }
        $fieldsByRole = config('customer.editable_fields_by_role', []);
        if (array_key_exists($user['role_slug'], $fieldsByRole)) {
            return in_array($field, $fieldsByRole[$user['role_slug']], true);
        }
        return true;
    }

    public static function login(array $user, array $permissions, string $sessionToken): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role_name'],
            'role_slug' => $user['role_slug'],
            'company_id' => isset($user['company_id']) && $user['company_id'] !== null ? (int) $user['company_id'] : null,
            'permissions' => $permissions,
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            'session_token' => $sessionToken,
        ];
        $_SESSION['last_activity'] = time();
    }

    /** The single company a non-admin user is confined to, or null when unrestricted (admin, or no company assigned). */
    public static function restrictedCompanyId(): ?int
    {
        $user = self::user();
        if ($user === null || ($user['role_slug'] ?? null) === 'admin') {
            return null;
        }
        return isset($user['company_id']) && $user['company_id'] !== null ? (int) $user['company_id'] : null;
    }

    public static function sessionIsCurrent(): bool
    {
        if (!self::check()) {
            return false;
        }
        return (new \App\Repositories\UserRepository())->sessionTokenMatches(self::id() ?? 0, $_SESSION['auth']['session_token'] ?? null);
    }

    /** Refresh the active user's role permissions so role changes take effect immediately. */
    public static function refreshAuthorization(): bool
    {
        if (!self::check()) {
            return false;
        }

        $repository = new \App\Repositories\UserRepository();
        $user = $repository->findWithRole(self::id() ?? 0);
        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        $_SESSION['auth']['username'] = $user['username'];
        $_SESSION['auth']['name'] = $user['name'];
        $_SESSION['auth']['role'] = $user['role_name'];
        $_SESSION['auth']['role_slug'] = $user['role_slug'];
        $_SESSION['auth']['company_id'] = isset($user['company_id']) && $user['company_id'] !== null ? (int) $user['company_id'] : null;
        $_SESSION['auth']['permissions'] = $repository->permissions((int) $user['id'], (int) $user['role_id']);
        $_SESSION['auth']['must_change_password'] = (bool) ($user['must_change_password'] ?? false);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
