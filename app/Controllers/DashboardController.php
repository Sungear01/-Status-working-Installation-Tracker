<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CustomerRepository;

final class DashboardController
{
    public function index(): void
    {
        $customers = new CustomerRepository();
        View::render('dashboard/index', ['summary' => $customers->dashboard(), 'charts' => $customers->chartData(), 'pageTitle' => 'Dashboard']);
    }
}

