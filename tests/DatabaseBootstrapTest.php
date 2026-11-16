<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/INC/database.php';

function bootstrapAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$path = tempnam(sys_get_temp_dir(), 'stock-keeper-bootstrap-');
if ($path === false) throw new RuntimeException('Unable to create a temporary database path.');
$db = null;
try {
    putenv('STOCK_KEEPER_DB_PATH=' . $path);
    ob_start();
    require dirname(__DIR__) . '/exe.php';
    ob_end_clean();

    $db = stockKeeperOpenDatabase();
    bootstrapAssert((int)$db->querySingle('SELECT COUNT(*) FROM company') === 0, 'Fresh initialization must not create a placeholder business account.');
    bootstrapAssert((string)$db->querySingle("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'") === '4', 'Fresh initialization must record the current schema version.');
    bootstrapAssert((int)$db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'product_units'") === 1, 'Fresh initialization must create the product units table.');
    bootstrapAssert((int)$db->querySingle('PRAGMA foreign_keys') === 1, 'Application database connections must enable foreign keys.');
    $db->close();
    $db = null;

    putenv('STOCK_KEEPER_DB_PATH=relative/database.db');
    $rejected = false;
    try { stockKeeperDatabasePath(); } catch (RuntimeException) { $rejected = true; }
    bootstrapAssert($rejected, 'Relative deployment database paths must be rejected.');

    echo "PASS: configurable database path, fresh initialization without placeholder accounts, and connection safety defaults.\n";
} finally {
    if ($db instanceof SQLite3) $db->close();
    putenv('STOCK_KEEPER_DB_PATH');
    foreach ([$path, $path . '-wal', $path . '-shm'] as $file) if (is_file($file)) unlink($file);
}
