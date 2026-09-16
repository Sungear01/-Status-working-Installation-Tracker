<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RoleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM roles ORDER BY id')->fetchAll();
    }

    public function permissions(): array
    {
        return $this->db->query('SELECT * FROM permissions ORDER BY id')->fetchAll();
    }

    public function permissionIds(int $roleId): array
    {
        $statement = $this->db->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id');
        $statement->execute(['role_id' => $roleId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $delete->execute(['role_id' => $roleId]);
            $insert = $this->db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
            foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
                $insert->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}

