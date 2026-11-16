<?php
require_once __DIR__ . '/INC/session.php';
stockKeeperStartSession();

include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<div class="content">
    <div class="container-fluid py-3">

        <?php
        $pageHeadingTitle = 'Stock Overview';
        $pageHeadingDescription = 'Monitor your inventory and stock movements.';
        $pageHeadingIcon = 'fa fa-chart-line text-primary';
        $pageHeadingMeta = '<small id="dashboardLastUpdated" class="text-muted">Last updated: --</small>';
        $pageHeadingActions = '<select id="dashboardRangeFilter" class="form-control form-control-sm" style="min-width:140px" aria-label="Dashboard date range"><option value="today">Today</option><option value="7d">Last 7 Days</option><option value="30d">Last 30 Days</option><option value="all" selected>All Time</option></select><div class="form-check form-switch"><input type="checkbox" class="form-check-input" id="dashboardAutoRefreshToggle" checked><label class="form-check-label" for="dashboardAutoRefreshToggle">Auto-refresh</label></div>';
        include __DIR__ . '/INC/page_heading.php';
        unset($pageHeadingTitle, $pageHeadingDescription, $pageHeadingIcon, $pageHeadingMeta, $pageHeadingActions);
        ?>

        <!-- INVENTORY SUMMARY -->
        <div class="row">

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body d-flex
                                justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Products</p>
                            <h3 id="totalProducts" class="mb-0">
                                --
                            </h3>
                            <small class="text-muted">
                                Registered products
                            </small>
                        </div>

                        <div class="dashboard-icon text-primary">
                            <i class="fas fa-boxes fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body d-flex
                                justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Stock Units</p>
                            <h3 id="totalStockUnits" class="mb-0">
                                --
                            </h3>
                            <small class="text-muted">
                                Current quantity on hand
                            </small>
                        </div>

                        <div class="dashboard-icon text-success">
                            <i class="fas fa-cubes fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body d-flex
                                justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Inventory Value</p>
                            <h3 id="inventoryValue" class="mb-0">
                                --
                            </h3>
                            <small class="text-muted">
                                Based on cost price
                            </small>
                        </div>

                        <div class="dashboard-icon text-info">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body d-flex
                                justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Movements</p>
                            <h3 id="totalMovements" class="mb-0">
                                --
                            </h3>
                            <small class="text-muted">
                                For the selected period
                            </small>
                        </div>

                        <div class="dashboard-icon text-secondary">
                            <i class="fas fa-exchange-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- STOCK ALERTS -->
        <div class="row mb-4">

            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex
                                justify-content-between align-items-center">

                        <div>
                            <p class="text-muted mb-1">Low Stock Items</p>
                            <h3 id="lowStockItems"
                                class="text-warning mb-1">--</h3>
                            <small class="text-muted">
                                Items at or below reorder level
                            </small>
                        </div>

                        <i class="fas fa-exclamation-triangle
                                  fa-2x text-warning"></i>
                    </div>

                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="products.php?filter=low-stock"
                           class="small">
                            View low stock items
                            <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex
                                justify-content-between align-items-center">

                        <div>
                            <p class="text-muted mb-1">Out of Stock</p>
                            <h3 id="outOfStockItems"
                                class="text-danger mb-1">--</h3>
                            <small class="text-muted">
                                Products with zero quantity
                            </small>
                        </div>

                        <i class="fas fa-ban fa-2x text-danger"></i>
                    </div>

                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="products.php?filter=out-of-stock"
                           class="small">
                            View out-of-stock items
                            <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- STOCK ACTIVITY -->
        <div class="row mb-4">

            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Stock Activity</h5>
                    </div>

                    <div class="card-body">
                        <div class="d-flex justify-content-between
                                    align-items-center mb-4">

                            <div>
                                <div class="text-muted small">Stock In Today</div>
                                <h4 id="stockInToday"
                                    class="text-success mb-0">--</h4>
                            </div>

                            <i class="fas fa-arrow-circle-down
                                      fa-2x text-success"></i>
                        </div>

                        <div class="d-flex justify-content-between
                                    align-items-center">

                            <div>
                                <div class="text-muted small">Stock Out Today</div>
                                <h4 id="stockOutToday"
                                    class="text-danger mb-0">--</h4>
                            </div>

                            <i class="fas fa-arrow-circle-up
                                      fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOW STOCK PRODUCTS -->
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent
                                d-flex justify-content-between
                                align-items-center">

                        <h5 class="mb-0">Low Stock Products</h5>

                        <a href="products.php?filter=low-stock"
                           class="small">View all</a>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm
                                          table-hover mb-0">

                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Available</th>
                                        <th class="text-end">Reorder At</th>
                                    </tr>
                                </thead>

                                <tbody id="lowStockProductsTable">
                                    <tr>
                                        <td colspan="3"
                                            class="text-center text-muted py-3">
                                            Loading stock alerts...
                                        </td>
                                    </tr>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RECENT STOCK MOVEMENTS -->
        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-transparent
                        d-flex flex-wrap justify-content-between
                        align-items-center">

                <h5 class="mb-0">Recent Stock Movements</h5>

                <a href="stock_movements.php" class="small">
                    View movement history
                    <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="card-body">
                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Movement</th>
                                <th class="text-end">Quantity In</th>
                                <th class="text-end">Quantity Out</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>

                        <tbody id="recentStockMovementsTable">
                            <tr>
                                <td colspan="6"
                                    class="text-center text-muted py-4">
                                    Loading recent stock movements...
                                </td>
                            </tr>
                        </tbody>

                    </table>

                </div>
            </div>
        </div>

    </div>
</div>

<?php
include "INC/footer.php";
?>

</body>
</html>
