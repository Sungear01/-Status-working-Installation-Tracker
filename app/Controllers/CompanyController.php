<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Repositories\CompanyColumnLabelRepository;
use App\Repositories\CompanyColumnSourceRepository;
use App\Repositories\CompanyGroupRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CustomColumnRepository;
use App\Repositories\CustomerRepository;
use App\Services\ActivityLogger;

final class CompanyController
{
    public function store(): void
    {
        $this->verifyCsrf();
        $names = $this->names();
        if ($names === []) {
            flash('error', 'กรุณาระบุชื่อบริษัท');
            redirect('/customers');
        }
        $shared = $this->sharedInput();
        $shared['created_by'] = Auth::id();
        $repository = new CompanyRepository();
        $logger = new ActivityLogger();
        $ids = [];
        foreach ($names as $name) {
            $existing = $repository->findByName($name);
            $data = array_merge($shared, ['name' => $name]);
            if ($existing) {
                $ids[] = (int) $existing['id'];
                $repository->update((int) $existing['id'], $data);
                $logger->log('update', 'company', (string) $existing['id'], 'แก้ไขบริษัท ' . $name, $existing, $data);
            } else {
                $newId = $repository->create($data);
                $ids[] = $newId;
                $logger->log('create', 'company', (string) $newId, 'เพิ่มบริษัท ' . $name, null, $data);
            }
        }
        flash('success', count($names) > 1 ? 'บันทึกบริษัทเรียบร้อย ' . count($names) . ' บริษัท' : 'บันทึกบริษัทเรียบร้อย');
        redirect($this->redirectUrl($ids));
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CompanyRepository();
        $old = $repository->find((int) $id);
        if (!$old) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        $names = $this->names();
        if ($names === []) {
            flash('error', 'กรุณาระบุชื่อบริษัท');
            redirect('/customers');
        }
        $shared = $this->sharedInput();
        $logger = new ActivityLogger();

        if (count($names) === 1) {
            // Single name selected: treat this as a plain rename/update of the company being edited.
            $data = array_merge($shared, ['name' => $names[0]]);
            $repository->update((int) $id, $data);
            $logger->log('update', 'company', $id, 'แก้ไขบริษัท ' . $data['name'], $old, $data);
            $ids = [(int) $id];
        } else {
            // Multiple names selected: apply the same start year / customer list to every selected
            // company (existing ones get updated, names with no match yet get created).
            $ids = [];
            foreach ($names as $name) {
                $existing = $name === $old['name'] ? $old : $repository->findByName($name);
                $data = array_merge($shared, ['name' => $name]);
                if ($existing) {
                    $ids[] = (int) $existing['id'];
                    $repository->update((int) $existing['id'], $data);
                    $logger->log('update', 'company', (string) $existing['id'], 'แก้ไขบริษัท ' . $name, $existing, $data);
                } else {
                    $data['created_by'] = Auth::id();
                    $newId = $repository->create($data);
                    $ids[] = $newId;
                    $logger->log('create', 'company', (string) $newId, 'เพิ่มบริษัท ' . $name, null, $data);
                }
            }
        }
        flash('success', count($names) > 1 ? 'บันทึกบริษัทเรียบร้อย ' . count($names) . ' บริษัท' : 'บันทึกข้อมูลบริษัทเรียบร้อย');
        redirect($this->redirectUrl($ids));
    }

