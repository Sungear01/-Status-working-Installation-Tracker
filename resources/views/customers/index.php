<?php
require base_path('resources/views/customers/_edit_modal.php');
$query = static fn(array $changes = []): string => '?' . http_build_query(array_merge($filters, $changes));
$sortLink = static function (string $column) use ($filters, $query): string {
    $direction = (($filters['sort'] ?? '') === $column && ($filters['direction'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
    return $query(['sort' => $column, 'direction' => $direction, 'page' => 1]);
};
$columns = [
    'name' => 'รายชื่อลูกค้า', 'zone' => 'Zone', 'machine_size' => 'ขนาดเครื่อง', 'po_sent_date' => 'วันที่ส่ง PO',
    'po_due_date' => 'กำหนดหลังจัดส่ง PO 60 วัน ครั้งที่ 1', 'engineer_appointment_1' => 'วิศวกรนัดครั้งที่ 1', 'installation_date_1' => 'ติดตั้งครั้งที่ 1',
    'engineer_appointment_2' => 'วิศวกรนัดครั้งที่ 2', 'installation_date_2' => 'ติดตั้งครั้งที่ 2', 'engineer_appointment_3' => 'วิศวกรนัดครั้งที่ 3',
    'due_60_date' => 'ครบกำหนด 60 วัน ครั้งที่ 2', 'days_early_late' => 'วันช้า/เร็ว', 'status' => 'Status',
    'construction_notes' => 'หมายเหตุทางฝ่ายก่อสร้าง', 'work_status' => 'สถานะงาน', 'cap_notes' => 'หมายเหตุทาง CAP+', 'actions' => 'จัดการ',
];
?>
<?php $columns['installation_date_3'] = 'ติดตั้งครั้งที่ 3'; ?>
<?php $installationLabel = $columns['installation_date_3']; unset($columns['installation_date_3']); $columns = array_merge(array_slice($columns, 0, 10, true), ['installation_date_3' => $installationLabel], array_slice($columns, 10, null, true)); ?>
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
            <a href="<?= e(url('/customers') . $query(['company_id' => null, 'group_id' => $group['id'], 'page' => 1])) ?>" class="btn btn-sm <?= (string) ($filters['group_id'] ?? '') === (string) $group['id'] ? 'btn-primary' : 'btn-outline-primary' ?>" title="รวมบริษัท <?= e($group['label']) ?>"><?= e($group['label']) ?></a>
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
<section class="panel p-0 overflow-hidden">
    <div class="table-scroll">
        <table class="enterprise-table">
            <thead><tr><th class="text-center">ลำดับ</th><?php foreach ($columns as $key => $label): ?><th><a href="<?= e($sortLink($key === 'days_early_late' ? 'due_60_date' : $key)) ?>"><?= e($label) ?><i class="fa-solid fa-sort ms-1"></i></a></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php if (!$result['items']): ?><tr><td colspan="19" class="empty-state"><i class="fa-regular fa-folder-open"></i><strong>ไม่พบข้อมูล</strong><span>ลองเปลี่ยนคำค้นหาหรือตัวกรอง</span></td></tr><?php endif; ?>
            <?php foreach ($result['items'] as $index => $row): $color = status_class((string) $row['status'], $row['due_60_date']); ?>
                <tr class="clickable-row row-<?= e($color) ?>" data-href="<?= e(url('/customers/' . rawurlencode((string) $row['id']))) ?>" tabindex="0" aria-label="ดูรายละเอียด <?= e($row['name']) ?>">
                    <td class="text-center text-secondary"><?= number_format($result['total'] - (($result['page'] - 1) * $result['per_page'] + $index)) ?></td>
                    <td><div class="d-flex align-items-center justify-content-between gap-3"><strong class="customer-name"><?= e($row['name']) ?></strong><?php if(can('edit_customer')): ?><button type="button" class="btn btn-sm btn-outline-primary edit-tracking-button" data-customer="<?= e(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" data-bs-toggle="modal" data-bs-target="#editTrackingModal" title="กรอกหรือแก้ไขข้อมูล"><i class="fa-solid fa-pen-to-square"></i></button><?php endif; ?></div></td><td class="data-entry-cell data-entry-zone"><span class="data-entry-value<?= empty($row['zone']) ? ' is-empty' : '' ?>"><?= e($row['zone'] ?: '-') ?></span></td><td class="data-entry-cell data-entry-machine_size"><span class="data-entry-value<?= empty($row['machine_size']) ? ' is-empty' : '' ?>"><?= e($row['machine_size'] ?: '-') ?></span></td>
                    <?php foreach (['po_sent_date','po_due_date','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date'] as $field): ?>
                        <td class="data-entry-cell data-entry-<?= e($field) ?>"><span class="data-entry-value<?= empty($row[$field]) ? ' is-empty' : '' ?>"><?= e(date_th($row[$field])) ?></span></td>
                    <?php endforeach; ?>
                    <td><span class="days-value text-<?= e($color) ?>"><?php if ($row['days_early_late'] === null || $row['days_early_late'] === '') { echo '-'; } else { $days=(int)$row['days_early_late']; echo $days < 0 ? 'ช้า '.abs($days).' วัน' : 'เร็ว/เหลือ '.$days.' วัน'; } ?></span></td>
                    <td><span class="status-badge status-<?= e($color) ?>"><span></span><?= e($row['status'] ?: 'STATUS') ?></span></td>
                    <td class="data-entry-cell data-entry-construction_notes"><span class="data-entry-value<?= empty($row['construction_notes']) ? ' is-empty' : '' ?>"><?= e($row['construction_notes'] ?: '-') ?></span></td>
                    <td class="data-entry-cell data-entry-work_status"><span class="data-entry-value<?= empty($row['work_status']) ? ' is-empty' : '' ?>"><?= e($row['work_status'] ?: '-') ?></span></td>
                    <td class="data-entry-cell data-entry-cap_notes"><span class="data-entry-value<?= empty($row['cap_notes']) ? ' is-empty' : '' ?>"><?= e($row['cap_notes'] ?: '-') ?></span></td>
                    <?php if (can('delete_customer') && (int) $row['id'] < 0): ?><td><form method="post" action="<?= e(url('/customers/' . rawurlencode((string) $row['id']) . '/delete')) ?>" data-confirm="ยืนยันการลบรายชื่อนี้?" data-confirm-detail="รายการนี้จะถูกลบออกจากข้อมูล CAP+ และไม่สามารถกู้คืนได้"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="ลบรายชื่อ"><i class="fa-solid fa-trash"></i></button></form></td><?php else: ?><td></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer"><form method="get" class="d-flex align-items-center gap-2"><?php foreach ($filters as $key => $value): if ($key === 'per_page' || is_array($value)) continue; ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endforeach; ?><label for="per_page" class="text-secondary text-nowrap">แสดง</label><select class="form-select form-select-sm" id="per_page" name="per_page" onchange="this.form.submit()"><?php foreach ([10,25,50,100] as $size): ?><option <?= $result['per_page']===$size?'selected':'' ?>><?= $size ?></option><?php endforeach; ?></select><span class="text-secondary text-nowrap">รายการ</span></form><nav aria-label="แบ่งหน้า"><ul class="pagination pagination-sm mb-0"><li class="page-item <?= $result['page']<=1?'disabled':'' ?>"><a class="page-link" href="<?= e($query(['page'=>max(1,$result['page']-1)])) ?>">ก่อนหน้า</a></li><li class="page-item disabled"><span class="page-link">หน้า <?= $result['page'] ?> / <?= $result['pages'] ?></span></li><li class="page-item <?= $result['page']>=$result['pages']?'disabled':'' ?>"><a class="page-link" href="<?= e($query(['page'=>min($result['pages'],$result['page']+1)])) ?>">ถัดไป</a></li></ul></nav></div>
</section>
