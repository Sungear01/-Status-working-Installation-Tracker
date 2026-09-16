<?php

declare(strict_types=1);

$authenticated = (bool) ($authenticated ?? false);
$referenceId = (string) ($referenceId ?? 'ERR-UNKNOWN');
$backUrl = (string) ($backUrl ?? url($authenticated ? '/dashboard' : '/login'));
$homeUrl = (string) ($homeUrl ?? url($authenticated ? '/dashboard' : '/login'));
$homeLabel = (string) ($homeLabel ?? ($authenticated ? 'กลับ Dashboard' : 'กลับหน้า Login'));
?>

<div class="error-page" role="main">
    <section class="error-card" aria-labelledby="error-title">
        <div class="error-icon" aria-hidden="true">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <span class="error-code">ERROR 500</span>
        <h1 id="error-title">ระบบไม่สามารถดำเนินการได้</h1>
        <p class="error-message">
            เกิดข้อผิดพลาดชั่วคราว กรุณาลองใหม่อีกครั้ง หากยังพบปัญหาให้ติดต่อผู้ดูแลระบบ
        </p>

        <div class="error-progress" role="status" aria-live="polite">
            <span class="progress-dot" aria-hidden="true"></span>
            <span>กำลังตรวจสอบระบบ</span>
            <span class="progress-dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>

        <div class="error-reference" role="note">
            <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
            <span>รหัสอ้างอิง: <code><?= e($referenceId) ?></code></span>
        </div>

        <div class="error-actions">
            <a class="btn btn-outline-primary" href="<?= e($backUrl) ?>">
                <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>
                ย้อนกลับหน้าก่อนหน้า
            </a>
            <a class="btn btn-primary" href="<?= e($homeUrl) ?>">
                <i class="fa-solid fa-house me-2" aria-hidden="true"></i>
                <?= e($homeLabel) ?>
            </a>
        </div>

        <p class="error-help">
            <i class="fa-solid fa-headset me-1" aria-hidden="true"></i>
            หากยังดำเนินการไม่ได้ กรุณาแจ้งรหัสอ้างอิงนี้แก่ผู้ดูแลระบบ
        </p>
    </section>
</div>

<style>
    .error-page {
        min-height: min(620px, calc(100vh - 150px));
        display: grid;
        place-items: center;
        padding: 32px 16px;
        background: #f5f7fa;
    }

    .error-card {
        width: min(100%, 680px);
        padding: clamp(28px, 5vw, 52px);
        text-align: center;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        box-shadow: 0 12px 30px rgba(16, 42, 67, .08);
    }

    .error-icon {
        width: 68px;
        height: 68px;
        margin: 0 auto 18px;
        display: grid;
        place-items: center;
        color: #dc2626;
        background: #fef2f2;
        border-radius: 18px;
        font-size: 28px;
    }

    .error-code {
        display: block;
        margin-bottom: 8px;
        color: #dc2626;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .14em;
    }

    .error-card h1 {
        margin: 0;
        color: #102a43;
        font-size: clamp(1.45rem, 3vw, 2rem);
        font-weight: 700;
    }

    .error-message {
        max-width: 500px;
        margin: 12px auto 20px;
        color: #627d98;
        line-height: 1.7;
    }

    .error-reference {
        display: flex;
        align-items: center;
        width: max-content;
        max-width: 100%;
        gap: 8px;
        margin: 0 auto;
        padding: 9px 13px;
        color: #486581;
        background: #f5f7fa;
        border: 1px solid #d9e2ec;
        border-radius: 10px;
        font-size: .9rem;
    }

    .error-progress {
        display: flex;
        align-items: center;
        gap: 8px;
        width: max-content;
        max-width: 100%;
        margin: 0 auto 14px;
        padding: 8px 13px;
        color: #627d98;
        background: #f7faff;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        font-size: .86rem;
    }

    .progress-dot {
        width: 13px;
        height: 13px;
        flex: 0 0 13px;
        border-radius: 50%;
        border: 2px solid #bfdbfe;
        border-top-color: #2563eb;
        background: transparent;
        animation: error-spin .9s linear infinite;
    }

    .progress-dots {
        display: inline-flex;
        gap: 3px;
    }

    .progress-dots i {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: #2563eb;
        animation: error-dot 1.2s ease-in-out infinite;
    }

    .progress-dots i:nth-child(2) { animation-delay: .15s; }
    .progress-dots i:nth-child(3) { animation-delay: .3s; }

    @keyframes error-pulse {
        0%, 100% { opacity: .35; transform: scale(.85); }
        50% { opacity: 1; transform: scale(1.15); }
    }

    @keyframes error-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes error-dot {
        0%, 100% { opacity: .25; transform: translateY(0); }
        50% { opacity: 1; transform: translateY(-3px); }
    }

    .error-reference code {
        color: #102a43;
        font-weight: 700;
    }

    .error-actions {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 28px;
    }

    .error-actions .btn {
        min-width: 190px;
        padding: 11px 18px;
        border-radius: 10px;
        font-weight: 600;
    }

    .error-actions .btn-primary {
        background: #2563eb;
        border-color: #2563eb;
    }

    .error-help {
        margin: 24px 0 0;
        color: #829ab1;
        font-size: .85rem;
    }

    .error-actions .btn:focus-visible {
        outline: 3px solid rgba(37, 99, 235, .35);
        outline-offset: 3px;
    }

    @media (max-width: 575.98px) {
        .error-page {
            min-height: calc(100vh - 90px);
            padding: 20px 12px;
        }

        .error-card {
            border-radius: 16px;
        }

        .error-actions,
        .error-actions .btn {
            width: 100%;
        }

        .error-help {
            line-height: 1.6;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .error-actions .btn,
        .progress-dot,
        .progress-dots i {
            animation: none !important;
            transition: none !important;
        }
    }
</style>
