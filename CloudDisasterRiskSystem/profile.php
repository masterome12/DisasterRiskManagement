<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pageTitle = 'My Profile — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$user = current_user();
?>
<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-person-circle" style="font-size:3rem;"></i>
    <div>
      <h4 class="mb-0"><?= htmlspecialchars($user['full_name']) ?></h4>
      <span class="text-muted"><?= htmlspecialchars($user['email']) ?> &middot; <span class="badge text-bg-secondary"><?= htmlspecialchars(role_label($user['role'])) ?></span></span>
    </div>
  </div>

  <h5><i class="bi bi-clock-history"></i> My Submitted Reports</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle" id="myReportsTable">
      <thead><tr><th>Date</th><th>Hazard</th><th>Severity</th><th>Status</th><th>Note</th></tr></thead>
      <tbody><tr><td colspan="5" class="text-muted">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<script>
(async () => {
  const res = await fetch(window.BASE_URL + '/api/incidents.php?action=mine');
  const data = await res.json();
  const tbody = document.querySelector('#myReportsTable tbody');
  if (!data.incidents || !data.incidents.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted">You have not submitted any reports yet.</td></tr>';
    return;
  }
  const statusBadge = s => ({
    pending: '<span class="badge text-bg-warning">Pending Review</span>',
    approved: '<span class="badge text-bg-success">Approved</span>',
    declined: '<span class="badge text-bg-danger">Declined</span>',
  })[s] || s;
  tbody.innerHTML = data.incidents.map(r => `
    <tr>
      <td>${new Date(r.created_at).toLocaleDateString()}</td>
      <td>${r.hazard_type}</td>
      <td class="text-capitalize">${r.severity}</td>
      <td>${statusBadge(r.status)}</td>
      <td class="text-muted small">${r.moderation_note ?? ''}</td>
    </tr>`).join('');
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
