<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;

final class ProfileController
{
    public function edit(): void
    {
        $user = (new UserRepository())->find((int) Auth::id());
        if (!$user) {
            Auth::logout();
            redirect('/login');
        }

        View::render('profile/index', ['profile' => $user, 'pageTitle' => 'โปรไฟล์ของฉัน']);
    }

    public function update(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }

        $id = (int) Auth::id();
        $repository = new UserRepository();
        $current = $repository->find($id);
        $username = trim((string) ($_POST['username'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if (!$current || $name === '' || !preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username)) {
            flash('error', 'กรุณากรอก Username และชื่อให้ถูกต้อง');
            redirect('/profile');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'รูปแบบ Email ไม่ถูกต้อง');
            redirect('/profile');
        }
        if ($repository->usernameExists($username, $id)) {
            flash('error', 'Username นี้ถูกใช้งานแล้ว');
            redirect('/profile');
        }

        // Password changes are deliberately handled through the Admin/IT reset flow.
        $safeName = mb_substr($name, 0, 150);
        $repository->updateProfile($id, $username, $safeName, $email !== '' ? $email : null);
        $_SESSION['auth']['username'] = $username;
        $_SESSION['auth']['name'] = $safeName;
        (new ActivityLogger())->log('update', 'user', $id, 'แก้ไขข้อมูลโปรไฟล์ของตนเอง', null, ['username' => $username, 'name' => $name, 'email' => $email]);
        flash('success', 'บันทึกข้อมูลโปรไฟล์เรียบร้อย');
        redirect('/profile');
    }
}
