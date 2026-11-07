<?php
/**
 * AI FEATURE — FR-19 Geospatial Hotspot / Anomaly Detection.
 * GET ?action=run  -> runs DBSCAN over approved incidents in the selected
 *                     time window and returns ranked hotspots + a heatmap-ready
 *                     point list. Cached to hotspot_runs for auditability (NFR-14).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Dbscan.php';
start_session();

$u = api_require_role(['drrmo', 'admin']);

$windowDays = max(7, min(365, (int) ($_GET['window_days'] ?? HOTSPOT_WINDOW_DAYS)));
$epsMeters  = max(50, min(2000, (int) ($_GET['eps_meters'] ?? HOTSPOT_EPS_METERS)));
$minPoints  = max(2, min(20, (int) ($_GET['min_points'] ?? HOTSPOT_MIN_POINTS)));

$stmt = db()->prepare(
    "SELECT ir.id, ir.hazard_type, ir.severity, ir.latitude AS lat, ir.longitude AS lng,
            ir.created_at, b.name AS barangay
     FROM incident_reports ir
     LEFT JOIN barangays b ON b.id = ir.barangay_id
     WHERE ir.status = 'approved' AND ir.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$stmt->execute([$windowDays]);
$points = $stmt->fetchAll();

// FR-19 alternate flow: insufficient data for a meaningful result.
if (count($points) < $minPoints) {
    json_response([
        'ok' => true,
        'insufficient_data' => true,
        'message' => "Only " . count($points) . " approved incident(s) in the last {$windowDays} days — below the minimum sample threshold ({$minPoints}) for reliable clustering.",
        'input_count' => count($points),
    ]);
}

$dbscan = new Dbscan((float) $epsMeters, $minPoints);
$result = $dbscan->run(array_map(fn($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng'],
    'id' => $p['id'], 'hazard_type' => $p['hazard_type'], 'severity' => $p['severity'],
    'barangay' => $p['barangay']], $points));

// Rank clusters by count desc, and tag severity mix for context.
$hotspots = array_map(function ($c) {
    $sevCounts = array_count_values(array_column($c['points'], 'severity'));
    $typeCounts = array_count_values(array_column($c['points'], 'hazard_type'));
    arsort($typeCounts);
    return [
        'cluster_id'    => $c['id'],
        'incident_count'=> $c['count'],
        'centroid_lat'  => round($c['centroid_lat'], 6),
        'centroid_lng'  => round($c['centroid_lng'], 6),
        'radius_meters' => $c['radius_meters'],
        'dominant_hazard' => array_key_first($typeCounts),
        'severity_mix'  => $sevCounts,
    ];
}, $result['clusters']);
usort($hotspots, fn($a, $b) => $b['incident_count'] <=> $a['incident_count']);

$noiseCount = count(array_filter($result['labels'], fn($l) => $l === -1));

$response = [
    'ok' => true,
    'insufficient_data' => false,
    'algorithm'   => 'DBSCAN',
    'params'      => ['eps_meters' => $epsMeters, 'min_points' => $minPoints, 'window_days' => $windowDays],
    'input_count' => count($points),
    'cluster_count' => count($hotspots),
    'noise_count' => $noiseCount,
    'hotspots'    => $hotspots,
    // heatmap-ready point list (for Leaflet.heat)
    'heat_points' => array_map(fn($p) => [(float) $p['lat'], (float) $p['lng'], 0.5], $points),
];

// Cache the run for auditability / reproducibility checks (NFR-14).
$ins = db()->prepare(
    'INSERT INTO hotspot_runs (algorithm, eps_meters, min_points, window_days, input_count, cluster_count, result_json, run_by)
     VALUES ("dbscan", ?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([$epsMeters, $minPoints, $windowDays, count($points), count($hotspots), json_encode($response), $u['id']]);

json_response($response);
