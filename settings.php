<?php

declare(strict_types=1);

require_once __DIR__ . '/INC/session.php';
stockKeeperStartSession();

include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>
<main class="settings-page" id="settingsPage">
<?php
$pageHeadingTitle = 'Settings';
$pageHeadingDescription = 'Configure and manage your Stock Keeper system.';
$pageHeadingClass = 'settings-page-header';
$pageHeadingTitleClass = 'settings-page-title';
$pageHeadingDescriptionClass = 'settings-page-description';
$pageHeadingActions = '<button type="button" class="settings-mobile-toggle" id="settingsMobileToggle" aria-expanded="false" aria-controls="settingsNavigation"><span>Settings Menu</span><span aria-hidden="true">☰</span></button>';
include __DIR__ . '/INC/page_heading.php';
unset($pageHeadingTitle, $pageHeadingDescription, $pageHeadingActions, $pageHeadingClass, $pageHeadingTitleClass, $pageHeadingDescriptionClass);
?>

<div id="settingsFeedback" class="settings-feedback" role="status" aria-live="polite" hidden></div>


<!-- SETTINGS LAYOUT -->
<div class="settings-layout">


    <!-- ==========================================
         SETTINGS NAVIGATION
    =========================================== -->
    <aside
        class="settings-navigation"
        id="settingsNavigation"
        aria-label="Settings navigation">


        <div class="settings-navigation-header">

            <div>
                <div class="settings-navigation-title">
                    Settings
                </div>

                <div class="settings-navigation-subtitle">
                    Manage your system
                </div>
            </div>

        </div>


        <!-- BUSINESS -->
        <section class="settings-nav-group">

            <button
                type="button"
                class="settings-nav-group-toggle is-open"
                data-settings-group="business"
                aria-expanded="true">

                <span class="settings-nav-group-label">
                    <span class="settings-nav-icon">🏢</span>
                    Business
                </span>

                <span
                    class="settings-nav-chevron"
                    aria-hidden="true">
                   ⌄
                </span>

            </button>


            <div
                class="settings-nav-items"
                data-settings-items="business">

                <button
                    type="button"
                    class="settings-nav-link is-active"
                    data-settings-tab="business-profile">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Profile
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="business-logo">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Logo
                    </span>

                </button>

                <button type="button" class="settings-nav-link" data-settings-tab="contact-import">
                    <span class="settings-nav-link-icon">⇧</span>
                    <span>Import Contacts</span>
                </button>

            </div>

        </section>


        <!-- INVENTORY -->
        <section class="settings-nav-group">

            <button
                type="button"
                class="settings-nav-group-toggle is-open"
                data-settings-group="inventory"
                aria-expanded="true">

                <span class="settings-nav-group-label">
                    <span class="settings-nav-icon">📦</span>
                    Inventory
                </span>

                <span
                    class="settings-nav-chevron"
                    aria-hidden="true">
                   ⌄
                </span>

            </button>


            <div
                class="settings-nav-items"
                data-settings-items="inventory">

                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="inventory-general">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        General
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="product-catalogue">

                    <span class="settings-nav-link-icon">◉</span>
                    <span>Product Catalogue</span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="product-categories">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Product Categories
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="product-units">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Product Units
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="stock-rules">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Stock Rules
                    </span>

                </button>

            </div>

        </section>


        <!-- APPEARANCE -->
        <section class="settings-nav-group">

            <button
                type="button"
                class="settings-nav-group-toggle"
                data-settings-group="appearance"
                aria-expanded="false">

                <span class="settings-nav-group-label">
                    <span class="settings-nav-icon">🎨</span>
                    Appearance
                </span>

                <span
                    class="settings-nav-chevron"
                    aria-hidden="true">
                   ⌄
                </span>

            </button>


            <div
                class="settings-nav-items"
                data-settings-items="appearance"
                hidden>

                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="appearance-theme">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Theme
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="appearance-display">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Display
                    </span>

                </button>

            </div>

        </section>


        <!-- ACCOUNT & SECURITY -->
        <section class="settings-nav-group">

            <button
                type="button"
                class="settings-nav-group-toggle"
                data-settings-group="account"
                aria-expanded="false">

                <span class="settings-nav-group-label">
                    <span class="settings-nav-icon">🔐</span>
                    Account & Security
                </span>

                <span
                    class="settings-nav-chevron"
                    aria-hidden="true">
                   ⌄
                </span>

            </button>


            <div
                class="settings-nav-items"
                data-settings-items="account"
                hidden>

                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="account-profile">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Account
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="account-password">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Password
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="account-security">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Security
                    </span>

                </button>

            </div>

        </section>


        <!-- SYSTEM -->
        <section class="settings-nav-group">

            <button
                type="button"
                class="settings-nav-group-toggle"
                data-settings-group="system"
                aria-expanded="false">

                <span class="settings-nav-group-label">
                    <span class="settings-nav-icon">⚙</span>
                    System
                </span>

                <span
                    class="settings-nav-chevron"
                    aria-hidden="true">
                   ⌄
                </span>

            </button>


            <div
                class="settings-nav-items"
                data-settings-items="system"
                hidden>

                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="system-application">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Application
                    </span>

                </button>


                <button
                    type="button"
                    class="settings-nav-link"
                    data-settings-tab="system-maintenance">

                    <span class="settings-nav-link-icon">
                        ◉
                    </span>

                    <span>
                        Maintenance
                    </span>

                </button>

            </div>

        </section>

    </aside>


    <!-- ==========================================
         SETTINGS CONTENT
    =========================================== -->
    <section class="settings-content">


        <!-- BUSINESS PROFILE -->
        <div
            class="settings-panel is-active"
            data-settings-panel="business-profile">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        BUSINESS
                    </span>

                    <h2>
                        Business Profile
                    </h2>

                    <p>
                        Manage the basic information displayed throughout Stock Keeper.
                    </p>

                </div>

            </div>


            <div class="settings-card">

                <div class="settings-form-grid">

                    <div class="settings-field settings-field-full">

                        <label for="businessNameInput">
                            Business Name
                        </label>

                        <input
                            type="text"
                            id="businessNameInput"
                            name="business_name"
                            class="settings-input"
                            maxlength="120"
                            required
                            placeholder="Enter your business name">

                    </div>


                    <div class="settings-field">

                        <label for="businessEmailInput">
                            Business Email
                        </label>

                        <input
                            type="email"
                            id="businessEmailInput"
                            name="business_email"
                            class="settings-input"
                            maxlength="190"
                            required
                            placeholder="business@example.com">

                    </div>


                    <div class="settings-field">

                        <label for="registrationDateInput">
                            Registration Date
                        </label>

                        <input
                            type="text"
                            id="registrationDateInput"
                            class="settings-input"
                            readonly>

                    </div>

                </div>


                <div class="settings-card-footer">

                    <button
                        type="button"
                        class="settings-primary-button"
                        id="saveBusinessSettingsButton">

                        Save Changes

                    </button>

                </div>

            </div>

        </div>

        <!-- CONTACT IMPORT -->
        <div class="settings-panel" data-settings-panel="contact-import">
            <div class="settings-panel-header">
                <span class="settings-panel-eyebrow">BUSINESS</span>
                <h2>Import Contacts</h2>
                <p>Upload a CSV to preview contacts and add new partners to this business account.</p>
            </div>
            <div class="settings-card">
                <div class="settings-field settings-field-full">
                    <label for="contactImportFile">Contact CSV</label>
                    <input type="file" id="contactImportFile" class="settings-input" accept=".csv,.vcf,text/csv,text/vcard">
                    <small>Accepts CSV headers name, phone, email, address, role, notes, or vCard (.vcf) contact files. Name is required; CSV role may be supplier, customer, or both (defaults to both). Maximum 5,000 contacts per file (2 MB).</small>
                </div>
                <div class="settings-card-footer">
                    <button type="button" class="settings-primary-button" id="previewContactImportButton">Preview Contacts</button>
                    <button type="button" class="settings-primary-button" id="runContactImportButton" disabled>Import Ready Contacts</button>
                    <button type="button" class="settings-primary-button" id="exportContactsCsvButton">Export Contacts CSV</button>
                    <button type="button" class="settings-primary-button" id="exportContactsVcfButton">Export Contacts vCard</button>
                </div>
                <div id="contactImportSummary" role="status" aria-live="polite"></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>CSV row</th><th>Name</th><th>Phone</th><th>Email</th><th>Role</th><th>Result</th></tr></thead>
                        <tbody id="contactImportRows"><tr><td colspan="6" class="text-muted">Choose a CSV and preview it.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- BUSINESS LOGO -->
        <div
            class="settings-panel"
            data-settings-panel="business-logo">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        BUSINESS
                    </span>

                    <h2>
                        Business Logo
                    </h2>

                    <p>
                        Manage the logo used by your Stock Keeper workspace.
                    </p>

                </div>

            </div>


            <div class="settings-card">
                <div class="settings-logo-layout">
                    <img id="settingsLogoPreview" src="Images/companyDP/logo.jpg" alt="Business logo preview">
                    <div class="settings-logo-controls">
                        <label for="businessLogoInput">Upload a business logo</label>
                        <input type="file" id="businessLogoInput" accept="image/jpeg,image/png,image/webp" class="settings-input">
                        <small>JPG, PNG, or WebP. Maximum file size: 2 MB.</small>
                        <div class="settings-card-footer">
                            <button type="button" class="settings-primary-button" id="uploadBusinessLogoButton">Upload Logo</button>
                        </div>
                        <div id="businessLogoMessage" role="status" aria-live="polite" hidden></div>
                    </div>
                </div>
            </div>

        </div>


        <!-- INVENTORY GENERAL -->
        <div
            class="settings-panel"
            data-settings-panel="inventory-general">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        INVENTORY
                    </span>

                    <h2>
                        Inventory General
                    </h2>

                    <p>
                        Configure general inventory behaviour.
                    </p>

                </div>

            </div>


            <div class="settings-card">

                <div class="settings-form-grid">

                    <div class="settings-field">

                        <label for="defaultProductUnit">
                            Default Product Unit
                        </label>

                        <select id="defaultProductUnit" class="settings-input">
                            <option value="pcs">Pieces (pcs)</option>
                        </select>

                    </div>


                    <div class="settings-field">

                        <label for="defaultReorderLevel">
                            Default Reorder Level
                        </label>

                        <input
                            type="number"
                            id="defaultReorderLevel"
                            class="settings-input"
                            min="0"
                            step="0.01"
                            value="0">

                    </div>

                </div>


                <div class="settings-card-footer">

                    <button type="button" class="settings-primary-button" id="saveInventorySettingsButton">

                        Save Changes

                    </button>

                </div>

            </div>

        </div>


        <!-- PRODUCT CATALOGUE INSTALLER -->
        <div class="settings-panel" data-settings-panel="product-catalogue">
            <div class="settings-panel-header">
                <span class="settings-panel-eyebrow">INVENTORY</span>
                <h2>Catalogue Management</h2>
                <p>Browse available product catalogues, install them into your business account, and review previous installation results.</p>
            </div>

            <div class="settings-card catalogue-installer-card">
                <div class="catalogue-section-heading">
                    <div>
                        <h3>Available catalogues</h3>
                        <p>New products use <strong>sheet</strong>, zero opening stock, zero prices, and your default reorder level.</p>
                    </div>
                </div>
                <div id="catalogueAvailableList" class="catalogue-available-list" aria-live="polite">
                    <p class="text-muted">Loading available catalogues…</p>
                </div>

                <div class="catalogue-history-section">
                    <h3>Installation history</h3>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Catalogue</th><th>Installed</th><th>Created</th><th>Skipped</th><th>Conflicts</th><th><span class="visually-hidden">Details</span></th></tr></thead>
                            <tbody id="catalogueHistoryList"><tr><td colspan="6" class="text-center text-muted">Loading installation history…</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <div id="catalogueInstallationReport" class="catalogue-report" hidden aria-live="polite">
                    <h3 id="catalogueReportTitle">Catalogue installation report</h3>
                    <div class="catalogue-report-grid">
                        <div class="catalogue-report-stat"><strong id="catalogueProductsCreated">0</strong><span>Products created</span></div>
                        <div class="catalogue-report-stat"><strong id="catalogueCategoriesReady">0</strong><span>Categories created or reused</span></div>
                        <div class="catalogue-report-stat"><strong id="catalogueUnitsReady">0</strong><span>Units created or reused</span></div>
                        <div class="catalogue-report-stat"><strong id="catalogueConflicts">0</strong><span>Conflicts</span></div>
                    </div>
                    <p id="catalogueRecordsPreserved" class="catalogue-preserved-note">Existing records preserved.</p>
                    <div id="catalogueReportDetails" class="catalogue-report-details" hidden>
                        <h4>Skipped products</h4>
                        <ul id="catalogueSkippedList" class="catalogue-detail-list" hidden></ul>
                        <p id="catalogueNoSkipped" class="text-muted">No products were skipped.</p>
                        <h4>Conflicts</h4>
                        <ul id="catalogueConflictList" class="catalogue-detail-list catalogue-conflict-list" hidden></ul>
                        <p id="catalogueNoConflicts" class="text-muted">No conflicts were reported.</p>
                    </div>
                </div>
            </div>
        </div>


