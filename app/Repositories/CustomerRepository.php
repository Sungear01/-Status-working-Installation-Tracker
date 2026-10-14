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

    /**
     * $nameExpr replaces the plain name column (see from()); null = the name from the view. $withAssignment adds
     * assignment_id, the customer_companies row a single-company tab row stands for (NULL for an unassigned customer).
     */
    private function selectColumns(?string $nameExpr = null, bool $withAssignment = false): string
    {
        $select = [];
        foreach ($this->columns as $alias => $column) {
            $select[] = $alias === 'name' && $nameExpr !== null
                ? sprintf('%s AS `name`', $nameExpr)
                : sprintf('`%s` AS `%s`', $column, $alias);
        }
        if ($withAssignment) {
            $select[] = 'cc_dup.cc_dup_row AS assignment_id';
        }
        $select[] = sprintf('CASE WHEN %s IS NULL OR %s IS NULL THEN NULL ELSE DATEDIFF(%s, %s) END AS days_early_late', $this->col('installation_date_3'), $this->col('due_60_date'), $this->col('due_60_date'), $this->col('installation_date_3'));
        return implode(', ', $select);
    }

    /**
     * The FROM clause. On a single-company tab, a customer assigned to that company more than once (the same
     * customer_id in several customer_companies rows, e.g. one Landy Home worksheet for 2 machines) is listed once
     * per assignment, so the table matches the company's list. The joined table only exposes a column named
     * cc_dup_* columns, so the view's own column names stay unambiguous. Group and "all" tabs list each customer once.
     *
     * Each of those rows may carry its own name in customer_companies.customer_other (e.g. "… (อาคาร B)"): when it is
     * filled, that row shows it instead of the name from Landy Home / the view. Returns [from, params, name expression
     * or null, whether the join is there]. Both sides are converted to one collation, since Landy Home may use another.
     * A company other than the shared-data one reads its own tracking data (see source()).
     */
    private function from(array $filters): array
    {
        $companyId = (string) ($filters['company_id'] ?? '');
        if (!empty($filters['company_ids']) || $companyId === '' || !ctype_digit($companyId)) {
            return ["`{$this->table}`", [], null, false];
        }
        [$source, $sourceParams] = $this->source($this->dataCompany((int) $companyId));
        $hasOtherName = self::hasCustomerOther($this->db);
        $from = sprintf(
            "%s LEFT JOIN (SELECT id AS cc_dup_row, customer_id AS cc_dup_customer%s FROM `customer_companies` WHERE company_id = :dup_company_id) AS cc_dup ON cc_dup.cc_dup_customer = %s",
            $source,
            $hasOtherName ? ', customer_other AS cc_dup_name' : '',
            $this->col('id')
        );
        $nameExpr = $hasOtherName
            ? sprintf("COALESCE(NULLIF(TRIM(CONVERT(cc_dup.cc_dup_name USING utf8mb4) COLLATE utf8mb4_unicode_ci), ''), CONVERT(%s USING utf8mb4) COLLATE utf8mb4_unicode_ci)", $this->col('name'))
            : null;
        return [$from, ['dup_company_id' => (int) $companyId] + $sourceParams, $nameExpr, true];
    }

    /** The tracking fields a company other than the shared-data one keeps for itself in company_installations. */
    private const COMPANY_FIELDS = [
        'zone', 'machine_size', 'po_sent_date', 'engineer_appointment_1', 'installation_date_1', 'engineer_appointment_2',
        'installation_date_2', 'engineer_appointment_3', 'installation_date_3', 'status', 'notes', 'construction_notes',
        'work_status', 'cap_notes', 'updated_by',
    ];

    private static ?bool $hasCompanyData = null;
    private static ?bool $hasCompanyDue60 = null;
    private static ?int $sharedCompanyId = null;

    /**
     * The company whose own tracking data a request reads and writes, or null for the shared data in
     * customer_installations. The shared-data company (CAP+, see sharedCompanyId()) uses the shared data; every other
     * company has its own rows in company_installations, so editing one company's table never changes another's.
     * Until database/company_installations.sql has run, every company keeps using the shared data.
     */
    public function dataCompany(int $companyId): ?int
    {
        return $this->hasCompanyData() && $companyId > 0 && $companyId !== $this->sharedCompanyId() ? $companyId : null;
    }

    /**
     * The company whose table shows the shared data in customer_installations: the one named in
     * config customer.shared_data_company (ids differ per server), else the default company (companies.is_default).
     */
    public function sharedCompanyId(): int
    {
        if (self::$sharedCompanyId === null) {
            $statement = $this->db->prepare('SELECT id FROM companies ORDER BY name = :name DESC, is_default DESC, id ASC LIMIT 1');
            $statement->execute(['name' => (string) config('customer.shared_data_company', 'CAP+')]);
            self::$sharedCompanyId = (int) $statement->fetchColumn();
        }
        return self::$sharedCompanyId;
    }

    /**
     * Whether company_installations keeps a typed ครบกำหนด 60 วัน ครั้งที่ 2 date (database/due_60_date_override.sql).
     * Without it, a company's date stays PO +60 days.
     */
    private function hasCompanyDue60(): bool
    {
        if (self::$hasCompanyDue60 === null) {
            self::$hasCompanyDue60 = (bool) $this->db->query("SHOW COLUMNS FROM `company_installations` LIKE 'due_60_date'")->fetch();
        }
        return self::$hasCompanyDue60;
    }

    private function hasCompanyData(): bool
    {
        if (self::$hasCompanyData === null) {
            self::$hasCompanyData = (bool) $this->db->query("SHOW TABLES LIKE 'company_installations'")->fetch();
        }
        return self::$hasCompanyData;
    }

    /**
     * The rows to read from, as [SQL, params]: the view itself, or for $dataCompany the view with that company's own
     * tracking fields in place of the shared ones. The derived table keeps the view's name and column names, so every
     * query, filter and sort works on it unchanged. Its zone falls back to the shared one until the company sets its
     * own, and its PO deadlines are calculated from its own PO date like the view does (+61 and +60 days).
     *
     * A column linked to another company's table (CompanyColumnSourceRepository, "ดึงจาก") is read from that table
     * instead: the view for the shared-data company, else that company's company_installations rows. The PO deadlines
     * follow วันที่ส่ง PO, so they come from wherever it comes from.
     */
    private function source(?int $dataCompany): array
    {
        if ($dataCompany === null) {
            return ["`{$this->table}`", []];
        }
        $links = (new CompanyColumnSourceRepository())->forCompany($dataCompany);
        $joins = '';
        $params = ['data_company_id' => $dataCompany];
        $aliases = [];  // source company id => alias of its company_installations join; null = the shared data (view)
        foreach (array_unique($links) as $sourceId) {
            if ($this->dataCompany($sourceId) === null) {
                $aliases[$sourceId] = null;
                continue;
            }
            $alias = 'cs' . $sourceId;
            $aliases[$sourceId] = $alias;
            $joins .= sprintf(' LEFT JOIN `company_installations` %1$s ON %1$s.customer_id = v.`%2$s` AND %1$s.company_id = :%1$s', $alias, $this->columns['id']);
            $params[$alias] = $sourceId;
        }
        // The raw value of a company_installations field: own row, linked company's row, or the shared view's column.
        $field = function (string $key) use ($links, $aliases): string {
            if (!isset($links[$key])) {
                return "cx.`{$key}`";
            }
            $alias = $aliases[$links[$key]];
            return $alias === null ? 'v.`' . $this->columns[$key] . '`' : "{$alias}.`{$key}`";
        };
        $poFromShared = isset($links['po_sent_date']) && $aliases[$links['po_sent_date']] === null;
        $poRow = isset($links['po_sent_date']) ? (string) $aliases[$links['po_sent_date']] : 'cx';
        $po = $field('po_sent_date');
        $select = [];
        foreach ($this->columns as $key => $column) {
            $select[] = match (true) {
                $key === 'zone' => sprintf("COALESCE(NULLIF(%s, ''), v.`%s`) AS `%s`", $field('zone'), $column, $column),
                $key === 'status' => sprintf("COALESCE(NULLIF(%s, ''), 'STATUS') AS `%s`", $field('status'), $column),
                $key === 'po_due_date' => $poFromShared ? "v.`{$column}` AS `{$column}`" : "{$po} + INTERVAL 61 DAY AS `{$column}`",
                $key === 'due_60_date' => match (true) {
                    $poFromShared => "v.`{$column}` AS `{$column}`",
                    $this->hasCompanyDue60() => "COALESCE({$poRow}.due_60_date, {$po} + INTERVAL 60 DAY) AS `{$column}`",
                    default => "{$po} + INTERVAL 60 DAY AS `{$column}`",
                },
                $key === 'updated_at' => "cx.`{$key}` AS `{$column}`",
                in_array($key, self::COMPANY_FIELDS, true) => $field($key) . " AS `{$column}`",
                default => "v.`{$column}` AS `{$column}`",
            };
        }
        return [
            sprintf(
                '(SELECT %s FROM `%s` v LEFT JOIN `company_installations` cx ON cx.customer_id = v.`%s` AND cx.company_id = :data_company_id%s) AS `%s`',
                implode(', ', $select),
                $this->table,
                $this->columns['id'],
                $joins,
                $this->table
            ),
            $params,
        ];
    }

    /**
     * One customer_companies row of $customerId, with its own name (customer_other, '' when the column is missing),
     * or null when the row does not exist or belongs to another customer.
     */
    public function findAssignment(int $assignmentId, int $customerId): ?array
    {
        $statement = $this->db->prepare(sprintf(
            'SELECT id, company_id, customer_id, %s AS customer_other FROM `customer_companies` WHERE id = :id AND customer_id = :customer_id',
            self::hasCustomerOther($this->db) ? 'customer_other' : "''"
        ));
        $statement->execute(['id' => $assignmentId, 'customer_id' => $customerId]);
        return $statement->fetch() ?: null;
    }

    /** Whether a customer_companies row can carry its own name (the customer_other column exists). */
    public function supportsAssignmentNames(): bool
    {
        return self::hasCustomerOther($this->db);
    }

    /** Set the name shown for one customer_companies row; null = show the customer's own name again. */
    public function saveAssignmentName(int $assignmentId, ?string $name): void
    {
        if (!self::hasCustomerOther($this->db)) {
            return;
        }
        $this->db->prepare('UPDATE `customer_companies` SET customer_other = :name WHERE id = :id')
            ->execute(['name' => $name, 'id' => $assignmentId]);
    }

    /**
     * Default-order prefix: customers with a tracking sort_order (their row order in the imported customers.xls) come
     * first in that order, the rest follow in the usual order. '' while the column has not been added.
     */
    private function sheetOrder(): string
    {
        $trackingTable = $this->identifier((string) config('customer.tracking_table', 'customer_installations'));
        if (self::$hasSortOrder === null) {
            self::$hasSortOrder = (bool) $this->db->query("SHOW COLUMNS FROM `{$trackingTable}` LIKE 'sort_order'")->fetch();
        }
        if (!self::$hasSortOrder) {
            return '';
        }
        $position = sprintf('(SELECT so.sort_order FROM `%s` so WHERE so.worksheet_id = `%s`.%s)', $trackingTable, $this->table, $this->col('id'));
        return "{$position} IS NULL ASC, {$position} ASC, ";
    }

    private static ?bool $hasSortOrder = null;

    /** Whether customer_companies has the customer_other column (added by hand); without it names are not replaced. */
    private static ?bool $hasCustomerOther = null;

    private static function hasCustomerOther(PDO $db): bool
    {
        if (self::$hasCustomerOther === null) {
            self::$hasCustomerOther = (bool) $db->query("SHOW COLUMNS FROM `customer_companies` LIKE 'customer_other'")->fetch();
        }
        return self::$hasCustomerOther;
    }

    public function paginate(array $filters): array
    {
        [$from, $fromParams, $nameExpr, $joined] = $this->from($filters);
        [$where, $params] = $this->where($filters, $nameExpr);
        $params += $fromParams;
        $allowedSorts = array_keys($this->columns);
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : null;
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $requestedPerPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($requestedPerPage, [10, 25, 50, 100], true) ? $requestedPerPage : 25;
        $count = $this->db->prepare("SELECT COUNT(*) FROM {$from} {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $orderBy = $sort !== null
            ? ($sort === 'name' && $nameExpr !== null ? $nameExpr : $this->col($sort)) . ' ' . $direction
            : $this->sheetOrder() . sprintf('CASE WHEN %s < 0 THEN 0 ELSE 1 END ASC, CASE WHEN %s < 0 THEN %s END ASC, %s DESC', $this->col('id'), $this->col('id'), $this->col('id'), $this->col('id'));
        $sql = sprintf('SELECT %s FROM %s %s ORDER BY %s LIMIT :limit OFFSET :offset', $this->selectColumns($nameExpr, $joined), $from, $where, $orderBy);
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
        [$from, $fromParams, $nameExpr, $joined] = $this->from($filters);
        [$where, $params] = $this->where($filters, $nameExpr);
        $params += $fromParams;
        $statement = $this->db->prepare(sprintf('SELECT %s FROM %s %s ORDER BY %s ASC', $this->selectColumns($nameExpr, $joined), $from, $where, $nameExpr ?? $this->col('name')));
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** $nameExpr: the shown name when a row's customer_companies.customer_other replaces it (see from()). */
    private function where(array $filters, ?string $nameExpr = null): array
    {
        $clauses = [];
        $params = [];
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . mb_substr(trim((string) $filters['search']), 0, 100) . '%';
            // The search finds a row by its shown name as well as by the original name.
            $shownName = $nameExpr !== null ? " OR {$nameExpr} LIKE :search_shown_name" : '';
            $clauses[] = sprintf('(%s LIKE :search_name%s OR %s LIKE :search_zone OR %s LIKE :search_status)', $this->col('name'), $shownName, $this->col('zone'), $this->col('status'));
            $params['search_name'] = $search;
            if ($nameExpr !== null) {
                $params['search_shown_name'] = $search;
            }
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

    /**
     * id/name/contract_sign_date list for the company-assignment picker: every Landy Home worksheet customer (id =
     * worksheet_id, contract date from projects.sign_contract_date), read straight from the Landy Home database rather
     * than the view, which only lists customers already assigned to a company, plus the customers added in this
     * system (negative ids, no contract date). Read-only; if Landy Home cannot be read, only the local ones are listed.
     */
    public function listForSelection(): array
    {
        $local = $this->db->query(sprintf(
            'SELECT -id AS id, customer_name AS name, NULL AS contract_sign_date FROM `%s` WHERE worksheet_id IS NULL',
            $this->identifier((string) config('customer.tracking_table', 'customer_installations'))
        ))->fetchAll();
        $landyhome = [];
        $database = (string) config('customer.landyhome_database', 'landyhome');
        try {
            // 1970-01-01 is the unset placeholder in projects.sign_contract_date.
            $landyhome = $this->db->query(sprintf(
                "SELECT w.worksheet_id AS id, c.customer_name AS name,
                        CASE WHEN p.sign_contract_date IS NULL OR p.sign_contract_date <= '1970-01-02' THEN NULL ELSE DATE(p.sign_contract_date) END AS contract_sign_date
                 FROM `%1\$s`.`worksheets` w
                 JOIN `%1\$s`.`customers` c ON c.customer_id = w.customer_id
                 LEFT JOIN `%1\$s`.`projects` p ON p.project_id = w.project_id",
                $this->identifier($database)
            ))->fetchAll();
        } catch (\PDOException $error) {
            error_log('[customer picker] ' . $error->getMessage());
        }
        $rows = array_merge($landyhome, $local);
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        return $rows;
    }

    /** The filter choices, from the data of the table of $companyId (0 = the shared data). */
    public function facets(int $companyId = 0): array
    {
        [$source, $params] = $this->source($this->dataCompany($companyId));
        $result = [];
        foreach (['zone', 'machine_size', 'status'] as $field) {
            $statement = $this->db->prepare(sprintf('SELECT DISTINCT %1$s AS value FROM %2$s WHERE %1$s IS NOT NULL AND %1$s <> \'\' ORDER BY %1$s', $this->col($field), $source));
            $statement->execute($params);
            $result[$field] = $statement->fetchAll(PDO::FETCH_COLUMN);
        }
        return $result;
    }

    /** One customer as the table of $companyId shows it (0 = the shared data). */
    public function find(string $id, int $companyId = 0): ?array
    {
        [$source, $params] = $this->source($this->dataCompany($companyId));
        $statement = $this->db->prepare(sprintf('SELECT %s FROM %s WHERE %s = :id LIMIT 1', $this->selectColumns(), $source, $this->col('id')));
        $statement->execute($params + ['id' => $id]);
        return $statement->fetch() ?: null;
    }

    /** Save a customer's tracking fields as edited in the table of $companyId (0 = the shared data). */
    public function update(string $id, array $data, int $companyId = 0): void
    {
        $this->assertWritesEnabled();
        $trackingTable = $this->identifier((string) config('customer.tracking_table', 'customer_installations'));
        $dataCompany = $this->dataCompany($companyId);
        if ($dataCompany !== null) {
            $this->updateCompanyData((int) $id, $dataCompany, $data, $trackingTable);
            return;
        }
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
        unset($params['name'], $params['name_override']);
        $statement->execute($params);

        // An admin renamed the customer on its detail page: the name is kept here (landyhome is never written) and
        // customer_installation_view shows it instead of the Landy Home name. NULL = back to the Landy Home name.
        if (array_key_exists('name_override', $data)) {
            $rename = $this->db->prepare("UPDATE `{$trackingTable}` SET customer_name = :name WHERE worksheet_id = :id");
            $rename->execute(['name' => $data['name_override'], 'id' => $id]);
        }
    }

    /**
     * When a column of $companyId goes back from "ดึงจาก" to กรอกเอง: $copyFrom = the company it was read from, whose
     * values become this company's own (a starting point to edit); null = empty the column. Only this company's own
     * rows change. วันที่ส่ง PO also resets the typed ครบกำหนด 60 วัน, so it follows the new PO date.
     */
    public function resetCompanyColumn(int $companyId, string $key, ?int $copyFrom): void
    {
        $this->assertWritesEnabled();
        if (!in_array($key, CompanyColumnSourceRepository::LINKABLE_FIELDS, true) || $this->dataCompany($companyId) === null) {
            return;
        }
        $empty = $key === 'status' ? "'STATUS'" : 'NULL';
        $also = $key === 'po_sent_date' && $this->hasCompanyDue60() ? ', `due_60_date` = NULL' : '';
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE `company_installations` SET `{$key}` = {$empty}{$also} WHERE company_id = :company_id")
                ->execute(['company_id' => $companyId]);
            if ($copyFrom !== null) {
                // From the shared data (view) or the source company's own rows, for the customers in this company.
                $from = $this->dataCompany($copyFrom) === null
                    ? sprintf('SELECT v.`%s` AS customer_id, v.`%s` AS value FROM `%s` v', $this->columns['id'], $this->columns[$key], $this->table)
                    : "SELECT s.customer_id, s.`{$key}` AS value FROM `company_installations` s WHERE s.company_id = :source_id";
                $statement = $this->db->prepare(sprintf(
                    "INSERT INTO `company_installations` (company_id, customer_id, `%1\$s`)
                     SELECT :company_id, src.customer_id, src.value FROM (%2\$s) src
                     WHERE src.value IS NOT NULL AND src.customer_id IN (SELECT customer_id FROM `customer_companies` WHERE company_id = :company_id2)
                     ON DUPLICATE KEY UPDATE `%1\$s` = VALUES(`%1\$s`)",
                    $key,
                    $from
                ));
                $params = ['company_id' => $companyId, 'company_id2' => $companyId];
                if ($this->dataCompany($copyFrom) !== null) {
                    $params['source_id'] = $copyFrom;
                }
                $statement->execute($params);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /**
     * update() for a company with its own data: the tracking fields go to its company_installations row. The name is
     * not a company's own (a table row has its own name in customer_companies.customer_other), so a local customer's
     * name and an admin's rename still change the shared name in customer_installations.
     */
    private function updateCompanyData(int $customerId, int $companyId, array $data, string $trackingTable): void
    {
        $fields = self::COMPANY_FIELDS;
        if ($this->hasCompanyDue60()) {
            $fields[] = 'due_60_date';
        }
        // A column read from another company's table ("ดึงจาก") shows that table's value; it must not be written
        // into this company's own row, or switching back to กรอกเอง would show a copy instead of its own data.
        $links = (new CompanyColumnSourceRepository())->forCompany($companyId);
        $linked = array_keys($links);
        if (isset($links['po_sent_date'])) {
            $linked[] = 'due_60_date';
        }
        $updateFields = array_values(array_diff($fields, $linked));
        $statement = $this->db->prepare(sprintf(
            'INSERT INTO `company_installations` (`company_id`, `customer_id`, %s) VALUES (:company_id, :customer_id, %s) ON DUPLICATE KEY UPDATE %s, `updated_at` = NOW()',
            implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
            implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields)),
            implode(', ', array_map(static fn (string $field): string => "`{$field}` = VALUES(`{$field}`)", $updateFields))
        ));
        $params = ['company_id' => $companyId, 'customer_id' => $customerId];
        foreach ($fields as $field) {
            $params[$field] = in_array($field, $linked, true) ? null : ($data[$field] ?? null);
        }
        $params['status'] = $params['status'] ?? 'STATUS';
        $statement->execute($params);

        if ($customerId < 0 && ($data['name'] ?? null) !== null) {
            $this->db->prepare("UPDATE `{$trackingTable}` SET customer_name = :name WHERE id = :local_id AND worksheet_id IS NULL")
                ->execute(['name' => $data['name'], 'local_id' => abs($customerId)]);
        }
        if ($customerId > 0 && array_key_exists('name_override', $data)) {
            $this->db->prepare("INSERT INTO `{$trackingTable}` (worksheet_id, customer_name) VALUES (:id, :name) ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)")
                ->execute(['id' => $customerId, 'name' => $data['name_override']]);
        }
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
            if ($this->hasCompanyData()) {
                $this->db->prepare('DELETE FROM `company_installations` WHERE customer_id = :id')->execute(['id' => (int) $id]);
            }
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
}
