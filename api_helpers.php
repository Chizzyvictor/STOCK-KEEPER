<?php
declare(strict_types=1);

require_once __DIR__ . '/INC/session.php';
require_once __DIR__ . '/INC/database.php';
stockKeeperStartSession();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function apiRespond(string $status, string $message = '', array $data = [], int $httpCode = 200): never
{
    if ($status === 'error' && $httpCode === 200) $httpCode = 400;
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function apiTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function apiTrim(mixed $value): string
{
    return trim((string) ($value ?? ''));
}

function apiInput(string $key, string $default = ''): string
{
    return apiTrim($_POST[$key] ?? $default);
}

function apiValidEmail(string $value): bool
{
    return strlen($value) <= 190 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function apiValidPassword(string $value): bool
{
    return strlen($value) >= 8 && strlen($value) <= 72;
}

function apiValidMoney(string $value, int $decimalPlaces = 2): bool
{
    if ($decimalPlaces < 0 || $decimalPlaces > 6) return false;
    $fraction = $decimalPlaces === 0 ? '' : '(?:\\.\\d{1,' . $decimalPlaces . '})?';
    return preg_match('/^\\d{1,12}' . $fraction . '$/', $value) === 1;
}

function apiValidQuantity(string $value): bool
{
    return preg_match('/^\\d{1,12}(?:\\.\\d{1,6})?$/', $value) === 1 && (float) $value <= 1000000000;
}

function apiValidDate(string $value): bool
{
    if ($value === '') return true;
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value) !== 1) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function apiExecuteStatement(SQLite3Stmt $statement, array $params = []): SQLite3Result
{
    foreach ($params as $key => $value) {
        $type = match (true) {
            is_int($value) => SQLITE3_INTEGER,
            is_float($value) => SQLITE3_FLOAT,
            $value === null => SQLITE3_NULL,
            default => SQLITE3_TEXT,
        };
        $parameter = is_int($key) || preg_match('/^[:@$]/', (string) $key) ? $key : ':' . $key;
        $statement->bindValue($parameter, $value, $type);
    }
    $result = $statement->execute();
    if (!$result instanceof SQLite3Result) throw new RuntimeException('SQLite statement execution failed.');
    return $result;
}


function apiQuery(SQLite3 $db, string $sql, array $params = []): SQLite3Result
{
    $statement = $db->prepare($sql);
    if (!$statement instanceof SQLite3Stmt) throw new RuntimeException('Unable to prepare database query: ' . $db->lastErrorMsg());
    return apiExecuteStatement($statement, $params);
}

function apiFetchAll(SQLite3Result $result): array
{
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
    return $rows;
}

function apiFetchOne(SQLite3 $db, string $sql, array $params = []): ?array
{
    $statement = $db->prepare($sql);
    if (!$statement instanceof SQLite3Stmt) throw new RuntimeException('Unable to prepare database query.');
    try {
        $result = apiExecuteStatement($statement, $params);
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

/** Parse JSON bodies while keeping standard form and file-upload requests intact. */
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $jsonInput = is_string($rawInput) ? json_decode($rawInput, true) : null;
    if (is_array($jsonInput)) $_POST = array_merge($_POST, $jsonInput);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    apiRespond('error', 'POST request required.', [], 405);
}

if (!defined('API_AUTHENTICATION_ENDPOINT')) {
    if (empty($_SESSION['isLogedin']) || empty($_SESSION['cid']) || (int) $_SESSION['cid'] < 1) {
        apiRespond('error', 'Your session has expired. Please log in again.', [], 401);
    }
    $activity = $_SESSION['last_activity'] ?? null;
    if (!is_numeric($activity) || time() - (int) $activity > 15000 || time() - (int) $activity < 0) {
        $_SESSION = [];
        session_destroy();
        apiRespond('error', 'Your session has expired. Please log in again.', [], 401);
    }
    $_SESSION['last_activity'] = time();
}

$sessionCsrfToken = (string) ($_SESSION['csrf_token'] ?? '');
$requestCsrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
if ($sessionCsrfToken === '' || $requestCsrfToken === '' || !hash_equals($sessionCsrfToken, $requestCsrfToken)) {
    apiRespond('error', 'Security token is invalid. Refresh the page and try again.', [], 403);
}