    /** After saving 2+ companies together, land on a combined tab that shows them all at once. */
    private function redirectUrl(array $ids): string
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) < 2) {
            return '/customers?company_id=' . ($ids[0] ?? '');
        }
        $groupId = (new CompanyGroupRepository())->findOrCreate($ids);
        return '/customers?group_id=' . $groupId;
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CompanyRepository();
        $company = $repository->find((int) $id);
        if (!$company) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        if ($company['is_default']) {
            flash('error', 'ไม่สามารถลบบริษัทหลักได้');
            redirect('/customers');
        }
        $repository->delete((int) $id);
        (new ActivityLogger())->log('delete', 'company', $id, 'ลบบริษัท ' . $company['name'], $company, null);
        flash('success', 'ลบบริษัทเรียบร้อย');
        redirect('/customers');
    }

    /** Columns a compact company table may show; keys match the customer list columns. */
    public const TABLE_COLUMNS = [
        'name', 'zone', 'machine_size', 'po_sent_date', 'po_due_date', 'engineer', 'engineer_appointment_1', 'installation_date_1',
        'engineer_appointment_2', 'installation_date_2', 'engineer_appointment_3', 'installation_date_3', 'due_60_date',
        'days_early_late', 'status', 'construction_notes', 'work_status', 'cap_notes', 'actions',
    ];

    public function updateColumns(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CompanyRepository();
        $company = $repository->find((int) $id);
        if (!$company) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        $selected = $_POST['columns'] ?? [];
        $selected = is_array($selected) ? array_map('strval', $selected) : [];
        // Keep the order the admin arranged; the customer name stays first and the actions column last.
        $valid = $this->orderedColumnKeys((int) $id);
        $columns = ['name'];
        foreach ($selected as $key) {
            if (in_array($key, $valid, true) && !in_array($key, ['name', 'actions'], true) && !in_array($key, $columns, true)) {
                $columns[] = $key;
            }
        }
        if (in_array('actions', $selected, true)) {
            $columns[] = 'actions';
        }
        $repository->updateTableColumns((int) $id, $columns);
        $labelChanges = $this->saveColumnLabels((int) $id, $valid);
        [$oldSources, $newSources] = $this->saveColumnSources((int) $id);
        (new ActivityLogger())->log('update', 'company', $id, 'แก้ไขคอลัมน์ตารางบริษัท ' . $company['name'], ['table_columns' => $company['table_columns'], 'sources' => $oldSources], ['table_columns' => $columns, 'labels' => $labelChanges, 'sources' => $newSources]);
        flash('success', 'บันทึกคอลัมน์ตารางเรียบร้อย');
        redirect('/customers?company_id=' . (int) $id);
    }

    public function storeCustomColumn(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CompanyRepository();
        $company = $repository->find((int) $id);
        if (!$company) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        $customColumns = new CustomColumnRepository();
        $data = $this->customColumnInput((int) $id, $customColumns);
        $columnId = $customColumns->create((int) $id, $data);

        // Show the new column straight away.
        $visible = json_decode((string) ($company['table_columns'] ?? ''), true);
        if (!is_array($visible) || $visible === []) {
            $visible = ['name', 'po_sent_date', 'po_due_date', 'engineer'];
        }
        // Appended after the columns already arranged, still ahead of the actions column.
        $hasActions = in_array('actions', $visible, true);
        $visible = array_values(array_diff($visible, ['actions']));
        $visible[] = 'custom_' . $columnId;
        if ($hasActions) {
            $visible[] = 'actions';
        }
        $repository->updateTableColumns((int) $id, $visible);

        (new ActivityLogger())->log('create', 'company_custom_column', (string) $columnId, 'เพิ่มคอลัมน์ ' . $data['label'] . ' ในตารางบริษัท ' . $company['name'], null, $data);
        flash('success', 'เพิ่มคอลัมน์ ' . $data['label'] . ' เรียบร้อย');
        redirect('/customers?company_id=' . (int) $id);
    }

    public function updateCustomColumn(string $id, string $columnId): void
    {
        $this->verifyCsrf();
        $company = (new CompanyRepository())->find((int) $id);
        if (!$company) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        $customColumns = new CustomColumnRepository();
        $column = $customColumns->find((int) $columnId);
        if (!$column || (int) $column['company_id'] !== (int) $id) {
            http_response_code(404);
            exit('ไม่พบคอลัมน์');
        }
        $data = $this->customColumnInput((int) $id, $customColumns, (int) $columnId);
        if ($data['type'] !== 'date') {
            foreach ($customColumns->forCompany((int) $id) as $other) {
                if (in_array('custom_' . $column['id'], [$other['formula_source'], $other['formula_target']], true)) {
                    flash('error', 'เปลี่ยนประเภทคอลัมน์ไม่ได้ เพราะคอลัมน์ ' . $other['label'] . ' ใช้คอลัมน์นี้ในสูตร');
                    redirect('/customers?company_id=' . (int) $id);
                }
            }
        }
        $customColumns->update((int) $columnId, $data);
        (new ActivityLogger())->log('update', 'company_custom_column', $columnId, 'แก้ไขคอลัมน์ ' . $data['label'] . ' ในตารางบริษัท ' . $company['name'], $column, $data);
        flash('success', 'บันทึกคอลัมน์ ' . $data['label'] . ' เรียบร้อย');
        redirect('/customers?company_id=' . (int) $id);
    }

    /**
     * Validated custom-column fields from the posted form; flashes and redirects when something is missing.
     * When editing, $columnId keeps a column from taking itself — or a later column — as its formula source,
     * so custom columns stay resolvable in id order.
     */
    private function customColumnInput(int $companyId, CustomColumnRepository $customColumns, ?int $columnId = null): array
    {
        $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 150);
        $type = in_array($_POST['type'] ?? '', ['date', 'number'], true) ? $_POST['type'] : 'text';
        if ($label === '') {
            flash('error', 'กรุณาระบุชื่อคอลัมน์');
            redirect('/customers?company_id=' . $companyId);
        }
        $source = null;
        $days = null;
        $target = null;
        if ($type === 'date' && !empty($_POST['use_formula'])) {
            $allowed = array_keys(CustomColumnRepository::DATE_SOURCES);
            foreach ($customColumns->forCompany($companyId) as $existing) {
                // A column that counts days is a number, so it cannot be one side of another date formula.
                if ($existing['type'] === 'date' && !CustomColumnRepository::isDifference($existing) && ($columnId === null || (int) $existing['id'] < $columnId)) {
                    $allowed[] = 'custom_' . $existing['id'];
                }
            }
            $source = (string) ($_POST['formula_source'] ?? '');
            if (!in_array($source, $allowed, true)) {
                flash('error', 'กรุณาเลือกคอลัมน์ต้นทางของสูตร');
                redirect('/customers?company_id=' . $companyId);
            }
            if (($_POST['formula_mode'] ?? 'offset') === 'diff') {
                // Days between two date columns, the way the built-in ช้า/เร็ว column is worked out.
                $target = (string) ($_POST['formula_target'] ?? '');
                if (!in_array($target, $allowed, true) || $target === $source) {
                    flash('error', 'กรุณาเลือกคอลัมน์วันที่อีกช่องที่จะนำมาลบกัน (ต้องไม่ใช่คอลัมน์เดียวกัน)');
                    redirect('/customers?company_id=' . $companyId);
                }
            } else {
                $days = max(0, min(3650, (int) ($_POST['formula_days'] ?? 0)));
                if (($_POST['formula_operator'] ?? '+') === '-') {
                    $days = -$days;
                }
            }
        }
        $options = [];
        if ($type === 'text' && !empty($_POST['use_options'])) {
            foreach (preg_split('/\R/u', (string) ($_POST['options'] ?? '')) as $option) {
                $option = mb_substr(trim($option), 0, 150);
                if ($option !== '' && !in_array($option, $options, true)) {
                    $options[] = $option;
                }
            }
            if ($options === []) {
                flash('error', 'กรุณาใส่ตัวเลือกของ Dropdown อย่างน้อย 1 รายการ');
                redirect('/customers?company_id=' . $companyId);
            }
        }
        $kind = CustomColumnRepository::valueKind(['type' => $type, 'formula_source' => $source, 'formula_target' => $target]);
        $colorRules = !empty($_POST['use_color_rules']) ? $this->colorRulesInput($kind) : [];
        if ($colorRules !== [] && !$customColumns->supportsColorRules()) {
            flash('error', 'ยังบันทึกเงื่อนไขสีไม่ได้ เพราะฐานข้อมูลยังไม่มีคอลัมน์ color_rules (ต้องรัน database/company_custom_columns.sql ก่อน) · ส่วนอื่นของคอลัมน์บันทึกแล้ว');
        }
        return ['label' => $label, 'type' => $type, 'formula_source' => $source, 'formula_days' => $days, 'formula_target' => $target, 'options' => $options, 'color_rules' => $colorRules];
    }

    /**
     * The colour rules posted as rule_op[] / rule_value[] / rule_color[] (the form disables the rows it hides, so
     * only the shown ones arrive). A condition the column's kind of value cannot use is dropped, and a rule left
     * without a usable value is skipped with a notice instead of throwing away the whole column form.
     */
    private function colorRulesInput(string $kind): array
    {
        $ops = is_array($_POST['rule_op'] ?? null) ? $_POST['rule_op'] : [];
        $values = is_array($_POST['rule_value'] ?? null) ? $_POST['rule_value'] : [];
        $colors = is_array($_POST['rule_color'] ?? null) ? $_POST['rule_color'] : [];
        $rules = [];
        $skipped = [];
        foreach (array_slice($ops, 0, CustomColumnRepository::MAX_RULES, true) as $index => $op) {
            $op = is_string($op) ? $op : '';
            $color = is_string($colors[$index] ?? null) ? $colors[$index] : '';
            if (!in_array($op, CustomColumnRepository::RULE_OPS_BY_KIND[$kind], true) || !isset(CustomColumnRepository::RULE_COLORS[$color])) {
                continue;
            }
            $value = '';
            if (!in_array($op, CustomColumnRepository::RULE_OPS_WITHOUT_VALUE, true)) {
                $value = is_string($values[$index] ?? null) ? mb_substr(trim($values[$index]), 0, 150) : '';
                if ($value === '' || ($kind === 'number' && !is_numeric($value))) {
                    $skipped[] = '"' . CustomColumnRepository::RULE_OPS[$op] . '"';
                    continue;
                }
            }
            $rules[] = ['op' => $op, 'value' => $value, 'color' => $color];
        }
        if ($skipped !== []) {
            flash('error', 'ข้ามเงื่อนไขสี ' . implode(', ', $skipped) . ' เพราะยังไม่ได้กรอกค่า' . ($kind === 'number' ? 'เป็นตัวเลข' : '') . ' · คอลัมน์บันทึกแล้ว กดแก้ไขคอลัมน์เพื่อเพิ่มสีภายหลังได้');
        }
        return $rules;
    }

    public function destroyCustomColumn(string $id, string $columnId): void
    {
        $this->verifyCsrf();
        $customColumns = new CustomColumnRepository();
        $column = $customColumns->find((int) $columnId);
        if (!$column || (int) $column['company_id'] !== (int) $id) {
            http_response_code(404);
            exit('ไม่พบคอลัมน์');
        }
        foreach ($customColumns->forCompany((int) $id) as $other) {
            if (in_array('custom_' . $column['id'], [$other['formula_source'], $other['formula_target']], true)) {
                flash('error', 'ลบไม่ได้ เพราะคอลัมน์ ' . $other['label'] . ' ใช้คอลัมน์นี้ในสูตร');
                redirect('/customers?company_id=' . (int) $id);
            }
        }
        $customColumns->delete((int) $columnId);
        (new ActivityLogger())->log('delete', 'company_custom_column', $columnId, 'ลบคอลัมน์ ' . $column['label'], $column, null);
        flash('success', 'ลบคอลัมน์ ' . $column['label'] . ' เรียบร้อย');
        redirect('/customers?company_id=' . (int) $id);
    }

    /**
     * Save where each column of the table takes its data (sources[key] = '' for กรอกเอง, or another company's id for
     * ดึงจาก). Only a company with its own data can link; returns [old links, new links] for the activity log.
     * A column going back to กรอกเอง starts from source_reset[key]: '' = this table's own data as it is, 'copy' = the
     * values it was showing from the other table, 'empty' = blank.
     */
    private function saveColumnSources(int $companyId): array
    {
        $posted = $_POST['sources'] ?? null;
        $customers = new CustomerRepository();
        if (!is_array($posted) || $customers->dataCompany($companyId) === null) {
            return [[], []];
        }
        $companyIds = array_map(static fn (array $c): int => (int) $c['id'], (new CompanyRepository())->all());
        $sources = new CompanyColumnSourceRepository();
        $old = $sources->forCompany($companyId);
        $next = [];
        foreach (CompanyColumnSourceRepository::LINKABLE_FIELDS as $key) {
            // Only the columns shown in the table send a choice; a hidden column keeps its link.
            $sourceId = array_key_exists($key, $posted) ? (int) $posted[$key] : (int) ($old[$key] ?? 0);
            if ($sourceId > 0 && $sourceId !== $companyId && in_array($sourceId, $companyIds, true)) {
                $next[$key] = $sourceId;
            }
        }
        $resets = is_array($_POST['source_reset'] ?? null) ? $_POST['source_reset'] : [];
        foreach ($old as $key => $oldSourceId) {
            $mode = (string) ($resets[$key] ?? '');
            if (!isset($next[$key]) && in_array($mode, ['copy', 'empty'], true)) {
                $customers->resetCompanyColumn($companyId, $key, $mode === 'copy' ? $oldSourceId : null);
                $next['reset:' . $key] = $mode;  // logged only; not a link
            }
        }
        $links = array_filter($next, static fn ($value, $key): bool => !str_starts_with((string) $key, 'reset:'), ARRAY_FILTER_USE_BOTH);
        if ($links !== $old) {
            $sources->replaceForCompany($companyId, $links);
        }
        return [$old, $next];
    }

    /**
     * Header names typed into the column-order list (labels[key]). A custom column is renamed in place; a built-in
     * one keeps a per-company name, and typing its default name back (default_labels[key]) or leaving it blank
     * removes that. Names of columns not posted (hidden ones) are kept. Returns the names that changed.
     */
    private function saveColumnLabels(int $companyId, array $validKeys): array
    {
        $posted = is_array($_POST['labels'] ?? null) ? $_POST['labels'] : [];
        $defaults = is_array($_POST['default_labels'] ?? null) ? $_POST['default_labels'] : [];
        $labels = new CompanyColumnLabelRepository();
        $current = $labels->forCompany($companyId);
        $next = $current;
        $customColumns = new CustomColumnRepository();
        $changes = [];
        foreach ($posted as $key => $label) {
            $key = (string) $key;
            $label = mb_substr(trim((string) $label), 0, CompanyColumnLabelRepository::MAX_LENGTH);
            if (!in_array($key, $validKeys, true) || $key === 'actions') {
                continue;
            }
            if (str_starts_with($key, 'custom_')) {
                $column = $customColumns->find((int) substr($key, 7));
                if ($label !== '' && $column && (int) $column['company_id'] === $companyId && $column['label'] !== $label) {
                    $customColumns->updateLabel((int) $column['id'], $label);
                    $changes[$key] = $label;
                }
                continue;
            }
            if ($label === '' || $label === trim((string) ($defaults[$key] ?? ''))) {
                unset($next[$key]);
            } else {
                $next[$key] = $label;
            }
            if (($current[$key] ?? null) !== ($next[$key] ?? null)) {
                $changes[$key] = $next[$key] ?? '(ค่าเริ่มต้น)';
            }
        }
        if ($next !== $current) {
            $labels->replaceForCompany($companyId, $next);
        }
        return $changes;
    }

    /** Built-in column keys followed by this company's custom columns, with the actions column kept last. */
    private function orderedColumnKeys(int $companyId): array
    {
        $keys = array_values(array_diff(self::TABLE_COLUMNS, ['actions']));
        foreach ((new CustomColumnRepository())->forCompany($companyId) as $column) {
            $keys[] = 'custom_' . $column['id'];
        }
        $keys[] = 'actions';
        return $keys;
    }

    public function updateGroup(string $id): void
    {
        $this->verifyCsrf();
        $groups = new CompanyGroupRepository();
        $old = $groups->find((int) $id);
        if (!$old) {
            http_response_code(404);
            exit('ไม่พบข้อมูลกลุ่มบริษัท');
        }
        $companies = new CompanyRepository();
        $raw = $_POST['company_ids'] ?? [];
        $ids = [];
        foreach (is_array($raw) ? $raw : [$raw] as $companyId) {
            $companyId = (int) $companyId;
            if ($companyId > 0 && $companies->find($companyId)) {
                $ids[] = $companyId;
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) < 2) {
            flash('error', 'กลุ่มบริษัทต้องมีอย่างน้อย 2 บริษัท');
            redirect('/customers?group_id=' . (int) $id);
        }

        // If another group already holds exactly this set, merge into it instead of keeping a duplicate tab.
        $sorted = $ids;
        sort($sorted);
        foreach ($groups->all() as $group) {
            $existing = $group['company_ids'];
            sort($existing);
            if ((int) $group['id'] !== (int) $id && $existing === $sorted) {
                $groups->delete((int) $id);
                (new ActivityLogger())->log('delete', 'company_group', $id, 'รวมกลุ่มบริษัท ' . $old['label'] . ' เข้ากับ ' . $group['label'], $old, $group);
                flash('success', 'มีกลุ่ม ' . $group['label'] . ' อยู่แล้ว ระบบรวมให้เรียบร้อย');
                redirect('/customers?group_id=' . (int) $group['id']);
            }
        }

        $groups->updateMembers((int) $id, $ids);
        $new = $groups->find((int) $id);
        (new ActivityLogger())->log('update', 'company_group', $id, 'แก้ไขกลุ่มบริษัท ' . ($new['label'] ?? ''), $old, $new);
        flash('success', 'บันทึกกลุ่มบริษัทเรียบร้อย');
        redirect('/customers?group_id=' . (int) $id);
    }

    public function destroyGroup(string $id): void
    {
        $this->verifyCsrf();
        $groups = new CompanyGroupRepository();
        $group = $groups->find((int) $id);
        if (!$group) {
            http_response_code(404);
            exit('ไม่พบข้อมูลกลุ่มบริษัท');
        }
        $groups->delete((int) $id);
        (new ActivityLogger())->log('delete', 'company_group', $id, 'ลบกลุ่มบริษัท ' . $group['label'], $group, null);
        flash('success', 'ลบกลุ่มบริษัทเรียบร้อย');
        redirect('/customers');
    }

    private function names(): array
    {
        $raw = $_POST['name'] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];
        $names = [];
        foreach ($raw as $name) {
            $name = mb_substr(trim((string) $name), 0, 150);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    private function sharedInput(): array
    {
        $startYear = trim((string) ($_POST['start_year'] ?? ''));
        // Sent as one comma-separated field (not customer_ids[] per checkbox) so a company
        // with hundreds of customers doesn't blow past PHP's max_input_vars and get truncated.
        // A customer may appear several times (the + button in the picker): one company-table row per appearance.
        $raw = trim((string) ($_POST['customer_ids'] ?? ''));
        $customerIds = $raw === '' ? [] : array_values(array_filter(array_map('intval', explode(',', $raw))));
        $startYear = preg_match('/^\d{4}$/', $startYear) ? $startYear : null;
        return [
            'start_year' => $startYear,
            'customer_ids' => $startYear === null ? $customerIds : $this->customersOfYear($startYear, $customerIds),
        ];
    }

    /**
     * A company with a contract-signing year (พ.ศ.) keeps only the ticked customers who signed that year: ticks left
     * over from other years are dropped, while a customer of the year the user unticked stays out. The picker ticks
     * the whole year when the year is picked; a customer added more than once with + keeps its extra rows.
     */
    private function customersOfYear(string $buddhistYear, array $submittedIds): array
    {
        $yearIds = [];
        foreach ((new CustomerRepository())->listForSelection() as $customer) {
            $date = (string) ($customer['contract_sign_date'] ?? '');
            if ($date !== '' && (string) ((int) substr($date, 0, 4) + 543) === $buddhistYear) {
                $yearIds[(int) $customer['id']] = true;
            }
        }
        // No customer of that year found (e.g. Landy Home could not be read): keep the ticked list as it is.
        if ($yearIds === []) {
            return $submittedIds;
        }
        return array_values(array_filter($submittedIds, static fn (int $id): bool => isset($yearIds[$id])));
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
