<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PasswordResetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Issues a new link and kills any earlier unused one, so only the latest email works. */
    public function issue(int $userId, string $tokenHash, string $expiresAt, ?int $createdBy): void
    {
        $this->invalidateForUser($userId);
        $statement = $this->db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_by) VALUES (:user_id, :token_hash, :expires_at, :created_by)');
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);
    }

    public function invalidateForUser(int $userId): void
    {
        $statement = $this->db->prepare('DELETE FROM password_resets WHERE user_id = :user_id AND used_at IS NULL');
        $statement->execute(['user_id' => $userId]);
    }

    /** The matching reset row together with its user, only while it is unused, unexpired and the account is active. */
    public function findUsable(string $tokenHash): ?array
    {
        $sql = 'SELECT pr.id, pr.user_id, u.username, u.name, u.email
                FROM password_resets pr
                JOIN users u ON u.id = pr.user_id
                WHERE pr.token_hash = :token_hash
                  AND pr.used_at IS NULL
                  AND pr.expires_at > NOW()
                  AND u.is_active = 1
                LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute(['token_hash' => $tokenHash]);
        return $statement->fetch() ?: null;
    }

    public function markUsed(int $id): void
    {
        $statement = $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
        $statement->execute(['id' => $id]);
    }
}
