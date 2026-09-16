<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'ระบบติดตามสถานะงานติดตั้งลูกค้า'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', 'http://localhost/cap-plus/public'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Bangkok'),
    'session_name' => env('SESSION_NAME', 'cap_plus_session'),
];

