<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$activeNav = 'users';
$pageTitle = 'Admin & Users — ' . APP_NAME;
$pageHeading = 'Admin & Users';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="pagehead">
  <div><div class="kicker">Settings · Organisation</div><h1>Admin &amp; Users</h1></div>
</div>
<p class="text-muted small">Create, update roles, and deactivate accounts (FR-16).</p>

<div class="widget-card mb-4" style="height:auto;">
  <div class="widget-title">
    <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-person-plus"></i></span>
    Create New Account
  </div>
  <div id="createMsg" class="alert d-none"></div>
  <form id="createForm" class="row g-2">
    <div class="col-md-3"><input class="form-control" name="full_name" placeholder="Full name" required></div>
    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" name="username" placeholder="Username" required></div>
    <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="Password" minlength="8" required></div>
    <div class="col-md-1">
      <select class="form-select pill-select" name="role">
        <option value="resident">User</option>
        <option value="drrmo">DRRMO</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="col-md-1"><button class="btn btn-success w-100"><i class="bi bi-plus"></i></button></div>
  </form>
</div>

<div class="row g-2 mb-3" id="userKpiRow"></div>

<div class="widget-card" style="height:auto;">
  <div class="widget-title">
    <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-people"></i></span>
    All Accounts
    <span class="badge text-bg-light text-dark ms-2" id="usersCountBadge"></span>
  </div>
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <input class="form-control form-control-sm" id="userSearch" placeholder="Search name, email, or username…">
    </div>
    <div class="col-md-3">
      <select class="form-select form-select-sm pill-select" id="userRoleFilter">
        <option value="">All roles</option>
        <option value="resident">User</option>
        <option value="drrmo">DRRMO</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="col-md-3">
      <select class="form-select form-select-sm pill-select" id="userStatusFilter">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Disabled</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary btn-sm w-100" id="userFilterReset">Reset</button>
    </div>
  </div>
  <div class="table-responsive sticky-col-table">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Name</th><th>Email / Username</th><th>Role</th><th>Barangay</th><th>Status</th><th>Joined</th><th>Last Login</th><th></th></tr></thead>
      <tbody id="usersBody"><tr><td colspan="8" class="text-muted">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- View account modal -->
<div class="modal fade" id="userViewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-badge"></i> Account Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="userViewBody">
        <div class="text-muted">Loading…</div>
      </div>
    </div>
  </div>
</div>

<script>
// Same relabel as everywhere else in the app: the stored role value stays
// 'resident', users just see it called "User".
const ROLE_LABELS = { resident: 'User', drrmo: 'DRRMO', admin: 'Admin' };
let userViewModal;

// Relative time ("3 hours ago") computed against the server's own clock
// (server_time from the API response), not the admin's local system
// clock — a skewed local clock would otherwise silently misreport how
// long ago an account actually logged in.
function timeAgo(dateStr, nowStr) {
  if (!dateStr) return '<span class="text-muted">Never</span>';
  const then = new Date(dateStr.replace(' ', 'T'));
  const now = nowStr ? new Date(nowStr) : new Date();
  const seconds = Math.max(0, Math.floor((now - then) / 1000));
  const units = [
    ['year', 31536000], ['month', 2592000], ['day', 86400],
    ['hour', 3600], ['minute', 60],
  ];
  for (const [label, secs] of units) {
    const n = Math.floor(seconds / secs);
    if (n >= 1) return `${n} ${label}${n > 1 ? 's' : ''} ago`;
  }
  return 'Just now';
}

// Unfiltered counts for the KPI row — deliberately a separate call from
// loadUsers() below, since that one applies the search/role/status filters
// and would otherwise make "Total accounts" silently mean "matching rows".
async function loadUserKpis() {
  const res = await fetch(window.BASE_URL + '/api/users.php?action=list');
  const data = await res.json();
  if (!res.ok || !data.users) return;
  const total = data.users.length;
  const active = data.users.filter(u => u.is_active == 1).length;
  const byRole = { resident: 0, drrmo: 0, admin: 0 };
  data.users.forEach(u => { if (byRole[u.role] !== undefined) byRole[u.role]++; });
  document.getElementById('userKpiRow').innerHTML = `
    <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Total accounts</div><div class="kpi-value">${total}</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Active</div><div class="kpi-value">${active}</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Disabled</div><div class="kpi-value">${total - active}</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">By role</div><div class="small mt-1">
      <span class="badge text-bg-light border me-1">${byRole.resident} User</span>
      <span class="badge text-bg-light border me-1">${byRole.drrmo} DRRMO</span>
      <span class="badge text-bg-light border">${byRole.admin} Admin</span>
    </div></div></div>`;
}

