<?php
declare(strict_types=1);

require_once __DIR__ . '/ProductCatalogueSeeder.php';

/** Installs versioned product catalogues without changing existing records. */
final class ProductCatalogueInstaller
{
    private SQLite3 $db;
    private int $cid;

    public function __construct(SQLite3 $db, int $cid)
    {
        if ($cid < 1) {
            throw new InvalidArgumentException('A valid company account is required.');
        }

        $this->db = $db;
        $this->cid = $cid;
    }

    public function install(string $catalogueKey): array
    {
        $catalogue = ProductCatalogueSeeder::catalogues()[$catalogueKey] ?? null;
        if (!is_array($catalogue)) {
            throw new InvalidArgumentException('The selected product catalogue is not available.');
        }

        $report = [
            'catalogue_name' => $catalogue['name'],
            'categories_created' => 0,
            'categories_reused' => 0,
            'unit_created' => 0,
            'unit_reused' => 0,
            'products_requested' => 0,
            'products_created' => 0,
            'products_skipped' => 0,
            'skipped_products' => [],
            'conflicts' => [],
        ];

        foreach ($catalogue['categories'] as $category) {
            $report['products_requested'] += count($category['products']);
        }

        $this->db->busyTimeout(5000);
        $this->db->enableExceptions(true);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('BEGIN IMMEDIATE');

        try {
            $account = $this->fetchOne(
                'SELECT cid FROM company WHERE cid = :cid LIMIT 1',
                ['cid' => $this->cid]
            );
            if ($account === null) {
                throw new RuntimeException('The authenticated business account no longer exists.');
            }

            $settings = $this->fetchOne(
                'SELECT setting_value FROM company_settings
                 WHERE cid = :cid AND setting_key = :setting_key LIMIT 1',
                ['cid' => $this->cid, 'setting_key' => 'default_reorder_level']
            );
            $reorderLevel = $settings === null ? 0.0 : (float) $settings['setting_value'];
            if (!is_finite($reorderLevel) || $reorderLevel < 0) {
                throw new RuntimeException('The account default reorder level is invalid.');
            }

            $categoryIds = [];
            foreach ($catalogue['categories'] as $category) {
                $existing = $this->fetchOne(
                    'SELECT category_id FROM product_categories
                     WHERE cid = :cid AND category_name = :name COLLATE NOCASE LIMIT 1',
                    ['cid' => $this->cid, 'name' => $category['name']]
                );

                if ($existing !== null) {
                    $categoryIds[$category['key']] = (int) $existing['category_id'];
                    $report['categories_reused']++;
                    continue;
                }

                $this->execute(
                    'INSERT INTO product_categories (cid, category_name, category_description, is_active)
                     VALUES (:cid, :name, :description, 1)',
                    ['cid' => $this->cid, 'name' => $category['name'], 'description' => '']
                );
                $categoryIds[$category['key']] = (int) $this->db->lastInsertRowID();
                $report['categories_created']++;
            }

            $unit = $this->fetchOne(
                'SELECT unit_id FROM product_units
                 WHERE cid = :cid AND unit_name = :name COLLATE NOCASE LIMIT 1',
                ['cid' => $this->cid, 'name' => 'Sheet']
            );
            if ($unit === null) {
                $this->execute(
                    'INSERT INTO product_units (cid, unit_name, unit_symbol, description, is_active)
                     VALUES (:cid, :name, :symbol, :description, 1)',
                    [
                        'cid' => $this->cid,
                        'name' => 'Sheet',
                        'symbol' => 'sheet',
                        'description' => 'Sheet unit used by the Chivicks Concept signage materials catalogue.',
                    ]
                );
                $report['unit_created'] = 1;
            } else {
                $report['unit_reused'] = 1;
            }

            foreach ($catalogue['categories'] as $category) {
                $categoryId = $categoryIds[$category['key']];
                foreach ($category['products'] as $productName) {
                    $existingProduct = $this->fetchOne(
                        'SELECT p.product_id, p.category_id, pc.category_name AS existing_category
                         FROM products p
                         LEFT JOIN product_categories pc
                           ON pc.category_id = p.category_id AND pc.cid = p.cid
                         WHERE p.cid = :cid AND p.product_name = :name COLLATE NOCASE
                         LIMIT 1',
                        ['cid' => $this->cid, 'name' => $productName]
                    );

                    if ($existingProduct !== null) {
                        if ((int) ($existingProduct['category_id'] ?? 0) === $categoryId) {
                            $report['products_skipped']++;
                            $report['skipped_products'][] = [
                                'product' => $productName,
                                'category' => $category['name'],
                                'reason' => 'Already exists in the catalogue category.',
                            ];
                        } else {
                            $report['conflicts'][] = [
                                'type' => 'product_category_conflict',
                                'product' => $productName,
                                'expected_category' => $category['name'],
                                'existing_category' => $existingProduct['existing_category'] ?: 'Uncategorised',
                                'message' => 'An existing product with this name belongs to another category.',
                            ];
                        }
                        continue;
                    }

                    $sku = $this->makeSku($category['key'], $productName);
                    $skuOwner = $this->fetchOne(
                        'SELECT product_name FROM products
                         WHERE cid = :cid AND LOWER(sku) = LOWER(:sku) LIMIT 1',
                        ['cid' => $this->cid, 'sku' => $sku]
                    );
                    if ($skuOwner !== null) {
                        $report['conflicts'][] = [
                            'type' => 'sku_conflict',
                            'product' => $productName,
                            'sku' => $sku,
                            'existing_product' => $skuOwner['product_name'],
                            'message' => 'The generated catalogue SKU already belongs to another product.',
                        ];
                        continue;
                    }

                    $createdAt = date('Y-m-d H:i:s');
                    $this->execute(
                        'INSERT INTO products (
                            cid, category_id, sku, product_name, product_unit,
                            cost_price, selling_price, reorder_level, is_active, timestamp, created_at
                         ) VALUES (
                            :cid, :category_id, :sku, :product_name, :product_unit,
                            0, 0, :reorder_level, 1, :timestamp, :created_at
                         )',
                        [
                            'cid' => $this->cid,
                            'category_id' => $categoryId,
                            'sku' => $sku,
                            'product_name' => $productName,
                            'product_unit' => 'sheet',
                            'reorder_level' => $reorderLevel,
                            'timestamp' => $createdAt,
                            'created_at' => $createdAt,
                        ]
                    );
                    $productId = (int) $this->db->lastInsertRowID();

                    $this->execute(
                        'INSERT INTO inventory (product_id, cid, quantity, last_updated)
                         VALUES (:product_id, :cid, 0, :last_updated)',
                        ['product_id' => $productId, 'cid' => $this->cid, 'last_updated' => $createdAt]
                    );
                    $this->execute(
                        'INSERT INTO stock_ledger (
                            product_id, cid, reference_type, qty_in, qty_out, balance_after,
                            unit_price, total_amount, unit_cost, unit_selling_price, note, created_at
                         ) VALUES (
                            :product_id, :cid, :reference_type, 0, 0, 0,
                            0, 0, 0, 0, :note, :created_at
                         )',
                        [
                            'product_id' => $productId,
                            'cid' => $this->cid,
                            'reference_type' => 'opening',
                            'note' => 'Opening product prices',
                            'created_at' => $createdAt,
                        ]
                    );
                    $report['products_created']++;
                }
            }

