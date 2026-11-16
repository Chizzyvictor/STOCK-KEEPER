<?php

declare(strict_types=1);

/**
 * Stock Keeper — Product Units Migration
 *
 * Creates the product_units table without modifying existing products,
 * inventory, categories or stock ledger records.
 *
 * Run this migration once, then remove or protect this file.
 */

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/INC/database.php';

$db = null;
$transactionStarted = false;

try {
    // ----------------------------------------------
    // 1. DATABASE CONNECTION
    // ----------------------------------------------

    $db = stockKeeperOpenDatabase();

    if (method_exists($db, 'enableExceptions')) {
        $db->enableExceptions(true);
    }


    // ----------------------------------------------
    // 2. VERIFY REQUIRED COMPANY TABLE
    // ----------------------------------------------

    $companyTableExists = (int) $db->querySingle(
        "SELECT COUNT(*)
         FROM sqlite_master
         WHERE type = 'table'
           AND name = 'company'"
    );

    if ($companyTableExists !== 1) {
        throw new RuntimeException(
            'The company table does not exist. Check the database.'
        );
    }

    // ----------------------------------------------
    // 3. BEGIN TRANSACTION
    // ----------------------------------------------

    $db->exec('BEGIN IMMEDIATE TRANSACTION');
    $transactionStarted = true;

    // ----------------------------------------------
    // 4. CREATE PRODUCT UNITS TABLE
    // ----------------------------------------------

    $db->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS product_units (
            unit_id INTEGER PRIMARY KEY AUTOINCREMENT,

            cid INTEGER NOT NULL,

            unit_name TEXT NOT NULL
                CHECK (length(trim(unit_name)) > 0),

            unit_symbol TEXT NOT NULL DEFAULT '',

            description TEXT NOT NULL DEFAULT '',

            is_active INTEGER NOT NULL DEFAULT 1
                CHECK (is_active IN (0, 1)),

            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (cid)
                REFERENCES company(cid)
                ON DELETE CASCADE
        );
        SQL
    );

    // ----------------------------------------------
    // 5. CREATE INDEXES
    // ----------------------------------------------

    // Prevent duplicate unit names within the same company.
    $db->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS
            idx_product_units_company_name
         ON product_units(cid, unit_name COLLATE NOCASE)'
    );

    // Speed up company-specific unit listings.
    $db->exec(
        'CREATE INDEX IF NOT EXISTS
            idx_product_units_company_status
         ON product_units(cid, is_active, unit_name)'
    );

    // ----------------------------------------------
    // 6. UPDATE SCHEMA VERSION
    // ----------------------------------------------

    $versionStmt = $db->prepare(
        "INSERT INTO app_settings (setting_key, setting_value)
         VALUES ('schema_version', '4')
         ON CONFLICT(setting_key)
         DO UPDATE SET setting_value = '4'"
    );

    if (!$versionStmt instanceof SQLite3Stmt) {
        throw new RuntimeException(
            'Unable to prepare schema version update.'
        );
    }

    $versionResult = $versionStmt->execute();

    if ($versionResult instanceof SQLite3Result) {
        $versionResult->finalize();
    }

    $versionStmt->close();

    // ----------------------------------------------
    // 7. COMMIT
    // ----------------------------------------------

    $db->exec('COMMIT');
    $transactionStarted = false;

    echo "SUCCESS: Product Units migration completed.\n\n";
    echo "Created or verified:\n";
    echo "- product_units table\n";
    echo "- Unique company/unit-name index\n";
    echo "- Company/status index\n";
    echo "- Schema version 4\n\n";
    echo "Existing product and stock records were not modified.\n";
    echo "You can now remove this migration file.";

} catch (Throwable $e) {

    if ($db instanceof SQLite3 && $transactionStarted) {
        try {
            $db->exec('ROLLBACK');
        } catch (Throwable $ignored) {
            // Ignore rollback errors.
        }
    }

    error_log(
        'Product Units migration error: ' . $e->getMessage()
    );

    http_response_code(500);

    echo "Migration failed.\n";
    echo "Check the PHP error log for details.";
    $migrationExitCode = 1;

} finally {

    if ($db instanceof SQLite3) {
        $db->close();
    }
}

exit($migrationExitCode ?? 0);
