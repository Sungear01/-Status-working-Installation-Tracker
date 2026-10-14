<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Per-user table access set by an admin: which company tables a user sees and, in each, which columns they may edit.
 *
 * A user with no rows here keeps the old behaviour (their role and single company decide). Once an admin saves
 * access for a user, a marker row (company_id = 0) is written, so "sees no table at all" is told apart from
 * "not configured". Columns not listed in a table's editable_fields are read-only for that user.
 */
final class UserTableAccessRepository
{
    /**
     * Built-in columns a person can type into. Calculated columns (PO +61 days, ช้า/เร็ว) and those read from
     * Landy Home (Zone, วิศวกร; see Auth::SOURCE_FIELDS) are never editable, so they are not offered.
     */
    public const EDITABLE_FIELDS = [
        'name' => 'รายชื่อลูกค้า',
        'machine_size' => 'ขนาดเครื่อง',
        'po_sent_date' => 'วันที่ส่ง PO',
        'engineer_appointment_1' => 'วิศวกรนัดครั้งที่ 1',
        'installation_date_1' => 'ติดตั้งครั้งที่ 1',
        'engineer_appointment_2' => 'วิศวกรนัดครั้งที่ 2',
        'installation_date_2' => 'ติดตั้งครั้งที่ 2',
        'engineer_appointment_3' => 'วิศวกรนัดครั้งที่ 3',
        'installation_date_3' => 'ติดตั้งครั้งที่ 3',
        'due_60_date' => 'ครบกำหนด 60 วัน ครั้งที่ 2',
        'status' => 'Status',
        'work_status' => 'สถานะงาน',
        'construction_notes' => 'หมายเหตุทางฝ่ายก่อสร้าง',
        'cap_notes' => 'หมายเหตุทาง CAP+',
    ];

    private const MARKER_COMPANY_ID = 0;

    private static bool $tableReady = false;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->ensureTable();
    }

    /** Created on first use as well, so the feature works before database/user_table_access.sql has been run. */
    private function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS user_table_access (
            user_id BIGINT UNSIGNED NOT NULL,
            company_id BIGINT UNSIGNED NOT NULL,
            editable_fields TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        self::$tableReady = true;
    }

    /** [company id => editable field keys] for one user, or null when an admin has not configured them. */
    public function forUser(int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT user_id, company_id, editable_fields FROM user_table_access WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return $this->group($statement->fetchAll())[$userId] ?? null;
    }

    /** forUser() for every configured user, keyed by user id. */
    public function all(): array
    {
        return $this->group($this->db->query('SELECT user_id, company_id, editable_fields FROM user_table_access')->fetchAll());
    }

    /** Replace a user's access with [company id => editable field keys]; an empty array means "sees no table". */
    public function replaceForUser(int $userId, array $access): void
    {
        $this->db->beginTransaction();
        try {
            $this->clearForUser($userId);
            $insert = $this->db->prepare('INSERT INTO user_table_access (user_id, company_id, editable_fields) VALUES (:user_id, :company_id, :fields)');
            $insert->execute(['user_id' => $userId, 'company_id' => self::MARKER_COMPANY_ID, 'fields' => null]);
            foreach ($access as $companyId => $fields) {
                $insert->execute(['user_id' => $userId, 'company_id' => (int) $companyId, 'fields' => json_encode(array_values($fields), JSON_UNESCAPED_UNICODE)]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** Back to the default (role / single company) behaviour. */
    public function clearForUser(int $userId): void
    {
        $this->db->prepare('DELETE FROM user_table_access WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    }

    /**
     * The columns an admin can make editable on one company's table, as [key => label]: a compact table offers only
     * the columns it shows, a full table every built-in column, and both their non-formula custom columns.
     */
    public static function editableColumnsFor(array $company, array $customColumns): array
    {
        $columns = self::EDITABLE_FIELDS;
        foreach ($customColumns as $column) {
            if (CustomColumnRepository::formulaText($column, $customColumns) === '') {
                $columns['custom_' . (int) $column['id']] = (string) $column['label'];
            }
        }
        if (($company['table_layout'] ?? 'full') === 'compact') {
            $visible = json_decode((string) ($company['table_columns'] ?? ''), true);
            if (!is_array($visible) || $visible === []) {
                $visible = ['name', 'po_sent_date', 'po_due_date', 'engineer'];
            }
            $columns = array_intersect_key($columns, array_flip(array_merge(['name'], $visible)));
        }
        return $columns;
    }

    private function group(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $grouped[$userId] ??= [];
            if ((int) $row['company_id'] === self::MARKER_COMPANY_ID) {
                continue;
            }
            $fields = json_decode((string) ($row['editable_fields'] ?? ''), true);
            $grouped[$userId][(int) $row['company_id']] = is_array($fields) ? array_values(array_filter($fields, 'is_string')) : [];
        }
        return $grouped;
    }
}
