<?php
/**
 * Reported Cases tab — browses the imported historical multi-hazard
 * dataset (database/import_historical_cases.php) and predicts near-term
 * risk per barangay from it.
 *
 * GET ?action=meta                        -> filter options + totals
 * GET ?action=list&hazard=&severity=&district=&year_from=&year_to=&limit=
 *                                          -> filtered record rows
 * GET ?action=predict&limit=N              -> per-barangay forecast, ranked
 *                                             by predicted next-month count
 *                                             descending, capped to top N
 *                                             (the same N used on the map)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Forecast.php';
start_session();

api_require_role(['drrmo', 'admin']);
$pdo = db();
$action = $_GET['action'] ?? 'list';

if ($action === 'meta') {
    $hazards = $pdo->query('SELECT DISTINCT hazard_type FROM historical_cases ORDER BY hazard_type')->fetchAll(PDO::FETCH_COLUMN);
    $districts = $pdo->query('SELECT DISTINCT district FROM historical_cases ORDER BY district')->fetchAll(PDO::FETCH_COLUMN);
    $range = $pdo->query('SELECT MIN(year) min_year, MAX(year) max_year, COUNT(*) total FROM historical_cases')->fetch();
    json_response(['ok' => true, 'hazards' => $hazards, 'districts' => $districts, 'severities' => ['low', 'moderate', 'high', 'critical'], 'range' => $range]);
}

if ($action === 'list') {
    $where = ['1=1'];
    $params = [];
    if (($h = $_GET['hazard'] ?? '') !== '') { $where[] = 'hazard_type = ?'; $params[] = $h; }
    if (($s = $_GET['severity'] ?? '') !== '') { $where[] = 'severity = ?'; $params[] = $s; }
    if (($d = $_GET['district'] ?? '') !== '') { $where[] = 'district = ?'; $params[] = $d; }
    if (($yf = $_GET['year_from'] ?? '') !== '') { $where[] = 'year >= ?'; $params[] = (int) $yf; }
    if (($yt = $_GET['year_to'] ?? '') !== '') { $where[] = 'year <= ?'; $params[] = (int) $yt; }
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 50)));

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM historical_cases WHERE ' . implode(' AND ', $where));
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = 'SELECT incident_ref, occurred_on, district, barangay, hazard_type, severity, latitude, longitude,
                   affected_population, casualties, injuries, estimated_damage_php, response_time_minutes,
                   weather_condition, magnitude, crime_category, fire_cause
            FROM historical_cases WHERE ' . implode(' AND ', $where) . '
            ORDER BY occurred_on DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['ok' => true, 'total' => $total, 'shown' => $limit, 'rows' => $stmt->fetchAll()]);
}

if ($action === 'predict') {
    $limit = max(1, min(47, (int) ($_GET['limit'] ?? 10)));
    $today = date('Y-m-d');

    // Yearly (not monthly) counts per barangay: with ~2000 cases spread
    // across 47 barangays and 26 years, a monthly series is mostly zeros
    // (< 1 case/barangay/month on average) and every barangay's trend
    // flattens to the same noise floor. Yearly buckets give each barangay
    // a real, differentiated signal — restricted to real ("past") data,
    // since forecasting against a dataset that already contains
    // future-dated rows would be circular.
    $yearlyRows = $pdo->prepare(
        "SELECT barangay, year, COUNT(*) AS total
         FROM historical_cases WHERE occurred_on <= ? GROUP BY barangay, year ORDER BY barangay, year"
    );
    $yearlyRows->execute([$today]);
    $byBarangay = [];
    foreach ($yearlyRows->fetchAll() as $r) {
        $byBarangay[$r['barangay']][(int) $r['year']] = (int) $r['total'];
    }

    // Centroid (avg lat/lng) + dominant hazard per barangay.
    $centroids = $pdo->prepare(
        "SELECT barangay, district, AVG(latitude) lat, AVG(longitude) lng
         FROM historical_cases WHERE occurred_on <= ? GROUP BY barangay"
    );
    $centroids->execute([$today]);
    $centroidByBarangay = [];
    foreach ($centroids->fetchAll() as $r) $centroidByBarangay[$r['barangay']] = $r;

    $hazardCounts = $pdo->prepare(
        "SELECT barangay, hazard_type, COUNT(*) total FROM historical_cases
         WHERE occurred_on <= ? GROUP BY barangay, hazard_type"
    );
    $hazardCounts->execute([$today]);
    $dominantHazard = [];
    foreach ($hazardCounts->fetchAll() as $r) {
        if (!isset($dominantHazard[$r['barangay']]) || $r['total'] > $dominantHazard[$r['barangay']]['total']) {
            $dominantHazard[$r['barangay']] = ['hazard' => $r['hazard_type'], 'total' => $r['total']];
        }
    }

    // Build one shared, zero-filled year range so every barangay's series
    // lines up on the same evenly-spaced timeline (required for the linear
    // trend model — gaps would silently distort the x-axis spacing).
    $range = $pdo->query('SELECT MIN(year) mn, MAX(year) mx FROM historical_cases WHERE occurred_on <= ' . $pdo->quote($today))->fetch();
    $years = [];
    if ($range['mn'] !== null) {
        for ($y = (int) $range['mn']; $y <= (int) date('Y', strtotime($today)); $y++) {
            $years[] = $y;
        }
    }

    $results = [];
    foreach ($byBarangay as $barangay => $counts) {
        $series = array_map(fn($y) => $counts[$y] ?? 0, $years);
        if (count($series) < FORECAST_MIN_MONTHS) continue; // reused as "min data points", not literally months here

        $r = Forecast::build($series, array_map('strval', $years), 1, FORECAST_BACKTEST_THRESHOLD_MAE);
        $centroid = $centroidByBarangay[$barangay] ?? null;
        if (!$centroid) continue;

        $predicted = ($r['accepted'] && !empty($r['forecast'])) ? $r['forecast'][0]['predicted_incidents'] : null;
        $results[] = [
            'barangay' => $barangay,
            'district' => $centroid['district'],
            'lat' => (float) $centroid['lat'],
            'lng' => (float) $centroid['lng'],
            'dominant_hazard' => $dominantHazard[$barangay]['hazard'] ?? 'Other',
            'trained_on_years' => count($years),
            'total_cases' => array_sum($series),
            'last_observed' => end($series),
            'accepted' => $r['accepted'],
            'backtest_mae' => $r['backtest_mae'],
            'threshold_mae' => $r['threshold_mae'],
            'predicted_next_year' => $predicted,
            'next_year_label' => (string) ((int) date('Y', strtotime($today)) + 1),
        ];
    }

    // Rank by predicted next-year volume (unavailable/withheld last), then
    // cap to the requested display count — this is the "filter on number of
    // display values" the table and the map both read from.
    usort($results, function ($a, $b) {
        $av = $a['predicted_next_year'] ?? -1;
        $bv = $b['predicted_next_year'] ?? -1;
        return $bv <=> $av;
    });

    json_response([
        'ok' => true,
        'threshold_mae' => FORECAST_BACKTEST_THRESHOLD_MAE,
        'min_months' => FORECAST_MIN_MONTHS,
        'total_barangays' => count($results),
        'barangays' => array_slice($results, 0, $limit),
    ]);
}

json_response(['error' => 'Not found'], 404);
