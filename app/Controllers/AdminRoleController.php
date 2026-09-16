<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Repositories\RoleRepository;
use App\Services\ActivityLogger;

final class AdminRoleController
{
    public function index(): void
    {
        $repository = new RoleRepository();
        $roles = $repository->all();
        foreach ($roles as &$role) {
            $role['permission_ids'] = $repository->permissionIds((int) $role['id']);
        }
        View::render('admin/roles/index', ['roles' => $roles, 'permissions' => $repository->permissions(), 'pageTitle' => 'Role และ Permission']);
    }

    public function update(string $id): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
        $permissionIds = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
        (new RoleRepository())->syncPermissions((int) $id, $permissionIds);
        (new ActivityLogger())->log('sync_permissions', 'role', (int) $id, 'แก้ไขสิทธิ์ของ Role', null, ['permission_ids' => array_map('intval', $permissionIds)]);
        flash('success', 'บันทึก Permission เรียบร้อย ผู้ใช้ที่กำลังออนไลน์จะได้รับสิทธิ์ใหม่ในการ Login ครั้งถัดไป');
        redirect('/admin/roles');
    }
}

