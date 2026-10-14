<?php
/**
 * AI FEATURE (Phase 2) — FR-20 Predictive Risk Modeling.
 *
 * GET ?action=run[&barangay_id=]  -> trains a monthly linear-trend model on
 *   approved historical incidents for one scope (a barangay, or citywide if
 *   barangay_id is omitted), backtests it (NFR-15), and returns a forecast
 *   ONLY if the backtest passes the accuracy threshold. Logs the run.
 *
 * GET ?action=map  -> runs the same model independently for EVERY barangay
 *   (so predictions can be plotted spatially, not just as a single time
 *   series) and returns one entry per barangay with its coordinates, so the
 *   map can show *where* elevated risk is predicted, not just a citywide
 *   number.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Forecast.php';
start_session();

$u = api_require_role(['drrmo', 'admin']);
$pdo = db();

/**
 * The full citywide calendar span (oldest to newest approved-incident
 * month), shared by every scope so each barangay's series is zero-filled
 * against the same timeline rather than just the months it happened to
 * have a report in — see run_forecast_for() for why that distinction
 * matters.
 */
function citywide_month_range(PDO $pdo): array
{
    $r = $pdo->query("SELECT MIN(created_at) mn, MAX(created_at) mx FROM incident_reports WHERE status = 'approved'")->fetch();
    if (!$r || !$r['mn']) return [];
    $cursor = new DateTime(date('Y-m-01', strtotime($r['mn'])));
    $end = new DateTime(date('Y-m-01', strtotime($r['mx'])));
    $months = [];
    while ($cursor <= $end) {
        $months[] = $cursor->format('Y-m');
        $cursor->modify('+1 month');
    }
    return $months;
}

/**
 * Builds and backtests a forecast from an already-fetched {month => count}
 * map, zero-filled against the shared citywide range — a barangay with
 * reports in only 3 of 9 available months is genuinely "9 months of
 * history, 6 of them quiet", not "insufficient data". Split out from
 * run_forecast_for() so action=map can batch-fetch every barangay's counts
 * in one query and just call this per barangay, instead of one query each.
 */
function build_forecast_from_counts(array $byMonth, array $monthRange): array
{
    $labels = $monthRange;
    $counts = array_map(fn($m) => $byMonth[$m] ?? 0, $monthRange);

    if (count($labels) < FORECAST_MIN_MONTHS) {
        return [
            'available' => false,
            'trained_on_months' => count($labels),
            'message' => 'Not enough historical monthly data (' . count($labels) . ' month(s) available; ' . FORECAST_MIN_MONTHS . ' required) to train and backtest a forecast model per NFR-15.',
        ];
    }

    $result = Forecast::build($counts, $labels, 3, FORECAST_BACKTEST_THRESHOLD_MAE);
    $result['available'] = true;
    return $result;
}

/** Runs the train+backtest pipeline for one scope (barangay id, or null = citywide). */
function run_forecast_for(PDO $pdo, ?int $barangayId, array $monthRange): array
{
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM incident_reports WHERE status = 'approved'";
    $params = [];
    if ($barangayId) { $sql .= ' AND barangay_id = ?'; $params[] = $barangayId; }
    $sql .= ' GROUP BY month';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $byMonth = [];
    foreach ($stmt->fetchAll() as $row) $byMonth[$row['month']] = (int) $row['total'];

    return build_forecast_from_counts($byMonth, $monthRange);
}

