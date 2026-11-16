<?php

declare(strict_types=1);

/**
 * Stock Keeper — SQLite Database Initializer
 * Requires the PHP SQLite3 extension.
 *
 * Run from the command line during initial setup or a planned schema upgrade.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/INC/database.php';

// -------------------------
// Database Connection
// -------------------------
try {
    $db = stockKeeperOpenDatabase(true);

    $db->exec('BEGIN IMMEDIATE TRANSACTION');

    $schema = [

        // COMPANY
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS company (
            cid INTEGER PRIMARY KEY AUTOINCREMENT,
            cName TEXT NOT NULL UNIQUE,
            cEmail TEXT NOT NULL UNIQUE,
            cPass TEXT NOT NULL,
            question TEXT NOT NULL DEFAULT '',
            answer TEXT NOT NULL DEFAULT '',
            cLogo TEXT DEFAULT 'logo.jpg',
            regDate INTEGER DEFAULT (strftime('%s','now')),
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        SQL,

        // PRODUCT CATEGORIES
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS product_categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            category_name TEXT NOT NULL,
            category_description TEXT DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1
                CHECK (is_active IN (0,1)),
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid)
                ON DELETE CASCADE,
            UNIQUE (category_id, cid),
            UNIQUE (cid, category_name)
        );
        SQL,

        // PRODUCT UNITS
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS product_units (
            unit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            unit_name TEXT NOT NULL CHECK (length(trim(unit_name)) > 0),
            unit_symbol TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
        );
        SQL,

        // PRODUCTS
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS products (
            product_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            category_id INTEGER,
            sku TEXT,
            product_name TEXT NOT NULL,
            product_image TEXT DEFAULT 'product.jpg',
            product_unit TEXT NOT NULL DEFAULT 'pcs',
            cost_price REAL NOT NULL DEFAULT 0
                CHECK (cost_price >= 0),
            selling_price REAL NOT NULL DEFAULT 0
                CHECK (selling_price >= 0),
            reorder_level REAL NOT NULL DEFAULT 0
                CHECK (reorder_level >= 0),
            is_active INTEGER NOT NULL DEFAULT 1
                CHECK (is_active IN (0,1)),
            timestamp INTEGER DEFAULT (strftime('%s','now')),
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid)
                ON DELETE CASCADE,
            FOREIGN KEY (category_id, cid)
                REFERENCES product_categories(category_id, cid)
                ON DELETE SET NULL,
            UNIQUE (product_id, cid),
            UNIQUE (cid, product_name),
            UNIQUE (cid, sku)
        );
        SQL,

        // INVENTORY
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS inventory (
            inventory_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            quantity REAL NOT NULL DEFAULT 0
                CHECK (quantity >= 0),
            last_updated TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id, cid)
                REFERENCES products(product_id, cid)
                ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid)
                ON DELETE CASCADE,
            UNIQUE (product_id, cid)
        );
        SQL,

        // STOCK LEDGER
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS stock_ledger (
            ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            reference_type TEXT NOT NULL
                CHECK (
                    reference_type IN (
                        'opening',
                        'stock_in',
                        'stock_out',
                        'adjustment'
                    )
                ),
            reference_id INTEGER,
            partner_id INTEGER,
            qty_in REAL NOT NULL DEFAULT 0 CHECK (qty_in >= 0),
            qty_out REAL NOT NULL DEFAULT 0 CHECK (qty_out >= 0),
            balance_after REAL NOT NULL CHECK (balance_after >= 0),
            unit_price REAL NOT NULL DEFAULT 0 CHECK (unit_price >= 0),
            total_amount REAL NOT NULL DEFAULT 0 CHECK (total_amount >= 0),
            unit_cost REAL CHECK (unit_cost IS NULL OR unit_cost >= 0),
            unit_selling_price REAL CHECK (unit_selling_price IS NULL OR unit_selling_price >= 0),
            previous_cost_price REAL CHECK (previous_cost_price IS NULL OR previous_cost_price >= 0),
            previous_selling_price REAL CHECK (previous_selling_price IS NULL OR previous_selling_price >= 0),
            note TEXT DEFAULT '',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id, cid)
                REFERENCES products(product_id, cid)
                ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid)
                ON DELETE CASCADE,
            CHECK (qty_in = 0 OR qty_out = 0)
        );
        SQL,

        // BUSINESS PARTNERS
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS partners (
            partner_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            name TEXT NOT NULL COLLATE NOCASE,
            phone TEXT,
            email TEXT COLLATE NOCASE,
            address TEXT,
            role TEXT NOT NULL DEFAULT 'both'
                CHECK (role IN ('supplier', 'customer', 'both')),
            is_system INTEGER NOT NULL DEFAULT 0 CHECK (is_system IN (0,1)),
            system_key TEXT,
            status TEXT NOT NULL DEFAULT 'active'
                CHECK (status IN ('active', 'inactive')),
            notes TEXT,
            customer_outstanding REAL NOT NULL DEFAULT 0 CHECK (customer_outstanding >= 0),
            supplier_outstanding REAL NOT NULL DEFAULT 0 CHECK (supplier_outstanding >= 0),
            customer_advance REAL NOT NULL DEFAULT 0 CHECK (customer_advance >= 0),
            supplier_advance REAL NOT NULL DEFAULT 0 CHECK (supplier_advance >= 0),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at TEXT,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (partner_id, cid),
            UNIQUE (cid, name)
        );
        SQL,

        // PARTNER ACCOUNT ACTIVITY
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS partner_ledger (
            entry_id INTEGER PRIMARY KEY AUTOINCREMENT,
            partner_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            account_type TEXT NOT NULL CHECK (account_type IN ('customer', 'supplier')),
            entry_type TEXT NOT NULL CHECK (entry_type IN ('sale', 'purchase', 'payment', 'refund', 'opening')),
            amount REAL NOT NULL CHECK (amount >= 0),
            balance_effect REAL,
            related_ledger_id INTEGER,
            note TEXT DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (partner_id, cid) REFERENCES partners(partner_id, cid) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            FOREIGN KEY (related_ledger_id) REFERENCES stock_ledger(ledger_id) ON DELETE SET NULL
        );
        SQL,

        // PHYSICAL STOCK TAKE AUDITS
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS stock_take_sessions (
            stock_take_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            name TEXT NOT NULL,
            notes TEXT DEFAULT '',
            total_products INTEGER NOT NULL DEFAULT 0 CHECK (total_products >= 0),
            changed_products INTEGER NOT NULL DEFAULT 0 CHECK (changed_products >= 0),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (stock_take_id, cid)
        );
        SQL,

        <<<'SQL'
        CREATE TABLE IF NOT EXISTS stock_take_lines (
            stock_take_line_id INTEGER PRIMARY KEY AUTOINCREMENT,
            stock_take_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            system_quantity REAL NOT NULL CHECK (system_quantity >= 0),
            counted_quantity REAL NOT NULL CHECK (counted_quantity >= 0),
            variance REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (stock_take_id, cid)
                REFERENCES stock_take_sessions(stock_take_id, cid) ON DELETE CASCADE,
            FOREIGN KEY (product_id, cid)
                REFERENCES products(product_id, cid) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (stock_take_id, product_id)
        );
        SQL,

        // APPLICATION SETTINGS
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL
        );
        SQL,
    ];

    foreach ($schema as $sql) {
        $db->exec($sql);
    }

    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_product_units_company_name
        ON product_units(cid, unit_name COLLATE NOCASE)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_product_units_company_status
        ON product_units(cid, is_active, unit_name)');

    // Upgrade stock ledgers created by earlier Stock Keeper versions.
    $ledgerColumns = [];
    $columnResult = $db->query('PRAGMA table_info(stock_ledger)');
    while ($column = $columnResult->fetchArray(SQLITE3_ASSOC)) {
        $ledgerColumns[$column['name']] = true;
    }
    $ledgerUpgrades = [
        'partner_id' => 'ALTER TABLE stock_ledger ADD COLUMN partner_id INTEGER',
        'unit_price' => 'ALTER TABLE stock_ledger ADD COLUMN unit_price REAL NOT NULL DEFAULT 0 CHECK (unit_price >= 0)',
        'total_amount' => 'ALTER TABLE stock_ledger ADD COLUMN total_amount REAL NOT NULL DEFAULT 0 CHECK (total_amount >= 0)',
        'unit_cost' => 'ALTER TABLE stock_ledger ADD COLUMN unit_cost REAL CHECK (unit_cost IS NULL OR unit_cost >= 0)',
        'unit_selling_price' => 'ALTER TABLE stock_ledger ADD COLUMN unit_selling_price REAL CHECK (unit_selling_price IS NULL OR unit_selling_price >= 0)',
        'previous_cost_price' => 'ALTER TABLE stock_ledger ADD COLUMN previous_cost_price REAL CHECK (previous_cost_price IS NULL OR previous_cost_price >= 0)',
        'previous_selling_price' => 'ALTER TABLE stock_ledger ADD COLUMN previous_selling_price REAL CHECK (previous_selling_price IS NULL OR previous_selling_price >= 0)',
    ];
    foreach ($ledgerUpgrades as $columnName => $upgradeSql) {
        if (!isset($ledgerColumns[$columnName])) {
            $db->exec($upgradeSql);
        }
    }

    // Composite foreign keys are enabled for every connection by MyDB.
    // These triggers also protect existing databases created before the
    // composite constraints were added.
    $integrityTriggers = [

        <<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_products_category_company_insert
        BEFORE INSERT ON products
        WHEN NEW.category_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1
             FROM product_categories
             WHERE category_id = NEW.category_id
               AND cid = NEW.cid
         )
        BEGIN
            SELECT RAISE(ABORT, 'Product category belongs to a different company');
        END;
        SQL,

        <<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_products_category_company_update
        BEFORE UPDATE OF category_id, cid ON products
        WHEN NEW.category_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1
             FROM product_categories
             WHERE category_id = NEW.category_id
               AND cid = NEW.cid
         )
        BEGIN
            SELECT RAISE(ABORT, 'Product category belongs to a different company');
        END;
        SQL,

        <<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_inventory_product_company_insert
        BEFORE INSERT ON inventory
        WHEN NOT EXISTS (
            SELECT 1
            FROM products
            WHERE product_id = NEW.product_id
              AND cid = NEW.cid
        )
        BEGIN
            SELECT RAISE(ABORT, 'Inventory product belongs to a different company');
        END;
        SQL,

        <<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_inventory_product_company_update
        BEFORE UPDATE OF product_id, cid ON inventory
        WHEN NOT EXISTS (
            SELECT 1
            FROM products
            WHERE product_id = NEW.product_id
              AND cid = NEW.cid
        )
        BEGIN
            SELECT RAISE(ABORT, 'Inventory product belongs to a different company');
        END;
        SQL,

        <<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_stock_ledger_product_company_insert
        BEFORE INSERT ON stock_ledger
        WHEN NOT EXISTS (
            SELECT 1
            FROM products
            WHERE product_id = NEW.product_id
              AND cid = NEW.cid
        )
        BEGIN
            SELECT RAISE(ABORT, 'Ledger product belongs to a different company');
        END;
        SQL,

        <<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_stock_ledger_product_company_update
        BEFORE UPDATE OF product_id, cid ON stock_ledger
        WHEN NOT EXISTS (
            SELECT 1
            FROM products
            WHERE product_id = NEW.product_id
              AND cid = NEW.cid
        )
        BEGIN
            SELECT RAISE(ABORT, 'Ledger product belongs to a different company');
        END;
        SQL,
    ];

    foreach ($integrityTriggers as $sql) {
        $db->exec($sql);
    }

    // INDEXES
    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_product_company
            ON products(cid)',
        'CREATE INDEX IF NOT EXISTS idx_product_category
            ON products(category_id)',
        'CREATE INDEX IF NOT EXISTS idx_inventory_company
            ON inventory(cid)',
        'CREATE INDEX IF NOT EXISTS idx_stock_ledger_product
            ON stock_ledger(product_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_stock_ledger_company
            ON stock_ledger(cid, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_stock_ledger_partner
            ON stock_ledger(partner_id, cid, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_stock_take_sessions_company
            ON stock_take_sessions(cid, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_stock_take_lines_session
            ON stock_take_lines(stock_take_id, cid)',
    ];

    foreach ($indexes as $sql) {
        $db->exec($sql);
    }

    $db->exec("INSERT OR IGNORE INTO app_settings (setting_key, setting_value)
        VALUES ('schema_version', '4')");

    // Apply the shared feature migrations and seed company-scoped defaults.
    require_once __DIR__ . '/INC/schema_extensions.php';
    ensureStockKeeperSchemaExtensions($db);
    $companies = $db->query('SELECT cid FROM company ORDER BY cid');
    while ($company = $companies->fetchArray(SQLITE3_ASSOC)) {
        ensureStockKeeperDefaultPartners($db, (int)$company['cid']);
    }

    $db->exec('COMMIT');

    echo 'Stock Keeper database initialized successfully.';

    $db->close();

} catch (Throwable $e) {
    if (isset($db) && $db instanceof SQLite3) {
        try {
            $db->exec('ROLLBACK');
        } catch (Throwable $ignored) {
            // No active transaction to roll back.
        }

        $db->close();
    }

    http_response_code(500);

    // Log detailed errors privately in production.
    error_log('Stock Keeper installer error: ' . $e->getMessage());

    echo 'Database initialization failed. Check the PHP error log.';
    exit(1);
}
