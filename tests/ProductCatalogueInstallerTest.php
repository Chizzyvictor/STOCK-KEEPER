<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ProductCatalogueInstaller.php';

function catalogueAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function openCatalogueFixture(string $path): SQLite3
{
    $db = new SQLite3($path);
    $db->enableExceptions(true);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('CREATE TABLE company (cid INTEGER PRIMARY KEY)');
    $db->exec("INSERT INTO company (cid) VALUES (101), (202)");
    $db->exec('CREATE TABLE company_settings (
        cid INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL,
        PRIMARY KEY (cid, setting_key)
    )');
    $db->exec("INSERT INTO company_settings VALUES (101, 'default_reorder_level', '5')");
    $db->exec('CREATE TABLE product_catalogue_installations (
        installation_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        catalogue_key TEXT NOT NULL,
        catalogue_name TEXT NOT NULL,
        products_created INTEGER NOT NULL DEFAULT 0,
        products_skipped INTEGER NOT NULL DEFAULT 0,
        conflict_count INTEGER NOT NULL DEFAULT 0,
        report_json TEXT NOT NULL,
        installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cid) REFERENCES company(cid)
    )');
    $db->exec('CREATE TABLE product_categories (
        category_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        category_name TEXT NOT NULL,
        category_description TEXT DEFAULT \'\',
        is_active INTEGER NOT NULL DEFAULT 1,
        UNIQUE (category_id, cid),
        UNIQUE (cid, category_name),
        FOREIGN KEY (cid) REFERENCES company(cid)
    )');
    $db->exec('CREATE TABLE product_units (
        unit_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        unit_name TEXT NOT NULL,
        unit_symbol TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        is_active INTEGER NOT NULL DEFAULT 1,
        FOREIGN KEY (cid) REFERENCES company(cid)
    )');
    $db->exec('CREATE UNIQUE INDEX idx_fixture_units_name ON product_units(cid, unit_name COLLATE NOCASE)');
    $db->exec('CREATE TABLE products (
        product_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        category_id INTEGER,
        sku TEXT,
        product_name TEXT NOT NULL,
        product_unit TEXT NOT NULL DEFAULT \'pcs\',
        cost_price REAL NOT NULL DEFAULT 0,
        selling_price REAL NOT NULL DEFAULT 0,
        reorder_level REAL NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        timestamp INTEGER,
        created_at TEXT,
        UNIQUE (product_id, cid),
        UNIQUE (cid, product_name),
        UNIQUE (cid, sku),
        FOREIGN KEY (category_id, cid) REFERENCES product_categories(category_id, cid),
        FOREIGN KEY (cid) REFERENCES company(cid)
    )');
    $db->exec('CREATE TABLE inventory (
        inventory_id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        cid INTEGER NOT NULL,
        quantity REAL NOT NULL DEFAULT 0,
        last_updated TEXT,
        UNIQUE (product_id, cid),
        FOREIGN KEY (product_id, cid) REFERENCES products(product_id, cid)
    )');
    $db->exec('CREATE TABLE stock_ledger (
        ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        cid INTEGER NOT NULL,
        reference_type TEXT NOT NULL,
        qty_in REAL NOT NULL DEFAULT 0,
        qty_out REAL NOT NULL DEFAULT 0,
        balance_after REAL NOT NULL,
        unit_price REAL NOT NULL DEFAULT 0,
        total_amount REAL NOT NULL DEFAULT 0,
        unit_cost REAL NOT NULL DEFAULT 0,
        unit_selling_price REAL NOT NULL DEFAULT 0,
        note TEXT,
        created_at TEXT,
        FOREIGN KEY (product_id, cid) REFERENCES products(product_id, cid)
    )');
    return $db;
}

function installCatalogue(SQLite3 $db): array
{
    return (new ProductCatalogueInstaller($db, 101))->install('chivicks-signage-materials');
}

$path = tempnam(sys_get_temp_dir(), 'stock-keeper-catalogue-');
$conflictPath = tempnam(sys_get_temp_dir(), 'stock-keeper-catalogue-conflicts-');
$rollbackPath = tempnam(sys_get_temp_dir(), 'stock-keeper-catalogue-rollback-');
if ($path === false || $conflictPath === false || $rollbackPath === false) {
    throw new RuntimeException('Unable to create the temporary SQLite fixture.');
}

