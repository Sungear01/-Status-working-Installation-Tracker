(() => {
    'use strict';
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
    document.querySelector('[data-realtime-search]')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => filterForm?.requestSubmit(), 450);
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
        row.addEventListener('click', (event) => { if (!event.target.closest('button, a, input, select, textarea')) go(); });
        row.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') go(); });
    });

    const trackingFields = ['name','zone','machine_size','po_sent_date','po_due_date','engineer_appointment_1','installation_date_1','engineer_appointment_2','installation_date_2','engineer_appointment_3','installation_date_3','due_60_date','status','work_status','construction_notes','cap_notes'];
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
        if (!sent || !due || !due60) return;
        if (!sent.value) { due.value = ''; due60.value = ''; return; }
        const date = new Date(`${sent.value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return;
        const firstDue = new Date(date);
        firstDue.setDate(firstDue.getDate() + 61);
        const secondDue = new Date(date);
        secondDue.setDate(secondDue.getDate() + 60);
        const pad = (value) => String(value).padStart(2, '0');
        due.value = `${firstDue.getFullYear()}-${pad(firstDue.getMonth() + 1)}-${pad(firstDue.getDate())}`;
        due60.value = `${secondDue.getFullYear()}-${pad(secondDue.getMonth() + 1)}-${pad(secondDue.getDate())}`;
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
