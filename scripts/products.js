// ============================
// Stock Keeper Products Module
// ============================
document.addEventListener("DOMContentLoaded", () => {
    'use strict';

    const productsPage = document.getElementById('productsPage');
    if (!productsPage) return;

    const App = window.AppCoreInstance;

    const productTable = document.getElementById('productsTableBody');
    const productSearch = document.getElementById('productSearchInput');
    const categoryFilter = document.getElementById('productCategoryFilter');
    const statusFilter = document.getElementById('productStatusFilter');
    const productSort = document.getElementById('productSortSelect');
    const filterSummary = document.getElementById('productFilterSummary');

    let productsCache = [];
    let productSearchTimer = null;
    let stockTakeCounts = {};
    let productSkuEdited = false;

    let productUnitsCache = [];
    let productDefaultUnit = 'pcs';
    let productDefaultReorderLevel = '0';

    function loadProductUnits(callback) {
        const addUnit = document.getElementById('productUnitSelect1');
        const editUnit = document.getElementById('editProductUnitSelect');
        const setFallbackUnits = () => {
            const fallback = '<option value="pcs">Pieces (pcs)</option>';
            if (addUnit && (!addUnit.options.length || addUnit.textContent.includes('Loading'))) addUnit.innerHTML = fallback;
            if (editUnit && (!editUnit.options.length || editUnit.textContent.includes('Loading'))) editUnit.innerHTML = fallback;
        };
        App.requestApi('apiUnits.php', 'list').then(response => {
            const units = response.data?.units;
            productUnitsCache = Array.isArray(units) ? units.filter(unit => Number(unit.is_active) === 1) : [];
            if (!productUnitsCache.length) { setFallbackUnits(); callback?.(); return; }
            const options = productUnitsCache.map(unit => {
                const value = String(unit.unit_symbol || unit.unit_name);
                const name = String(unit.unit_name || value);
                const symbol = String(unit.unit_symbol || '');
                const label = symbol && symbol !== name ? name + ' (' + symbol + ')' : name;
                return '<option value="' + productEscape(value) + '">' + productEscape(label) + '</option>';
            }).join('');
            if (addUnit) addUnit.innerHTML = options;
            if (editUnit) editUnit.innerHTML = options;
            if (addUnit) addUnit.value = addUnit.querySelector('option[value="pcs"]') ? 'pcs' : (addUnit.options[0]?.value || '');
            callback?.();
        }).catch(error => {
            if (error.status !== 401) console.error('Product Units API error:', error);
            setFallbackUnits();
            callback?.();
        });
    }

        function loadProductDefaults() {
        App.requestApi('apiSettings.php', 'load').then(response => {
            const settings = response.data?.settings || {};
            const unit = String(settings.default_unit || 'pcs');
            productDefaultUnit = unit;
            productDefaultReorderLevel = String(settings.default_reorder_level ?? '0');
            const unitSelect = document.getElementById('productUnitSelect1');
            if (unitSelect) {
                if (![...unitSelect.options].some(option => option.value === unit)) {
                    const option = document.createElement('option');
                    option.value = unit; option.textContent = unit; unitSelect.append(option);
                }
                unitSelect.value = unit;
            }
            const reorderInput = document.getElementById('reorderLevelInput');
            if (reorderInput) reorderInput.value = productDefaultReorderLevel;
            localStorage.setItem('stockWarnings', JSON.stringify({ low: settings.low_stock_warning !== '0', out: settings.out_of_stock_warning !== '0' }));
            if (typeof loadProducts === 'function') loadProducts();
        }).catch(error => { if (error.status !== 401) console.error('Settings API error:', error); });
    }

    function productEscape(value) { return App.escapeHtml(value); }

    function productNumber(value) { return App.formatQuantity(value); }

        function skuSegment(value) {
        return String(value || '')
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function suggestProductSku() {
        const nameInput = document.getElementById('productNameInput');
        const catSelect = document.getElementById('productCategorySelect');
        const skuInput = document.getElementById('productSkuInput');
        if (!nameInput || !skuInput) return;

        const name = skuSegment(nameInput.value);
        if (!name) {
            skuInput.value = '';
            return;
        }

        const categoryName = catSelect && catSelect.options[catSelect.selectedIndex] ? catSelect.options[catSelect.selectedIndex].text : '';
        const category = catSelect && catSelect.value === '0'
            ? 'GENERAL'
            : (skuSegment(categoryName) || 'GENERAL');
        const base = (category.slice(0, 35) + '-' + name.slice(0, 64))
            .slice(0, 100)
            .replace(/-+$/g, '');
        const existing = new Set(productsCache.map(product => String(product.sku || '').trim().toUpperCase()));

        let candidate = base;
        let suffix = 2;
        while (existing.has(candidate.toUpperCase())) {
            const ending = '-' + suffix++;
            candidate = base.slice(0, 100 - ending.length).replace(/-+$/g, '') + ending;
        }
        skuInput.value = candidate;
    }

    function productMoney(value) { return App.formatMoney(value); }

    function getProductStockStatus(product) {
        const quantity = Number(product.quantity) || 0;
        const reorderLevel = Number(product.reorder_level) || 0;
        let warnings = { low: true, out: true };
        try {
            const savedWarnings = JSON.parse(localStorage.getItem('stockWarnings') || 'null');
            if (savedWarnings && typeof savedWarnings === 'object') warnings = Object.assign(warnings, savedWarnings);
        } catch (error) { /* Keep enabled */ }

        if (quantity <= 0) {
            if (!warnings.out) return null;
            return { label: 'Out of stock', className: 'stock-status-out' };
        }

        if (quantity <= reorderLevel && warnings.low) {
            return { label: 'Low stock', className: 'stock-status-low' };
        }

        if (quantity > 0 && quantity <= reorderLevel && !warnings.low) return null;

        return { label: 'In stock', className: 'stock-status-in' };
    }

    async function productRequest(data, onSuccess, onComplete) {
        const action = data.action;
        try {
            const response = await App.requestApi('apiProducts.php', action, data);
            onSuccess?.(response.data || {}, response.message || 'Success.');
        } catch (error) {
            if (error.status !== 401) App.showAlert(error.message || 'The request could not be completed.', 'error');
        } finally { onComplete?.(); }
    }

    function loadProductCategories() {
        productRequest(
            { action: 'listCategories' },
            function (data) {
                const categories = Array.isArray(data.categories) ? data.categories : [];
                let options = '<option value="0">No category</option>';
                let filterOptions = '<option value="">All categories</option>';

                categories.forEach(category => {
                    const id = String(category.category_id);
                    const name = productEscape(category.category_name);
                    options += `<option value="${productEscape(id)}">${name}</option>`;
                    filterOptions += `<option value="${productEscape(id)}">${name}</option>`;
                });

                const catSelect = document.getElementById('productCategorySelect');
                const editCatSelect = document.getElementById('editProductCategorySelect');
                if (catSelect) catSelect.innerHTML = options;
                if (editCatSelect) editCatSelect.innerHTML = options;
                if (categoryFilter) categoryFilter.innerHTML = filterOptions;
            }
        );
    }

    function renderProducts() {
        const search = productSearch ? productSearch.value.trim().toLowerCase() : '';
        const category = categoryFilter ? categoryFilter.value : '';
        const status = statusFilter ? statusFilter.value : '';

        let filtered = productsCache.filter(product => {
            const searchable = (
                String(product.product_name || '') + ' ' +
                String(product.sku || '') + ' ' +
                String(product.category_name || '') + ' ' +
                String(product.product_unit || '')
            ).toLowerCase();

            const matchesSearch = !search || searchable.includes(search);
            const matchesCategory = !category || String(product.category_id || 0) === category;
            const matchesStatus = status === '' || String(product.is_active) === status;

            return matchesSearch && matchesCategory && matchesStatus;
        });

        const sortMode = productSort ? productSort.value : 'name-asc';

        filtered.sort((a, b) => {
            const nameA = String(a.product_name || '').toLowerCase();
            const nameB = String(b.product_name || '').toLowerCase();
            const priceA = Number(a.selling_price) || 0;
            const priceB = Number(b.selling_price) || 0;
            const stockA = Number(a.quantity) || 0;
            const stockB = Number(b.quantity) || 0;

            switch (sortMode) {
                case 'name-desc': return nameB.localeCompare(nameA);
                case 'price-asc': return priceA - priceB;
                case 'price-desc': return priceB - priceA;
                case 'stock-asc': return stockA - stockB;
                case 'stock-desc': return stockB - stockA;
                case 'name-asc':
                default: return nameA.localeCompare(nameB);
            }
        });

        if (filterSummary && filterSummary.length !== 0) {
            const hasFilters = Boolean(search || category || status !== '');
            filterSummary.textContent = hasFilters
                ? 'Filters applied · ' + filtered.length + ' match(es)'
                : filtered.length + ' product(s)';
        }

        const setTxt = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
        setTxt('productCount', productsCache.length);
        setTxt('activeProductCount', productsCache.filter(p => Number(p.is_active) === 1).length);
        setTxt('inactiveProductCount', productsCache.filter(p => Number(p.is_active) === 0).length);
        setTxt('matchingProductCount', filtered.length);

        if (!productTable) return;

        if (filtered.length === 0) {
            productTable.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fa fa-box-open fa-2x mb-2 d-block"></i>
                        No matching products found.
                    </td>
                </tr>
            `;
            const listMsg = document.getElementById('productsListMessage');
            if (listMsg) listMsg.textContent = 'No matching products.';
            return;
        }

        const rows = filtered.map(product => {
            const id = Number(product.product_id);
            const active = Number(product.is_active) === 1;
            const unit = productEscape(product.product_unit || 'pcs');
            const stockStatus = getProductStockStatus(product);

            return `
                <tr>
                    <td>
                        <div class="fw-bold">
                            ${productEscape(product.product_name || 'Unnamed product')}
                        </div>
                        <small class="text-muted">
                            Reorder at ${productNumber(product.reorder_level)} ${unit}
                        </small>
                    </td>
                    <td>${productEscape(product.sku || '—')}</td>
                    <td>${productEscape(product.category_name || 'Uncategorised')}</td>
                    <td>
                        <div class="product-stock-quantity">
                            <span class="fw-bold">${productNumber(product.quantity)}</span>
                            <small class="text-muted">${unit}</small>
                        </div>
                        ${stockStatus ? `<span class="stock-status-badge ${stockStatus.className}">${stockStatus.label}</span>` : ''}
                    </td>
                    <td>${productMoney(product.cost_price)}</td>
                    <td>${productMoney(product.selling_price)}</td>
                    <td>
                        <span class="badge bg-${active ? 'success' : 'secondary'}">
                            ${active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-light dropdown-toggle border" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Actions
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <button class="dropdown-item stock-action-trigger" type="button" data-action="in" data-id="${id}">
                                    <i class="fa fa-arrow-down text-success me-2"></i> Stock In
                                </button>
                                <button class="dropdown-item stock-action-trigger" type="button" data-action="out" data-id="${id}">
                                    <i class="fa fa-arrow-up text-danger me-2"></i> Stock Out
                                </button>
                                <button class="dropdown-item stock-action-trigger" type="button" data-action="adjust" data-id="${id}">
                                    <i class="fa fa-sliders text-primary me-2"></i> Adjust Stock
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item stock-history-trigger" type="button" data-id="${id}">
                                    <i class="fa fa-history text-info me-2"></i> Movement &amp; Price History
                                </button>
                                <button class="dropdown-item edit-product-btn" type="button" data-id="${id}">
                                    <i class="fa fa-pencil text-muted me-2"></i> Edit Details
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        productTable.innerHTML = rows;
        const listMsg = document.getElementById('productsListMessage');
        if (listMsg) {
            listMsg.textContent = 'Showing ' + filtered.length + ' of ' + productsCache.length + ' products.';
        }
    }

    function loadProducts() {
        if (productTable) {
            productTable.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        Loading products...
                    </td>
                </tr>
            `;
        }

        productRequest(
            { action: 'listProducts', search: '' },
            function (data) {
                productsCache = Array.isArray(data.products) ? data.products : [];
                const openStockTakeBtn = document.getElementById('openStockTakingBtn');
                if (openStockTakeBtn) openStockTakeBtn.disabled = productsCache.length === 0;
                renderProducts();
            }
        );
    }

    function refreshProductPage() {
        loadProductCategories();
        loadProductUnits(loadProductDefaults);
        loadProducts();
    }

    const openAddProductModalBtn = document.getElementById('openAddProductModal');
    if (openAddProductModalBtn) {
        openAddProductModalBtn.addEventListener('click', () => {
            const addProductForm = document.getElementById('addProductForm');
            if (addProductForm) addProductForm.reset();
            productSkuEdited = false;
            
            const catSelect = document.getElementById('productCategorySelect');
            if (catSelect) catSelect.value = '0';

            const unitSelect = document.getElementById('productUnitSelect1');
            if (unitSelect) {
                const pcsOpt = unitSelect.querySelector('option[value="pcs"]');
                if (pcsOpt) {
                    unitSelect.value = productDefaultUnit;
                } else {
                    unitSelect.selectedIndex = 0;
                }
            }

            const reorderInput = document.getElementById('reorderLevelInput');
            if (reorderInput) reorderInput.value = productDefaultReorderLevel;

            suggestProductSku();
            const modalEl = document.getElementById('addProductModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    }

    const productNameInput = document.getElementById('productNameInput');
    const productCategorySelect = document.getElementById('productCategorySelect');
    if (productNameInput) productNameInput.addEventListener('input', () => { if (!productSkuEdited) suggestProductSku(); });
    if (productCategorySelect) productCategorySelect.addEventListener('change', () => { if (!productSkuEdited) suggestProductSku(); });

    const productSkuInput = document.getElementById('productSkuInput');
    if (productSkuInput) {
        productSkuInput.addEventListener('input', function () {
            if (!String(this.value || '').trim()) {
                productSkuEdited = false;
                suggestProductSku();
            } else {
                productSkuEdited = true;
            }
        });
    }

    if (productSearch) {
        productSearch.addEventListener('input', () => {
            window.clearTimeout(productSearchTimer);
            productSearchTimer = window.setTimeout(renderProducts, 180);
        });
    }

    [categoryFilter, statusFilter, productSort].forEach(filterEl => {
        if (filterEl) filterEl.addEventListener('change', renderProducts);
    });

    const clearFiltersBtn = document.getElementById('clearProductFiltersButton');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (productSearch) productSearch.value = '';
            if (categoryFilter) categoryFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (productSort) productSort.value = 'name-asc';
            renderProducts();
            if (productSearch) productSearch.focus();
        });
    }

    function updateStockTakeSummary() {
        const ids = Object.keys(stockTakeCounts);
        const counted = ids.filter(id => stockTakeCounts[id] !== '' && stockTakeCounts[id] != null).length;
        const changed = ids.filter(id => {
            const product = productsCache.find(item => String(item.product_id) === id);
            const physical = Number(stockTakeCounts[id]);
            return product && stockTakeCounts[id] !== '' && Number.isFinite(physical) &&
                Math.abs(physical - (Number(product.quantity) || 0)) >= 0.0001;
        }).length;

        const summaryEl = document.getElementById('stockTakingSummary');
        if (summaryEl) {
            summaryEl.textContent = 'Counted ' + counted + ' of ' + productsCache.length + ' products; ' + changed + ' variance(s) to reconcile.';
        }
        const saveBtn = document.getElementById('saveStockTakeBtn');
        if (saveBtn) saveBtn.disabled = productsCache.length === 0 || counted !== productsCache.length;
    }

    function renderStockTakeRows() {
        const stockTakingSearch = document.getElementById('stockTakingSearch');
        const term = String(stockTakingSearch ? stockTakingSearch.value : '').trim().toLowerCase();
        const visibleProducts = productsCache.filter(product => {
            return [product.product_name, product.sku, product.category_name].join(' ').toLowerCase().includes(term);
        });

        const tbody = document.getElementById('stockTakingTableBody');
        if (!tbody) return;

        if (!visibleProducts.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No products match this search.</td></tr>';
            updateStockTakeSummary();
            return;
        }

        tbody.innerHTML = visibleProducts.map(product => {
            const id = String(product.product_id);
            const systemQty = Number(product.quantity) || 0;
            const value = stockTakeCounts[id] == null ? '' : stockTakeCounts[id];
            return `<tr data-product-id="${Number(product.product_id)}">
                <td><strong>${productEscape(product.product_name)}</strong><small class="d-block text-muted">${productEscape(product.category_name || 'Uncategorized')}</small></td>
                <td>${productEscape(product.sku || '—')}</td>
                <td class="text-end text-nowrap"><span class="system-qty">${productNumber(systemQty)}</span> <small class="text-muted">${productEscape(product.product_unit || 'pcs')}</small></td>
                <td><input type="number" step="any" min="0" required class="form-control form-control-sm physical-count-input" value="${productEscape(value)}" placeholder="Count stock" data-system="${systemQty}" aria-label="Physical count for ${productEscape(product.product_name)}"></td>
                <td><span class="variance-badge badge bg-secondary">${value === '' ? 'Not counted' : 'Balanced (0)'}</span></td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.physical-count-input').forEach(input => {
            if (input.value !== '') {
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        updateStockTakeSummary();
    }

    const openStockTakingBtn = document.getElementById('openStockTakingBtn');
    if (openStockTakingBtn) {
        openStockTakingBtn.addEventListener('click', () => {
            if (!productsCache.length) return;
            stockTakeCounts = {};
            productsCache.forEach(product => { stockTakeCounts[String(product.product_id)] = ''; });
            
            const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
            setVal('stockTakeName', '');
            setVal('stockTakeNotes', '');
            setVal('stockTakingSearch', '');

            renderStockTakeRows();
            const modalEl = document.getElementById('stockTakingModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    }

    const stockTakingSearch = document.getElementById('stockTakingSearch');
    if (stockTakingSearch) stockTakingSearch.addEventListener('input', renderStockTakeRows);

    const stockTakingTableBody = document.getElementById('stockTakingTableBody');
    if (stockTakingTableBody) {
        stockTakingTableBody.addEventListener('input', function (e) {
            if (!e.target.classList.contains('physical-count-input')) return;
            const input = e.target;
            const systemQty = Number(input.getAttribute('data-system')) || 0;
            const tr = input.closest('tr');
            const id = String(tr.getAttribute('data-product-id'));
            const raw = input.value;
            const badge = tr.querySelector('.variance-badge');
            stockTakeCounts[id] = raw;

            if (raw === '') {
                badge.textContent = 'Not counted';
                badge.className = 'variance-badge badge bg-secondary';
            } else if (!Number.isFinite(Number(raw)) || Number(raw) < 0) {
                badge.textContent = 'Invalid';
                badge.className = 'variance-badge badge bg-warning text-dark';
            } else {
                const difference = Number(raw) - systemQty;
                if (Math.abs(difference) < 0.0001) {
                    badge.textContent = 'Balanced (0)';
                    badge.className = 'variance-badge badge bg-success';
                } else if (difference > 0) {
                    badge.textContent = '+' + productNumber(difference) + ' (Addition)';
                    badge.className = 'variance-badge badge bg-primary';
                } else {
                    badge.textContent = productNumber(difference) + ' (Shortage)';
                    badge.className = 'variance-badge badge bg-danger';
                }
            }
            updateStockTakeSummary();
        });
    }

    const stockTakingForm = document.getElementById('stockTakingForm');
    if (stockTakingForm) {
        stockTakingForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const counts = productsCache.map(product => {
                return {
                    product_id: Number(product.product_id),
                    system_quantity: Number(product.quantity) || 0,
                    latest_ledger_id: Number(product.latest_ledger_id) || 0,
                    counted_quantity: stockTakeCounts[String(product.product_id)] === ''
                        ? NaN
                        : Number(stockTakeCounts[String(product.product_id)])
                };
            });
            if (counts.some(row => !Number.isFinite(row.counted_quantity) || row.counted_quantity < 0)) {
                window.alert('Enter a valid physical count for every product.');
                return;
            }

            const saveBtn = document.getElementById('saveStockTakeBtn');
            if (saveBtn) saveBtn.disabled = true;

            const nameVal = document.getElementById('stockTakeName')?.value || '';
            const notesVal = document.getElementById('stockTakeNotes')?.value || '';

            productRequest({
                action: 'saveStockTake',
                counts_json: JSON.stringify(counts),
                stock_take_name: nameVal,
                stock_take_notes: notesVal
            }, function (_, message) {
                const modalEl = document.getElementById('stockTakingModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                window.alert(message);
                loadProducts();
            }, function () {
                updateStockTakeSummary();
            });
        });
    }

    function renderStockTakeHistory(stockTakes) {
        const bodyEl = document.getElementById('stockTakeHistoryBody');
        if (!bodyEl) return;
        if (!stockTakes.length) {
            bodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5">No stock takes recorded yet.</td></tr>';
            return;
        }
        bodyEl.innerHTML = stockTakes.map(take => {
            return `<tr>
                <td class="text-nowrap">${productEscape(take.created_at)}</td>
                <td><strong>${productEscape(take.name)}</strong><small class="d-block text-muted">${productEscape(take.notes || '')}</small></td>
                <td class="text-end">${productNumber(take.total_products)}</td>
                <td class="text-end">${productNumber(take.changed_products)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary view-stock-take" data-id="${Number(take.stock_take_id)}">View counts</button></td>
            </tr>`;
        }).join('');
    }

    const openStockTakeHistoryBtn = document.getElementById('openStockTakeHistoryBtn');
    if (openStockTakeHistoryBtn) {
        openStockTakeHistoryBtn.addEventListener('click', () => {
            const bodyEl = document.getElementById('stockTakeHistoryBody');
            if (bodyEl) bodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading stock takes…</td></tr>';
            
            const modalEl = document.getElementById('stockTakeHistoryModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }

            productRequest({ action: 'listStockTakes' }, data => {
                renderStockTakeHistory(Array.isArray(data.stock_takes) ? data.stock_takes : []);
            });
        });
    }

    const stockTakeHistoryBody = document.getElementById('stockTakeHistoryBody');
    if (stockTakeHistoryBody) {
        stockTakeHistoryBody.addEventListener('click', function (e) {
            if (!e.target.classList.contains('view-stock-take')) return;
            const stockTakeId = Number(e.target.getAttribute('data-id'));
            
            const historyModalEl = document.getElementById('stockTakeHistoryModal');
            const detailsModalEl = document.getElementById('stockTakeDetailsModal');

            if (historyModalEl) {
                historyModalEl.addEventListener('hidden.bs.modal', function handler() {
                    historyModalEl.removeEventListener('hidden.bs.modal', handler);
                    
                    const detailsBody = document.getElementById('stockTakeDetailsBody');
                    if (detailsBody) detailsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading counted quantities…</td></tr>';
                    
                    if (detailsModalEl) {
                        const modal = bootstrap.Modal.getInstance(detailsModalEl) || new bootstrap.Modal(detailsModalEl);
                        modal.show();
                    }

                    productRequest({ action: 'loadStockTakeDetails', stock_take_id: stockTakeId }, data => {
                        const take = data.stock_take || {};
                        const lines = Array.isArray(data.lines) ? data.lines : [];
                        
                        const titleEl = document.getElementById('stockTakeDetailsTitle');
                        if (titleEl) titleEl.textContent = (take.name || 'Stock Take') + ' — ' + (take.created_at || '');

                        const notesEl = document.getElementById('stockTakeDetailsNotes');
                        if (notesEl) notesEl.textContent = take.notes || (take.changed_products + ' of ' + take.total_products + ' products adjusted');

                        if (!lines.length) {
                            if (detailsBody) detailsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No count lines are available for this session.</td></tr>';
                            return;
                        }

                        if (detailsBody) {
                            detailsBody.innerHTML = lines.map(line => {
                                const variance = Number(line.variance) || 0;
                                const label = Math.abs(variance) < 0.0001 ? 'Balanced' : (variance > 0 ? 'Surplus' : 'Shortage');
                                const badge = label === 'Balanced' ? 'success' : (label === 'Surplus' ? 'primary' : 'danger');
                                return `<tr>
                                    <td>${productEscape(line.product_name)}</td>
                                    <td>${productEscape(line.sku || '—')}</td>
                                    <td class="text-end">${productNumber(line.system_quantity)} ${productEscape(line.product_unit || '')}</td>
                                    <td class="text-end">${productNumber(line.counted_quantity)} ${productEscape(line.product_unit || '')}</td>
                                    <td class="text-end">${variance > 0 ? '+' : ''}${productNumber(variance)} ${productEscape(line.product_unit || '')}</td>
                                    <td><span class="badge bg-${badge}">${label}</span></td>
                                </tr>`;
                            }).join('');
                        }
                    });
                });

                const modalInst = bootstrap.Modal.getInstance(historyModalEl);
                if (modalInst) modalInst.hide();
            }
        });
    }

    const refreshProductsBtn = document.getElementById('refreshProductsButton');
    if (refreshProductsBtn) refreshProductsBtn.addEventListener('click', refreshProductPage);

    const addProductForm = document.getElementById('addProductForm');
    if (addProductForm) {
        addProductForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const payload = {
                action: 'saveProduct',
                product_id: 0,
            };
            formData.forEach((value, key) => { payload[key] = value; });

            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            productRequest(payload, (_, message) => {
                const modalEl = document.getElementById('addProductModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                this.reset();
                alert(message);
                loadProducts();
            });

            window.setTimeout(() => {
                if (submitBtn) submitBtn.disabled = false;
            }, 1000);
        });
    }

    if (productTable) {
        productTable.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-product-btn');
            if (editBtn) {
                const id = Number(editBtn.getAttribute('data-id'));
                const product = productsCache.find(item => Number(item.product_id) === id);
                if (!product) {
                    alert('Product details could not be found. Refresh the list.');
                    return;
                }

                const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                setVal('editProductIdInput', product.product_id);
                setVal('editProductNameInput', product.product_name || '');
                setVal('editProductSkuInput', product.sku || '');
                setVal('editProductCategorySelect', String(product.category_id || 0));

                const currentUnit = String(product.product_unit || 'pcs');
                const editUnit = document.getElementById('editProductUnitSelect');
                if (editUnit) {
                    let found = false;
                    for (let opt of editUnit.options) {
                        if (opt.value === currentUnit) { found = true; break; }
                    }
                    if (!found) {
                        const newOpt = document.createElement('option');
                        newOpt.value = currentUnit;
                        newOpt.textContent = currentUnit + ' (Existing unit)';
                        editUnit.appendChild(newOpt);
                    }
                    editUnit.value = currentUnit;
                }

                setVal('editCostPriceInput', product.cost_price || 0);
                setVal('editSellingPriceInput', product.selling_price || 0);
                setVal('editReorderLevelInput', product.reorder_level || 0);
                setVal('editStatusSelect', String(product.is_active));

                const modalEl = document.getElementById('editProductModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }

            const stockActionBtn = e.target.closest('.stock-action-trigger');
            if (stockActionBtn) {
                const action = stockActionBtn.getAttribute('data-action');
                const id = Number(stockActionBtn.getAttribute('data-id'));
                const product = productsCache.find(item => Number(item.product_id) === id);

                if (!product) {
                    alert('Product details could not be found.');
                    return;
                }

                const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                setVal('stockTransactionProductId', product.product_id);
                setVal('stockTransactionType', action);
                
                const nameEl = document.getElementById('stockModalProductName');
                if (nameEl) nameEl.textContent = product.product_name;

                const qtyEl = document.getElementById('stockModalCurrentQuantity');
                if (qtyEl) qtyEl.textContent = productNumber(product.quantity) + ' ' + (product.product_unit || 'pcs');

                setVal('stockQuantityInput', '');
                setVal('stockNotesInput', '');
                setVal('stockUnitPriceInput', action === 'in' ? product.cost_price : product.selling_price);
                setVal('stockPaidInput', '0');
                setVal('adjustCostPriceInput', '');
                setVal('adjustSellingPriceInput', '');

                const previewEl = document.getElementById('stockHistoryPreview');
                if (previewEl) previewEl.textContent = '';

                const isAdjustment = action === 'adjust';
                const partnerGroup = document.getElementById('stockPartnerGroup');
                const paidGroup = document.getElementById('stockPaidGroup');
                const priceGroup = document.getElementById('stockPriceGroup');
                if (partnerGroup) partnerGroup.style.display = isAdjustment ? 'none' : 'block';
                if (paidGroup) paidGroup.style.display = isAdjustment ? 'none' : 'block';
                if (priceGroup) priceGroup.style.display = isAdjustment ? 'none' : 'block';

                const adjPrices = document.getElementById('stockAdjustmentPrices');
                if (adjPrices) adjPrices.classList.toggle('d-none', !isAdjustment);

                const partnerSelect = document.getElementById('stockPartnerSelect');
                if (partnerSelect) {
                    partnerSelect.required = !isAdjustment;
                    partnerSelect.innerHTML = '<option value="">Loading partners…</option>';
                }

                const unitPriceInput = document.getElementById('stockUnitPriceInput');
                if (unitPriceInput) unitPriceInput.required = !isAdjustment;

                const qtyInput = document.getElementById('stockQuantityInput');
                if (qtyInput) qtyInput.setAttribute('min', isAdjustment ? '0' : '0.001');

                if (!isAdjustment) {
                    App.requestApi('apiPartners.php', 'list').then(response => {
                        const records = Array.isArray(response.data?.partners) ? response.data.partners : [];
                        const role = action === 'in' ? ['supplier', 'both'] : ['customer', 'both'];
                        const eligible = records.filter(partner => partner.status === 'active' && role.includes(partner.role));
                        let options = '<option value="">Select ' + (action === 'in' ? 'supplier' : 'customer') + '</option>';
                        eligible.forEach(partner => { options += '<option value="' + Number(partner.partner_id) + '">' + productEscape(partner.name) + '</option>'; });
                        if (partnerSelect) partnerSelect.innerHTML = options;
                        const defaultKey = action === 'in' ? 'general_supplier' : 'walk_in_customer';
                        const defaultPartner = eligible.find(partner => partner.system_key === defaultKey);
                        if (defaultPartner && partnerSelect) partnerSelect.value = String(defaultPartner.partner_id);
                        if (!eligible.length && previewEl) previewEl.textContent = 'Add an active ' + (action === 'in' ? 'supplier' : 'customer') + ' in Partners first.';
                    }).catch(() => { if (partnerSelect) partnerSelect.innerHTML = '<option value="">Could not load partners</option>'; });
                }

                    const titles = {
                    in: 'Stock In (Add Inventory)',
                    out: 'Stock Out (Remove Inventory)',
                    adjust: 'Direct Stock Adjustment'
                };
                const modalTitleEl = document.getElementById('stockModalTitle');
                if (modalTitleEl) modalTitleEl.textContent = titles[action] || 'Stock Action';

                const qtyLabelEl = document.getElementById('stockQuantityLabel');
                if (qtyLabelEl) {
                    qtyLabelEl.innerHTML = action === 'adjust'
                        ? 'New Absolute Quantity <span class="text-danger">*</span>'
                        : 'Quantity to ' + (action === 'in' ? 'Add' : 'Remove') + ' <span class="text-danger">*</span>';
                }

                const partnerLabelEl = document.getElementById('stockPartnerLabel');
                if (partnerLabelEl) partnerLabelEl.textContent = action === 'in' ? 'Supplier *' : 'Customer *';

                const unitPriceLabelEl = document.getElementById('stockUnitPriceLabel');
                if (unitPriceLabelEl) unitPriceLabelEl.textContent = action === 'in' ? 'Unit Cost Price (₦) *' : 'Unit Selling Price (₦) *';

                const paidLabelEl = document.getElementById('stockPaidLabel');
                if (paidLabelEl) paidLabelEl.textContent = action === 'in' ? 'Amount Paid (₦)' : 'Amount Received (₦)';

                const stockModalEl = document.getElementById('stockTransactionModal');
                if (stockModalEl) {
                    const modal = bootstrap.Modal.getInstance(stockModalEl) || new bootstrap.Modal(stockModalEl);
                    modal.show();
                }
            }

            const historyBtn = e.target.closest('.stock-history-trigger');
            if (historyBtn) {
                const id = Number(historyBtn.getAttribute('data-id'));
                const product = productsCache.find(item => Number(item.product_id) === id);
                if (!product) return;

                const historyTitle = document.getElementById('stockHistoryTitle');
                if (historyTitle) historyTitle.textContent = product.product_name + ' — Movement & Price History';

                const historyBody = document.getElementById('stockHistoryTableBody');
                if (historyBody) historyBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading history…</td></tr>';

                const historyModalEl = document.getElementById('stockHistoryModal');
                if (historyModalEl) {
                    const modal = bootstrap.Modal.getInstance(historyModalEl) || new bootstrap.Modal(historyModalEl);
                    modal.show();
                }

                productRequest({ action: 'loadStockHistory', product_id: id }, data => {
                    const rows = Array.isArray(data.movements) ? data.movements : [];
                    if (!rows.length) {
                        if (historyBody) historyBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr>';
                        return;
                    }
                    if (historyBody) {
                        historyBody.innerHTML = rows.map(m => {
                            const esc = v => productEscape(v == null || v === '' ? '—' : String(v));
                            const price = v => v == null ? '—' : productMoney(v);
                            return `<tr>
                                <td>${esc(m.created_at)}</td>
                                <td>${esc(m.reference_type.replace('_', ' '))}</td>
                                <td>${esc(m.partner_name)}</td>
                                <td class="text-end">${productNumber(m.qty_in)}</td>
                                <td class="text-end">${productNumber(m.qty_out)}</td>
                                <td class="text-end">${productNumber(m.balance_after)}</td>
                                <td class="text-end">${price(m.previous_cost_price)} → ${price(m.unit_cost)}</td>
                                <td class="text-end">${price(m.previous_selling_price)} → ${price(m.unit_selling_price)}</td>
                                <td class="text-end">${price(m.total_amount)}</td>
                                <td>${esc(m.note)}</td>
                            </tr>`;
                        }).join('');
                    }
                });
            }
        });
    }

    const editProductForm = document.getElementById('editProductForm');
    if (editProductForm) {
        editProductForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const payload = {
                action: 'saveProduct',
            };
            formData.forEach((value, key) => { payload[key] = value; });

            payload.is_active = payload.status;
            delete payload.status;

            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            productRequest(payload, (_, message) => {
                const modalEl = document.getElementById('editProductModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                alert(message);
                loadProducts();
            });

            window.setTimeout(() => {
                if (submitBtn) submitBtn.disabled = false;
            }, 1000);
        });
    }

    const stockQuantityInput = document.getElementById('stockQuantityInput');
    const stockUnitPriceInput = document.getElementById('stockUnitPriceInput');
    const updatePaidAmount = () => {
        const qty = Number(document.getElementById('stockQuantityInput')?.value) || 0;
        const price = Number(document.getElementById('stockUnitPriceInput')?.value) || 0;
        const paidInput = document.getElementById('stockPaidInput');
        if (paidInput) paidInput.value = (qty * price).toFixed(2);
    };
    if (stockQuantityInput) stockQuantityInput.addEventListener('input', updatePaidAmount);
    if (stockUnitPriceInput) stockUnitPriceInput.addEventListener('input', updatePaidAmount);

    const stockTransactionForm = document.getElementById('stockTransactionForm');
    if (stockTransactionForm) {
        stockTransactionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const payload = {
                action: 'saveStockTransaction',
            };
            formData.forEach((value, key) => { payload[key] = value; });

            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            productRequest(payload, (_, message) => {
                const modalEl = document.getElementById('stockTransactionModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                alert(message);
                loadProducts();
            });

            window.setTimeout(() => {
                if (submitBtn) submitBtn.disabled = false;
            }, 1000);
        });
    }

    refreshProductPage();
});
