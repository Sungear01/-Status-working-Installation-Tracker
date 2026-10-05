<?php
$query = static fn(array $changes = []): string => '?' . http_build_query(array_merge($filters, $changes));
$sortLink = static function (string $column) use ($filters, $query): string {
    $direction = (($filters['sort'] ?? '') === $column && ($filters['direction'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
    return $query(['sort' => $column, 'direction' => $direction, 'page' => 1]);
};
$columns = [
    'name' => 'รายชื่อลูกค้า', 'zone' => 'Zone', 'machine_size' => 'ขนาดเครื่อง', 'po_sent_date' => 'วันที่ส่ง PO',
    'po_due_date' => 'กำหนดหลังจัดส่ง PO 60 วัน ครั้งที่ 1', 'engineer' => 'วิศวกร', 'engineer_appointment_1' => 'วิศวกรนัดครั้งที่ 1', 'installation_date_1' => 'ติดตั้งครั้งที่ 1',
    'engineer_appointment_2' => 'วิศวกรนัดครั้งที่ 2', 'installation_date_2' => 'ติดตั้งครั้งที่ 2', 'engineer_appointment_3' => 'วิศวกรนัดครั้งที่ 3',
    'due_60_date' => 'ครบกำหนด 60 วัน ครั้งที่ 2', 'days_early_late' => 'วันช้า/เร็ว', 'status' => 'Status',
    'construction_notes' => 'หมายเหตุทางฝ่ายก่อสร้าง', 'work_status' => 'สถานะงาน', 'cap_notes' => 'หมายเหตุทาง CAP+', 'actions' => 'จัดการ',
];
?>
<?php $columns['installation_date_3'] = 'ติดตั้งครั้งที่ 3'; ?>
<?php $installationLabel = $columns['installation_date_3']; unset($columns['installation_date_3']); $columns = array_merge(array_slice($columns, 0, 11, true), ['installation_date_3' => $installationLabel], array_slice($columns, 11, null, true)); ?>
<?php
// A single-company tab whose layout is 'compact' shows only the 5 core columns; group / "all" tabs keep the full table.
$compactTable = false;
$compactCompany = null;
if (($filters['group_id'] ?? '') === '') {
    foreach ($companies as $company) {
        if ((string) $company['id'] === (string) ($filters['company_id'] ?? '')) {
            $compactTable = ($company['table_layout'] ?? 'full') === 'compact';
            $compactCompany = $compactTable ? $company : null;
            break;
        }
    }
}
// Every column a compact table can pick from (in display order); the 4 defaults use shorter labels.
$allColumns = array_merge($columns, ['po_due_date' => 'หลังจัดส่ง PO 60 วัน']);
// Custom columns go before the actions column, which always stays last.
$customColumns = $customColumns ?? [];
$customByKey = [];
// Colour rules per custom column key; app.js paints the matching cells, so they stay right after inline edits.
$colorRuleMap = [];
foreach ($customColumns as $customColumn) {
    $customByKey['custom_' . $customColumn['id']] = $customColumn;
    if (($customRules = App\Repositories\CustomColumnRepository::colorRules($customColumn)) !== []) {
        $colorRuleMap['custom_' . $customColumn['id']] = $customRules;
    }
}
$actionsLabel = $allColumns['actions'];
unset($allColumns['actions']);
$allColumns += array_map(static fn (array $c): string => $c['label'], $customByKey);
$allColumns['actions'] = $actionsLabel;
if ($compactTable) {
    $visibleKeys = json_decode((string) ($compactCompany['table_columns'] ?? ''), true);
    if (!is_array($visibleKeys) || $visibleKeys === []) {
        $visibleKeys = ['name', 'po_sent_date', 'po_due_date', 'engineer'];
    }
    $columns = array_intersect_key($allColumns, array_flip($visibleKeys));
}
// The edit modal needs $compactTable and $columns, so it is included once the visible columns are known.
require base_path('resources/views/customers/_edit_modal.php');
$daysText = static function ($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    $days = (int) $value;
    return $days < 0 ? 'ช้า ' . abs($days) . ' วัน' : 'เร็ว/เหลือ ' . $days . ' วัน';
};
?>
<div class="page-heading"><div><span class="eyebrow text-primary">CUSTOMER OPERATIONS</span><h2>รายการติดตามงานติดตั้ง</h2><p>พบข้อมูลทั้งหมด <?= number_format($result['total']) ?> รายการ · ชื่อลูกค้าจาก customers และ Zone จาก worksheets</p></div><div class="d-flex gap-2"><?php if (can('create_customer') && !config('customer.master_managed_externally')): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomer" <?= !config('customer.writes_enabled')?'disabled title="ระบบอยู่ในโหมดอ่านอย่างเดียว"':'' ?>><i class="fa-solid fa-plus me-1"></i>เพิ่มลูกค้า</button><?php endif; ?><?php if (can('export_customer')): ?><a class="btn btn-outline-primary" href="<?= e(url('/customers/export') . $query(['format' => 'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a><a class="btn btn-success" href="<?= e(url('/customers/export') . $query(['format' => 'excel'])) ?>"><i class="fa-solid fa-file-excel me-1"></i>Excel</a><?php endif; ?></div></div>
<section class="panel mb-4 company-tabs">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (empty($restrictedToCompany)): ?><a href="<?= e(url('/customers') . $query(['company_id' => null, 'group_id' => null, 'page' => 1])) ?>" class="btn btn-sm <?= ($filters['company_id'] ?? '') === '' && ($filters['group_id'] ?? '') === '' ? 'btn-primary' : 'btn-outline-primary' ?>">ทั้งหมด</a><?php endif; ?>
        <?php foreach ($companies as $company): ?>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= e(url('/customers') . $query(['company_id' => $company['id'], 'group_id' => null, 'page' => 1])) ?>" class="btn <?= ($filters['group_id'] ?? '') === '' && (string) ($filters['company_id'] ?? '') === (string) $company['id'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e($company['name']) ?></a>
                <?php if (can('manage_companies')): ?><button type="button" class="btn btn-outline-secondary edit-company-button" data-company="<?= e(json_encode($company, JSON_UNESCAPED_UNICODE)) ?>" title="แก้ไขบริษัท <?= e($company['name']) ?>"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($companyGroups ?? [] as $group): ?>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= e(url('/customers') . $query(['company_id' => null, 'group_id' => $group['id'], 'page' => 1])) ?>" class="btn <?= (string) ($filters['group_id'] ?? '') === (string) $group['id'] ? 'btn-primary' : 'btn-outline-primary' ?>" title="รวมบริษัท <?= e($group['label']) ?>"><?= e($group['label']) ?></a>
                <?php if (can('manage_companies')): ?><button type="button" class="btn btn-outline-secondary edit-group-button" data-group="<?= e(json_encode($group, JSON_UNESCAPED_UNICODE)) ?>" title="แก้ไขกลุ่ม <?= e($group['label']) ?>"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (can('manage_companies')): ?><button type="button" class="btn btn-sm btn-primary" id="addCompanyBtn" title="เพิ่มบริษัท"><i class="fa-solid fa-plus"></i></button><?php endif; ?>
    </div>
</section>
<?php if (can('manage_companies')): ?>
<div class="modal fade" id="companyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= e(url('/companies')) ?>" id="companyForm">
                <?= csrf_field() ?>
                <div class="modal-header"><h2 class="modal-title fs-5" id="companyModalTitle">เพิ่มบริษัท</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อบริษัท *</label>
                            <select class="form-select" name="name[]" id="companyNameInput" multiple>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= e($company['name']) ?>"><?= e($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-secondary">เลือกบริษัทที่มีอยู่ หรือพิมพ์ชื่อใหม่แล้วกด Enter เพื่อเพิ่ม (เลือก/เพิ่มได้มากกว่า 1 บริษัท)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ปีที่เริ่มดำเนินงาน</label>
                            <select class="form-select" name="start_year" id="companyStartYear">
                                <option value="">-- เลือกปี --</option>
                                <?php $currentBuddhistYear = (int) date('Y') + 543; for ($year = $currentBuddhistYear + 1; $year >= $currentBuddhistYear - 15; $year--): ?>
                                    <option value="<?= $year ?>"><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-secondary">เลือกปีเพื่อติ๊กลูกค้าที่เซ็นสัญญาปีนั้นให้อัตโนมัติ (หรือระบบจะเติมปีให้เองเมื่อติ๊กลูกค้าด้านล่าง)</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ลูกค้าเพิ่มเติมในบริษัทนี้</label>
                            <div class="d-flex gap-2 mb-2">
                                <input type="text" class="form-control" id="companyCustomerSearch" placeholder="ค้นหารายชื่อลูกค้า...">
                                <button type="button" class="btn btn-outline-secondary text-nowrap" id="selectAllCustomers">เลือกทั้งหมด</button>
                                <button type="button" class="btn btn-outline-secondary text-nowrap" id="clearAllCustomers">ล้างทั้งหมด</button>
                            </div>
                            <div class="border rounded p-2" style="max-height:260px;overflow-y:auto" id="companyCustomerList">
                                <?php foreach ($customerOptions as $option): $signYear = $option['contract_sign_date'] ? ((int) date('Y', strtotime((string) $option['contract_sign_date'])) + 543) : ''; ?>
                                    <div class="form-check company-customer-option" data-name="<?= e(mb_strtolower((string) $option['name'])) ?>">
                                        <input class="form-check-input" type="checkbox" value="<?= e($option['id']) ?>" id="cc_<?= e($option['id']) ?>" data-po-year="<?= e((string) $signYear) ?>">
                                        <label class="form-check-label" for="cc_<?= e($option['id']) ?>"><?= e($option['name']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="customer_ids" id="companyCustomerIdsHidden">
                        </div>
                    </div>
                </div>
            </form>
            <div class="modal-footer">
                <form method="post" id="companyDeleteForm" class="d-none me-auto" data-confirm="ยืนยันการลบบริษัทนี้?" data-confirm-detail="รายชื่อลูกค้าที่ผูกกับบริษัทนี้จะถูกตัดความเชื่อมโยงออก และไม่สามารถกู้คืนได้">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-trash me-1"></i>ลบบริษัท</button>
                </form>
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary" type="submit" form="companyForm"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="groupForm">
                <?= csrf_field() ?>
                <div class="modal-header"><h2 class="modal-title fs-5">แก้ไขกลุ่มบริษัท</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">บริษัทในกลุ่ม *</label>
                    <div class="border rounded p-2" style="max-height:260px;overflow-y:auto">
                        <?php foreach ($companies as $company): ?>
                            <div class="form-check">
                                <input class="form-check-input group-company-checkbox" type="checkbox" name="company_ids[]" value="<?= e($company['id']) ?>" id="gc_<?= e($company['id']) ?>">
                                <label class="form-check-label" for="gc_<?= e($company['id']) ?>"><?= e($company['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-secondary">เลือกอย่างน้อย 2 บริษัท</small>
                </div>
            </form>
            <div class="modal-footer">
                <form method="post" id="groupDeleteForm" class="me-auto" data-confirm="ยืนยันการลบกลุ่มบริษัทนี้?" data-confirm-detail="จะลบเฉพาะแท็บรวม ข้อมูลบริษัทและลูกค้าจะไม่ถูกลบ">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-trash me-1"></i>ลบกลุ่ม</button>
                </form>
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary" type="submit" form="groupForm"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modalEl = document.getElementById('groupModal');
    if (!modalEl) return;
    var form = document.getElementById('groupForm');
    var deleteForm = document.getElementById('groupDeleteForm');
    var boxes = modalEl.querySelectorAll('.group-company-checkbox');
    document.querySelectorAll('.edit-group-button').forEach(function (button) {
        button.addEventListener('click', function () {
            var group = JSON.parse(button.dataset.group);
            var ids = group.company_ids.map(String);
            form.action = <?= json_encode(url('/company-groups')) ?> + '/' + group.id;
            deleteForm.action = form.action + '/delete';
            boxes.forEach(function (box) { box.checked = ids.indexOf(box.value) !== -1; });
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
    form.addEventListener('submit', function (event) {
        if (modalEl.querySelectorAll('.group-company-checkbox:checked').length < 2) {
            event.preventDefault();
            alert('กรุณาเลือกอย่างน้อย 2 บริษัท');
        }
    });
})();
</script>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    var modalEl = document.getElementById('companyModal');
    if (!modalEl) return;
    var form = document.getElementById('companyForm');
    var title = document.getElementById('companyModalTitle');
    var nameInput = document.getElementById('companyNameInput');
    var nameSelect = new TomSelect(nameInput, {
        create: true,
        persist: false,
        createOnBlur: true,
        maxItems: null,
        placeholder: 'เลือกหรือพิมพ์ชื่อบริษัท',
        // Default fuzzy scoring can rank an unrelated existing company above the
        // "add <text>" choice (e.g. typing a new name that loosely resembles one
        // already in the list), so pressing Enter would silently select that
        // existing company instead of creating the new one. Require the typed
        // text to actually be a substring of an option before it can match...
        score: function (search) {
            var term = search.toLowerCase();
            return function (item) {
                return item.text.toLowerCase().indexOf(term) !== -1 ? 1 : 0;
            };
        },
        // ...and make the "add <text>" choice the default highlighted option so
        // Enter creates the typed name instead of picking whatever option was
        // last highlighted before the dropdown re-filtered.
        addPrecedence: true,
    });
    var startYear = document.getElementById('companyStartYear');
    var search = document.getElementById('companyCustomerSearch');
    var checkboxes = form.querySelectorAll('#companyCustomerList input[type="checkbox"]');
    var customerIdsHidden = document.getElementById('companyCustomerIdsHidden');
    var baseAction = <?= json_encode(url('/companies'), JSON_UNESCAPED_SLASHES) ?>;
    var deleteForm = document.getElementById('companyDeleteForm');

    // Hundreds of individual customer_ids[] fields can exceed PHP's max_input_vars
    // and get silently truncated, so the checked ids are collected into one field instead.
    function syncCustomerIdsHidden() {
        var ids = [];
        checkboxes.forEach(function (box) { if (box.checked) ids.push(box.value); });
        customerIdsHidden.value = ids.join(',');
    }
    form.addEventListener('submit', syncCustomerIdsHidden);

    function resetForm() {
        form.reset();
        form.action = baseAction;
        title.textContent = 'เพิ่มบริษัท';
        nameSelect.clear();
        nameSelect.settings.maxItems = null;
        checkboxes.forEach(function (box) { box.checked = false; });
        if (search) search.value = '';
        deleteForm.classList.add('d-none');
        filterCustomerList();
    }

    function openForCreate() {
        resetForm();
        new bootstrap.Modal(modalEl).show();
    }

    function openForEdit(company) {
        resetForm();
        form.action = baseAction + '/' + company.id;
        title.textContent = 'แก้ไขบริษัท';
        nameSelect.settings.maxItems = null;
        if (company.name && !nameSelect.options[company.name]) {
            nameSelect.addOption({ value: company.name, text: company.name });
        }
        nameSelect.setValue([company.name || '']);
        startYear.value = company.start_year || '';
        var ids = (company.customer_ids || []).map(String);
        checkboxes.forEach(function (box) {
            box.checked = ids.indexOf(box.value) !== -1;
        });
        selectCustomersByYear();
        filterCustomerList();
        if (Number(company.is_default) !== 1) {
            deleteForm.action = baseAction + '/' + company.id + '/delete';
            deleteForm.classList.remove('d-none');
        }
        new bootstrap.Modal(modalEl).show();
    }

    function filterCustomerList() {
        var term = (search.value || '').trim().toLowerCase();
        document.querySelectorAll('.company-customer-option').forEach(function (row) {
            row.style.display = !term || row.dataset.name.indexOf(term) !== -1 ? '' : 'none';
        });
    }

    // Picking a year here bulk-checks every customer whose contract was signed that year,
    // so the admin doesn't have to search each name one by one.
    function selectCustomersByYear() {
        var year = startYear.value;
        if (!year) return;
        checkboxes.forEach(function (box) {
            if (box.dataset.poYear === year) box.checked = true;
        });
    }

    var addBtn = document.getElementById('addCompanyBtn');
    if (addBtn) addBtn.addEventListener('click', openForCreate);
    document.querySelectorAll('.edit-company-button').forEach(function (button) {
        button.addEventListener('click', function () {
            openForEdit(JSON.parse(button.dataset.company));
        });
    });
    if (search) search.addEventListener('input', filterCustomerList);
    startYear.addEventListener('change', selectCustomersByYear);

    var selectAllBtn = document.getElementById('selectAllCustomers');
    var clearAllBtn = document.getElementById('clearAllCustomers');
    if (selectAllBtn) selectAllBtn.addEventListener('click', function () {
        document.querySelectorAll('.company-customer-option').forEach(function (row) {
            if (row.style.display !== 'none') row.querySelector('input[type="checkbox"]').checked = true;
        });
    });
    if (clearAllBtn) clearAllBtn.addEventListener('click', function () {
        checkboxes.forEach(function (box) { box.checked = false; });
    });

    form.addEventListener('submit', function (event) {
        var pending = nameSelect.control_input ? nameSelect.control_input.value.trim() : '';
        if (pending !== '') {
            nameSelect.createItem(pending);
        }
        if (nameSelect.items.length === 0) {
            event.preventDefault();
            alert('กรุณาระบุชื่อบริษัทอย่างน้อย 1 ชื่อ');
            nameSelect.focus();
        }
    });
})();
</script>
<?php endif; ?>
<section class="panel mb-4">
    <form method="get" action="<?= e(url('/customers')) ?>" class="filter-grid" id="customerFilters">
        <div class="filter-search"><label for="search" class="form-label">ค้นหา</label><div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="ชื่อลูกค้า, Zone หรือ Status" data-realtime-search></div></div>
        <div><label for="zone" class="form-label">Zone</label><select class="form-select" id="zone" name="zone" data-auto-submit><option value="">ทั้งหมด</option><?php foreach ($facets['zone'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['zone'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
        <div><label for="machine_size" class="form-label">ขนาดเครื่อง</label><select class="form-select" id="machine_size" name="machine_size" data-auto-submit><option value="">ทั้งหมด</option><?php foreach ($facets['machine_size'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['machine_size'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
        <div><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status" data-auto-submit><option value="">ทั้งหมด</option><?php foreach ($facets['status'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
        <div><label for="date_from" class="form-label">วันที่ส่ง PO จาก</label><input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit></div>
        <div><label for="date_to" class="form-label">ถึงวันที่</label><input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>" data-auto-submit></div>
        <div class="filter-actions"><a href="<?= e(url('/customers')) ?>" class="btn btn-light" title="ล้างตัวกรอง"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
</section>
<?php if(can('create_customer')): ?><div class="modal fade" id="createCustomer" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content"><form method="post" action="<?= e(url('/customers')) ?>"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title fs-5">เพิ่มข้อมูลลูกค้า</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">รายชื่อลูกค้า *</label><input class="form-control" name="name" maxlength="150" required></div><div class="col-md-3"><label class="form-label">Zone</label><input class="form-control" name="zone" maxlength="150"></div><div class="col-md-3"><label class="form-label">ขนาดเครื่อง</label><input class="form-control" name="machine_size" maxlength="150"></div><?php $dateLabels=['po_sent_date'=>'วันที่ส่ง PO','po_due_date'=>'กำหนดหลังจัดส่ง PO','engineer_appointment_1'=>'วิศวกรนัดครั้งที่ 1','installation_date_1'=>'ติดตั้งครั้งที่ 1','engineer_appointment_2'=>'วิศวกรนัดครั้งที่ 2','installation_date_2'=>'ติดตั้งครั้งที่ 2','engineer_appointment_3'=>'วิศวกรนัดครั้งที่ 3','due_60_date'=>'ครบกำหนด 60 วัน']; foreach($dateLabels as $field=>$label): ?><div class="col-md-3"><label class="form-label"><?= e($label) ?></label><input type="date" class="form-control" name="<?= e($field) ?>"></div><?php endforeach; ?><div class="col-md-4"><label class="form-label">Status</label><input class="form-control" name="status" maxlength="150"></div><div class="col-md-8"><label class="form-label">หมายเหตุ</label><textarea class="form-control" name="notes" rows="2" maxlength="5000"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">บันทึกข้อมูล</button></div></form></div></div></div><?php endif; ?>
<?php if ($compactTable && can('manage_companies')): $companyBase = '/companies/' . (int) $compactCompany['id'];
    /**
     * Date columns a formula may point at, as [key => label]: the built-in ones plus this company's own date
     * columns. A column that counts days holds a number, so it is left out, and when editing column $beforeId
     * only earlier columns are offered, which keeps the columns resolvable in id order.
     */
    $formulaSources = static function (?int $beforeId = null) use ($customColumns): array {
        $sources = App\Repositories\CustomColumnRepository::DATE_SOURCES;
        foreach ($customColumns as $sourceColumn) {
            if ($sourceColumn['type'] !== 'date' || App\Repositories\CustomColumnRepository::isDifference($sourceColumn)) {
                continue;
            }
            if ($beforeId !== null && (int) $sourceColumn['id'] >= $beforeId) {
                continue;
            }
            $sources['custom_' . (int) $sourceColumn['id']] = (string) $sourceColumn['label'];
        }
        return $sources;
    };
    /**
     * The optional colour rules of a column form, hidden behind a checkbox. Each rule reads as a sentence
     * ("ถ้าช่องนี้ <condition> <value> → ระบายสี <colour>"); only the first row shows until "เพิ่มเงื่อนไข" is pressed.
     * Each condition lists the kinds of value it works on (data-kinds), so the script below offers only the ones
     * that fit the column's type.
     */
    $colorRuleFields = static function (string $idSuffix, array $rules = []): string {
        $defaultColors = ['red', 'blue', 'green'];
        $html = '<div class="col-12"><div class="form-check"><input class="form-check-input js-use-colors" type="checkbox" name="use_color_rules" value="1" id="useColors' . e($idSuffix) . '"' . ($rules !== [] ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="useColors' . e($idSuffix) . '">ระบายสีช่องอัตโนมัติตามค่าที่กรอก <span class="text-secondary">(ไม่บังคับ)</span></label></div>'
            . '<div class="js-color-fields mt-2 p-3 border rounded-3 bg-light"><div class="d-flex flex-column gap-2">';
        for ($index = 0; $index < App\Repositories\CustomColumnRepository::MAX_RULES; $index++) {
            $rule = $rules[$index] ?? null;
            $shown = $index === 0 || $rule !== null;
            $html .= '<div class="d-flex flex-wrap align-items-center gap-2 js-color-rule' . ($shown ? '' : ' d-none') . '"><span>ถ้าช่องนี้</span>'
                . '<select class="form-select w-auto js-rule-op" name="rule_op[]" aria-label="เงื่อนไขที่ ' . ($index + 1) . '">';
            foreach (App\Repositories\CustomColumnRepository::RULE_OPS as $op => $opLabel) {
                $kinds = array_keys(array_filter(App\Repositories\CustomColumnRepository::RULE_OPS_BY_KIND, static fn (array $ops): bool => in_array($op, $ops, true)));
                $html .= '<option value="' . e($op) . '" data-kinds="' . e(implode(' ', $kinds)) . '"'
                    . (in_array($op, App\Repositories\CustomColumnRepository::RULE_OPS_WITHOUT_VALUE, true) ? ' data-no-value="1"' : '')
                    . (($rule['op'] ?? '') === $op ? ' selected' : '') . '>' . e($opLabel) . '</option>';
            }
            $html .= '</select><input class="form-control js-rule-value" style="width:150px" name="rule_value[]" maxlength="150" value="' . e((string) ($rule['value'] ?? '')) . '" aria-label="ค่าที่ใช้เทียบ">'
                . '<span>→ ระบายสี</span><select class="form-select w-auto js-rule-color" name="rule_color[]" aria-label="สีของเงื่อนไขที่ ' . ($index + 1) . '">';
            $selectedColor = $rule['color'] ?? $defaultColors[$index % count($defaultColors)];
            foreach (App\Repositories\CustomColumnRepository::RULE_COLORS as $color => $colorLabel) {
                $html .= '<option value="' . e($color) . '"' . ($selectedColor === $color ? ' selected' : '') . '>' . e($colorLabel) . '</option>';
            }
            $html .= '</select><span class="rule-preview js-rule-preview">ตัวอย่างสี</span>'
                . ($index > 0 ? '<button type="button" class="btn btn-sm btn-link text-danger js-rule-remove" title="ลบเงื่อนไขนี้"><i class="fa-solid fa-xmark"></i></button>' : '')
                . '</div>';
        }
        return $html . '</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2 js-rule-add"><i class="fa-solid fa-plus me-1"></i>เพิ่มเงื่อนไข</button>'
            . '<small class="d-block text-secondary mt-2 js-rule-example"></small></div></div>';
    }; ?>
<div class="modal fade" id="tableColumnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5">คอลัมน์ตาราง <?= e($compactCompany['name']) ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <h3 class="fs-6 mb-2"><i class="fa-solid fa-user-gear me-1"></i>รายชื่อวิศวกร</h3>
                <form method="post" action="<?= e(url('/engineers')) ?>" class="mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= (int) $compactCompany['id'] ?>">
                    <label class="form-label" for="engineerList">ตัวเลือกของคอลัมน์ "วิศวกร" (บรรทัดละ 1 ชื่อ)</label>
                    <textarea class="form-control" id="engineerList" name="engineers" rows="6" placeholder="เช่น&#10;สมชาย ใจดี&#10;สมหญิง รักงาน"><?= e(implode("
", $engineerOptions ?? [])) ?></textarea>
                    <small class="text-secondary">ใช้ร่วมกันทุกบริษัท · ชื่อที่ลูกค้าเลือกไว้แล้วจะยังคงแสดงอยู่ แม้จะถูกนำออกจากรายการนี้</small>
                    <div class="text-end mt-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึกรายชื่อวิศวกร</button></div>
                </form>
                <hr>
                <?php if ($customColumns): ?>
                    <h3 class="fs-6 mb-3"><i class="fa-solid fa-table-columns me-1"></i>คอลัมน์ที่สร้างเอง</h3>
                    <div class="list-group mb-4">
                        <?php foreach ($customColumns as $custom): $cid = (int) $custom['id'];
                            $customOptions = App\Repositories\CustomColumnRepository::options($custom);
                            $customFormula = App\Repositories\CustomColumnRepository::formulaText($custom, $customColumns); ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold"><?= e($custom['label']) ?></span>
                                    <span class="badge text-bg-light border"><?= $customFormula !== '' ? 'สูตร: ' . e($customFormula) : ($custom['type'] === 'date' ? 'วันที่' : ($custom['type'] === 'number' ? 'ตัวเลข' : ($customOptions !== [] ? 'Dropdown' : 'ข้อความ'))) ?></span>
                                    <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#editColumn<?= $cid ?>" aria-expanded="false" aria-controls="editColumn<?= $cid ?>" title="แก้ไขคอลัมน์ <?= e($custom['label']) ?>"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" form="deleteCustomColumn<?= $cid ?>" title="ลบคอลัมน์ <?= e($custom['label']) ?>"><i class="fa-solid fa-trash"></i></button>
                                </div>
                                <div class="collapse" id="editColumn<?= $cid ?>">
                                    <form method="post" action="<?= e(url($companyBase . '/custom-columns/' . $cid)) ?>" class="row g-3 mt-0" data-column-form>
                                        <?= csrf_field() ?>
                                        <div class="col-md-7"><label class="form-label" for="editLabel<?= $cid ?>">ชื่อคอลัมน์ *</label><input class="form-control" id="editLabel<?= $cid ?>" name="label" maxlength="150" value="<?= e($custom['label']) ?>" required></div>
                                        <div class="col-md-5"><label class="form-label" for="editType<?= $cid ?>">ประเภท</label><select class="form-select js-column-type" id="editType<?= $cid ?>" name="type"><option value="text" <?= $custom['type'] === 'text' ? 'selected' : '' ?>>ข้อความ</option><option value="date" <?= $custom['type'] === 'date' ? 'selected' : '' ?>>วันที่</option><option value="number" <?= $custom['type'] === 'number' ? 'selected' : '' ?>>ตัวเลข</option></select></div>
                                        <div class="col-12 js-options-toggle">
                                            <div class="form-check"><input class="form-check-input js-use-options" type="checkbox" name="use_options" value="1" id="editUseOptions<?= $cid ?>" <?= $customOptions !== [] ? 'checked' : '' ?>><label class="form-check-label" for="editUseOptions<?= $cid ?>">ทำเป็น Dropdown (ให้เลือกจากรายการ แทนการพิมพ์เอง)</label></div>
                                        </div>
                                        <div class="col-12 js-options-fields">
                                            <label class="form-label" for="editOptions<?= $cid ?>">ตัวเลือก (บรรทัดละ 1 ตัวเลือก)</label>
                                            <textarea class="form-control" id="editOptions<?= $cid ?>" name="options" rows="4"><?= e(implode("\n", $customOptions)) ?></textarea>
                                        </div>
                                        <div class="col-12 js-formula-toggle">
                                            <div class="form-check"><input class="form-check-input js-use-formula" type="checkbox" name="use_formula" value="1" id="editUseFormula<?= $cid ?>" <?= $customFormula !== '' ? 'checked' : '' ?>><label class="form-check-label" for="editUseFormula<?= $cid ?>">คำนวณอัตโนมัติจากสูตร (ถ้าไม่ติ๊ก จะกรอกวันที่เองในหน้าแก้ไขข้อมูล)</label></div>
                                        </div>
                                        <div class="col-12 js-formula-fields">
                                            <?php $isDiff = App\Repositories\CustomColumnRepository::isDifference($custom); ?>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <span>=</span>
                                                <select class="form-select w-auto" name="formula_source" aria-label="คอลัมน์ต้นทาง">
                                                    <?php foreach ($formulaSources($cid) as $sourceKey => $sourceLabel): ?><option value="<?= e($sourceKey) ?>" <?= $custom['formula_source'] === $sourceKey ? 'selected' : '' ?>><?= e($sourceLabel) ?></option><?php endforeach; ?>
                                                </select>
                                                <select class="form-select w-auto js-formula-mode" name="formula_mode" aria-label="รูปแบบสูตร">
                                                    <option value="offset" <?= $isDiff ? '' : 'selected' ?>>บวก/ลบ จำนวนวัน</option>
                                                    <option value="diff" <?= $isDiff ? 'selected' : '' ?>>ลบด้วยคอลัมน์วันที่ (ได้จำนวนวัน)</option>
                                                </select>
                                                <span class="d-flex align-items-center gap-2 js-formula-offset">
                                                    <select class="form-select w-auto" name="formula_operator" aria-label="บวกหรือลบ"><option value="+">+ บวก</option><option value="-" <?= (int) $custom['formula_days'] < 0 ? 'selected' : '' ?>>− ลบ</option></select>
                                                    <input type="number" class="form-control" style="width:100px" name="formula_days" min="0" max="3650" value="<?= abs((int) $custom['formula_days']) ?>" aria-label="จำนวนวัน">
                                                    <span>วัน</span>
                                                </span>
                                                <span class="d-flex align-items-center gap-2 js-formula-diff">
                                                    <span>−</span>
                                                    <select class="form-select w-auto" name="formula_target" aria-label="คอลัมน์วันที่ที่นำมาลบ">
                                                        <?php foreach ($formulaSources($cid) as $targetKey => $targetLabel): ?><option value="<?= e($targetKey) ?>" <?= $custom['formula_target'] === $targetKey ? 'selected' : '' ?>><?= e($targetLabel) ?></option><?php endforeach; ?>
                                                    </select>
                                                </span>
                                            </div>
                                            <small class="text-secondary js-formula-hint-offset">อ้างอิงได้เฉพาะคอลัมน์วันที่ที่สร้างไว้ก่อนหน้าคอลัมน์นี้</small>
                                            <small class="text-secondary js-formula-hint-diff">ได้ผลเป็นจำนวนวัน เช่น ครบกำหนด 60 วัน ครั้งที่ 2 − ติดตั้งครั้งที่ 3 · ติดตั้งช้ากว่ากำหนดจะได้เลขติดลบ · ถ้ามีช่องใดว่าง ช่องนี้จะว่าง</small>
                                        </div>
                                        <?= $colorRuleFields((string) $cid, App\Repositories\CustomColumnRepository::colorRules($custom)) ?>
                                        <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึกคอลัมน์</button></div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                <?php endif; ?>
                <form method="post" action="<?= e(url($companyBase . '/custom-columns')) ?>" id="customColumnForm" data-column-form>
                    <?= csrf_field() ?>
                    <h3 class="fs-6 mb-3"><i class="fa-solid fa-plus me-1"></i>สร้างคอลัมน์ใหม่</h3>
                    <div class="row g-3">
                        <div class="col-md-7"><label class="form-label" for="customColumnLabel">ชื่อคอลัมน์ *</label><input class="form-control" id="customColumnLabel" name="label" maxlength="150" required></div>
                        <div class="col-md-5"><label class="form-label" for="customColumnType">ประเภท</label><select class="form-select js-column-type" id="customColumnType" name="type"><option value="text">ข้อความ</option><option value="date">วันที่</option><option value="number">ตัวเลข</option></select></div>
                        <div class="col-12 js-options-toggle" id="customOptionsToggle">
                            <div class="form-check"><input class="form-check-input js-use-options" type="checkbox" name="use_options" value="1" id="customUseOptions"><label class="form-check-label" for="customUseOptions">ทำเป็น Dropdown (ให้เลือกจากรายการ แทนการพิมพ์เอง)</label></div>
                        </div>
                        <div class="col-12 js-options-fields" id="customOptionsFields">
                            <label class="form-label" for="customOptions">ตัวเลือก (บรรทัดละ 1 ตัวเลือก)</label>
                            <textarea class="form-control" id="customOptions" name="options" rows="4" placeholder="เช่น&#10;รอดำเนินการ&#10;กำลังดำเนินการ&#10;เสร็จแล้ว"></textarea>
                        </div>
                        <div class="col-12 js-formula-toggle" id="customFormulaToggle">
                            <div class="form-check"><input class="form-check-input js-use-formula" type="checkbox" name="use_formula" value="1" id="customUseFormula"><label class="form-check-label" for="customUseFormula">คำนวณอัตโนมัติจากสูตร (ถ้าไม่ติ๊ก จะกรอกวันที่เองในหน้าแก้ไขข้อมูล)</label></div>
                        </div>
                        <div class="col-12 js-formula-fields" id="customFormulaFields">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span>=</span>
                                <select class="form-select w-auto" name="formula_source" aria-label="คอลัมน์ต้นทาง">
                                    <?php foreach ($formulaSources() as $sourceKey => $sourceLabel): ?><option value="<?= e($sourceKey) ?>"><?= e($sourceLabel) ?></option><?php endforeach; ?>
                                </select>
                                <select class="form-select w-auto js-formula-mode" name="formula_mode" aria-label="รูปแบบสูตร">
                                    <option value="offset">บวก/ลบ จำนวนวัน</option>
                                    <option value="diff">ลบด้วยคอลัมน์วันที่ (ได้จำนวนวัน)</option>
                                </select>
                                <span class="d-flex align-items-center gap-2 js-formula-offset">
                                    <select class="form-select w-auto" name="formula_operator" aria-label="บวกหรือลบ"><option value="+">+ บวก</option><option value="-">− ลบ</option></select>
                                    <input type="number" class="form-control" style="width:100px" name="formula_days" min="0" max="3650" value="0" aria-label="จำนวนวัน">
                                    <span>วัน</span>
                                </span>
                                <span class="d-flex align-items-center gap-2 js-formula-diff">
                                    <span>−</span>
                                    <select class="form-select w-auto" name="formula_target" aria-label="คอลัมน์วันที่ที่นำมาลบ">
                                        <?php foreach ($formulaSources() as $targetKey => $targetLabel): ?><option value="<?= e($targetKey) ?>"><?= e($targetLabel) ?></option><?php endforeach; ?>
                                    </select>
                                </span>
                            </div>
                            <small class="text-secondary js-formula-hint-offset">เช่น วันที่ส่ง PO + 30 วัน · ถ้าช่องต้นทางว่าง ช่องนี้จะว่างด้วย</small>
                            <small class="text-secondary js-formula-hint-diff">ได้ผลเป็นจำนวนวัน เช่น ครบกำหนด 60 วัน ครั้งที่ 2 − ติดตั้งครั้งที่ 3 · ติดตั้งช้ากว่ากำหนดจะได้เลขติดลบ · ถ้ามีช่องใดว่าง ช่องนี้จะว่าง</small>
                        </div>
                        <?= $colorRuleFields('New') ?>
                        <div class="col-12 text-end"><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-plus me-1"></i>สร้างคอลัมน์</button></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>
<?php foreach ($customColumns as $custom): ?>
<form method="post" id="deleteCustomColumn<?= (int) $custom['id'] ?>" action="<?= e(url($companyBase . '/custom-columns/' . (int) $custom['id'] . '/delete')) ?>" class="d-none" data-confirm="ยืนยันการลบคอลัมน์ <?= e($custom['label']) ?>?" data-confirm-detail="ข้อมูลที่กรอกในคอลัมน์นี้ของลูกค้าทุกรายจะถูกลบ และไม่สามารถกู้คืนได้"><?= csrf_field() ?></form>
<?php endforeach; ?>
<script>
// Each custom-column form (the create form and one edit form per column) shows the formula fields only for a
// date column, the dropdown options only for a text column, and the colour conditions that fit the column's values.
document.querySelectorAll('[data-column-form]').forEach(function (form) {
    var type = form.querySelector('.js-column-type');
    var formulaToggle = form.querySelector('.js-formula-toggle');
    var useFormula = form.querySelector('.js-use-formula');
    var formulaFields = form.querySelector('.js-formula-fields');
    var optionsToggle = form.querySelector('.js-options-toggle');
    var useOptions = form.querySelector('.js-use-options');
    var optionsFields = form.querySelector('.js-options-fields');
    var formulaMode = form.querySelector('.js-formula-mode');
    function sync() {
        var isDate = type.value === 'date';
        var isText = type.value === 'text';
        formulaToggle.classList.toggle('d-none', !isDate);
        formulaFields.classList.toggle('d-none', !(isDate && useFormula.checked));
        optionsToggle.classList.toggle('d-none', !isText);
        optionsFields.classList.toggle('d-none', !isText || !useOptions.checked);
        var isDiff = formulaMode && formulaMode.value === 'diff';
        form.querySelectorAll('.js-formula-offset, .js-formula-hint-offset').forEach(function (element) { element.classList.toggle('d-none', isDiff); });
        form.querySelectorAll('.js-formula-diff, .js-formula-hint-diff').forEach(function (element) { element.classList.toggle('d-none', !isDiff); });
        // A days-difference formula gives a number, so its colour conditions are the number ones.
        var kind = type.value === 'number' || (isDate && useFormula.checked && isDiff) ? 'number' : type.value;
        colorFields.classList.toggle('d-none', !useColors.checked);
        var rows = form.querySelectorAll('.js-color-rule');
        rows.forEach(function (row) {
            var op = row.querySelector('.js-rule-op');
            var value = row.querySelector('.js-rule-value');
            Array.prototype.forEach.call(op.options, function (option) {
                var allowed = option.dataset.kinds.split(' ').indexOf(kind) !== -1;
                option.hidden = !allowed;
                option.disabled = !allowed;
            });
            if (!op.selectedOptions[0] || op.selectedOptions[0].disabled) {
                op.value = Array.prototype.find.call(op.options, function (option) { return !option.disabled; }).value;
            }
            value.classList.toggle('d-none', op.selectedOptions[0].dataset.noValue === '1');
            value.type = kind === 'number' ? 'number' : 'text';
            value.step = 'any';
            value.placeholder = kind === 'number' ? 'ตัวเลข เช่น 0' : 'พิมพ์ข้อความ';
            row.querySelector('.js-rule-preview').className = 'rule-preview js-rule-preview cell-rule-' + row.querySelector('.js-rule-color').value;
            // A hidden row (or every row, when colours are off) is disabled so the browser does not send it.
            var off = !useColors.checked || row.classList.contains('d-none');
            row.querySelectorAll('select, input').forEach(function (input) { input.disabled = off; });
            // The browser points at an empty value box before sending; a hidden box must never be required.
            value.required = !off && op.selectedOptions[0].dataset.noValue !== '1';
        });
        var shown = Array.prototype.filter.call(rows, function (row) { return !row.classList.contains('d-none'); }).length;
        form.querySelector('.js-rule-add').classList.toggle('d-none', shown >= rows.length);
        var examples = {
            number: 'ตัวอย่าง: ถ้าช่องนี้ "น้อยกว่า" 0 → ระบายสีแดง (เช่น ช่องช้า/เร็ว ที่ติดลบ = ติดตั้งช้ากว่ากำหนด)',
            text: 'ตัวอย่าง: ถ้าช่องนี้ "เท่ากับ" เสร็จสิ้น → ระบายสีเขียว',
            date: 'ตัวอย่าง: ถ้าช่องนี้ "ก่อนวันนี้ (เลยมาแล้ว)" → ระบายสีแดง'
        };
        form.querySelector('.js-rule-example').textContent = examples[kind] + (shown > 1 ? ' · ถ้าตรงหลายเงื่อนไข จะใช้สีของเงื่อนไขที่อยู่บนสุด' : '');
    }
    var useColors = form.querySelector('.js-use-colors');
    var colorFields = form.querySelector('.js-color-fields');
    useColors.addEventListener('change', sync);
    form.querySelectorAll('.js-rule-op, .js-rule-color').forEach(function (select) { select.addEventListener('change', sync); });
    form.querySelector('.js-rule-add').addEventListener('click', function () {
        var next = form.querySelector('.js-color-rule.d-none');
        if (next) next.classList.remove('d-none');
        sync();
    });
    form.querySelectorAll('.js-rule-remove').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = button.closest('.js-color-rule');
            row.classList.add('d-none');
            row.querySelector('.js-rule-value').value = '';
            sync();
        });
    });
    formulaMode && formulaMode.addEventListener('change', sync);
    type.addEventListener('change', sync);
    useFormula.addEventListener('change', sync);
    useOptions.addEventListener('change', sync);
    sync();
});
</script>
<?php endif; ?>
<section class="panel p-0 overflow-hidden">
    <div class="table-scroll">
        <table class="enterprise-table" data-layout="<?= $compactTable ? 'compact' : 'full' ?>" data-company-id="<?= (int) ($filters['company_id'] ?? 0) ?>"<?= $compactTable && $colorRuleMap !== [] ? ' data-color-rules="' . e(json_encode($colorRuleMap, JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
            <thead><tr><th class="text-center">ลำดับ</th><?php foreach ($columns as $key => $label): ?><?php if ($key === 'engineer'): ?><th><?= e($label) ?></th><?php elseif (isset($customByKey[$key])): $formula = App\Repositories\CustomColumnRepository::formulaText($customByKey[$key], $customColumns); ?><th<?= $formula !== '' ? ' title="สูตร: ' . e($formula) . '"' : '' ?>><?= e($label) ?><?php if ($formula !== ''): ?><i class="fa-solid fa-calculator ms-1 text-secondary"></i><?php endif; ?></th><?php else: ?><th><a href="<?= e($sortLink($key === 'days_early_late' ? 'due_60_date' : $key)) ?>"><?= e($label) ?><i class="fa-solid fa-sort ms-1"></i></a></th><?php endif; ?><?php endforeach; ?><?php if ($compactTable): ?><th class="text-center" style="width:1%"><?php if (can('manage_companies')): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tableColumnsModal" title="เพิ่ม/ลดคอลัมน์"><i class="fa-solid fa-plus"></i></button><?php endif; ?></th><?php endif; ?></tr></thead>
            <tbody>
            <?php if (!$result['items']): ?><tr><td colspan="<?= count($columns) + ($compactTable ? 2 : 1) ?>" class="empty-state"><i class="fa-regular fa-folder-open"></i><strong>ไม่พบข้อมูล</strong><span>ลองเปลี่ยนคำค้นหาหรือตัวกรอง</span></td></tr><?php endif; ?>
            <?php
            $zoneOptions = config('customer.zones', []);
            $engineerOptions = $engineerOptions ?? [];
            $machineSizes = config('customer.machine_sizes', []);
            $statusOptions = config('customer.status_options', []);
            /**
             * One table cell the inline editor can open. The data attributes carry what the editor needs to build
             * its input: the field name, its kind (date|text|select), the raw value, the choices of a select, and
             * the column id when the field is a custom column.
             */
            // Custom columns have no per-field colour, so each one gets a tone from its position: neighbours never share one.
            $customTones = array_flip(array_map(static fn (array $c): int => (int) $c['id'], $customColumns));
            $dataCell = static function (string $field, ?string $raw, string $display, string $kind = 'text', array $options = [], ?int $customId = null, bool $editable = true) use ($customTones): string {
                $editable = $editable && ($customId !== null ? can('edit_customer') : App\Core\Auth::canEditCustomerField($field));
                $tone = $customId !== null ? ' custom-tone-' . (($customTones[$customId] ?? $customId) % 6) : '';
                $html = '<td class="data-entry-cell data-entry-' . e($field) . $tone . '" data-field="' . e($field) . '" data-kind="' . e($kind) . '" data-raw="' . e((string) $raw) . '"';
                if ($options !== []) {
                    $html .= ' data-options="' . e(json_encode(array_values($options), JSON_UNESCAPED_UNICODE)) . '"';
                }
                if ($customId !== null) {
                    $html .= ' data-custom-id="' . $customId . '"';
                }
                if ($editable) {
                    $html .= ' data-editable="1" tabindex="0" title="คลิกเพื่อกรอกข้อมูล"';
                }
                $classes = 'data-entry-value';
                if ($raw === null || $raw === '') {
                    $classes .= ' is-empty';
                } elseif ($kind === 'number') {
                    $classes .= ' data-entry-number';
                }
                return $html . '><span class="' . $classes . '">' . e($display) . '</span></td>';
            };
            /** The status cell keeps its coloured badge, so it cannot go through $dataCell. */
            $statusCell = static function (array $row, string $color) use ($statusOptions): string {
                $html = '<td class="data-entry-cell data-entry-status" data-field="status" data-kind="select" data-raw="' . e((string) ($row['status'] ?? '')) . '" data-options="' . e(json_encode($statusOptions, JSON_UNESCAPED_UNICODE)) . '"';
                if (App\Core\Auth::canEditCustomerField('status')) {
                    $html .= ' data-editable="1" tabindex="0" title="คลิกเพื่อเลือก Status"';
                }
                return $html . '><span class="status-badge status-' . e($color) . '"><span></span>' . e($row['status'] ?: 'STATUS') . '</span></td>';
            };
            ?>
            <?php foreach ($result['items'] as $index => $row): $color = status_class((string) $row['status'], $row['due_60_date']); ?>
                <tr class="clickable-row row-<?= e($color) ?>" data-href="<?= e(url('/customers/' . rawurlencode((string) $row['id']))) ?>" data-customer-id="<?= e((string) $row['id']) ?>" tabindex="0" aria-label="ดูรายละเอียด <?= e($row['name']) ?>">
                    <td class="text-center text-secondary"><?= number_format($result['total'] - (($result['page'] - 1) * $result['per_page'] + $index)) ?></td>
                    <td><div class="d-flex align-items-center justify-content-between gap-3"><strong class="customer-name"><?= e($row['name']) ?></strong><?php if(can('edit_customer')): ?><button type="button" class="btn btn-sm btn-outline-primary edit-tracking-button" data-customer="<?= e(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" data-bs-toggle="modal" data-bs-target="#editTrackingModal" title="กรอกหรือแก้ไขข้อมูล"><i class="fa-solid fa-pen-to-square"></i></button><?php endif; ?></div></td>
                    <?php if ($compactTable): ?>
                    <?php foreach (array_keys($columns) as $field): if ($field === 'name') continue; ?>
                        <?php if ($field === 'days_early_late'): ?><td><span class="days-value text-<?= e($color) ?>"><?= e($daysText($row['days_early_late'])) ?></span></td>
                        <?php elseif ($field === 'status'): ?><?= $statusCell($row, $color) ?>
                        <?php elseif ($field === 'actions'): ?><?php if (can('delete_customer') && (int) $row['id'] < 0): ?><td><form method="post" action="<?= e(url('/customers/' . rawurlencode((string) $row['id']) . '/delete')) ?>" data-confirm="ยืนยันการลบรายชื่อนี้?" data-confirm-detail="รายการนี้จะถูกลบออกจากข้อมูล CAP+ และไม่สามารถกู้คืนได้"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="ลบรายชื่อ"><i class="fa-solid fa-trash"></i></button></form></td><?php else: ?><td></td><?php endif; ?>
                        <?php elseif (isset($customByKey[$field])): $customColumn = $customByKey[$field]; $isDate = $customColumn['type'] === 'date';
                            $isFormula = App\Repositories\CustomColumnRepository::formulaText($customColumn, $customColumns) !== '';
                            $customOptions = $isDate ? [] : App\Repositories\CustomColumnRepository::options($customColumn); ?>
                            <?php if (App\Repositories\CustomColumnRepository::isDifference($customColumn)): ?><td class="data-entry-<?= e($field) ?>" data-field="<?= e($field) ?>" data-kind="days" data-raw="<?= e((string) ($row[$field] ?? '')) ?>"><span class="days-value text-<?= e($color) ?>"><?= e($daysText($row[$field] ?? null)) ?></span></td>
                            <?php elseif ($customColumn['type'] === 'number'): $number = (string) ($row[$field] ?? ''); ?><?= $dataCell($field, $number, $number !== '' ? $number : '-', 'number', [], (int) $customColumn['id']) ?>
                            <?php else: ?><?= $dataCell($field, $row[$field] ?? null, $isDate ? date_th($row[$field] ?? null) : (($row[$field] ?? '') ?: '-'), $isDate ? 'date' : ($customOptions !== [] ? 'select' : 'text'), $customOptions, (int) $customColumn['id'], !$isFormula) ?><?php endif; ?>
                        <?php elseif ($field === 'engineer'): ?><?= $dataCell('engineer', $row['engineer'] ?? null, ($row['engineer'] ?? '') ?: '-', 'select', $engineerOptions) ?>
                        <?php elseif (str_ends_with($field, '_date') || str_starts_with($field, 'engineer_appointment_') || str_starts_with($field, 'installation_date_')): ?><?= $dataCell($field, $row[$field], date_th($row[$field]), 'date') ?>
                        <?php elseif ($field === 'zone'): ?><?= $dataCell('zone', $row['zone'], $row['zone'] ?: '-', 'select', $zoneOptions) ?>
                        <?php elseif ($field === 'machine_size'): ?><?= $dataCell('machine_size', $row['machine_size'], $row['machine_size'] ?: '-', 'select', $machineSizes) ?>
                        <?php else: ?><?= $dataCell($field, $row[$field] ?? null, ($row[$field] ?? '') ?: '-') ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <td></td>
                    <?php else: ?>
                    <?= $dataCell('zone', $row['zone'], $row['zone'] ?: '-', 'select', $zoneOptions) ?><?= $dataCell('machine_size', $row['machine_size'], $row['machine_size'] ?: '-', 'select', $machineSizes) ?>
                    <?php foreach (['po_sent_date','po_due_date'] as $field): ?><?= $dataCell($field, $row[$field], date_th($row[$field]), 'date') ?><?php endforeach; ?>
                    <?= $dataCell('engineer', $row['engineer'] ?? null, ($row['engineer'] ?? '') ?: '-', 'select', $engineerOptions) ?>
                    <?php foreach (['engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date'] as $field): ?>
                        <?= $dataCell($field, $row[$field], date_th($row[$field]), 'date') ?>
                    <?php endforeach; ?>
                    <td><span class="days-value text-<?= e($color) ?>"><?php if ($row['days_early_late'] === null || $row['days_early_late'] === '') { echo '-'; } else { $days=(int)$row['days_early_late']; echo $days < 0 ? 'ช้า '.abs($days).' วัน' : 'เร็ว/เหลือ '.$days.' วัน'; } ?></span></td>
                    <?= $statusCell($row, $color) ?>
                    <?= $dataCell('construction_notes', $row['construction_notes'], $row['construction_notes'] ?: '-') ?>
                    <?= $dataCell('work_status', $row['work_status'], $row['work_status'] ?: '-') ?>
                    <?= $dataCell('cap_notes', $row['cap_notes'], $row['cap_notes'] ?: '-') ?>
                    <?php if (can('delete_customer') && (int) $row['id'] < 0): ?><td><form method="post" action="<?= e(url('/customers/' . rawurlencode((string) $row['id']) . '/delete')) ?>" data-confirm="ยืนยันการลบรายชื่อนี้?" data-confirm-detail="รายการนี้จะถูกลบออกจากข้อมูล CAP+ และไม่สามารถกู้คืนได้"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="ลบรายชื่อ"><i class="fa-solid fa-trash"></i></button></form></td><?php else: ?><td></td><?php endif; ?>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer"><form method="get" class="d-flex align-items-center gap-2"><?php foreach ($filters as $key => $value): if ($key === 'per_page' || is_array($value)) continue; ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endforeach; ?><label for="per_page" class="text-secondary text-nowrap">แสดง</label><select class="form-select form-select-sm" id="per_page" name="per_page" onchange="this.form.submit()"><?php foreach ([10,25,50,100] as $size): ?><option <?= $result['per_page']===$size?'selected':'' ?>><?= $size ?></option><?php endforeach; ?></select><span class="text-secondary text-nowrap">รายการ</span></form><nav aria-label="แบ่งหน้า"><ul class="pagination pagination-sm mb-0"><li class="page-item <?= $result['page']<=1?'disabled':'' ?>"><a class="page-link" href="<?= e($query(['page'=>max(1,$result['page']-1)])) ?>">ก่อนหน้า</a></li><li class="page-item disabled"><span class="page-link">หน้า <?= $result['page'] ?> / <?= $result['pages'] ?></span></li><li class="page-item <?= $result['page']>=$result['pages']?'disabled':'' ?>"><a class="page-link" href="<?= e($query(['page'=>min($result['pages'],$result['page']+1)])) ?>">ถัดไป</a></li></ul></nav></div>
</section>
