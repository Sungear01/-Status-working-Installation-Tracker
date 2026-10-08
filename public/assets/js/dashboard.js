(() => {
    'use strict';
    const data = window.dashboardData;
    if (!data || !Array.isArray(data.boards)) return;
    const hasCharts = !!window.Chart;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (hasCharts) {
        Chart.defaults.font.family = "'Noto Sans Thai', sans-serif";
        Chart.defaults.color = '#56708c';
        if (reduceMotion) Chart.defaults.animation = false;
    }

    const boards = data.boards;
    const colors = { green: '#16A34A', blue: '#2563EB', slate: '#94A3B8', amber: '#F59E0B', red: '#DC2626', grid: '#edf1f5' };
    // Status colors carry meaning (done / waiting / problem); the legend and tooltip always spell the status out too.
    const statusColor = (value, index) => ({ YES: colors.green, 'รอ': colors.amber, NO: colors.red, STATUS: colors.slate }[value]
        || ['#0891B2', '#7C3AED', '#DB2777', '#64748B'][index % 4]);
    const number = (value) => Number(value || 0).toLocaleString('th-TH');
    const percent = (part, whole) => (whole > 0 ? Math.round((part * 100) / whole) : 0);
    const thaiDate = (iso) => { const [y, m, d] = iso.split('-'); return `${d}/${m}/${Number(y) + 543}`; };
    const alpha = (hex, a) => { const n = parseInt(hex.slice(1), 16); return `rgba(${n >> 16},${(n >> 8) & 255},${n & 255},${a})`; };

    // Opening the customer table of a board with filters: the drill-down behind every clickable chart part.
    const tableUrl = (board, params = {}) => {
        const url = new URL(board ? board.link : data.customersUrl, window.location.href);
        Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
        return url.toString();
    };
    const pointer = (event, elements) => { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; };

    const charts = {};
    const draw = (id, config) => {
        charts[id]?.destroy();
        const canvas = document.getElementById(id);
        if (hasCharts && canvas) charts[id] = new Chart(canvas, config);
    };
    const barScales = (horizontal = false, stacked = false) => ({
        x: { stacked, beginAtZero: true, grid: { display: horizontal, color: colors.grid }, ticks: { precision: 0 } },
        y: { stacked, beginAtZero: true, grid: { display: !horizontal, color: colors.grid }, ticks: { precision: 0 } },
    });

    // Doughnut middle: the share of finished jobs.
    const centerText = {
        id: 'centerText',
        afterDraw(chart, args, options) {
            if (!options || !options.text) return;
            const { ctx, chartArea: { left, right, top, bottom } } = chart;
            const x = (left + right) / 2;
            const y = (top + bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#102A43';
            ctx.font = "700 26px 'Noto Sans Thai', sans-serif";
            ctx.fillText(options.text, x, y - 9);
            ctx.fillStyle = '#56708c';
            ctx.font = "12px 'Noto Sans Thai', sans-serif";
            ctx.fillText(options.sub, x, y + 15);
            ctx.restore();
        },
    };

    // Numbers roll up to their new value when the board changes.
    const countUp = (element, to) => {
        const from = Number(element.dataset.value ?? String(element.textContent).replace(/[^\d]/g, '')) || 0;
        element.dataset.value = String(to);
        if (reduceMotion || from === to) { element.textContent = number(to); return; }
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / 600);
            element.textContent = number(Math.round(from + (to - from) * (1 - Math.pow(1 - t, 3))));
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    };

    const overviewKpi = () => boards.reduce((sum, board) => {
        Object.keys(sum).forEach((key) => { sum[key] += Number(board.kpi[key] || 0); });
        return sum;
    }, { total: 0, completed: 0, pending: 0, overdue: 0, near_due: 0 });

    const renderKpis = (board) => {
        const kpi = board ? board.kpi : overviewKpi();
        const done = percent(kpi.completed, kpi.total);
        const notes = {
            total: board ? 'รายการในตารางนี้' : `รวม ${boards.length} บริษัท`,
            completed: `คิดเป็น ${done}% ของทั้งหมด`,
            pending: 'ยังไม่ปิดงาน',
            overdue: 'เลยครบกำหนด 60 วันแล้ว',
            near_due: `ครบกำหนดภายใน ${data.nearDays} วัน`,
        };
        // Cards that match a filter of the customer table open it; the rest are read-only.
        const links = board ? { total: tableUrl(board), completed: tableUrl(board, { status: 'YES' }) } : {};
        document.querySelectorAll('[data-kpi]').forEach((card) => {
            const key = card.dataset.kpi;
            countUp(card.querySelector('[data-count]'), Number(kpi[key] || 0));
            card.querySelector('[data-note]').textContent = notes[key];
            if (links[key]) card.setAttribute('href', links[key]); else card.removeAttribute('href');
            const bar = card.querySelector('.kpi-progress');
            if (bar) {
                bar.setAttribute('aria-valuenow', String(done));
                bar.firstElementChild.style.width = `${done}%`;
            }
        });
    };

    const renderOverview = () => {
        draw('compareChart', {
            type: 'bar',
            data: {
                labels: boards.map((board) => board.name),
                datasets: [
                    { label: 'เสร็จแล้ว', data: boards.map((b) => b.kpi.completed), backgroundColor: colors.green },
                    { label: 'กำลังดำเนินการ', data: boards.map((b) => b.kpi.pending - b.kpi.overdue - b.kpi.near_due), backgroundColor: '#BFD3F6' },
                    { label: 'ใกล้ครบกำหนด', data: boards.map((b) => b.kpi.near_due), backgroundColor: colors.amber },
                    { label: 'เกินกำหนด', data: boards.map((b) => b.kpi.overdue), backgroundColor: colors.red },
                ],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false, borderRadius: 4, barThickness: 26,
                scales: barScales(true, true),
                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { footer: (items) => `รวม ${number(boards[items[0].dataIndex].kpi.total)} ราย · คลิกเพื่อดูรายละเอียด` } } },
                onHover: pointer,
                onClick: (event, elements) => { if (elements.length) show(boards[elements[0].index].id); },
            },
        });

        const cards = document.getElementById('companyCards');
        cards.replaceChildren(...boards.map((board) => {
            const done = percent(board.kpi.completed, board.kpi.total);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'company-card';
            card.style.setProperty('--tab-color', board.color);
            card.innerHTML = '<span class="dash-dot"></span><strong></strong><span class="company-total"></span>'
                + '<div class="kpi-progress"><span></span></div><div class="company-meta"><span class="company-done"></span><span class="company-late"></span></div>';
            card.querySelector('strong').textContent = board.name;
            card.querySelector('.company-total').textContent = `${number(board.kpi.total)} ราย`;
            card.querySelector('.kpi-progress span').style.width = `${done}%`;
            card.querySelector('.company-done').textContent = `เสร็จ ${number(board.kpi.completed)} (${done}%)`;
            const late = card.querySelector('.company-late');
            late.textContent = board.kpi.overdue > 0 ? `เกินกำหนด ${number(board.kpi.overdue)}` : 'ไม่มีงานเกินกำหนด';
            late.classList.toggle('is-late', board.kpi.overdue > 0);
            card.setAttribute('aria-label', `ดูแดชบอร์ดของ ${board.name}`);
            card.addEventListener('click', () => show(board.id));
            return card;
        }));

        // One line per company over the latest 12 months any of them has.
        const months = [...new Map(boards.flatMap((b) => b.months).map((m) => [m.value, m.label])).entries()]
            .sort(([a], [b]) => a.localeCompare(b)).slice(-12);
        draw('trendChart', {
            type: 'line',
            data: {
                labels: months.map(([, label]) => label),
                datasets: boards.map((board) => {
                    const totals = Object.fromEntries(board.months.map((m) => [m.value, m.total]));
                    return { label: board.name, data: months.map(([value]) => totals[value] || 0), borderColor: board.color, backgroundColor: alpha(board.color, 0.08), fill: boards.length === 1, tension: 0.35, borderWidth: 3, pointRadius: 3, pointHoverRadius: 6 };
                }),
            },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: barScales(), plugins: { legend: { position: 'bottom' } } },
        });
    };

    const renderAttention = (board) => {
        const list = document.getElementById('attentionList');
        if (!board.attention.length) {
            list.innerHTML = '<div class="attention-empty"><i class="fa-solid fa-circle-check"></i><strong>ไม่มีงานที่ต้องตามด่วน</strong><span>ไม่มีงานที่เกินกำหนดหรือใกล้ครบกำหนดในตารางนี้</span></div>';
            return;
        }
        list.replaceChildren(...board.attention.map((item) => {
            const link = document.createElement('a');
            link.className = 'attention-item';
            link.href = tableUrl(board, { search: item.name });
            link.innerHTML = '<div><strong></strong><small></small></div><span class="days-pill"></span>';
            link.querySelector('strong').textContent = item.name || '(ไม่มีชื่อ)';
            link.querySelector('small').textContent = `ครบกำหนด ${thaiDate(item.due)}${item.zone ? ` · Zone ${item.zone}` : ''}`;
            const pill = link.querySelector('.days-pill');
            pill.classList.add(item.days < 0 ? 'late' : 'soon');
            pill.textContent = item.days < 0 ? `เกิน ${number(-item.days)} วัน` : (item.days === 0 ? 'ครบกำหนดวันนี้' : `อีก ${number(item.days)} วัน`);
            return link;
        }));
    };

    const renderCompany = (board) => {
        const done = percent(board.kpi.completed, board.kpi.total);
        draw('statusChart', {
            type: 'doughnut',
            data: { labels: board.statuses.map((s) => s.label), datasets: [{ data: board.statuses.map((s) => s.total), backgroundColor: board.statuses.map((s, i) => statusColor(s.value, i)), borderColor: '#fff', borderWidth: 2, hoverOffset: 8 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { position: 'bottom' }, centerText: { text: `${done}%`, sub: 'งานเสร็จแล้ว' }, tooltip: { callbacks: { label: (c) => ` ${c.label}: ${number(c.raw)} ราย (${percent(c.raw, board.kpi.total)}%)` } } },
                onHover: pointer,
                onClick: (event, elements) => { if (elements.length) window.location.href = tableUrl(board, { status: board.statuses[elements[0].index].value }); },
            },
            plugins: [centerText],
        });

        const bar = (id, rows, field, label) => draw(id, {
            type: 'bar',
            data: { labels: rows.map((r) => r.label), datasets: [{ label, data: rows.map((r) => r.total), backgroundColor: alpha(board.color, 0.85), hoverBackgroundColor: board.color, borderRadius: 6, maxBarThickness: 48 }] },
            options: {
                responsive: true, maintainAspectRatio: false, scales: barScales(),
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${number(c.raw)} ราย · คลิกเพื่อดูรายชื่อ` } } },
                onHover: pointer,
                onClick: (event, elements) => { if (elements.length) window.location.href = tableUrl(board, { [field]: rows[elements[0].index].value }); },
            },
        });
        bar('zoneChart', board.zones, 'zone', 'ลูกค้า');
        bar('sizeChart', board.sizes, 'machine_size', 'งาน');

        // Blanks stay out of the bars; say how many there are instead.
        const unknown = { zone: [board.unknown.zone, 'ไม่ระบุ Zone'], size: [board.unknown.size, 'ไม่ระบุขนาด'] };
        document.querySelectorAll('[data-unknown]').forEach((chip) => {
            const [count, label] = unknown[chip.dataset.unknown];
            chip.hidden = !count;
            chip.textContent = `${label} ${number(count)} ราย`;
        });

        draw('monthChart', {
            type: 'line',
            data: { labels: board.months.map((m) => m.label), datasets: [{ label: 'งาน', data: board.months.map((m) => m.total), borderColor: board.color, backgroundColor: alpha(board.color, 0.12), fill: true, tension: 0.35, borderWidth: 3, pointRadius: 4, pointHoverRadius: 7 }] },
            options: {
                responsive: true, maintainAspectRatio: false, scales: barScales(),
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${number(c.raw)} งาน · คลิกเพื่อดูรายชื่อ` } } },
                onHover: pointer,
                onClick: (event, elements) => {
                    if (!elements.length) return;
                    const [year, month] = board.months[elements[0].index].value.split('-').map(Number);
                    const last = new Date(year, month, 0).getDate();
                    window.location.href = tableUrl(board, { date_from: `${year}-${String(month).padStart(2, '0')}-01`, date_to: `${year}-${String(month).padStart(2, '0')}-${last}` });
                },
            },
        });

        renderAttention(board);
    };

    // Switching boards happens in place (no reload); the address keeps ?company= so a refresh or a shared link
    // opens the same board.
    const show = (id) => {
        const board = boards.find((b) => b.id === id) || null;
        const view = board ? 'company' : 'overview';
        document.querySelectorAll('.dash-tab').forEach((tab) => {
            const active = tab.dataset.board === (board ? board.id : 'all');
            tab.classList.toggle('is-active', active);
            if (active) tab.setAttribute('aria-current', 'page'); else tab.removeAttribute('aria-current');
        });
        document.querySelectorAll('[data-view]').forEach((section) => {
            section.hidden = section.dataset.view !== view;
            if (!section.hidden && !reduceMotion) {
                section.classList.remove('dash-fade');
                void section.offsetWidth;
                section.classList.add('dash-fade');
            }
        });
        const tableLink = document.getElementById('dashTableLink');
        if (tableLink) tableLink.href = tableUrl(board);
        renderKpis(board);
        if (board) renderCompany(board); else renderOverview();
        const url = new URL(window.location.href);
        url.searchParams.set('company', board ? board.id : 'all');
        window.history.replaceState(null, '', url);
    };

    document.querySelectorAll('.dash-tab').forEach((tab) => tab.addEventListener('click', (event) => {
        event.preventDefault();
        show(tab.dataset.board);
    }));
    show(boards.length === 1 ? boards[0].id : data.selected);
})();
