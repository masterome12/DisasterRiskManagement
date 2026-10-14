<?php
/** @var array|null $user set by header.php */
$path = $_SERVER['SCRIPT_NAME'];
function nav_active(string $file, string $path): string {
    return (basename($path) === $file) ? 'active' : '';
}
$b = BASE_URL;
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-brand-green shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?= $b ?>/index.php">
      <img src="<?= asset_url('/assets/img/logo-seal.png') ?>" alt="" width="30" height="30" style="border-radius:50%;object-fit:cover;">
      South Caloocan DRRMS
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= nav_active('index.php', $path) ?>" href="<?= $b ?>/index.php"><i class="bi bi-map"></i> Risk Map</a></li>
        <?php if ($user && $user['role'] === 'drrmo'): ?>
          <li class="nav-item"><a class="nav-link <?= nav_active('dashboard.php', $path) ?>" href="<?= $b ?>/dashboard.php"><i class="bi bi-bar-chart"></i> Dashboard</a></li>
        <?php endif; ?>
        <?php if ($user): ?>
          <li class="nav-item"><a class="nav-link <?= nav_active('report-incident.php', $path) ?>" href="<?= $b ?>/report-incident.php"><i class="bi bi-geo-alt"></i> Report Incident</a></li>
          <li class="nav-item"><a class="nav-link <?= nav_active('profile.php', $path) ?>" href="<?= $b ?>/profile.php"><i class="bi bi-person"></i> My Reports</a></li>
        <?php endif; ?>
        <?php if ($user && $user['role'] === 'drrmo'): ?>
          <li class="nav-item"><a class="nav-link <?= nav_active('hotspots.php', $path) ?>" href="<?= $b ?>/admin/hotspots.php"><i class="bi bi-cpu"></i> AI Hotspots</a></li>
        <?php endif; ?>
        <?php if ($user && $user['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link <?= nav_active('index.php', $path) ?>" href="<?= $b ?>/admin/index.php"><i class="bi bi-speedometer2"></i> Admin</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php if ($user): ?>
          <li class="nav-item dropdown" id="notifDropdown">
            <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-bell"></i>
              <span class="badge rounded-pill text-bg-warning d-none" id="notifCount">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end p-2" id="notifList" style="min-width:280px;max-height:320px;overflow-y:auto;">
              <li class="text-muted small px-2">No notifications yet.</li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['full_name']) ?>
              <span class="badge text-bg-light text-dark ms-1"><?= htmlspecialchars(role_label($user['role'])) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= $b ?>/profile.php">My Profile</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= $b ?>/logout.php">Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= $b ?>/login.php">Login</a></li>
          <li class="nav-item"><a class="btn btn-light btn-sm ms-2 mt-1 text-success fw-semibold" href="<?= $b ?>/register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
