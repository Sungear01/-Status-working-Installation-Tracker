<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** User-defined columns on a company's customer table, and the per-customer values entered in them. */
final class CustomColumnRepository
{
    /** Built-in date columns a formula column may be computed from. */
    public const DATE_SOURCES = [
        'po_sent_date' => 'วันที่ส่ง PO',
        'po_due_date' => 'หลังจัดส่ง PO 60 วัน',
        'engineer_appointment_1' => 'วิศวกรนัดครั้งที่ 1',
        'installation_date_1' => 'ติดตั้งครั้งที่ 1',
        'engineer_appointment_2' => 'วิศวกรนัดครั้งที่ 2',
        'installation_date_2' => 'ติดตั้งครั้งที่ 2',
        'engineer_appointment_3' => 'วิศวกรนัดครั้งที่ 3',
        'installation_date_3' => 'ติดตั้งครั้งที่ 3',
        'due_60_date' => 'ครบกำหนด 60 วัน ครั้งที่ 2',
    ];

    /**
     * Colour-rule conditions, as [op => label]. lt/gt compare numbers, eq/ne compare numbers or text depending on
     * the column, before_today/from_today compare dates, and empty/not_empty work on any column.
     */
    public const RULE_OPS = [
        'lt' => 'น้อยกว่า',
        'gt' => 'มากกว่า',
        'eq' => 'เท่ากับ',
        'ne' => 'ไม่เท่ากับ',
        'contains' => 'มีคำว่า',
        'before_today' => 'ก่อนวันนี้ (เลยมาแล้ว)',
        'from_today' => 'วันนี้หรือหลังจากนี้',
        'empty' => 'ว่าง',
        'not_empty' => 'ไม่ว่าง',
    ];

    /** The conditions each kind of value can use; see valueKind(). */
    public const RULE_OPS_BY_KIND = [
        'number' => ['lt', 'gt', 'eq', 'ne', 'empty', 'not_empty'],
        'text' => ['eq', 'ne', 'contains', 'empty', 'not_empty'],
        'date' => ['before_today', 'from_today', 'empty', 'not_empty'],
    ];

    /** Conditions that compare against no value of their own. */
    public const RULE_OPS_WITHOUT_VALUE = ['before_today', 'from_today', 'empty', 'not_empty'];

    /** Colours a rule can paint a cell with, as [key => label]; the keys match the cell-rule-* classes in app.css. */
    public const RULE_COLORS = [
        'red' => 'แดง',
        'orange' => 'ส้ม',
        'yellow' => 'เหลือง',
        'green' => 'เขียว',
        'blue' => 'น้ำเงิน',
        'purple' => 'ม่วง',
        'gray' => 'เทา',
    ];

    /** How many colour rules the column form offers. */
    public const MAX_RULES = 3;

    /** Which optional columns the database already has; see database/company_custom_columns.sql. */
    private static array $hasColumn = [];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * formula_target and color_rules were added after the first release, so a database that has not run the
     * migration yet keeps working: the column simply reads as null and nothing writes to it.
     */
    private function hasColumn(string $name): bool
    {
        if (!isset(self::$hasColumn[$name])) {
            // SHOW does not take placeholders in MariaDB, so the (internal, fixed) name is quoted in place.
            self::$hasColumn[$name] = (bool) $this->db->query('SHOW COLUMNS FROM company_custom_columns LIKE ' . $this->db->quote($name))->fetch();
        }
        return self::$hasColumn[$name];
    }

    /** False until database/company_custom_columns.sql has added color_rules; until then rules are not saved. */
    public function supportsColorRules(): bool
    {
        return $this->hasColumn('color_rules');
    }

    private function selectColumns(): string
    {
        return 'id, company_id, label, type, formula_source, formula_days, '
            . ($this->hasColumn('formula_target') ? 'formula_target' : 'NULL AS formula_target')
            . ', options, '
            . ($this->hasColumn('color_rules') ? 'color_rules' : 'NULL AS color_rules');
    }