/** Hazard composition for one scope — only needed for the single-scope run, not the per-barangay map sweep. */
function hazard_breakdown_for(PDO $pdo, ?int $barangayId): array
{
    $sql = "SELECT hazard_type, COUNT(*) total FROM incident_reports WHERE status = 'approved'";
    $params = [];
    if ($barangayId) { $sql .= ' AND barangay_id = ?'; $params[] = $barangayId; }
    $sql .= ' GROUP BY hazard_type ORDER BY total DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$action = $_GET['action'] ?? 'run';

if ($action === 'map') {
    $monthRange = citywide_month_range($pdo);
    $barangays = $pdo->query('SELECT id, name, center_lat, center_lng FROM barangays ORDER BY name')->fetchAll();

    // Dominant hazard per barangay, so prediction markers can use the same
    // hazard icon/color as everywhere else in the app, not a generic dot.
    $hazardCounts = $pdo->query(
        "SELECT barangay_id, hazard_type, COUNT(*) total FROM incident_reports
         WHERE status = 'approved' AND barangay_id IS NOT NULL GROUP BY barangay_id, hazard_type"
    )->fetchAll();
    $dominantHazard = [];
    foreach ($hazardCounts as $r) {
        $bid = (int) $r['barangay_id'];
        if (!isset($dominantHazard[$bid]) || $r['total'] > $dominantHazard[$bid]['total']) {
            $dominantHazard[$bid] = ['hazard' => $r['hazard_type'], 'total' => $r['total']];
        }
    }

    // One query for every barangay's monthly counts, instead of the 188
    // separate queries run_forecast_for() would issue if called per
    // barangay in this loop — same result, ~190x fewer round-trips.
    $monthlyCounts = $pdo->query(
        "SELECT barangay_id, DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
         FROM incident_reports WHERE status = 'approved' AND barangay_id IS NOT NULL
         GROUP BY barangay_id, month"
    )->fetchAll();
    $byBarangayMonth = [];
    foreach ($monthlyCounts as $row) {
        $byBarangayMonth[(int) $row['barangay_id']][$row['month']] = (int) $row['total'];
    }

    $out = [];
    foreach ($barangays as $b) {
        if ($b['center_lat'] === null || $b['center_lng'] === null) continue; // can't plot without coordinates
        $r = build_forecast_from_counts($byBarangayMonth[(int) $b['id']] ?? [], $monthRange);
        $nextPredicted = ($r['available'] && !empty($r['forecast'])) ? $r['forecast'][0]['predicted_incidents'] : null;
        $lastObserved = $r['available'] ? end($r['observed']) : null;
        $out[] = [
            'barangay_id' => (int) $b['id'],
            'barangay_name' => $b['name'],
            'lat' => (float) $b['center_lat'],
            'lng' => (float) $b['center_lng'],
            'dominant_hazard' => $dominantHazard[(int) $b['id']]['hazard'] ?? 'Other',
            'available' => $r['available'],
            'accepted' => $r['available'] ? $r['accepted'] : false,
            'backtest_mae' => $r['available'] ? $r['backtest_mae'] : null,
            'trained_on_months' => $r['trained_on_months'] ?? ($r['available'] ? count($r['observed']) : 0),
            'next_period_predicted' => $nextPredicted,
            'last_observed' => $lastObserved,
            'message' => $r['available'] ? null : $r['message'],
        ];
    }
    // Citywide roll-up so the UI can lead with headline numbers instead of
    // making the reader scan a 188-row table to find them.
    $accepted = array_values(array_filter($out, fn($r) => $r['accepted']));
    $rising = array_filter($accepted, fn($r) => $r['next_period_predicted'] !== null && $r['last_observed'] !== null && $r['next_period_predicted'] - $r['last_observed'] > 0.5);
    $declining = array_filter($accepted, fn($r) => $r['next_period_predicted'] !== null && $r['last_observed'] !== null && $r['next_period_predicted'] - $r['last_observed'] < -0.5);
    usort($accepted, fn($a, $b) => ($b['next_period_predicted'] ?? 0) <=> ($a['next_period_predicted'] ?? 0));

    json_response([
        'ok' => true,
        'threshold_mae' => FORECAST_BACKTEST_THRESHOLD_MAE,
        'min_months' => FORECAST_MIN_MONTHS,
        'summary' => [
            'total_barangays' => count($out),
            'trained_and_accepted' => count($accepted),
            'insufficient_or_withheld' => count($out) - count($accepted),
            'citywide_predicted_next_period' => round(array_sum(array_map(fn($r) => $r['next_period_predicted'] ?? 0, $accepted)), 1),
            'rising_count' => count($rising),
            'declining_count' => count($declining),
            'stable_count' => count($accepted) - count($rising) - count($declining),
            'top_predicted' => array_slice($accepted, 0, 10),
        ],
        'barangays' => $out,
    ]);
}

// ---- action=run (default): single scope, with audit logging -------------
$barangayId = ($_GET['barangay_id'] ?? '') !== '' ? (int) $_GET['barangay_id'] : null;
$result = run_forecast_for($pdo, $barangayId, citywide_month_range($pdo));
$result['ok'] = true;
$result['barangay_id'] = $barangayId;
$result['hazard_breakdown'] = hazard_breakdown_for($pdo, $barangayId);

if ($result['available']) {
    // Log the run for the AI-governance audit trail.
    $ins = $pdo->prepare(
        'INSERT INTO forecast_runs (method, barangay_id, backtest_mae, backtest_accepted, threshold_mae, result_json, run_by)
         VALUES ("linear_trend", ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $barangayId, $result['backtest_mae'], $result['accepted'] ? 1 : 0,
        FORECAST_BACKTEST_THRESHOLD_MAE, json_encode($result), $u['id'],
    ]);
}

json_response($result);
