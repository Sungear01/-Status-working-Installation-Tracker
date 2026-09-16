<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            flash('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
            redirect('/login');
        }
        if (!Auth::sessionIsCurrent()) {
            Auth::logout();
            redirect('/login?reason=session-replaced');
        }
        if (!Auth::refreshAuthorization()) {
            Auth::logout();
            redirect('/login');
        }
        if (time() - (int) ($_SESSION['last_activity'] ?? 0) > 7200) {
            Auth::logout();
            redirect('/login');
        }
        $path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
        if (!empty($_SESSION['auth']['must_change_password']) && !str_ends_with($path, '/change-password') && !str_ends_with($path, '/logout')) {
            redirect('/change-password');
        }
        $_SESSION['last_activity'] = time();
    }
}