<!-- PRODUCT CATEGORIES -->
<div
    class="settings-panel"
    data-settings-panel="product-categories">

    <div class="settings-panel-header settings-panel-header-row">
        <div>
            <span class="settings-panel-eyebrow">INVENTORY</span>
            <h2>Product Categories</h2>
            <p>
                Organize your products into categories for easier
                stock management and reporting.
            </p>
        </div>

        <button
            type="button"
            class="settings-primary-button"
            id="openAddCategoryButton"
            data-bs-toggle="modal"
            data-bs-target="#addCategoryModal">

            <i class="fa fa-plus me-1"></i>
            Add Category
        </button>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="settings-category-stats">

        <div class="settings-category-stat">
            <span>Total Categories</span>
            <strong id="categoryTotalCount">0</strong>
        </div>

        <div class="settings-category-stat">
            <span>Active</span>
            <strong id="categoryActiveCount">0</strong>
        </div>

        <div class="settings-category-stat">
            <span>Inactive</span>
            <strong id="categoryInactiveCount">0</strong>
        </div>

    </div>

    <!-- CATEGORY MANAGEMENT -->
    <div class="settings-card">

        <div class="settings-category-toolbar">

            <div class="settings-category-search">
                <i class="fa fa-search"></i>

                <input
                    type="search"
                    id="categorySearchInput"
                    class="settings-input"
                    placeholder="Search categories..."
                    aria-label="Search categories">
            </div>

            <select
                id="categoryStatusFilter"
                class="settings-input settings-category-filter"
                aria-label="Filter categories by status">

                <option value="all">All statuses</option>
                <option value="1">Active only</option>
                <option value="0">Inactive only</option>
            </select>

            <button
                type="button"
                class="settings-secondary-button"
                id="refreshCategoriesButton">

                <i class="fa fa-refresh me-1"></i>
                Refresh
            </button>

        </div>

        <div
            id="categoriesListMessage"
            class="settings-category-message"
            role="status"
            aria-live="polite"
            hidden>
        </div>

        <div class="settings-category-table-wrap">

            <table class="settings-category-table">

                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="settings-category-actions-heading">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody id="categoriesTableBody">

                    <tr>
                        <td colspan="4">
                            <div class="settings-category-loading">
                                <i class="fa fa-spinner fa-spin me-2"></i>
                                Categories will load here.
                            </div>
                        </td>
                    </tr>

                </tbody>

            </table>

        </div>

        <div class="settings-category-footer">
            <span id="categoriesResultCount">
                0 categories
            </span>
        </div>

    </div>

