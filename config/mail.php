<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('MAIL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'host' => env('MAIL_HOST', ''),
    'port' => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username' => env('MAIL_USERNAME', ''),
    'password' => env('MAIL_PASSWORD', ''),
    'from_address' => env('MAIL_FROM_ADDRESS', ''),
    'from_name' => env('MAIL_FROM_NAME', 'Status working Installation Tracker'),
    // Keep this as "test" until email delivery has been verified.
    'password_reset_mode' => env('MAIL_PASSWORD_RESET_MODE', 'test'),
    'test_recipient' => env('MAIL_TEST_RECIPIENT', 'Dnaigook@gmail.com'),
    'password_reset_admin_recipient' => env('MAIL_PASSWORD_RESET_ADMIN_RECIPIENT', ''),
    // Comma-separated fallback addresses when an Admin account has no email address.
    'admin_recipients' => env('MAIL_ADMIN_RECIPIENTS', ''),
];
