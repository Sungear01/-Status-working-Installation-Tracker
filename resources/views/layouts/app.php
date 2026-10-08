<?php $authUser = App\Core\Auth::user(); ?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="app-url" content="<?= e(rtrim(url('/'), '/')) ?>">
    <meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
    <title><?= e(($pageTitle ?? '') . ' | ' . config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/app.css?v=' . (string) @filemtime(base_path('public/assets/css/app.css')))) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="เมนูหลัก">
        <a href="<?= e(url(App\Core\Auth::landingPath())) ?>" class="brand text-decoration-none">
            <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
            <span><strong>Status working</strong><small>Installation Tracker</small></span>
        </a>
        <nav class="sidebar-nav">
            <p class="nav-label">ภาพรวม</p>
            <?php if (can('view_dashboard')): ?><a href="<?= e(url('/dashboard')) ?>" class="nav-item"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a><?php endif; ?>
            <?php if (can('view_customer')): ?><a href="<?= e(url('/customers')) ?>" class="nav-item"><i class="fa-solid fa-users-viewfinder"></i><span>ข้อมูลลูกค้า</span></a><?php endif; ?>
            <?php if (can('manage_users') || can('view_activity_log')): ?>
                <p class="nav-label mt-4">ผู้ดูแลระบบ</p>
                <?php if (can('manage_users')): ?><a href="<?= e(url('/admin/users')) ?>" class="nav-item"><i class="fa-solid fa-user-gear"></i><span>ผู้ใช้งาน</span></a><?php endif; ?>
                <?php if (can('manage_users')): ?><a href="<?= e(url('/admin/roles')) ?>" class="nav-item"><i class="fa-solid fa-shield-halved"></i><span>Role & Permission</span></a><?php endif; ?>
                <?php if (can('view_activity_log')): ?><a href="<?= e(url('/admin/logs')) ?>" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i><span>ประวัติการใช้งาน</span></a><?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer"><i class="fa-solid fa-lock me-2"></i>Internal use only</div>
    </aside>
    <div class="main-area">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="icon-button d-lg-none" id="menuToggle" type="button" aria-label="เปิดเมนู"><i class="fa-solid fa-bars"></i></button>
                <div><h1><?= e($pageTitle ?? config('app.name')) ?></h1><p class="mb-0 d-none d-md-block"><?= e(config('app.name')) ?></p></div>
            </div>
                <div class="topbar-actions">
                    <div class="notification-wrap">
                        <button class="icon-button position-relative" id="notificationButton" type="button" aria-label="การแจ้งเตือน" aria-expanded="false" aria-controls="notificationPanel" title="การแจ้งเตือน"><i class="fa-regular fa-bell"></i><span class="notification-dot d-none"></span><span class="notification-count d-none">0</span></button>
                        <div class="notification-panel" id="notificationPanel" role="status" aria-live="polite" hidden>
                            <div class="notification-head"><strong>การแจ้งเตือน</strong><button type="button" class="notification-close" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></div>
                            <div class="notification-list"><div class="notification-empty">กำลังโหลดการแจ้งเตือน...</div></div>
                        </div>
                    </div>
                <a class="user-meta d-none d-sm-flex text-decoration-none text-reset" href="<?= e(url('/profile')) ?>" title="แก้ไขโปรไฟล์"><span class="avatar"><i class="fa-solid fa-user"></i></span><span><strong><?= e($authUser['name'] ?? '') ?></strong><small><?= e($authUser['role'] ?? '') ?></small></span></a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid fa-right-from-bracket me-1"></i><span class="d-none d-md-inline">Logout</span></button></form>
            </div>
        </header>
        <main class="content-area">
            <?php if ($impersonator = App\Core\Auth::impersonator()): ?><div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status"><span><i class="fa-solid fa-user-secret me-2"></i>คุณกำลังใช้งานในนาม: <strong><?= e($authUser['name'] ?? '') ?></strong> (<?= e($authUser['username'] ?? '') ?>) — ทุกการแก้ไขจะถูกบันทึกว่าทำโดยผู้สร้างระบบ</span><form method="post" action="<?= e(url('/impersonate/stop')) ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-dark" type="submit"><i class="fa-solid fa-arrow-rotate-left me-1"></i>กลับเป็นผู้สร้างระบบ</button></form></div><?php endif; ?>
            <?php if ($message = flash('success')): ?><div class="system-toast system-toast-static" role="status"><span class="system-toast-icon"><i class="fa-solid fa-check"></i></span><span class="system-toast-body"><strong>ดำเนินการสำเร็จ</strong><small><?= e($message) ?></small></span><button type="button" class="system-toast-close" data-bs-dismiss="alert" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></div><?php endif; ?>
            <?php if ($message = flash('error')): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<style>
.notification-wrap{position:relative}.notification-panel{position:absolute;top:calc(100% + 12px);right:0;width:min(360px,calc(100vw - 24px));background:#fff;border:1px solid #d9e2ec;border-radius:14px;box-shadow:0 16px 36px rgba(16,42,67,.16);z-index:2100;overflow:hidden}.notification-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #edf2f7;color:#102a43}.notification-close{border:0;background:transparent;color:#829ab1}.notification-list{max-height:340px;overflow:auto}.notification-empty{padding:24px 16px;text-align:center;color:#829ab1;font-size:13px}.notification-item{display:flex;gap:10px;padding:12px 16px;color:#486581;text-decoration:none;border-bottom:1px solid #f0f4f8}.notification-item:hover{background:#f7faff}.notification-item-icon{width:30px;height:30px;display:grid;place-items:center;flex:none;border-radius:50%}.notification-item-danger .notification-item-icon{color:#dc2626;background:#fef2f2}.notification-item-warning .notification-item-icon{color:#b45309;background:#fffbeb}.notification-item strong{display:block;color:#102a43;font-size:13px}.notification-item small{display:block;line-height:1.45}.notification-count{position:absolute;top:-5px;right:-5px;min-width:17px;height:17px;padding:0 4px;border-radius:99px;background:#dc2626;color:#fff;font-size:10px;line-height:17px;text-align:center}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?= e(url('/assets/js/app.js?v=' . (string) @filemtime(base_path('public/assets/js/app.js')))) ?>"></script>
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content confirm-modal"><div class="modal-body text-center p-4"><div class="confirm-icon"><i class="fa-solid fa-trash-can"></i></div><h2 class="h5 mb-2" id="confirmActionTitle">ยืนยันการดำเนินการ</h2><p class="text-secondary small mb-0" id="confirmActionDetail">กรุณาตรวจสอบก่อนยืนยัน</p></div><div class="modal-footer justify-content-center gap-2"><button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">ยกเลิก</button><button type="button" class="btn btn-danger px-4" id="confirmActionSubmit"><i class="fa-solid fa-trash-can me-1"></i>ยืนยันการลบ</button></div></div></div></div>
<style>.confirm-modal{border:0;border-radius:16px;box-shadow:0 20px 55px rgba(16,42,67,.22)}.confirm-icon{width:54px;height:54px;display:grid;place-items:center;margin:0 auto 14px;border-radius:50%;background:#FDECEC;color:#DC2626;font-size:21px}.confirm-modal .modal-footer{border-top:1px solid #D9E2EC}.system-toast{position:fixed;top:92px;right:24px;z-index:2000;display:flex;align-items:center;gap:11px;min-width:300px;max-width:390px;padding:13px 14px;border:1px solid #A7E3BC;border-radius:12px;background:#F3FFF7;color:#14532D;box-shadow:0 12px 30px rgba(16,42,67,.16);animation:toast-in .22s ease-out}.system-toast-icon{width:30px;height:30px;display:grid;place-items:center;flex:none;border-radius:50%;background:#DCFCE7;color:#16A34A}.system-toast-body{display:flex;flex:1;flex-direction:column;gap:2px}.system-toast-body strong{font-size:13px}.system-toast-body small{color:#47705A;font-size:11px;line-height:1.4}.system-toast-close{padding:4px;border:0;background:transparent;color:#6B8A78}.system-toast-close:hover{color:#14532D}.system-toast-static{position:relative;top:auto;right:auto;margin-bottom:18px}.system-toast-static .system-toast-close{margin-left:auto}@keyframes toast-in{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}@media(max-width:575px){.system-toast{right:12px;left:12px;min-width:0;max-width:none;top:82px}}</style>
<?= $scripts ?? '' ?>
</body>
</html>
