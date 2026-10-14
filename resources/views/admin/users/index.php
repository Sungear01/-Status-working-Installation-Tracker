<div class="page-heading">
    <div><span class="eyebrow text-primary">ACCESS CONTROL</span><h2>จัดการผู้ใช้งาน</h2><p>บัญชีผู้ใช้ สถานะ และ Role ภายในระบบ</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUser"><i class="fa-solid fa-user-plus me-2"></i>เพิ่มผู้ใช้งาน</button>
</div>
<section class="panel p-0 overflow-hidden"><div class="table-scroll"><table class="enterprise-table compact-table"><thead><tr><th>ผู้ใช้งาน</th><th>Username</th><th>Role</th><th>ตารางที่เห็น</th><th>เข้าสู่ระบบล่าสุด</th><th>สถานะ</th><th class="text-end">จัดการ</th></tr></thead><tbody>
<?php foreach ($users as $user): ?>
<tr data-failed-attempts="<?= (int) ($user['failed_attempts'] ?? 0) ?>" data-user-id="<?= (int) $user['id'] ?>">
    <td><div class="table-user"><span class="avatar"><i class="fa-solid fa-user"></i></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['email'] ?: '-') ?></small></div></div></td>
    <td><code><?= e($user['username']) ?></code></td><td><span class="role-badge"><?= e($user['role_name']) ?></span></td>
    <td><?php $userAccess = $tableAccess[(int) $user['id']] ?? null; ?>
        <?php if (App\Core\Auth::isSuperRole($user['role_slug'])): ?><span class="access-chip access-chip-all">ทุกตาราง</span>
        <?php elseif ($userAccess === null && !empty($user['company_name'])): ?><span class="access-chip access-chip-default" title="ค่าเดิมจากบริษัทที่เคยผูกไว้ · สิทธิ์แก้ไขตาม Role · กดปุ่มตารางเพื่อกำหนดใหม่"><?= e($user['company_name']) ?> <small>(ค่าเดิม)</small></span>
        <?php elseif ($userAccess === null): ?><span class="access-chip access-chip-default" title="ยังไม่ได้กำหนด: เห็นทุกตาราง สิทธิ์แก้ไขตาม Role">ค่าเริ่มต้น (ทุกตาราง)</span>
        <?php elseif ($userAccess === []): ?><span class="access-chip access-chip-none">ไม่เห็นตารางใด</span>
        <?php else: foreach ($companies as $company): if (!isset($userAccess[(int) $company['id']])) continue; ?><span class="access-chip" title="แก้ไขได้ <?= count($userAccess[(int) $company['id']]) ?> คอลัมน์"><?= e($company['name']) ?> <small><?= count($userAccess[(int) $company['id']]) ?>/<?= count($company['access_columns']) ?></small></span><?php endforeach; endif; ?>
    </td>
    <td><?= e($user['last_login_at'] ?: 'ยังไม่เคย') ?></td>
    <td><span class="status-badge status-<?= $user['is_active'] ? 'success' : 'secondary' ?>"><span></span><?= $user['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน' ?></span></td>
    <td class="text-end text-nowrap">
        <?php if (App\Core\Auth::isDeveloper() && $user['is_active'] && $user['role_slug'] !== 'developer'): ?><form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/impersonate')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-warning" title="เข้าใช้ในนามผู้ใช้นี้"><i class="fa-solid fa-user-secret"></i></button></form><?php endif; ?>
        <?php if (!App\Core\Auth::isSuperRole($user['role_slug'])): ?><button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#accessUser<?= (int) $user['id'] ?>" title="กำหนดตารางและคอลัมน์ที่แก้ไขได้"><i class="fa-solid fa-table-columns"></i></button><?php endif; ?>
        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editUser<?= (int) $user['id'] ?>" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#passwordUser<?= (int) $user['id'] ?>" title="Reset Password"><i class="fa-solid fa-key"></i></button>
        <?php if ((int) $user['id'] !== App\Core\Auth::id()): ?><form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="active" value="<?= $user['is_active'] ? '0' : '1' ?>"><button class="btn btn-sm <?= $user['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $user['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>"><i class="fa-solid <?= $user['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i></button></form><?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php $lockedUsers = array_filter($users, static fn (array $item): bool => (int) ($item['failed_attempts'] ?? 0) >= 5); ?>
<?php if ($lockedUsers): ?><div class="alert alert-warning mt-3" role="status"><i class="fa-solid fa-user-lock me-2"></i><strong>บัญชีที่ถูกระงับ:</strong> <?php foreach ($lockedUsers as $locked): ?><?= e($locked['username']) ?> (ผิด <?= (int) $locked['failed_attempts'] ?> ครั้ง)<?php if ($locked !== end($lockedUsers)): ?>, <?php endif; ?><?php endforeach; ?></div><?php endif; ?>
<?php if ($lockedUsers): ?><div class="alert alert-danger mt-3" role="status"><i class="fa-solid fa-lock me-2"></i><strong>มีบัญชีถูกระงับชั่วคราว</strong> — พบการกรอกรหัสผ่านผิดครบ 5 ครั้ง ระบบจะปลดล็อกอัตโนมัติภายใน 15 นาที</div><?php endif; ?>
</tbody></table></div></section>

<div class="modal fade" id="createUser" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" action="<?= e(url('/admin/users')) ?>"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title fs-5">เพิ่มผู้ใช้งาน</h2><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" pattern="[A-Za-z0-9._-]{3,100}" required></div><div class="col-md-6"><label class="form-label">ชื่อ-นามสกุล</label><input class="form-control" name="name" maxlength="150" required></div><div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div><div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Password เริ่มต้น</label><input type="password" class="form-control" name="password" minlength="12" autocomplete="new-password" required><div class="form-text">อย่างน้อย 12 ตัวอักษร · กำหนดตารางที่เห็นได้หลังสร้างผู้ใช้ ที่ปุ่ม <i class="fa-solid fa-table-columns"></i></div></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">สร้างผู้ใช้งาน</button></div></form></div></div></div>

<?php foreach ($users as $user): ?>
<div class="modal fade" id="editUser<?= (int) $user['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title fs-5">แก้ไข <?= e($user['username']) ?></h2><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($user['username']) ?>" pattern="[A-Za-z0-9._-]{3,100}" required></div><div class="mb-3"><label class="form-label">ชื่อ-นามสกุล</label><input class="form-control" name="name" value="<?= e($user['name']) ?>" required></div><div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($user['email']) ?>"></div><div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $user['role_slug'] === $role['slug'] ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int) $user['id'] ?>" <?= $user['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="active<?= (int) $user['id'] ?>">เปิดใช้งานบัญชี</label></div></div><div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div></form></div></div></div>
<?php if (!App\Core\Auth::isSuperRole($user['role_slug'])): $userAccess = $tableAccess[(int) $user['id']] ?? null; $custom = $userAccess !== null; ?>
<div class="modal fade" id="accessUser<?= (int) $user['id'] ?>" tabindex="-1" aria-labelledby="accessUserTitle<?= (int) $user['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/table-access')) ?>" class="access-form d-flex flex-column overflow-hidden"><?= csrf_field() ?>
        <div class="modal-header"><div><h2 class="modal-title fs-5" id="accessUserTitle<?= (int) $user['id'] ?>">สิทธิ์ตาราง: <?= e($user['name']) ?></h2><small class="text-secondary">เลือกว่าผู้ใช้นี้เห็นตารางไหน และแก้ไขคอลัมน์ไหนได้บ้าง</small></div><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
        <div class="modal-body">
            <div class="access-mode mb-3">
                <label class="access-mode-option"><input class="form-check-input" type="radio" name="access_mode" value="default" <?= $custom ? '' : 'checked' ?>><span><strong>ใช้ค่าเริ่มต้น</strong><small><?= !empty($user['company_name']) ? 'เห็นเฉพาะตาราง ' . e($user['company_name']) . ' (ค่าเดิม)' : 'เห็นทุกตาราง' ?> · สิทธิ์แก้ไขตาม Role (<?= e($user['role_name']) ?>)</small></span></label>
                <label class="access-mode-option"><input class="form-check-input" type="radio" name="access_mode" value="custom" <?= $custom ? 'checked' : '' ?>><span><strong>กำหนดเองรายตาราง</strong><small>เลือกตารางที่เห็น และคอลัมน์ที่แก้ไขได้ในแต่ละตาราง</small></span></label>
            </div>
            <div class="access-custom">
                <p class="access-hint"><i class="fa-solid fa-circle-info me-1"></i>เปิดสวิตช์ = เห็นตารางนั้น · ติ๊กคอลัมน์ = <strong>แก้ไขได้</strong> · ไม่ติ๊ก = ดูได้อย่างเดียว<br><small>ผู้ใช้ต้องมีสิทธิ์ "แก้ไขข้อมูลลูกค้า" ใน Role ด้วย จึงจะแก้ไขได้จริง</small></p>
                <?php foreach ($companies as $company): $companyId = (int) $company['id']; $sees = $custom && isset($userAccess[$companyId]); $editableNow = $sees ? $userAccess[$companyId] : []; ?>
                    <div class="access-table">
                        <div class="access-table-head">
                            <div class="form-check form-switch m-0"><input class="form-check-input access-table-toggle" type="checkbox" role="switch" name="tables[<?= $companyId ?>]" value="1" id="acc<?= (int) $user['id'] ?>_<?= $companyId ?>" <?= $sees ? 'checked' : '' ?>><label class="form-check-label" for="acc<?= (int) $user['id'] ?>_<?= $companyId ?>">เห็นตาราง <strong><?= e($company['name']) ?></strong><?php if (($company['table_layout'] ?? 'full') === 'compact'): ?> <small class="text-secondary">(ตารางแบบย่อ)</small><?php endif; ?></label></div>
                            <div class="access-quick"><button type="button" class="btn btn-link btn-sm p-0" data-access-check="all">แก้ไขได้ทั้งหมด</button> · <button type="button" class="btn btn-link btn-sm p-0" data-access-check="none">ดูอย่างเดียวทั้งหมด</button></div>
                        </div>
                        <div class="access-columns">
                            <?php foreach ($company['access_columns'] as $key => $label): ?>
                                <label class="access-column"><input class="form-check-input" type="checkbox" name="editable[<?= $companyId ?>][]" value="<?= e($key) ?>" <?= in_array($key, $editableNow, true) ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>บันทึกสิทธิ์</button></div>
    </form>
