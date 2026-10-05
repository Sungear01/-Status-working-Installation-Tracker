<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\CompanyGroupRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CustomColumnRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\EngineerRepository;
use App\Repositories\LogRepository;
use App\Services\ActivityLogger;
use App\Services\MailService;

final class CustomerController
{
    public function index(): void
    {
        $repository = new CustomerRepository();
        $companies = (new CompanyRepository())->all();
        $viewFilters = $_GET;

        // A user tied to one company (non-admin) is confined to it, regardless of what ?company_id= is passed.
        $restrictedCompanyId = Auth::restrictedCompanyId();
        if ($restrictedCompanyId !== null) {
            $companies = array_values(array_filter($companies, static fn (array $c): bool => (int) $c['id'] === $restrictedCompanyId));
            $viewFilters['company_id'] = (string) $restrictedCompanyId;
            unset($viewFilters['group_id']);
        }

        $companyGroups = $restrictedCompanyId === null ? (new CompanyGroupRepository())->all() : [];

        $queryFilters = $viewFilters;
        $activeGroup = null;
        if (($queryFilters['group_id'] ?? '') !== '') {
            foreach ($companyGroups as $group) {
                if ((string) $group['id'] === (string) $queryFilters['group_id']) {
                    $activeGroup = $group;
                    break;
                }
            }
        }
        if ($activeGroup !== null) {
            $queryFilters['company_ids'] = $activeGroup['company_ids'];
            $queryFilters['company_is_default'] = false;
            foreach ($companies as $company) {
                if (in_array((int) $company['id'], $activeGroup['company_ids'], true) && !empty($company['is_default'])) {
                    $queryFilters['company_is_default'] = true;
                    break;
                }
            }
        } else {
            foreach ($companies as $company) {
                if ((string) $company['id'] === (string) ($queryFilters['company_id'] ?? '')) {
                    $queryFilters['company_is_default'] = !empty($company['is_default']);
                    break;
                }
            }
        }
        $result = $repository->paginate($queryFilters);

        // Custom columns belong to one company, so they only apply on a single-company tab.
        $customColumns = [];
        if ($activeGroup === null && ($viewFilters['company_id'] ?? '') !== '') {
            $customRepository = new CustomColumnRepository();
            $customColumns = $customRepository->forCompany((int) $viewFilters['company_id']);
            if ($customColumns !== []) {
                $values = $customRepository->valuesFor(array_column($result['items'], 'id'), array_column($customColumns, 'id'));
                foreach ($result['items'] as &$item) {
                    $item += CustomColumnRepository::resolve($customColumns, $item, $values[(int) $item['id']] ?? []);
                }
                unset($item);
            }
        }

        // The built-in "วิศวกร" column lives in its own table, so it is merged onto each row like a custom column.
        $engineers = new EngineerRepository();
        $engineerValues = $engineers->valuesFor(array_column($result['items'], 'id'));
        foreach ($result['items'] as &$item) {
            $item['engineer'] = $engineerValues[(int) $item['id']] ?? null;
        }
        unset($item);

        View::render('customers/index', [
            'result' => $result,
            'customColumns' => $customColumns,
            'engineerOptions' => $engineers->names(),
            'facets' => $repository->facets(),
            'filters' => $viewFilters,
            'companies' => $companies,
            'companyGroups' => $companyGroups,
            'customerOptions' => can('manage_companies') ? $repository->listForSelection() : [],
            'restrictedToCompany' => $restrictedCompanyId !== null,
            'pageTitle' => 'ข้อมูลลูกค้า',
        ]);
    }

