<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class PermissionMiddleware
{
    public static function handle(string $permission): void
    {
        AuthMiddleware::handle();
        if (!Auth::can($permission)) {
            http_response_code(403);
            exit('403 - คุณไม่มีสิทธิ์ดำเนินการนี้');
        }
    }
}

