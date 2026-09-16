<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByUsername(string $username): ?array
    {
        $sql = 'SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = :username LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute(['username' => $username]);
        return $statement->fetch() ?: null;
    }

    public function permissions(int $userId, int $roleId): array
    {
        $sql = 'SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = :role_id AND NOT EXISTS (SELECT 1 FROM user_permissions up WHERE up.user_id = :user_id AND up.permission_id = p.id AND up.allow = 0) UNION SELECT p.slug FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = :user_id2 AND up.allow = 1';
        $statement = $this->db->prepare($sql);
        $statement->execute(['role_id' => $roleId, 'user_id' => $userId, 'user_id2' => $userId]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function touchLastLogin(int $id): void
    {
        $statement = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function setSessionToken(int $id, string $token): void
    {
        $statement = $this->db->prepare('UPDATE users SET session_token = :token WHERE id = :id');
        $statement->execute(['token' => $token, 'id' => $id]);
    }

    public function sessionTokenMatches(int $id, ?string $token): bool
    {
        $statement = $this->db->prepare('SELECT session_token FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $storedToken = $statement->fetchColumn();
        if ($storedToken === false) {
            return false;
        }
        if ($token === null || $token === '') {
            return $storedToken === null || $storedToken === '';
        }
        return is_string($storedToken) && hash_equals($storedToken, $token);
    }

    public function all(): array
    {
        return $this->db->query('SELECT u.id, u.username, u.name, u.email, u.is_active, u.last_login_at, u.created_at, u.company_id, c.name AS company_name, r.name AS role_name, r.slug AS role_slug, (SELECT COUNT(*) FROM login_logs ll WHERE ll.username_attempt = u.username AND ll.success = 0 AND ll.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS failed_attempts FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN companies c ON c.id = u.company_id ORDER BY u.created_at DESC')->fetchAll();
    }

    public function adminEmails(): array
    {
        $statement = $this->db->query("SELECT u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin' AND u.is_active = 1 AND u.email IS NOT NULL AND u.email <> ''");
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function primaryAdminEmail(): ?string
    {
        $statement = $this->db->query("SELECT u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin' AND u.is_active = 1 AND u.email IS NOT NULL AND u.email <> '' ORDER BY u.id ASC LIMIT 1");
        $email = $statement->fetchColumn();
        return $email === false ? null : (string) $email;
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function findWithRole(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username' . ($exceptId !== null ? ' AND id <> :except_id' : '');
        $statement = $this->db->prepare($sql);
        $params = ['username' => $username];
        if ($exceptId !== null) {
            $params['except_id'] = $exceptId;
        }
        $statement->execute($params);
        return (int) $statement->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $statement = $this->db->prepare('INSERT INTO users (role_id, company_id, username, name, email, password, must_change_password, is_active) VALUES (:role_id, :company_id, :username, :name, :email, :password, :must_change_password, :is_active)');
        $statement->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $statement = $this->db->prepare('UPDATE users SET username=:username, role_id=:role_id, company_id=:company_id, name=:name, email=:email, is_active=:is_active WHERE id=:id');
        $statement->execute($data + ['id' => $id]);
    }

    public function updateProfile(int $id, string $username, string $name, ?string $email): void
    {
        $statement = $this->db->prepare('UPDATE users SET username=:username, name=:name, email=:email WHERE id=:id');
        $statement->execute(['username' => $username, 'name' => $name, 'email' => $email, 'id' => $id]);
    }

    public function resetPassword(int $id, string $hash): void
    {
        $statement = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $statement->execute(['password' => $hash, 'id' => $id]);
    }

    public function clearLoginFailures(string $username): void
    {
        $statement = $this->db->prepare('DELETE FROM login_logs WHERE username_attempt = :username AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $statement->execute(['username' => $username]);
    }

    public function changePassword(int $id, string $hash): void
    {
        $statement = $this->db->prepare('UPDATE users SET password = :password, must_change_password = 0 WHERE id = :id');
        $statement->execute(['password' => $hash, 'id' => $id]);
    }

    public function toggle(int $id, bool $active): void
    {
        $statement = $this->db->prepare('UPDATE users SET is_active = :active WHERE id = :id');
        $statement->execute(['active' => (int) $active, 'id' => $id]);
    }
}
