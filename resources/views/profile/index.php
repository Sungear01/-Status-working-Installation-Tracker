<div class="page-heading">
    <div>
        <span class="eyebrow text-primary">ACCOUNT SETTINGS</span>
        <h2>โปรไฟล์ของฉัน</h2>
        <p>แก้ไขข้อมูลบัญชีสำหรับใช้งานในระบบ</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="panel">
            <div class="panel-header">
                <div><h3>ข้อมูลบัญชี</h3><p>Username, ชื่อ และ Email</p></div>
                <i class="fa-solid fa-user-pen panel-icon" aria-hidden="true"></i>
            </div>
            <form method="post" action="<?= e(url('/profile')) ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="profile_username">Username</label><input class="form-control" id="profile_username" name="username" value="<?= e($profile['username']) ?>" maxlength="100" pattern="[A-Za-z0-9._-]{3,100}" required></div>
                    <div class="col-md-6"><label class="form-label" for="profile_name">ชื่อ-นามสกุล</label><input class="form-control" id="profile_name" name="name" value="<?= e($profile['name']) ?>" maxlength="150" required></div>
                    <div class="col-12"><label class="form-label" for="profile_email">Email</label><input class="form-control" type="email" id="profile_email" name="email" value="<?= e($profile['email'] ?? '') ?>" maxlength="150"></div>
                </div>
                <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึกโปรไฟล์</button></div>
            </form>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="panel">
            <div class="panel-header"><div><h3>สิทธิ์การใช้งาน</h3><p>ข้อมูลจาก Role ปัจจุบัน</p></div><i class="fa-solid fa-shield-halved panel-icon" aria-hidden="true"></i></div>
            <div class="d-flex align-items-center gap-3"><span class="avatar"><i class="fa-solid fa-user"></i></span><div><strong><?= e($profile['name']) ?></strong><small class="d-block text-secondary"><?= e(App\Core\Auth::user()['role'] ?? '-') ?></small></div></div>
        </section>
    </div>
</div>