    public function forCompany(int $companyId): array
    {
        $statement = $this->db->prepare('SELECT ' . $this->selectColumns() . ' FROM company_custom_columns WHERE company_id = :company_id ORDER BY id ASC');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT ' . $this->selectColumns() . ' FROM company_custom_columns WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function create(int $companyId, array $data): int
    {
        $values = $this->writeValues($data) + ['company_id' => $companyId];
        $fields = array_keys($values);
        $statement = $this->db->prepare('INSERT INTO company_custom_columns (' . implode(', ', $fields) . ') VALUES (:' . implode(', :', $fields) . ')');
        $statement->execute($values);
        return (int) $this->db->lastInsertId();
    }

    /** The column values a create or an update writes, minus the ones this database does not have yet. */
    private function writeValues(array $data): array
    {
        $values = [
            'label' => $data['label'],
            'type' => $data['type'],
            'formula_source' => $data['formula_source'],
            'formula_days' => $data['formula_days'],
            'options' => !empty($data['options']) ? json_encode(array_values($data['options']), JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($this->hasColumn('formula_target')) {
            $values['formula_target'] = $data['formula_target'] ?? null;
        }
        if ($this->hasColumn('color_rules')) {
            $values['color_rules'] = !empty($data['color_rules']) ? json_encode(array_values($data['color_rules']), JSON_UNESCAPED_UNICODE) : null;
        }
        return $values;
    }

    public function update(int $id, array $data): void
    {
        $values = $this->writeValues($data);
        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, array_keys($values)));
        $statement = $this->db->prepare('UPDATE company_custom_columns SET ' . $assignments . ' WHERE id = :id');
        $statement->execute($values + ['id' => $id]);
    }

    /** Rename a column only, as the column-order list does; the rest of its settings stay as they are. */
    public function updateLabel(int $id, string $label): void
    {
        $this->db->prepare('UPDATE company_custom_columns SET label = :label WHERE id = :id')->execute(['label' => $label, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM company_custom_columns WHERE id = :id')->execute(['id' => $id]);
    }

    /** Raw entered values as [customer_id => [column_id => value]]. */
    public function valuesFor(array $customerIds, array $columnIds): array
    {
        if ($customerIds === [] || $columnIds === []) {
            return [];
        }
        $customerIds = array_values(array_map('intval', $customerIds));
        $columnIds = array_values(array_map('intval', $columnIds));
        $sql = 'SELECT customer_id, column_id, value FROM customer_custom_values WHERE customer_id IN ('
            . implode(',', array_fill(0, count($customerIds), '?')) . ') AND column_id IN ('
            . implode(',', array_fill(0, count($columnIds), '?')) . ')';
        $statement = $this->db->prepare($sql);
        $statement->execute(array_merge($customerIds, $columnIds));
        $values = [];
        foreach ($statement->fetchAll() as $row) {
            $values[(int) $row['customer_id']][(int) $row['column_id']] = $row['value'];
        }
        return $values;
    }

    public function saveValue(int $customerId, int $columnId, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->db->prepare('DELETE FROM customer_custom_values WHERE customer_id = :customer_id AND column_id = :column_id')
                ->execute(['customer_id' => $customerId, 'column_id' => $columnId]);
            return;
        }
        $this->db->prepare('INSERT INTO customer_custom_values (customer_id, column_id, value) VALUES (:customer_id, :column_id, :value)
                            ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute(['customer_id' => $customerId, 'column_id' => $columnId, 'value' => $value]);
    }

    /**
     * Display values for one customer row as ['custom_<id>' => value]. Formula columns are computed from their
     * source (a built-in date column or an earlier custom column) plus formula_days; a missing source gives null.
     */
    public static function resolve(array $columns, array $row, array $rawValues): array
    {
        $resolved = [];
        foreach ($columns as $column) {
            $key = 'custom_' . $column['id'];
            if (self::isDifference($column)) {
                $source = self::dateOf((string) $column['formula_source'], $row, $resolved);
                $target = self::dateOf((string) $column['formula_target'], $row, $resolved);
                // Days between the two dates (source - target), like the built-in ช้า/เร็ว column.
                $resolved[$key] = $source && $target ? (string) (int) $target->diff($source)->format('%r%a') : null;
            } elseif ($column['type'] === 'date' && $column['formula_source'] !== null && $column['formula_source'] !== '') {
                $source = (string) $column['formula_source'];
                $base = str_starts_with($source, 'custom_') ? ($resolved[$source] ?? null) : ($row[$source] ?? null);
                $date = $base ? date_create_immutable((string) $base) : false;
                $resolved[$key] = $date ? $date->modify(sprintf('%+d days', (int) $column['formula_days']))->format('Y-m-d') : null;
            } else {
                $value = $rawValues[(int) $column['id']] ?? null;
                $resolved[$key] = $value === '' ? null : $value;
            }
        }
        return $resolved;
    }

    /** True when the column counts the days between two date columns instead of holding a date. */
    public static function isDifference(array $column): bool
    {
        return $column['type'] === 'date'
            && !empty($column['formula_source'])
            && !empty($column['formula_target']);
    }

    /** The date a formula side points at: a built-in column of $row, or an already resolved custom column. */
    private static function dateOf(string $key, array $row, array $resolved): ?\DateTimeImmutable
    {
        $value = str_starts_with($key, 'custom_') ? ($resolved[$key] ?? null) : ($row[$key] ?? null);
        return $value ? (date_create_immutable((string) $value) ?: null) : null;
    }

    /** What a column's cells hold for colour rules: 'number' (a number column or a days difference), 'date' or 'text'. */
    public static function valueKind(array $column): string
    {
        if ($column['type'] === 'number' || self::isDifference($column)) {
            return 'number';
        }
        return $column['type'] === 'date' ? 'date' : 'text';
    }

    /** The column's colour rules as a list of ['op' => ..., 'value' => ..., 'color' => ...], or [] when it has none. */
    public static function colorRules(array $column): array
    {
        $rules = json_decode((string) ($column['color_rules'] ?? ''), true);
        if (!is_array($rules)) {
            return [];
        }
        return array_values(array_filter($rules, static fn ($rule): bool => is_array($rule)
            && isset(self::RULE_OPS[$rule['op'] ?? ''], self::RULE_COLORS[$rule['color'] ?? ''])));
    }

    /** Dropdown choices of a text column, or [] when it is free text. */
    public static function options(array $column): array
    {
        $options = json_decode((string) ($column['options'] ?? ''), true);
        return is_array($options) ? array_values(array_map('strval', $options)) : [];
    }

    public static function formulaText(array $column, array $customColumns): string
    {
        if ($column['type'] !== 'date' || empty($column['formula_source'])) {
            return '';
        }
        $label = self::sourceLabel((string) $column['formula_source'], $customColumns);
        if (self::isDifference($column)) {
            return $label . ' − ' . self::sourceLabel((string) $column['formula_target'], $customColumns);
        }
        $days = (int) $column['formula_days'];
        return $label . ($days < 0 ? ' - ' . abs($days) : ' + ' . $days) . ' วัน';
    }

    /** Readable name of one side of a formula. */
    private static function sourceLabel(string $key, array $customColumns): string
    {
        foreach ($customColumns as $other) {
            if ('custom_' . $other['id'] === $key) {
                return (string) $other['label'];
            }
        }
        return self::DATE_SOURCES[$key] ?? $key;
    }
}
