(() => {
    'use strict';
    // The browser's own date input orders day/month by the OS locale (here mm/dd/yyyy) and HTML cannot change that.
    // So each date field gets a text box in front that reads and types dd/mm/yy (slashes added while typing; dd/mm/yyyy
    // and Buddhist years like 2569 are accepted too), plus a button that opens the browser's own calendar. The date
    // input stays underneath and still holds yyyy-mm-dd, so forms, filters and the server are unchanged.
    // Returns the text box; a typed date is applied on Enter or when leaving the box.
    const dayFirstDate = (input) => {
        if (input.dataset.dayFirst) return input.dayFirstText;
        input.dataset.dayFirst = '1';
        const format = (iso) => (iso ? `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(2, 4)}` : '');
        const parse = (text) => {
            const match = text.trim().match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$/);
            if (!match) return null;
            let year = Number(match[3]);
            if (match[3].length === 2) year += 2000;
            if (year > 2400) year -= 543;
            const date = new Date(year, Number(match[2]) - 1, Number(match[1]));
            if (date.getFullYear() !== year || date.getMonth() !== Number(match[2]) - 1 || date.getDate() !== Number(match[1])) return null;
            const pad = (number) => String(number).padStart(2, '0');
            return `${year}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        };
        const wrap = document.createElement('span');
        wrap.className = 'date-day-first';
        input.parentNode.insertBefore(wrap, input);
        const text = document.createElement('input');
        text.type = 'text';
        text.className = `${input.className.replace('data-entry-input', '')} date-day-first-text`;
        text.placeholder = 'dd/mm/yy';
        text.inputMode = 'numeric';
        text.maxLength = 10;
        text.autocomplete = 'off';
        text.disabled = input.disabled;
        text.readOnly = input.readOnly;
        text.setAttribute('aria-label', input.getAttribute('aria-label') || (input.id && document.querySelector(`label[for="${input.id}"]`)?.textContent.trim()) || 'วันที่ (dd/mm/yy)');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'date-day-first-button';
        button.tabIndex = -1;
        button.title = 'เลือกจากปฏิทิน';
        button.innerHTML = '<i class="fa-regular fa-calendar"></i>';
        button.hidden = input.disabled || input.readOnly;
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        wrap.append(input, text, button);
        const apply = (iso) => {
            text.classList.remove('is-invalid');
            text.value = format(iso);
            if (input.value === iso) return;
            input.value = iso;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const commit = () => {
            if (text.value.trim() === '') { apply(''); return; }
            const iso = parse(text.value);
            if (iso) apply(iso); else text.classList.add('is-invalid');
        };
        text.addEventListener('input', (event) => {
            // A slash typed right after the one added below is not doubled (06//10 → 06/10).
            if (text.value.includes('//')) text.value = text.value.replace(/\/{2,}/g, '/');
            // 06 → 06/ and 06/10 → 06/10/ while typing forward, so only digits need typing.
            if (event.inputType === 'insertText' && /^\d{2}$|^\d{1,2}\/\d{2}$/.test(text.value)) text.value += '/';
            text.classList.remove('is-invalid');
        });
        text.addEventListener('change', commit);
        text.addEventListener('keydown', (event) => { if (event.key === 'Enter') commit(); });
        button.addEventListener('click', () => {
            try { input.showPicker(); } catch { text.focus(); }
        });
        // A date picked on the calendar (a real user event) shows in the text box.
        input.addEventListener('change', (event) => { if (event.isTrusted) { text.value = format(input.value); text.classList.remove('is-invalid'); } });
        text.value = format(input.value);
        input.dayFirstText = text;
        return text;
    };
    document.querySelectorAll('input[type="date"]').forEach(dayFirstDate);
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleSidebar = () => {
        sidebar?.classList.toggle('open');
        backdrop?.classList.toggle('show');
    };
    document.getElementById('menuToggle')?.addEventListener('click', toggleSidebar);
    backdrop?.addEventListener('click', toggleSidebar);

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.parentElement.querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
            button.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
    // Add the same eye toggle to password fields on profile and password-change pages.
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const parent = input.parentElement;
        if (!parent || parent.classList.contains('input-icon')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'input-icon';
        parent.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('data-password-toggle', '');
        button.setAttribute('aria-label', 'แสดงรหัสผ่าน');
        button.innerHTML = '<i class="fa-regular fa-eye"></i>';
        wrapper.appendChild(button);
        button.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            button.querySelector('i')?.classList.toggle('fa-eye-slash');
        });
    });
    document.querySelectorAll('.system-toast-static .system-toast-close').forEach((button) => {
        button.addEventListener('click', () => button.closest('.system-toast-static')?.remove());
    });

    if (location.pathname.endsWith('/login') || location.pathname === '/') {
        const passwordGroup = document.getElementById('password')?.closest('.mb-4');
        if (passwordGroup && !document.getElementById('forgotPasswordLink')) {
            const link = document.createElement('div');
            link.className = 'text-end mt-2';
            const forgotPath = location.pathname.endsWith('/login') ? location.pathname.replace(/\/login$/, '/forgot-password') : '/forgot-password';
            link.innerHTML = '<a id="forgotPasswordLink" class="small text-decoration-none">ลืมรหัสผ่าน?</a>';
            link.querySelector('a').href = forgotPath;
            passwordGroup.appendChild(link);
        }
    }

    const loginReason = new URLSearchParams(location.search).get('reason');
    if (loginReason === 'session-replaced') {
        const helpModal = document.getElementById('accountHelpModal');
        if (helpModal && window.bootstrap) {
            helpModal.querySelector('#accountHelpTitle').textContent = 'แจ้งเตือน';
            helpModal.querySelector('.text-secondary').textContent = 'บัญชีนี้ถูกเข้าใช้งานจากอุปกรณ์อื่น กรุณาเข้าสู่ระบบใหม่';
            bootstrap.Modal.getOrCreateInstance(helpModal).show();
            history.replaceState({}, '', location.pathname);
        }
    }

    const filterForm = document.getElementById('customerFilters');
    let searchTimer;
    const realtimeSearch = document.querySelector('[data-realtime-search]');
    realtimeSearch?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => filterForm?.requestSubmit(), 5000);
    });
    // The filter form has no submit button and holds date fields, so the browser does not submit it on Enter by itself.
    realtimeSearch?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing) return;
        event.preventDefault();
        clearTimeout(searchTimer);
        filterForm?.requestSubmit();
    });
    document.querySelectorAll('[data-auto-submit]').forEach((field) => field.addEventListener('change', () => filterForm?.requestSubmit()));

    let pendingConfirmForm = null;
    const confirmModalElement = document.getElementById('confirmActionModal');
    let confirmModal = confirmModalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(confirmModalElement) : null;
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
            event.preventDefault();
            pendingConfirmForm = form;
            document.getElementById('confirmActionDetail').textContent = form.dataset.confirmDetail || form.dataset.confirm;
            confirmModal = confirmModal || (window.bootstrap && bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmActionModal')));
            confirmModal?.show();
        });
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('#confirmActionSubmit')) return;
        if (!pendingConfirmForm) return;
        pendingConfirmForm.dataset.confirmed = '1';
        confirmModal?.hide();
        pendingConfirmForm.submit();
        pendingConfirmForm = null;
    });

    document.querySelectorAll('.clickable-row').forEach((row) => {
        const go = () => { window.location.href = row.dataset.href; };
        row.addEventListener('click', (event) => { if (!event.target.closest('button, a, input, select, textarea, td[data-editable]')) go(); });
        row.addEventListener('keydown', (event) => { if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest?.('td[data-editable]')) go(); });
    });

    const trackingFields = ['name','zone','machine_size','po_sent_date','po_due_date','engineer','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date','status','work_status','construction_notes','cap_notes', ...[...document.querySelectorAll('[id^="edit_custom_"]')].map((input) => input.id.slice(5))];
    const draftKey = (id) => `cap_plus_customer_draft_${id}`;
    const readDraft = (id) => {
        try { return JSON.parse(localStorage.getItem(draftKey(id)) || 'null'); } catch { return null; }
    };
    const saveDraft = () => {
        const form = document.getElementById('editTrackingForm');
        const id = form?.dataset.customerId;
        if (!form || !id) return;
        const draft = {};
        trackingFields.forEach((field) => {
            const input = document.getElementById(`edit_${field}`);
            if (input) draft[field] = input.value;
        });
        try { localStorage.setItem(draftKey(id), JSON.stringify(draft)); } catch { /* storage may be disabled */ }
    };
    const clearDraft = (id) => { try { localStorage.removeItem(draftKey(id)); } catch { /* ignore */ } };
    const calculateDueDates = () => {
        const sent = document.getElementById('edit_po_sent_date');
        const due = document.getElementById('edit_po_due_date');
        const due60 = document.getElementById('edit_due_60_date');
        if (!sent || (!due && !due60)) return;
        if (!sent.value) { if (due) due.value = ''; if (due60) due60.value = ''; return; }
        const date = new Date(`${sent.value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return;
        const firstDue = new Date(date);
        firstDue.setDate(firstDue.getDate() + 61);
        const secondDue = new Date(date);
        secondDue.setDate(secondDue.getDate() + 60);
        const pad = (value) => String(value).padStart(2, '0');
        if (due) due.value = `${firstDue.getFullYear()}-${pad(firstDue.getMonth() + 1)}-${pad(firstDue.getDate())}`;
        if (due60) due60.value = `${secondDue.getFullYear()}-${pad(secondDue.getMonth() + 1)}-${pad(secondDue.getDate())}`;
        calculateDaysEarlyLate();
        saveDraft();
    };
    const calculateDaysEarlyLate = () => {
        const installation = document.getElementById('edit_installation_date_3');
        const due = document.getElementById('edit_due_60_date');
        const output = document.getElementById('edit_days_early_late');
        if (!installation || !due || !output) return;
        if (!installation.value || !due.value) { output.value = ''; return; }
        const days = Math.round((new Date(`${due.value}T00:00:00`) - new Date(`${installation.value}T00:00:00`)) / 86400000);
        output.value = String(days);
    };
    const statusColor = (status, dueDate) => {
        const colors = { YES: 'success', NO: 'danger', 'รอ': 'warning', STATUS: 'secondary' };
        const normalized = String(status || '').trim().toUpperCase();
        if (colors[normalized]) return colors[normalized];
        if (!dueDate) return 'secondary';
        const due = new Date(`${dueDate}T00:00:00`);
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const days = Math.round((due - today) / 86400000);
        return days < 0 ? 'danger' : (days <= 7 ? 'warning' : 'secondary');
    };
    const daysText = (value) => (value === null || value === undefined || value === ''
        ? '-'
        : (Number(value) < 0 ? `ช้า ${Math.abs(Number(value))} วัน` : `เร็ว/เหลือ ${Number(value)} วัน`));

    // Colour rules of the custom columns ({field: [{op, value, color}]}), set on the table by customers/index.php.
    // The first rule that matches a cell's raw value colours it, like conditional formatting in Excel.
    const colorRuleMap = (() => {
        try { return JSON.parse(document.querySelector('.enterprise-table')?.dataset.colorRules || '{}'); } catch { return {}; }
    })();
    const ruleColors = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'gray'];
    const todayIso = () => {
        const today = new Date();
        const pad = (number) => String(number).padStart(2, '0');
        return `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
    };
    const ruleMatches = (rule, raw, numeric) => {
        if (rule.op === 'empty') return raw === '';
        if (rule.op === 'not_empty') return raw !== '';
        if (raw === '') return false;
        switch (rule.op) {
            case 'lt': return Number(raw) < Number(rule.value);
            case 'gt': return Number(raw) > Number(rule.value);
            case 'eq': return numeric ? Number(raw) === Number(rule.value) : raw === rule.value;
            case 'ne': return numeric ? Number(raw) !== Number(rule.value) : raw !== rule.value;
            case 'contains': return raw.toLowerCase().includes(String(rule.value).toLowerCase());
            case 'before_today': return raw.slice(0, 10) < todayIso();
            case 'from_today': return raw.slice(0, 10) >= todayIso();
            default: return false;
        }
    };
    const applyColorRules = (scope) => {
        scope?.querySelectorAll('td[data-field]').forEach((cell) => {
            const rules = colorRuleMap[cell.dataset.field];
            const target = cell.querySelector('.data-entry-value, .days-value');
            if (!rules || !target) return;
            ruleColors.forEach((color) => target.classList.remove(`cell-rule-${color}`));
            const numeric = cell.dataset.kind === 'number' || cell.dataset.kind === 'days';
            const rule = rules.find((item) => ruleMatches(item, (cell.dataset.raw || '').trim(), numeric));
            if (rule) target.classList.add(`cell-rule-${rule.color}`);
        });
    };
    document.getElementById('edit_po_sent_date')?.addEventListener('change', calculateDueDates);
    document.getElementById('edit_po_sent_date')?.addEventListener('input', calculateDueDates);
    document.getElementById('edit_installation_date_3')?.addEventListener('change', calculateDaysEarlyLate);
    document.getElementById('editTrackingModal')?.addEventListener('shown.bs.modal', calculateDueDates);
    document.querySelectorAll('.edit-tracking-button').forEach((button) => {
        button.addEventListener('click', () => {
            const customer = JSON.parse(button.dataset.customer || '{}');
            const form = document.getElementById('editTrackingForm');
            if (!form) return;
            form.action = `${document.querySelector('meta[name="app-url"]')?.content || ''}/customers/${encodeURIComponent(customer.id)}`;
            form.dataset.customerId = customer.id;
            document.getElementById('edit_name').value = customer.name || '';
            document.getElementById('edit_return_search').value = customer.name || '';
            document.getElementById('edit_return_query').value = window.location.search || '';
            document.getElementById('editTrackingSubtitle').textContent = `Worksheet #${customer.id}`;
            trackingFields.forEach((field) => {
                const input = document.getElementById(`edit_${field}`);
                if (!input) return;
                if (input.tagName === 'SELECT' && customer[field] && !Array.from(input.options).some((option) => option.value === customer[field])) {
                    input.add(new Option(customer[field], customer[field]));
                }
                input.value = customer[field] || '';
            });
            const draft = readDraft(customer.id);
            if (draft) {
                trackingFields.forEach((field) => {
                    const input = document.getElementById(`edit_${field}`);
                    if (input && Object.prototype.hasOwnProperty.call(draft, field)) input.value = draft[field] || '';
                });
            }
            calculateDueDates();
            calculateDaysEarlyLate();
            window.setTimeout(calculateDueDates, 0);
        });
    });
    const editForm = document.getElementById('editTrackingForm');
    editForm?.querySelectorAll('input, select, textarea').forEach((input) => {
        input.addEventListener('input', saveDraft);
        input.addEventListener('change', saveDraft);
    });
    window.addEventListener('beforeunload', saveDraft);
    editForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const saveButton = editForm.querySelector('button[type="submit"]');
        if (saveButton) { saveButton.disabled = true; saveButton.classList.add('disabled'); }
        try {
            const response = await fetch(editForm.action, {
                method: 'POST',
                body: new FormData(editForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกข้อมูลไม่สำเร็จ');
            clearDraft(editForm.dataset.customerId);
            const modalElement = document.getElementById('editTrackingModal');
            bootstrap.Modal.getInstance(modalElement)?.hide();
            const id = result.customer?.id;
            const editButton = [...document.querySelectorAll('.edit-tracking-button')].find((item) => {
                try { return JSON.parse(item.dataset.customer || '{}').id == id; } catch { return false; }
            });
            const row = editButton?.closest('tr');
            if (row && result.customer) {
                const cells = row.querySelectorAll('td');
                const c = result.customer;
                const dateText = (value) => {
                    if (!value) return '-';
                    const date = new Date(`${value}T00:00:00`);
                    if (Number.isNaN(date.getTime())) return '-';
                    const pad = (number) => String(number).padStart(2, '0');
                    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear() + 543}`;
                };
                const customerName = row.querySelector('.customer-name');
                if (customerName) customerName.textContent = c.name || '-';
                row.setAttribute('aria-label', `ดูรายละเอียด ${c.name || 'ลูกค้า'}`);
                const updateDataEntry = (cell, value, formatter = (item) => item || '-') => {
                    if (!cell) return;
                    let valueElement = cell.querySelector('.data-entry-value');
                    if (!valueElement) {
                        valueElement = document.createElement('span');
                        valueElement.className = 'data-entry-value';
                        cell.replaceChildren(valueElement);
                    }
                    valueElement.textContent = formatter(value);
                    valueElement.classList.toggle('is-empty', !value);
                };
                if (row.closest('table')?.dataset.layout === 'compact') {
                    // Compact tables show a per-company set of columns, so find each cell by its column key.
                    const dateFields = ['po_sent_date','po_due_date','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date'];
                    Object.keys(c).forEach((field) => {
                        const cell = row.querySelector(`.data-entry-${CSS.escape(field)}`);
                        if (!cell) return;
                        cell.dataset.raw = c[field] === null || c[field] === undefined ? '' : String(c[field]);
                        if (cell.dataset.kind === 'days') {
                            const daysValue = cell.querySelector('.days-value');
                            if (daysValue) daysValue.textContent = daysText(c[field]);
                            return;
                        }
                        updateDataEntry(cell, c[field], dateFields.includes(field) || cell.dataset.kind === 'date' ? dateText : undefined);
                    });
                    // The built-in ช้า/เร็ว cell; custom days columns are refreshed by the loop above.
                    const days = row.querySelector('td:not([data-kind="days"]) > .days-value');
                    if (days) days.textContent = daysText(c.days_early_late);
                    const badge = row.querySelector('.status-badge');
                    const color = statusColor(c.status, c.due_60_date);
                    if (badge) {
                        badge.className = `status-badge status-${color}`;
                        badge.replaceChildren(document.createElement('span'), document.createTextNode(c.status || 'STATUS'));
                    }
                    row.classList.remove('row-success', 'row-danger', 'row-warning', 'row-secondary');
                    row.classList.add(`row-${color}`);
                    row.querySelectorAll('td[data-kind="days"] .days-value').forEach((element) => { element.className = `days-value text-${color}`; });
                    applyColorRules(row);
                    if (editButton) editButton.dataset.customer = JSON.stringify(c);
                } else {
                updateDataEntry(cells[2], c.zone);
                updateDataEntry(cells[3], c.machine_size);
                [c.po_sent_date,c.po_due_date,c.engineer_appointment_1,c.installation_date_1,c.engineer_appointment_2,c.installation_date_2,c.engineer_appointment_3,c.installation_date_3,c.due_60_date].forEach((value, index) => updateDataEntry(cells[index + 4], value, dateText));
                if (cells[13]) cells[13].textContent = c.days_early_late === null || c.days_early_late === undefined ? '-' : c.days_early_late;
                if (cells[14]) {
                    const badge = cells[14].querySelector('.status-badge');
                    if (badge) {
                        const color = statusColor(c.status, c.due_60_date);
                        badge.className = `status-badge status-${color}`;
                        badge.replaceChildren(document.createElement('span'), document.createTextNode(c.status || 'STATUS'));
                        row?.classList.remove('row-success', 'row-danger', 'row-warning');
                        if (color !== 'secondary') row?.classList.add(`row-${color}`);
                    }
                }
                [c.construction_notes, c.work_status, c.cap_notes].forEach((value, index) => updateDataEntry(cells[index + 15], value));
                if (editButton) editButton.dataset.customer = JSON.stringify(c);
                }
            }
            const notice = document.createElement('div');
            notice.className = 'system-toast';
            notice.setAttribute('role', 'status');
            notice.innerHTML = '<span class="system-toast-icon"><i class="fa-solid fa-check"></i></span><span class="system-toast-body"><strong>บันทึกสำเร็จ</strong><small></small></span><button type="button" class="system-toast-close" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button>';
            notice.querySelector('small').textContent = result.message || 'ข้อมูลถูกบันทึกเรียบร้อย';
            document.body.appendChild(notice);
            notice.querySelector('.system-toast-close')?.addEventListener('click', () => notice.remove());
            setTimeout(() => notice.remove(), 3500);
        } catch (error) {
            window.alert(error.message || 'บันทึกข้อมูลไม่สำเร็จ');
        } finally {
            if (saveButton) { saveButton.disabled = false; saveButton.classList.remove('disabled'); }
        }
    });

    const notificationButton = document.getElementById('notificationButton');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationList = notificationPanel?.querySelector('.notification-list');
    const notificationCount = notificationButton?.querySelector('.notification-count');
    const notificationDot = notificationButton?.querySelector('.notification-dot');
    const showIncomingNotification = (items) => {
        const item = items.find((candidate) => String(candidate.key || '').startsWith('password-reset-'));
        if (!item) return;
        const storageKey = `cap_plus_seen_${item.key}`;
        try {
            if (localStorage.getItem(storageKey)) return;
            localStorage.setItem(storageKey, '1');
        } catch { /* The notification can still be shown when storage is unavailable. */ }
        const toast = document.createElement('div');
        toast.className = 'system-toast system-toast-notification';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<span class="system-toast-icon"><i class="fa-solid fa-key"></i></span><span class="system-toast-body"><strong>คำขอรีเซ็ตรหัสผ่านใหม่</strong><small></small></span><button type="button" class="system-toast-close" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button>';
        toast.querySelector('small').textContent = item.detail;
        toast.addEventListener('click', (event) => { if (!event.target.closest('.system-toast-close')) window.location.href = item.url; });
        toast.querySelector('.system-toast-close')?.addEventListener('click', (event) => { event.stopPropagation(); toast.remove(); });
        document.body.appendChild(toast);
        window.setTimeout(() => toast.remove(), 7000);
    };
    const loadNotifications = async () => {
        if (!notificationList) return;
        try {
            const response = await fetch(`${document.querySelector('meta[name="app-url"]')?.content || ''}/notifications`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const result = await response.json();
            showIncomingNotification(result.items || []);
            notificationList.replaceChildren();
            if (!result.items?.length) {
                notificationList.innerHTML = '<div class="notification-empty"><i class="fa-regular fa-circle-check me-1"></i>ไม่มีการแจ้งเตือนใหม่</div>';
            } else {
                result.items.forEach((item) => {
                    const link = document.createElement('a');
                    link.className = `notification-item notification-item-${item.type}`;
                    link.href = item.url;
                    link.innerHTML = `<span class="notification-item-icon"><i class="fa-solid ${item.icon}"></i></span><span><strong></strong><small></small></span>`;
                    link.querySelector('strong').textContent = item.title;
                    link.querySelector('small').textContent = item.detail;
                    notificationList.appendChild(link);
                });
            }
            const count = Number(result.count || 0);
            notificationCount.textContent = count;
            notificationCount.classList.toggle('d-none', count === 0);
            notificationDot?.classList.toggle('d-none', count === 0);
        } catch {
            notificationList.innerHTML = '<div class="notification-empty">ไม่สามารถโหลดการแจ้งเตือนได้</div>';
        }
    };
    notificationButton?.addEventListener('click', () => {
        const open = notificationPanel.hidden;
        notificationPanel.hidden = !open;
        notificationButton.setAttribute('aria-expanded', String(open));
        if (open) loadNotifications();
    });
    notificationPanel?.querySelector('.notification-close')?.addEventListener('click', () => {
        notificationPanel.hidden = true;
        notificationButton?.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.notification-wrap') && notificationPanel && !notificationPanel.hidden) {
            notificationPanel.hidden = true;
            notificationButton?.setAttribute('aria-expanded', 'false');
        }
    });
    loadNotifications();
    window.setInterval(loadNotifications, 3000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) loadNotifications();
    });
    document.querySelectorAll('tr[data-failed-attempts]').forEach((row) => {
        const failed = Number(row.dataset.failedAttempts || 0);
        if (failed < 5) return;
        const statusCell = row.cells[4];
        if (!statusCell || statusCell.querySelector('.temporary-lock-badge')) return;
        const badge = document.createElement('div');
        badge.className = 'temporary-lock-badge mt-1';
        badge.innerHTML = '<span class="status-badge status-danger"><span></span>ถูกระงับชั่วคราว</span>';
        statusCell.appendChild(badge);
        const actionsCell = row.cells[row.cells.length - 1];
        const csrf = document.querySelector('input[name="_token"]')?.value;
        if (actionsCell && csrf) {
            const form = document.createElement('form');
            form.method = 'post';
            form.className = 'd-inline ms-1';
            form.action = `${document.querySelector('meta[name="app-url"]')?.content || ''}/admin/users/${encodeURIComponent(row.dataset.userId)}/unlock`;
            form.innerHTML = `<input type="hidden" name="_token" value="${csrf}"><button class="btn btn-sm btn-outline-warning" type="submit" title="ปลดล็อกบัญชี"><i class="fa-solid fa-unlock"></i></button>`;
            actionsCell.appendChild(form);
        }
    });
    const logTables = document.querySelectorAll('.enterprise-table');
    if (location.pathname.includes('/admin/logs') && logTables.length >= 2) {
        const panel = logTables[0].closest('.panel');
        const overview = document.createElement('div');
        overview.className = 'log-overview';
        const activityCount = logTables[0].querySelectorAll('tbody tr').length;
        const loginRows = [...logTables[1].querySelectorAll('tbody tr')];
        const failedCount = loginRows.filter((row) => /ไม่สำเร็จ/i.test(row.textContent)).length;
        overview.innerHTML = `<div class="log-stat"><span class="log-stat-icon blue"><i class="fa-solid fa-list-check"></i></span><span><small>กิจกรรมล่าสุด</small><strong>${activityCount}</strong></span></div><div class="log-stat"><span class="log-stat-icon green"><i class="fa-solid fa-right-to-bracket"></i></span><span><small>ประวัติการเข้าสู่ระบบ</small><strong>${loginRows.length}</strong></span></div><div class="log-stat"><span class="log-stat-icon red"><i class="fa-solid fa-shield-halved"></i></span><span><small>เข้าสู่ระบบไม่สำเร็จ</small><strong>${failedCount}</strong></span></div>`;
        panel?.before(overview);
        const tools = document.createElement('div');
        tools.className = 'log-tools';
        tools.innerHTML = '<div class="log-search"><i class="fa-solid fa-magnifying-glass"></i><input id="logSearch" type="search" placeholder="ค้นหาผู้ใช้, Action, IP หรือรายละเอียด" aria-label="ค้นหาประวัติการใช้งาน"></div><select id="logType" class="form-select"><option value="">ทุกประเภท</option><option value="activity">Activity</option><option value="login">Login</option></select><button type="button" class="btn btn-light" id="logClear">ล้าง</button>';
        overview.after(tools);
        const applyLogFilter = () => {
            const term = (document.getElementById('logSearch')?.value || '').toLowerCase();
            const type = document.getElementById('logType')?.value || '';
            logTables.forEach((table, index) => {
                const section = table.closest('.panel');
                const matchesType = !type || (type === 'activity' && index === 0) || (type === 'login' && index === 1);
                section.hidden = !matchesType;
                if (!matchesType) return;
                table.querySelectorAll('tbody tr').forEach((row) => { row.hidden = !row.textContent.toLowerCase().includes(term); });
            });
        };
        document.getElementById('logSearch')?.addEventListener('input', applyLogFilter);
        document.getElementById('logType')?.addEventListener('change', applyLogFilter);
        document.getElementById('logClear')?.addEventListener('click', () => { document.getElementById('logSearch').value = ''; document.getElementById('logType').value = ''; applyLogFilter(); });
    }

    if (location.pathname.includes('/customers')) {
        const createCustomerButton = document.querySelector('button[data-bs-target="#adminCreateCustomer"]');
        const customerHeaderActions = document.querySelector('.page-heading > .d-flex.gap-2');
        if (createCustomerButton && customerHeaderActions) {
            const createCustomerWrapper = createCustomerButton.parentElement;
            customerHeaderActions.appendChild(createCustomerButton);
            createCustomerWrapper?.remove();
        }
    }


    // Excel-style editing in the customer table: click a cell to fill it in, Enter or Tab saves and steps to the
    // next one. A save posts only the field that changed, so the rest of the record is left untouched, and the
    // response refreshes the whole row because dates and status colours are derived from each other.
    const customerTable = document.querySelector('.enterprise-table');
    if (customerTable) {
        const baseUrl = document.querySelector('meta[name="app-url"]')?.content || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const companyId = customerTable.dataset.companyId || '0';
        const thaiDate = (value) => {
            if (!value) return '-';
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return '-';
            const pad = (number) => String(number).padStart(2, '0');
            return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear() + 543}`;
        };
        const toast = (message, isError) => {
            const element = document.createElement('div');
            element.className = `system-toast${isError ? ' system-toast-error' : ''}`;
            element.setAttribute('role', 'status');
            element.innerHTML = `<span class="system-toast-icon"><i class="fa-solid fa-${isError ? 'circle-exclamation' : 'check'}"></i></span><span class="system-toast-body"><strong>${isError ? 'บันทึกไม่สำเร็จ' : 'บันทึกแล้ว'}</strong><small></small></span>`;
            element.querySelector('small').textContent = message;
            document.body.appendChild(element);
            window.setTimeout(() => element.remove(), isError ? 5000 : 1800);
        };

        applyColorRules(customerTable);
        const editableCells = () => [...customerTable.querySelectorAll('td[data-editable]')];
        const paint = (cell, raw) => {
            cell.dataset.raw = raw === null || raw === undefined ? '' : String(raw);
            const badge = cell.querySelector('.status-badge');
            if (badge) {
                badge.replaceChildren(document.createElement('span'), document.createTextNode(cell.dataset.raw || 'STATUS'));
                return;
            }
            const daysValue = cell.dataset.kind === 'days' ? cell.querySelector('.days-value') : null;
            if (daysValue) {
                daysValue.textContent = daysText(cell.dataset.raw);
                applyColorRules(cell.closest('tr'));
                return;
            }
            let value = cell.querySelector('.data-entry-value');
            if (!value) {
                value = document.createElement('span');
                value.className = 'data-entry-value';
                cell.replaceChildren(value);
            }
            value.textContent = cell.dataset.kind === 'date' ? thaiDate(cell.dataset.raw) : (cell.dataset.raw || '-');
            value.classList.toggle('is-empty', cell.dataset.raw === '');
            if (cell.dataset.kind === 'number') value.classList.add('data-entry-number');
            applyColorRules(cell.closest('tr'));
        };
        const refreshRow = (row, customer) => {
            row.querySelectorAll('td[data-field]').forEach((cell) => {
                if (cell.dataset.editing === '1') return;
                const field = cell.dataset.field;
                if (Object.prototype.hasOwnProperty.call(customer, field)) paint(cell, customer[field]);
            });
            // The built-in ช้า/เร็ว cell; custom days columns carry data-field and were painted above.
            const days = row.querySelector('td:not([data-kind="days"]) > .days-value');
            if (days) days.textContent = daysText(customer.days_early_late);
            const color = statusColor(customer.status, customer.due_60_date);
            const badge = row.querySelector('.status-badge');
            if (badge) badge.className = `status-badge status-${color}`;
            if (days) days.className = `days-value text-${color}`;
            row.querySelectorAll('td[data-kind="days"] .days-value').forEach((element) => { element.className = `days-value text-${color}`; });
            applyColorRules(row);
            row.classList.remove('row-success', 'row-danger', 'row-warning', 'row-secondary');
            row.classList.add(`row-${color}`);
            const modalButton = row.querySelector('.edit-tracking-button');
            if (modalButton) modalButton.dataset.customer = JSON.stringify(customer);
        };

        const save = async (cell, value, previous) => {
            const row = cell.closest('tr');
            const customerId = row?.dataset.customerId;
            if (!customerId) return;
            const body = new FormData();
            body.append('_token', csrfToken);
            // The table being edited decides which columns a user with per-table access may change.
            body.append('access_company_id', companyId);
            if (cell.dataset.customId) {
                body.append('custom_company_id', companyId);
                body.append(`custom[${cell.dataset.customId}]`, value);
            } else {
                body.append(cell.dataset.field, value);
            }
            cell.classList.add('is-saving');
            try {
                const response = await fetch(`${baseUrl}/customers/${encodeURIComponent(customerId)}`, {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const result = await response.json().catch(() => null);
                if (!response.ok || !result?.success) throw new Error(result?.message || (response.status === 419 ? 'เซสชันหมดอายุ กรุณารีเฟรชหน้า' : 'บันทึกข้อมูลไม่สำเร็จ'));
                refreshRow(row, result.customer || {});
                cell.classList.add('is-saved');
                window.setTimeout(() => cell.classList.remove('is-saved'), 900);
            } catch (error) {
                paint(cell, previous);
                cell.classList.add('is-error');
                window.setTimeout(() => cell.classList.remove('is-error'), 2500);
                toast(error.message || 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', true);
            } finally {
                cell.classList.remove('is-saving');
            }
        };

        let openCell = null;
        const closeEditor = (commit) => {
            const cell = openCell;
            if (!cell) return;
            const input = cell.querySelector('.data-entry-input');
            const previous = cell.dataset.raw || '';
            const value = input ? input.value.trim() : previous;
            openCell = null;
            delete cell.dataset.editing;
            paint(cell, commit ? value : previous);
            if (commit && value !== previous) save(cell, value, previous);
        };
        const openEditor = (cell) => {
            if (openCell === cell) return;
            closeEditor(true);
            const raw = cell.dataset.raw || '';
            let input;
            if (cell.dataset.kind === 'select') {
                input = document.createElement('select');
                input.className = 'form-select form-select-sm';
                input.add(new Option('-- ว่าง --', ''));
                JSON.parse(cell.dataset.options || '[]').forEach((option) => input.add(new Option(option, option)));
                if (raw && !Array.from(input.options).some((option) => option.value === raw)) input.add(new Option(raw, raw));
            } else {
                input = document.createElement('input');
                input.type = cell.dataset.kind === 'date' ? 'date' : (cell.dataset.kind === 'number' ? 'number' : 'text');
                input.className = 'form-control form-control-sm';
                if (input.type === 'number') input.step = 'any'; else input.maxLength = 5000;
            }
            input.classList.add('data-entry-input');
            input.value = raw;
            openCell = cell;
            cell.dataset.editing = '1';
            cell.replaceChildren(input);
            // A date cell is typed as dd/mm/yy in the text box (or picked on the calendar button next to it).
            const dateText = input.type === 'date' ? dayFirstDate(input) : null;
            const field = dateText || input;
            field.focus();
            field.select?.();

            if (dateText) {
                // Saved once a date is applied (typed + Enter/leave, or picked), or when focus leaves the box and button.
                input.addEventListener('change', () => { if (openCell === cell) closeEditor(true); });
                dateText.parentNode.addEventListener('focusout', (event) => {
                    if (openCell === cell && !dateText.parentNode.contains(event.relatedTarget)) closeEditor(true);
                });
            } else {
                input.addEventListener('blur', () => { if (openCell === cell) closeEditor(true); });
            }
            field.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeEditor(false);
                    cell.focus();
                    return;
                }
                if (event.key !== 'Enter' && event.key !== 'Tab') return;
                event.preventDefault();
                if (dateText) {
                    // Apply what was typed first (Tab does not do it on its own); an unreadable date keeps the box open.
                    dateText.dispatchEvent(new Event('change'));
                    if (dateText.classList.contains('is-invalid')) return;
                }
                const cells = editableCells();
                const index = cells.indexOf(cell);
                let next = null;
                if (event.key === 'Tab') {
                    next = cells[index + (event.shiftKey ? -1 : 1)] || null;
                } else {
                    // Enter steps down the same column, the way a spreadsheet does.
                    const rows = [...customerTable.querySelectorAll('tbody tr')];
                    const nextRow = rows[rows.indexOf(cell.closest('tr')) + (event.shiftKey ? -1 : 1)];
                    next = nextRow?.querySelector(`td[data-editable][data-field="${CSS.escape(cell.dataset.field)}"]`) || null;
                }
                closeEditor(true);
                if (next) openEditor(next); else cell.focus();
            });
        };

        customerTable.addEventListener('click', (event) => {
            const cell = event.target.closest('td[data-editable]');
            if (!cell || cell.dataset.editing === '1') return;
            event.stopPropagation();
            openEditor(cell);
        });
        customerTable.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== 'F2') return;
            const cell = event.target.closest?.('td[data-editable]');
            if (!cell || cell.dataset.editing === '1' || event.target !== cell) return;
            event.preventDefault();
            event.stopPropagation();
            openEditor(cell);
        });
    }

    if (document.querySelector('.app-shell')) {
        let replacementHandled = false;
        const checkSessionStatus = async () => {
            if (replacementHandled || document.hidden) return;
            try {
                const baseUrl = document.querySelector('meta[name="app-url"]')?.content || '';
                const response = await fetch(`${baseUrl}/session-status`, { credentials: 'same-origin', cache: 'no-store' });
                if (response.status !== 401) return;
                replacementHandled = true;
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.tabIndex = -1;
                modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content session-replaced-modal"><div class="modal-body text-center p-4"><div class="session-warning-icon"><i class="fa-solid fa-right-from-bracket"></i></div><span class="session-status-label">SESSION ENDED</span><h2 class="h4 fw-bold mb-2">เซสชันสิ้นสุดแล้ว</h2><p class="text-secondary mb-3">บัญชีนี้ถูกเข้าใช้งานจากอุปกรณ์อื่น เพื่อความปลอดภัย ระบบจึงออกจากระบบของคุณ</p><div class="session-info"><i class="fa-solid fa-shield-halved"></i><span>กรุณาเข้าสู่ระบบอีกครั้งเพื่อใช้งานต่อ</span></div><button type="button" class="btn btn-primary w-100 mt-4" data-bs-dismiss="modal">ไปหน้าเข้าสู่ระบบ <i class="fa-solid fa-arrow-right ms-2"></i></button></div></div></div>';
                document.body.appendChild(modal);
                const instance = bootstrap.Modal.getOrCreateInstance(modal, { backdrop: 'static', keyboard: false });
                modal.addEventListener('hidden.bs.modal', () => { window.location.href = `${baseUrl}/login?reason=session-replaced`; });
                instance.show();
            } catch { /* A temporary network issue must not sign the user out. */ }
        };
        window.setInterval(checkSessionStatus, 5000);
    }
})();

