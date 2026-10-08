<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The dashboard numbers of one company's table, worked out from its rows (CustomerRepository::filtered()), so they
 * match exactly what that company's table shows: its own customers and its own tracking data.
 */
final class DashboardStats
{
    private const THAI_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    /** How many of the most urgent unfinished jobs the "ต้องติดตาม" list shows. */
    private const ATTENTION_LIMIT = 8;

    public function summarize(array $rows): array
    {
        $completedValues = array_map(static fn ($value): string => mb_strtoupper((string) $value), config('customer.status.completed', []));
        $today = new \DateTimeImmutable('today');
        $nearLimit = $today->modify('+' . (int) config('customer.near_due_days', 7) . ' days');

        $kpi = ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'near_due' => 0];
        $zones = $statuses = $sizes = $months = [];
        $attention = [];
        foreach ($rows as $row) {
            $kpi['total']++;
            $status = trim((string) ($row['status'] ?? ''));
            $isCompleted = in_array(mb_strtoupper($status), $completedValues, true);
            $kpi[$isCompleted ? 'completed' : 'pending']++;

            $zone = trim((string) ($row['zone'] ?? ''));
            $size = trim((string) ($row['machine_size'] ?? ''));
            $zones[$zone] = ($zones[$zone] ?? 0) + 1;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $sizes[$size] = ($sizes[$size] ?? 0) + 1;
            if (!empty($row['po_sent_date'])) {
                $month = substr((string) $row['po_sent_date'], 0, 7);
                $months[$month] = ($months[$month] ?? 0) + 1;
            }

            if ($isCompleted || empty($row['due_60_date'])) {
                continue;
            }
            $due = new \DateTimeImmutable((string) $row['due_60_date']);
            if ($due < $today) {
                $kpi['overdue']++;
            } elseif ($due <= $nearLimit) {
                $kpi['near_due']++;
            } else {
                continue;
            }
            $attention[] = [
                'id' => (string) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
                'zone' => (string) ($row['zone'] ?? ''),
                'due' => $due->format('Y-m-d'),
                // Negative = days late, 0 = due today, positive = days left.
                'days' => (int) $today->diff($due)->format('%r%a'),
            ];
        }

        usort($attention, static fn (array $a, array $b): int => $a['days'] <=> $b['days']);
        ksort($months);
        $months = array_slice($months, -12, null, true);

        return [
            'kpi' => $kpi,
            // Zones in their natural order (1, 2, … 10), the rest by size; "ไม่ระบุ" is kept out of the charts and
            // reported on its own, so a few hundred blanks do not flatten every real bar.
            'zones' => $this->entries($zones, static fn (array $a, array $b): int => strnatcmp($a['value'], $b['value'])),
            'statuses' => $this->entries($statuses),
            'sizes' => $this->entries($sizes),
            'unknown' => ['zone' => $zones[''] ?? 0, 'size' => $sizes[''] ?? 0],
            'months' => array_map(fn (string $month, int $total): array => [
                'value' => $month,
                'label' => $this->monthLabel($month),
                'total' => $total,
            ], array_keys($months), array_values($months)),
            'attention' => array_slice($attention, 0, self::ATTENTION_LIMIT),
        ];
    }

    /** [value => count] as [{value, label, total}], blanks left out; sorted by count unless $sort is given. */
    private function entries(array $counts, ?callable $sort = null): array
    {
        $entries = [];
        foreach ($counts as $value => $total) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $entries[] = ['value' => $value, 'label' => $value === 'STATUS' ? 'ยังไม่ระบุสถานะ' : $value, 'total' => $total];
        }
        usort($entries, $sort ?? static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        return $entries;
    }

    /** "2026-01" as "ม.ค. 69" (Buddhist year, like the dates in the tables). */
    private function monthLabel(string $month): string
    {
        [$year, $number] = array_map('intval', explode('-', $month));
        return (self::THAI_MONTHS[$number - 1] ?? $month) . ' ' . substr((string) ($year + 543), -2);
    }
}
