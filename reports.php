<?php
declare(strict_types=1);
require_once __DIR__ . '/INC/session.php';
stockKeeperStartSession();
require_once __DIR__ . '/INC/isLogedin.php';
include __DIR__ . '/INC/header.php';
include __DIR__ . '/INC/navbar.php';
?>
<main class="container-fluid py-4" id="reportsPage">
    <?php
    $pageHeadingTitle = 'Reports';
    $pageHeadingDescription = 'Review stock activity, inventory value, and partner accounts.';
    $pageHeadingIcon = 'fa fa-chart-bar text-primary';
    $pageHeadingActions = '<button class="btn btn-outline-primary" type="button" id="exportReportCsv"><i class="fa fa-download me-1"></i>Export CSV</button>';
    include __DIR__ . '/INC/page_heading.php';
    unset($pageHeadingTitle, $pageHeadingDescription, $pageHeadingIcon, $pageHeadingActions);
    ?>

    <div class="card shadow-sm border-0 mb-3"><div class="card-body">
        <form id="reportFilters" class="row align-items-end">
            <div class="form-group col-md-3 mb-2"><label for="reportFromDate">From</label><input type="date" class="form-control" id="reportFromDate"></div>
            <div class="form-group col-md-3 mb-2"><label for="reportToDate">To</label><input type="date" class="form-control" id="reportToDate"></div>
            <div class="form-group col-md-6 mb-2 d-flex"><button class="btn btn-primary me-2" type="submit">Apply dates</button><button class="btn btn-outline-secondary" type="button" id="reportAllDates">All dates</button><small class="text-muted align-self-center ms-auto" id="reportPeriodLabel"></small></div>
        </form>
    </div></div>

    <div id="reportMessage" class="alert alert-danger d-none" role="alert"></div>
    <h5 class="fw-bold mb-3">Activity in selected period</h5>
    <?php
    $activityStats = [
        ['Purchases recorded', 'reportPurchaseValue', '—', '—', 'reportQuantityIn'],
        ['Sales recorded', 'reportSalesValue', '—', '—', 'reportQuantityOut'],
        ['Stock movements', 'reportTransactionCount', '—', '—', 'reportAdjustmentCount'],
        ['Stock takes', 'reportStockTakeCount', '—', '—', 'reportStockTakeChanged'],
    ];
    ?>
    <div class="row mb-4">
        <?php foreach ($activityStats as [$label, $id, $value, $note, $noteId]):
            $statCardLabel = $label; $statCardValueId = $id; $statCardValue = $value;
            $statCardNote = $note; $statCardNoteId = $noteId; $statCardTag = 'div';
            include __DIR__ . '/INC/stat_card.php';
        endforeach; unset($activityStats, $statCardLabel, $statCardValueId, $statCardValue, $statCardNote, $statCardNoteId, $statCardTag); ?>
    </div>

    <h5 class="fw-bold mb-3">Current inventory snapshot <small class="text-muted font-weight-normal">(not limited by selected dates)</small></h5>
    <?php
    $inventoryStats = [
        ['Inventory at current cost', 'reportInventoryCost', '—', 'Uses current product cost, not receipt layers.'],
        ['Potential selling value', 'reportInventorySelling', '—', ''],
        ['Low stock', 'reportLowStock', '—', ''],
        ['Out of stock', 'reportOutOfStock', '—', ''],
    ];
    ?>
    <div class="row mb-4">
        <?php foreach ($inventoryStats as [$label, $id, $value, $note]):
            $statCardLabel = $label; $statCardValueId = $id; $statCardValue = $value;
            $statCardNote = $note; $statCardTag = 'div';
            include __DIR__ . '/INC/stat_card.php';
        endforeach; unset($inventoryStats, $statCardLabel, $statCardValueId, $statCardValue, $statCardNote, $statCardTag); ?>
    </div>

    <div class="row">
        <div class="col-xl-7 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white"><h5 class="mb-0">Product activity</h5></div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Product</th><th class="text-end">Stock in</th><th class="text-end">Purchases</th><th class="text-end">Stock out</th><th class="text-end">Sales</th></tr></thead><tbody id="reportProducts"><tr><td colspan="5" class="text-center text-muted py-4">Loading reports…</td></tr></tbody></table></div></div></div>
        <div class="col-xl-5 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white"><h5 class="mb-0">Partner activity</h5><small class="text-muted">Debits increase the balance owed; credits reduce it or record advances.</small></div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Partner</th><th class="text-end">Customer debits</th><th class="text-end">Customer credits</th><th class="text-end">Supplier debits</th><th class="text-end">Supplier credits</th></tr></thead><tbody id="reportPartners"><tr><td colspan="5" class="text-center text-muted py-4">Loading reports…</td></tr></tbody></table></div></div></div>
    </div>
    <div class="card shadow-sm border-0 mb-4"><div class="card-header bg-white"><h5 class="mb-0">Current partner balances <small class="text-muted font-weight-normal">(all dates)</small></h5></div><div class="card-body"><div class="row text-center"><div class="col-md-3 mb-3 mb-md-0"><small class="text-muted d-block">Customer receivables</small><strong id="reportCustomerDue">—</strong></div><div class="col-md-3 mb-3 mb-md-0"><small class="text-muted d-block">Supplier payables</small><strong id="reportSupplierDue">—</strong></div><div class="col-md-3 mb-3 mb-md-0"><small class="text-muted d-block">Customer advances held</small><strong id="reportCustomerAdvance">—</strong></div><div class="col-md-3"><small class="text-muted d-block">Supplier advances paid</small><strong id="reportSupplierAdvance">—</strong></div></div></div></div>
</main>
<?php include __DIR__ . '/INC/footer.php'; ?>
<script src="scripts/reports.js?v=<?= (string) filemtime(__DIR__ . '/scripts/reports.js') ?>"></script>
</body></html>
