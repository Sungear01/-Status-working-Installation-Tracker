<div class="page-heading">
    <div><span class="eyebrow text-primary">ACCESS CONTROL</span><h2>จัดการผู้ใช้งาน</h2><p>บัญชีผู้ใช้ สถานะ และ Role ภายในระบบ</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUser"><i class="fa-solid fa-user-plus me-2"></i>เพิ่มผู้ใช้งาน</button>
</div>
<section class="panel p-0 overflow-hidden"><div class="table-scroll"><table class="enterprise-table compact-table"><thead><tr><th>ผู้ใช้งาน</th><th>Username</th><th>Role</th><th>บริษัท</th><th>เข้าสู่ระบบล่าสุด</th><th>สถานะ</th><th class="text-end">จัดการ</th></tr></thead><tbody>
<?php foreach ($users as $user): ?>
<tr data-failed-attempts="<?= (int) ($user['failed_attempts'] ?? 0) ?>" data-user-id="<?= (int) $user['id'] ?>">
    <td><div class="table-user"><span class="avatar"><i class="fa-solid fa-user"></i></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['email'] ?: '-') ?></small></div></div></td>
    <td><code><?= e($user['username']) ?></code></td><td><span class="role-badge"><?= e($user['role_name']) ?></span></td><td><?= e($user['company_name'] ?: '-') ?></td><td><?= e($user['last_login_at'] ?: 'ยังไม่เคย') ?></td>
    <td><span class="status-badge status-<?= $user['is_active'] ? 'success' : 'secondary' ?>"><span></span><?= $user['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน' ?></span></td>
    <td class="text-end text-nowrap">
        <?php if (App\Core\Auth::isDeveloper() && $user['is_active'] && $user['role_slug'] !== 'developer'): ?><form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/impersonate')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-warning" title="เข้าใช้ในนามผู้ใช้นี้"><i class="fa-solid fa-user-secret"></i></button></form><?php endif; ?>
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

<div class="modal fade" id="createUser" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" action="<?= e(url('/admin/users')) ?>"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title fs-5">เพิ่มผู้ใช้งาน</h2><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" pattern="[A-Za-z0-9._-]{3,100}" required></div><div class="col-md-6"><label class="form-label">ชื่อ-นามสกุล</label><input class="form-control" name="name" maxlength="150" required></div><div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div><div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">บริษัท</label><select class="form-select" name="company_id"><option value="">-- ไม่ระบุ --</option><?php foreach ($companies as $company): ?><option value="<?= (int) $company['id'] ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Password เริ่มต้น</label><input type="password" class="form-control" name="password" minlength="12" autocomplete="new-password" required><div class="form-text">อย่างน้อย 12 ตัวอักษร</div></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">สร้างผู้ใช้งาน</button></div></form></div></div></div>

<?php foreach ($users as $user): ?>
<div class="modal fade" id="editUser<?= (int) $user['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title fs-5">แก้ไข <?= e($user['username']) ?></h2><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($user['username']) ?>" pattern="[A-Za-z0-9._-]{3,100}" required></div><div class="mb-3"><label class="form-label">ชื่อ-นามสกุล</label><input class="form-control" name="name" value="<?= e($user['name']) ?>" required></div><div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($user['email']) ?>"></div><div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $user['role_slug'] === $role['slug'] ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">บริษัท</label><select class="form-select" name="company_id"><option value="">-- ไม่ระบุ --</option><?php foreach ($companies as $company): ?><option value="<?= (int) $company['id'] ?>" <?= (int) ($user['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int) $user['id'] ?>" <?= $user['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="active<?= (int) $user['id'] ?>">เปิดใช้งานบัญชี</label></div></div><div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div></form></div></div></div>
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
        <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/reset-password')) ?>">
            <?= csrf_field() ?>
            <label class="form-label fw-bold" for="manualPassword<?= (int) $user['id'] ?>">กำหนดรหัสผ่านชั่วคราวเอง</label>
            <input type="password" class="form-control" id="manualPassword<?= (int) $user['id'] ?>" name="password" minlength="12" autocomplete="new-password" required>
            <div class="form-text mb-3">อย่างน้อย 12 ตัวอักษร และควรส่งให้ผู้ใช้ผ่านช่องทางที่ปลอดภัย</div>
            <button class="btn btn-outline-warning w-100">กำหนดรหัสผ่านเอง</button>
        </form>
    </div>
</div></div></div>
<?php endforeach; ?>
<?php $resetUserId = filter_input(INPUT_GET, 'reset_user', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); if ($resetUserId): ?><script>document.addEventListener('DOMContentLoaded',function(){var modal=document.getElementById('passwordUser<?= (int) $resetUserId ?>');if(modal&&window.bootstrap){bootstrap.Modal.getOrCreateInstance(modal).show();}});</script><?php endif; ?>
