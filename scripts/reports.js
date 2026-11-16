// ============================
// Stock Keeper Reports Module
// ============================
document.addEventListener("DOMContentLoaded", () => {
    'use strict';

    const reportsPage = document.getElementById('reportsPage');
    if (!reportsPage) return;

    const app = window.AppCoreInstance;
    let reportData = null;

    const money = value => app.formatMoney(value);
    const qty = value => app.formatQuantity(value);
    const esc = value => app.escapeHtml(value);

    async function loadReports() {
        const reportMessage = document.getElementById('reportMessage');
        if (reportMessage) { reportMessage.classList.add('d-none'); reportMessage.textContent = ''; }
        const reportProducts = document.getElementById('reportProducts');
        const reportPartners = document.getElementById('reportPartners');
        if (reportProducts) reportProducts.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading reports…</td></tr>';
        if (reportPartners) reportPartners.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading reports…</td></tr>';
        try {
            const response = await app.requestApi('apiReports.php', 'load', {
                from_date: document.getElementById('reportFromDate')?.value || '',
                to_date: document.getElementById('reportToDate')?.value || ''
            });
            reportData = response.data || {};
            render(reportData);
        } catch (error) {
            if (error.status === 401) return;
            if (reportMessage) { reportMessage.classList.remove('d-none'); reportMessage.textContent = error.message || 'Could not load reports.'; }
        }
    }

    function render(data) {
        const m = data.movements || {}, inv = data.inventory || {}, b = data.balances || {}, st = data.stock_takes || {};

        const setTxt = (id, text) => app.setText('#' + id, text);

        setTxt('reportPurchaseValue', money(m.purchase_value));
        setTxt('reportSalesValue', money(m.sales_value));
        setTxt('reportQuantityIn', qty(m.quantity_in) + ' units received');
        setTxt('reportQuantityOut', qty(m.quantity_out) + ' units issued');
        setTxt('reportTransactionCount', qty(m.transactions));
        setTxt('reportAdjustmentCount', qty(m.adjustments) + ' adjustments / price updates');
        setTxt('reportStockTakeCount', qty(st.count));
        setTxt('reportStockTakeChanged', qty(st.changed_products) + ' product counts with variance');
        setTxt('reportInventoryCost', money(inv.cost_value));
        setTxt('reportInventorySelling', money(inv.selling_value));
        setTxt('reportLowStock', qty(inv.low_stock));
        setTxt('reportOutOfStock', qty(inv.out_of_stock));
        setTxt('reportCustomerDue', money(b.customer_outstanding));
        setTxt('reportSupplierDue', money(b.supplier_outstanding));
        setTxt('reportCustomerAdvance', money(b.customer_advance));
        setTxt('reportSupplierAdvance', money(b.supplier_advance));

        const from = data.from_date || 'Beginning';
        const to = data.to_date || 'Today';
        setTxt('reportPeriodLabel', 'Period: ' + from + ' to ' + to);

        const products = Array.isArray(data.products) ? data.products : [];
        const reportProducts = document.getElementById('reportProducts');
        if (reportProducts) {
            reportProducts.innerHTML = products.length ? products.map(p => `<tr>
                <td>${esc(p.product_name)}${p.sku ? '<small class="d-block text-muted">' + esc(p.sku) + '</small>' : ''}</td>
                <td class="text-end">${qty(p.quantity_in)} ${esc(p.product_unit)}</td>
                <td class="text-end text-nowrap">${money(p.purchase_value)}</td>
                <td class="text-end">${qty(p.quantity_out)} ${esc(p.product_unit)}</td>
                <td class="text-end text-nowrap">${money(p.sales_value)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No product movement in this period.</td></tr>';
        }

        const partners = Array.isArray(data.partners) ? data.partners : [];
        const reportPartners = document.getElementById('reportPartners');
        if (reportPartners) {
            reportPartners.innerHTML = partners.length ? partners.map(p => `<tr>
                <td>${esc(p.name)}</td>
                <td class="text-end text-nowrap">${money(p.customer_debits)}</td>
                <td class="text-end text-nowrap">${money(p.customer_credits)}</td>
                <td class="text-end text-nowrap">${money(p.supplier_debits)}</td>
                <td class="text-end text-nowrap">${money(p.supplier_credits)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No partner account activity in this period.</td></tr>';
        }
    }

    const reportFilters = document.getElementById('reportFilters');
    if (reportFilters) {
        reportFilters.addEventListener('submit', function (e) {
            e.preventDefault();
            loadReports();
        });
    }

    const reportAllDates = document.getElementById('reportAllDates');
    if (reportAllDates) {
        reportAllDates.addEventListener('click', function () {
            const fromDateEl = document.getElementById('reportFromDate');
            const toDateEl = document.getElementById('reportToDate');
            if (fromDateEl) fromDateEl.value = '';
            if (toDateEl) toDateEl.value = '';
            loadReports();
        });
    }

    const exportReportCsv = document.getElementById('exportReportCsv');
    if (exportReportCsv) {
        exportReportCsv.addEventListener('click', function () {
            if (!reportData) return;
            const m = reportData.movements || {}, inv = reportData.inventory || {}, b = reportData.balances || {};
            const rows = [
                ['Stock Keeper Report', 'From', reportData.from_date || 'Beginning', 'To', reportData.to_date || 'Today'],
                [], ['Period summary', 'Value'],
                ['Purchases recorded', m.purchase_value || 0], ['Sales recorded', m.sales_value || 0],
                ['Quantity received', m.quantity_in || 0], ['Quantity issued', m.quantity_out || 0],
                ['Stock transactions', m.transactions || 0], ['Adjustments', m.adjustments || 0],
                [], ['Current inventory snapshot (all dates)', 'Value'],
                ['Inventory at current cost (not receipt layers)', inv.cost_value || 0], ['Potential selling value', inv.selling_value || 0],
                ['Low stock products', inv.low_stock || 0], ['Out of stock products', inv.out_of_stock || 0],
                [], ['Current partner balances (all dates)', 'Value'],
                ['Customer receivables', b.customer_outstanding || 0], ['Supplier payables', b.supplier_outstanding || 0],
                ['Customer advances held', b.customer_advance || 0], ['Supplier advances paid', b.supplier_advance || 0],
                [], ['Product', 'SKU', 'Stock in', 'Purchase value', 'Stock out', 'Sales value']
            ];
            (reportData.products || []).forEach(p => rows.push([p.product_name, p.sku || '', p.quantity_in, p.purchase_value, p.quantity_out, p.sales_value]));
            rows.push([], ['Partner', 'Customer debits', 'Customer credits', 'Supplier debits', 'Supplier credits']);
            (reportData.partners || []).forEach(p => rows.push([p.name, p.customer_debits, p.customer_credits, p.supplier_debits, p.supplier_credits]));
            
            const csv = rows.map(row => row.map(cell => '"' + String(cell == null ? '' : cell).replace(/"/g, '""') + '"').join(',')).join('\r\n');
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob); 
            const a = document.createElement('a');
            a.href = url; 
            a.download = 'stock-keeper-report.csv'; 
            document.body.appendChild(a); 
            a.click(); 
            a.remove(); 
            URL.revokeObjectURL(url);
        });
    }

    loadReports();
});