<?php

declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';


header('Cache-Control: no-store, no-cache, must-revalidate');

date_default_timezone_set('Africa/Lagos');

/**
 * Stock Keeper — Dashboard API
 *
 * Database: configured by STOCK_KEEPER_DB_PATH (project-local fallback for development)
 * Authentication: existing company session
 * Action: loadDashboard
 */

function respond(string $status, string $text = '', array $extra = [], int $httpCode = 200): void
{
    $data = isset($extra['data']) && is_array($extra['data']) ? $extra['data'] : $extra;
    apiRespond($status, $text, $data, $httpCode);
}

/**
 * Run a prepared query with named parameters.
 */
function dashboardRow(
    SQLite3 $db,
    string $sql,
    array $params = []
): array {
    $result = apiQuery($db, $sql, $params);
    $row = $result->fetchArray(SQLITE3_ASSOC);

    $result->finalize();

    return $row ?: [];
}

/**
 * Return a single numeric result.
 */
function dashboardNumber(
    SQLite3 $db,
    string $sql,
    array $params = []
): float {
    $row = dashboardRow($db, $sql, $params);

    return (float) ($row['value'] ?? 0);
}


if (($_POST['action'] ?? '') !== 'loadDashboard') {
    respond('error', 'Unknown dashboard action.');
}

$cid = (int) $_SESSION['cid'];

// --------------------------------------------------
// 3. DATE FILTER
// --------------------------------------------------

$range = $_POST['range'] ?? 'all';

if (!in_array($range, ['today', '7d', '30d', 'all'], true)) {
    respond('error', 'Invalid dashboard date range.');
}

$lagos = new DateTimeZone('Africa/Lagos');
$utc = new DateTimeZone('UTC');

$nowUtc = new DateTimeImmutable('now', $utc);

$todayStart = new DateTimeImmutable('today', $lagos);
$todayStartUtc = $todayStart->setTimezone($utc);

$todayStartSql = $todayStartUtc->format('Y-m-d H:i:s');
$nowUtcSql = $nowUtc->format('Y-m-d H:i:s');

$rangeStartSql = null;

if ($range === 'today') {
    $rangeStartSql = $todayStartSql;
} elseif ($range === '7d') {
    $rangeStartSql = $todayStart
        ->modify('-6 days')
        ->setTimezone($utc)
        ->format('Y-m-d H:i:s');
} elseif ($range === '30d') {
    $rangeStartSql = $todayStart
        ->modify('-29 days')
        ->setTimezone($utc)
        ->format('Y-m-d H:i:s');
}


// --------------------------------------------------
// 4. DATABASE CONNECTION
// --------------------------------------------------

$db = null;

