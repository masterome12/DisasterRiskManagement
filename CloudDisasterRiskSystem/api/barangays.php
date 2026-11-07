<?php
/**
 * Lookup list used to populate barangay <select> dropdowns (report form,
 * quick-report modal). Sorted numerically within district — a plain
 * alphabetical sort on "Barangay N" strings would order 1, 10, 100, 101...
 * before 2, which only became visible once all 188 barangays were added.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

$rows = db()->query(
    "SELECT id, name, district FROM barangays
     ORDER BY district, CAST(SUBSTRING(name, 10) AS UNSIGNED)"
)->fetchAll();
json_response(['ok' => true, 'barangays' => $rows]);
