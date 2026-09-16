(() => {
    'use strict';
    if (!window.Chart || !window.dashboardCharts) return;
    Chart.defaults.font.family = "'Noto Sans Thai', sans-serif";
    Chart.defaults.color = '#56708c';
    const palette = ['#2563EB','#16A34A','#F59E0B','#DC2626','#7C3AED','#0891B2','#64748B','#DB2777'];
    const make = (id, source, type, label) => {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const rows = source || [];
        new Chart(canvas, {type, data:{labels:rows.map(row=>row.label),datasets:[{label,data:rows.map(row=>Number(row.total)),backgroundColor:type==='line'?'rgba(37,99,235,.12)':palette,borderColor:type==='line'?'#2563EB':'#fff',borderWidth:type==='line'?3:2,fill:type==='line',tension:.35,borderRadius:type==='bar'?6:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:type==='doughnut',position:'bottom'}},scales:type==='doughnut'?{}:{y:{beginAtZero:true,grid:{color:'#edf1f5'}},x:{grid:{display:false}}}}});
    };
    make('zoneChart', dashboardCharts.zones, 'bar', 'ลูกค้า');
    make('statusChart', dashboardCharts.statuses, 'doughnut', 'สถานะ');
    make('monthChart', [...dashboardCharts.months].reverse(), 'line', 'งาน');
    make('sizeChart', dashboardCharts.sizes, 'bar', 'งาน');
})();