$db = null;
$conflictDb = null;
$rollbackDb = null;
try {
    $db = openCatalogueFixture($path);

    // Fresh account: all catalogue rows and their stock records are created.
    $first = installCatalogue($db);
    catalogueAssert($first['products_created'] === 76, 'Fresh install should create all 76 products.');
    catalogueAssert($first['categories_created'] === 6, 'Fresh install should create all 6 categories.');
    catalogueAssert($first['unit_created'] === 1, 'Fresh install should create the Sheet unit.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM products WHERE cid = 101') === 76, 'Fresh install product count is wrong.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM inventory WHERE cid = 101') === 76, 'Each created product must have one inventory row.');
    catalogueAssert((int) $db->querySingle("SELECT COUNT(*) FROM stock_ledger WHERE cid = 101 AND reference_type = 'opening'") === 76, 'Each created product must have one opening ledger entry.');
    catalogueAssert((int) $db->querySingle("SELECT COUNT(*) FROM products p
        WHERE p.cid = 101
          AND (SELECT COUNT(*) FROM inventory i WHERE i.product_id = p.product_id AND i.cid = p.cid) != 1") === 0, 'A created product is missing a unique inventory row.');
    catalogueAssert((int) $db->querySingle("SELECT COUNT(*) FROM products p
        WHERE p.cid = 101
          AND (SELECT COUNT(*) FROM stock_ledger l WHERE l.product_id = p.product_id AND l.cid = p.cid AND l.reference_type = 'opening') != 1") === 0, 'A created product is missing exactly one opening ledger entry.');
    catalogueAssert((int) $db->querySingle("SELECT COUNT(*) FROM stock_ledger WHERE cid = 101 AND reference_type = 'opening' AND (qty_in != 0 OR qty_out != 0 OR balance_after != 0 OR unit_cost != 0 OR unit_selling_price != 0)") === 0, 'Opening ledger values must represent zero opening stock and prices.');
    catalogueAssert((int) $db->querySingle("SELECT COUNT(*) FROM products WHERE cid = 101 AND product_unit = 'sheet' AND cost_price = 0 AND selling_price = 0 AND reorder_level = 5") === 76, 'Product unit, prices, or default reorder level is wrong.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM products WHERE cid = 202') === 0, 'Installation wrote products into another account.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM product_catalogue_installations WHERE cid = 101') === 1, 'The installation report was not saved with the install.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM product_catalogue_installations WHERE cid = 202') === 0, 'Installation history was written into another account.');

    // Repeat install: products are recognized and no new stock rows are added.
    $second = installCatalogue($db);
    catalogueAssert($second['products_created'] === 0, 'Repeat install must not create products.');
    catalogueAssert($second['products_skipped'] === 76, 'Repeat install should skip all matching products.');
    catalogueAssert($second['categories_reused'] === 6 && $second['categories_created'] === 0, 'Repeat install should reuse all six categories.');
    catalogueAssert($second['unit_reused'] === 1 && $second['unit_created'] === 0, 'Repeat install should reuse the Sheet unit.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM products WHERE cid = 101') === 76, 'Repeat install duplicated products.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM product_categories WHERE cid = 101') === 6, 'Repeat install duplicated categories.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM product_units WHERE cid = 101') === 1, 'Repeat install duplicated units.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM inventory WHERE cid = 101') === 76, 'Repeat install duplicated inventory rows.');
    catalogueAssert((int) $db->querySingle("SELECT COUNT(*) FROM stock_ledger WHERE cid = 101 AND reference_type = 'opening'") === 76, 'Repeat install duplicated opening ledger entries.');
    catalogueAssert((int) $db->querySingle('SELECT COUNT(*) FROM product_catalogue_installations WHERE cid = 101') === 2, 'Repeat install history entry was not recorded.');

    // Separate account fixture: category and SKU conflicts are reported while
    // the existing products and their stock remain untouched.
    $conflictDb = openCatalogueFixture($conflictPath);
    $conflictDb->exec("INSERT INTO product_categories (cid, category_name) VALUES (101, 'Other')");
    $otherCategory = (int) $conflictDb->lastInsertRowID();
    $conflictDb->exec("INSERT INTO products (cid, category_id, sku, product_name, product_unit, cost_price, selling_price, reorder_level)
        VALUES (101, $otherCategory, 'CUSTOM-001', '3mm Foamboard', 'box', 14, 25, 9)");
    $categoryConflictProduct = (int) $conflictDb->lastInsertRowID();
    $conflictDb->exec("INSERT INTO inventory (product_id, cid, quantity) VALUES ($categoryConflictProduct, 101, 12)");
    $conflictDb->exec("INSERT INTO products (cid, category_id, sku, product_name, product_unit, cost_price, selling_price, reorder_level)
        VALUES (101, $otherCategory, 'CAT-FOAMBOARD-PVC-4MM-FOAMBOARD', 'Unrelated Existing Product', 'box', 8, 11, 2)");
    $skuConflictProduct = (int) $conflictDb->lastInsertRowID();
    $conflictDb->exec("INSERT INTO inventory (product_id, cid, quantity) VALUES ($skuConflictProduct, 101, 3)");

    $beforeRows = $conflictDb->query("SELECT p.product_id, p.category_id, p.sku, p.product_name, p.product_unit,
        p.cost_price, p.selling_price, p.reorder_level, i.quantity
        FROM products p JOIN inventory i ON i.product_id = p.product_id AND i.cid = p.cid
        WHERE p.product_id IN ($categoryConflictProduct, $skuConflictProduct) ORDER BY p.product_id");
    $before = [];
    while ($row = $beforeRows->fetchArray(SQLITE3_ASSOC)) $before[] = $row;

    $third = installCatalogue($conflictDb);
    catalogueAssert($third['products_created'] === 74, 'Conflict fixture should create the 74 non-conflicting products.');
    catalogueAssert($third['conflict_count'] === 2, 'Category and SKU conflicts should both be reported.');
    $conflictTypes = array_column($third['conflicts'], 'type');
    catalogueAssert(in_array('product_category_conflict', $conflictTypes, true), 'The category conflict was not reported.');
    catalogueAssert(in_array('sku_conflict', $conflictTypes, true), 'The SKU conflict was not reported.');
    $afterRows = $conflictDb->query("SELECT p.product_id, p.category_id, p.sku, p.product_name, p.product_unit,
        p.cost_price, p.selling_price, p.reorder_level, i.quantity
        FROM products p JOIN inventory i ON i.product_id = p.product_id AND i.cid = p.cid
        WHERE p.product_id IN ($categoryConflictProduct, $skuConflictProduct) ORDER BY p.product_id");
    $after = [];
    while ($row = $afterRows->fetchArray(SQLITE3_ASSOC)) $after[] = $row;
    catalogueAssert($before === $after, 'Conflict handling modified an existing product or its stock.');
    catalogueAssert((int) $conflictDb->querySingle('SELECT COUNT(*) FROM product_catalogue_installations WHERE cid = 101 AND conflict_count = 2') === 1, 'Conflict details were not saved in installation history.');

    // Fail partway through an import and verify all catalogue writes roll back.
    $rollbackDb = openCatalogueFixture($rollbackPath);
    $rollbackDb->exec("CREATE TRIGGER fail_catalogue_inventory BEFORE INSERT ON inventory
        WHEN (SELECT product_name FROM products WHERE product_id = NEW.product_id) = '4mm Foamboard'
        BEGIN SELECT RAISE(ABORT, 'Simulated inventory write failure'); END");
    $failedAsExpected = false;
    try {
        installCatalogue($rollbackDb);
    } catch (Throwable $error) {
        $failedAsExpected = true;
    }
    catalogueAssert($failedAsExpected, 'The simulated mid-install database failure did not occur.');
    foreach (['product_categories', 'product_units', 'products', 'inventory', 'stock_ledger', 'product_catalogue_installations'] as $table) {
        catalogueAssert((int) $rollbackDb->querySingle("SELECT COUNT(*) FROM $table WHERE cid = 101") === 0, "Failed install left partial rows in $table.");
    }

    echo "PASS: fresh and repeat installs, conflict preservation, account isolation, inventory/ledger creation, and atomic rollback.\n";
} finally {
    if ($db instanceof SQLite3) {
        $db->close();
    }
    if ($conflictDb instanceof SQLite3) {
        $conflictDb->close();
    }
    if ($rollbackDb instanceof SQLite3) {
        $rollbackDb->close();
    }
    @unlink($path);
    @unlink($conflictPath);
    @unlink($rollbackPath);
}
