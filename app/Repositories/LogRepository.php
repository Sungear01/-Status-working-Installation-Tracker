<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class LogRepository
{
    public function activities(int $limit = 200): array
    {
        $statement = Database::connection()->prepare('SELECT a.*, u.username, u.name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function logins(int $limit = 200): array
    {
        $statement = Database::connection()->prepare('SELECT l.*, u.name FROM login_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function pendingPasswordResetRequests(int $limit = 10): array
    {
        $sql = "SELECT a.id, a.created_at, a.entity_id, u.username, u.name
                FROM activity_logs a
                JOIN users u ON u.id = a.entity_id
                WHERE a.action = 'password_reset_request'
                  AND NOT EXISTS (
                    SELECT 1 FROM activity_logs completed
                    WHERE completed.entity_id = a.entity_id
                      AND completed.action = 'reset_password'
                      AND completed.created_at >= a.created_at
                  )
                ORDER BY a.created_at DESC
                LIMIT :limit";
        $statement = Database::connection()->prepare($sql);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function customerHistory(string $id): array
    {
        $statement = Database::connection()->prepare("SELECT a.*, u.name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity_type='customer' AND a.entity_id=:id ORDER BY a.created_at DESC LIMIT 100");
        $statement->execute(['id' => $id]);
        return $statement->fetchAll();
    }
}