</div>

<div class="settings-panel" data-settings-panel="product-units">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div class="mb-3 mb-md-0">
            <small class="text-primary fw-bold">INVENTORY SETTINGS</small>
            <h3 class="mb-1">Product Units</h3>
            <p class="text-muted mb-0">
                Manage the units used to measure and track your products.
            </p>
        </div>

        <button type="button" class="btn btn-primary"
                id="openAddUnitButton"
                data-bs-toggle="modal" data-bs-target="#addUnitModal">
            <i class="fa fa-plus me-1"></i> Add Unit
        </button>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Units</div>
                    <h3 class="mb-0" id="unitTotalCount">0</h3>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Active Units</div>
                    <h3 class="mb-0 text-success" id="unitActiveCount">0</h3>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Inactive Units</div>
                    <h3 class="mb-0 text-secondary" id="unitInactiveCount">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- SEARCH AND FILTERS -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6 mb-3">
                    <label for="unitSearchInput">Search units</label>
                    <input type="search" class="form-control"
                           id="unitSearchInput"
                           placeholder="Search name, symbol or description...">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="unitStatusFilter">Status</label>
                    <select class="form-control" id="unitStatusFilter">
                        <option value="all">All units</option>
                        <option value="1">Active only</option>
                        <option value="0">Inactive only</option>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <button type="button"
                            class="btn btn-outline-secondary w-100"
                            id="refreshUnitsButton">
                        <i class="fa fa-refresh me-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div id="unitsListMessage" class="alert mb-3"
                 role="alert" hidden></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Unit Name</th>
                            <th>Symbol</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="unitsTableBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Units will appear here.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted" id="unitsResultCount">
                    0 units
                </small>
                <small class="text-muted">Manage your measurement units</small>
            </div>
        </div>
    </div>
