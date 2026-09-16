<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Services\ActivityLogger;
use App\Services\AuthService;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(Auth::landingPath());
        }
        View::render('auth/login', [], 'layouts/guest');
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            flash('error', 'แบบฟอร์มหมดอายุ กรุณาลองใหม่');
            redirect('/login');
        }
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $_SESSION['_old'] = ['username' => $username];
        $authService = new AuthService();
        if ($username !== '' && $authService->isLocked($username)) {
            flash('error', 'บัญชีนี้ถูกระงับชั่วคราว เนื่องจากกรอกรหัสผ่านผิดเกิน 5 ครั้ง กรุณารอ 15 นาทีแล้วลองใหม่ หรือติดต่อ Admin เพื่อปลดล็อกบัญชี');
            usleep(300000);
            redirect('/login');
        }
        if ($username === '' || $password === '' || !$authService->attempt($username, $password)) {
            flash('error', 'Username หรือ Password ไม่ถูกต้อง');
            usleep(300000);
            redirect('/login');
        }
        unset($_SESSION['_old']);
        redirect(Auth::landingPath());
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
        (new ActivityLogger())->log('logout', 'user', Auth::id(), 'ออกจากระบบ');
        Auth::logout();
        redirect('/login');
    }
}
