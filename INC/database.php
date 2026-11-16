<?php
declare(strict_types=1);

/**
 * Database location is configurable so production can keep SQLite outside the
 * document root. The project-local fallback preserves existing local installs.
 */
function stockKeeperDatabasePath(): string
{
    $configured = getenv('STOCK_KEEPER_DB_PATH');
    if ($configured === false || trim($configured) === '') {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'mysqlitedb.db';
    }

    $path = trim($configured);
    $absolute = str_starts_with($path, DIRECTORY_SEPARATOR)
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    if (!$absolute) {
        throw new RuntimeException('STOCK_KEEPER_DB_PATH must be an absolute filesystem path.');
    }
    return $path;
}

/** Open the configured SQLite database with the application's required settings. */
function stockKeeperOpenDatabase(bool $create = false): SQLite3
{
    $path = stockKeeperDatabasePath();
    if (!$create && !is_file($path)) {
        throw new RuntimeException('Stock Keeper database file was not found. Check STOCK_KEEPER_DB_PATH.');
    }
    $flags = SQLITE3_OPEN_READWRITE | ($create ? SQLITE3_OPEN_CREATE : 0);
    $db = new SQLite3($path, $flags);
    if (method_exists($db, 'enableExceptions')) $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON');
    return $db;
}
