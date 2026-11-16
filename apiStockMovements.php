<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';


function stockMovementResponse(string $status, string $text, array $data = [], int $code = 200): void
{
    apiRespond($status, $text, $data, $code);
}

$cid = (int) $_SESSION['cid'];

require_once __DIR__ . '/INC/schema_extensions.php';

try {
    $db = stockKeeperOpenDatabase();
    ensureStockKeeperSchemaExtensions($db);

    $action = apiInput('action');
    if ($action !== 'list') {
        stockMovementResponse('error', 'Unknown stock movements action.', [], 400);
    }

    $search = substr(trim((string)($_POST['search'] ?? '')), 0, 120);
    $type = trim((string)($_POST['type'] ?? 'all'));
    $from = trim((string)($_POST['from_date'] ?? ''));
    $to = trim((string)($_POST['to_date'] ?? ''));
    $page = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 25;
    $allowedTypes = ['all', 'stock_in', 'stock_out', 'adjustment', 'opening'];
    $validDate = static fn(string $value): bool => apiValidDate($value);
    if (!in_array($type, $allowedTypes, true) || !$validDate($from) || !$validDate($to)) {
        stockMovementResponse('error', 'Choose a valid movement type and date range.', [], 422);
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        stockMovementResponse('error', 'The start date must be on or before the end date.', [], 422);
    }

    $conditions = ['l.cid = :cid'];
    $params = ['cid'=>$cid];
    if ($type !== 'all') {
        $conditions[] = 'l.reference_type = :type';
        $params['type'] = $type;
    }
    if ($from !== '') {
        $conditions[] = 'substr(l.created_at, 1, 10) >= :from_date';
        $params['from_date'] = $from;
    }
    if ($to !== '') {
        $conditions[] = 'substr(l.created_at, 1, 10) <= :to_date';
        $params['to_date'] = $to;
    }
    if ($search !== '') {
        $conditions[] = '(p.product_name LIKE :search OR p.sku LIKE :search OR pt.name LIKE :search OR l.note LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    $where = implode(' AND ', $conditions);

    $bind = static function (SQLite3Stmt $stmt, array $values): void {
        foreach ($values as $key => $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT : SQLITE3_TEXT);
            $stmt->bindValue(':' . $key, $value, $type);
        }
    };
    $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM stock_ledger l INNER JOIN products p ON p.product_id = l.product_id AND p.cid = l.cid LEFT JOIN partners pt ON pt.partner_id = l.partner_id AND pt.cid = l.cid WHERE {$where}");
    $bind($countStmt, $params);
    $countResult = $countStmt->execute();
    $countRow = $countResult->fetchArray(SQLITE3_ASSOC);
    $total = (int)($countRow['total'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $params['limit'] = $perPage;
    $params['offset'] = ($page - 1) * $perPage;

    $stmt = $db->prepare("SELECT l.ledger_id, l.reference_type, l.qty_in, l.qty_out,
            l.balance_after, l.unit_cost, l.unit_selling_price,
            l.previous_cost_price, l.previous_selling_price,
            l.total_amount, l.note, l.created_at,
            p.product_id, p.product_name, p.sku, p.product_unit,
            pt.partner_id, pt.name AS partner_name
        FROM stock_ledger l
        INNER JOIN products p ON p.product_id = l.product_id AND p.cid = l.cid
        LEFT JOIN partners pt ON pt.partner_id = l.partner_id AND pt.cid = l.cid
        WHERE {$where}
        ORDER BY l.created_at DESC, l.ledger_id DESC
        LIMIT :limit OFFSET :offset");
    $bind($stmt, $params);
    $result = $stmt->execute();
    $movements = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $movements[] = $row;
    }

    stockMovementResponse('success', 'Stock movements loaded.', [
        'movements'=>$movements,
        'page'=>$page,
        'per_page'=>$perPage,
        'total'=>$total,
        'total_pages'=>$totalPages,
    ]);
} catch (Throwable $error) {
    error_log('apiStockMovements.php: ' . $error->getMessage());
    stockMovementResponse('error', 'The stock movements service encountered a server error.', [], 500);
}
