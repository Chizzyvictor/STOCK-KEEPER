<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';



function productResponse(string $status, string $text, array $data = [], int $code = 200): void
{
    apiRespond($status, $text, $data, $code);
}

function productBool(string $value): int
{
    return in_array(strtolower($value), ['1', 'active', 'true'], true)
        ? 1
        : 0;
}

$cid = (int) $_SESSION['cid'];

/* Open the existing SQLite database. */
try {
    $db = stockKeeperOpenDatabase();
    require_once __DIR__ . '/INC/schema_extensions.php';
    ensureStockKeeperSchemaExtensions($db);

    $action = apiInput('action');

    switch ($action) {

        /* Load active categories belonging to this company. */
        case 'listCategories':
            $result = apiQuery(
                $db,
                'SELECT category_id, category_name
                 FROM product_categories
                 WHERE cid = :cid AND is_active = 1
                 ORDER BY category_name ASC',
                ['cid' => $cid]
            );

            productResponse(
                'success',
                'Categories loaded.',
                ['categories' => apiFetchAll($result)]
            );
            break;

        /* Load products and their current stock. */
        case 'listProducts':
            $result = apiQuery(
                $db,
                "SELECT
                    p.product_id,
                    p.cid,
                    p.category_id,
                    p.sku,
                    p.product_name,
                    p.product_unit,
                    p.cost_price,
                    p.selling_price,
                    p.reorder_level,
                    p.is_active,
                    p.created_at,
                    COALESCE(pc.category_name, 'Uncategorized')
                        AS category_name,
                    COALESCE(i.quantity, 0) AS quantity,
                    COALESCE((SELECT MAX(l.ledger_id) FROM stock_ledger l
                        WHERE l.product_id = p.product_id AND l.cid = p.cid), 0) AS latest_ledger_id
                 FROM products p
                 LEFT JOIN product_categories pc
                    ON pc.category_id = p.category_id
                    AND pc.cid = p.cid
                 LEFT JOIN inventory i
                    ON i.product_id = p.product_id
                    AND i.cid = p.cid
                 WHERE p.cid = :cid
                 ORDER BY p.product_id DESC",
                ['cid' => $cid]
            );

            productResponse(
                'success',
                'Products loaded.',
                ['products' => apiFetchAll($result)]
            );
            break;

        /* Add a product or update an existing one. */
        case 'saveProduct':
            $productIdRaw = apiInput('product_id', '0');
            if (!preg_match('/^\d+$/', $productIdRaw)) productResponse('error', 'Invalid product selection.');
            $productId = (int)$productIdRaw;
            $name = apiInput('product_name');
            $sku = apiInput('sku');
            $unit = apiInput('product_unit');
            $categoryRaw = apiInput('category_id', '0');
            $costRaw = apiInput('cost_price', '0');
            $sellingRaw = apiInput('selling_price', '0');
            $reorderRaw = apiInput('reorder_level', '0');
            $active = productBool(apiInput('is_active', '1'));

            if ($productId === 0 && ($unit === '' || $reorderRaw === '')) {
                $defaults = apiQuery(
                    $db,
                    'SELECT setting_key, setting_value FROM company_settings
                     WHERE cid = :cid AND setting_key IN (\'default_unit\', \'default_reorder_level\')',
                    ['cid' => $cid]
                );
                $defaultValues = [];
                while ($defaultRow = $defaults->fetchArray(SQLITE3_ASSOC)) {
                    $defaultValues[$defaultRow['setting_key']] = $defaultRow['setting_value'];
                }
                if ($unit === '') $unit = (string)($defaultValues['default_unit'] ?? 'pcs');
                if ($reorderRaw === '') $reorderRaw = (string)($defaultValues['default_reorder_level'] ?? '0');
            }

            if ($name === '' || strlen($name) > 800) {
                productResponse('error', 'Product name is required and must not exceed 200 characters.');
            }

            if ($sku === '' || strlen($sku) > 100) {
                productResponse('error', 'Product code is required and must not exceed 100 characters.');
            }

            if ($unit === '' || strlen($unit) > 120) {
                productResponse('error', 'Please select a valid product unit.');
            }

            if (
                !apiValidMoney($costRaw)
                || !apiValidMoney($sellingRaw)
                || !apiValidQuantity($reorderRaw)
            ) {
                productResponse(
                    'error',
                    'Cost price, selling price and reorder level must be valid non-negative numbers.'
                );
            }

            $categoryId = filter_var($categoryRaw, FILTER_VALIDATE_INT);

            if ($categoryId === false || $categoryId < 0) {
                productResponse('error', 'Invalid product category.');
            }

            if ($categoryId > 0) {
                $categoryCheck = apiQuery(
                    $db,
                    'SELECT category_id
                     FROM product_categories
                     WHERE category_id = :category_id
                       AND cid = :cid
                       AND is_active = 1
                     LIMIT 1',
                    [
                        'category_id' => $categoryId,
                        'cid' => $cid
                    ]
                );

                if (!$categoryCheck->fetchArray(SQLITE3_ASSOC)) {
                    productResponse(
                        'error',
                        'Selected category is invalid or inactive.'
                    );
                }
            } else {
                $categoryId = null;
            }

            /* Prevent duplicate SKU within this company. */
            $duplicate = apiQuery(
                $db,
                'SELECT product_id
                 FROM products
                 WHERE cid = :cid
                   AND LOWER(sku) = LOWER(:sku)
                   AND product_id != :product_id
                 LIMIT 1',
                [
                    'cid' => $cid,
                    'sku' => $sku,
                    'product_id' => $productId
                ]
            );

            if ($duplicate->fetchArray(SQLITE3_ASSOC)) {
                productResponse(
                    'error',
                    'This SKU already exists. Please use a unique SKU.'
                );
            }

            $cost = (float)$costRaw;
            $selling = (float)$sellingRaw;
            $reorder = (float)$reorderRaw;

            $db->exec('BEGIN IMMEDIATE');

            try {
                if ($productId > 0) {
                    /* Confirm the product belongs to this company. */
                    $existing = apiQuery(
                        $db,
                        'SELECT p.product_id, p.cost_price, p.selling_price,
                                COALESCE(i.quantity, 0) AS quantity
                         FROM products p
                         LEFT JOIN inventory i
                           ON i.product_id = p.product_id AND i.cid = p.cid
                         WHERE p.product_id = :product_id
                           AND p.cid = :cid
                         LIMIT 1',
                        [
                            'product_id' => $productId,
                            'cid' => $cid
                        ]
                    );

                    $existingRow = $existing->fetchArray(SQLITE3_ASSOC);
                    if (!$existingRow) {
                        throw new InvalidArgumentException(
                            'Product not found or access denied.'
                        );
                    }

                    apiQuery(
                        $db,
                        'UPDATE products SET
                            product_name = :name,
                            sku = :sku,
                            category_id = :category_id,
                            product_unit = :unit,
                            cost_price = :cost,
                            selling_price = :selling,
                            reorder_level = :reorder,
                            is_active = :active
                         WHERE product_id = :product_id
                           AND cid = :cid',
                        [
                            'name' => $name,
                            'sku' => $sku,
                            'category_id' => $categoryId,
                            'unit' => $unit,
                            'cost' => $cost,
                            'selling' => $selling,
                            'reorder' => $reorder,
                            'active' => $active,
                            'product_id' => $productId,
                            'cid' => $cid
                        ]
                    );

                    if (
                        abs((float)$existingRow['cost_price'] - $cost) > 0.000001
                        || abs((float)$existingRow['selling_price'] - $selling) > 0.000001
                    ) {
                        apiQuery(
                            $db,
                            'INSERT INTO stock_ledger (
                                product_id, cid, reference_type, qty_in, qty_out,
                                balance_after, unit_price, total_amount, unit_cost,
                                unit_selling_price, previous_cost_price,
                                previous_selling_price, note, created_at
                             ) VALUES (
                                :product_id, :cid, :reference_type, 0, 0,
                                :balance_after, 0, 0, :unit_cost,
                                :unit_selling_price, :previous_cost_price,
                                :previous_selling_price, :note, :created_at
                             )',
                            [
                                'product_id' => $productId, 'cid' => $cid,
                                'reference_type' => 'adjustment',
                                'balance_after' => (float)$existingRow['quantity'],
                                'unit_cost' => $cost,
                                'unit_selling_price' => $selling,
                                'previous_cost_price' => (float)$existingRow['cost_price'],
                                'previous_selling_price' => (float)$existingRow['selling_price'],
                                'note' => 'Product price updated',
                                'created_at' => date('Y-m-d H:i:s'),
                            ]
                        );
                    }

                    $message = 'Product updated successfully.';
                } else {
                    $createdAt = date('Y-m-d H:i:s');

                    apiQuery(
                        $db,
                        'INSERT INTO products (
                            cid,
                            category_id,
                            sku,
                            product_name,
                            product_unit,
                            cost_price,
                            selling_price,
                            reorder_level,
                            is_active,
                            timestamp,
                            created_at
                        ) VALUES (
                            :cid,
                            :category_id,
                            :sku,
                            :name,
                            :unit,
                            :cost,
                            :selling,
                            :reorder,
                            1,
                            :timestamp,
                            :created_at
                        )',
                        [
                            'cid' => $cid,
                            'category_id' => $categoryId,
                            'sku' => $sku,
                            'name' => $name,
                            'unit' => $unit,
                            'cost' => $cost,
                            'selling' => $selling,
                            'reorder' => $reorder,
                            'timestamp' => $createdAt,
                            'created_at' => $createdAt
                        ]
                    );

                    $productId = (int)$db->lastInsertRowID();

                    /* Start stock at zero. */
                    apiQuery(
                        $db,
                        'INSERT INTO inventory (
                            product_id,
                            cid,
                            quantity,
                            last_updated
                        ) VALUES (
                            :product_id,
                            :cid,
                            0,
                            :last_updated
                        )',
                        [
                            'product_id' => $productId,
                            'cid' => $cid,
                            'last_updated' => $createdAt
                        ]
                    );

                    apiQuery(
                        $db,
                        'INSERT INTO stock_ledger (
                            product_id, cid, reference_type, qty_in, qty_out,
                            balance_after, unit_price, total_amount, unit_cost,
                            unit_selling_price, note, created_at
                         ) VALUES (
                            :product_id, :cid, :reference_type, 0, 0,
                            0, 0, 0, :unit_cost, :unit_selling_price,
                            :note, :created_at
                         )',
                        [
                            'product_id' => $productId, 'cid' => $cid,
                            'reference_type' => 'opening',
                            'unit_cost' => $cost,
                            'unit_selling_price' => $selling,
                            'note' => 'Opening product prices',
                            'created_at' => $createdAt,
                        ]
                    );

                    $message = 'Product added successfully.';
                }

                $db->exec('COMMIT');
            } catch (Throwable $e) {
                $db->exec('ROLLBACK');
                throw $e;
            }

            productResponse(
                'success',
                $message,
                ['product_id' => $productId]
            );
            break;

        /* Activate or deactivate a product. */
        case 'toggleProductStatus':
            $productId = (int)apiInput('product_id', '0');
            $active = productBool(apiInput('is_active', '0'));

            if ($productId <= 0) {
                productResponse('error', 'Invalid product ID.');
            }

            apiQuery(
                $db,
                'UPDATE products
                 SET is_active = :active
                 WHERE product_id = :product_id AND cid = :cid',
                [
                    'active' => $active,
                    'product_id' => $productId,
                    'cid' => $cid
                ]
            );

            if ($db->changes() === 0) {
                productResponse(
                    'error',
                    'Product was not found or its status is unchanged.'
                );
            }

            productResponse(
                'success',
                $active
                    ? 'Product activated successfully.'
                    : 'Product deactivated successfully.'
            );
            break;

        /* Handle Stock In, Stock Out, and Adjustments. */
        case 'listStockTakes':
            $result = apiQuery(
                $db,
                'SELECT stock_take_id, name, notes, total_products,
                        changed_products, created_at
                 FROM stock_take_sessions WHERE cid = :cid
                 ORDER BY stock_take_id DESC LIMIT 100',
                ['cid' => $cid]
            );
            productResponse('success', 'Stock take history loaded.', ['stock_takes' => apiFetchAll($result)]);
            break;

        case 'loadStockTakeDetails':
            $stockTakeId = (int)apiInput('stock_take_id', '0');
            $sessionResult = apiQuery(
                $db,
                'SELECT stock_take_id, name, notes, total_products,
                        changed_products, created_at
                 FROM stock_take_sessions WHERE stock_take_id = :id AND cid = :cid LIMIT 1',
                ['id' => $stockTakeId, 'cid' => $cid]
            );
            $stockTake = $sessionResult->fetchArray(SQLITE3_ASSOC);
            if (!$stockTake) {
                productResponse('error', 'Stock take session not found.');
            }
            $lines = apiQuery(
                $db,
                'SELECT l.product_id, p.product_name, p.sku, p.product_unit,
                        l.system_quantity, l.counted_quantity, l.variance
                 FROM stock_take_lines l
                 INNER JOIN products p
                    ON p.product_id = l.product_id AND p.cid = l.cid
                 WHERE l.stock_take_id = :id AND l.cid = :cid
                 ORDER BY p.product_name COLLATE NOCASE',
                ['id' => $stockTakeId, 'cid' => $cid]
            );
            productResponse('success', 'Stock take details loaded.', [
                'stock_take' => $stockTake,
                'lines' => apiFetchAll($lines),
            ]);
            break;

        case 'saveStockTake':
            $countsRaw = (string)($_POST['counts_json'] ?? '');
            $counts = json_decode($countsRaw, true);
            $sessionName = apiInput('stock_take_name');
            $sessionNotes = apiInput('stock_take_notes');
            if (!is_array($counts) || !$counts || count($counts) > 5000) {
                productResponse('error', 'A stock take must include at least one product count.');
            }
            if (strlen($sessionName) > 100 || strlen($sessionNotes) > 500) {
                productResponse('error', 'The stock take name or notes are too long.');
            }
            if ($sessionName === '') {
                $sessionName = 'Stock take ' . date('Y-m-d H:i');
            }

            $submittedCounts = [];
            foreach ($counts as $countRow) {
                if (!is_array($countRow)) {
                    productResponse('error', 'One or more product counts are malformed. No changes were made.');
                }
                $idRaw = $countRow['product_id'] ?? null;
                $id = filter_var($idRaw, FILTER_VALIDATE_INT);
                $systemRaw = $countRow['system_quantity'] ?? null;
                $physicalRaw = $countRow['counted_quantity'] ?? null;
                $ledgerVersionRaw = $countRow['latest_ledger_id'] ?? null;
                $ledgerVersion = filter_var($ledgerVersionRaw, FILTER_VALIDATE_INT);
                if ($id === false || $id === null || $id <= 0 || isset($submittedCounts[$id])
                    || !apiValidQuantity((string)$systemRaw)
                    || !apiValidQuantity((string)$physicalRaw)
                    || $ledgerVersion === false || $ledgerVersion === null || $ledgerVersion < 0
                    ) {
                    productResponse('error', 'Every product needs a valid non-negative physical count. No changes were made.');
                }
                $submittedCounts[$id] = [
                    'system_quantity' => (float)$systemRaw,
                    'counted_quantity' => (float)$physicalRaw,
                    'latest_ledger_id' => $ledgerVersion,
                ];
            }

            $db->exec('BEGIN IMMEDIATE');
            try {
                $currentResult = apiQuery(
                    $db,
                    'SELECT p.product_id, p.product_name, p.cost_price, p.selling_price,
                            COALESCE((SELECT MAX(l.ledger_id) FROM stock_ledger l
                                WHERE l.product_id = p.product_id AND l.cid = p.cid), 0) AS latest_ledger_id,
                            COALESCE(i.quantity, 0) AS current_quantity
                     FROM products p
                     LEFT JOIN inventory i
                       ON i.product_id = p.product_id AND i.cid = p.cid
                     WHERE p.cid = :cid ORDER BY p.product_id',
                    ['cid' => $cid]
                );
                $currentProducts = [];
                while ($row = $currentResult->fetchArray(SQLITE3_ASSOC)) {
                    $currentProducts[(int)$row['product_id']] = $row;
                }
                if (!$currentProducts) {
                    throw new InvalidArgumentException('There are no products to count.');
                }
                if (count($submittedCounts) !== count($currentProducts)) {
                    throw new RuntimeException('The product list changed while this count was open. Refresh the Products page and start the stock take again.');
                }

                $varianceRows = [];
                foreach ($currentProducts as $id => $product) {
                    if (!isset($submittedCounts[$id])) {
                        throw new RuntimeException('The submitted count does not include every current product. Refresh the Products page and count again.');
                    }
                    $systemQuantity = (float)$product['current_quantity'];
                    if ($submittedCounts[$id]['latest_ledger_id'] !== (int)$product['latest_ledger_id']) {
                        throw new RuntimeException('A stock movement or price change was recorded for "' . $product['product_name'] . '" after this count began. Refresh Products and recount before reconciling. No changes were made.');
                    }
                    if (abs($submittedCounts[$id]['system_quantity'] - $systemQuantity) > 0.0001) {
                        throw new RuntimeException('Stock changed for "' . $product['product_name'] . '" after this count began. Refresh Products and recount before reconciling. No changes were made.');
                    }
                    $countedQuantity = $submittedCounts[$id]['counted_quantity'];
                    $variance = $countedQuantity - $systemQuantity;
                    if (abs($variance) < 0.0001) {
                        $variance = 0.0;
                        $countedQuantity = $systemQuantity;
                    }
                    $varianceRows[] = [
                        'product_id' => $id,
                        'product' => $product,
                        'system_quantity' => $systemQuantity,
                        'counted_quantity' => $countedQuantity,
                        'variance' => $variance,
                    ];
                }

                $changedCount = count(array_filter($varianceRows, static function (array $row): bool {
                    return $row['variance'] != 0.0;
                }));
                $now = date('Y-m-d H:i:s');
                apiQuery(
                    $db,
                    'INSERT INTO stock_take_sessions (cid, name, notes, total_products, changed_products, created_at)
                     VALUES (:cid, :name, :notes, :total, :changed, :created_at)',
                    ['cid'=>$cid, 'name'=>$sessionName, 'notes'=>$sessionNotes, 'total'=>count($varianceRows), 'changed'=>$changedCount, 'created_at'=>$now]
                );
                $stockTakeId = (int)$db->lastInsertRowID();

                foreach ($varianceRows as $row) {
                    $productId = $row['product_id'];
                    $product = $row['product'];
                    $variance = $row['variance'];
                    apiQuery(
                        $db,
                        'INSERT INTO stock_take_lines (
                            stock_take_id, product_id, cid, system_quantity,
                            counted_quantity, variance, created_at
                         ) VALUES (
                            :stock_take_id, :product_id, :cid, :system_quantity,
                            :counted_quantity, :variance, :created_at
                         )',
                        ['stock_take_id'=>$stockTakeId, 'product_id'=>$productId, 'cid'=>$cid,
                         'system_quantity'=>$row['system_quantity'], 'counted_quantity'=>$row['counted_quantity'],
                         'variance'=>$variance, 'created_at'=>$now]
                    );

                    if ($variance == 0.0) {
                        continue;
                    }
                    apiQuery(
                        $db,
                        'INSERT INTO inventory (product_id, cid, quantity, last_updated)
                         VALUES (:product_id, :cid, :quantity, :now)
                         ON CONFLICT(product_id, cid) DO UPDATE
                         SET quantity = excluded.quantity, last_updated = excluded.last_updated',
                        ['quantity'=>$row['counted_quantity'], 'now'=>$now, 'product_id'=>$productId, 'cid'=>$cid]
                    );
                    apiQuery(
                        $db,
                        'INSERT INTO stock_ledger (
                            product_id, cid, reference_type, reference_id,
                            qty_in, qty_out, balance_after, unit_price, total_amount,
                            unit_cost, unit_selling_price, previous_cost_price,
                            previous_selling_price, note, created_at
                         ) VALUES (
                            :product_id, :cid, :reference_type, :reference_id,
                            :qty_in, :qty_out, :balance_after, 0, 0,
                            :unit_cost, :unit_selling_price, :previous_cost_price,
                            :previous_selling_price, :note, :created_at
                         )',
                        ['product_id'=>$productId, 'cid'=>$cid, 'reference_type'=>'adjustment',
                         'reference_id'=>$stockTakeId, 'qty_in'=>max(0.0, $variance),
                         'qty_out'=>max(0.0, -$variance), 'balance_after'=>$row['counted_quantity'],
                         'unit_cost'=>(float)$product['cost_price'], 'unit_selling_price'=>(float)$product['selling_price'],
                         'previous_cost_price'=>(float)$product['cost_price'], 'previous_selling_price'=>(float)$product['selling_price'],
                         'note'=>'Physical stock take #' . $stockTakeId . ': ' . $sessionName, 'created_at'=>$now]
                    );
                }
                $db->exec('COMMIT');
            } catch (Throwable $error) {
                $db->exec('ROLLBACK');
                productResponse('error', $error->getMessage());
            }

            productResponse(
                'success',
                'Stock take reconciled. Counted ' . count($varianceRows) . ' product(s); adjusted ' . $changedCount . '.',
                ['stock_take_id'=>$stockTakeId, 'total_products'=>count($varianceRows), 'changed_products'=>$changedCount]
            );
            break;

        case 'loadStockHistory':
            $productId = (int)apiInput('product_id', '0');
            $result = apiQuery(
                $db,
                'SELECT l.ledger_id, l.reference_type, l.qty_in, l.qty_out,
                        l.balance_after, l.unit_cost, l.unit_selling_price,
                        l.previous_cost_price, l.previous_selling_price,
                        l.unit_price, l.total_amount, l.note, l.created_at,
                        p.name AS partner_name
                 FROM stock_ledger l
                 LEFT JOIN partners p
                    ON p.partner_id = l.partner_id AND p.cid = l.cid
                 WHERE l.product_id = :product_id AND l.cid = :cid
                 ORDER BY l.ledger_id DESC LIMIT 500',
                ['product_id' => $productId, 'cid' => $cid]
            );
            productResponse('success', 'Stock history loaded.', ['movements' => apiFetchAll($result)]);
            break;

        case 'saveStockTransaction':
            $productIdRaw = apiInput('product_id', '0');
            $partnerIdRaw = apiInput('partner_id', '0');
            if (!preg_match('/^\d+$/', $productIdRaw) || ($partnerIdRaw !== '' && !preg_match('/^\d+$/', $partnerIdRaw))) {
                productResponse('error', 'Invalid product or partner selection.');
            }
            $productId = (int)$productIdRaw;
            $transactionType = apiInput('transaction_type'); // in, out, adjust
            $quantityRaw = apiInput('quantity', '0');
            $notes = apiInput('notes');
            $partnerId = $partnerIdRaw === '' ? 0 : (int)$partnerIdRaw;
            $priceRaw = apiInput('unit_price', '');
            $paidRaw = apiInput('amount_paid', '0');
            $newCostRaw = apiInput('new_cost_price', '');
            $newSellingRaw = apiInput('new_selling_price', '');

            if ($productId <= 0) {
                productResponse('error', 'Invalid product selection.');
            }

            if (!in_array($transactionType, ['in', 'out', 'adjust'], true)) {
                productResponse('error', 'Invalid stock transaction type.');
            }

            if (!apiValidQuantity($quantityRaw)) {
                productResponse('error', 'Please enter a valid quantity.');
            }
            if (strlen($notes) > 1000) productResponse('error', 'Notes must not exceed 1000 characters.');

            $qty = (float)$quantityRaw;

            if ($transactionType !== 'adjust' && $qty <= 0) {
                productResponse('error', 'Quantity must be greater than zero.');
            }

            if ($transactionType !== 'adjust' && (!apiValidMoney($priceRaw) || !apiValidMoney($paidRaw))) {
                productResponse('error', 'Enter a valid unit price and payment amount.');
            }
            if ($transactionType !== 'adjust' && $partnerId <= 0) {
                productResponse('error', 'Select a supplier or customer for this movement.');
            }
            if (($newCostRaw !== '' && !apiValidMoney($newCostRaw)) || ($newSellingRaw !== '' && !apiValidMoney($newSellingRaw))) {
                productResponse('error', 'Adjustment prices must be zero or greater.');
            }

            $unitPrice = $transactionType === 'adjust' ? 0.0 : (float)$priceRaw;
            $amountPaid = (float)$paidRaw;
            if ($transactionType !== 'adjust' && $qty * $unitPrice > 1000000000000) {
                productResponse('error', 'Transaction total exceeds the supported amount.');
            }

            $db->exec('BEGIN IMMEDIATE');

            try {
                // Verify product ownership
                $prodCheck = apiQuery(
                    $db,
                    'SELECT product_id, cost_price, selling_price FROM products WHERE product_id = :id AND cid = :cid LIMIT 1',
                    ['id' => $productId, 'cid' => $cid]
                );
                $productRow = $prodCheck->fetchArray(SQLITE3_ASSOC);
                if (!$productRow) {
                    throw new InvalidArgumentException('Product not found or access denied.');
                }

                $currentCost = (float)$productRow['cost_price'];
                $currentSelling = (float)$productRow['selling_price'];
                $previousCost = $currentCost;
                $previousSelling = $currentSelling;
                $partner = null;
                if ($transactionType !== 'adjust') {
                    $partnerResult = apiQuery(
                        $db,
                        'SELECT partner_id, role, customer_outstanding, supplier_outstanding,
                                customer_advance, supplier_advance
                         FROM partners
                         WHERE partner_id = :id AND cid = :cid AND status = :status
                           AND deleted_at IS NULL LIMIT 1',
                        ['id' => $partnerId, 'cid' => $cid, 'status' => 'active']
                    );
                    $partner = $partnerResult->fetchArray(SQLITE3_ASSOC);
                    $allowedRoles = $transactionType === 'in' ? ['supplier', 'both'] : ['customer', 'both'];
                    if (!$partner || !in_array($partner['role'], $allowedRoles, true)) {
                        throw new InvalidArgumentException('The selected partner is not active or cannot take this role.');
                    }
                }

                // Get current stock record
                $invCheck = apiQuery(
                    $db,
                    'SELECT quantity FROM inventory WHERE product_id = :id AND cid = :cid LIMIT 1',
                    ['id' => $productId, 'cid' => $cid]
                );
                $currentStockRow = $invCheck->fetchArray(SQLITE3_ASSOC);
                $currentQuantity = $currentStockRow ? (float)$currentStockRow['quantity'] : 0.0;

                $newQuantity = $currentQuantity;

                if ($transactionType === 'in') {
                    $newQuantity += $qty;
                } elseif ($transactionType === 'out') {
                    $newQuantity -= $qty;
                    if ($newQuantity < 0) {
                        throw new RuntimeException('Insufficient stock available for this operation.');
                    }
                } else { // adjust
                    $newQuantity = $qty;
                }
                if ($newQuantity > 1000000000) {
                    throw new InvalidArgumentException('Stock quantity exceeds the supported limit.');
                }

                if ($transactionType === 'adjust') {
                    $currentCost = $newCostRaw === '' ? $currentCost : (float)$newCostRaw;
                    $currentSelling = $newSellingRaw === '' ? $currentSelling : (float)$newSellingRaw;
                    if ($newCostRaw !== '' || $newSellingRaw !== '') {
                        apiQuery(
                            $db,
                            'UPDATE products SET cost_price = :cost, selling_price = :selling
                             WHERE product_id = :id AND cid = :cid',
                            ['cost' => $currentCost, 'selling' => $currentSelling, 'id' => $productId, 'cid' => $cid]
                        );
                    }
                } elseif ($transactionType === 'in') {
                    // The product's current cost follows the latest receipt;
                    // each receipt's own cost remains preserved in the ledger.
                    apiQuery(
                        $db,
                        'UPDATE products SET cost_price = :cost WHERE product_id = :id AND cid = :cid',
                        ['cost' => $unitPrice, 'id' => $productId, 'cid' => $cid]
                    );
                    $currentCost = $unitPrice;
                }

                $now = date('Y-m-d H:i:s');

                // Upsert inventory quantity
                if ($currentStockRow) {
                    apiQuery(
                        $db,
                        'UPDATE inventory SET quantity = :qty, last_updated = :now WHERE product_id = :id AND cid = :cid',
                        ['qty' => $newQuantity, 'now' => $now, 'id' => $productId, 'cid' => $cid]
                    );
                } else {
                    apiQuery(
                        $db,
                        'INSERT INTO inventory (product_id, cid, quantity, last_updated) VALUES (:id, :cid, :qty, :now)',
                        ['id' => $productId, 'cid' => $cid, 'qty' => $newQuantity, 'now' => $now]
                    );
                }

                $qtyIn = $transactionType === 'in' ? $qty : 0.0;
                $qtyOut = $transactionType === 'out' ? $qty : 0.0;
                $unitCost = $transactionType === 'in' ? $unitPrice : $currentCost;
                $unitSelling = $transactionType === 'out' ? $unitPrice : $currentSelling;
                $totalAmount = $transactionType === 'adjust' ? 0.0 : $qty * $unitPrice;
                apiQuery(
                    $db,
                    'INSERT INTO stock_ledger (
                        product_id, cid, reference_type, partner_id, qty_in, qty_out,
                        balance_after, unit_price, total_amount, unit_cost,
                        unit_selling_price, previous_cost_price, previous_selling_price,
                        note, created_at
                     ) VALUES (
                        :product_id, :cid, :reference_type, :partner_id, :qty_in, :qty_out,
                        :balance_after, :unit_price, :total_amount, :unit_cost,
                        :unit_selling_price, :previous_cost_price, :previous_selling_price,
                        :note, :created_at
                     )',
                    [
                        'product_id' => $productId, 'cid' => $cid,
                        'reference_type' => $transactionType === 'in' ? 'stock_in' : ($transactionType === 'out' ? 'stock_out' : 'adjustment'),
                        'partner_id' => $transactionType === 'adjust' ? null : $partnerId,
                        'qty_in' => $qtyIn, 'qty_out' => $qtyOut, 'balance_after' => $newQuantity,
                        'unit_price' => $unitPrice, 'total_amount' => $totalAmount,
                        'unit_cost' => $unitCost, 'unit_selling_price' => $unitSelling,
                        'previous_cost_price' => $previousCost,
                        'previous_selling_price' => $previousSelling,
                        'note' => $notes, 'created_at' => $now,
                    ]
                );
                $ledgerId = (int)$db->lastInsertRowID();

                if ($partner && $transactionType !== 'adjust') {
                    $isSale = $transactionType === 'out';
                    $dueColumn = $isSale ? 'customer_outstanding' : 'supplier_outstanding';
                    $advanceColumn = $isSale ? 'customer_advance' : 'supplier_advance';
                    $accountType = $isSale ? 'customer' : 'supplier';
                    $oldDue = (float)$partner[$dueColumn];
                    $oldAdvance = (float)$partner[$advanceColumn];
                    $net = $oldDue + $totalAmount - $oldAdvance - $amountPaid;
                    $newDue = max(0.0, $net);
                    $newAdvance = max(0.0, -$net);
                    apiQuery(
                        $db,
                        "UPDATE partners SET {$dueColumn} = :due, {$advanceColumn} = :advance,
                            updated_at = :now WHERE partner_id = :partner_id AND cid = :cid",
                        ['due' => $newDue, 'advance' => $newAdvance, 'now' => $now, 'partner_id' => $partnerId, 'cid' => $cid]
                    );
                    apiQuery(
                        $db,
                        'INSERT INTO partner_ledger (partner_id, cid, account_type, entry_type, amount, balance_effect, related_ledger_id, note, created_at)
                         VALUES (:partner_id, :cid, :account, :entry_type, :amount, :balance_effect, :ledger_id, :note, :created_at)',
                        ['partner_id' => $partnerId, 'cid' => $cid, 'account' => $accountType,
                         'entry_type' => $isSale ? 'sale' : 'purchase', 'amount' => $totalAmount, 'balance_effect' => $totalAmount,
                         'ledger_id' => $ledgerId, 'note' => $notes, 'created_at' => $now]
                    );
                    if ($amountPaid > 0) {
                        apiQuery(
                            $db,
                            'INSERT INTO partner_ledger (partner_id, cid, account_type, entry_type, amount, balance_effect, related_ledger_id, note, created_at)
                             VALUES (:partner_id, :cid, :account, :entry_type, :amount, :balance_effect, :ledger_id, :note, :created_at)',
                            ['partner_id' => $partnerId, 'cid' => $cid, 'account' => $accountType,
                             'entry_type' => 'payment', 'amount' => $amountPaid, 'balance_effect' => -$amountPaid, 'ledger_id' => $ledgerId,
                             'note' => $notes, 'created_at' => $now]
                        );
                    }
                }

                $db->exec('COMMIT');
                $message = 'Stock movement recorded with price and partner account details. New balance: ' . $newQuantity;
            } catch (Throwable $e) {
                $db->exec('ROLLBACK');
                productResponse('error', $e->getMessage());
            }

            productResponse('success', $message, ['new_quantity' => $newQuantity]);
            break;


        default:
            http_response_code(400);
            productResponse('error', 'Unknown products API action.');
    }

} catch (Throwable $e) {
    error_log('apiProducts.php error: ' . $e->getMessage());

    http_response_code(500);
    productResponse(
        'error',
        'The products service encountered a server error. Check the PHP error log.',
        [],
        500
    );
}
