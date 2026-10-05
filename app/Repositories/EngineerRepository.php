<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** The dropdown choices of the built-in "วิศวกร" column, and the name picked for each customer. */
final class EngineerRepository
{
    /** Longest name the column accepts; matches engineers.name / customer_engineers.engineer. */
    public const MAX_LENGTH = 150;

    /** Whether the database has run database/engineers.sql yet; see hasTables(). */
    private static ?bool $hasTables = null;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * The engineer tables arrived after the first release, so a database that has not run the migration yet keeps
     * working: the column reads as empty everywhere and nothing writes to it, instead of erroring on every page.
     */
    private function hasTables(): bool
    {
        if (self::$hasTables === null) {
            $found = $this->db->query("SHOW TABLES LIKE 'engineers'")->fetch()
                && $this->db->query("SHOW TABLES LIKE 'customer_engineers'")->fetch();
            self::$hasTables = (bool) $found;
        }
        return self::$hasTables;
    }

    /** The dropdown choices, in display order. */
    public function names(): array
    {
        if (!$this->hasTables()) {
            return [];
        }
        return $this->db->query('SELECT name FROM engineers ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Replace the whole list with $names, keeping the given order. A name already in the list keeps its row, so
     * reordering never disturbs the value a customer already holds.
     */
    public function replaceNames(array $names): void
    {
        if (!$this->hasTables()) {
            return;
        }
        $clean = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '' && !in_array($name, $clean, true)) {
                $clean[] = mb_substr($name, 0, self::MAX_LENGTH);
            }
        }
        $this->db->beginTransaction();
        try {
            if ($clean === []) {
                $this->db->exec('DELETE FROM engineers');
            } else {
                $delete = $this->db->prepare('DELETE FROM engineers WHERE name NOT IN (' . implode(',', array_fill(0, count($clean), '?')) . ')');
                $delete->execute($clean);
                $upsert = $this->db->prepare('INSERT INTO engineers (name, sort_order) VALUES (:name, :sort_order) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)');
                foreach ($clean as $index => $name) {
                    $upsert->execute(['name' => $name, 'sort_order' => $index + 1]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    /** The engineer of each of $customerIds, as [customer_id => name]. */
    public function valuesFor(array $customerIds): array
    {
        if ($customerIds === [] || !$this->hasTables()) {
            return [];
        }
        $customerIds = array_values(array_map('intval', $customerIds));
        $statement = $this->db->prepare('SELECT customer_id, engineer FROM customer_engineers WHERE customer_id IN ('
            . implode(',', array_fill(0, count($customerIds), '?')) . ')');
        $statement->execute($customerIds);
        $values = [];
        foreach ($statement->fetchAll() as $row) {
            $values[(int) $row['customer_id']] = (string) $row['engineer'];
        }
        return $values;
    }

    public function valueFor(int $customerId): ?string
    {
        return $this->valuesFor([$customerId])[$customerId] ?? null;
    }

    /** An empty value clears the cell rather than storing a blank row. */
    public function saveValue(int $customerId, ?string $engineer): void
    {
        if (!$this->hasTables()) {
            return;
        }
        $engineer = $engineer === null ? '' : trim($engineer);
        if ($engineer === '') {
            $this->db->prepare('DELETE FROM customer_engineers WHERE customer_id = :customer_id')->execute(['customer_id' => $customerId]);
            return;
        }
        $this->db->prepare('INSERT INTO customer_engineers (customer_id, engineer) VALUES (:customer_id, :engineer)
                            ON DUPLICATE KEY UPDATE engineer = VALUES(engineer)')
            ->execute(['customer_id' => $customerId, 'engineer' => mb_substr($engineer, 0, self::MAX_LENGTH)]);
    }
}
