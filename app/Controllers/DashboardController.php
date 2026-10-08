<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Repositories\CompanyRepository;
use App\Repositories\CustomerRepository;
use App\Services\DashboardStats;

final class DashboardController
{
    public function index(): void
    {
        $customers = new CustomerRepository();
        $stats = new DashboardStats();

        // One board per company table the user may see, each counted from exactly what that table lists.
        $companies = (new CompanyRepository())->all();
        $allowedCompanyIds = Auth::allowedCompanyIds();
        if ($allowedCompanyIds !== null) {
            $companies = array_values(array_filter($companies, static fn (array $c): bool => in_array((int) $c['id'], $allowedCompanyIds, true)));
        }
        $boards = [];
        foreach ($companies as $company) {
            $rows = $customers->filtered(['company_id' => (string) $company['id'], 'company_is_default' => !empty($company['is_default'])]);
            $boards[] = ['id' => (string) $company['id'], 'name' => (string) $company['name'], 'link' => url('/customers') . '?company_id=' . (int) $company['id']]
                + $stats->summarize($rows);
        }
        if ($boards === [] && $allowedCompanyIds === null) {
            // No company set up yet: a single board of every customer.
            $boards[] = ['id' => '', 'name' => 'ทั้งหมด', 'link' => url('/customers')] + $stats->summarize($customers->filtered([]));
        }

        // ?company=<id> opens that company; otherwise the cross-company overview (or the only board there is).
        $requested = (string) ($_GET['company'] ?? 'all');
        $selected = count($boards) === 1 ? $boards[0]['id'] : 'all';
        foreach ($boards as $board) {
            if ($board['id'] === $requested) {
                $selected = $requested;
            }
        }

        View::render('dashboard/index', ['boards' => $boards, 'selected' => $selected, 'pageTitle' => 'Dashboard']);
    }
}
