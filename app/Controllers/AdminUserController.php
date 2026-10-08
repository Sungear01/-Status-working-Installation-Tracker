<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Repositories\CustomColumnRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserTableAccessRepository;
use App\Services\ActivityLogger;
use App\Services\MailService;

final class AdminUserController
{
    private const RESET_LINK_LIFETIME_MINUTES = 60;

    public function index(): void
    {
        $users = (new UserRepository())->all();
        $roles = (new RoleRepository())->all();
        if (!Auth::isDeveloper()) {
            // The system creator account and role stay invisible to everyone else.
            $users = array_values(array_filter($users, static fn (array $user): bool => $user['role_slug'] !== 'developer'));
            $roles = array_values(array_filter($roles, static fn (array $role): bool => $role['slug'] !== 'developer'));
        }
        View::render('admin/users/index', [
            'users' => $users,
            'roles' => $roles,
            'companies' => $this->companiesWithAccessColumns(),
            'tableAccess' => (new UserTableAccessRepository())->all(),
            'pageTitle' => 'จัดการผู้ใช้งาน',
        ]);
    }

    /** Save which tables a user sees and which columns they may edit in each, or put them back on the defaults. */
    public function updateTableAccess(string $id): void
    {
        $this->verifyCsrf();
        $userId = (int) $id;
        $this->guardDeveloperAccount($userId);
        $user = (new UserRepository())->findWithRole($userId);
        if (!$user) {
            http_response_code(404);
            exit('ไม่พบผู้ใช้งาน');
        }
        if (Auth::isSuperRole($user['role_slug'] ?? null)) {
            flash('error', 'Admin เห็นและแก้ไขได้ทุกตารางอยู่แล้ว ไม่ต้องกำหนดสิทธิ์');
            redirect('/admin/users');
        }
        $repository = new UserTableAccessRepository();
        $old = $repository->forUser($userId);
        if (($_POST['access_mode'] ?? 'default') !== 'custom') {
            $repository->clearForUser($userId);
            (new ActivityLogger())->log('update', 'user', $userId, 'ตั้งสิทธิ์ตารางกลับเป็นค่าเริ่มต้น', ['table_access' => $old], null);
            flash('success', 'ใช้สิทธิ์ตารางตามค่าเริ่มต้นของ ' . $user['username'] . ' แล้ว');
            redirect('/admin/users');
        }
        $tables = is_array($_POST['tables'] ?? null) ? $_POST['tables'] : [];
        $editable = is_array($_POST['editable'] ?? null) ? $_POST['editable'] : [];
        $access = [];
        foreach ($this->companiesWithAccessColumns() as $company) {
            $companyId = (int) $company['id'];
            if (empty($tables[$companyId])) {
                continue;
            }
            $posted = is_array($editable[$companyId] ?? null) ? array_map('strval', $editable[$companyId]) : [];
            $access[$companyId] = array_values(array_intersect(array_keys($company['access_columns']), $posted));
        }
        $repository->replaceForUser($userId, $access);
        (new ActivityLogger())->log('update', 'user', $userId, 'กำหนดสิทธิ์ตารางและคอลัมน์', ['table_access' => $old], ['table_access' => $access]);
        flash('success', 'บันทึกสิทธิ์ตารางของ ' . $user['username'] . ' แล้ว (เห็น ' . count($access) . ' ตาราง)');
        redirect('/admin/users');
    }

    /** Every company, each with the columns an admin can make editable on its table (see editableColumnsFor). */
    private function companiesWithAccessColumns(): array
    {
        $customColumns = new CustomColumnRepository();
        $companies = (new CompanyRepository())->all();
        foreach ($companies as &$company) {
            $company['access_columns'] = UserTableAccessRepository::editableColumnsFor($company, $customColumns->forCompany((int) $company['id']));
        }
        unset($company);
        return $companies;
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->validatedUser(true);
        $this->guardDeveloperRole($data['role_id']);
        if ((new UserRepository())->usernameExists($data['username'])) {
            flash('error', 'Username นี้ถูกใช้งานแล้ว');
            redirect('/admin/users');
        }
        $id = (new UserRepository())->create([
            'role_id' => $data['role_id'],
            'company_id' => null,
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'is_active' => 1,
        ]);
        (new ActivityLogger())->log('create', 'user', $id, 'เพิ่มผู้ใช้งาน', null, ['username' => $data['username'], 'role_id' => $data['role_id']]);
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
        $this->guardDeveloperAccount($userId);
        $data = $this->validatedUser(false);
        $this->guardDeveloperRole($data['role_id']);
        if ((new UserRepository())->usernameExists($data['username'], $userId)) {
            flash('error', 'Username นี้ถูกใช้งานแล้ว');
            redirect('/admin/users');
        }
        $repository->update($userId, [
            'username' => $data['username'],
            'role_id' => $data['role_id'],
            // The form no longer has "บริษัท" (table access replaced it), so an existing company is kept as it was.
            'company_id' => $old['company_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
        ]);
        (new ActivityLogger())->log('update', 'user', $userId, 'แก้ไขผู้ใช้งาน', ['role_id' => $old['role_id'], 'is_active' => $old['is_active']], ['role_id' => $data['role_id'], 'is_active' => $data['is_active']]);
        flash('success', 'แก้ไขผู้ใช้งานเรียบร้อย');
        redirect('/admin/users');
    }

    public function toggle(string $id): void
    {
        $this->verifyCsrf();
        $userId = (int) $id;
        $this->guardDeveloperAccount($userId);
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
        $this->guardDeveloperAccount((int) $id);
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
        $this->guardDeveloperAccount((int) $id);
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
        return ['username' => $username, 'name' => mb_substr($name, 0, 150), 'email' => $email ?: null, 'password' => $password, 'role_id' => (int) $roleId, 'is_active' => isset($_POST['is_active']) ? 1 : 0];
    }

    /** Only the system creator may change a developer account, so an admin cannot take it over. */
    private function guardDeveloperAccount(int $userId): void
    {
        $user = (new UserRepository())->findWithRole($userId);
        if ($user && $user['role_slug'] === 'developer' && !Auth::isDeveloper()) {
            http_response_code(403);
            exit('403 - คุณไม่มีสิทธิ์ดำเนินการนี้');
        }
    }

    /** Only the system creator may grant the developer role. */
    private function guardDeveloperRole(int $roleId): void
    {
        foreach ((new RoleRepository())->all() as $role) {
            if ((int) $role['id'] === $roleId && $role['slug'] === 'developer' && !Auth::isDeveloper()) {
                http_response_code(403);
                exit('403 - คุณไม่มีสิทธิ์ดำเนินการนี้');
            }
        }
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
