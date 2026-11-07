<?php
/**
 * Seeds realistic demo incident reports across all hazard tags (Flooding,
 * Fire, Crime, Earthquake, Accident, Landslide, Storm/Typhoon, Road Hazard)
 * so the app is demonstrable rather than empty:
 *
 *  - Recent reports (last ~90 days) are deliberately placed in tight
 *    geographic clusters (within ~120m of a real barangay center, well
 *    under DBSCAN's default 250m eps) so FR-19 Hotspot Detection has
 *    actual clusters to find on first run, plus a few scattered "noise"
 *    points to show that isolated reports get correctly excluded.
 *  - Older reports span 8 months with a rising monthly trend so FR-20's
 *    forecast has enough history (>= FORECAST_MIN_MONTHS) to backtest.
 *
 * Run once via CLI: php database/seed_demo_data.php
 * Safe to re-run: it clears previously seeded demo data (tagged via a
 * fixed set of demo reporter accounts) before inserting, rather than
 * touching any real reports a live install might have.
 */
require __DIR__ . '/../config/db.php';

$pdo = db();

// ---------------------------------------------------------------------
// Demo reporter accounts (created if missing) so moderation/attribution
// looks realistic instead of everything being "System Administrator".
// ---------------------------------------------------------------------
$demoUsers = [
    ['full_name' => 'Maria Santos', 'email' => 'maria.santos@demo.local', 'username' => 'demo_maria', 'role' => 'resident', 'barangay' => 'Barangay 3'],
    ['full_name' => 'Pedro Reyes',  'email' => 'pedro.reyes@demo.local',  'username' => 'demo_pedro', 'role' => 'drrmo',    'barangay' => 'Barangay 174'],
    ['full_name' => 'Ana Cruz',     'email' => 'ana.cruz@demo.local',     'username' => 'demo_ana',   'role' => 'resident', 'barangay' => 'Barangay 10'],
];
$demoHash = password_hash('Demo@12345', PASSWORD_DEFAULT);
$reporterIds = [];
foreach ($demoUsers as $du) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$du['username']]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $ins = $pdo->prepare('INSERT INTO users (full_name, email, username, password_hash, role, barangay) VALUES (?,?,?,?,?,?)');
        $ins->execute([$du['full_name'], $du['email'], $du['username'], $demoHash, $du['role'], $du['barangay']]);
        $id = $pdo->lastInsertId();
    }
    $reporterIds[] = (int) $id;
}
$adminId = (int) $pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
if (!$adminId) {
    fwrite(STDERR, "No admin account found — run install.php first.\n");
    exit(1);
}
// Seed rows are only ever attributed to the 3 demo accounts (never the
// admin account) so the cleanup step below — which only targets those
// demo accounts — is guaranteed to fully remove everything this script
// created on a re-run, with no risk of leaving stale rows behind.
$allReporterIds = $reporterIds;

// ---------------------------------------------------------------------
// Clear previously seeded demo reports (anything reported by the demo
// accounts) so this script can be re-run without piling up duplicates.
// Real reports from other users are left untouched.
// ---------------------------------------------------------------------
$placeholders = implode(',', array_fill(0, count($reporterIds), '?'));
$pdo->prepare("DELETE FROM incident_reports WHERE reporter_id IN ($placeholders)")->execute($reporterIds);

// ---------------------------------------------------------------------
// Barangay lookup
// ---------------------------------------------------------------------
$barangays = $pdo->query('SELECT id, name, center_lat, center_lng FROM barangays')->fetchAll(PDO::FETCH_ASSOC);
$byName = [];
foreach ($barangays as $b) $byName[$b['name']] = $b;

function jitter(float $lat, float $lng, int $maxMeters): array
{
    $angle = mt_rand(0, 359) * M_PI / 180;
    $dist = mt_rand(0, $maxMeters);
    $dLat = ($dist * cos($angle)) / 111320;
    $dLng = ($dist * sin($angle)) / (111320 * cos(deg2rad($lat)));
    return [$lat + $dLat, $lng + $dLng];
}

