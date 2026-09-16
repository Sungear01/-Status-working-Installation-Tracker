<?php
$cards = [
    ['total', 'จำนวนลูกค้าทั้งหมด', 'fa-users', 'blue'],
    ['yes_count', 'สถานะ YES', 'fa-circle-check', 'green'],
    ['pending_count', 'รอดำเนินการ', 'fa-hourglass-half', 'navy'],
    ['overdue_count', 'งานเกินกำหนด', 'fa-triangle-exclamation', 'red'],
    ['near_due_count', 'ใกล้ครบกำหนด', 'fa-clock', 'yellow'],
    ['completed_count', 'งานเสร็จแล้ว', 'fa-flag-checkered', 'green'],
];
?>
<div class="page-heading"><div><span class="eyebrow text-primary">OPERATIONS OVERVIEW</span><h2>ภาพรวมงานติดตั้ง</h2><p>ข้อมูลล่าสุด ณ <?= e(date('d/m/Y H:i')) ?> น.</p></div><?php if (can('view_customer')): ?><a class="btn btn-primary" href="<?= e(url('/customers')) ?>"><i class="fa-solid fa-table-list me-2"></i><?= can('edit_customer') ? 'ดูและกรอกข้อมูล' : 'ดูข้อมูลทั้งหมด' ?></a><?php endif; ?></div>
<div class="row g-3 mb-4">
<?php foreach ($cards as [$key, $label, $icon, $color]): ?>
    <div class="col-12 col-sm-6 col-xl-4"><article class="summary-card"><span class="summary-icon <?= e($color) ?>"><i class="fa-solid <?= e($icon) ?>"></i></span><div><p><?= e($label) ?></p><strong><?= number_format((int) ($summary[$key] ?? 0)) ?></strong></div></article></div>
<?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-12 col-xl-6"><section class="panel"><div class="panel-header"><div><h3>ลูกค้าแยกตาม Zone</h3><p>สัดส่วนจำนวนลูกค้าในแต่ละพื้นที่</p></div></div><div class="chart-wrap"><canvas id="zoneChart"></canvas></div></section></div>
    <div class="col-12 col-xl-6"><section class="panel"><div class="panel-header"><div><h3>ข้อมูลแยกตาม Status</h3><p>สถานะปัจจุบันของงานทั้งหมด</p></div></div><div class="chart-wrap"><canvas id="statusChart"></canvas></div></section></div>
    <div class="col-12 col-xl-7"><section class="panel"><div class="panel-header"><div><h3>จำนวนงานในแต่ละเดือน</h3><p>อ้างอิงจากวันที่ส่ง PO</p></div></div><div class="chart-wrap"><canvas id="monthChart"></canvas></div></section></div>
    <div class="col-12 col-xl-5"><section class="panel"><div class="panel-header"><div><h3>งานแยกตามขนาดเครื่อง</h3><p>การกระจายตามประเภทขนาด</p></div></div><div class="chart-wrap"><canvas id="sizeChart"></canvas></div></section></div>
</div>
<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
window.dashboardCharts = <?= json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e(url('/assets/js/dashboard.js')) ?>"></script>
<?php $scripts = ob_get_clean(); ?>
