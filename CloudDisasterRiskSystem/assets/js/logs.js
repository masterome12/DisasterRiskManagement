// History Log — browse the audit_log table (admin only).
(function () {
  // Actions that represent something going wrong / worth attention get a
  // warning/danger badge; everything else is a neutral "info" badge, so
  // scanning a long log for trouble doesn't require reading every row.
  const SEVERITY = {
    login_failed: 'warning',
    login_blocked_locked: 'danger',
    account_locked: 'danger',
    login_blocked_inactive: 'danger',
    unauthorized_access: 'danger',
    unauthorized_api_access: 'danger',
    user_active_toggled: 'warning',
    incident_moderated: 'info',
    layer_deleted: 'warning',
  };
  function badgeClass(action) {
    return 'text-bg-' + (SEVERITY[action] || 'secondary');
  }
  function actionLabel(action) {
    return action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
  // Same relabel as everywhere else: the stored role stays 'resident',
  // users just see it called "User".
  const ROLE_LABELS = { resident: 'User', drrmo: 'DRRMO', admin: 'Admin' };

  // ---- Filter option population -------------------------------------------
  fetch(window.BASE_URL + '/api/audit_log.php?action=meta').then(r => r.json()).then(data => {
    document.getElementById('logTotalBadge').textContent = data.total.toLocaleString();

    const typeSel = document.getElementById('logType');
    data.types.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = actionLabel(t); typeSel.appendChild(o); });

    const userSel = document.getElementById('logUser');
    data.users.forEach(u => { const o = document.createElement('option'); o.value = u.id; o.textContent = `${u.full_name} (@${u.username})`; userSel.appendChild(o); });
  }).catch(() => {});

  window.loadAuditLog = async function () {
    const params = new URLSearchParams({ action: 'list', limit: document.getElementById('logLimit').value });
    const type = document.getElementById('logType').value;
    const user = document.getElementById('logUser').value;
    const from = document.getElementById('logDateFrom').value;
    const to = document.getElementById('logDateTo').value;
    const q = document.getElementById('logSearch').value.trim();
    if (type) params.set('type', type);
    if (user) params.set('user_id', user);
    if (from) params.set('date_from', from);
    if (to) params.set('date_to', to);
    if (q) params.set('q', q);

    const tbody = document.getElementById('logTable');
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Loading…</td></tr>';

    const res = await fetch(window.BASE_URL + '/api/audit_log.php?' + params.toString());
    const data = await res.json();
    if (!res.ok || !data.rows) { tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Failed to load.</td></tr>'; return; }

    if (!data.rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No log entries match these filters.</td></tr>';
      document.getElementById('logShownNote').textContent = '';
      return;
    }

    tbody.innerHTML = data.rows.map(r => `
      <tr>
        <td class="text-nowrap">${new Date(r.created_at.replace(' ', 'T')).toLocaleString()}</td>
        <td><span class="badge ${badgeClass(r.action)}">${actionLabel(r.action)}</span></td>
        <td>${r.full_name ? `${r.full_name}<div class="text-muted small">@${r.username} · ${ROLE_LABELS[r.role] || r.role}</div>` : '<span class="text-muted">—</span>'}</td>
        <td class="small">${r.details ?? ''}</td>
        <td class="text-muted small">${r.ip_address ?? ''}</td>
      </tr>`).join('');
    document.getElementById('logShownNote').textContent =
      `Showing ${data.rows.length} of ${data.total.toLocaleString()} matching entr${data.total === 1 ? 'y' : 'ies'}, most recent first.`;
  };

  loadAuditLog();
})();
