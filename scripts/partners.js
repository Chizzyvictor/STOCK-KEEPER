// ============================
// Stock Keeper Partners Module
// ============================
document.addEventListener("DOMContentLoaded", () => {
    const partnersPage = document.getElementById('partnersPage');
    if (!partnersPage) return;

    const app = window.AppCoreInstance;
    const bodyEl = document.getElementById('partnersTableBody');
    let partners = [];
    let statementData = null;

    const escapeHtml = value => app.escapeHtml(value);
    const money = value => app.formatMoney(value);

    function balanceText(value, account) {
        const amount = Number(value) || 0;
        if (Math.abs(amount) < 0.005) return money(0);
        const label = account === 'customer'
            ? (amount > 0 ? 'customer owes us' : 'customer advance')
            : (amount > 0 ? 'payable to supplier' : 'supplier advance paid');
        return money(Math.abs(amount)) + ' (' + label + ')';
    }

    async function request(action, data = {}, success) {
        try {
            const response = await app.requestApi('apiPartners.php', action, data);
            success?.(response.data || {}, response.message || 'Done.');
        } catch (error) {
            if (error.status !== 401) app.showAlert(error.message || 'Partners request failed.', 'error');
        }
    }

    function render() {
        const searchInput = document.getElementById('partnerSearch');
        const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
        
        const shown = partners.filter(function (p) {
            return [p.name, p.phone, p.email, p.role].join(' ').toLowerCase().includes(term);
        });

        if (!bodyEl) return;

        if (!shown.length) {
            bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">No partners match this search.</td></tr>';
        } else {
            bodyEl.innerHTML = shown.map(function (p) {
                const role = {supplier:'Supplier', customer:'Customer', both:'Supplier and customer'}[p.role] || p.role;
                const active = p.status === 'active';
                const isSystem = Number(p.is_system) === 1;
                const contact = [p.phone, p.email].filter(Boolean).map(escapeHtml).join('<br>') || '—';
                const customerAccount = p.role === 'customer' || p.role === 'both' || Number(p.customer_outstanding) > 0 || Number(p.customer_advance) > 0;
                const supplierAccount = p.role === 'supplier' || p.role === 'both' || Number(p.supplier_outstanding) > 0 || Number(p.supplier_advance) > 0;
                
                const payButtons = (customerAccount ? `<button class="dropdown-item partner-payment" data-account="customer" data-id="${Number(p.partner_id)}">Receive customer payment</button>` : '') +
                    (supplierAccount ? `<button class="dropdown-item partner-payment" data-account="supplier" data-id="${Number(p.partner_id)}">Pay supplier</button>` : '');
                
                return `<tr>
                    <td><strong>${escapeHtml(p.name)}</strong>${isSystem?'<small class="d-block text-muted">System account</small>':''}</td>
                    <td>${escapeHtml(role)}</td>
                    <td>${contact}</td>
                    <td class="text-end">${money(p.customer_outstanding)}</td>
                    <td class="text-end">${money(p.supplier_outstanding)}</td>
                    <td class="text-end">${money(p.customer_advance)}</td>
                    <td class="text-end">${money(p.supplier_advance)}</td>
                    <td><span class="badge bg-${active?'success':'secondary'}">${active?'Active':'Inactive'}</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary partner-history" data-id="${Number(p.partner_id)}">Activity</button>
                            <button class="btn btn-sm btn-outline-info partner-statement" data-id="${Number(p.partner_id)}" data-account="${customerAccount && !supplierAccount ? 'customer' : 'supplier'}">Statement</button>
                            ${isSystem ? '' : `<button class="btn btn-sm btn-outline-primary partner-edit" data-id="${Number(p.partner_id)}">Edit</button>`}
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More actions"></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                ${payButtons}
                                ${active && !isSystem ? '<div class="dropdown-divider"></div><button class="dropdown-item text-danger partner-archive" data-id="' + Number(p.partner_id) + '">Archive</button>' : ''}
                            </div>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        const sum = function (field) { return partners.reduce(function (n, p) { return n + (Number(p[field]) || 0); }, 0); };
        
        const setText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        setText('partnerCount', partners.length);
        setText('customerOutstandingTotal', money(sum('customer_outstanding')));
        setText('supplierOutstandingTotal', money(sum('supplier_outstanding')));
        setText('partnerAdvanceTotal', money(sum('customer_advance') + sum('supplier_advance')));
        setText('partnersListMessage', 'Showing ' + shown.length + ' of ' + partners.length + ' partners.');
    }

    function load() {
        request('list', {}, function (data) { 
            partners = Array.isArray(data.partners) ? data.partners : []; 
            render(); 
        });
    }

    function openPartner(p) {
        const form = document.getElementById('partnerForm');
        if (form) form.reset();

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };

        setVal('partnerId', p ? p.partner_id : '');
        const titleEl = document.getElementById('partnerModalTitle');
        if (titleEl) titleEl.textContent = p ? 'Edit Partner' : 'Add Partner';

        setVal('partnerName', p ? p.name : '');
        setVal('partnerPhone', p ? p.phone : '');
        setVal('partnerEmail', p ? p.email : '');
        setVal('partnerAddress', p ? p.address : '');
        setVal('partnerRole', p ? p.role : 'both');
        setVal('partnerStatus', p ? p.status : 'active');
        setVal('partnerNotes', p ? p.notes : '');

        const openingBalancesEl = document.getElementById('partnerOpeningBalances');
        if (openingBalancesEl) {
            openingBalancesEl.style.display = p ? 'none' : 'block';
        }
        document.querySelectorAll('#partnerOpeningBalances input').forEach(input => {
            input.disabled = Boolean(p);
        });

        const modalEl = document.getElementById('partnerModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    const addBtn = document.getElementById('addPartnerButton');
    if (addBtn) addBtn.addEventListener('click', () => openPartner(null));

    const refreshBtn = document.getElementById('refreshPartnersButton');
    if (refreshBtn) refreshBtn.addEventListener('click', load);

    const searchInput = document.getElementById('partnerSearch');
    if (searchInput) searchInput.addEventListener('input', render);

    if (bodyEl) {
        bodyEl.addEventListener('click', function (e) {
            const target = e.target;

            // Edit Partner
            if (target.classList.contains('partner-edit')) {
                const id = Number(target.getAttribute('data-id'));
                openPartner(partners.find(p => Number(p.partner_id) === id));
            }

            // Archive Partner
            if (target.classList.contains('partner-archive')) {
                const id = Number(target.getAttribute('data-id'));
                const p = partners.find(item => Number(item.partner_id) === id);
                if (!p || !window.confirm('Archive ' + p.name + '? Existing movements and balances will be preserved.')) return;
                request('archive', { partner_id: p.partner_id }, (_, message) => { 
                    window.alert(message); 
                    load(); 
                });
            }

            // Payment Modal Trigger
            if (target.classList.contains('partner-payment')) {
                const id = Number(target.getAttribute('data-id'));
                const p = partners.find(item => Number(item.partner_id) === id);
                if (!p) return;
                const account = String(target.getAttribute('data-account'));

                const paymentForm = document.getElementById('partnerPaymentForm');
                if (paymentForm) paymentForm.reset();

                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.value = val;
                };

                setVal('paymentPartnerId', p.partner_id);
                setVal('paymentAccountType', account);

                const titleEl = document.getElementById('partnerPaymentTitle');
                if (titleEl) titleEl.textContent = account === 'customer' ? 'Receive Customer Payment' : 'Pay Supplier';

                const nameEl = document.getElementById('paymentPartnerName');
                if (nameEl) nameEl.textContent = p.name;

                const paymentModalEl = document.getElementById('partnerPaymentModal');
                if (paymentModalEl) {
                    const modal = bootstrap.Modal.getInstance(paymentModalEl) || new bootstrap.Modal(paymentModalEl);
                    modal.show();
                }
            }

            // Activity History Modal Trigger
            if (target.classList.contains('partner-history')) {
                const id = Number(target.getAttribute('data-id'));
                const p = partners.find(item => Number(item.partner_id) === id);
                if (!p) return;

                const titleEl = document.getElementById('partnerHistoryTitle');
                if (titleEl) titleEl.textContent = p.name + ' — Account Activity';

                const historyBody = document.getElementById('partnerHistoryBody');
                if (historyBody) historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';

                const historyModalEl = document.getElementById('partnerHistoryModal');
                if (historyModalEl) {
                    const modal = bootstrap.Modal.getInstance(historyModalEl) || new bootstrap.Modal(historyModalEl);
                    modal.show();
                }

                request('history', { partner_id: p.partner_id }, data => {
                    const entries = Array.isArray(data.entries) ? data.entries : [];
                    if (!entries.length) {
                        if (historyBody) historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No account activity recorded.</td></tr>';
                        return;
                    }
                    if (historyBody) {
                        historyBody.innerHTML = entries.map(e => `<tr>
                            <td>${escapeHtml(e.created_at)}</td>
                            <td>${escapeHtml(e.account_type === 'customer' ? 'Customer' : 'Supplier')}</td>
                            <td>${escapeHtml(e.entry_type)}</td>
                            <td class="text-end">${money(e.amount)}</td>
                            <td>${escapeHtml(e.note || '—')}</td>
                        </tr>`).join('');
                    }
                });
            }

            // Statement Modal Trigger
            if (target.classList.contains('partner-statement')) {
                const id = Number(target.getAttribute('data-id'));
                const partner = partners.find(item => Number(item.partner_id) === id);
                
                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.value = val;
                };

                setVal('statementPartnerId', id);
                setVal('statementAccount', String(target.getAttribute('data-account')));
                setVal('statementFromDate', '');
                setVal('statementToDate', '');
                statementData = null;

                const titleEl = document.getElementById('partnerStatementTitle');
                if (titleEl) titleEl.textContent = (partner ? partner.name : 'Partner') + ' — Statement';

                const statementModalEl = document.getElementById('partnerStatementModal');
                if (statementModalEl) {
                    const modal = bootstrap.Modal.getInstance(statementModalEl) || new bootstrap.Modal(statementModalEl);
                    modal.show();
                }
                loadStatement();
            }
        });
    }

    const partnerForm = document.getElementById('partnerForm');
    if (partnerForm) {
        partnerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const fields = {};
            formData.forEach((value, key) => { fields[key] = value; });

            request('save', fields, (_, message) => {
                const modalEl = document.getElementById('partnerModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                window.alert(message);
                load();
            });
        });
    }

    const partnerPaymentForm = document.getElementById('partnerPaymentForm');
    if (partnerPaymentForm) {
        partnerPaymentForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const fields = {};
            formData.forEach((value, key) => { fields[key] = value; });

            request('payment', fields, (_, message) => {
                const modalEl = document.getElementById('partnerPaymentModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                window.alert(message);
                load();
            });
        });
    }

    function loadStatement() {
        const partnerId = Number(document.getElementById('statementPartnerId')?.value || 0);
        const statementBody = document.getElementById('partnerStatementBody');
        if (statementBody) statementBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading statement…</td></tr>';

        request('statement', {
            partner_id: partnerId,
            account_type: document.getElementById('statementAccount')?.value || 'customer',
            from_date: document.getElementById('statementFromDate')?.value || '',
            to_date: document.getElementById('statementToDate')?.value || ''
        }, function (data) {
            statementData = data;
            const account = data.account_type;

            const setText = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };

            setText('partnerStatementTitle', (data.partner && data.partner.name ? data.partner.name : 'Partner') + ' — ' + (account === 'customer' ? 'Customer' : 'Supplier') + ' Statement');
            setText('partnerStatementPeriod', 'Period: ' + (data.from_date || 'Beginning') + ' to ' + (data.to_date || 'Today'));
            setText('statementOpeningBalance', balanceText(data.opening_balance, account));
            setText('statementDebits', money(data.total_debits));
            setText('statementCredits', money(data.total_credits));
            setText('statementClosingBalance', balanceText(data.closing_balance, account));
            setText('statementCurrentBalance', balanceText(data.current_balance, account));

            const entries = Array.isArray(data.entries) ? data.entries : [];
            const labels = {opening:'Opening balance', sale:'Sale / stock out', purchase:'Purchase / stock in', payment:'Payment', refund:'Refund'};
            
            if (!entries.length) {
                if (statementBody) statementBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No activity in this period.</td></tr>';
                return;
            }

            if (statementBody) {
                statementBody.innerHTML = entries.map(function (entry) {
                    const running = Number(entry.balance_after) || 0;
                    return `<tr>
                        <td class="text-nowrap">${escapeHtml(entry.created_at)}</td>
                        <td>${escapeHtml(labels[entry.entry_type] || entry.entry_type)}</td>
                        <td>${escapeHtml(entry.note || '—')}</td>
                        <td class="text-end text-nowrap">${Number(entry.debit) > 0 ? money(entry.debit) : '—'}</td>
                        <td class="text-end text-nowrap">${Number(entry.credit) > 0 ? money(entry.credit) : '—'}</td>
                        <td class="text-end text-nowrap">${balanceText(running, account)}</td>
                    </tr>`;
                }).join('');
            }
        });
    }

    const statementFilters = document.getElementById('partnerStatementFilters');
    if (statementFilters) {
        statementFilters.addEventListener('submit', function (event) {
            event.preventDefault();
            loadStatement();
        });
    }

    const statementAccountEl = document.getElementById('statementAccount');
    if (statementAccountEl) statementAccountEl.addEventListener('change', loadStatement);

    const statementAllDatesBtn = document.getElementById('statementAllDates');
    if (statementAllDatesBtn) {
        statementAllDatesBtn.addEventListener('click', function () {
            const fromDateEl = document.getElementById('statementFromDate');
            const toDateEl = document.getElementById('statementToDate');
            if (fromDateEl) fromDateEl.value = '';
            if (toDateEl) toDateEl.value = '';
            loadStatement();
        });
    }

    const printStatementBtn = document.getElementById('printPartnerStatement');
    if (printStatementBtn) printStatementBtn.addEventListener('click', () => window.print());

    const exportStatementBtn = document.getElementById('exportPartnerStatement');
    if (exportStatementBtn) {
        exportStatementBtn.addEventListener('click', function () {
            if (!statementData) return;
            const account = statementData.account_type;
            const rows = [
                ['Partner statement', statementData.partner.name],
                ['Account', account === 'customer' ? 'Customer' : 'Supplier'],
                ['From', statementData.from_date || 'Beginning'], ['To', statementData.to_date || 'Today'],
                ['Opening balance', statementData.opening_balance], ['Total debits', statementData.total_debits],
                ['Total credits', statementData.total_credits], ['Closing balance', statementData.closing_balance],
                ['Current balance (all dates)', statementData.current_balance], [],
                ['Date','Activity','Note','Debit','Credit','Running balance']
            ];
            (statementData.entries || []).forEach(function (entry) {
                rows.push([entry.created_at, entry.entry_type, entry.note, entry.debit, entry.credit, entry.balance_after]);
            });
            const csv = rows.map(function (row) { return row.map(function (cell) { return '"'+String(cell == null ? '' : cell).replace(/"/g,'""')+'"'; }).join(','); }).join('\r\n');
            const blob = new Blob(['\uFEFF'+csv], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob); 
            const a = document.createElement('a');
            a.href = url; 
            a.download = 'partner-statement.csv'; 
            document.body.appendChild(a); 
            a.click(); 
            a.remove(); 
            URL.revokeObjectURL(url);
        });
    }

    load();
});