</div>


        <!-- STOCK RULES -->
        <div
            class="settings-panel"
            data-settings-panel="stock-rules">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        INVENTORY
                    </span>

                    <h2>
                        Stock Rules
                    </h2>

                    <p>
                        Configure low-stock and inventory warning behaviour.
                    </p>

                </div>

            </div>


            <div class="settings-card">

                <div class="settings-option">

                    <div>

                        <strong>
                            Low-stock warnings
                        </strong>

                        <small>
                        Show low-stock warning badges in the product list.
                        </small>

                    </div>

                    <label class="settings-switch">

                        <input
                            type="checkbox"
                            id="lowStockWarning">

                        <span></span>

                    </label>

                </div>

                <div class="settings-option">

                    <div>

                        <strong>
                            Out-of-stock warnings
                        </strong>

                        <small>
                            Show out-of-stock warning badges in the product list.
                        </small>

                    </div>

                    <label class="settings-switch">

                        <input
                            type="checkbox"
                            id="outOfStockWarning">

                        <span></span>

                    </label>

                </div>

                <div class="settings-card-footer">
                    <button type="button" class="settings-primary-button" id="saveStockRulesButton">Save Changes</button>
                </div>

            </div>

        </div>


        <!-- APPEARANCE THEME -->
        <div
            class="settings-panel"
            data-settings-panel="appearance-theme">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        APPEARANCE
                    </span>

                    <h2>
                        Theme
                    </h2>

                    <p>
                        Choose how Stock Keeper should appear.
                    </p>

                </div>

            </div>


            <div class="settings-card">

                <div class="settings-field">

                    <label for="themeSelect">
                        Application Theme
                    </label>

                    <select
                        id="themeSelect"
                        class="settings-input">

                        <option value="system">
                            System Default
                        </option>

                        <option value="light">
                            Light
                        </option>

                        <option value="dark">
                            Dark
                        </option>

                    </select>

                </div>

                <div class="settings-card-footer">
                    <button type="button" class="settings-primary-button" id="saveAppearanceButton">Save Preferences</button>
                </div>

            </div>

        </div>


        <!-- APPEARANCE DISPLAY -->
        <div
            class="settings-panel"
            data-settings-panel="appearance-display">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        APPEARANCE
                    </span>

                    <h2>
                        Display
                    </h2>

                    <p>
                        Control how information is presented in the application.
                    </p>

                </div>

            </div>


            <div class="settings-card">
                <div class="settings-field">
                    <label for="displayDensitySelect">Table density</label>
                    <select id="displayDensitySelect" class="settings-input">
                        <option value="comfortable">Comfortable</option>
                        <option value="compact">Compact</option>
                    </select>
                    <small>Compact mode reduces table row spacing across the app.</small>
                </div>
                <div class="settings-card-footer">
                    <button type="button" class="settings-primary-button" id="saveAppearanceButtonDisplay">Save Preferences</button>
                </div>
            </div>

        </div>


        <!-- ACCOUNT -->
        <div
            class="settings-panel"
            data-settings-panel="account-profile">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        ACCOUNT & SECURITY
                    </span>

                    <h2>
                        Account
                    </h2>

                    <p>
                        Manage your Stock Keeper account information.
                    </p>

                </div>

            </div>


            <div class="settings-card">
                <div class="settings-form-grid">
                    <div class="settings-field"><label>Account / business name</label><input id="accountNameSummary" class="settings-input" readonly></div>
                    <div class="settings-field"><label>Sign-in email</label><input id="accountEmailSummary" class="settings-input" readonly></div>
                    <div class="settings-field"><label>Member since</label><input id="accountCreatedSummary" class="settings-input" readonly></div>
                </div>
                <p class="text-muted mt-3 mb-0">Update your business name and email from Business → Profile.</p>
            </div>

        </div>


        <!-- PASSWORD -->
        <div
            class="settings-panel"
            data-settings-panel="account-password">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        ACCOUNT & SECURITY
                    </span>

                    <h2>
                        Password
                    </h2>

                    <p>
                        Change your account password.
                    </p>

                </div>

            </div>


            <div class="settings-card">
                <div class="settings-form-grid">
                    <div class="settings-field settings-field-full"><label for="currentPasswordInput">Current password</label><input type="password" id="currentPasswordInput" class="settings-input" autocomplete="current-password"></div>
                    <div class="settings-field"><label for="newPasswordInput">New password</label><input type="password" id="newPasswordInput" class="settings-input" minlength="8" maxlength="72" autocomplete="new-password"></div>
                    <div class="settings-field"><label for="confirmPasswordInput">Confirm new password</label><input type="password" id="confirmPasswordInput" class="settings-input" minlength="8" maxlength="72" autocomplete="new-password"></div>
                </div>
                <div class="settings-card-footer"><button type="button" class="settings-primary-button" id="changePasswordButton">Change Password</button></div>
            </div>

        </div>


        <!-- SECURITY -->
        <div
            class="settings-panel"
            data-settings-panel="account-security">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        ACCOUNT & SECURITY
                    </span>

                    <h2>
                        Security
                    </h2>

                    <p>
                        Manage security preferences for your account.
                    </p>

                </div>

            </div>


            <div class="settings-card">
                <p class="text-muted">These details are used for account recovery. Changing them requires your current password.</p>
                <div class="settings-form-grid">
                    <div class="settings-field settings-field-full"><label for="securityQuestionInput">Recovery question</label><input type="text" id="securityQuestionInput" class="settings-input" maxlength="200" placeholder="Enter a question only you can answer"></div>
                    <div class="settings-field"><label for="securityAnswerInput">Recovery answer</label><input type="password" id="securityAnswerInput" class="settings-input" minlength="2" maxlength="72" autocomplete="off"></div>
                    <div class="settings-field"><label for="securityCurrentPasswordInput">Current password</label><input type="password" id="securityCurrentPasswordInput" class="settings-input" autocomplete="current-password"></div>
                </div>
                <div class="settings-card-footer"><button type="button" class="settings-primary-button" id="saveSecurityButton">Save Recovery Details</button></div>
            </div>

        </div>


        <!-- SYSTEM APPLICATION -->
        <div
            class="settings-panel"
            data-settings-panel="system-application">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        SYSTEM
                    </span>

                    <h2>
                        Application
                    </h2>

                    <p>
                        View Stock Keeper application information.
                    </p>

                </div>

            </div>


            <div class="settings-card">

                <div class="settings-info-grid">

                    <div>
                        <small>Application</small>
                        <strong>Stock Keeper</strong>
                    </div>

                    <div>
                        <small>Version</small>
                        <strong>1.0.0</strong>
                    </div>

                    <div>
                        <small>Database</small>
                        <strong>SQLite</strong>
                    </div>

                </div>

            </div>

        </div>


        <!-- SYSTEM MAINTENANCE -->
        <div
            class="settings-panel"
            data-settings-panel="system-maintenance">

            <div class="settings-panel-header">

                <div>

                    <span class="settings-panel-eyebrow">
                        SYSTEM
                    </span>

                    <h2>
                        Maintenance
                    </h2>

                    <p>
                        System maintenance and diagnostic tools.
                    </p>

                </div>

            </div>


            <div class="settings-card">
                <p>Run a read-only integrity check and view company-scoped record counts.</p>
                <button type="button" class="settings-primary-button" id="runDiagnosticsButton">Run Database Health Check</button>
                <div id="diagnosticsResult" class="settings-info-grid mt-4" role="status" aria-live="polite"></div>
            </div>

        </div>

    </section>