try {
    $db = stockKeeperOpenDatabase();

    // Confirm that the authenticated company still exists.
    $company = dashboardRow(
        $db,
        'SELECT cid FROM company WHERE cid = :cid LIMIT 1',
        [':cid' => $cid]
    );

    if (!$company) {
        session_unset();
        session_destroy();

        respond('error', 'Your business account could not be found.');
    }

    // Record valid authenticated activity.
    $_SESSION['last_activity'] = time();


    // --------------------------------------------------
    // 5. CURRENT INVENTORY SUMMARY
    // These figures do not change with the date filter.
    // --------------------------------------------------

    $totalProducts = dashboardNumber(
        $db,
        "SELECT COUNT(*) AS value
         FROM products
         WHERE cid = :cid
           AND is_active = 1",
        [':cid' => $cid]
    );

    $inventorySummary = dashboardRow(
        $db,
        "SELECT
            COALESCE(SUM(COALESCE(i.quantity, 0)), 0)
                AS total_units,

            COALESCE(
                SUM(COALESCE(i.quantity, 0) * p.cost_price),
                0
            ) AS inventory_value

         FROM products p

         LEFT JOIN inventory i
           ON i.product_id = p.product_id
          AND i.cid = p.cid

         WHERE p.cid = :cid
           AND p.is_active = 1",
        [':cid' => $cid]
    );

    $totalStockUnits = (float) (
        $inventorySummary['total_units'] ?? 0
    );

    $inventoryValue = (float) (
        $inventorySummary['inventory_value'] ?? 0
    );


    // --------------------------------------------------
    // 6. STOCK ALERTS
    // Low stock excludes products already out of stock.
    // --------------------------------------------------

    $outOfStockItems = dashboardNumber(
        $db,
        "SELECT COUNT(*) AS value

         FROM products p

         LEFT JOIN inventory i
           ON i.product_id = p.product_id
          AND i.cid = p.cid

         WHERE p.cid = :cid
           AND p.is_active = 1
           AND COALESCE(i.quantity, 0) <= 0",
        [':cid' => $cid]
    );

    $lowStockCondition = "
        p.cid = :cid
        AND p.is_active = 1
        AND p.reorder_level > 0
        AND COALESCE(i.quantity, 0) > 0
        AND COALESCE(i.quantity, 0) <= p.reorder_level
    ";

    $lowStockItems = dashboardNumber(
        $db,
        "SELECT COUNT(*) AS value

         FROM products p

         LEFT JOIN inventory i
           ON i.product_id = p.product_id
          AND i.cid = p.cid

         WHERE $lowStockCondition",
        [':cid' => $cid]
    );

    $lowStockResult = apiQuery(
        $db,
        "SELECT
            p.product_id,
            p.product_name,
            p.product_unit,
            p.reorder_level,
            COALESCE(i.quantity, 0) AS quantity

         FROM products p

         LEFT JOIN inventory i
           ON i.product_id = p.product_id
          AND i.cid = p.cid

         WHERE $lowStockCondition

         ORDER BY
            COALESCE(i.quantity, 0) ASC,
            p.product_name ASC

         LIMIT 8",
        [':cid' => $cid]
    );

    $lowStockProducts = [];

    while ($row = $lowStockResult->fetchArray(SQLITE3_ASSOC)) {
        $row['product_id'] = (int) $row['product_id'];
        $row['quantity'] = (float) $row['quantity'];
        $row['reorder_level'] = (float) $row['reorder_level'];

        $lowStockProducts[] = $row;
    }

    $lowStockResult->finalize();


    // --------------------------------------------------
    // 7. STOCK ACTIVITY FOR TODAY
    // Quantities, not movement counts.
    // --------------------------------------------------

    $todayActivity = dashboardRow(
        $db,
        "SELECT
            COALESCE(SUM(qty_in), 0) AS stock_in,
            COALESCE(SUM(qty_out), 0) AS stock_out

         FROM stock_ledger

         WHERE cid = :cid
           AND created_at >= :start
           AND created_at <= :now",
        [
            ':cid' => $cid,
            ':start' => $todayStartSql,
            ':now' => $nowUtcSql,
        ]
    );

    $stockInToday = (float) ($todayActivity['stock_in'] ?? 0);
    $stockOutToday = (float) ($todayActivity['stock_out'] ?? 0);


    // --------------------------------------------------
    // 8. MOVEMENT COUNT FOR SELECTED DATE RANGE
    // --------------------------------------------------

    $movementSql = "
        SELECT COUNT(*) AS value
        FROM stock_ledger
        WHERE cid = :cid
          AND reference_type <> 'opening'
    ";

    $movementParams = [':cid' => $cid];

    if ($rangeStartSql !== null) {
        $movementSql .= ' AND created_at >= :start
                          AND created_at <= :now';

        $movementParams[':start'] = $rangeStartSql;
        $movementParams[':now'] = $nowUtcSql;
    }

    $totalMovements = dashboardNumber(
        $db,
        $movementSql,
        $movementParams
    );


    // --------------------------------------------------
    // 9. RECENT STOCK MOVEMENTS
    // Follows the selected date range.
    // --------------------------------------------------

    $recentSql = "
        SELECT
            l.ledger_id,
            l.product_id,
            p.product_name,
            p.product_unit,
            l.reference_type,
            l.qty_in,
            l.qty_out,
            l.balance_after,
            l.note,
            l.created_at

        FROM stock_ledger l

        INNER JOIN products p
          ON p.product_id = l.product_id
         AND p.cid = l.cid

        WHERE l.cid = :cid
          AND l.reference_type <> 'opening'
    ";

    $recentParams = [':cid' => $cid];

    if ($rangeStartSql !== null) {
        $recentSql .= ' AND l.created_at >= :start
                        AND l.created_at <= :now';

        $recentParams[':start'] = $rangeStartSql;
        $recentParams[':now'] = $nowUtcSql;
    }

    $recentSql .= "
        ORDER BY l.created_at DESC, l.ledger_id DESC
        LIMIT 10
    ";

    $recentResult = apiQuery(
        $db,
        $recentSql,
        $recentParams
    );

    $recentStockMovements = [];

    while ($row = $recentResult->fetchArray(SQLITE3_ASSOC)) {
        $row['ledger_id'] = (int) $row['ledger_id'];
        $row['product_id'] = (int) $row['product_id'];
        $row['qty_in'] = (float) $row['qty_in'];
        $row['qty_out'] = (float) $row['qty_out'];
        $row['balance_after'] = (float) $row['balance_after'];

        $recentStockMovements[] = $row;
    }

    $recentResult->finalize();


    // --------------------------------------------------
    // 10. RESPONSE
    // --------------------------------------------------

    $data = [
        'totalProducts' => (int) $totalProducts,
        'totalStockUnits' => $totalStockUnits,
        'inventoryValue' => $inventoryValue,
        'totalMovements' => (int) $totalMovements,
        'lowStockItems' => (int) $lowStockItems,
        'outOfStockItems' => (int) $outOfStockItems,
        'stockInToday' => $stockInToday,
        'stockOutToday' => $stockOutToday,
        'lowStockProducts' => $lowStockProducts,
        'recentStockMovements' => $recentStockMovements,
        'range' => $range,
        'generatedAt' => $nowUtc->format(DateTimeInterface::ATOM),
    ];

    $db->close();

    respond('success', 'Dashboard loaded successfully.', [
        'data' => $data,
    ]);

} catch (Throwable $e) {
    if ($db instanceof SQLite3) {
        $db->close();
    }

    error_log('Stock Keeper dashboard API: ' . $e->getMessage());

    respond(
        'error',
        'Unable to load dashboard data. Check the PHP error log.',
        [],
        500
    );
}
