<main class="login-page">
    <section class="login-visual d-none d-lg-flex" aria-hidden="true">
        <div class="login-visual-content">
            <span class="eyebrow">ACCOUNT RECOVERY</span>
            <h1>ตั้งรหัสผ่านใหม่<br>ด้วยตัวคุณเอง</h1>
            <p>ลิงก์นี้ใช้ได้เพียงครั้งเดียว และจะหมดอายุตามเวลาที่ระบุในอีเมล</p>
        </div>
    </section>
    <section class="login-panel">
        <div class="login-box">
            <a class="brand login-brand text-decoration-none" href="<?= e(url('/login')) ?>"><span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span><span><strong>Status working</strong><small>Installation Tracker</small></span></a>
            <?php if ($isValid): ?>
                <div class="mt-5 mb-4"><h2>ตั้งรหัสผ่านใหม่</h2><p class="text-secondary mb-0">สำหรับบัญชี <strong><?= e((string) $username) ?></strong></p></div>
                <?php if ($message = flash('error')): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/reset-password')) ?>" id="resetForm" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">รหัสผ่านใหม่</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required autofocus>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="แสดงรหัสผ่าน"><i class="fa-regular fa-eye"></i></button>
                        </div>
                        <div class="password-meter" id="passwordMeter" data-level="0" hidden>
                            <div class="password-meter-track"><span></span><span></span><span></span><span></span></div>
                            <small class="password-meter-label" id="passwordMeterLabel"></small>
                        </div>
                        <ul class="password-rules" id="passwordRules">
                            <li data-rule="length"><i class="fa-regular fa-circle"></i><span>ความยาวอย่างน้อย 12 ตัวอักษร</span></li>
                            <li data-rule="variety"><i class="fa-regular fa-circle"></i><span>ควรผสมตัวอักษรและตัวเลข เพื่อความปลอดภัยที่มากขึ้น</span></li>
                            <li data-rule="match"><i class="fa-regular fa-circle"></i><span>ยืนยันรหัสผ่านให้ตรงกันทั้งสองช่อง</span></li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">ยืนยันรหัสผ่านใหม่</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="แสดงรหัสผ่าน"><i class="fa-regular fa-eye"></i></button>
                        </div>
                        <div class="field-hint" id="matchHint" hidden><i class="fa-solid fa-circle-exclamation"></i><span>รหัสผ่านทั้งสองช่องยังไม่ตรงกัน</span></div>
                    </div>
                    <button class="btn btn-primary w-100 btn-lg" type="submit" id="resetSubmit"><i class="fa-solid fa-floppy-disk me-2"></i>บันทึกรหัสผ่านใหม่</button>
                </form>
                <script>
                (function () {
                    var form = document.getElementById('resetForm');
                    if (!form) return;
                    var password = document.getElementById('password');
                    var confirmation = document.getElementById('password_confirmation');
                    var rules = document.getElementById('passwordRules');
                    var meter = document.getElementById('passwordMeter');
                    var meterLabel = document.getElementById('passwordMeterLabel');
                    var matchHint = document.getElementById('matchHint');
                    var labels = ['', 'อ่อนมาก', 'พอใช้', 'ดี', 'แข็งแรง'];

                    function setRule(name, state) {
                        var row = rules.querySelector('[data-rule="' + name + '"]');
                        var icon = row.querySelector('i');
                        row.classList.toggle('is-valid', state === 'valid');
                        row.classList.toggle('is-invalid', state === 'invalid');
                        icon.className = state === 'valid' ? 'fa-solid fa-circle-check'
                            : state === 'invalid' ? 'fa-solid fa-circle-xmark'
                            : 'fa-regular fa-circle';
                    }

                    function strengthOf(value) {
                        if (!value) return 0;
                        var score = 0;
                        if (value.length >= 12) score++;
                        if (value.length >= 16) score++;
                        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
                        if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) score++;
                        return Math.max(1, Math.min(score, 4));
                    }

                    // "touched" keeps the form quiet until the user has actually typed in a field.
                    function evaluate(touched) {
                        var value = password.value;
                        var confirmValue = confirmation.value;
                        var longEnough = value.length >= 12;
                        var varied = /[A-Za-z]/.test(value) && /\d/.test(value);
                        var matched = value !== '' && value === confirmValue;

                        setRule('length', value === '' ? 'idle' : longEnough ? 'valid' : 'invalid');
                        setRule('variety', value === '' ? 'idle' : varied ? 'valid' : 'idle');
                        setRule('match', confirmValue === '' ? 'idle' : matched ? 'valid' : 'invalid');

                        var level = strengthOf(value);
                        meter.hidden = value === '';
                        meter.dataset.level = String(level);
                        meterLabel.textContent = labels[level];

                        password.classList.toggle('is-valid', longEnough);
                        password.classList.toggle('is-invalid', touched === 'password' && value !== '' && !longEnough);
                        confirmation.classList.toggle('is-valid', matched && longEnough);
                        confirmation.classList.toggle('is-invalid', confirmValue !== '' && !matched);
                        matchHint.hidden = confirmValue === '' || matched;

                        return longEnough && matched;
                    }

                    password.addEventListener('input', function () { evaluate('password'); });
                    confirmation.addEventListener('input', function () { evaluate('confirmation'); });

                    form.addEventListener('submit', function (event) {
                        if (!evaluate('all')) {
                            event.preventDefault();
                            (password.value.length >= 12 ? confirmation : password).focus();
                        }
                    });

                    evaluate(null);
                })();
                </script>
            <?php else: ?>
                <div class="mt-5 mb-4"><h2>ลิงก์ใช้ไม่ได้</h2><p class="text-secondary mb-0">ลิงก์ตั้งรหัสผ่านนี้ไม่ถูกต้อง หมดอายุ หรือถูกใช้ไปแล้ว</p></div>
                <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>กรุณาติดต่อผู้ดูแลระบบเพื่อขอลิงก์ใหม่อีกครั้ง</div>
            <?php endif; ?>
            <p class="login-help"><a class="text-decoration-none" href="<?= e(url('/login')) ?>"><i class="fa-solid fa-arrow-left me-2"></i>กลับหน้าเข้าสู่ระบบ</a></p>
        </div>
    </section>
</main>