$descriptions = [
    'Flooding'       => ['Ankle-deep floodwater on the main road.', 'Street flooded after heavy rain.', 'Waist-deep flooding reported near the market.', 'Flash flood along the creek.', 'Knee-deep water blocking the intersection.'],
    'Fire'           => ['Fire broke out in a residential area.', 'Small fire contained by neighbors before spreading.', 'Fire alarm triggered, smoke visible from nearby units.', 'Electrical fire reported in a sari-sari store.'],
    'Crime'          => ['Reported robbery near the corner store.', 'Snatching incident reported by a resident.', 'Break-in reported overnight.', 'Suspicious activity reported near the plaza.', 'Reported theft of motorcycle parts.'],
    'Earthquake'     => ['Minor structural cracks observed after tremor.', 'Wall damage reported following an earthquake.', 'Items fell from shelves during the shaking.'],
    'Accident'       => ['Vehicular collision reported at the intersection.', 'Motorcycle accident along the highway.', 'Pedestrian sideswiped by a jeepney.'],
    'Landslide'      => ['Soil erosion along the slope reported.', 'Loose debris sliding onto the road after rain.'],
    'Storm/Typhoon'  => ['Strong winds damaged rooftops.', 'Fallen trees blocking the road due to storm.', 'Power lines down after the storm.'],
    'Road Hazard'    => ['Large pothole reported on the main road.', 'Fallen electric post blocking traffic.', 'Open manhole reported near the crossing.'],
];
function pickDescription(array $descriptions, string $hazard): string
{
    $pool = $descriptions[$hazard] ?? ['Reported by a community member.'];
    return $pool[array_rand($pool)];
}

$rows = []; // each: hazard, severity, lat, lng, barangay_id, status, created_at, description, reporter_id

// =======================================================================
// 1) Recent, tightly-clustered reports (last ~90 days) — gives FR-19
//    Hotspot Detection real clusters to find with the default
//    eps=250m / minPoints=4 / window=90 days.
// =======================================================================
$clusters = [
    ['hazard' => 'Flooding', 'barangay' => 'Barangay 3', 'count' => 6, 'severity' => ['high', 'critical'], 'daysAgo' => [5, 80]],
    ['hazard' => 'Fire',     'barangay' => 'Barangay 1', 'count' => 5, 'severity' => ['high', 'critical'], 'daysAgo' => [10, 70]],
    ['hazard' => 'Crime',    'barangay' => 'Barangay 10', 'count' => 5, 'severity' => ['moderate', 'high'], 'daysAgo' => [3, 60]],
    ['hazard' => 'Flooding', 'barangay' => 'Barangay 174',    'count' => 4, 'severity' => ['moderate', 'high'], 'daysAgo' => [15, 85]],
];
$now = new DateTime('now');
foreach ($clusters as $c) {
    $b = $byName[$c['barangay']];
    for ($i = 0; $i < $c['count']; $i++) {
        [$lat, $lng] = jitter((float) $b['center_lat'], (float) $b['center_lng'], 120);
        $daysAgo = mt_rand($c['daysAgo'][0], $c['daysAgo'][1]);
        $created = (clone $now)->modify("-$daysAgo days")->modify('-' . mt_rand(0, 23) . ' hours');
        $rows[] = [
            'hazard' => $c['hazard'],
            'severity' => $c['severity'][array_rand($c['severity'])],
            'lat' => $lat, 'lng' => $lng,
            'barangay_id' => $b['id'],
            'status' => 'approved',
            'created_at' => $created->format('Y-m-d H:i:s'),
            'description' => pickDescription($descriptions, $c['hazard']),
            'reporter_id' => $allReporterIds[array_rand($allReporterIds)],
        ];
    }
}

// ---- Scattered "noise" points (recent, but nowhere near each other) ----
$hazardKeys = array_keys($descriptions);
for ($i = 0; $i < 6; $i++) {
    $b = $barangays[array_rand($barangays)];
    [$lat, $lng] = jitter((float) $b['center_lat'], (float) $b['center_lng'], 900); // spread wide -> won't cluster
    $hazard = $hazardKeys[array_rand($hazardKeys)];
    $daysAgo = mt_rand(1, 89);
    $created = (clone $now)->modify("-$daysAgo days");
    $rows[] = [
        'hazard' => $hazard, 'severity' => ['low', 'moderate', 'high'][array_rand([0, 1, 2])],
        'lat' => $lat, 'lng' => $lng, 'barangay_id' => $b['id'], 'status' => 'approved',
        'created_at' => $created->format('Y-m-d H:i:s'),
        'description' => pickDescription($descriptions, $hazard),
        'reporter_id' => $allReporterIds[array_rand($allReporterIds)],
    ];
}

