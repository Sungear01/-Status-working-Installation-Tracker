<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * The built-in "วิศวกร" column: the initials (employee.initial_name) of the employee on each customer's Landy Home
 * worksheet. It is read-only here (Auth::SOURCE_FIELDS), the same in every company's table.
 */
final class EngineerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * The engineer of each of $customerIds, as [customer_id => initials], joined worksheets.employee_id =
     * employee.employee_id. Customer ids above zero are worksheet ids (negative ones are local rows with no worksheet,
     * so they have none). Read-only; if the Landy Home database cannot be read, the column is just empty.
     */
    public function valuesFor(array $customerIds): array
    {
        $worksheetIds = array_values(array_filter(array_map('intval', $customerIds), static fn (int $id): bool => $id > 0));
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

    public function valueFor(int $customerId): ?string
    {
        return $this->valuesFor([$customerId])[$customerId] ?? null;
    }
}
