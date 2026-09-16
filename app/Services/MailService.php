<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function sendPasswordResetRequest(array $user): bool
    {
        $mode = (string) config('mail.password_reset_mode', 'test');
        $recipient = match ($mode) {
            'user' => (string) ($user['email'] ?? ''),
            'admin' => $this->passwordResetAdminRecipient(),
            default => (string) config('mail.test_recipient', ''),
        };

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('[mail] Password-reset recipient is not configured.');
            return false;
        }

        $username = $this->escape((string) ($user['username'] ?? '-'));
        $name = $this->escape((string) ($user['name'] ?? '-'));
        $email = $this->escape((string) ($user['email'] ?? '-'));
        $userId = (int) ($user['id'] ?? 0);
        $adminLink = $userId > 0 ? url('/admin/users?reset_user=' . $userId) : url('/admin/users');
        $body = $this->template(
            'คำขอรีเซ็ตรหัสผ่าน',
            '<p>มีคำขอรีเซ็ตรหัสผ่านจากผู้ใช้งานในระบบ Status working</p>'
            . '<table><tr><th>ชื่อผู้ใช้</th><td>' . $username . '</td></tr>'
            . '<tr><th>ชื่อ-นามสกุล</th><td>' . $name . '</td></tr>'
            . '<tr><th>อีเมล</th><td>' . $email . '</td></tr>'
            . '<tr><th>เวลา</th><td>' . $this->escape(date('d/m/Y H:i')) . '</td></tr></table>'
            . '<p style="margin:24px 0"><a href="' . $this->escape($adminLink) . '" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:bold">เปิดหน้าจัดการเพื่อรีเซ็ตรหัส</a></p>'
            . '<p>กรุณาตรวจสอบและดำเนินการตามขั้นตอนขององค์กร</p>'
        );

        return $this->send($recipient, 'Status working | คำขอรีเซ็ตรหัสผ่าน', $body, 'มีคำขอรีเซ็ตรหัสผ่านจาก ' . ($user['username'] ?? '-'));
    }

    /** Sends the set-your-own-password link straight to the user whose password an Admin reset. */
    public function sendPasswordResetLink(array $user, string $link, string $expiresAt): bool
    {
        $recipient = (string) ($user['email'] ?? '');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('[mail] User has no valid email address for the password reset link.');
            return false;
        }

        $body = $this->template(
            'ตั้งรหัสผ่านใหม่',
            '<p>ผู้ดูแลระบบได้รีเซ็ตรหัสผ่านบัญชีของคุณในระบบ Status working</p>'
            . '<table><tr><th>ชื่อผู้ใช้</th><td>' . $this->escape((string) ($user['username'] ?? '-')) . '</td></tr>'
            . '<tr><th>ชื่อ-นามสกุล</th><td>' . $this->escape((string) ($user['name'] ?? '-')) . '</td></tr>'
            . '<tr><th>ลิงก์ใช้ได้ถึง</th><td>' . $this->escape($expiresAt) . '</td></tr></table>'
            . '<p style="margin:24px 0"><a href="' . $this->escape($link) . '" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:bold">ตั้งรหัสผ่านใหม่</a></p>'
            . '<p>หากปุ่มด้านบนกดไม่ได้ ให้คัดลอกลิงก์นี้ไปวางในเบราว์เซอร์:<br><span style="word-break:break-all;color:#486581">' . $this->escape($link) . '</span></p>'
            . '<p>ลิงก์นี้ใช้ได้เพียงครั้งเดียว หากคุณไม่ได้ร้องขอ กรุณาแจ้งผู้ดูแลระบบทันที</p>'
        );

        return $this->send($recipient, 'Status working | ตั้งรหัสผ่านใหม่', $body, 'ตั้งรหัสผ่านใหม่ได้ที่: ' . $link);
    }

    public function sendCustomerSavedToAdmins(array $customer, ?array $actor, bool $isNew): bool
    {
        $recipients = $this->adminRecipients();
        if ($recipients === []) {
            error_log('[mail] No Admin email recipient is configured.');
            return false;
        }

        $customerName = $this->escape((string) ($customer['name'] ?? '-'));
        $savedBy = $this->escape((string) ($actor['name'] ?? $actor['username'] ?? '-'));
        $status = $this->escape((string) ($customer['status'] ?? 'STATUS'));
        $action = $isNew ? 'เพิ่มข้อมูลลูกค้าใหม่' : 'บันทึกการแก้ไขข้อมูลงานติดตั้ง';
        $body = $this->template(
            $action,
            '<p>' . $action . ' ในระบบ Status working</p>'
            . '<table><tr><th>รายชื่อลูกค้า</th><td>' . $customerName . '</td></tr>'
            . '<tr><th>Zone</th><td>' . $this->escape((string) ($customer['zone'] ?? '-')) . '</td></tr>'
            . '<tr><th>ขนาดเครื่อง</th><td>' . $this->escape((string) ($customer['machine_size'] ?? '-')) . '</td></tr>'
            . '<tr><th>Status</th><td>' . $status . '</td></tr>'
            . '<tr><th>บันทึกโดย</th><td>' . $savedBy . '</td></tr>'
            . '<tr><th>เวลา</th><td>' . $this->escape(date('d/m/Y H:i')) . '</td></tr></table>'
            . '<p><a href="' . $this->escape(url('/customers/' . rawurlencode((string) ($customer['id'] ?? '')))) . '">เปิดดูข้อมูลใน Status working</a></p>'
        );

        return $this->sendMany($recipients, 'Status working | ' . $action . ': ' . ($customer['name'] ?? '-'), $body, $action . ' - ' . ($customer['name'] ?? '-'));
    }

    private function adminRecipients(): array
    {
        $emails = (new UserRepository())->adminEmails();
        $fallback = preg_split('/\s*,\s*/', (string) config('mail.admin_recipients', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $emails = array_merge($emails, $fallback);
        $emails = array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
        return array_values(array_unique(array_map('strtolower', $emails)));
    }

    private function passwordResetAdminRecipient(): string
    {
        $configuredRecipient = trim((string) config('mail.password_reset_admin_recipient', ''));
        if (filter_var($configuredRecipient, FILTER_VALIDATE_EMAIL)) {
            return $configuredRecipient;
        }

        return (new UserRepository())->primaryAdminEmail() ?? '';
    }

    private function sendMany(array $recipients, string $subject, string $html, string $plain): bool
    {
        $sent = false;
        foreach ($recipients as $recipient) {
            $sent = $this->send($recipient, $subject, $html, $plain) || $sent;
        }
        return $sent;
    }

    private function send(string $recipient, string $subject, string $html, string $plain): bool
    {
        if (!$this->isConfigured()) {
            error_log('[mail] SMTP is not configured; message was not sent.');
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) config('mail.host');
            $mail->Port = (int) config('mail.port', 587);
            $mail->SMTPAuth = true;
            $mail->Username = (string) config('mail.username');
            $mail->Password = (string) config('mail.password');
            $mail->Timeout = 10;
            $encryption = strtolower((string) config('mail.encryption', 'tls'));
            $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom((string) config('mail.from_address'), (string) config('mail.from_name'));
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $plain;
            $mail->send();
            return true;
        } catch (Exception $exception) {
            error_log('[mail] Delivery failed: ' . $exception->getMessage());
            return false;
        }
    }

    private function isConfigured(): bool
    {
        return (bool) config('mail.enabled')
            && filter_var((string) config('mail.from_address'), FILTER_VALIDATE_EMAIL)
            && (string) config('mail.host') !== ''
            && (string) config('mail.username') !== ''
            && (string) config('mail.password') !== '';
    }

    private function template(string $heading, string $content): string
    {
        return '<!doctype html><html lang="th"><body style="margin:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#243b53">'
            . '<main style="max-width:620px;margin:24px auto;background:#ffffff;border:1px solid #e3eaf2;border-radius:14px;overflow:hidden">'
            . '<header style="padding:22px 28px;background:#102a43;color:#ffffff"><strong style="font-size:20px">Status working</strong><span style="margin-left:8px;color:#bcccdc">Installation Tracker</span></header>'
            . '<section style="padding:28px"><h1 style="margin:0 0 18px;font-size:22px;color:#102a43">' . $this->escape($heading) . '</h1>' . $content . '</section>'
            . '<footer style="padding:16px 28px;border-top:1px solid #e3eaf2;color:#627d98;font-size:12px">อีเมลนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ</footer>'
            . '</main></body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
