<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
start_session();
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset_url('/assets/img/favicon.svg') ?>">

<!-- Bootstrap 5 (CSS framework) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Leaflet (map rendering) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php $flashMsg = flash('success'); $flashErr = flash('error'); ?>
<?php if ($flashMsg): ?>
  <div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flashMsg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
<?php endif; ?>
<?php if ($flashErr): ?>
  <div class="container mt-3">
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flashErr) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
<?php endif; ?>
