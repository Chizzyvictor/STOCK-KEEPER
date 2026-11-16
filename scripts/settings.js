// ============================
// Stock Keeper Settings Module
// ============================
document.addEventListener("DOMContentLoaded", () => {
    'use strict';

    const settingsPage = document.getElementById('settingsPage');
    if (!settingsPage) return;

    const app = window.AppCoreInstance;

    const navigation = document.getElementById('settingsNavigation');
    const mobileToggle = document.getElementById('settingsMobileToggle');

    const MOBILE_BREAKPOINT = 900;
    const DEFAULT_TAB = 'business-profile';

    // ============================
    // 1. SETTINGS NAVIGATION & TABS
    // ============================
    function toggleNavigationGroup(button, forceOpen) {
        const group = button.getAttribute('data-settings-group');
        if (!group) return;

        const items = settingsPage.querySelectorAll(`[data-settings-items="${group}"]`);
        if (!items.length) return;

        const isOpen = typeof forceOpen === 'boolean'
            ? forceOpen
            : !button.classList.contains('is-open');

        button.classList.toggle('is-open', isOpen);
        button.setAttribute('aria-expanded', String(isOpen));

        items.forEach(item => {
            if (isOpen) {
                item.hidden = false;
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
                item.hidden = true;
            }
        });
    }

    function activateSettingsTab(tab, options = {}) {
        const updateUrl = options.updateUrl !== false;
        const closeMobileMenu = options.closeMobileMenu !== false;

        const link = settingsPage.querySelector(`.settings-nav-link[data-settings-tab="${tab}"]`);
        const panel = settingsPage.querySelector(`.settings-panel[data-settings-panel="${tab}"]`);

        if (!link || !panel) {
            console.warn('[Settings] Invalid tab:', tab);
            return false;
        }

        settingsPage.querySelectorAll('.settings-nav-link').forEach(el => {
            el.classList.remove('is-active');
            el.removeAttribute('aria-current');
        });

        link.classList.add('is-active');
        link.setAttribute('aria-current', 'page');

        settingsPage.querySelectorAll('.settings-panel').forEach(el => {
            el.classList.remove('is-active');
            el.hidden = true;
        });

        panel.classList.add('is-active');
        panel.hidden = false;

        const itemsContainer = link.closest('.settings-nav-items');
        if (itemsContainer) {
            const group = itemsContainer.getAttribute('data-settings-items');
            if (group) {
                const groupButton = settingsPage.querySelector(`.settings-nav-group-toggle[data-settings-group="${group}"]`);
                if (groupButton) {
                    groupButton.classList.add('is-open');
                    groupButton.setAttribute('aria-expanded', 'true');
                }
                itemsContainer.hidden = false;
                itemsContainer.style.display = 'block';
            }
        }

        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
        }

        if (closeMobileMenu && window.innerWidth <= MOBILE_BREAKPOINT && navigation) {
            navigation.classList.remove('is-visible');
            if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
        }

        return true;
    }

    settingsPage.addEventListener('click', (e) => {
        const groupToggle = e.target.closest('.settings-nav-group-toggle');
        if (groupToggle) {
            toggleNavigationGroup(groupToggle);
            return;
        }

        const navLink = e.target.closest('.settings-nav-link');
        if (navLink) {
            e.preventDefault();
            const tab = navLink.getAttribute('data-settings-tab');
            activateSettingsTab(tab);
        }
    });

    if (mobileToggle && navigation) {
        mobileToggle.addEventListener('click', () => {
            const isVisible = navigation.classList.contains('is-visible');
            navigation.classList.toggle('is-visible', !isVisible);
            mobileToggle.setAttribute('aria-expanded', String(!isVisible));
        });
    }

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const requestedTab = params.get('tab');
        activateSettingsTab(requestedTab || DEFAULT_TAB, { updateUrl: false, closeMobileMenu: false });
    });

    const initialParams = new URLSearchParams(window.location.search);
    const initialTab = initialParams.get('tab');
    if (!activateSettingsTab(initialTab || DEFAULT_TAB, { updateUrl: false, closeMobileMenu: false })) {
        activateSettingsTab(DEFAULT_TAB, { updateUrl: false, closeMobileMenu: false });
    }

    console.log('[Settings] Premium navigation initialized.');

    // Helper fetch wrapper for general settings endpoints
    async function requestApi(url, action, data = {}) {
        return app.requestApi(url, action, data);
    }

        // ============================
    // 2. PRODUCT CATEGORIES PANEL
    // ============================
    const categoriesPanel = document.querySelector('[data-settings-panel="product-categories"]');
    if (categoriesPanel) {
        const apiUrl = 'apiCategories.php';
        let categories = [];

        const escapeHtml = value => app.escapeHtml(value);

        function showCategoryMessage(message, isError = false) {
            const msgEl = document.getElementById('categoriesListMessage');
            if (!msgEl) return;
            msgEl.textContent = message;
            msgEl.style.background = isError ? '#fff0f0' : '#f0f7ff';
            msgEl.style.color = isError ? '#a61b1b' : '#174a7e';
            msgEl.hidden = false;
        }

        function renderCategories() {
            const search = String(document.getElementById('categorySearchInput')?.value || '').trim().toLowerCase();
            const status = document.getElementById('categoryStatusFilter')?.value || 'all';

            const filtered = categories.filter(category => {
                const name = String(category.category_name || '').toLowerCase();
                const description = String(category.category_description || '').toLowerCase();
                const matchesSearch = name.includes(search) || description.includes(search);
                const matchesStatus = status === 'all' || String(category.is_active) === status;
                return matchesSearch && matchesStatus;
            });

            const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setTxt('categoryTotalCount', categories.length);
            setTxt('categoryActiveCount', categories.filter(c => Number(c.is_active) === 1).length);
            setTxt('categoryInactiveCount', categories.filter(c => Number(c.is_active) === 0).length);
            setTxt('categoriesResultCount', filtered.length + (filtered.length === 1 ? ' category' : ' categories'));

            const tbody = document.getElementById('categoriesTableBody');
            if (!tbody) return;

            if (!filtered.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4">
                            <div class="settings-category-loading">
                                <i class="fa fa-folder-open me-2"></i>
                                No matching categories found.
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filtered.map(category => {
                const id = Number(category.category_id);
                const active = Number(category.is_active) === 1;
                return `
                    <tr>
                        <td><strong>${escapeHtml(category.category_name)}</strong></td>
                        <td>${escapeHtml(category.category_description || '—')}</td>
                        <td>
                            <span class="settings-category-status ${active ? 'is-active' : 'is-inactive'}">
                                ${active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <div class="settings-category-actions">
                                <button type="button" class="settings-category-action js-edit-category" data-id="${id}" aria-label="Edit category ${escapeHtml(category.category_name)}" title="Edit category">
                                    <i class="fa fa-pencil"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function loadCategories(successMessage = '') {
            const tbody = document.getElementById('categoriesTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4">
                            <div class="settings-category-loading">
                                <i class="fa fa-spinner fa-spin me-2"></i>
                                Loading categories...
                            </div>
                        </td>
                    </tr>
                `;
            }

            try {
                const res = await requestApi(apiUrl, 'list');
                if (res.status !== 'success') {
                    showCategoryMessage(res.message || 'Could not load categories.', true);
                    return;
                }
                categories = Array.isArray(res.data?.categories) ? res.data.categories : [];
                renderCategories();
                if (successMessage) showCategoryMessage(successMessage);
            } catch (xhr) {
                const res = xhr.responseJSON || {};
                showCategoryMessage(res.message || 'Unable to load categories. Check your session and database.', true);
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="4"><div class="settings-category-loading">Categories could not be loaded.</div></td></tr>`;
                }
            }
        }

        const catSearch = document.getElementById('categorySearchInput');
        const catFilter = document.getElementById('categoryStatusFilter');
        if (catSearch) catSearch.addEventListener('input', renderCategories);
        if (catFilter) catFilter.addEventListener('change', renderCategories);

        const refreshCatBtn = document.getElementById('refreshCategoriesButton');
        if (refreshCatBtn) refreshCatBtn.addEventListener('click', () => loadCategories());

        const addCatModal = document.getElementById('addCategoryModal');
        if (addCatModal) {
            addCatModal.addEventListener('hidden.bs.modal', () => {
                const form = document.getElementById('addCategoryForm');
                if (form) form.reset();
            });
        }

        const addCatForm = document.getElementById('addCategoryForm');
        if (addCatForm) {
            addCatForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const submitBtn = this.querySelector('[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                try {
                    const res = await requestApi(apiUrl, 'create', {
                        category_name: document.getElementById('categoryNameInput')?.value || '',
                        category_description: document.getElementById('categoryDescriptionInput')?.value || ''
                    });
                    if (res.status !== 'success') {
                        showCategoryMessage(res.message || 'Could not add category.', true);
                        return;
                    }
                    const modalInst = bootstrap.Modal.getInstance(addCatModal) || new bootstrap.Modal(addCatModal);
                    modalInst.hide();
                    this.reset();
                    loadCategories(res.message || 'Category added successfully.');
                } catch (xhr) {
                    const res = xhr.responseJSON || {};
                    showCategoryMessage(res.message || 'Could not add category.', true);
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        categoriesPanel.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.js-edit-category');
            if (editBtn) {
                const id = Number(editBtn.getAttribute('data-id'));
                const category = categories.find(item => Number(item.category_id) === id);
                if (!category) {
                    showCategoryMessage('Category not found. Refresh and try again.', true);
                    return;
                }

                const setVal = (elemId, val) => { const el = document.getElementById(elemId); if (el) el.value = val; };
                setVal('editCategoryIdInput', category.category_id);
                setVal('editCategoryNameInput', category.category_name);
                setVal('editCategoryDescriptionInput', category.category_description || '');
                setVal('editCategoryStatusSelect', String(Number(category.is_active)));

                const editModalEl = document.getElementById('editCategoryModal');
                if (editModalEl) {
                    const modal = bootstrap.Modal.getInstance(editModalEl) || new bootstrap.Modal(editModalEl);
                    modal.show();
                }
            }
        });

        const editCatModal = document.getElementById('editCategoryModal');
        if (editCatModal) {
            editCatModal.addEventListener('hidden.bs.modal', () => {
                const form = document.getElementById('editCategoryForm');
                if (form) form.reset();
            });
        }

        const editCatForm = document.getElementById('editCategoryForm');
        if (editCatForm) {
            editCatForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const submitBtn = this.querySelector('[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                try {
                    const res = await requestApi(apiUrl, 'update', {
                        category_id: document.getElementById('editCategoryIdInput')?.value || '',
                        category_name: document.getElementById('editCategoryNameInput')?.value || '',
                        category_description: document.getElementById('editCategoryDescriptionInput')?.value || '',
                        status: document.getElementById('editCategoryStatusSelect')?.value || '1'
                    });
                    if (res.status !== 'success') {
                        showCategoryMessage(res.message || 'Could not update category.', true);
                        return;
                    }
                    const modalInst = bootstrap.Modal.getInstance(editCatModal) || new bootstrap.Modal(editCatModal);
                    modalInst.hide();
                    loadCategories(res.message || 'Category updated successfully.');
                } catch (xhr) {
                    const res = xhr.responseJSON || {};
                    showCategoryMessage(res.message || 'Could not update category.', true);
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        loadCategories();
    }

    // ============================
    // 3. PRODUCT UNITS PANEL
    // ============================
    const unitsPanel = document.querySelector('[data-settings-panel="product-units"]');
    if (unitsPanel) {
        const apiUrl = 'apiUnits.php';
        let units = [];
        let isLoading = false;

        const table = document.getElementById('unitsTableBody');
        const messageEl = document.getElementById('unitsListMessage');

        const escapeHtml = value => app.escapeHtml(value);

        function showUnitMessage(message, type) {
            if (!messageEl) return;
            messageEl.className = 'alert alert-' + (type || 'info');
            messageEl.textContent = message;
            messageEl.hidden = false;
        }

        function hideUnitMessage() {
            if (messageEl) {
                messageEl.hidden = true;
                messageEl.textContent = '';
            }
        }

        async function requestUnitApi(data) {
            return app.requestApi(apiUrl, data.action, data);
        }

            function renderUnits() {
            const search = String(document.getElementById('unitSearchInput')?.value || '').trim().toLowerCase();
            const status = String(document.getElementById('unitStatusFilter')?.value || 'all');

            const filtered = units.filter(unit => {
                const searchableText = [unit.unit_name, unit.unit_symbol, unit.description].join(' ').toLowerCase();
                const matchesSearch = searchableText.includes(search);
                const matchesStatus = status === 'all' || String(unit.is_active) === status;
                return matchesSearch && matchesStatus;
            });

            const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setTxt('unitTotalCount', units.length);
            setTxt('unitActiveCount', units.filter(u => Number(u.is_active) === 1).length);
            setTxt('unitInactiveCount', units.filter(u => Number(u.is_active) === 0).length);
            setTxt('unitsResultCount', 'Showing ' + filtered.length + ' of ' + units.length + ' units');

            if (!table) return;

            if (!filtered.length) {
                const emptyMessage = units.length ? 'No units match your search or filter.' : 'No product units yet. Click Add Unit to create one.';
                table.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">${escapeHtml(emptyMessage)}</td></tr>`;
                return;
            }

            table.innerHTML = filtered.map(unit => {
                const active = Number(unit.is_active) === 1;
                return `
                    <tr>
                        <td><span class="fw-bold">${escapeHtml(unit.unit_name)}</span></td>
                        <td>${escapeHtml(unit.unit_symbol || '—')}</td>
                        <td>${escapeHtml(unit.description || '—')}</td>
                        <td><span class="badge bg-${active ? 'success' : 'secondary'}">${active ? 'Active' : 'Inactive'}</span></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary js-edit-unit" data-unit-id="${Number(unit.unit_id)}">
                                <i class="fa fa-pencil me-1"></i> Edit
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function loadUnits() {
            if (isLoading) return;
            isLoading = true;

            if (table) {
                table.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading units...</td></tr>`;
            }

            try {
                const res = await requestUnitApi({ action: 'list' });
                units = res.data?.units || [];
                hideUnitMessage();
                renderUnits();
            } catch (xhr) {
                if (table) table.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Could not load product units.</td></tr>`;
                showUnitMessage(xhr.message || 'Something went wrong.', 'danger');
            } finally {
                isLoading = false;
            }
        }

        const addUnitForm = document.getElementById('addUnitForm');
        if (addUnitForm) {
            addUnitForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const submitBtn = this.querySelector('[type="submit"]');
                const formMessage = document.getElementById('addUnitFormMessage');
                if (submitBtn?.disabled) return;

                if (formMessage) {
                    formMessage.hidden = true;
                    formMessage.textContent = '';
                }
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';

                try {
                    const res = await requestUnitApi({
                        action: 'create',
                        unit_name: String(document.getElementById('unitNameInput')?.value || '').trim(),
                        unit_symbol: String(document.getElementById('unitSymbolInput')?.value || '').trim(),
                        description: String(document.getElementById('unitDescriptionInput')?.value || '').trim()
                    });

                    const addModalEl = document.getElementById('addUnitModal');
                    const modalInst = bootstrap.Modal.getInstance(addModalEl) || new bootstrap.Modal(addModalEl);
                    modalInst.hide();
                    this.reset();
                    loadUnits();
                    showUnitMessage(res.message || 'Product unit created successfully.', 'success');
                } catch (xhr) {
                    if (formMessage) {
                        formMessage.className = 'alert alert-danger';
                        formMessage.textContent = xhr.message || 'Unable to save the unit. Please try again.';
                        formMessage.hidden = false;
                    }
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa fa-save me-1"></i> Save Unit';
                }
            });
        }

        unitsPanel.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.js-edit-unit');
            if (editBtn) {
                const unitId = Number(editBtn.getAttribute('data-unit-id'));
                const unit = units.find(item => Number(item.unit_id) === unitId);
                if (!unit) {
                    showUnitMessage('That unit could not be found. Refresh and try again.', 'warning');
                    return;
                }

                const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                setVal('editUnitIdInput', unit.unit_id);
                setVal('editUnitNameInput', unit.unit_name || '');
                setVal('editUnitSymbolInput', unit.unit_symbol || '');
                setVal('editUnitDescriptionInput', unit.description || '');
                setVal('editUnitStatusSelect', String(unit.is_active));

                const editModalEl = document.getElementById('editUnitModal');
                if (editModalEl) {
                    const modal = bootstrap.Modal.getInstance(editModalEl) || new bootstrap.Modal(editModalEl);
                    modal.show();
                }
            }
        });

        const editUnitForm = document.getElementById('editUnitForm');
        if (editUnitForm) {
            editUnitForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const submitBtn = this.querySelector('[type="submit"]');
                if (submitBtn?.disabled) return;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';

                try {
                    const res = await requestUnitApi({
                        action: 'update',
                        unit_id: document.getElementById('editUnitIdInput')?.value || '',
                        unit_name: document.getElementById('editUnitNameInput')?.value.trim() || '',
                        unit_symbol: document.getElementById('editUnitSymbolInput')?.value.trim() || '',
                        description: document.getElementById('editUnitDescriptionInput')?.value.trim() || '',
                        status: document.getElementById('editUnitStatusSelect')?.value || '1'
                    });

                    const editModalEl = document.getElementById('editUnitModal');
                    const modalInst = bootstrap.Modal.getInstance(editModalEl) || new bootstrap.Modal(editModalEl);
                    modalInst.hide();
                    loadUnits();
                    showUnitMessage(res.message || 'Product unit updated successfully.', 'success');
                } catch (xhr) {
                    showUnitMessage(xhr.message || 'Update failed.', 'danger');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa fa-save me-1"></i> Update Unit';
                }
            });
        }

        const unitSearch = document.getElementById('unitSearchInput');
        const unitFilter = document.getElementById('unitStatusFilter');
        if (unitSearch) unitSearch.addEventListener('input', renderUnits);
        if (unitFilter) unitFilter.addEventListener('change', renderUnits);

        const refreshUnitsBtn = document.getElementById('refreshUnitsButton');
        if (refreshUnitsBtn) {
            refreshUnitsBtn.addEventListener('click', () => {
                hideUnitMessage();
                loadUnits();
            });
        }

        ['addUnitModal', 'editUnitModal'].forEach(modalId => {
            const mEl = document.getElementById(modalId);
            if (mEl) {
                mEl.addEventListener('hidden.bs.modal', function () {
                    const form = this.querySelector('form');
                    if (form) form.reset();
                });
            }
        });

        loadUnits();
    }

    // ============================
    // 4. GENERAL BUSINESS & PROFILE SETTINGS
    // ============================
    const settingsUrl = 'apiSettings.php';

    function notify(message, isError) {
        const feedback = document.getElementById('settingsFeedback');
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.toggle('is-error', Boolean(isError));
        feedback.hidden = false;
        window.clearTimeout(notify.timer);
        notify.timer = window.setTimeout(() => { feedback.hidden = true; }, 5000);
    }

    async function sendRequest(action, data = {}) {
        return app.requestApi(settingsUrl, action, data);
    }

    // CSV reader that handles quoted commas, escaped quotes, and quoted newlines.
    function parseContactCsv(text) {
        const rows = [];
        let row = [], cell = '', quoted = false;
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            if (quoted && char === '"' && text[i + 1] === '"') { cell += '"'; i++; }
            else if (char === '"') quoted = !quoted;
            else if (!quoted && char === ',') { row.push(cell); cell = ''; }
            else if (!quoted && (char === '\n' || char === '\r')) {
                row.push(cell); rows.push(row); row = []; cell = '';
                if (char === '\r' && text[i + 1] === '\n') i++;
            } else cell += char;
        }
        if (quoted) throw new Error('The CSV contains an unclosed quoted field.');
        if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
        while (rows.length && rows[0].every(value => value.trim() === '')) rows.shift();
        if (!rows.length) throw new Error('The CSV is empty.');
        const headers = rows.shift().map((value, index) => value.replace(/^\uFEFF/, '').trim().toLowerCase());
        if (!headers.includes('name')) throw new Error('The CSV needs a name column.');
        const allowed = ['name', 'phone', 'email', 'address', 'role', 'notes'];
        const unknown = headers.filter(header => header && !allowed.includes(header));
        if (unknown.length) throw new Error('Unsupported CSV column: ' + unknown.join(', ') + '. Use name, phone, email, address, role, and notes.');
        if (new Set(headers).size !== headers.length) throw new Error('The CSV has duplicate column names.');
        const contacts = rows.filter(values => values.some(value => value.trim() !== '')).map(values => {
            const contact = {};
            headers.forEach((header, index) => { if (header) contact[header] = values[index] || ''; });
            return contact;
        });
        if (!contacts.length) throw new Error('The CSV has no contact rows.');
        if (contacts.length > 5000) throw new Error('A contact file can contain up to 5,000 contacts.');
        return contacts;
    }

    function parseVCard(text) {
        const source = text.replace(/^\uFEFF/, '').replace(/=\r?\n/g, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const unfolded = [];
        source.split('\n').forEach(line => {
            if (/^[ \t]/.test(line) && unfolded.length) unfolded[unfolded.length - 1] += line.slice(1);
            else unfolded.push(line);
        });
        const unescape = value => value.replace(/\\[nN]/g, '\n').replace(/\\([\\,;:])/g, '$1');
        const cards = [];
        let properties = null;
        unfolded.forEach(line => {
            if (/^BEGIN:VCARD\s*$/i.test(line.trim())) { properties = []; return; }
            if (/^END:VCARD\s*$/i.test(line.trim())) { if (properties) cards.push(properties); properties = null; return; }
            if (!properties) return;
            const colon = line.indexOf(':');
            if (colon < 0) return;
            const left = line.slice(0, colon);
            const rawValue = line.slice(colon + 1);
            const pieces = left.split(';');
            const key = pieces[0].split('.').pop().toUpperCase();
            const quotedPrintable = pieces.some(piece => /^ENCODING=QUOTED-PRINTABLE$/i.test(piece));
            let value = rawValue;
            if (quotedPrintable) {
                const bytes = [];
                for (let i = 0; i < value.length; i++) {
                    if (value[i] === '=' && /^[0-9a-f]{2}$/i.test(value.slice(i + 1, i + 3))) { bytes.push(parseInt(value.slice(i + 1, i + 3), 16)); i += 2; }
                    else bytes.push(value.charCodeAt(i) & 255);
                }
                value = new TextDecoder().decode(new Uint8Array(bytes));
            }
            properties.push({key, value});
        });
        if (!cards.length) throw new Error('The selected file does not contain any vCard contacts.');
        const first = (card, key) => unescape((card.find(property => property.key === key)?.value || '').trim());
        const all = (card, key) => card.filter(property => property.key === key).map(property => unescape(property.value.trim())).filter(Boolean);
        const splitAddress = value => {
            const fields = []; let field = '', escaped = false;
            for (const char of value) {
                if (escaped) { field += '\\' + char; escaped = false; }
                else if (char === '\\') escaped = true;
                else if (char === ';') { fields.push(field); field = ''; }
                else field += char;
            }
            fields.push(field);
            return fields.map(part => unescape(part.trim())).filter(Boolean);
        };
        return cards.map(card => {
            let name = first(card, 'FN') || first(card, 'ORG');
            if (!name) {
                const structured = first(card, 'N').split(';').map(part => part.trim()).filter(Boolean);
                name = structured.join(' ');
            }
            const addressParts = card.filter(property => property.key === 'ADR').flatMap(property => splitAddress(property.value));
            return {
                name,
                phone: all(card, 'TEL').join(' / '),
                email: first(card, 'EMAIL'),
                address: addressParts.join(', '),
                role: ['supplier', 'customer', 'both'].includes(first(card, 'X-STOCK-KEEPER-ROLE').toLowerCase())
                    ? first(card, 'X-STOCK-KEEPER-ROLE').toLowerCase() : 'both',
                notes: all(card, 'NOTE').join('\n')
            };
        });
    }

    function escapeVCard(value) {
        return String(value == null ? '' : value).replace(/\\/g, '\\\\').replace(/\r?\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
    }

    function downloadText(filename, content, type) {
        const blob = new Blob([content], {type});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    const contactFile = document.getElementById('contactImportFile');
    const previewContactsButton = document.getElementById('previewContactImportButton');
    const importContactsButton = document.getElementById('runContactImportButton');
    const contactImportRows = document.getElementById('contactImportRows');
    const contactImportSummary = document.getElementById('contactImportSummary');
    let contactRows = [];
    function renderContactPreview(report) {
        contactRows = Array.isArray(report.rows) ? report.rows : [];
        if (contactImportRows) {
            contactImportRows.replaceChildren();
            contactRows.forEach(contact => {
                const row = document.createElement('tr');
                [contact.row, contact.name, contact.phone, contact.email, contact.role,
                    `${contact.status === 'ready' ? 'Ready to import' : contact.status === 'imported' ? 'Created' : contact.status === 'existing' ? 'Existing' : contact.status === 'conflict' ? 'Conflict' : 'Invalid'}${contact.reason ? ': ' + contact.reason : ''}`]
                    .forEach(value => { const cell = document.createElement('td'); cell.textContent = String(value == null ? '' : value); row.append(cell); });
                contactImportRows.append(row);
            });
        }
        const counts = report.counts || {};
        if (contactImportSummary) contactImportSummary.textContent = `${report.total || contactRows.length} contacts · ${counts.imported || 0} created · ${counts.ready || 0} ready · ${counts.existing || 0} already present · ${(counts.conflict || 0) + (counts.invalid || 0)} need attention. Existing partner data is preserved.`;
        if (importContactsButton) importContactsButton.disabled = !(counts.ready > 0);
    }
    contactFile?.addEventListener('change', () => {
        contactRows = [];
        if (importContactsButton) importContactsButton.disabled = true;
        if (contactImportSummary) contactImportSummary.textContent = '';
        if (contactImportRows) contactImportRows.innerHTML = '<tr><td colspan="6" class="text-muted">Preview the selected CSV to review matches and errors.</td></tr>';
    });
    previewContactsButton?.addEventListener('click', async () => {
        const file = contactFile?.files?.[0];
        if (!file) { notify('Choose a CSV or vCard file first.', true); return; }
        if (file.size > 2 * 1024 * 1024) { notify('Contact files must be 2 MB or smaller.', true); return; }
        previewContactsButton.disabled = true;
        previewContactsButton.textContent = 'Reading…';
        try {
            const fileText = await file.text();
            const isVCard = /\.vcf$/i.test(file.name) || /^\s*BEGIN:VCARD/im.test(fileText);
            const contacts = isVCard ? parseVCard(fileText) : parseContactCsv(fileText);
            if (contacts.length > 5000) throw new Error('A contact file can contain up to 5,000 contacts.');
            const payload = new FormData();
            payload.set('contacts_json', JSON.stringify(contacts));
            const response = await sendRequest('preview_contact_import', payload);
            renderContactPreview(response.data || {});
        } catch (error) { notify(error.message || 'Could not preview contacts.', true); }
        finally { previewContactsButton.disabled = false; previewContactsButton.textContent = 'Preview Contacts'; }
    });
    importContactsButton?.addEventListener('click', async () => {
        if (!contactRows.length || importContactsButton.disabled) return;
        importContactsButton.disabled = true;
        importContactsButton.textContent = 'Importing…';
        try {
            const contacts = contactRows.map(({name, phone, email, address, role, notes}) => ({name, phone, email, address, role, notes}));
            const payload = new FormData();
            payload.set('contacts_json', JSON.stringify(contacts));
            const response = await sendRequest('import_contacts', payload);
            renderContactPreview(response.data || {});
            notify(`${response.data?.created || 0} partners created. Existing records were preserved.`);
        } catch (error) { notify(error.message || 'Contact import failed. No partial changes were saved.', true); }
        finally { importContactsButton.textContent = 'Import Ready Contacts'; }
    });

    async function exportContacts(format) {
        const response = await sendRequest('export_contacts');
        const contacts = Array.isArray(response.data?.contacts) ? response.data.contacts : [];
        if (!contacts.length) { notify('There are no contacts available to export.', true); return; }
        if (format === 'csv') {
            const fields = ['name', 'phone', 'email', 'address', 'role', 'notes'];
            const rows = [fields, ...contacts.map(contact => fields.map(field => contact[field] || ''))];
            const csv = rows.map(row => row.map(value => '"' + String(value).replace(/"/g, '""') + '"').join(',')).join('\r\n');
            downloadText('stock-keeper-contacts.csv', '\uFEFF' + csv, 'text/csv;charset=utf-8');
        } else {
            const vcards = contacts.map(contact => [
                'BEGIN:VCARD', 'VERSION:3.0', `FN:${escapeVCard(contact.name)}`,
                `N:${escapeVCard(contact.name)};;;;`, ...(contact.phone ? [`TEL;TYPE=VOICE:${escapeVCard(contact.phone)}`] : []),
                ...(contact.email ? [`EMAIL:${escapeVCard(contact.email)}`] : []),
                ...(contact.address ? [`ADR;TYPE=WORK:;;${escapeVCard(contact.address)};;;;`] : []),
                `X-STOCK-KEEPER-ROLE:${escapeVCard(contact.role)}`,
                ...(contact.notes ? [`NOTE:${escapeVCard(contact.notes)}`] : []), 'END:VCARD'
            ].join('\r\n')).join('\r\n');
            downloadText('stock-keeper-contacts.vcf', vcards + '\r\n', 'text/vcard;charset=utf-8');
        }
        notify(`${contacts.length} contacts exported as ${format.toUpperCase()}.`);
    }
    document.getElementById('exportContactsCsvButton')?.addEventListener('click', () => exportContacts('csv').catch(error => notify(error.message || 'Could not export contacts.', true)));
    document.getElementById('exportContactsVcfButton')?.addEventListener('click', () => exportContacts('vcf').catch(error => notify(error.message || 'Could not export contacts.', true)));

    const catalogueAvailableList = document.getElementById('catalogueAvailableList');
    const catalogueHistoryList = document.getElementById('catalogueHistoryList');
    const catalogueHistoryReports = new Map();

    function renderInstallationReport(report, title = 'Catalogue installation report') {
        const reportPanel = document.getElementById('catalogueInstallationReport');
        const detailsPanel = document.getElementById('catalogueReportDetails');
        const skipped = Array.isArray(report.skipped_products) ? report.skipped_products : [];
        const conflicts = Array.isArray(report.conflicts) ? report.conflicts : [];
        const skippedList = document.getElementById('catalogueSkippedList');
        const conflictList = document.getElementById('catalogueConflictList');

        app.setText('#catalogueReportTitle', title);
        app.setText('#catalogueProductsCreated', report.products_created || 0);
        app.setText('#catalogueCategoriesReady', Number(report.categories_created || 0) + Number(report.categories_reused || 0));
        app.setText('#catalogueUnitsReady', Number(report.unit_created || 0) + Number(report.unit_reused || 0));
        app.setText('#catalogueConflicts', report.conflict_count ?? conflicts.length);

        if (skippedList) {
            skippedList.replaceChildren();
            skipped.forEach(entry => {
                const item = document.createElement('li');
                item.textContent = `${entry.product}: ${entry.reason || 'Already exists.'}`;
                skippedList.append(item);
            });
            skippedList.hidden = skipped.length === 0;
        }
        if (conflictList) {
            conflictList.replaceChildren();
            conflicts.forEach(conflict => {
                const item = document.createElement('li');
                item.textContent = conflict.type === 'sku_conflict'
                    ? `${conflict.product}: SKU ${conflict.sku} belongs to ${conflict.existing_product}.`
                    : `${conflict.product}: already assigned to ${conflict.existing_category}; expected ${conflict.expected_category}.`;
                conflictList.append(item);
            });
            conflictList.hidden = conflicts.length === 0;
        }
        const noSkipped = document.getElementById('catalogueNoSkipped');
        const noConflicts = document.getElementById('catalogueNoConflicts');
        if (noSkipped) noSkipped.hidden = skipped.length > 0;
        if (noConflicts) noConflicts.hidden = conflicts.length > 0;
        if (detailsPanel) detailsPanel.hidden = false;
        if (reportPanel) reportPanel.hidden = false;
    }

    function renderCatalogueManagement(data = {}) {
        if (catalogueAvailableList) {
            catalogueAvailableList.replaceChildren();
            const catalogues = Array.isArray(data.catalogues) ? data.catalogues : [];
            if (!catalogues.length) {
                const empty = document.createElement('p');
                empty.className = 'text-muted';
                empty.textContent = 'No product catalogues are available.';
                catalogueAvailableList.append(empty);
            }
            catalogues.forEach(catalogue => {
                const card = document.createElement('article');
                card.className = 'catalogue-available-card';
                const heading = document.createElement('h3');
                heading.textContent = catalogue.name;
                const summary = document.createElement('p');
                summary.textContent = `${catalogue.category_count} categories · ${catalogue.product_count} products`;
                card.append(heading, summary);
                if (catalogue.installed) {
                    const badge = document.createElement('span');
                    badge.className = 'catalogue-installed-badge';
                    badge.textContent = 'Previously installed';
                    card.append(badge);
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'settings-primary-button';
                button.dataset.installCatalogueKey = catalogue.key;
                button.innerHTML = '<i class="fa fa-download me-1" aria-hidden="true"></i> Install Catalogue';
                card.append(button);
                catalogueAvailableList.append(card);
            });
        }

        if (catalogueHistoryList) {
            catalogueHistoryList.replaceChildren();
            catalogueHistoryReports.clear();
            const history = Array.isArray(data.history) ? data.history : [];
            if (!history.length) {
                app.renderTableMessage(catalogueHistoryList, 6, 'No catalogue installations yet.');
            }
            history.forEach(entry => {
                catalogueHistoryReports.set(String(entry.installation_id), entry);
                const row = document.createElement('tr');
                const values = [
                    entry.catalogue_name,
                    app.formatDate(entry.installed_at, '—', { hour: undefined, minute: undefined }),
                    entry.products_created,
                    entry.products_skipped,
                    entry.conflict_count
                ];
                values.forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = value == null ? '0' : String(value);
                    row.append(cell);
                });
                const actionCell = document.createElement('td');
                const review = document.createElement('button');
                review.type = 'button';
                review.className = 'btn btn-sm btn-outline-primary';
                review.dataset.reviewCatalogueInstallation = String(entry.installation_id);
                review.textContent = 'Review';
                actionCell.append(review);
                row.append(actionCell);
                catalogueHistoryList.append(row);
            });
        }
    }

    async function loadCatalogueManagement() {
        try {
            const response = await sendRequest('catalogue_management');
            renderCatalogueManagement(response.data || {});
        } catch (error) {
            if (catalogueAvailableList) catalogueAvailableList.textContent = error.message || 'Could not load catalogues.';
            if (catalogueHistoryList) app.renderTableMessage(catalogueHistoryList, 6, 'Could not load installation history.');
        }
    }

    catalogueAvailableList?.addEventListener('click', async event => {
        const button = event.target.closest('[data-install-catalogue-key]');
        if (!button) return;
        const catalogueKey = button.dataset.installCatalogueKey;
        button.disabled = true;
        button.textContent = 'Installing…';
        try {
            const response = await sendRequest('install_catalogue', { catalogue_key: catalogueKey });
            const report = response.data?.report;
            if (!report) throw new Error('The server did not return an installation report.');
            renderInstallationReport(report, `${report.catalogue_name} installation report`);
            notify(`Catalogue installation completed: ${report.products_created || 0} products created, ${report.conflict_count || 0} conflicts.`, Number(report.conflict_count) > 0);
            await loadCatalogueManagement();
        } catch (error) {
            notify(error.message || 'Catalogue installation failed. No partial changes were saved.', true);
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fa fa-download me-1" aria-hidden="true"></i> Install Catalogue';
        }
    });

    catalogueHistoryList?.addEventListener('click', event => {
        const button = event.target.closest('[data-review-catalogue-installation]');
        if (!button) return;
        const entry = catalogueHistoryReports.get(button.dataset.reviewCatalogueInstallation);
        if (!entry) return;
        renderInstallationReport(entry.report || {}, `${entry.catalogue_name} · ${app.formatDate(entry.installed_at)}`);
    });

    loadCatalogueManagement();

    function applySettingsTheme(theme) {
        const dark = theme === 'dark' || (theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.body.classList.toggle('dark-mode', Boolean(dark));
    }

    // Load Main Settings Data
    sendRequest('load').then(response => {
        if (!response || response.status !== 'success') {
            notify(response.message || 'Could not load settings.', true);
            return;
        }

        const data = response.data || {};
        const prefs = data.settings || {};

        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
        setVal('businessNameInput', data.cName || '');
        setVal('businessEmailInput', data.cEmail || '');
        setVal('accountNameSummary', data.cName || '');
        setVal('accountEmailSummary', data.cEmail || '');

        const registered = Number(data.regDate);
        const registeredDate = registered > 0 ? new Date(registered * 1000) : new Date(data.regDate || '');
        const dateText = Number.isNaN(registeredDate.getTime()) ? '' : registeredDate.toLocaleDateString();
        setVal('registrationDateInput', dateText);
        setVal('accountCreatedSummary', dateText);
        setVal('securityQuestionInput', data.question || '');

        const logoPreview = document.getElementById('settingsLogoPreview');
        if (logoPreview) {
            logoPreview.setAttribute('src', 'Images/companyDP/' + encodeURIComponent(data.cLogo || 'logo.jpg') + '?v=' + Date.now());
        }

        setVal('defaultReorderLevel', prefs.default_reorder_level ?? '0');

        const lowWarn = document.getElementById('lowStockWarning');
        const outWarn = document.getElementById('outOfStockWarning');
        if (lowWarn) lowWarn.checked = prefs.low_stock_warning !== '0';
        if (outWarn) outWarn.checked = prefs.out_of_stock_warning !== '0';

        localStorage.setItem('stockWarnings', JSON.stringify({
            low: prefs.low_stock_warning !== '0',
            out: prefs.out_of_stock_warning !== '0'
        }));

        setVal('themeSelect', prefs.theme || 'system');
        setVal('displayDensitySelect', prefs.display_density || 'comfortable');

        localStorage.setItem('theme', prefs.theme || 'system');
        localStorage.setItem('displayDensity', prefs.display_density || 'comfortable');
        document.body.classList.toggle('compact-display', prefs.display_density === 'compact');
        applySettingsTheme(prefs.theme || 'system');

        // Fetch Units for inventory default dropdown
        app.requestApi('apiUnits.php', 'list').then(unitsResponse => {
            const units = unitsResponse?.data?.units || [];
            const select = document.getElementById('defaultProductUnit');
            if (!select) return;
            select.innerHTML = '';
            units.filter(unit => Number(unit.is_active) === 1).forEach(unit => {
                const opt = document.createElement('option');
                opt.value = unit.unit_symbol;
                opt.textContent = unit.unit_name + ' (' + unit.unit_symbol + ')';
                select.appendChild(opt);
            });
            if (!select.options.length) {
                const opt = document.createElement('option');
                opt.value = 'pcs';
                opt.textContent = 'Pieces (pcs)';
                select.appendChild(opt);
            }
            const savedUnit = prefs.default_unit || 'pcs';
            let foundUnit = false;
            for (let opt of select.options) {
                if (opt.value === savedUnit) { foundUnit = true; break; }
            }
            if (!foundUnit) {
                const opt = document.createElement('option');
                opt.value = savedUnit;
                opt.textContent = savedUnit;
                select.appendChild(opt);
            }
            select.value = savedUnit;
        });
    }).catch(err => {
        console.error('Failed to load settings:', err);
        notify('Could not load settings.', true);
    });

    const saveBizBtn = document.getElementById('saveBusinessSettingsButton');
    if (saveBizBtn) {
        saveBizBtn.addEventListener('click', async () => {
            try {
                const r = await sendRequest('save_business', {
                    business_name: document.getElementById('businessNameInput')?.value || '',
                    business_email: document.getElementById('businessEmailInput')?.value || ''
                });
                if (r?.status === 'success') {
                    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                    setVal('accountNameSummary', document.getElementById('businessNameInput')?.value || '');
                    setVal('accountEmailSummary', document.getElementById('businessEmailInput')?.value || '');
                    notify(r.message);
                } else {
                    notify(r.message || 'Could not save profile.', true);
                }
            } catch (x) {
                notify('Could not save profile.', true);
            }
        });
    }

    const saveInvBtn = document.getElementById('saveInventorySettingsButton');
    if (saveInvBtn) {
        saveInvBtn.addEventListener('click', async () => {
            try {
                const r = await sendRequest('save_inventory', {
                    default_unit: document.getElementById('defaultProductUnit')?.value || 'pcs',
                    default_reorder_level: document.getElementById('defaultReorderLevel')?.value || '0'
                });
                notify(r.message || 'Inventory defaults saved.', r?.status !== 'success');
            } catch (x) {
                notify('Could not save inventory defaults.', true);
            }
        });
    }

    const saveRulesBtn = document.getElementById('saveStockRulesButton');
    if (saveRulesBtn) {
        saveRulesBtn.addEventListener('click', async () => {
            const warnings = {
                low: document.getElementById('lowStockWarning')?.checked || false,
                out: document.getElementById('outOfStockWarning')?.checked || false
            };
            try {
                const r = await sendRequest('save_stock_rules', {
                    low_stock_warning: warnings.low ? '1' : '0',
                    out_of_stock_warning: warnings.out ? '1' : '0'
                });
                if (r?.status === 'success') {
                    localStorage.setItem('stockWarnings', JSON.stringify(warnings));
                    notify(r.message);
                } else {
                    notify(r.message || 'Could not save warning preferences.', true);
                }
            } catch (x) {
                notify('Could not save warning preferences.', true);
            }
        });
    }

    async function saveAppearance() {
        const themeVal = document.getElementById('themeSelect')?.value || 'system';
        const densityVal = document.getElementById('displayDensitySelect')?.value || 'comfortable';
        try {
            const r = await sendRequest('save_appearance', {
                theme: themeVal,
                display_density: densityVal
            });
            if (!r || r.status !== 'success') {
                notify(r.message || 'Could not save preferences.', true);
                return;
            }
            localStorage.setItem('theme', themeVal);
            localStorage.setItem('displayDensity', densityVal);
            document.body.classList.toggle('compact-display', densityVal === 'compact');
            applySettingsTheme(themeVal);
            notify(r.message || 'Appearance preferences saved.');
        } catch (x) {
            notify('Could not save preferences.', true);
        }
    }

    const saveAppBtn1 = document.getElementById('saveAppearanceButton');
    const saveAppBtn2 = document.getElementById('saveAppearanceButtonDisplay');
    if (saveAppBtn1) saveAppBtn1.addEventListener('click', saveAppearance);
    if (saveAppBtn2) saveAppBtn2.addEventListener('click', saveAppearance);

    const themeSelectEl = document.getElementById('themeSelect');
    if (themeSelectEl) themeSelectEl.addEventListener('change', function () { applySettingsTheme(this.value); });

    const bizLogoInput = document.getElementById('businessLogoInput');
    if (bizLogoInput) {
        bizLogoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            const logoPreview = document.getElementById('settingsLogoPreview');
            if (file && logoPreview) logoPreview.setAttribute('src', URL.createObjectURL(file));
        });
    }

    const uploadLogoBtn = document.getElementById('uploadBusinessLogoButton');
    if (uploadLogoBtn) {
        uploadLogoBtn.addEventListener('click', async function () {
            const fileInput = document.getElementById('businessLogoInput');
            const file = fileInput && fileInput.files[0];
            if (!file) return notify('Choose a logo image first.', true);

            try {
                const formData = new FormData();
                formData.append('logo', file);
                const r = await app.requestApi(settingsUrl, 'upload_logo', formData);
                if (r?.status !== 'success') return notify(r.message || 'Logo upload failed.', true);

                const logoPath = 'Images/companyDP/' + encodeURIComponent(r.data.logo) + '?v=' + Date.now();
                const logoPreview = document.getElementById('settingsLogoPreview');
                const cLogo = document.getElementById('cLogo');
                if (logoPreview) logoPreview.setAttribute('src', logoPath);
                if (cLogo) cLogo.setAttribute('src', logoPath);
                if (fileInput) fileInput.value = '';
                notify(r.message);
            } catch (x) {
                notify('Logo upload failed.', true);
            }
        });
    }

    const changePwdBtn = document.getElementById('changePasswordButton');
    if (changePwdBtn) {
        changePwdBtn.addEventListener('click', async function () {
            try {
                const r = await sendRequest('change_password', {
                    current_password: document.getElementById('currentPasswordInput')?.value || '',
                    new_password: document.getElementById('newPasswordInput')?.value || '',
                    confirm_password: document.getElementById('confirmPasswordInput')?.value || ''
                });
                if (r?.status === 'success') {
                    const clearVal = (id) => { const el = document.getElementById(id); if (el) el.value = ''; };
                    clearVal('currentPasswordInput');
                    clearVal('newPasswordInput');
                    clearVal('confirmPasswordInput');
                    notify(r.message);
                } else {
                    notify(r.message || 'Could not change password.', true);
                }
            } catch (x) {
                notify('Could not change password.', true);
            }
        });
    }

    const saveSecBtn = document.getElementById('saveSecurityButton');
    if (saveSecBtn) {
        saveSecBtn.addEventListener('click', async function () {
            try {
                const r = await sendRequest('save_security', {
                    current_password: document.getElementById('securityCurrentPasswordInput')?.value || '',
                    security_question: document.getElementById('securityQuestionInput')?.value || '',
                    security_answer: document.getElementById('securityAnswerInput')?.value || ''
                });
                if (r?.status === 'success') {
                    const clearVal = (id) => { const el = document.getElementById(id); if (el) el.value = ''; };
                    clearVal('securityAnswerInput');
                    clearVal('securityCurrentPasswordInput');
                    notify(r.message);
                } else {
                    notify(r.message || 'Could not save recovery details.', true);
                }
            } catch (x) {
                notify('Could not save recovery details.', true);
            }
        });
    }

    const runDiagBtn = document.getElementById('runDiagnosticsButton');
    if (runDiagBtn) {
        runDiagBtn.addEventListener('click', async function () {
            this.disabled = true;
            this.textContent = 'Checking…';
            try {
                const r = await sendRequest('diagnostics');
                if (r?.status !== 'success') {
                    notify(r.message || 'Health check failed.', true);
                    return;
                }
                const d = r.data || {};
                const healthy = String(d.integrity).toLowerCase() === 'ok';
                const diagRes = document.getElementById('diagnosticsResult');
                if (diagRes) {
                    diagRes.innerHTML = '<div><small>Database integrity</small><strong>' + (healthy ? 'Healthy' : 'Check required') + '</strong></div><div><small>Products</small><strong>' + Number(d.products || 0).toLocaleString() + '</strong></div><div><small>Stock movements</small><strong>' + Number(d.movements || 0).toLocaleString() + '</strong></div><div><small>Partners</small><strong>' + Number(d.partners || 0).toLocaleString() + '</strong></div>';
                }
                notify(healthy ? 'Database integrity check passed.' : 'Database check reported an issue.', !healthy);
            } catch (x) {
                notify('Health check failed.', true);
            } finally {
                this.disabled = false;
                this.textContent = 'Run Database Health Check';
            }
        });
    }
});
