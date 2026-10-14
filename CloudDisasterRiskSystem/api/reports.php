<?php
/** FR-15 Report Generation / Export (CSV, printable). */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();
api_require_role(['drrmo', 'admin']);

$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';
$barangayId = (int) ($_GET['barangay_id'] ?? 0);
$hazardType = clean_str($_GET['hazard_type'] ?? '', 60);

$where = ["ir.status = 'approved'"];
$params = [];
if ($dateFrom !== '') { $where[] = 'ir.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '')   { $where[] = 'ir.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
if ($barangayId)      { $where[] = 'ir.barangay_id = ?'; $params[] = $barangayId; }
if ($hazardType !== '') { $where[] = 'ir.hazard_type = ?'; $params[] = $hazardType; }

$sql = "SELECT ir.id, ir.hazard_type, ir.severity, ir.description, ir.latitude, ir.longitude,
               b.name AS barangay, u.full_name AS reporter, ir.created_at
        FROM incident_reports ir
        LEFT JOIN barangays b ON b.id = ir.barangay_id
        LEFT JOIN users u ON u.id = ir.reporter_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ir.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// FR-15 alternate flow: inform before generating an empty file.
if (empty($rows) && ($_GET['format'] ?? 'csv') !== 'json') {
    header('Content-Type: text/plain');
    echo "No data matches the selected parameters. No file was generated.";
    exit;
}

if (($_GET['format'] ?? 'csv') === 'json') {
    json_response(['ok' => true, 'count' => count($rows), 'rows' => $rows]);
}

$filename = 'incident_report_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Hazard Type', 'Severity', 'Description', 'Latitude', 'Longitude', 'Barangay', 'Reporter', 'Reported At']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'], $r['hazard_type'], $r['severity'], $r['description'],
        $r['latitude'], $r['longitude'], $r['barangay'], $r['reporter'], $r['created_at'],
    ]);
}
fclose($out);
log_action('report_exported', "rows=" . count($rows));
