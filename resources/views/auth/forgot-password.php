<main class="login-page">
    <section class="login-visual d-none d-lg-flex" aria-hidden="true">
        <div class="login-visual-content">
            <span class="eyebrow">ACCOUNT RECOVERY</span>
            <h1>กู้คืนการเข้าถึง<br>อย่างปลอดภัย</h1>
            <p>ส่งคำขอรีเซ็ตรหัสผ่าน แล้วผู้ดูแลระบบจะช่วยตรวจสอบและดำเนินการให้</p>
        </div>
    </section>
    <section class="login-panel">
        <div class="login-box">
            <a class="brand login-brand text-decoration-none" href="<?= e(url('/login')) ?>"><span class="brand-mark"><i class="fa-solid fa-chart-line"></i></span><span><strong>Status working</strong><small>Installation Tracker</small></span></a>
            <div class="mt-5 mb-4"><h2>ลืมรหัสผ่าน</h2><p class="text-secondary mb-0">กรอกข้อมูลเพื่อส่งคำขอให้ผู้ดูแลระบบรีเซ็ตรหัสผ่าน</p></div>
            <form method="post" action="<?= e(url('/forgot-password')) ?>" novalidate data-validate>
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="username">Username</label><div class="input-icon"><i class="fa-regular fa-user"></i><input class="form-control" id="username" name="username" autocomplete="username" required autofocus aria-describedby="usernameError" data-required-message="กรุณากรอก Username"></div><div class="field-error" id="usernameError" role="alert" hidden><i class="fa-solid fa-circle-exclamation"></i><span></span></div></div>
                <div class="mb-4"><label class="form-label" for="email">Email <span class="text-secondary fw-normal">(ถ้ามี)</span></label><div class="input-icon"><i class="fa-regular fa-envelope"></i><input class="form-control" id="email" name="email" type="email" autocomplete="email" aria-describedby="emailError" data-invalid-message="รูปแบบอีเมลไม่ถูกต้อง เช่น name@company.com"></div><div class="field-error" id="emailError" role="alert" hidden><i class="fa-solid fa-circle-exclamation"></i><span></span></div></div>
                <button class="btn btn-primary w-100 btn-lg" type="submit"><i class="fa-solid fa-paper-plane me-2"></i>ส่งคำขอรีเซ็ตรหัสผ่าน</button>
            </form>
            <p class="login-help"><a class="text-decoration-none" href="<?= e(url('/login')) ?>"><i class="fa-solid fa-arrow-left me-2"></i>กลับหน้าเข้าสู่ระบบ</a></p>
        </div>
    </section>
</main>