</div></div></div>
<?php endif; ?>
<div class="modal fade" id="passwordUser<?= (int) $user['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5">รีเซ็ตรหัสผ่าน: <?= e($user['username']) ?></h2><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
    <div class="modal-body">
        <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/send-reset-link')) ?>">
            <?= csrf_field() ?>
            <label class="form-label fw-bold">ส่งลิงก์ให้ผู้ใช้ตั้งรหัสผ่านเอง</label>
            <?php if (filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL)): ?>
                <p class="text-secondary small mb-3">ระบบจะส่งอีเมลพร้อมลิงก์ไปที่ <strong><?= e($user['email']) ?></strong> ให้ผู้ใช้กดตั้งรหัสผ่านใหม่ด้วยตัวเอง (ลิงก์ใช้ได้ครั้งเดียว ภายใน 60 นาที)</p>
                <button class="btn btn-primary w-100"><i class="fa-solid fa-paper-plane me-2"></i>ส่งลิงก์ทางอีเมล</button>
            <?php else: ?>
                <div class="alert alert-warning mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>ผู้ใช้รายนี้ยังไม่มีอีเมล กรุณาเพิ่มอีเมลในหน้าแก้ไขก่อน จึงจะส่งลิงก์ได้</div>
            <?php endif; ?>
        </form>
        <div class="text-center text-secondary my-3">หรือ</div>
        <?php $passwordId = 'manualPassword' . (int) $user['id']; ?>
        <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/reset-password')) ?>" novalidate data-validate>
            <?= csrf_field() ?>
            <label class="form-label fw-bold" for="<?= $passwordId ?>">กำหนดรหัสผ่านชั่วคราวเอง</label>
            <input type="password" class="form-control" id="<?= $passwordId ?>" name="password" minlength="12" autocomplete="new-password" required aria-describedby="<?= $passwordId ?>Error <?= $passwordId ?>Length" data-required-message="กรุณากรอกรหัสผ่านชั่วคราว" data-invalid-message="รหัสผ่านสั้นเกินไป ต้องมีอย่างน้อย 12 ตัวอักษร">
            <div class="field-error" id="<?= $passwordId ?>Error" role="alert" hidden><i class="fa-solid fa-circle-exclamation"></i><span></span></div>
            <div class="length-hint" id="<?= $passwordId ?>Length" data-length-hint="<?= $passwordId ?>" aria-live="polite"><i class="fa-regular fa-circle"></i><span>อย่างน้อย 12 ตัวอักษร</span></div>
            <div class="form-text mb-3">ควรส่งรหัสผ่านให้ผู้ใช้ผ่านช่องทางที่ปลอดภัย</div>
            <button class="btn btn-outline-warning w-100">กำหนดรหัสผ่านเอง</button>
        </form>
    </div>
</div></div></div>
<?php endforeach; ?>
<script>
// Table-access modal: "all / none" ticks every column of one table; ticking a column also turns its table on.
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-access-check]');
    if (!button) return;
    var table = button.closest('.access-table');
    var on = button.dataset.accessCheck === 'all';
    table.querySelectorAll('.access-columns input').forEach(function (input) { input.checked = on; });
    table.querySelector('.access-table-toggle').checked = true;
});
document.addEventListener('change', function (event) {
    if (event.target.matches('.access-columns input') && event.target.checked) {
        event.target.closest('.access-table').querySelector('.access-table-toggle').checked = true;
    }
    if (event.target.matches('.access-table-toggle, .access-columns input')) {
        var custom = event.target.closest('.access-form').querySelector('input[name="access_mode"][value="custom"]');
        if (custom) custom.checked = true;
    }
});
</script>
<?php $resetUserId = filter_input(INPUT_GET, 'reset_user', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); if ($resetUserId): ?><script>document.addEventListener('DOMContentLoaded',function(){var modal=document.getElementById('passwordUser<?= (int) $resetUserId ?>');if(modal&&window.bootstrap){bootstrap.Modal.getOrCreateInstance(modal).show();}});</script><?php endif; ?>
