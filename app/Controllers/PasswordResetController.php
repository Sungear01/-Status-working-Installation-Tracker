<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;

final class PasswordResetController
{
    public function show(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $reset = $this->findReset($token);
        View::render('auth/reset-password', [
            'token' => $token,
            'username' => $reset['username'] ?? null,
            'isValid' => $reset !== null,
        ], 'layouts/guest');
    }

    public function update(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }

        $token = (string) ($_POST['token'] ?? '');
        $reset = $this->findReset($token);
        if ($reset === null) {
            flash('error', 'ลิงก์ตั้งรหัสผ่านไม่ถูกต้อง หมดอายุ หรือถูกใช้ไปแล้ว');
            redirect('/reset-password?token=' . rawurlencode($token));
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
            flash('error', 'Password ต้องมีอย่างน้อย 12 ตัวอักษร และต้องตรงกัน');
            redirect('/reset-password?token=' . rawurlencode($token));
        }

        $repository = new UserRepository();
        $repository->changePassword((int) $reset['user_id'], password_hash($password, PASSWORD_DEFAULT));
        // Any session still signed in with the old password is dropped.
        $repository->setSessionToken((int) $reset['user_id'], bin2hex(random_bytes(32)));
        (new PasswordResetRepository())->markUsed((int) $reset['id']);
        (new ActivityLogger())->log('reset_password_link', 'user', (int) $reset['user_id'], 'ผู้ใช้ตั้งรหัสผ่านใหม่ผ่านลิงก์ทางอีเมล');

        flash('success', 'ตั้งรหัสผ่านใหม่เรียบร้อย กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
        redirect('/login');
    }

    private function findReset(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return (new PasswordResetRepository())->findUsable(PasswordResetRepository::hash($token));
    }
}
