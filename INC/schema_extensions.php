<?php
declare(strict_types=1);

/** Ensure partner, signed-balance, movement-price, and stock-take schema exists. */
function ensureStockKeeperSchemaExtensions(SQLite3 $db): void
{
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS company_settings (
            cid INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cid, setting_key),
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
        )
    SQL);

    $db->exec(<<<'SQL'
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
        )
    SQL);

    $partnerColumns = [];
    $partnerResult = $db->query('PRAGMA table_info(partners)');
    while ($column = $partnerResult->fetchArray(SQLITE3_ASSOC)) {
        $partnerColumns[(string)$column['name']] = true;
    }
    if (!isset($partnerColumns['is_system'])) {
        $db->exec('ALTER TABLE partners ADD COLUMN is_system INTEGER NOT NULL DEFAULT 0 CHECK (is_system IN (0,1))');
    }
    if (!isset($partnerColumns['system_key'])) {
        $db->exec('ALTER TABLE partners ADD COLUMN system_key TEXT');
    }

    // Existing installations can gain these fields without losing ledger rows.
    $columns = [];
    $result = $db->query('PRAGMA table_info(stock_ledger)');
    while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[(string)$column['name']] = true;
    }

    $upgrades = [
        'partner_id' => 'ALTER TABLE stock_ledger ADD COLUMN partner_id INTEGER',
        'unit_price' => 'ALTER TABLE stock_ledger ADD COLUMN unit_price REAL NOT NULL DEFAULT 0 CHECK (unit_price >= 0)',
        'total_amount' => 'ALTER TABLE stock_ledger ADD COLUMN total_amount REAL NOT NULL DEFAULT 0 CHECK (total_amount >= 0)',
        'unit_cost' => 'ALTER TABLE stock_ledger ADD COLUMN unit_cost REAL CHECK (unit_cost IS NULL OR unit_cost >= 0)',
        'unit_selling_price' => 'ALTER TABLE stock_ledger ADD COLUMN unit_selling_price REAL CHECK (unit_selling_price IS NULL OR unit_selling_price >= 0)',
        'previous_cost_price' => 'ALTER TABLE stock_ledger ADD COLUMN previous_cost_price REAL CHECK (previous_cost_price IS NULL OR previous_cost_price >= 0)',
        'previous_selling_price' => 'ALTER TABLE stock_ledger ADD COLUMN previous_selling_price REAL CHECK (previous_selling_price IS NULL OR previous_selling_price >= 0)',
    ];
    foreach ($upgrades as $name => $sql) {
        if (!isset($columns[$name])) {
            $db->exec($sql);
        }
    }

    $db->exec(<<<'SQL'
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
        )
    SQL);

    $partnerLedgerColumns = [];
    $partnerLedgerResult = $db->query('PRAGMA table_info(partner_ledger)');
    while ($column = $partnerLedgerResult->fetchArray(SQLITE3_ASSOC)) {
        $partnerLedgerColumns[(string)$column['name']] = true;
    }
    if (!isset($partnerLedgerColumns['balance_effect'])) {
        $db->exec('ALTER TABLE partner_ledger ADD COLUMN balance_effect REAL');
    }

    // Backfill a signed net-balance impact for existing account activity.
    $db->exec("UPDATE partner_ledger SET balance_effect = CASE
        WHEN entry_type IN ('sale', 'purchase') THEN amount
        WHEN entry_type IN ('payment', 'refund') THEN -amount
        ELSE 0 END
        WHERE entry_type <> 'opening' AND balance_effect IS NULL");
    $db->exec("UPDATE partner_ledger AS opening
        SET balance_effect = CASE
            WHEN opening.entry_id = (
                SELECT MIN(first_opening.entry_id) FROM partner_ledger first_opening
                WHERE first_opening.partner_id = opening.partner_id
                  AND first_opening.cid = opening.cid
                  AND first_opening.account_type = opening.account_type
                  AND first_opening.entry_type = 'opening'
            ) THEN
                COALESCE((SELECT CASE opening.account_type
                    WHEN 'customer' THEN p.customer_outstanding - p.customer_advance
                    ELSE p.supplier_outstanding - p.supplier_advance END
                    FROM partners p WHERE p.partner_id = opening.partner_id AND p.cid = opening.cid), 0)
                - COALESCE((SELECT SUM(activity.balance_effect) FROM partner_ledger activity
                    WHERE activity.partner_id = opening.partner_id
                      AND activity.cid = opening.cid
                      AND activity.account_type = opening.account_type
                      AND activity.entry_type <> 'opening'), 0)
            ELSE 0 END
        WHERE opening.entry_type = 'opening' AND opening.balance_effect IS NULL");

    $db->exec(<<<'SQL'
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
        )
    SQL);

    $db->exec(<<<'SQL'
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
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TRIGGER IF NOT EXISTS trg_stock_ledger_partner_company_insert
        BEFORE INSERT ON stock_ledger
        WHEN NEW.partner_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1 FROM partners
             WHERE partner_id = NEW.partner_id AND cid = NEW.cid
         )
        BEGIN
            SELECT RAISE(ABORT, 'Stock movement partner belongs to a different company');
        END
    SQL);

    $db->exec('CREATE INDEX IF NOT EXISTS idx_partners_company ON partners(cid, status, name)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_partners_system_key ON partners(cid, system_key) WHERE system_key IS NOT NULL');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_partner_ledger_partner ON partner_ledger(partner_id, cid, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_stock_ledger_partner ON stock_ledger(partner_id, cid, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_stock_take_sessions_company ON stock_take_sessions(cid, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_stock_take_lines_session ON stock_take_lines(stock_take_id, cid)');
}

/** Seed company-scoped default counterparties used for cash and generic transactions. */
function ensureStockKeeperDefaultPartners(SQLite3 $db, int $cid): void
{
    if ($cid <= 0) return;
    $defaults = [
        ['key'=>'walk_in_customer', 'name'=>'Walk-in Customer', 'role'=>'customer', 'notes'=>'System partner for immediate or walk-in sales.'],
        ['key'=>'general_supplier', 'name'=>'General Supplier', 'role'=>'supplier', 'notes'=>'System partner for generic or unassigned purchases.'],
    ];
    foreach ($defaults as $default) {
        $findSystem = $db->prepare('SELECT partner_id FROM partners WHERE cid = :cid AND system_key = :system_key LIMIT 1');
        $findSystem->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $findSystem->bindValue(':system_key', $default['key'], SQLITE3_TEXT);
        $systemResult = $findSystem->execute();
        if ($systemResult->fetchArray(SQLITE3_ASSOC)) continue;

        $name = $default['name'];
        $suffix = 1;
        do {
            $findName = $db->prepare('SELECT partner_id FROM partners WHERE cid = :cid AND name = :name COLLATE NOCASE LIMIT 1');
            $findName->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $findName->bindValue(':name', $name, SQLITE3_TEXT);
            $nameResult = $findName->execute();
            if (!$nameResult->fetchArray(SQLITE3_ASSOC)) break;
            $suffix++;
            $name = $default['name'] . ' (' . $suffix . ')';
        } while ($suffix < 1000);

        $insert = $db->prepare('INSERT OR IGNORE INTO partners (cid, name, role, status, notes, is_system, system_key)
            VALUES (:cid, :name, :role, :status, :notes, 1, :system_key)');
        $insert->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $insert->bindValue(':name', $name, SQLITE3_TEXT);
        $insert->bindValue(':role', $default['role'], SQLITE3_TEXT);
        $insert->bindValue(':status', 'active', SQLITE3_TEXT);
        $insert->bindValue(':notes', $default['notes'], SQLITE3_TEXT);
        $insert->bindValue(':system_key', $default['key'], SQLITE3_TEXT);
        $insert->execute();
    }
}
