<?php
/**
 * Imports the external multi-hazard historical dataset into
 * `historical_cases`, normalizing its hazard/severity labels to match this
 * app's own tags (so the same hazard-icons.js colors/icons apply here too).
 *
 * Usage: php database/import_historical_cases.php <path-to-csv>
 *
 * Idempotent: uses the CSV's own Incident_ID as a unique key, so re-running
 * against the same (or an updated) file upserts rather than duplicating.
 */
require __DIR__ . '/../config/db.php';

$csvPath = $argv[1] ?? null;
if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php database/import_historical_cases.php <path-to-csv>\n");
    exit(1);
}

// The dataset's own labels -> this app's canonical hazard tags (hazard-icons.js).
const HAZARD_MAP = [
    'Flood'      => 'Flooding',
    'Fire'       => 'Fire',
    'Crime'      => 'Crime',
    'Earthquake' => 'Earthquake',
];
const SEVERITY_MAP = [
    'Low' => 'low', 'Moderate' => 'moderate', 'High' => 'high', 'Critical' => 'critical',
];

$pdo = db();
$upsert = $pdo->prepare(
    'INSERT INTO historical_cases
        (incident_ref, occurred_on, year, month, district, barangay, hazard_type, severity,
         latitude, longitude, affected_population, casualties, injuries, estimated_damage_php,
         response_time_minutes, weather_condition, magnitude, crime_category, fire_cause)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        occurred_on=VALUES(occurred_on), year=VALUES(year), month=VALUES(month),
        district=VALUES(district), barangay=VALUES(barangay), hazard_type=VALUES(hazard_type),
        severity=VALUES(severity), latitude=VALUES(latitude), longitude=VALUES(longitude),
        affected_population=VALUES(affected_population), casualties=VALUES(casualties),
        injuries=VALUES(injuries), estimated_damage_php=VALUES(estimated_damage_php),
        response_time_minutes=VALUES(response_time_minutes), weather_condition=VALUES(weather_condition),
        magnitude=VALUES(magnitude), crime_category=VALUES(crime_category), fire_cause=VALUES(fire_cause)'
);

$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$idx = array_flip($header);
foreach (['Incident_ID', 'Date', 'Year', 'Month', 'District', 'Barangay', 'Hazard_Type', 'Severity', 'Latitude', 'Longitude'] as $required) {
    if (!isset($idx[$required])) {
        fwrite(STDERR, "CSV is missing expected column: $required\n");
        exit(1);
    }
}

$imported = 0; $skipped = 0;
$pdo->beginTransaction();
while (($row = fgetcsv($fh)) !== false) {
    $get = fn(string $col) => isset($idx[$col]) ? ($row[$idx[$col]] === '' ? null : $row[$idx[$col]]) : null;

    $hazardRaw = $get('Hazard_Type');
    $hazard = HAZARD_MAP[$hazardRaw] ?? $hazardRaw; // fall back to the raw label if unmapped
    $severity = SEVERITY_MAP[$get('Severity')] ?? 'low';

    $ok = $upsert->execute([
        $get('Incident_ID'), $get('Date'), (int) $get('Year'), (int) $get('Month'),
        $get('District'), $get('Barangay'), $hazard, $severity,
        (float) $get('Latitude'), (float) $get('Longitude'),
        (int) ($get('Affected_Population') ?? 0), (int) ($get('Casualties') ?? 0), (int) ($get('Injuries') ?? 0),
        (float) ($get('Estimated_Damage_PHP') ?? 0), $get('Response_Time_Minutes'),
        $get('Weather_Condition'), $get('Magnitude'), $get('Crime_Category'), $get('Fire_Cause'),
    ]);
    $ok ? $imported++ : $skipped++;
}
$pdo->commit();
fclose($fh);

fwrite(STDOUT, "Imported/updated $imported historical case(s)" . ($skipped ? ", $skipped skipped" : '') . ".\n");
