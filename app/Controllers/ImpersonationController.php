<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;

/** Lets the system creator (developer role) use the system as another user to reproduce bugs. */
final class ImpersonationController
{
    public function start(string $id): void
    {
        $this->verifyCsrf();
        if (!Auth::isDeveloper()) {
            http_response_code(403);
            exit('403 - เฉพาะผู้สร้างระบบเท่านั้น');
        }
        $repository = new UserRepository();
        $target = $repository->findWithRole((int) $id);
        if (!$target || (int) $target['id'] === Auth::id() || $target['role_slug'] === 'developer') {
            flash('error', 'ไม่สามารถเข้าใช้ในนามผู้ใช้นี้ได้');
            redirect('/admin/users');
        }
        if (!(bool) $target['is_active']) {
            flash('error', 'บัญชีนี้ถูกปิดใช้งาน กรุณาเปิดใช้งานก่อน');
            redirect('/admin/users');
        }
        (new ActivityLogger())->log('impersonate_start', 'user', (int) $target['id'], 'ผู้สร้างระบบเข้าใช้งานในนาม ' . $target['username']);
        Auth::startImpersonation($target, $repository->permissions((int) $target['id'], (int) $target['role_id']));
        flash('success', 'คุณกำลังใช้งานในนาม ' . $target['name']);
        redirect(Auth::landingPath());
    }

    public function stop(): void
    {
        $this->verifyCsrf();
        $targetId = Auth::id();
        $impersonator = Auth::endImpersonation();
        if ($impersonator === null) {
            redirect(Auth::landingPath());
        }
        $repository = new UserRepository();
        $developer = $repository->findWithRole((int) $impersonator['id']);
        if (!$developer || !(bool) $developer['is_active'] || $developer['role_slug'] !== 'developer') {
            Auth::logout();
            redirect('/login');
        }
        Auth::login($developer, $repository->permissions((int) $developer['id'], (int) $developer['role_id']), (string) ($impersonator['session_token'] ?? ''));
        (new ActivityLogger())->log('impersonate_stop', 'user', $targetId, 'ผู้สร้างระบบออกจากการใช้งานในนามผู้ใช้');
        flash('success', 'กลับเป็นผู้สร้างระบบเรียบร้อย');
        redirect('/admin/users');
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
