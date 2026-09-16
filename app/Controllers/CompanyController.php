<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Repositories\CompanyGroupRepository;
use App\Repositories\CompanyRepository;
use App\Services\ActivityLogger;

final class CompanyController
{
    public function store(): void
    {
        $this->verifyCsrf();
        $names = $this->names();
        if ($names === []) {
            flash('error', 'กรุณาระบุชื่อบริษัท');
            redirect('/customers');
        }
        $shared = $this->sharedInput();
        $shared['created_by'] = Auth::id();
        $repository = new CompanyRepository();
        $logger = new ActivityLogger();
        $ids = [];
        foreach ($names as $name) {
            $existing = $repository->findByName($name);
            $data = array_merge($shared, ['name' => $name]);
            if ($existing) {
                $ids[] = (int) $existing['id'];
                $repository->update((int) $existing['id'], $data);
                $logger->log('update', 'company', (string) $existing['id'], 'แก้ไขบริษัท ' . $name, $existing, $data);
            } else {
                $newId = $repository->create($data);
                $ids[] = $newId;
                $logger->log('create', 'company', (string) $newId, 'เพิ่มบริษัท ' . $name, null, $data);
            }
        }
        flash('success', count($names) > 1 ? 'บันทึกบริษัทเรียบร้อย ' . count($names) . ' บริษัท' : 'บันทึกบริษัทเรียบร้อย');
        redirect($this->redirectUrl($ids));
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CompanyRepository();
        $old = $repository->find((int) $id);
        if (!$old) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        $names = $this->names();
        if ($names === []) {
            flash('error', 'กรุณาระบุชื่อบริษัท');
            redirect('/customers');
        }
        $shared = $this->sharedInput();
        $logger = new ActivityLogger();

        if (count($names) === 1) {
            // Single name selected: treat this as a plain rename/update of the company being edited.
            $data = array_merge($shared, ['name' => $names[0]]);
            $repository->update((int) $id, $data);
            $logger->log('update', 'company', $id, 'แก้ไขบริษัท ' . $data['name'], $old, $data);
            $ids = [(int) $id];
        } else {
            // Multiple names selected: apply the same start year / customer list to every selected
            // company (existing ones get updated, names with no match yet get created).
            $ids = [];
            foreach ($names as $name) {
                $existing = $name === $old['name'] ? $old : $repository->findByName($name);
                $data = array_merge($shared, ['name' => $name]);
                if ($existing) {
                    $ids[] = (int) $existing['id'];
                    $repository->update((int) $existing['id'], $data);
                    $logger->log('update', 'company', (string) $existing['id'], 'แก้ไขบริษัท ' . $name, $existing, $data);
                } else {
                    $data['created_by'] = Auth::id();
                    $newId = $repository->create($data);
                    $ids[] = $newId;
                    $logger->log('create', 'company', (string) $newId, 'เพิ่มบริษัท ' . $name, null, $data);
                }
            }
        }
        flash('success', count($names) > 1 ? 'บันทึกบริษัทเรียบร้อย ' . count($names) . ' บริษัท' : 'บันทึกข้อมูลบริษัทเรียบร้อย');
        redirect($this->redirectUrl($ids));
    }

    /** After saving 2+ companies together, land on a combined tab that shows them all at once. */
    private function redirectUrl(array $ids): string
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) < 2) {
            return '/customers?company_id=' . ($ids[0] ?? '');
        }
        $groupId = (new CompanyGroupRepository())->findOrCreate($ids);
        return '/customers?group_id=' . $groupId;
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $repository = new CompanyRepository();
        $company = $repository->find((int) $id);
        if (!$company) {
            http_response_code(404);
            exit('ไม่พบข้อมูลบริษัท');
        }
        if ($company['is_default']) {
            flash('error', 'ไม่สามารถลบบริษัทหลักได้');
            redirect('/customers');
        }
        $repository->delete((int) $id);
        (new ActivityLogger())->log('delete', 'company', $id, 'ลบบริษัท ' . $company['name'], $company, null);
        flash('success', 'ลบบริษัทเรียบร้อย');
        redirect('/customers');
    }

    private function names(): array
    {
        $raw = $_POST['name'] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];
        $names = [];
        foreach ($raw as $name) {
            $name = mb_substr(trim((string) $name), 0, 150);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    private function sharedInput(): array
    {
        $startYear = trim((string) ($_POST['start_year'] ?? ''));
        // Sent as one comma-separated field (not customer_ids[] per checkbox) so a company
        // with hundreds of customers doesn't blow past PHP's max_input_vars and get truncated.
        $raw = trim((string) ($_POST['customer_ids'] ?? ''));
        $customerIds = $raw === '' ? [] : array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
        return [
            'start_year' => preg_match('/^\d{4}$/', $startYear) ? $startYear : null,
            'customer_ids' => $customerIds,
        ];
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
