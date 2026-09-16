<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

final class SessionController
{
    public function status(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!Auth::check() || !Auth::sessionIsCurrent()) {
            Auth::logout();
            http_response_code(401);
            echo json_encode(['active' => false]);
            return;
        }
        echo json_encode(['active' => true]);
    }
}
