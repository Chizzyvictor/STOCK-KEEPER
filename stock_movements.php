<?php
declare(strict_types=1);
require_once __DIR__ . '/INC/session.php';
stockKeeperStartSession();
require_once __DIR__ . '/INC/isLogedin.php';
include __DIR__ . '/INC/header.php';
include __DIR__ . '/INC/navbar.php';
?>
<main class="container-fluid py-4" id="stockMovementsPage">
    <?php
    $pageHeadingTitle = 'Stock Movements';
    $pageHeadingDescription = 'Review stock changes, partners, balances, and the price at each movement.';
    $pageHeadingIcon = 'fa fa-exchange-alt text-primary';
    $pageHeadingActions = '<a class="btn btn-primary" href="products.php"><i class="fa fa-boxes me-1"></i>Manage Products</a>';
    include __DIR__ . '/INC/page_heading.php';
    unset($pageHeadingTitle, $pageHeadingDescription, $pageHeadingIcon, $pageHeadingActions);
    ?>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form id="movementFilters" class="row align-items-end">
                <div class="form-group col-lg-4 col-md-6 mb-2"><label for="movementSearch">Search</label><input type="search" class="form-control" id="movementSearch" placeholder="Product, SKU, partner, or note"></div>
                <div class="form-group col-lg-2 col-md-6 mb-2"><label for="movementType">Movement</label><select class="form-control" id="movementType"><option value="all">All movements</option><option value="stock_in">Stock In</option><option value="stock_out">Stock Out</option><option value="adjustment">Adjustment / Price change</option><option value="opening">Opening prices</option></select></div>
                <div class="form-group col-lg-2 col-md-4 mb-2"><label for="movementFromDate">From</label><input type="date" class="form-control" id="movementFromDate"></div>
                <div class="form-group col-lg-2 col-md-4 mb-2"><label for="movementToDate">To</label><input type="date" class="form-control" id="movementToDate"></div>
                <div class="form-group col-lg-2 col-md-4 mb-2 d-flex"><button class="btn btn-primary flex-fill me-2" type="submit">Filter</button><button class="btn btn-outline-secondary" type="button" id="clearMovementFilters" aria-label="Clear filters"><i class="fa fa-times"></i></button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-1">Movement Ledger</h5><small id="movementSummary" class="text-muted" aria-live="polite">Loading…</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Movement</th><th>Product</th><th>Partner</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th><th class="text-end">Cost (before → after)</th><th class="text-end">Selling (before → after)</th><th class="text-end">Value</th><th>Note</th></tr></thead>
                <tbody id="movementTableBody"><tr><td colspan="11" class="text-center text-muted py-5">Loading stock movements…</td></tr></tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small id="movementPageLabel" class="text-muted">Page 1 of 1</small>
            <div><button class="btn btn-sm btn-outline-secondary me-1" type="button" id="movementPrevious" disabled>Previous</button><button class="btn btn-sm btn-outline-secondary" type="button" id="movementNext" disabled>Next</button></div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/INC/footer.php'; ?>
<script src="scripts/stock-movements.js?v=<?= (string) filemtime(__DIR__ . '/scripts/stock-movements.js') ?>"></script>
</body></html>
