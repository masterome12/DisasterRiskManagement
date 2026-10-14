<?php $pageTitle = 'Not Authorized'; require_once __DIR__ . '/includes/header.php'; ?>
<div class="container py-5 text-center">
  <i class="bi bi-slash-circle text-danger" style="font-size:3rem;"></i>
  <h2 class="mt-3">Not Authorized</h2>
  <p class="text-muted">You don't have permission to view this page with your current role.</p>
  <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-danger">Back to Risk Map</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