</div>

</main><style>

/* =========================================================
   SETTINGS PAGE
========================================================= */

.settings-page {
    padding: 24px;
    max-width: 1500px;
    margin: 0 auto;
}

.settings-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
}

.settings-page-title {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
}

.settings-page-description {
    margin: 6px 0 0;
    color: #6c757d;
}


/* =========================================================
   LAYOUT
========================================================= */

.settings-layout {
    display: grid;
    grid-template-columns: 270px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}


/* =========================================================
   NAVIGATION
========================================================= */

.settings-navigation {
    position: sticky;
    top: 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
}

.settings-navigation-header {
    padding: 20px;
    border-bottom: 1px solid #edf0f2;
}

.settings-navigation-title {
    font-weight: 700;
}

.settings-navigation-subtitle {
    color: #8a9299;
    font-size: .8rem;
    margin-top: 3px;
}

.settings-nav-group {
    border-bottom: 1px solid #edf0f2;
}

.settings-nav-group:last-child {
    border-bottom: 0;
}

.settings-nav-group-toggle {
    width: 100%;
    border: 0;
    background: transparent;
    padding: 14px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: 600;
    text-align: left;
}

.settings-nav-group-toggle:hover {
    background: #f8f9fa;
}

.settings-nav-group-label {
    display: flex;
    align-items: center;
    gap: 9px;
}

