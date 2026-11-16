<?php
declare(strict_types=1);

$sessionActivity = $_SESSION['last_activity'] ?? null;
if (empty($_SESSION['isLogedin']) || empty($_SESSION['cid']) || (int)$_SESSION['cid'] < 1
    || !is_numeric($sessionActivity) || time() - (int)$sessionActivity > 15000 || time() - (int)$sessionActivity < 0) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();
?>
