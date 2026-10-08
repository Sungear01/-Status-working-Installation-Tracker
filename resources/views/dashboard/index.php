<?php
// Boards: one per company table (see DashboardController). 'all' = the cross-company overview.
// Each company keeps one color across the tabs and every chart.
$palette = ['#2563EB', '#7C3AED', '#0891B2', '#DB2777', '#EA580C', '#0D9488', '#4F46E5', '#65A30D'];
foreach ($boards as $index => $board) {
    $boards[$index]['color'] = $palette[$index % count($palette)];
}
$overviewKpi = ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'near_due' => 0];
foreach ($boards as $board) {
    foreach ($overviewKpi as $key => $value) {
        $overviewKpi[$key] = $value + (int) $board['kpi'][$key];
    }
}
$current = $overviewKpi;
$currentLink = url('/customers');
foreach ($boards as $board) {
    if ($board['id'] === $selected) {
        $current = $board['kpi'];
        $currentLink = $board['link'];
    }
}
$isOverview = $selected === 'all';
$nearDays = (int) config('customer.near_due_days', 7);
$kpis = [
    ['total', 'ลูกค้าทั้งหมด', 'fa-users', 'blue'],
    ['completed', 'งานเสร็จแล้ว', 'fa-flag-checkered', 'green'],
    ['pending', 'รอดำเนินการ', 'fa-hourglass-half', 'navy'],
    ['overdue', 'งานเกินกำหนด', 'fa-triangle-exclamation', 'red'],
    ['near_due', 'ใกล้ครบกำหนด', 'fa-clock', 'yellow'],
];
$percent = $current['total'] > 0 ? (int) round($current['completed'] * 100 / $current['total']) : 0;
$panelHead = static fn (string $title, string $subtitle, string $icon, string $extra = ''): string =>
    '<div class="panel-header"><div><h3>' . e($title) . '</h3><p>' . e($subtitle) . '</p></div><div class="d-flex align-items-center gap-2">' . $extra . '<span class="panel-icon-chip"><i class="fa-solid ' . e($icon) . '"></i></span></div></div>';
?>
<div class="page-heading"><div><span class="eyebrow text-primary"><i class="fa-solid fa-chart-simple"></i> OPERATIONS OVERVIEW</span><h2>ภาพรวมงานติดตั้ง</h2><p>ข้อมูลล่าสุด ณ <?= e(date('d/m/Y H:i')) ?> น.</p></div><?php if (can('view_customer')): ?><a class="btn btn-primary" id="dashTableLink" href="<?= e($currentLink) ?>"><i class="fa-solid fa-table-list me-2"></i><?= can('edit_customer') ? 'ดูและกรอกข้อมูล' : 'ดูข้อมูลทั้งหมด' ?></a><?php endif; ?></div>

<?php if (count($boards) > 1): ?>
<nav class="dash-switch" aria-label="เลือกตารางบริษัท">
    <a class="dash-tab<?= $isOverview ? ' is-active' : '' ?>" href="?company=all" data-board="all"<?= $isOverview ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-layer-group"></i><span>ภาพรวมทุกบริษัท</span><b><?= number_format($overviewKpi['total']) ?></b></a>
    <?php foreach ($boards as $board): $active = $board['id'] === $selected; ?>
    <a class="dash-tab<?= $active ? ' is-active' : '' ?>" href="?company=<?= e(rawurlencode($board['id'])) ?>" data-board="<?= e($board['id']) ?>"<?= $active ? ' aria-current="page"' : '' ?> style="--tab-color: <?= e($board['color']) ?>"><span class="dash-dot"></span><span><?= e($board['name']) ?></span><b><?= number_format((int) $board['kpi']['total']) ?></b></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="kpi-grid" aria-live="polite">
<?php foreach ($kpis as [$key, $label, $icon, $color]): ?>
    <a class="kpi-card<?= $key === 'overdue' ? ' is-priority' : '' ?>" data-kpi="<?= e($key) ?>">
        <div class="kpi-head"><span class="summary-icon <?= e($color) ?>"><i class="fa-solid <?= e($icon) ?>"></i></span><p><?= e($label) ?></p><i class="fa-solid fa-arrow-right kpi-go" aria-hidden="true"></i></div>
        <strong data-count><?= number_format((int) $current[$key]) ?></strong>
        <?php if ($key === 'completed'): ?><div class="kpi-progress" role="progressbar" aria-label="ความคืบหน้างาน" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>"><span style="width:<?= $percent ?>%"></span></div><?php endif; ?>
        <small data-note><?= match ($key) {
            'total' => $isOverview ? 'รวม ' . count($boards) . ' บริษัท' : 'รายการในตารางนี้',
            'completed' => 'คิดเป็น ' . $percent . '% ของทั้งหมด',
            'pending' => 'ยังไม่ปิดงาน',
            'overdue' => 'เลยครบกำหนด 60 วันแล้ว',
            default => 'ครบกำหนดภายใน ' . $nearDays . ' วัน',
        } ?></small>
    </a>
