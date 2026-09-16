<main class="login-page">
    <section class="login-visual d-none d-lg-flex" aria-hidden="true">
        <div class="login-visual-content">
            <span class="eyebrow">CUSTOMER INSTALLATION OPERATIONS</span>
            <h1>ติดตามทุกงานติดตั้ง<br>ในมุมมองเดียว</h1>
            <p>ตรวจสอบสถานะ กำหนดส่งมอบ และความคืบหน้างานติดตั้งได้อย่างชัดเจน</p>
            <div class="visual-stats"><span><i class="fa-solid fa-shield-halved"></i> Secure Access</span><span><i class="fa-solid fa-chart-column"></i> Live Insights</span></div>
        </div>
    </section>
    <section class="login-panel">
        <div class="login-box">
            <div class="brand login-brand"><span class="brand-mark"><i class="fa-solid fa-chart-line"></i></span><span><strong>Status working</strong><small>Installation Tracker</small></span></div>
            <div class="mt-5 mb-4"><h2>เข้าสู่ระบบ</h2><p class="text-secondary mb-0">กรอกข้อมูลบัญชีองค์กรของคุณเพื่อดำเนินการต่อ</p></div>
            <?php if ($message = flash('error')): ?><div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($message) ?></div><?php endif; ?>
            <?php if ($message = flash('success')): ?><div class="alert alert-success" role="status"><i class="fa-solid fa-circle-check me-2"></i><?= e($message) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('/login')) ?>" autocomplete="on">
                <?= csrf_field() ?>
                <div class="mb-3"><label for="username" class="form-label">Username</label><div class="input-icon"><i class="fa-regular fa-user"></i><input class="form-control" id="username" name="username" value="<?= old('username') ?>" autocomplete="username" maxlength="100" required autofocus></div></div>
                <div class="mb-2"><label for="password" class="form-label">Password</label><div class="input-icon"><i class="fa-solid fa-lock"></i><input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle aria-label="แสดงรหัสผ่าน"><i class="fa-regular fa-eye"></i></button></div></div>
                <div class="d-flex justify-content-between align-items-center mb-4"><small class="text-secondary"><i class="fa-solid fa-shield-halved me-1"></i>กรอกรหัสผิดได้ไม่เกิน 5 ครั้ง</small><a class="btn btn-link btn-sm p-0 fw-semibold text-decoration-none" href="<?= e(url('/forgot-password')) ?>">ลืมรหัสผ่าน?</a></div>
                <button class="btn btn-primary w-100 btn-lg" type="submit">เข้าสู่ระบบ <i class="fa-solid fa-arrow-right ms-2"></i></button>
            </form>
            <p class="login-help"><i class="fa-solid fa-headset me-2"></i>หากไม่สามารถเข้าสู่ระบบ กรุณาติดต่อผู้ดูแลระบบ</p>
        </div>
    </section>
</main>