.settings-nav-icon {
    width: 24px;
    text-align: center;
}

.settings-nav-chevron {
    transition: transform .2s ease;
}

.settings-nav-group-toggle.is-open .settings-nav-chevron {
    transform: rotate(180deg);
}

.settings-nav-items {
    padding: 0 8px 10px;
}

.settings-nav-link {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 0;
    background: transparent;
    padding: 10px 12px;
    border-radius: 9px;
    cursor: pointer;
    text-align: left;
    color: #59636e;
    font-size: .9rem;
}

.settings-nav-link:hover {
    background: #f5f7f9;
    color: #212529;
}

.settings-nav-link.is-active {
    background: #eaf2ff;
    color: #0d6efd;
    font-weight: 600;
}

.settings-nav-link-icon {
    font-size: .55rem;
}


/* =========================================================
   CONTENT
========================================================= */

.settings-content {
    min-width: 0;
}

.settings-panel {
    display: none;
}

.settings-panel.is-active {
    display: block;
}

.settings-panel-header {
    margin-bottom: 20px;
}

.settings-panel-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.settings-panel-eyebrow {
    display: block;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    color: #6c757d;
    margin-bottom: 5px;
}

.settings-panel-header h2 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
}

.settings-panel-header p {
    color: #6c757d;
    margin: 6px 0 0;
}


/* =========================================================
   CARDS
========================================================= */

.settings-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,.03);
}

.settings-card-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #edf0f2;
}


/* =========================================================
   FORMS
========================================================= */

.settings-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.settings-field {
    min-width: 0;
}

.settings-field-full {
    grid-column: 1 / -1;
}

.settings-field label {
    display: block;
    font-size: .85rem;
    font-weight: 600;
    margin-bottom: 7px;
}

.settings-input {
    width: 100%;
    min-height: 42px;
    padding: 9px 12px;
    border: 1px solid #dfe3e7;
    border-radius: 8px;
    background: #fff;
    color: #212529;
    outline: none;
}

.settings-input:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 3px rgba(13,110,253,.1);
}


/* =========================================================
   BUTTONS
========================================================= */

.settings-primary-button {
    border: 0;
    border-radius: 8px;
    background: #0d6efd;
    color: #fff;
    padding: 9px 16px;
    font-weight: 600;
    cursor: pointer;
}

.settings-primary-button:hover {
    opacity: .92;
}


/* =========================================================
   EMPTY STATES
========================================================= */

.settings-empty-state {
    text-align: center;
    padding: 45px 20px;
    color: #6c757d;
}

.settings-empty-state.compact {
    padding: 35px 20px;
}

.settings-empty-icon {
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: .55;
}

.settings-empty-state h3 {
    font-size: 1rem;
    margin: 0 0 5px;
    color: #343a40;
}