// =======================================================================
// 2) Older monthly history (up to 8 months back) with a rising trend —
//    gives FR-20's forecast enough months to train + backtest against.
// =======================================================================
// Uses only fully-completed past months (1..8 months back) — never the
// current partial month — so no generated date can land in the future
// relative to whenever this script actually runs, and so the newest
// bucket isn't artificially undercounted just because the month is young.
$monthlyTargets = [6, 8, 9, 11, 13, 15, 17, 19]; // oldest -> newest
$monthsBack = count($monthlyTargets); // e.g. 8..1
foreach ($monthlyTargets as $target) {
    $monthStart = (clone $now)->modify("-$monthsBack months")->modify('first day of this month')->setTime(0, 0);
    $daysInMonth = (int) $monthStart->format('t');
    for ($i = 0; $i < $target; $i++) {
        $b = $barangays[array_rand($barangays)];
        [$lat, $lng] = jitter((float) $b['center_lat'], (float) $b['center_lng'], 300);
        $hazard = $hazardKeys[array_rand($hazardKeys)];
        $created = (clone $monthStart)->modify('+' . mt_rand(0, $daysInMonth - 1) . ' days')->modify('+' . mt_rand(0, 23) . ' hours');
        $roll = mt_rand(1, 100);
        $status = $roll <= 85 ? 'approved' : ($roll <= 93 ? 'pending' : 'declined');
        $rows[] = [
            'hazard' => $hazard, 'severity' => ['low', 'moderate', 'high', 'critical'][array_rand([0, 1, 2, 3])],
            'lat' => $lat, 'lng' => $lng, 'barangay_id' => $b['id'], 'status' => $status,
            'created_at' => $created->format('Y-m-d H:i:s'),
            'description' => pickDescription($descriptions, $hazard),
            'reporter_id' => $allReporterIds[array_rand($allReporterIds)],
        ];
    }
    $monthsBack--;
}

// ---- A handful of very recent "pending" reports for the moderation queue demo ----
for ($i = 0; $i < 3; $i++) {
    $b = $barangays[array_rand($barangays)];
    [$lat, $lng] = jitter((float) $b['center_lat'], (float) $b['center_lng'], 400);
    $hazard = $hazardKeys[array_rand($hazardKeys)];
    $created = (clone $now)->modify('-' . mt_rand(0, 2) . ' days')->modify('-' . mt_rand(0, 23) . ' hours');
    $rows[] = [
        'hazard' => $hazard, 'severity' => ['moderate', 'high', 'critical'][array_rand([0, 1, 2])],
        'lat' => $lat, 'lng' => $lng, 'barangay_id' => $b['id'], 'status' => 'pending',
        'created_at' => $created->format('Y-m-d H:i:s'),
        'description' => pickDescription($descriptions, $hazard),
        'reporter_id' => $reporterIds[array_rand($reporterIds)],
    ];
}

// ---------------------------------------------------------------------
// Insert.
// ---------------------------------------------------------------------
$ins = $pdo->prepare(
    'INSERT INTO incident_reports
        (reporter_id, hazard_type, severity, description, latitude, longitude, barangay_id, status,
         moderated_by, moderated_at, moderation_note, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$approvedCount = 0;
foreach ($rows as $r) {
    $isModerated = $r['status'] !== 'pending';
    $ins->execute([
        $r['reporter_id'], $r['hazard'], $r['severity'], $r['description'],
        $r['lat'], $r['lng'], $r['barangay_id'], $r['status'],
        $isModerated ? $adminId : null,
        $isModerated ? $r['created_at'] : null,
        $r['status'] === 'declined' ? 'Insufficient evidence provided.' : ($isModerated ? 'Verified against community reports.' : null),
        $r['created_at'],
    ]);
    if ($r['status'] === 'approved') $approvedCount++;
}

fwrite(STDOUT, 'Seeded ' . count($rows) . " demo incident reports ($approvedCount approved) across " . count($clusters) . " hotspot clusters and " . count($monthlyTargets) . " months of history.\n");
fwrite(STDOUT, "Demo reporter accounts (password: Demo@12345): demo_maria, demo_pedro, demo_ana\n");
