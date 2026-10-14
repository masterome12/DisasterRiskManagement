// ---------------------------------------------------------------------
// Logout confirmation — applies to every "Logout" link on every page
// (public navbar, admin sidebar, admin dropdown) without needing each
// template to wire it up individually. The modal markup is injected once
// on demand rather than duplicated across header.php/admin_header.php.
// ---------------------------------------------------------------------
(function () {
  const logoutLinks = document.querySelectorAll('a[href$="/logout.php"], a[href="logout.php"]');
  if (!logoutLinks.length) return;

  let modal = null;
  let pendingHref = null;

  function ensureModal() {
    if (modal) return modal;
    const div = document.createElement('div');
    div.innerHTML = `
      <div class="modal fade" id="logoutConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
          <div class="modal-content">
            <div class="modal-header">
              <h6 class="modal-title"><i class="bi bi-box-arrow-right"></i> Log Out</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">Are you sure you want to log out?</div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-danger btn-sm" id="logoutConfirmBtn">Log Out</button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(div.firstElementChild);
    modal = new bootstrap.Modal(document.getElementById('logoutConfirmModal'));
    document.getElementById('logoutConfirmBtn').addEventListener('click', () => {
      if (pendingHref) window.location.href = pendingHref;
    });
    return modal;
  }

  logoutLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      pendingHref = link.getAttribute('href');
      ensureModal().show();
    });
  });
})();

// FR-17 System Notifications — polls the current user's notification feed.
(function () {
  const notifDropdown = document.getElementById('notifDropdown');
  if (!notifDropdown) return; // not logged in

  async function loadNotifications() {
    try {
      const res = await fetch(window.BASE_URL + '/api/notifications.php?action=list');
      if (!res.ok) return;
      const data = await res.json();
      const list = document.getElementById('notifList');
      const countBadge = document.getElementById('notifCount');
      const unread = data.notifications.filter(n => !n.is_read);

      if (unread.length > 0) {
        countBadge.textContent = unread.length;
        countBadge.classList.remove('d-none');
      } else {
        countBadge.classList.add('d-none');
      }

      if (!data.notifications.length) {
        list.innerHTML = '<li class="text-muted small px-2">No notifications yet.</li>';
        return;
      }
      list.innerHTML = data.notifications.map(n => `
        <li>
          <div class="dropdown-item small ${n.is_read ? 'text-muted' : ''}" style="white-space:normal;cursor:pointer;" data-id="${n.id}">
            ${n.message}
            <div class="text-muted" style="font-size:.7rem;">${new Date(n.created_at).toLocaleString()}</div>
          </div>
        </li>`).join('');
      list.querySelectorAll('[data-id]').forEach(el => {
        el.addEventListener('click', async () => {
          await fetch(window.BASE_URL + '/api/notifications.php?action=mark_read', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: el.dataset.id }),
          });
          loadNotifications();
        });
      });
    } catch (e) { /* silent */ }
  }

  loadNotifications();
  setInterval(loadNotifications, 30000);
})();