// Inline required-field validation for forms marked data-validate (Thai message under the field, no browser bubble).
(() => {
    'use strict';
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        const fields = [...form.querySelectorAll('input, select, textarea')].filter((field) => field.type !== 'hidden' && (field.required || field.dataset.invalidMessage));
        const isEmpty = (field) => (field.type === 'checkbox' ? !field.checked : field.value.trim() === '');
        // A required field is a problem when empty; a filled field is a problem when its format is wrong (e.g. an email without "@").
        const problem = (field) => {
            if (field.required && isEmpty(field)) return field.dataset.requiredMessage || 'กรุณากรอกข้อมูลในช่องนี้';
            if (!isEmpty(field) && !field.validity.valid) return field.dataset.invalidMessage || 'รูปแบบข้อมูลไม่ถูกต้อง';
            return null;
        };
        const errorBox = (field) => document.getElementById(`${field.id}Error`);
        const show = (field, message) => {
            const box = errorBox(field);
            field.classList.add('has-error');
            field.setAttribute('aria-invalid', 'true');
            if (box) {
                box.querySelector('span').textContent = message;
                box.hidden = false;
            }
        };
        const hide = (field) => {
            const box = errorBox(field);
            field.classList.remove('has-error');
            field.removeAttribute('aria-invalid');
            if (box) box.hidden = true;
        };
        form.addEventListener('submit', (event) => {
            let first = null;
            fields.forEach((field) => {
                const message = problem(field);
                if (message) {
                    show(field, message);
                    first = first || field;
                } else {
                    hide(field);
                }
            });
            if (!first) return;
            event.preventDefault();
            first.focus();
            const wrapper = first.closest('.input-icon');
            if (wrapper) {
                wrapper.classList.remove('shake');
                void wrapper.offsetWidth;
                wrapper.classList.add('shake');
            }
        });
        fields.forEach((field) => {
            const clear = () => { if (field.classList.contains('has-error') && !problem(field)) hide(field); };
            field.addEventListener('input', clear);
            field.addEventListener('change', clear);
        });
    });
})();
