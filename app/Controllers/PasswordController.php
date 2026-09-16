<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;

final class PasswordController
{
    public function edit(): void
    {
        View::render('auth/change-password', ['pageTitle' => 'ตั้งรหัสผ่านใหม่']);
    }

    public function update(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
            flash('error', 'Password ต้องมีอย่างน้อย 12 ตัวอักษร และต้องตรงกัน');
            redirect('/change-password');
        }
        $user = (new UserRepository())->find((int) Auth::id());
        if (!$user || password_verify($password, $user['password'])) {
            flash('error', 'กรุณาตั้ง Password ใหม่ที่แตกต่างจากรหัสชั่วคราว');
            redirect('/change-password');
        }
        (new UserRepository())->changePassword((int) Auth::id(), password_hash($password, PASSWORD_DEFAULT));
        $_SESSION['auth']['must_change_password'] = false;
        (new ActivityLogger())->log('change_password', 'user', Auth::id(), 'เปลี่ยนรหัสผ่านครั้งแรก');
        flash('success', 'ตั้งรหัสผ่านใหม่เรียบร้อย');
        redirect(Auth::landingPath());
    }
}
