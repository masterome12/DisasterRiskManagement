<?php
/** FR-14 Search — locations, barangays, and reported incidents. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

$q = clean_str($_GET['q'] ?? '', 100);
if ($q === '') {
    json_response(['ok' => true, 'results' => []]);
}
$like = '%' . $q . '%';
$pdo = db();

$barangays = $pdo->prepare('SELECT id, name, center_lat, center_lng FROM barangays WHERE name LIKE ? LIMIT 5');
$barangays->execute([$like]);

$incidents = $pdo->prepare(
    "SELECT id, hazard_type, description, latitude, longitude FROM incident_reports
     WHERE status='approved' AND (hazard_type LIKE ? OR description LIKE ?) LIMIT 5"
);
$incidents->execute([$like, $like]);

$results = [];
foreach ($barangays->fetchAll() as $b) {
    $results[] = [
        'type' => 'barangay', 'label' => $b['name'],
        'lat' => $b['center_lat'], 'lng' => $b['center_lng'], 'id' => $b['id'],
    ];
}
foreach ($incidents->fetchAll() as $i) {
    $results[] = [
        'type' => 'incident', 'label' => $i['hazard_type'] . ' — ' . mb_strimwidth($i['description'] ?? '', 0, 60, '…'),
        'lat' => $i['latitude'], 'lng' => $i['longitude'], 'id' => $i['id'],
    ];
}

if (empty($results)) {
    json_response(['ok' => true, 'results' => [], 'message' => 'No results found.']); // FR-14 alternate flow
}
json_response(['ok' => true, 'results' => $results]);
