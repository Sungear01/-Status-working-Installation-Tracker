<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Where a column of a company's own table takes its data: a column with a row here shows (read-only) the same
 * column of another company's table, e.g. ร้าน B "วันที่ส่ง PO" = NOVA's. A column without a row is typed in the
 * company's own table (กรอกเอง). Only companies with their own data (company_installations) can link a column.
 */
final class CompanyColumnSourceRepository
{
    /** Built-in columns that can be linked, as they are named in company_installations. */
    public const LINKABLE_FIELDS = [
        'zone', 'machine_size', 'po_sent_date', 'engineer_appointment_1', 'installation_date_1', 'engineer_appointment_2',
        'installation_date_2', 'engineer_appointment_3', 'installation_date_3', 'status', 'construction_notes',
        'work_status', 'cap_notes',
    ];

    private static bool $tableReady = false;

    /** Per-request cache of forCompany(). */
    private static array $cache = [];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->ensureTable();
    }

    /** Created on first use, like company_column_labels, so no SQL file has to be run first. */
    private function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS company_column_sources (
            company_id BIGINT UNSIGNED NOT NULL,
            column_key VARCHAR(64) NOT NULL,
            source_company_id BIGINT UNSIGNED NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (company_id, column_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        self::$tableReady = true;
    }

    /** [column key => source company id] for one company; columns left out are typed in its own table. */
    public function forCompany(int $companyId): array
    {
        if (!isset(self::$cache[$companyId])) {
            $statement = $this->db->prepare('SELECT s.column_key, s.source_company_id FROM company_column_sources s
                JOIN companies c ON c.id = s.source_company_id WHERE s.company_id = :company_id');
            $statement->execute(['company_id' => $companyId]);
            $sources = [];
            foreach ($statement->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $sourceId) {
                if (in_array($key, self::LINKABLE_FIELDS, true) && (int) $sourceId !== $companyId) {
                    $sources[$key] = (int) $sourceId;
                }
            }
            self::$cache[$companyId] = $sources;
        }
        return self::$cache[$companyId];
    }

    /** Replace a company's links with [column key => source company id]; a column left out goes back to กรอกเอง. */
    public function replaceForCompany(int $companyId, array $sources): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM company_column_sources WHERE company_id = :company_id')->execute(['company_id' => $companyId]);
            $insert = $this->db->prepare('INSERT INTO company_column_sources (company_id, column_key, source_company_id) VALUES (:company_id, :column_key, :source_company_id)');
            foreach ($sources as $key => $sourceId) {
                $insert->execute(['company_id' => $companyId, 'column_key' => (string) $key, 'source_company_id' => (int) $sourceId]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        unset(self::$cache[$companyId]);
    }
}
