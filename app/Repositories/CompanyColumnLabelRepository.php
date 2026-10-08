<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Header names an admin gave the built-in columns of one company's table (e.g. "Zone" → "พื้นที่"). Custom columns
 * keep their name in company_custom_columns.label instead. A column without a row here shows its default name.
 */
final class CompanyColumnLabelRepository
{
    public const MAX_LENGTH = 150;

    private static bool $tableReady = false;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->ensureTable();
    }

    /** Created on first use as well, so the feature works before database/company_column_labels.sql has been run. */
    private function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS company_column_labels (
            company_id BIGINT UNSIGNED NOT NULL,
            column_key VARCHAR(64) NOT NULL,
            label VARCHAR(150) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (company_id, column_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        self::$tableReady = true;
    }

    /** [column key => header name] for one company. */
    public function forCompany(int $companyId): array
    {
        $statement = $this->db->prepare('SELECT column_key, label FROM company_column_labels WHERE company_id = :company_id');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Replace a company's header names with [column key => name]; a column left out goes back to its default. */
    public function replaceForCompany(int $companyId, array $labels): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM company_column_labels WHERE company_id = :company_id')->execute(['company_id' => $companyId]);
            $insert = $this->db->prepare('INSERT INTO company_column_labels (company_id, column_key, label) VALUES (:company_id, :column_key, :label)');
            foreach ($labels as $key => $label) {
                $insert->execute(['company_id' => $companyId, 'column_key' => (string) $key, 'label' => mb_substr((string) $label, 0, self::MAX_LENGTH)]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
