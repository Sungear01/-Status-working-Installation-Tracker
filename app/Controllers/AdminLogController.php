<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\LogRepository;

final class AdminLogController
{
    public function index(): void
    {
        $repository = new LogRepository();
        View::render('admin/logs/index', ['activities' => $repository->activities(), 'logins' => $repository->logins(), 'pageTitle' => 'ประวัติการใช้งาน']);
    }
}

