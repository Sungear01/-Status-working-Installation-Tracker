<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class CustomerRepository
{
    private PDO $db;
    private string $table;
    private array $columns;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->table = $this->identifier((string) config('customer.table'));
        $this->columns = config('customer.columns');
        foreach ($this->columns as $column) {
            $this->identifier((string) $column);
        }
    }

    private function identifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException('ชื่อตารางหรือคอลัมน์ใน config/customer.php ไม่ถูกต้อง');
        }
        return $value;
    }

    private function col(string $key): string
    {
        return '`' . $this->columns[$key] . '`';
    }

    private function selectColumns(): string
    {
        $select = [];
        foreach ($this->columns as $alias => $column) {
            $select[] = sprintf('`%s` AS `%s`', $column, $alias);
        }
        $select[] = sprintf('CASE WHEN %s IS NULL OR %s IS NULL THEN NULL ELSE DATEDIFF(%s, %s) END AS days_early_late', $this->col('installation_date_3'), $this->col('due_60_date'), $this->col('due_60_date'), $this->col('installation_date_3'));
        return implode(', ', $select);
    }

    public function paginate(array $filters): array
    {
        [$where, $params] = $this->where($filters);
        $allowedSorts = array_keys($this->columns);
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : null;
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $requestedPerPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($requestedPerPage, [10, 25, 50, 100], true) ? $requestedPerPage : 25;
        $count = $this->db->prepare("SELECT COUNT(*) FROM `{$this->table}` {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $orderBy = $sort !== null
            ? $this->col($sort) . ' ' . $direction
            : sprintf('CASE WHEN %s < 0 THEN 0 ELSE 1 END ASC, CASE WHEN %s < 0 THEN %s END ASC, %s DESC', $this->col('id'), $this->col('id'), $this->col('id'), $this->col('id'));
        $sql = sprintf('SELECT %s FROM `%s` %s ORDER BY %s LIMIT :limit OFFSET :offset', $this->selectColumns(), $this->table, $where, $orderBy);
        $statement = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function filtered(array $filters): array
    {
        [$where, $params] = $this->where($filters);
        $statement = $this->db->prepare(sprintf('SELECT %s FROM `%s` %s ORDER BY %s ASC', $this->selectColumns(), $this->table, $where, $this->col('name')));
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function where(array $filters): array
    {
        $clauses = [];
        $params = [];
        if (($filters['search'] ?? '') !== '') {
            $clauses[] = sprintf('(%s LIKE :search_name OR %s LIKE :search_zone OR %s LIKE :search_status)', $this->col('name'), $this->col('zone'), $this->col('status'));
            $search = '%' . mb_substr(trim((string) $filters['search']), 0, 100) . '%';
            $params['search_name'] = $search;
            $params['search_zone'] = $search;
            $params['search_status'] = $search;
        }
        foreach (['zone', 'machine_size', 'status'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $clauses[] = $this->col($field) . " = :{$field}";
                $params[$field] = mb_substr((string) $filters[$field], 0, 100);
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['date_from'] ?? ''))) {
            $clauses[] = $this->col('po_sent_date') . ' >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['date_to'] ?? ''))) {
            $clauses[] = $this->col('po_sent_date') . ' <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['company_ids']) && is_array($filters['company_ids'])) {
            // A combined "group" tab (e.g. "CAP+, NOVA") filters by several companies at once.
            $ids = array_values(array_unique(array_map('intval', $filters['company_ids'])));
            $placeholders = [];
            foreach ($ids as $index => $companyId) {
                $key = 'company_ids_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $companyId;
            }
            $inList = implode(',', $placeholders);
            // The default company (CAP+) also picks up every customer nobody has explicitly assigned elsewhere.
            $clauses[] = !empty($filters['company_is_default'])
                ? sprintf('(%1$s IN (SELECT customer_id FROM `customer_companies` WHERE company_id IN (%2$s)) OR %1$s NOT IN (SELECT customer_id FROM `customer_companies`))', $this->col('id'), $inList)
                : $this->col('id') . ' IN (SELECT customer_id FROM `customer_companies` WHERE company_id IN (' . $inList . '))';
        } elseif (($filters['company_id'] ?? '') !== '' && ctype_digit((string) $filters['company_id'])) {
            // The default company (CAP+) also picks up every customer nobody has explicitly assigned elsewhere.
            $clauses[] = !empty($filters['company_is_default'])
                ? sprintf('(%1$s IN (SELECT customer_id FROM `customer_companies` WHERE company_id = :company_id) OR %1$s NOT IN (SELECT customer_id FROM `customer_companies`))', $this->col('id'))
                : $this->col('id') . ' IN (SELECT customer_id FROM `customer_companies` WHERE company_id = :company_id)';
            $params['company_id'] = (int) $filters['company_id'];
        }
        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /** Lightweight id/name/contract_sign_date list for the company-assignment picker. */
    public function listForSelection(): array
    {
        $sql = sprintf('SELECT %s AS id, %s AS name, %s AS contract_sign_date FROM `%s` ORDER BY %s ASC', $this->col('id'), $this->col('name'), $this->col('contract_sign_date'), $this->table, $this->col('name'));
        return $this->db->query($sql)->fetchAll();
    }

    public function facets(): array
    {
        $result = [];
        foreach (['zone', 'machine_size', 'status'] as $field) {
            $result[$field] = $this->db->query(sprintf('SELECT DISTINCT %s AS value FROM `%s` WHERE %s IS NOT NULL AND %s <> \'\' ORDER BY %s', $this->col($field), $this->table, $this->col($field), $this->col($field), $this->col($field)))->fetchAll(PDO::FETCH_COLUMN);
        }
        return $result;
    }

    public function find(string $id): ?array
    {
        $statement = $this->db->prepare(sprintf('SELECT %s FROM `%s` WHERE %s = :id LIMIT 1', $this->selectColumns(), $this->table, $this->col('id')));
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function update(string $id, array $data): void
    {
        $this->assertWritesEnabled();
        $trackingTable = $this->identifier((string) config('customer.tracking_table', 'customer_installations'));
        if ((int) $id < 0) {
            $localId = abs((int) $id);
            $fields = ['customer_name', 'zone', 'machine_size', 'po_sent_date', 'po_due_date', 'engineer_appointment_1', 'installation_date_1', 'engineer_appointment_2', 'installation_date_2', 'engineer_appointment_3', 'installation_date_3', 'due_60_date', 'status', 'notes', 'construction_notes', 'work_status', 'cap_notes', 'updated_by'];
            $assignments = implode(', ', array_map(static fn (string $field): string => "`{$field}` = :{$field}", $fields));
            $statement = $this->db->prepare("UPDATE `{$trackingTable}` SET {$assignments}, updated_at = NOW() WHERE id = :local_id AND worksheet_id IS NULL");
            $params = $data + ['customer_name' => $data['name'] ?? null, 'local_id' => $localId];
            unset($params['name']);
            $statement->execute($params);
            return;
        }
        $sql = sprintf(
            'INSERT INTO `%s` (`worksheet_id`, `zone`, `machine_size`, `po_sent_date`, `po_due_date`, `engineer_appointment_1`, `installation_date_1`, `engineer_appointment_2`, `installation_date_2`, `engineer_appointment_3`, `installation_date_3`, `due_60_date`, `status`, `notes`, `construction_notes`, `work_status`, `cap_notes`, `updated_by`) VALUES (:id, :zone, :machine_size, :po_sent_date, :po_due_date, :engineer_appointment_1, :installation_date_1, :engineer_appointment_2, :installation_date_2, :engineer_appointment_3, :installation_date_3, :due_60_date, :status, :notes, :construction_notes, :work_status, :cap_notes, :updated_by) ON DUPLICATE KEY UPDATE `zone`=VALUES(`zone`), `machine_size`=VALUES(`machine_size`), `po_sent_date`=VALUES(`po_sent_date`), `po_due_date`=VALUES(`po_due_date`), `engineer_appointment_1`=VALUES(`engineer_appointment_1`), `installation_date_1`=VALUES(`installation_date_1`), `engineer_appointment_2`=VALUES(`engineer_appointment_2`), `installation_date_2`=VALUES(`installation_date_2`), `engineer_appointment_3`=VALUES(`engineer_appointment_3`), `installation_date_3`=VALUES(`installation_date_3`), `due_60_date`=VALUES(`due_60_date`), `status`=VALUES(`status`), `notes`=VALUES(`notes`), `construction_notes`=VALUES(`construction_notes`), `work_status`=VALUES(`work_status`), `cap_notes`=VALUES(`cap_notes`), `updated_by`=VALUES(`updated_by`), `updated_at`=NOW()',
            $trackingTable
        );
        $statement = $this->db->prepare($sql);
        // A worksheet customer's name comes from landyhome, so 'name' has no placeholder here; native prepares
        // reject any extra parameter, so pass only the ones the statement uses.
        $params = $data + ['id' => $id];
        unset($params['name']);
        $statement->execute($params);
    }

    public function create(array $data): string
    {
        $this->assertWritesEnabled();
        $trackingTable = $this->identifier((string) config('customer.tracking_table', 'customer_installations'));
        $fields = ['customer_name', 'zone', 'machine_size', 'po_sent_date', 'po_due_date', 'engineer_appointment_1', 'installation_date_1', 'engineer_appointment_2', 'installation_date_2', 'engineer_appointment_3', 'installation_date_3', 'due_60_date', 'status', 'notes', 'construction_notes', 'work_status', 'cap_notes', 'updated_by'];
        $columnSql = implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields));
        $valueSql = implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields));
        $statement = $this->db->prepare("INSERT INTO `{$trackingTable}` ({$columnSql}) VALUES ({$valueSql})");
        $params = $data;
        $params['customer_name'] = $data['name'] ?? '';
        unset($params['name']);
        $statement->execute($params);
        return (string) (-1 * (int) $this->db->lastInsertId());

        /* Kept below for reference if the master source is ever made writable. */
        if (config('customer.master_managed_externally', false)) {
            throw new InvalidArgumentException('ข้อมูลลูกค้าถูกจัดการจากฐานข้อมูลต้นทาง ไม่สามารถเพิ่มจากระบบนี้ได้');
        }
        $fields = array_keys($data);
        $columnSql = implode(', ', array_map(fn (string $field): string => $this->col($field), $fields));
        $valueSql = implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields));
        $statement = $this->db->prepare(sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $this->table, $columnSql, $valueSql));
        $statement->execute($data);
        return (string) $this->db->lastInsertId();
    }

    public function delete(string $id): void
    {
        $this->assertWritesEnabled();
        if ((int) $id < 0) {
            $trackingTable = $this->identifier((string) config('customer.tracking_table', 'customer_installations'));
            $statement = $this->db->prepare("DELETE FROM `{$trackingTable}` WHERE id = :id AND worksheet_id IS NULL");
            $statement->execute(['id' => abs((int) $id)]);
            return;
        }
        if (config('customer.master_managed_externally', false)) {
            throw new InvalidArgumentException('ข้อมูลลูกค้าถูกจัดการจากฐานข้อมูลต้นทาง ไม่สามารถลบจากระบบนี้ได้');
        }
        $statement = $this->db->prepare(sprintf('DELETE FROM `%s` WHERE %s = :id', $this->table, $this->col('id')));
        $statement->execute(['id' => $id]);
    }

    private function assertWritesEnabled(): void
    {
        if (!config('customer.writes_enabled', false)) {
            throw new InvalidArgumentException('ระบบถูกตั้งเป็นอ่านอย่างเดียว กรุณาตรวจ mapping และเปิด CUSTOMER_WRITES_ENABLED ก่อน');
        }
    }

    public function dashboard(): array
    {
        $completedValues = config('customer.status.completed', []);
        $marks = implode(',', array_fill(0, count($completedValues), '?'));
        $sql = sprintf('SELECT COUNT(*) total, SUM(UPPER(%s) = \'YES\') yes_count, SUM(UPPER(%s) NOT IN (%s)) pending_count, SUM(%s < CURDATE() AND UPPER(%s) NOT IN (%s)) overdue_count, SUM(%s BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY) AND UPPER(%s) NOT IN (%s)) near_due_count, SUM(UPPER(%s) IN (%s)) completed_count FROM `%s`', $this->col('status'), $this->col('status'), $marks, $this->col('due_60_date'), $this->col('status'), $marks, $this->col('due_60_date'), (int) config('customer.near_due_days', 7), $this->col('status'), $marks, $this->col('status'), $marks, $this->table);
        $params = array_merge($completedValues, $completedValues, $completedValues, $completedValues);
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: [];
    }

    public function chartData(): array
    {
        return [
            'zones' => $this->groupBy('zone'),
            'statuses' => $this->groupBy('status'),
            'sizes' => $this->groupBy('machine_size'),
            'months' => $this->db->query(sprintf("SELECT DATE_FORMAT(%s, '%%Y-%%m') label, COUNT(*) total FROM `%s` WHERE %s IS NOT NULL GROUP BY label ORDER BY label DESC LIMIT 12", $this->col('po_sent_date'), $this->table, $this->col('po_sent_date')))->fetchAll(),
        ];
    }

    private function groupBy(string $field): array
    {
        return $this->db->query(sprintf("SELECT COALESCE(NULLIF(%s, ''), 'ไม่ระบุ') label, COUNT(*) total FROM `%s` GROUP BY %s ORDER BY total DESC", $this->col($field), $this->table, $this->col($field)))->fetchAll();
    }
}
