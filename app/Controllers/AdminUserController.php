<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;
use App\Services\MailService;

final class AdminUserController
{
    private const RESET_LINK_LIFETIME_MINUTES = 60;

    public function index(): void
    {
        View::render('admin/users/index', [
            'users' => (new UserRepository())->all(),
            'roles' => (new RoleRepository())->all(),
            'companies' => (new CompanyRepository())->all(),
            'pageTitle' => 'จัดการผู้ใช้งาน',
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->validatedUser(true);
        if ((new UserRepository())->usernameExists($data['username'])) {
            flash('error', 'Username นี้ถูกใช้งานแล้ว');
            redirect('/admin/users');
        }
        $id = (new UserRepository())->create([
            'role_id' => $data['role_id'],
            'company_id' => $data['company_id'],
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'is_active' => 1,
        ]);
        (new ActivityLogger())->log('create', 'user', $id, 'เพิ่มผู้ใช้งาน', null, ['username' => $data['username'], 'role_id' => $data['role_id'], 'company_id' => $data['company_id']]);
        flash('success', 'เพิ่มผู้ใช้งานเรียบร้อย');
        redirect('/admin/users');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $userId = (int) $id;
        $repository = new UserRepository();
        $old = $repository->find($userId);
        if (!$old) {
            http_response_code(404);
            exit('ไม่พบผู้ใช้งาน');
        }
        $data = $this->validatedUser(false);
        if ((new UserRepository())->usernameExists($data['username'], $userId)) {
            flash('error', 'Username นี้ถูกใช้งานแล้ว');
            redirect('/admin/users');
        }
        $repository->update($userId, [
            'username' => $data['username'],
            'role_id' => $data['role_id'],
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
        ]);
        (new ActivityLogger())->log('update', 'user', $userId, 'แก้ไขผู้ใช้งาน', ['role_id' => $old['role_id'], 'company_id' => $old['company_id'], 'is_active' => $old['is_active']], ['role_id' => $data['role_id'], 'company_id' => $data['company_id'], 'is_active' => $data['is_active']]);
        flash('success', 'แก้ไขผู้ใช้งานเรียบร้อย');
        redirect('/admin/users');
    }

    public function toggle(string $id): void
    {
        $this->verifyCsrf();
        $userId = (int) $id;
        if ($userId === Auth::id()) {
            flash('error', 'ไม่สามารถปิดบัญชีของตนเอง');
            redirect('/admin/users');
        }
        $active = ($_POST['active'] ?? '0') === '1';
        (new UserRepository())->toggle($userId, $active);
        (new ActivityLogger())->log($active ? 'activate' : 'deactivate', 'user', $userId, $active ? 'เปิดใช้งานผู้ใช้' : 'ปิดใช้งานผู้ใช้');
        flash('success', 'ปรับสถานะผู้ใช้งานเรียบร้อย');
        redirect('/admin/users');
    }

    /** Emails the user a single-use link so they can set their own password. */
    public function sendResetLink(string $id): void
    {
        $this->verifyCsrf();
        $user = (new UserRepository())->find((int) $id);
        if (!$user) {
            http_response_code(404);
            exit('ไม่พบผู้ใช้งาน');
        }
        if (!filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            flash('error', 'ผู้ใช้รายนี้ยังไม่มีอีเมล กรุณาเพิ่มอีเมลก่อนส่งลิงก์ตั้งรหัสผ่าน');
            redirect('/admin/users');
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify('+' . self::RESET_LINK_LIFETIME_MINUTES . ' minutes');
        (new PasswordResetRepository())->issue(
            (int) $user['id'],
            PasswordResetRepository::hash($token),
            $expiresAt->format('Y-m-d H:i:s'),
            Auth::id()
        );

        $sent = (new MailService())->sendPasswordResetLink($user, url('/reset-password?token=' . $token), $expiresAt->format('d/m/Y H:i'));
        (new ActivityLogger())->log('send_reset_link', 'user', (int) $user['id'], $sent ? 'ส่งลิงก์ตั้งรหัสผ่านใหม่ทางอีเมล' : 'ส่งลิงก์ตั้งรหัสผ่านใหม่ไม่สำเร็จ', null, ['email' => $user['email']]);

        if (!$sent) {
            flash('error', 'ส่งอีเมลไม่สำเร็จ กรุณาตรวจสอบการตั้งค่า SMTP แล้วลองใหม่');
            redirect('/admin/users');
        }
        flash('success', 'ส่งลิงก์ตั้งรหัสผ่านใหม่ไปที่ ' . $user['email'] . ' เรียบร้อย (ลิงก์ใช้ได้ ' . self::RESET_LINK_LIFETIME_MINUTES . ' นาที)');
        redirect('/admin/users');
    }

    public function resetPassword(string $id): void
    {
        $this->verifyCsrf();
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 12) {
            flash('error', 'Password ต้องมีอย่างน้อย 12 ตัวอักษร');
            redirect('/admin/users');
        }
        (new UserRepository())->resetPassword((int) $id, password_hash($password, PASSWORD_DEFAULT));
        (new ActivityLogger())->log('reset_password', 'user', (int) $id, 'Reset Password โดยผู้ดูแลระบบ');
        flash('success', 'Reset Password เรียบร้อย');
        redirect('/admin/users');
    }

    public function unlock(string $id): void
    {
        $this->verifyCsrf();
        $user = (new UserRepository())->find((int) $id);
        if ($user) {
            (new UserRepository())->clearLoginFailures((string) $user['username']);
            (new ActivityLogger())->log('unlock_login', 'user', (int) $id, 'ปลดล็อกบัญชีจากการกรอกรหัสผ่านผิดเกินกำหนด');
        }
        flash('success', 'ปลดล็อกบัญชีเรียบร้อยแล้ว');
        redirect('/admin/users');
    }

    private function validatedUser(bool $creating): array
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || !$roleId || !preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) || ($creating && strlen($password) < 12)) {
            flash('error', 'ข้อมูลไม่ครบถ้วน Username ต้องเป็นตัวอักษรอังกฤษ/ตัวเลข และ Password อย่างน้อย 12 ตัว');
            redirect('/admin/users');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'รูปแบบอีเมลไม่ถูกต้อง');
            redirect('/admin/users');
        }
        $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
        return ['username' => $username, 'name' => mb_substr($name, 0, 150), 'email' => $email ?: null, 'password' => $password, 'role_id' => (int) $roleId, 'company_id' => $companyId ?: null, 'is_active' => isset($_POST['is_active']) ? 1 : 0];
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
