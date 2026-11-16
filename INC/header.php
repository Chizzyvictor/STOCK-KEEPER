<!DOCTYPE html>
<html lang="en">
<head>
<title>Stock Keeper</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
<meta name="description" content="Manage products, stock movements, partners, and inventory reports.">
<meta name="keywords" content="inventory, stock, products, reports">
<meta name="author" content="Chivicks Technology">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<link rel="shortcut icon" href="Images/companyDP/logo.jpg" type="image/x-icon">
<!-- Bootstrap 5 has no jQuery dependency. -->
<link rel="stylesheet" href="assets/vendor/css/bootstrap-5.3.8.min.css">

<!-- ✅ Font Awesome 5 (local vendor) -->
<link rel="stylesheet" href="assets/vendor/css/fontawesome-5.15.4-all.min.css">

<!-- ✅ Your custom stylesheet -->
<link rel="stylesheet" href="styles/style.css" type="text/css"/>
</head>

<body>
<div class="container-fluid">

<?php include __DIR__ . "/modals.php"; ?>

<div class="header">
  <div class="h_left"><img id="cLogo" src="" class="logo image"></div>
  <div class="h_right">Stock Keeper</div>
</div>

<div class="tab">
