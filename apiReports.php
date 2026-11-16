<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

function reportsRespond(string $status, string $text, array $data = [], int $code = 200): void
{
    apiRespond($status, $text, $data, $code);
}

$cid = (int) $_SESSION['cid'];
$from = apiInput('from_date');
$to = apiInput('to_date');
if (!apiValidDate($from) || !apiValidDate($to) || ($from !== '' && $to !== '' && $from > $to)) {
    reportsRespond('error', 'Choose a valid date range.', [], 422);
}


try {
    require_once __DIR__ . '/INC/schema_extensions.php';
    $db = stockKeeperOpenDatabase();
    ensureStockKeeperSchemaExtensions($db);

    $bind = static function (SQLite3Stmt $stmt, array $params): void {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT : SQLITE3_TEXT);
            $stmt->bindValue(':' . $key, $value, $type);
        }
    };
    $one = static function (string $sql, array $params = []) use ($db, $bind): array {
        $stmt = $db->prepare($sql);
        $bind($stmt, $params);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC) ?: [];
        $result->finalize();
        return $row;
    };
    $all = static function (string $sql, array $params = []) use ($db, $bind): array {
        $stmt = $db->prepare($sql);
        $bind($stmt, $params);
        $result = $stmt->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
        $result->finalize();
        return $rows;
    };
    $conditions = ['cid = :cid'];
    $params = ['cid'=>$cid];
    if ($from !== '') { $conditions[] = "substr(created_at, 1, 10) >= :from_date"; $params['from_date'] = $from; }
    if ($to !== '') { $conditions[] = "substr(created_at, 1, 10) <= :to_date"; $params['to_date'] = $to; }
    $dateWhere = implode(' AND ', $conditions);
    $ledgerConditions = ['l.cid = :cid'];
    $partnerConditions = ['l.cid = :cid'];
    $stockTakeConditions = ['cid = :cid'];
    if ($from !== '') {
        $ledgerConditions[] = 'substr(l.created_at, 1, 10) >= :from_date';
        $partnerConditions[] = 'substr(l.created_at, 1, 10) >= :from_date';
        $stockTakeConditions[] = 'substr(created_at, 1, 10) >= :from_date';
    }
    if ($to !== '') {
        $ledgerConditions[] = 'substr(l.created_at, 1, 10) <= :to_date';
        $partnerConditions[] = 'substr(l.created_at, 1, 10) <= :to_date';
        $stockTakeConditions[] = 'substr(created_at, 1, 10) <= :to_date';
    }
    $ledgerDateWhere = implode(' AND ', $ledgerConditions);
    $partnerDateWhere = implode(' AND ', $partnerConditions);
    $stockTakeDateWhere = implode(' AND ', $stockTakeConditions);

    $movement = $one("SELECT
        COALESCE(SUM(CASE WHEN reference_type = 'stock_in' THEN qty_in ELSE 0 END),0) AS quantity_in,
        COALESCE(SUM(CASE WHEN reference_type = 'stock_out' THEN qty_out ELSE 0 END),0) AS quantity_out,
        COALESCE(SUM(CASE WHEN reference_type = 'stock_in' THEN total_amount ELSE 0 END),0) AS purchase_value,
        COALESCE(SUM(CASE WHEN reference_type = 'stock_out' THEN total_amount ELSE 0 END),0) AS sales_value,
        COALESCE(SUM(CASE WHEN reference_type = 'adjustment' THEN 1 ELSE 0 END),0) AS adjustments,
        COALESCE(SUM(CASE WHEN reference_type IN ('stock_in','stock_out') THEN 1 ELSE 0 END),0) AS transactions
        FROM stock_ledger WHERE {$dateWhere}", $params);

    $inventory = $one("SELECT COUNT(*) AS product_count,
        COALESCE(SUM(COALESCE(i.quantity,0) * p.cost_price),0) AS cost_value,
        COALESCE(SUM(COALESCE(i.quantity,0) * p.selling_price),0) AS selling_value,
        COALESCE(SUM(CASE WHEN COALESCE(i.quantity,0) <= 0 THEN 1 ELSE 0 END),0) AS out_of_stock,
        COALESCE(SUM(CASE WHEN COALESCE(i.quantity,0) > 0 AND COALESCE(i.quantity,0) <= p.reorder_level THEN 1 ELSE 0 END),0) AS low_stock
        FROM products p LEFT JOIN inventory i ON i.product_id = p.product_id AND i.cid = p.cid
        WHERE p.cid = :cid AND p.is_active = 1", ['cid'=>$cid]);

    $balances = $one("SELECT
        COALESCE(SUM(customer_outstanding),0) AS customer_outstanding,
        COALESCE(SUM(supplier_outstanding),0) AS supplier_outstanding,
        COALESCE(SUM(customer_advance),0) AS customer_advance,
        COALESCE(SUM(supplier_advance),0) AS supplier_advance
        FROM partners WHERE cid = :cid AND deleted_at IS NULL", ['cid'=>$cid]);

    $productRows = $all("SELECT p.product_id, p.product_name, p.sku, p.product_unit,
        SUM(CASE WHEN l.reference_type = 'stock_in' THEN l.qty_in ELSE 0 END) AS quantity_in,
        SUM(CASE WHEN l.reference_type = 'stock_out' THEN l.qty_out ELSE 0 END) AS quantity_out,
        SUM(CASE WHEN l.reference_type = 'stock_in' THEN l.total_amount ELSE 0 END) AS purchase_value,
        SUM(CASE WHEN l.reference_type = 'stock_out' THEN l.total_amount ELSE 0 END) AS sales_value,
        COUNT(l.ledger_id) AS movement_count
        FROM stock_ledger l INNER JOIN products p ON p.product_id = l.product_id AND p.cid = l.cid
        WHERE {$ledgerDateWhere}
        GROUP BY p.product_id, p.product_name, p.sku, p.product_unit
        ORDER BY (purchase_value + sales_value) DESC, p.product_name COLLATE NOCASE LIMIT 100", $params);

    $partnerRows = $all("SELECT p.partner_id, p.name,
        SUM(CASE WHEN l.account_type = 'customer' AND l.balance_effect > 0 THEN l.balance_effect ELSE 0 END) AS customer_debits,
        SUM(CASE WHEN l.account_type = 'customer' AND l.balance_effect < 0 THEN -l.balance_effect ELSE 0 END) AS customer_credits,
        SUM(CASE WHEN l.account_type = 'supplier' AND l.balance_effect > 0 THEN l.balance_effect ELSE 0 END) AS supplier_debits,
        SUM(CASE WHEN l.account_type = 'supplier' AND l.balance_effect < 0 THEN -l.balance_effect ELSE 0 END) AS supplier_credits,
        SUM(l.amount) AS activity_value
        FROM partner_ledger l INNER JOIN partners p ON p.partner_id = l.partner_id AND p.cid = l.cid
        WHERE {$partnerDateWhere} AND p.deleted_at IS NULL
        GROUP BY p.partner_id, p.name
        ORDER BY activity_value DESC, p.name COLLATE NOCASE LIMIT 100", $params);

    $stockTakes = $one("SELECT COUNT(*) AS count,
        COALESCE(SUM(total_products),0) AS products_counted,
        COALESCE(SUM(changed_products),0) AS changed_products
        FROM stock_take_sessions WHERE {$stockTakeDateWhere}", $params);

    reportsRespond('success', 'Reports loaded.', [
        'from_date'=>$from, 'to_date'=>$to,
        'movements'=>$movement,
        'inventory'=>$inventory,
        'balances'=>$balances,
        'products'=>$productRows,
        'partners'=>$partnerRows,
        'stock_takes'=>$stockTakes,
    ]);
} catch (Throwable $error) {
    error_log('apiReports.php: ' . $error->getMessage());
    reportsRespond('error', 'Unable to load reports right now.', [], 500);
}
