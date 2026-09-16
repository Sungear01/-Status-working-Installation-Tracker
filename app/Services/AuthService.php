<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\UserRepository;

final class AuthService
{
    public function attempt(string $username, string $password): bool
    {
        $users = new UserRepository();
        $logger = new ActivityLogger();
        if ($this->tooManyAttempts($username)) {
            $logger->loginAttempt($username, null, false);
            return false;
        }
        $user = $users->findByUsername($username);
        $valid = $user !== null && (bool) $user['is_active'] && password_verify($password, $user['password']);
        $logger->loginAttempt($username, $user ? (int) $user['id'] : null, $valid);
        if (!$valid) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return false;
        }
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $users->resetPassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $sessionToken = bin2hex(random_bytes(32));
        $users->setSessionToken((int) $user['id'], $sessionToken);
        Auth::login($user, $users->permissions((int) $user['id'], (int) $user['role_id']), $sessionToken);
        $users->touchLastLogin((int) $user['id']);
        $logger->log('login', 'user', (int) $user['id'], 'เข้าสู่ระบบสำเร็จ');
        return true;
    }

    public function isLocked(string $username): bool
    {
        return $this->tooManyAttempts($username);
    }

    private function tooManyAttempts(string $username): bool
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM login_logs WHERE username_attempt = :username AND ip_address <=> :ip_address AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $statement->execute(['username' => $username, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null]);
        return (int) $statement->fetchColumn() >= 5;
    }
}
