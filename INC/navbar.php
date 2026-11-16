<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$pageTitles = [
    'index.php'           => 'Dashboard',
    'products.php'        => 'Products',
    'partners.php'        => 'Partners',
    'stock_movements.php' => 'Stock Movements',
    'settings.php'        => 'Settings',
    'stock.php'           => 'Stock',
    'reports.php'         => 'Reports',
];

$pageTitle = $pageTitles[$currentPage] ?? 'Stock Keeper';
?>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container-fluid">

        <!-- CURRENT PAGE TITLE / BRAND -->
        <a class="navbar-brand fw-bold" href="index.php">
            <?php echo htmlspecialchars($pageTitle); ?>
        </a>

        <!-- TOGGLER -->
        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarMenu"
                aria-controls="navbarMenu"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- MENU ITEMS -->
        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>"
                       href="index.php">
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'products.php' ? 'active' : ''; ?>"
                       href="products.php">
                        Products
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'partners.php' ? 'active' : ''; ?>"
                       href="partners.php">
                        Partners
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'stock_movements.php' ? 'active' : ''; ?>"
                       href="stock_movements.php">
                        Stock Movements
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>"
                       href="reports.php">
                        Reports
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>"
                       href="settings.php">
                        Settings
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link text-danger"
                       href="#"
                       id="logout">
                        Logout
                    </a>
                </li>

            </ul>
        </div>

    </div>
</nav>