            $report['conflict_count'] = count($report['conflicts']);
            $this->execute(
                'INSERT INTO product_catalogue_installations (
                    cid, catalogue_key, catalogue_name, products_created,
                    products_skipped, conflict_count, report_json
                 ) VALUES (
                    :cid, :catalogue_key, :catalogue_name, :products_created,
                    :products_skipped, :conflict_count, :report_json
                 )',
                [
                    'cid' => $this->cid,
                    'catalogue_key' => $catalogueKey,
                    'catalogue_name' => $catalogue['name'],
                    'products_created' => $report['products_created'],
                    'products_skipped' => $report['products_skipped'],
                    'conflict_count' => $report['conflict_count'],
                    'report_json' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                ]
            );
            $report['installation_id'] = (int) $this->db->lastInsertRowID();
            $this->db->exec('COMMIT');
        } catch (Throwable $error) {
            try {
                $this->db->exec('ROLLBACK');
            } catch (Throwable $rollbackError) {
                // Preserve the original installation failure for logging.
            }
            throw $error;
        }

        return $report;
    }

    private function makeSku(string $categoryKey, string $productName): string
    {
        $slug = static function (string $value): string {
            $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
            $value = strtoupper($value);
            return trim((string) preg_replace('/[^A-Z0-9]+/', '-', $value), '-');
        };

        return 'CAT-' . $slug($categoryKey) . '-' . $slug($productName);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $statement = $this->db->prepare($sql);
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('Unable to prepare catalogue query.');
        }

        try {
            foreach ($params as $key => $value) {
                $statement->bindValue(':' . ltrim((string) $key, ':'), $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
            }
            $result = $statement->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('Unable to execute catalogue query.');
            }
            try {
                $row = $result->fetchArray(SQLITE3_ASSOC);
                return $row === false ? null : $row;
            } finally {
                $result->finalize();
            }
        } finally {
            $statement->close();
        }
    }

    private function execute(string $sql, array $params): void
    {
        $statement = $this->db->prepare($sql);
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('Unable to prepare catalogue insert.');
        }

        try {
            foreach ($params as $key => $value) {
                $type = match (true) {
                    is_int($value) => SQLITE3_INTEGER,
                    is_float($value) => SQLITE3_FLOAT,
                    $value === null => SQLITE3_NULL,
                    default => SQLITE3_TEXT,
                };
                $statement->bindValue(':' . ltrim((string) $key, ':'), $value, $type);
            }
            $result = $statement->execute();
            if ($result instanceof SQLite3Result) {
                $result->finalize();
            }
        } finally {
            $statement->close();
        }
    }
}
