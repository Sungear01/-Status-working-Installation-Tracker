<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

final class ActivityLogger
{
    public function log(string $action, ?string $entityType = null, string|int|null $entityId = null, ?string $description = null, ?array $old = null, ?array $new = null): void
    {
        $sql = 'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent) VALUES (:user_id, :action, :entity_type, :entity_id, :description, :old_values, :new_values, :ip_address, :user_agent)';
        $impersonator = Auth::impersonator();
        if ($impersonator !== null) {
            $description = trim(($description ?? '') . ' [ทำโดยผู้สร้างระบบ: ' . $impersonator['username'] . ']');
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    public function loginAttempt(string $username, ?int $userId, bool $success): void
    {
        $statement = Database::connection()->prepare('INSERT INTO login_logs (user_id, username_attempt, success, ip_address, user_agent) VALUES (:user_id, :username, :success, :ip_address, :user_agent)');
        $statement->execute([
            'user_id' => $userId,
            'username' => $username,
            'success' => (int) $success,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }
}

