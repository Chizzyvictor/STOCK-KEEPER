<?php
declare(strict_types=1);

function apiFetchOne(SQLite3 $db, string $sql, array $params = []): ?array
{
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row === false ? null : $row;
}
function apiQuery(SQLite3 $db, string $sql, array $params = []): SQLite3Result
{
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    return $stmt->execute();
}
require_once dirname(__DIR__) . '/PartnerContactImporter.php';

function importAssert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

$db = new SQLite3(':memory:');
$db->enableExceptions(true);
$db->exec('CREATE TABLE partners (
    partner_id INTEGER PRIMARY KEY AUTOINCREMENT, cid INTEGER NOT NULL, name TEXT NOT NULL COLLATE NOCASE,
    phone TEXT, email TEXT COLLATE NOCASE, address TEXT, role TEXT NOT NULL, status TEXT NOT NULL,
    notes TEXT, is_system INTEGER NOT NULL DEFAULT 0, customer_outstanding REAL NOT NULL DEFAULT 0, supplier_outstanding REAL NOT NULL DEFAULT 0,
    customer_advance REAL NOT NULL DEFAULT 0, supplier_advance REAL NOT NULL DEFAULT 0,
    deleted_at TEXT, UNIQUE(cid, name)
)');
$db->exec("INSERT INTO partners (cid, name, email, role, status) VALUES (1, 'Existing Contact', NULL, 'customer', 'active'), (1, 'Email Owner', 'owned@example.com', 'both', 'active'), (2, 'Other Account', NULL, 'both', 'active')");
$db->exec("INSERT INTO partners (cid, name, role, status, is_system) VALUES (1, 'Walk-in Customer', 'customer', 'active', 1), (1, 'Archived Contact', 'both', 'inactive', 0)");
$db->exec("UPDATE partners SET deleted_at = CURRENT_TIMESTAMP WHERE cid = 1 AND name = 'Archived Contact'");
$importer = new PartnerContactImporter($db, 1);
$contacts = [
    ['name'=>'New Contact', 'email'=>'NEW@example.com', 'role'=>'supplier', 'phone'=>'123'],
    ['name'=>'Existing Contact'],
    ['name'=>'new contact', 'email'=>'other@example.com'],
    ['name'=>'Bad Email', 'email'=>'invalid'],
    ['name'=>'Email Collision', 'email'=>'new@example.com'],
    ['name'=>'Different Name', 'email'=>'OWNED@example.com'],
    ['name'=>'New Second Contact', 'email'=>'ok@example.com'],
];
$preview = $importer->preview($contacts);
importAssert($preview['counts']['ready'] === 2, 'Only the two unique, valid new contacts should be ready.');
importAssert($preview['counts']['existing'] === 1, 'Existing name should be skipped.');
importAssert($preview['counts']['conflict'] === 3, 'In-file duplicates and existing email matches should be conflicts.');
importAssert($preview['counts']['invalid'] === 1, 'Malformed email should be invalid.');
$largerPreview = $importer->preview(array_map(static fn(int $i): array => ['name'=>'Bulk Contact ' . $i], range(1, 501)));
importAssert($largerPreview['total'] === 501 && $largerPreview['counts']['ready'] === 501, 'A file with more than 500 contacts should preview successfully.');
$result = $importer->import($contacts);
importAssert($result['created'] === 2, 'Import should create only ready contacts.');
importAssert($result['counts']['ready'] === 0 && $result['counts']['imported'] === 2, 'Imported rows must not remain eligible for a repeat import.');
importAssert((int)$db->querySingle('SELECT COUNT(*) FROM partners WHERE cid = 1') === 6, 'Import should remain scoped to the current account.');
importAssert((int)$db->querySingle("SELECT COUNT(*) FROM partners WHERE cid = 1 AND name = 'New Contact' COLLATE NOCASE AND role = 'supplier' AND customer_outstanding = 0 AND supplier_outstanding = 0") === 1, 'Created partner should preserve role and begin with zero balances.');
$export = $importer->exportContacts();
importAssert(count($export) === 4, 'Export should include non-system, non-archived contacts from the current account only.');
importAssert(!in_array('Walk-in Customer', array_column($export, 'name'), true) && !in_array('Archived Contact', array_column($export, 'name'), true) && !in_array('Other Account', array_column($export, 'name'), true), 'Export must exclude system, archived, and other-account partners.');

$db->exec("CREATE TRIGGER fail_contact BEFORE INSERT ON partners WHEN NEW.name = 'Trigger Failure' BEGIN SELECT RAISE(ABORT, 'simulated'); END");
$before = (int)$db->querySingle('SELECT COUNT(*) FROM partners WHERE cid = 1');
$failed = false;
try { $importer->import([['name'=>'Will Roll Back'], ['name'=>'Trigger Failure']]); } catch (Throwable) { $failed = true; }
importAssert($failed, 'Injected database error should fail the import.');
importAssert((int)$db->querySingle('SELECT COUNT(*) FROM partners WHERE cid = 1') === $before, 'Failed import must roll back all partner rows.');

echo "PASS: validation, duplicate/conflict detection, account scoping, existing-data preservation, zero balances, repeat safety, and rollback.\n";