.settings-empty-state p {
    margin: 0;
    font-size: .85rem;
}


/* =========================================================
   PLACEHOLDER TABLE
========================================================= */

.settings-placeholder-table {
    overflow-x: auto;
}

.settings-placeholder-row {
    min-width: 550px;
    display: grid;
    grid-template-columns: 1fr 150px 120px;
    gap: 15px;
    padding: 13px 5px;
}

.settings-placeholder-heading {
    font-size: .75rem;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    border-bottom: 1px solid #edf0f2;
}


/* =========================================================
   OPTIONS
========================================================= */

.settings-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 17px 0;
    border-bottom: 1px solid #edf0f2;
}

.settings-option:first-child {
    padding-top: 0;
}

.settings-option:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.settings-option strong {
    display: block;
    font-size: .9rem;
}

.settings-option small {
    display: block;
    color: #7a838c;
    margin-top: 3px;
}


/* =========================================================
   SWITCH
========================================================= */

.settings-switch {
    position: relative;
    display: inline-flex;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.settings-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.settings-switch span {
    position: absolute;
    inset: 0;
    background: #ced4da;
    border-radius: 20px;
    cursor: pointer;
    transition: .2s;
}

.settings-switch span::after {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: .2s;
}

.settings-switch input:checked + span {
    background: #0d6efd;
}

.settings-switch input:checked + span::after {
    transform: translateX(20px);
}


/* =========================================================
   SYSTEM INFORMATION
========================================================= */

.settings-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.settings-info-grid small {
    display: block;
    color: #7a838c;
    margin-bottom: 5px;
}

.settings-info-grid strong {
    display: block;
}


/* =========================================================
   MOBILE
========================================================= */

.settings-mobile-toggle {
    display: none;
    border: 1px solid #dee2e6;
    background: #fff;
    border-radius: 8px;
    padding: 9px 13px;
    cursor: pointer;
    gap: 10px;
    align-items: center;
}


@media (max-width: 900px) {

    .settings-page {
        padding: 16px;
    }

    .settings-layout {
        grid-template-columns: 1fr;
    }

    .settings-navigation {
        position: static;
    }

    .settings-mobile-toggle {
        display: inline-flex;
    }

    .settings-navigation {
        display: none;
    }

    .settings-navigation.is-visible {
        display: block;
    }

}


@media (max-width: 650px) {

    .settings-page-header {
        align-items: center;
    }

    .settings-page-description {
        font-size: .85rem;
    }

    .settings-page-title {
        font-size: 1.45rem;
    }

    .settings-form-grid {
        grid-template-columns: 1fr;
    }

    .settings-field-full {
        grid-column: auto;
    }

    .settings-panel-header-row {
        flex-direction: column;
    }

    .settings-info-grid {
        grid-template-columns: 1fr;
    }

    .settings-card {
        padding: 18px;
    }

}
/* CATEGORY MANAGEMENT */

.settings-category-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.settings-category-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px;
}

.settings-category-stat span {
    display: block;
    color: #6c757d;
    font-size: .8rem;
    margin-bottom: 8px;
}

.settings-category-stat strong {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #212529;
}

.settings-category-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.settings-category-search {
    position: relative;
    flex: 1 1 220px;
}

.settings-category-search > i {
    position: absolute;
    left: 13px;
    top: 14px;
    color: #8a9299;
}

.settings-category-search .settings-input {
    padding-left: 38px;
}

.settings-category-filter {
    width: auto;
    min-width: 145px;
}

.settings-secondary-button {
    min-height: 42px;
    padding: 9px 14px;
    border: 1px solid #dfe3e7;
    border-radius: 8px;
    background: #fff;
    color: #343a40;
    font-weight: 600;
    cursor: pointer;
}

.settings-secondary-button:hover {
    background: #f8f9fa;
}

.settings-category-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.settings-category-table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

.settings-category-table th {
    padding: 13px 12px;
    background: #f8f9fb;
    border-bottom: 1px solid #e5e7eb;
    color: #6c757d;
    font-size: .73rem;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.settings-category-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f2;
    font-size: .88rem;
    vertical-align: middle;
}

.settings-category-table tbody tr:last-child td {
    border-bottom: 0;
}

.settings-category-actions-heading {
    text-align: right !important;
}

.settings-category-loading {
    padding: 32px 12px;
    text-align: center;
    color: #6c757d;
}

.settings-category-message {
    padding: 11px 14px;
    margin-bottom: 16px;
    border-radius: 8px;
    background: #f8f9fa;
    color: #495057;
    font-size: .88rem;
}

.settings-category-footer {
    border-top: 1px solid #edf0f2;
    margin-top: 10px;
    padding-top: 16px;
    color: #6c757d;
    font-size: .8rem;
}

.settings-category-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 600;
}

.settings-category-status.is-active {
    background: #e7f6ec;
    color: #187344;
}

