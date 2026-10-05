<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CompanyGroupRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Every group with its member company ids (int[]) and a display label ("CAP+, NOVA"). */
    public function all(): array
    {
        $sql = "SELECT g.id, GROUP_CONCAT(c.id ORDER BY c.id) AS company_ids, GROUP_CONCAT(c.name ORDER BY c.id SEPARATOR ', ') AS label
                FROM company_groups g
                JOIN company_group_members m ON m.group_id = g.id
                JOIN companies c ON c.id = m.company_id
                GROUP BY g.id
                HAVING COUNT(*) > 1
                ORDER BY g.id ASC";
        $rows = $this->db->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['company_ids'] = array_map('intval', explode(',', (string) $row['company_ids']));
        }
        return $rows;
    }

    /** Reuse an existing group with exactly this set of companies, or create a new one. */
    public function findOrCreate(array $companyIds): int
    {
        $companyIds = array_values(array_unique(array_map('intval', $companyIds)));
        sort($companyIds);

        foreach ($this->all() as $group) {
            $existing = $group['company_ids'];
            sort($existing);
            if ($existing === $companyIds) {
                return (int) $group['id'];
            }
        }

        $this->db->prepare('INSERT INTO company_groups (created_at) VALUES (NOW())')->execute();
        $groupId = (int) $this->db->lastInsertId();
        $insert = $this->db->prepare('INSERT INTO company_group_members (group_id, company_id) VALUES (:group_id, :company_id)');
        foreach ($companyIds as $companyId) {
            $insert->execute(['group_id' => $groupId, 'company_id' => $companyId]);
        }
        return $groupId;
    }

    /** Replace the group's member companies with exactly this set. */
    public function updateMembers(int $id, array $companyIds): void
    {
        $companyIds = array_values(array_unique(array_map('intval', $companyIds)));
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM company_group_members WHERE group_id = :group_id')->execute(['group_id' => $id]);
            $insert = $this->db->prepare('INSERT INTO company_group_members (group_id, company_id) VALUES (:group_id, :company_id)');
            foreach ($companyIds as $companyId) {
                $insert->execute(['group_id' => $id, 'company_id' => $companyId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM company_group_members WHERE group_id = :group_id')->execute(['group_id' => $id]);
        $this->db->prepare('DELETE FROM company_groups WHERE id = :id')->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $group) {
            if ((int) $group['id'] === $id) {
                return $group;
            }
        }
        return null;
    }
}
