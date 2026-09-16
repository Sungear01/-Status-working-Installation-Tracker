<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;
use App\Services\MailService;

final class ForgotPasswordController
{
    public function show(): void
    {
        View::render('auth/forgot-password', [], 'layouts/guest');
    }

    public function request(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            redirect('/forgot-password');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $user = $username !== '' ? (new UserRepository())->findByUsername($username) : null;

        // Keep this response generic so the form cannot be used to discover accounts.
        if ($user && ($email === '' || strcasecmp((string) $user['email'], $email) === 0)) {
            (new ActivityLogger())->log('password_reset_request', 'user', (int) $user['id'], 'ผู้ใช้ส่งคำขอรีเซ็ตรหัสผ่าน', null, ['username' => $user['username']]);
            (new MailService())->sendPasswordResetRequest($user);
        }

        flash('success', 'ระบบได้รับคำขอแล้ว กรุณาติดต่อผู้ดูแลระบบเพื่อรีเซ็ตรหัสผ่าน');
        redirect('/login');
    }
}
