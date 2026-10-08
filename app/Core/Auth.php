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
        return $user !== null && (self::isSuperRole($user['role_slug']) || in_array($permission, $user['permissions'] ?? [], true));
    }

    /** Admin and developer (system creator) roles bypass permission and company restrictions. */
    public static function isSuperRole(?string $roleSlug): bool
    {
        return $roleSlug === 'admin' || $roleSlug === 'developer';
    }

    /** True only for a signed-in system creator who is not currently using another user's account. */
    public static function isDeveloper(): bool
    {
        $user = self::user();
        return $user !== null && self::impersonator() === null && ($user['role_slug'] ?? null) === 'developer';
    }

    /** The original system creator while they are using another user's account, otherwise null. */
    public static function impersonator(): ?array
    {
        return $_SESSION['impersonator'] ?? null;
    }

    /** Switch the session to $target while remembering the developer so they can switch back. */
    public static function startImpersonation(array $target, array $permissions): void
    {
        $developer = self::user();
        session_regenerate_id(true);
        $_SESSION['impersonator'] = [
            'id' => (int) $developer['id'],
            'username' => $developer['username'],
            'name' => $developer['name'],
            'session_token' => $developer['session_token'] ?? null,
        ];
        $_SESSION['auth'] = [
            'id' => (int) $target['id'],
            'username' => $target['username'],
            'name' => $target['name'],
            'role' => $target['role_name'],
            'role_slug' => $target['role_slug'],
            'company_id' => isset($target['company_id']) && $target['company_id'] !== null ? (int) $target['company_id'] : null,
            'permissions' => $permissions,
            'must_change_password' => false,
            'session_token' => null,
        ];
        $_SESSION['last_activity'] = time();
    }

    /** Forget the impersonation and return the developer it belonged to, or null when not impersonating. */
    public static function endImpersonation(): ?array
    {
        $impersonator = self::impersonator();
        unset($_SESSION['impersonator']);
        return $impersonator;
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

    /** Company table the current request works on; per-table edit rights (see tableAccess) are checked against it. */
    private static ?int $currentCompanyId = null;

    /** Per-request cache of tableAccess(); false means "not loaded yet". */
    private static array|null|false $tableAccess = false;

    public static function setCurrentCompany(?int $companyId): void
    {
        self::$currentCompanyId = $companyId !== null && $companyId > 0 ? $companyId : null;
    }

    public static function currentCompanyId(): ?int
    {
        return self::$currentCompanyId;
    }

    /**
     * [company id => editable column keys] an admin set for the signed-in user, or null when not configured (or for
     * admin/developer, who see and edit everything). Read on every request, so changes apply without signing out.
     */
    public static function tableAccess(): ?array
    {
        $user = self::user();
        if ($user === null || self::isSuperRole($user['role_slug'] ?? null)) {
            return null;
        }
        if (self::$tableAccess === false) {
            self::$tableAccess = (new \App\Repositories\UserTableAccessRepository())->forUser((int) $user['id']);
        }
        return self::$tableAccess;
    }

    /** The company tables the user may see, or null when unrestricted. Per-user access wins over the single company. */
    public static function allowedCompanyIds(): ?array
    {
        $access = self::tableAccess();
        if ($access !== null) {
            return array_keys($access);
        }
        $companyId = self::restrictedCompanyId();
        return $companyId === null ? null : [$companyId];
    }

    /**
     * Columns nobody edits here, not even admin: read from Landy Home (worksheets.zone, employee.initial_name), or
     * calculated from วันที่ส่ง PO by customer_installation_view (+61 and +60 days), which ignores any typed value.
     */
    public const SOURCE_FIELDS = ['zone', 'engineer', 'po_due_date', 'due_60_date'];

    public static function canEditCustomerField(string $field): bool
    {
        $user = self::user();
        if ($user === null || !self::can('edit_customer') || in_array($field, self::SOURCE_FIELDS, true)) {
            return false;
        }
        if (self::isSuperRole($user['role_slug'])) {
            return true;
        }
        $access = self::tableAccess();
        if ($access !== null) {
            return self::$currentCompanyId !== null && in_array($field, $access[self::$currentCompanyId] ?? [], true);
        }
        // Without per-user access, custom columns follow the edit_customer permission alone, as before.
        if (str_starts_with($field, 'custom_')) {
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
        if ($user === null || self::isSuperRole($user['role_slug'] ?? null)) {
            return null;
        }
        return isset($user['company_id']) && $user['company_id'] !== null ? (int) $user['company_id'] : null;
    }

    public static function sessionIsCurrent(): bool
    {
        if (!self::check()) {
            return false;
        }
        // While impersonating, the session belongs to the developer, so validate the developer's own token.
        $impersonator = self::impersonator();
        if ($impersonator !== null) {
            return (new \App\Repositories\UserRepository())->sessionTokenMatches((int) $impersonator['id'], $impersonator['session_token'] ?? null);
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
        $_SESSION['auth']['must_change_password'] = self::impersonator() === null && (bool) ($user['must_change_password'] ?? false);

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
