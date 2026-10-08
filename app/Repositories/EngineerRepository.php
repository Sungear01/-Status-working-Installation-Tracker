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
     * The engineer of each of $customerIds, as [customer_id => name]: the initials (employee.initial_name) of the
     * employee on the customer's Landy Home worksheet, unless someone picked a name in this system, which wins.
     * $dataCompany (see CustomerRepository::dataCompany()): the names picked in that company's table, which keeps
     * its own picks in company_installations; null = the shared picks in customer_engineers.
     */
    public function valuesFor(array $customerIds, ?int $dataCompany = null): array
    {
        if ($customerIds === []) {
            return [];
        }
        $customerIds = array_values(array_map('intval', $customerIds));
        $values = $this->worksheetEngineers($customerIds);
        $marks = implode(',', array_fill(0, count($customerIds), '?'));
        if ($dataCompany !== null) {
            $statement = $this->db->prepare("SELECT customer_id, engineer FROM company_installations WHERE company_id = ? AND engineer <> '' AND customer_id IN ({$marks})");
            $statement->execute(array_merge([$dataCompany], $customerIds));
        } elseif (!$this->hasTables()) {
            return $values;
        } else {
            $statement = $this->db->prepare("SELECT customer_id, engineer FROM customer_engineers WHERE customer_id IN ({$marks})");
            $statement->execute($customerIds);
        }
        foreach ($statement->fetchAll() as $row) {
            $values[(int) $row['customer_id']] = (string) $row['engineer'];
        }
        return $values;
    }

    /**
     * [worksheet id => employee initials] from Landy Home, joined worksheets.employee_id = employee.employee_id.
     * Customer ids above zero are worksheet ids (negative ones are local rows with no worksheet). Read-only; if the
     * Landy Home database cannot be read, the column just falls back to the names picked in this system.
     */
    private function worksheetEngineers(array $customerIds): array
    {
        $worksheetIds = array_values(array_filter($customerIds, static fn (int $id): bool => $id > 0));
        $database = (string) config('customer.landyhome_database', 'landyhome');
        if ($worksheetIds === [] || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $database)) {
            return [];
        }
        try {
            $statement = $this->db->prepare(sprintf(
                'SELECT w.worksheet_id, TRIM(e.initial_name) AS initial_name
                 FROM `%1$s`.`worksheets` w
                 JOIN `%1$s`.`employee` e ON e.employee_id = w.employee_id
                 WHERE w.worksheet_id IN (%2$s)',
                $database,
                implode(',', array_fill(0, count($worksheetIds), '?'))
            ));
            $statement->execute($worksheetIds);
        } catch (\PDOException $error) {
            error_log('[engineer] ' . $error->getMessage());
            return [];
        }
        $values = [];
        foreach ($statement->fetchAll() as $row) {
            if ((string) $row['initial_name'] !== '') {
                $values[(int) $row['worksheet_id']] = (string) $row['initial_name'];
            }
        }
        return $values;
    }

    public function valueFor(int $customerId, ?int $dataCompany = null): ?string
    {
        return $this->valuesFor([$customerId], $dataCompany)[$customerId] ?? null;
    }

    /** An empty value clears the cell rather than storing a blank row. $dataCompany: see valuesFor(). */
    public function saveValue(int $customerId, ?string $engineer, ?int $dataCompany = null): void
    {
        $engineer = $engineer === null ? '' : trim($engineer);
        if ($dataCompany !== null) {
            $this->db->prepare('INSERT INTO company_installations (company_id, customer_id, engineer) VALUES (:company_id, :customer_id, :engineer)
                                ON DUPLICATE KEY UPDATE engineer = VALUES(engineer)')
                ->execute(['company_id' => $dataCompany, 'customer_id' => $customerId, 'engineer' => $engineer === '' ? null : mb_substr($engineer, 0, self::MAX_LENGTH)]);
            return;
        }
        if (!$this->hasTables()) {
            return;
        }
        if ($engineer === '') {
            $this->db->prepare('DELETE FROM customer_engineers WHERE customer_id = :customer_id')->execute(['customer_id' => $customerId]);
            return;
        }
        $this->db->prepare('INSERT INTO customer_engineers (customer_id, engineer) VALUES (:customer_id, :engineer)
                            ON DUPLICATE KEY UPDATE engineer = VALUES(engineer)')
            ->execute(['customer_id' => $customerId, 'engineer' => mb_substr($engineer, 0, self::MAX_LENGTH)]);
    }
}