.settings-category-status.is-inactive {
    background: #f1f2f4;
    color: #606770;
}

.settings-category-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.settings-category-action {
    width: 34px;
    height: 34px;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    background: #fff;
    color: #495057;
    cursor: pointer;
}

.settings-category-action:hover {
    background: #f1f5f9;
}

@media (max-width: 650px) {
    .settings-category-stats {
        gap: 8px;
    }

    .settings-category-stat {
        padding: 12px;
    }

    .settings-category-stat span {
        font-size: .72rem;
    }

    .settings-category-stat strong {
        font-size: 1.25rem;
    }

    .settings-category-toolbar {
        align-items: stretch;
    }

    .settings-category-search {
        flex-basis: 100%;
    }

    .settings-category-filter {
        flex: 1;
    }
}
.settings-logo-layout { display:flex; align-items:center; gap:24px; }
.settings-logo-layout > img { width:128px; height:128px; object-fit:contain; border:1px solid #dfe5ec; border-radius:12px; padding:8px; background:#fff; }
.settings-logo-controls { flex:1; min-width:0; }
.settings-logo-controls > label { display:block; font-weight:600; margin-bottom:8px; }
.settings-logo-controls small { display:block; color:#687385; margin-top:7px; }
.settings-logo-controls .settings-card-footer { justify-content:flex-start; border:0; padding:12px 0 0; }
.settings-logo-controls #businessLogoMessage { margin-top:10px; }
.settings-feedback { padding:11px 15px; margin:0 0 18px; border-radius:8px; background:#eaf7ee; color:#17643a; }
.settings-feedback.is-error { background:#fff0f0; color:#a61b1b; }
.settings-feedback[hidden] { display:none; }
body.dark-mode .settings-card, body.dark-mode .settings-navigation { background:#202a36; color:#e8edf4; }
body.dark-mode .settings-nav-link, body.dark-mode .settings-option small, body.dark-mode .settings-panel-header p { color:#b8c2cf; }
body.dark-mode .settings-input { background:#17212c; color:#e8edf4; border-color:#435162; }
body.dark-mode .settings-logo-layout > img { background:#fff; }
body.compact-display .table td, body.compact-display .table th { padding:.35rem .5rem; }
.catalogue-installer-card h3 { margin:0 0 8px; font-size:1.05rem; font-weight:700; }
.catalogue-section-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; }
.catalogue-section-heading p { margin:0 0 8px; color:#6c757d; }
.catalogue-available-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:14px; margin-top:16px; }
.catalogue-available-card { display:flex; flex-direction:column; align-items:flex-start; gap:12px; padding:18px; border:1px solid #e5e7eb; border-radius:10px; }
.catalogue-available-card p { margin:0; color:#6c757d; font-size:.88rem; }
.catalogue-available-card .settings-primary-button { margin-top:auto; }
.catalogue-installed-badge { display:inline-flex; padding:3px 8px; border-radius:999px; background:#e8f5ee; color:#17643a; font-size:.75rem; font-weight:700; }
.catalogue-history-section { margin-top:26px; padding-top:22px; border-top:1px solid #edf0f2; }
.catalogue-history-section h3 { margin-bottom:14px; }
.catalogue-report { margin-top:24px; padding-top:20px; border-top:1px solid #edf0f2; }
.catalogue-report-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.catalogue-report-stat { display:flex; flex-direction:column; gap:4px; padding:16px; border:1px solid #e5e7eb; border-radius:10px; }
.catalogue-report-stat strong { font-size:1.5rem; line-height:1.2; }
.catalogue-report-stat span { color:#6c757d; font-size:.82rem; }
.catalogue-preserved-note { margin:16px 0 0; color:#198754; font-weight:600; }
.catalogue-conflict-list { margin:12px 0 0; padding:14px 14px 14px 34px; border-radius:8px; background:#fff4e5; color:#704d00; }
.catalogue-conflict-list li + li { margin-top:8px; }
.catalogue-report-details { margin-top:20px; }
.catalogue-report-details h4 { margin-top:18px; font-size:.95rem; }
.catalogue-detail-list { margin:10px 0 0; padding-left:22px; }
.catalogue-detail-list li + li { margin-top:6px; }
body.dark-mode .catalogue-report-stat { border-color:#435162; }
body.dark-mode .catalogue-section-heading p, body.dark-mode .catalogue-report-stat span, body.dark-mode .catalogue-available-card p { color:#b8c2cf; }
body.dark-mode .catalogue-available-card { border-color:#435162; }
body.dark-mode .catalogue-installed-badge { background:#254837; color:#b9f0d0; }
@media (max-width: 650px) { .settings-logo-layout { align-items:flex-start; flex-direction:column; } }
@media (max-width: 760px) { .catalogue-report-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>


<?php

include  'INC/footer.php';

?>
<script src="scripts/settings.js?ver=<?= (string) filemtime(__DIR__ . '/scripts/settings.js') ?>"></script>
</body>
</html>