<?php endforeach; ?>
</div>

<div class="row g-4" data-view="overview"<?= $isOverview ? '' : ' hidden' ?>>
    <div class="col-12 col-xl-7"><section class="panel h-100"><?= $panelHead('เปรียบเทียบสถานะงานแต่ละบริษัท', 'คลิกแท่งของบริษัทเพื่อดูรายละเอียด', 'fa-chart-bar') ?><div class="chart-wrap"><canvas id="compareChart" aria-label="กราฟเปรียบเทียบสถานะงานแต่ละบริษัท" role="img"></canvas></div></section></div>
    <div class="col-12 col-xl-5"><section class="panel h-100"><?= $panelHead('สรุปรายบริษัท', 'ความคืบหน้าและงานเกินกำหนด', 'fa-building') ?><div class="company-cards" id="companyCards"></div></section></div>
    <div class="col-12"><section class="panel"><?= $panelHead('งานเข้าใหม่รายเดือนแยกบริษัท', 'นับจากวันที่ส่ง PO · 12 เดือนล่าสุดที่มีข้อมูล', 'fa-chart-line') ?><div class="chart-wrap"><canvas id="trendChart" aria-label="กราฟจำนวนงานรายเดือนแยกบริษัท" role="img"></canvas></div></section></div>
</div>

<div class="row g-4" data-view="company"<?= $isOverview ? ' hidden' : '' ?>>
    <div class="col-12 col-xl-4"><section class="panel h-100"><?= $panelHead('ข้อมูลแยกตาม Status', 'คลิกส่วนของวงกลมเพื่อดูรายชื่อ', 'fa-chart-pie') ?><div class="chart-wrap"><canvas id="statusChart" aria-label="กราฟสถานะงาน" role="img"></canvas></div></section></div>
    <div class="col-12 col-xl-8"><section class="panel h-100"><?= $panelHead('ลูกค้าแยกตาม Zone', 'คลิกแท่งกราฟเพื่อดูรายชื่อใน Zone นั้น', 'fa-map-location-dot', '<span class="chart-chip" data-unknown="zone" hidden></span>') ?><div class="chart-wrap"><canvas id="zoneChart" aria-label="กราฟลูกค้าแยกตาม Zone" role="img"></canvas></div></section></div>
    <div class="col-12 col-xl-7"><section class="panel h-100"><?= $panelHead('งานเข้าใหม่รายเดือน', 'นับจากวันที่ส่ง PO · คลิกจุดเพื่อดูงานของเดือนนั้น', 'fa-chart-line') ?><div class="chart-wrap"><canvas id="monthChart" aria-label="กราฟจำนวนงานรายเดือน" role="img"></canvas></div></section></div>
    <div class="col-12 col-xl-5"><section class="panel h-100"><?= $panelHead('ต้องติดตามด่วน', 'งานที่เกินกำหนดหรือใกล้ครบกำหนด เรียงจากด่วนที่สุด', 'fa-bell') ?><div class="attention-list" id="attentionList"></div></section></div>
    <div class="col-12"><section class="panel"><?= $panelHead('งานแยกตามขนาดเครื่อง', 'คลิกแท่งกราฟเพื่อดูรายชื่อของขนาดนั้น', 'fa-ruler-combined', '<span class="chart-chip" data-unknown="size" hidden></span>') ?><div class="chart-wrap"><canvas id="sizeChart" aria-label="กราฟงานแยกตามขนาดเครื่อง" role="img"></canvas></div></section></div>
</div>
<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
window.dashboardData = <?= json_encode(['boards' => $boards, 'selected' => $selected, 'nearDays' => $nearDays, 'customersUrl' => url('/customers')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e(url('/assets/js/dashboard.js?v=' . (string) @filemtime(base_path('public/assets/js/dashboard.js')))) ?>"></script>
<?php $scripts = ob_get_clean(); ?>