    public function show(string $id): void
    {
        $customer = (new CustomerRepository())->find($id);
        if (!$customer) {
            http_response_code(404);
            exit('ไม่พบข้อมูลลูกค้า');
        }
        $this->assertCompanyAccess($id);
        $customer['engineer'] = (new EngineerRepository())->valueFor((int) $id);
        View::render('customers/show', ['customer' => $customer, 'history' => (new LogRepository())->customerHistory($id), 'pageTitle' => 'รายละเอียดลูกค้า']);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->customerInput();
        if ($data['name'] === '') {
            flash('error', 'กรุณาระบุชื่อลูกค้า');
            redirect('/customers');
        }
        $data['updated_by'] = Auth::id();
        $id = (new CustomerRepository())->create($data);
        $savedCustomer = (new CustomerRepository())->find($id);
        if ($savedCustomer) {
            (new MailService())->sendCustomerSavedToAdmins($savedCustomer, Auth::user(), true);
        }
        (new ActivityLogger())->log('create', 'customer', $id, 'เพิ่มข้อมูลลูกค้า', null, $data);
        flash('success', 'เพิ่มข้อมูลลูกค้าเรียบร้อย');
        redirect('/customers/' . rawurlencode($id));
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CustomerRepository();
        $old = $repository->find($id);
        if (!$old) {
            http_response_code(404);
            exit('ไม่พบข้อมูลลูกค้า');
        }
        $this->assertCompanyAccess($id);
        $data = $this->trackingInput($old);
        $data['updated_by'] = Auth::id();
        $repository->update($id, $data);
        $customValues = $this->saveCustomValues((int) $id);
        $engineerValue = $this->saveEngineer((int) $id);
        $savedCustomer = $repository->find($id);
        if ($savedCustomer) {
            (new MailService())->sendCustomerSavedToAdmins($savedCustomer, Auth::user(), false);
        }
        $trackedFields = array_keys($data);
        $oldValues = array_intersect_key($old, array_flip($trackedFields));
        (new ActivityLogger())->log('update', 'customer', $id, 'แก้ไขข้อมูลงานติดตั้ง', $oldValues, $data);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อย', 'customer' => ($repository->find($id) ?? []) + $customValues + $engineerValue], JSON_UNESCAPED_UNICODE);
            return;
        }
        flash('success', 'บันทึกข้อมูลเรียบร้อย');
        $returnTo = (string) ($_POST['_return_to'] ?? 'detail');
        if ($returnTo === 'table') {
            $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
            if ($returnQuery !== '' && str_starts_with($returnQuery, '?') && strlen($returnQuery) <= 2000) {
                redirect('/customers' . $returnQuery);
            }
            $search = trim((string) ($_POST['return_search'] ?? ''));
            redirect('/customers' . ($search !== '' ? '?search=' . rawurlencode($search) : ''));
        }
        redirect('/customers/' . rawurlencode($id));
    }

    /**
     * Save the "วิศวกร" dropdown when it was posted, and return the stored value as ['engineer' => ...] so the
     * table can repaint the cell. A request that does not carry the field leaves the saved engineer alone.
     */
    private function saveEngineer(int $customerId): array
    {
        $engineers = new EngineerRepository();
        if (!array_key_exists('engineer', $_POST) || !$this->canEditField('engineer')) {
            return ['engineer' => $engineers->valueFor($customerId)];
        }
        $value = mb_substr(trim((string) $_POST['engineer']), 0, EngineerRepository::MAX_LENGTH);
        $engineers->saveValue($customerId, $value);
        return ['engineer' => $value === '' ? null : $value];
    }

    /**
     * Save the custom-column fields posted from the edit modal (custom[<column id>] => value) for the company
     * given in custom_company_id, and return every custom column's resolved value (formulas recomputed).
     */
    private function saveCustomValues(int $customerId): array
    {
        $companyId = (int) ($_POST['custom_company_id'] ?? 0);
        if ($companyId <= 0) {
            return [];
        }
        $customRepository = new CustomColumnRepository();
        $columns = $customRepository->forCompany($companyId);
        if ($columns === []) {
            return [];
        }
        $posted = is_array($_POST['custom'] ?? null) ? $_POST['custom'] : [];
        foreach ($columns as $column) {
            $isFormula = $column['type'] === 'date' && !empty($column['formula_source']);
            if ($isFormula || !array_key_exists((string) $column['id'], $posted)) {
                continue;
            }
            $value = trim((string) $posted[(string) $column['id']]);
            if ($column['type'] === 'date') {
                $value = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
            } elseif ($column['type'] === 'number') {
                $value = str_replace([',', ' '], '', $value);
                // Plain decimals only (no exponent), at most 15 digits before the point and 6 after; ".5" is kept as "0.5".
                $value = preg_replace('/^(-?)\./', '${1}0.', $value);
                $value = preg_match('/^-?\d{1,15}(\.\d{1,6})?$/', $value) ? $value : '';
            } elseif (($options = CustomColumnRepository::options($column)) !== []) {
                $value = in_array($value, $options, true) ? $value : '';
            } else {
                $value = mb_substr($value, 0, 5000);
            }
            $customRepository->saveValue($customerId, (int) $column['id'], $value);
        }
        $row = (new CustomerRepository())->find((string) $customerId) ?? [];
        $values = $customRepository->valuesFor([$customerId], array_column($columns, 'id'));
        return CustomColumnRepository::resolve($columns, $row, $values[$customerId] ?? []);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CustomerRepository();
        $old = $repository->find($id);
        if (!$old) {
            http_response_code(404);
            exit('ไม่พบข้อมูลลูกค้า');
        }
        $this->assertCompanyAccess($id);
        $repository->delete($id);
        (new ActivityLogger())->log('delete', 'customer', $id, 'ลบข้อมูลลูกค้า', $old, null);
        flash('success', 'ลบข้อมูลลูกค้าเรียบร้อย');
        redirect('/customers');
    }

    public function export(): void
    {
        $format = ($_GET['format'] ?? 'csv') === 'excel' ? 'excel' : 'csv';
        $rows = (new CustomerRepository())->filtered($_GET);
        (new ActivityLogger())->log('export', 'customer', null, 'Export ' . strtoupper($format) . ' จำนวน ' . count($rows) . ' รายการ');
        $headers = ['ลำดับ','รายชื่อลูกค้า','Zone','ขนาดเครื่อง','วันที่ส่ง PO','กำหนดหลังจัดส่ง PO','นัดติดตั้ง 1','ติดตั้ง 1','นัดติดตั้ง 2','ติดตั้ง 2','นัดติดตั้ง 3','ครบกำหนด 60 วัน','วันช้า/เร็ว','Status','หมายเหตุทางฝ่ายก่อสร้าง','สถานะงาน','หมายเหตุทาง CAP+'];
        $fields = ['name','zone','machine_size','po_sent_date','po_due_date','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','due_60_date','days_early_late','status','construction_notes','work_status','cap_notes','installation_date_3'];
        $headers[] = 'วันที่เข้าติดตั้งครั้งที่ 3';
        // The engineer of each row comes from its own table, so it is merged in and exported as a last column.
        $engineerValues = (new EngineerRepository())->valuesFor(array_column($rows, 'id'));
        foreach ($rows as &$exportRow) {
            $exportRow['engineer'] = $engineerValues[(int) $exportRow['id']] ?? '';
        }
        unset($exportRow);
        $fields[] = 'engineer';
        $headers[] = 'วิศวกร';
        $filename = 'customers-' . date('Ymd-His');
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header("Content-Disposition: attachment; filename={$filename}.csv");
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $index => $row) {
                $values = [$index + 1];
                foreach ($fields as $field) {
                    $value = (string) ($row[$field] ?? '');
                    $values[] = preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
                }
                fputcsv($output, $values);
            }
            fclose($output);
            return;
        }
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header("Content-Disposition: attachment; filename={$filename}.xls");
        echo "\xEF\xBB\xBF<table border=\"1\"><tr>";
        foreach ($headers as $header) echo '<th>' . e($header) . '</th>';
        echo '</tr>';
        foreach ($rows as $index => $row) {
            echo '<tr><td>' . ($index + 1) . '</td>';
            foreach ($fields as $field) echo '<td>' . e($row[$field] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    /** Blocks a user confined to one company from viewing/editing/deleting another company's customer directly by URL. */
    private function assertCompanyAccess(string $customerId): void
    {
        $restrictedCompanyId = Auth::restrictedCompanyId();
        if ($restrictedCompanyId === null) {
            return;
        }
        $company = (new CompanyRepository())->find($restrictedCompanyId);
        $isDefault = $company !== null && !empty($company['is_default']);
        if (!(new CompanyRepository())->customerBelongs($restrictedCompanyId, $customerId, $isDefault)) {
            http_response_code(403);
            exit('403 - คุณไม่มีสิทธิ์เข้าถึงข้อมูลลูกค้ารายนี้');
        }
    }

    private function customerInput(): array
    {
        $data = [];
        foreach (['name', 'zone', 'machine_size', 'status', 'notes', 'construction_notes', 'work_status', 'cap_notes'] as $field) {
            $data[$field] = mb_substr(trim((string) ($_POST[$field] ?? '')), 0, $field === 'notes' ? 5000 : 150);
        }
        $data['construction_notes'] = mb_substr(trim((string) ($_POST['construction_notes'] ?? '')), 0, 5000);
        $data['cap_notes'] = mb_substr(trim((string) ($_POST['cap_notes'] ?? '')), 0, 5000);
        $data['status'] = $data['status'] ?: 'STATUS';
        foreach (['po_sent_date','po_due_date','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date'] as $field) {
            $value = (string) ($_POST[$field] ?? '');
            $data[$field] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
        }
        // Both calculated deadlines use the PO due date as the source date (+61 days).
        if ($this->canEditField('po_sent_date') && array_key_exists('po_sent_date', $_POST)) {
            if ($data['po_sent_date'] === null) {
                $data['po_due_date'] = null;
                $data['due_60_date'] = null;
            } else {
                $sourceDate = new \DateTimeImmutable($data['po_sent_date']);
                $data['po_due_date'] = $sourceDate->modify('+61 days')->format('Y-m-d');
                $data['due_60_date'] = $sourceDate->modify('+60 days')->format('Y-m-d');
            }
        }
        return $data;
    }

    private function trackingInput(array $existing): array
    {
        $data = [
            'name' => $this->fieldValue('name', $existing['name'] ?? null, 255),
            'zone' => $this->zoneValue($existing['zone'] ?? null),
            'machine_size' => $this->fieldValue('machine_size', $existing['machine_size'] ?? null, 100),
            'status' => $this->statusValue($existing['status'] ?? 'STATUS'),
            'notes' => $this->fieldValue('notes', $existing['notes'] ?? null, 5000),
            'construction_notes' => $this->fieldValue('construction_notes', $existing['construction_notes'] ?? null, 5000),
            'work_status' => $this->fieldValue('work_status', $existing['work_status'] ?? null, 255),
            'cap_notes' => $this->fieldValue('cap_notes', $existing['cap_notes'] ?? null, 5000),
        ];
        foreach (['po_sent_date','po_due_date','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date'] as $field) {
            if (!$this->canEditField($field) || !array_key_exists($field, $_POST)) {
                $data[$field] = $existing[$field] ?? null;
                continue;
            }
            $value = trim((string) $_POST[$field]);
            $data[$field] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
        }
        // PO deadlines are calculated only in the update path used by the table Modal.
        if ($this->canEditField('po_sent_date') && array_key_exists('po_sent_date', $_POST)) {
            if ($data['po_sent_date'] === null) {
                $data['po_due_date'] = null;
                $data['due_60_date'] = null;
            } else {
                $sourceDate = new \DateTimeImmutable($data['po_sent_date']);
                $data['po_due_date'] = $sourceDate->modify('+61 days')->format('Y-m-d');
                $data['due_60_date'] = $sourceDate->modify('+60 days')->format('Y-m-d');
            }
        }
        return $data;
    }

    private function fieldValue(string $field, mixed $existing, int $length): ?string
    {
        if (!$this->canEditField($field) || !array_key_exists($field, $_POST)) {
            return $existing !== null && $existing !== '' ? (string) $existing : null;
        }
        return mb_substr(trim((string) $_POST[$field]), 0, $length) ?: null;
    }

    private function zoneValue(mixed $existing): ?string
    {
        $value = $this->fieldValue('zone', $existing, 2);

        return $value !== null && in_array($value, config('customer.zones', []), true)
            ? $value
            : ($existing !== null && $existing !== '' ? (string) $existing : null);
    }

    private function statusValue(mixed $existing): string
    {
        $value = $this->fieldValue('status', $existing, 100);
        $allowed = config('customer.status_options', []);

        if ($value !== null && in_array($value, $allowed, true)) {
            return $value;
        }

        return $existing !== null && $existing !== '' ? (string) $existing : 'STATUS';
    }

    private function canEditField(string $field): bool
    {
        return \App\Core\Auth::canEditCustomerField($field);
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