async function loadUsers() {
  const params = new URLSearchParams({ action: 'list' });
  const q = document.getElementById('userSearch').value.trim();
  const role = document.getElementById('userRoleFilter').value;
  const status = document.getElementById('userStatusFilter').value;
  if (q) params.set('q', q);
  if (role) params.set('role', role);
  if (status) params.set('status', status);

  const tbody = document.getElementById('usersBody');
  tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Loading…</td></tr>';

  const res = await fetch(window.BASE_URL + '/api/users.php?' + params.toString());
  const data = await res.json();
  if (!res.ok || !data.users) { tbody.innerHTML = '<tr><td colspan="8" class="text-danger">Failed to load.</td></tr>'; return; }

  document.getElementById('usersCountBadge').textContent = `${data.users.length} account${data.users.length === 1 ? '' : 's'}`;

  if (!data.users.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No accounts match these filters.</td></tr>';
    return;
  }

  const serverTime = data.server_time;
  tbody.innerHTML = data.users.map(u => `
    <tr>
      <td>${u.full_name}</td>
      <td>${u.email}<br><span class="text-muted small">@${u.username}</span></td>
      <td>
        <select class="form-select form-select-sm pill-select" style="width:110px" onchange="changeRole(${u.id}, this.value)">
          ${['resident','drrmo','admin'].map(r => `<option value="${r}" ${r===u.role?'selected':''}>${ROLE_LABELS[r]}</option>`).join('')}
        </select>
      </td>
      <td>${u.barangay ?? '—'}</td>
      <td>
        <div class="form-check form-switch" title="${u.is_active == 1 ? 'Disable account' : 'Renew (re-enable) account'}">
          <input class="form-check-input" type="checkbox" ${u.is_active == 1 ? 'checked' : ''} onchange="toggleActive(${u.id}, this.checked, this)">
        </div>
      </td>
      <td>${new Date(u.created_at).toLocaleDateString()}</td>
      <td class="small" title="${u.last_login_at ? new Date(u.last_login_at.replace(' ', 'T')).toLocaleString() : ''}">${timeAgo(u.last_login_at, serverTime)}</td>
      <td><button class="btn btn-sm btn-outline-secondary" onclick="viewUser(${u.id})"><i class="bi bi-eye"></i> View</button></td>
    </tr>`).join('');
}

async function viewUser(id) {
  if (!userViewModal) userViewModal = new bootstrap.Modal(document.getElementById('userViewModal'));
  const body = document.getElementById('userViewBody');
  body.innerHTML = '<div class="text-muted">Loading…</div>';
  userViewModal.show();

  const res = await fetch(window.BASE_URL + '/api/users.php?action=view&id=' + id);
  const data = await res.json();
  if (!res.ok || !data.user) { body.innerHTML = `<div class="text-danger">${data.error || 'Failed to load account.'}</div>`; return; }

  const u = data.user, c = data.counts;
  const statusBadge = s => ({
    pending: '<span class="badge text-bg-warning">Pending</span>',
    approved: '<span class="badge text-bg-success">Approved</span>',
    declined: '<span class="badge text-bg-danger">Declined</span>',
  })[s] || s;

  body.innerHTML = `
    <div class="d-flex align-items-center gap-3 mb-3">
      <i class="bi bi-person-circle" style="font-size:2.5rem;"></i>
      <div>
        <h5 class="mb-0">${u.full_name}</h5>
        <span class="text-muted small">@${u.username} · ${ROLE_LABELS[u.role] || u.role}
          <span class="badge ${u.is_active == 1 ? 'text-bg-success' : 'text-bg-secondary'} ms-1">${u.is_active == 1 ? 'Active' : 'Disabled'}</span>
        </span>
      </div>
    </div>
    <dl class="row small mb-3">
      <dt class="col-4">Email</dt><dd class="col-8">${u.email}</dd>
      <dt class="col-4">Barangay</dt><dd class="col-8">${u.barangay ?? '—'}</dd>
      <dt class="col-4">Joined</dt><dd class="col-8">${new Date(u.created_at).toLocaleString()}</dd>
      <dt class="col-4">Last Login</dt><dd class="col-8">${u.last_login_at ? new Date(u.last_login_at.replace(' ', 'T')).toLocaleString() : '<span class="text-muted">Never logged in</span>'}</dd>
    </dl>
    <h6>Report Activity</h6>
    <div class="d-flex gap-2 mb-3">
      <span class="badge text-bg-light text-dark border">${c.total} total</span>
      <span class="badge text-bg-warning">${c.pending} pending</span>
      <span class="badge text-bg-success">${c.approved} approved</span>
      <span class="badge text-bg-danger">${c.declined} declined</span>
    </div>
    ${data.recent_reports.length ? `
      <h6>Recent Reports</h6>
      <table class="table table-sm">
        <thead><tr><th>Date</th><th>Hazard</th><th>Severity</th><th>Status</th></tr></thead>
        <tbody>${data.recent_reports.map(r => `
          <tr><td>${new Date(r.created_at).toLocaleDateString()}</td><td>${r.hazard_type}</td><td class="text-capitalize">${r.severity}</td><td>${statusBadge(r.status)}</td></tr>`).join('')}</tbody>
      </table>` : '<div class="text-muted small">No reports submitted yet.</div>'}
  `;
}

document.getElementById('userSearch').addEventListener('input', debounce(loadUsers, 300));
document.getElementById('userRoleFilter').addEventListener('change', loadUsers);
document.getElementById('userStatusFilter').addEventListener('change', loadUsers);
document.getElementById('userFilterReset').addEventListener('click', () => {
  document.getElementById('userSearch').value = '';
  document.getElementById('userRoleFilter').value = '';
  document.getElementById('userStatusFilter').value = '';
  loadUsers();
});
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

async function changeRole(id, role) {
  await fetch(window.BASE_URL + '/api/users.php?action=update_role', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id, role})
  });
}

async function toggleActive(id, active, checkbox) {
  const res = await fetch(window.BASE_URL + '/api/users.php?action=toggle_active', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id, active})
  });
  const data = await res.json();
  if (!res.ok) { alert(data.error); checkbox.checked = !active; }
  else loadUserKpis();
}

document.getElementById('createForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('createMsg');
  msg.className = 'alert d-none';
  const fd = new FormData(e.target);
  const res = await fetch(window.BASE_URL + '/api/users.php?action=create', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(fd.entries()))
  });
  const data = await res.json();
  msg.classList.remove('d-none');
  msg.classList.add(res.ok ? 'alert-success' : 'alert-danger');
  msg.textContent = data.message || data.error;
  if (res.ok) { e.target.reset(); loadUsers(); loadUserKpis(); }
});

loadUsers();
loadUserKpis();
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
