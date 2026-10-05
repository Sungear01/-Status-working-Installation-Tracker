<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CompanyRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** All companies with the ids of the customers assigned to each (as string[]). */
    public function all(): array
    {
        $sql = "SELECT c.id, c.name, c.start_year, c.is_default, c.table_layout, c.table_columns,
                       COALESCE(GROUP_CONCAT(cc.customer_id ORDER BY cc.customer_id), '') AS customer_ids
                FROM companies c
                LEFT JOIN customer_companies cc ON cc.company_id = c.id
                GROUP BY c.id, c.name, c.start_year, c.is_default, c.table_layout, c.table_columns
                ORDER BY c.id ASC";
        $rows = $this->db->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['customer_ids'] = $row['customer_ids'] === '' ? [] : explode(',', $row['customer_ids']);
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT id, name, start_year, is_default, table_layout, table_columns FROM companies WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $statement = $this->db->prepare('SELECT id, name, start_year, is_default, table_layout, table_columns FROM companies WHERE name = :name');
        $statement->execute(['name' => $name]);
        return $statement->fetch() ?: null;
    }

    public function delete(int $id): void
    {
        $statement = $this->db->prepare('DELETE FROM companies WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /** Whether the given customer (its customer_installation_view id) belongs to this company. */
    public function customerBelongs(int $companyId, string $customerId, bool $isDefault): bool
    {
        if ($isDefault) {
            $statement = $this->db->prepare(
                'SELECT EXISTS(SELECT 1 FROM customer_companies WHERE customer_id = :id AND company_id = :company_id)
                    OR NOT EXISTS(SELECT 1 FROM customer_companies WHERE customer_id = :id2)'
            );
            $statement->execute(['id' => $customerId, 'company_id' => $companyId, 'id2' => $customerId]);
            return (bool) $statement->fetchColumn();
        }
        $statement = $this->db->prepare('SELECT EXISTS(SELECT 1 FROM customer_companies WHERE customer_id = :id AND company_id = :company_id)');
        $statement->execute(['id' => $customerId, 'company_id' => $companyId]);
        return (bool) $statement->fetchColumn();
    }

    public function create(array $data): int
    {
        $statement = $this->db->prepare('INSERT INTO companies (name, start_year, created_by) VALUES (:name, :start_year, :created_by)');
        $statement->execute([
            'name' => $data['name'],
            'start_year' => $data['start_year'],
            'created_by' => $data['created_by'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->syncCustomers($id, $data['customer_ids']);
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $statement = $this->db->prepare('UPDATE companies SET name = :name, start_year = :start_year WHERE id = :id');
        $statement->execute([
            'name' => $data['name'],
            'start_year' => $data['start_year'],
            'id' => $id,
        ]);
        $this->syncCustomers($id, $data['customer_ids']);
    }

    /** Save which columns a compact-layout company table shows (column keys, in display order). */
    public function updateTableColumns(int $id, array $columns): void
    {
        $statement = $this->db->prepare('UPDATE companies SET table_columns = :columns WHERE id = :id');
        $statement->execute(['columns' => json_encode(array_values($columns)), 'id' => $id]);
    }

    private function syncCustomers(int $companyId, array $customerIds): void
    {
        $delete = $this->db->prepare('DELETE FROM customer_companies WHERE company_id = :company_id');
        $delete->execute(['company_id' => $companyId]);
        if (!$customerIds) {
            return;
        }
        $insert = $this->db->prepare('INSERT INTO customer_companies (customer_id, company_id) VALUES (:customer_id, :company_id)');
        foreach (array_unique($customerIds) as $customerId) {
            $insert->execute(['customer_id' => $customerId, 'company_id' => $companyId]);
        }
    }
}
