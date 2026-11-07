<?php
/**
 * Sidebar shell for the admin/DRRMO section (admin/*.php + dashboard.php),
 * modeled on a standard admin-dashboard layout: fixed left sidebar grouped
 * into MAIN / MANAGE / INSIGHTS / SYSTEM, plus a topbar with page title and
 * notifications. Every admin page sets $pageTitle and $activeNav before
 * including this file, and closes with includes/admin_footer.php.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
start_session();
$user = current_user();
$activeNav = $activeNav ?? '';
$pageTitle = $pageTitle ?? APP_NAME;

function admin_nav_link(string $id, string $href, string $icon, string $label, string $active, string $extraClass = ''): string {
    $cls = 'admin-nav-link' . ($id === $active ? ' active' : '') . ($extraClass ? " $extraClass" : '');
    // data-label feeds the collapsed-rail hover tooltip (CSS ::after, see style.css).
    return '<a class="' . $cls . '" href="' . htmlspecialchars($href) . '" data-label="' . htmlspecialchars($label) . '"><i class="bi ' . $icon . '"></i> <span>' . htmlspecialchars($label) . '</span></a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset_url('/assets/img/favicon.svg') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <div class="admin-sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="admin-sidebar" id="adminSidebar">
    <a class="brand" href="<?= BASE_URL ?>/admin/index.php">
      <span class="brand-mark"><img src="<?= asset_url('/assets/img/logo-seal.png') ?>" alt="" width="22" height="22" style="border-radius:50%;object-fit:cover;"></span>
      <span>South Caloocan DRRMS</span>
    </a>
    <nav>
      <div class="admin-nav-heading">Operations</div>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
      <?= admin_nav_link('overview', BASE_URL . '/admin/index.php', 'bi-speedometer2', 'Map Dashboard', $activeNav) ?>
      <?php endif; ?>
      <?= admin_nav_link('hotspots', BASE_URL . '/admin/hotspots.php', 'bi-cpu', 'Hotspot Analysis', $activeNav) ?>
      <?= admin_nav_link('prediction', BASE_URL . '/admin/hotspots.php?tab=prediction', 'bi-graph-up-arrow', 'Prediction', $activeNav) ?>
      <?= admin_nav_link('map', BASE_URL . '/index.php', 'bi-map', 'Risk Map', $activeNav) ?>
      <?= admin_nav_link('report', BASE_URL . '/report-incident.php', 'bi-geo-alt', 'Report Incident', $activeNav) ?>

      <?php if (($user['role'] ?? '') === 'admin'): ?>
      <div class="admin-nav-heading">Records</div>
      <?= admin_nav_link('moderation', BASE_URL . '/admin/moderation.php', 'bi-bell', 'Alerts', $activeNav) ?>
      <?= admin_nav_link('data', BASE_URL . '/admin/data-management.php', 'bi-database', 'Data & Layers', $activeNav) ?>
      <?= admin_nav_link('users', BASE_URL . '/admin/users.php', 'bi-people', 'Admin & Users', $activeNav) ?>
      <?php endif; ?>

      <div class="admin-nav-heading">Insights</div>
      <?= admin_nav_link('analytics', BASE_URL . '/dashboard.php', 'bi-bar-chart-line', 'Analytics', $activeNav) ?>

      <div class="admin-nav-heading">System</div>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
      <?= admin_nav_link('logs', BASE_URL . '/admin/logs.php', 'bi-clock-history', 'History Log', $activeNav) ?>
      <?php endif; ?>
      <?= admin_nav_link('profile', BASE_URL . '/profile.php', 'bi-person', 'My Profile', $activeNav) ?>
      <?= admin_nav_link('logout', BASE_URL . '/logout.php', 'bi-box-arrow-right', 'Logout', $activeNav, 'text-danger-link') ?>
    </nav>
    <div class="admin-sidebar-disclaimer">Unofficial student project &mdash; not affiliated with the City Government of Caloocan.</div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="admin-sidebar-toggle btn btn-outline-secondary btn-sm" id="sidebarToggle" aria-label="Toggle menu">
          <i class="bi bi-list"></i>
        </button>
        <button type="button" class="admin-rail-toggle" id="railToggle" aria-label="Collapse navigation" aria-expanded="true">
          <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <div>
          <div class="fw-semibold"><?= htmlspecialchars($pageHeading ?? $pageTitle) ?></div>
          <div class="text-muted small d-none d-sm-block admin-clock" id="adminClock"><span class="pulse-dot"></span><?= date('l, F j, Y') ?></div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="dropdown" id="notifDropdown">
          <a class="text-secondary position-relative" href="#" data-bs-toggle="dropdown" style="font-size:1.2rem;">
            <i class="bi bi-bell"></i>
            <span class="badge rounded-pill text-bg-warning d-none position-absolute top-0 start-100 translate-middle" id="notifCount" style="font-size:.55rem;">0</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-2" id="notifList" style="min-width:280px;max-height:320px;overflow-y:auto;">
            <li class="text-muted small px-2">No notifications yet.</li>
          </ul>
        </div>
        <div class="dropdown">
          <a class="d-flex align-items-center gap-2 text-decoration-none text-dark" href="#" data-bs-toggle="dropdown">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold" style="width:32px;height:32px;font-size:.85rem;">
              <?= htmlspecialchars(mb_strtoupper(mb_substr($user['full_name'] ?? 'A', 0, 1))) ?>
            </span>
            <span class="small fw-semibold d-none d-md-inline"><?= htmlspecialchars($user['full_name'] ?? '') ?></span>
            <i class="bi bi-chevron-down small text-muted"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="admin-content">
<script>
(function () {
  const sidebar = document.getElementById('adminSidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const toggle = document.getElementById('sidebarToggle');
  function close() { sidebar.classList.remove('open'); backdrop.classList.remove('open'); }
  toggle.addEventListener('click', () => { sidebar.classList.toggle('open'); backdrop.classList.toggle('open'); });
  backdrop.addEventListener('click', close);

  // ---- Collapsible icon rail (desktop only), persisted across page loads --
  const shell = document.querySelector('.admin-shell');
  const railToggle = document.getElementById('railToggle');
  const RAIL_KEY = 'drs_rail_collapsed';
  if (localStorage.getItem(RAIL_KEY) === '1') shell.classList.add('rail-collapsed');
  railToggle.setAttribute('aria-expanded', String(!shell.classList.contains('rail-collapsed')));
  railToggle.addEventListener('click', () => {
    const collapsed = shell.classList.toggle('rail-collapsed');
    localStorage.setItem(RAIL_KEY, collapsed ? '1' : '0');
    railToggle.setAttribute('aria-expanded', String(!collapsed));
  });

  // ---- Live clock (client clock, display only — not used for any record) --
  const clockEl = document.getElementById('adminClock');
  const dateLabel = clockEl.textContent.trim();
  function tick() {
    const t = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    clockEl.innerHTML = '<span class="pulse-dot"></span>' + dateLabel + ' · ' + t;
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
