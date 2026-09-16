<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Repositories\CustomerRepository;
use App\Repositories\LogRepository;

final class NotificationController
{
    public function index(): void
    {
        $items = [];
        $passwordResetCount = 0;
        if (Auth::can('manage_users')) {
            foreach ((new LogRepository())->pendingPasswordResetRequests() as $request) {
                $passwordResetCount++;
                $name = $request['name'] ?: $request['username'];
                $items[] = [
                    'key' => 'password-reset-' . $request['id'],
                    'type' => 'danger',
                    'icon' => 'fa-key',
                    'title' => 'คำขอรีเซ็ตรหัสผ่าน',
                    'detail' => $name . ' ขอรีเซ็ตรหัสผ่าน เมื่อ ' . $request['created_at'],
                    'url' => url('/admin/users'),
                ];
            }
        }
        foreach ((new CustomerRepository())->filtered([]) as $customer) {
            $due = $customer['due_60_date'] ?? null;
            try {
                $days = $due ? (int) (new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable((string) $due))->format('%r%a') : null;
            } catch (\Throwable) {
                $days = null;
            }
            $status = strtoupper(trim((string) ($customer['status'] ?? '')));
            if (in_array($status, ['YES', 'DONE', 'เสร็จแล้ว'], true)) {
                continue;
            }
            if ($days === null) {
                $items[] = ['type' => 'warning', 'icon' => 'fa-hourglass-half', 'title' => 'งานรอดำเนินการ', 'detail' => ($customer['name'] ?? 'ลูกค้า') . ' ยังไม่มีวันครบกำหนด', 'url' => url('/customers/' . rawurlencode((string) $customer['id']))];
            } elseif ($days < 0) {
                $items[] = ['type' => 'danger', 'icon' => 'fa-circle-exclamation', 'title' => 'งานเกินกำหนด', 'detail' => ($customer['name'] ?? 'ลูกค้า') . ' เกินกำหนด ' . abs($days) . ' วัน', 'url' => url('/customers/' . rawurlencode((string) $customer['id']))];
            } elseif ($days <= 7) {
                $items[] = ['type' => 'warning', 'icon' => 'fa-clock', 'title' => 'งานใกล้ครบกำหนด', 'detail' => ($customer['name'] ?? 'ลูกค้า') . ' เหลือ ' . $days . ' วัน', 'url' => url('/customers/' . rawurlencode((string) $customer['id']))];
            }
            if (count($items) >= 10) {
                break;
            }
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'count' => $passwordResetCount > 0 ? $passwordResetCount : count($items),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
    }
}
