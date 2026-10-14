<?php
/** FR-13 Statistical Dashboard & Visualization — aggregation feed for Chart.js. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();
api_require_role(['drrmo', 'admin']);

$pdo = db();

// By hazard type
$byType = $pdo->query(
    "SELECT hazard_type, COUNT(*) AS total FROM incident_reports WHERE status='approved' GROUP BY hazard_type ORDER BY total DESC"
)->fetchAll();

// By barangay
$byBarangay = $pdo->query(
    "SELECT COALESCE(b.name,'Unspecified') AS barangay, COUNT(*) AS total
     FROM incident_reports ir LEFT JOIN barangays b ON b.id = ir.barangay_id
     WHERE ir.status='approved' GROUP BY barangay ORDER BY total DESC"
)->fetchAll();

// By severity
$bySeverity = $pdo->query(
    "SELECT severity, COUNT(*) AS total FROM incident_reports WHERE status='approved' GROUP BY severity"
)->fetchAll();

// Trend: last 12 months
$trend = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
     FROM incident_reports WHERE status='approved'
       AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY month ORDER BY month ASC"
)->fetchAll();

// Moderation summary (useful for admins)
$modSummary = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM incident_reports GROUP BY status"
)->fetchAll();

// ---- Overview widgets for admin/index.php ------------------------------
$totalUsers = (int) $pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
$usersThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) c FROM users WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetch()['c'];

$totalLayers = (int) $pdo->query("SELECT COUNT(*) c FROM geo_layers")->fetch()['c'];
$visibleLayers = (int) $pdo->query("SELECT COUNT(*) c FROM geo_layers WHERE is_visible = 1")->fetch()['c'];

$totalReports = (int) $pdo->query("SELECT COUNT(*) c FROM incident_reports")->fetch()['c'];
$reportsThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) c FROM incident_reports WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetch()['c'];

$pendingCount = (int) $pdo->query("SELECT COUNT(*) c FROM incident_reports WHERE status='pending'")->fetch()['c'];
$pendingCritical = (int) $pdo->query(
    "SELECT COUNT(*) c FROM incident_reports WHERE status='pending' AND severity='critical'"
)->fetch()['c'];

// Peak reporting hours (0-23) over approved incidents.
$peakHoursRaw = $pdo->query(
    "SELECT HOUR(created_at) AS hr, COUNT(*) AS total FROM incident_reports
     WHERE status = 'approved' GROUP BY hr ORDER BY hr"
)->fetchAll();
$peakHours = array_fill(0, 24, 0);
foreach ($peakHoursRaw as $row) {
    $peakHours[(int) $row['hr']] = (int) $row['total'];
}

json_response([
    'ok' => true,
    'by_type'      => $byType,
    'by_barangay'  => $byBarangay,
    'by_severity'  => $bySeverity,
    'trend'        => $trend,
    'moderation'   => $modSummary,
    'overview' => [
        'total_users'        => $totalUsers,
        'users_this_month'   => $usersThisMonth,
        'total_layers'       => $totalLayers,
        'visible_layers'     => $visibleLayers,
        'total_reports'      => $totalReports,
        'reports_this_month' => $reportsThisMonth,
        'pending_count'      => $pendingCount,
        'pending_critical'   => $pendingCritical,
        'peak_hours'         => $peakHours,
    ],
]);
