<?php
declare(strict_types=1);

require_once __DIR__ . '/INC/session.php';
stockKeeperStartSession();
require_once __DIR__ . '/INC/isLogedin.php';

include __DIR__ . '/INC/header.php';
include __DIR__ . '/INC/navbar.php';
?>

<div class="container-fluid py-4 products-page" id="productsPage">

    <?php
    $pageHeadingTitle = 'Products';
    $pageHeadingDescription = 'Manage your products, prices, categories and reorder levels.';
    $pageHeadingIcon = 'fa fa-boxes text-primary';
    $pageHeadingClass = 'products-page-heading';
    $pageHeadingActions = '<button type="button" class="btn btn-outline-secondary shadow-sm" id="openStockTakeHistoryBtn"><i class="fa fa-history me-1"></i>Count History</button><button type="button" class="btn btn-outline-primary shadow-sm" id="openStockTakingBtn" disabled><i class="fa fa-clipboard-check me-1"></i>Physical Stock Take</button><button type="button" class="btn btn-primary shadow-sm" id="openAddProductModal"><i class="fa fa-plus me-1"></i>Add Product</button>';
    include __DIR__ . '/INC/page_heading.php';
    unset($pageHeadingTitle, $pageHeadingDescription, $pageHeadingIcon, $pageHeadingActions, $pageHeadingClass);
    ?>

    <!-- Product summary -->
    <?php
    $productStats = [
        ['Products Found', 'productCount', 'products-stat-card products-stat-card--total', 'fw-bold mb-0'],
        ['Active Products', 'activeProductCount', 'products-stat-card products-stat-card--active', 'fw-bold text-success mb-0'],
        ['Inactive Products', 'inactiveProductCount', 'products-stat-card products-stat-card--inactive', 'fw-bold text-secondary mb-0'],
        ['Matching Products', 'matchingProductCount', 'products-stat-card products-stat-card--matching', 'fw-bold text-primary mb-0'],
    ];
    ?>
    <div class="row mb-4">
        <?php foreach ($productStats as [$label, $id, $cardClass, $valueClass]):
            $statCardLabel = $label; $statCardValueId = $id; $statCardValue = '0';
            $statCardClass = $cardClass . ' border-0 shadow-sm h-100'; $statCardValueClass = $valueClass;
            $statCardTag = 'h3'; $statCardColClass = 'col-sm-6 col-xl-3 mb-3';
            include __DIR__ . '/INC/stat_card.php';
        endforeach; unset($productStats, $statCardLabel, $statCardValueId, $statCardValue, $statCardClass, $statCardValueClass, $statCardTag, $statCardColClass); ?>
    </div>


    <!-- Product list -->
    <div class="card products-list-card border-0 shadow-sm">
        <div class="card-header products-list-toolbar bg-white py-3">
            <div class="row align-items-center">
                <div class="col-lg-5 mb-2 mb-lg-0">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                        </div>
                        <input type="search" class="form-control"
                               id="productSearchInput"
                               placeholder="Search product name or SKU..."
                               autocomplete="off">
                    </div>
                </div>

                <div class="col-lg-4 mb-2 mb-lg-0">
                    <select class="form-control" id="productCategoryFilter">
                        <option value="">All categories</option>
                    </select>
                </div>

                <div class="col-lg-3">
                    <select class="form-control" id="productStatusFilter">
                        <option value="">All statuses</option>
                        <option value="1">Active only</option>
                        <option value="0">Inactive only</option>
                    </select>
                </div>

                <div class="col-lg-5 mt-2">
                    <label class="visually-hidden" for="productSortSelect">Sort products</label>
                    <select class="form-control" id="productSortSelect">
                        <option value="name-asc">Sort: Name (A–Z)</option>
                        <option value="name-desc">Sort: Name (Z–A)</option>
                        <option value="price-asc">Sort: Selling price (low to high)</option>
                        <option value="price-desc">Sort: Selling price (high to low)</option>
                        <option value="stock-asc">Sort: Stock (low to high)</option>
                        <option value="stock-desc">Sort: Stock (high to low)</option>
                    </select>
                </div>

                <div class="col-lg-7 mt-2 d-flex align-items-center justify-content-lg-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            id="clearProductFiltersButton">
                        <i class="fa fa-times-circle me-1"></i> Clear filters
                    </button>
                    <small class="text-muted ms-3" id="productFilterSummary" aria-live="polite">
                        Showing all products
                    </small>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody id="productsTableBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            Loading products...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer products-list-footer bg-white d-flex justify-content-between
                    align-items-center flex-wrap">
            <small class="text-muted" id="productsListMessage">
                Loading product list...
            </small>

            <button type="button" class="btn btn-sm btn-outline-secondary"
                    id="refreshProductsButton">
                <i class="fa fa-refresh me-1"></i> Refresh
            </button>
        </div>
    </div>

</div>

<div class="modal fade" id="stockTakingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <div><h5 class="modal-title fw-bold"><i class="fa fa-clipboard-check me-2 text-primary"></i>Physical Stock Take</h5><small class="text-muted">Count each listed product. Stock or price ledger changes after this view loads require a refresh and recount.</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stockTakingForm">
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom">
                        <div class="row">
                            <div class="form-group col-md-4 mb-2"><label class="small mb-1" for="stockTakeName">Count name</label><input type="text" id="stockTakeName" class="form-control form-control-sm" maxlength="100" placeholder="e.g. October monthly count"></div>
                            <div class="form-group col-md-8 mb-2"><label class="small mb-1" for="stockTakeNotes">Notes (optional)</label><input type="text" id="stockTakeNotes" class="form-control form-control-sm" maxlength="500" placeholder="Location, team, or audit notes"></div>
                            <div class="col-12"><input type="search" class="form-control form-control-sm" id="stockTakingSearch" placeholder="Filter products while counting…" autocomplete="off" aria-label="Filter products"></div>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height:60vh;overflow-y:auto;">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light" style="position:sticky;top:0;z-index:2;"><tr><th>Product</th><th>SKU</th><th class="text-end">System Stock</th><th style="width:180px;">Physical Count *</th><th>Variance</th></tr></thead>
                            <tbody id="stockTakingTableBody"><tr><td colspan="5" class="text-center text-muted py-4">Loading products…</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between">
                    <span class="text-muted small" id="stockTakingSummary" aria-live="polite">Enter a physical count for every product.</span>
                    <div><button type="button" class="btn btn-outline-secondary me-1" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary shadow-sm" id="saveStockTakeBtn" disabled>Complete &amp; Reconcile</button></div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="stockTakeHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Previous Stock Takes</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Date</th><th>Count</th><th class="text-end">Products counted</th><th class="text-end">Adjusted</th><th></th></tr></thead><tbody id="stockTakeHistoryBody"></tbody></table></div>
        <div class="modal-footer"><small class="text-muted me-auto">Balanced counts are retained even when no stock adjustment was needed.</small><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<div class="modal fade" id="stockTakeDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="stockTakeDetailsTitle">Stock Take Details</h5><small id="stockTakeDetailsNotes" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Product</th><th>SKU</th><th class="text-end">System quantity</th><th class="text-end">Counted quantity</th><th class="text-end">Variance</th><th>Result</th></tr></thead><tbody id="stockTakeDetailsBody"></tbody></table></div>
    </div></div>
</div>

<?php
include __DIR__ . '/INC/footer.php';
?>
<script src="scripts/products.js?v=<?= (string) filemtime(__DIR__ . '/scripts/products.js') ?>"></script>
</body>
</html>
