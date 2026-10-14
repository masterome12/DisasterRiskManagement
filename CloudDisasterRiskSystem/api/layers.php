<?php
/**
 * FR-05 layer overlay, FR-06 visibility, FR-11 dataset upload, FR-12 CRUD.
 *
 * GET    ?action=list                -> visible layers (authenticated users) as GeoJSON
 * POST   ?action=upload   (multipart)-> Admin: upload GeoJSON/CSV dataset -> creates a layer
 * POST   ?action=toggle              -> Admin: show/hide a layer
 * POST   ?action=delete              -> Admin: delete a layer (with confirmation on the client)
 * POST   ?action=update              -> Admin: rename / change metadata
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

$action = $_GET['action'] ?? 'list';

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $u = api_require_role(['resident', 'drrmo', 'admin']); // FR-05 precondition: authenticated

    // Admins managing layers (FR-12) need to see hidden ones too, or a
    // toggled-off layer would silently vanish from its own management page
    // with no way to turn it back on. Everyone else (the public risk map,
    // FR-05) only ever sees visible layers.
    $wantAll = ($_GET['all'] ?? '') === '1' && $u['role'] === 'admin';
    $sql = "SELECT gl.id, gl.name, gl.layer_type, gl.description, gl.geojson, gl.source_format,
                   gl.is_visible, gl.created_at, u.full_name AS uploaded_by
            FROM geo_layers gl LEFT JOIN users u ON u.id = gl.uploaded_by";
    if (!$wantAll) $sql .= ' WHERE gl.is_visible = 1';
    $sql .= ' ORDER BY gl.created_at DESC';

    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $decoded = json_decode($r['geojson']);
        $features = $decoded->features ?? [];
        $r['row_count'] = count($features);
        $r['mapped_count'] = count(array_filter($features, fn($f) => ($f->geometry ?? null) !== null));
        $r['geojson'] = $decoded; // send as real JSON, not a string
    }
    json_response(['ok' => true, 'layers' => $rows]);
}

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = api_require_role(['admin']); // FR-11: Administrator only

    $name = clean_str($_POST['name'] ?? '', 150);
    $type = in_array($_POST['layer_type'] ?? '', ['hazard_zone','barangay_boundary','evacuation_route','baseline_dataset','other'], true)
            ? $_POST['layer_type'] : 'other';
    $desc = clean_str($_POST['description'] ?? '', 255);

    if ($name === '' || empty($_FILES['dataset']['name'])) {
        json_response(['error' => 'Layer name and a dataset file are required.'], 422);
    }
    $file = $_FILES['dataset'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Upload failed.'], 422);
    }
    if ($file['size'] > MAX_DATASET_BYTES) {
        json_response(['error' => 'File exceeds the 20MB limit.'], 422);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $sourceFormat = null;
    $geojson = null;

    if ($ext === 'geojson' || $ext === 'json') {
        $raw = file_get_contents($file['tmp_name']);
        $decoded = json_decode($raw, true);
        if (!$decoded || !isset($decoded['type'])) {
            json_response(['error' => 'File is not valid GeoJSON.'], 422); // FR-11 alternate flow
        }
        $geojson = $raw;
        $sourceFormat = 'geojson';
    } elseif ($ext === 'csv') {
        // Any CSV is accepted and always becomes a browsable table (FR-11
        // "just create a table based on the uploaded CSV" — never reject a
        // dataset just because it isn't geospatial). If a recognizable
        // lat/lng-style column pair exists, rows also get Point geometry so
        // they can additionally be plotted as a map overlay; rows with
        // missing/invalid coordinates simply keep geometry: null (valid
        // GeoJSON) and still show up in the table, instead of being
        // silently dropped as the previous lat/lng-only importer did.
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        if ($header === false || !count($header)) {
            fclose($handle);
            json_response(['error' => 'CSV file is empty or unreadable.'], 422);
        }
        $header = array_map(fn($h) => trim((string) $h), $header);
        $normalized = array_map(fn($h) => strtolower(trim($h)), $header);

        $latAliases = ['lat', 'latitude', 'y'];
        $lngAliases = ['lng', 'lon', 'long', 'longitude', 'x'];
        $latIdx = false;
        $lngIdx = false;
        foreach ($latAliases as $alias) { $idx = array_search($alias, $normalized, true); if ($idx !== false) { $latIdx = $idx; break; } }
        foreach ($lngAliases as $alias) { $idx = array_search($alias, $normalized, true); if ($idx !== false) { $lngIdx = $idx; break; } }
        $hasCoordColumns = $latIdx !== false && $lngIdx !== false;

        $features = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && ($row[0] === null || trim((string) $row[0]) === '')) continue; // skip blank lines
            $rowCount++;

            $geometry = null;
            if ($hasCoordColumns) {
                $lat = is_numeric($row[$latIdx] ?? null) ? (float) $row[$latIdx] : null;
                $lng = is_numeric($row[$lngIdx] ?? null) ? (float) $row[$lngIdx] : null;
                if ($lat !== null && $lng !== null && ($lat !== 0.0 || $lng !== 0.0)) {
                    $geometry = ['type' => 'Point', 'coordinates' => [$lng, $lat]];
                }
            }

            $props = [];
            foreach ($header as $i => $h) {
                $props[$h] = $row[$i] ?? null;
            }
            $features[] = ['type' => 'Feature', 'geometry' => $geometry, 'properties' => $props];
        }
        fclose($handle);

        if ($rowCount === 0) {
            json_response(['error' => 'CSV has a header row but no data rows.'], 422);
        }

        $geojson = json_encode(['type' => 'FeatureCollection', 'features' => $features]);
        $sourceFormat = 'csv';
    } elseif ($ext === 'shp' || $ext === 'zip') {
        // Native shapefile parsing needs an external library; not available in a
        // dependency-free native-PHP build. Documented limitation (FR-11).
        json_response(['error' => 'Shapefile upload requires a conversion step not included in this build. Please convert to GeoJSON or CSV first.'], 422);
    } else {
        json_response(['error' => 'Unsupported format. Use .geojson, .json, or .csv.'], 422);
    }

    $stmt = db()->prepare(
        'INSERT INTO geo_layers (name, layer_type, description, geojson, source_format, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $type, $desc, $geojson, $sourceFormat, $u['id']]);
    log_action('layer_uploaded', $name . " ($rowCount rows)");

    if ($sourceFormat === 'csv') {
        $mappedCount = count(array_filter($features, fn($f) => $f['geometry'] !== null));
        $msg = "Layer '$name' imported: $rowCount row(s) loaded as a table.";
        $msg .= $mappedCount > 0
            ? " $mappedCount of them have coordinates and are also plotted on the map."
            : ' No coordinate columns were found, so this dataset is table-only (not shown on the map).';
    } else {
        $mappedCount = null;
        $msg = "Layer '$name' imported and available for overlay.";
    }

    json_response(['ok' => true, 'message' => $msg, 'row_count' => $rowCount ?? null, 'mapped_count' => $mappedCount]);
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_role(['admin']);
    $body = read_json_body() ?: $_POST;
    $id = (int) ($body['id'] ?? 0);
    $visible = !empty($body['visible']) ? 1 : 0;
    db()->prepare('UPDATE geo_layers SET is_visible = ? WHERE id = ?')->execute([$visible, $id]);
    json_response(['ok' => true]);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_role(['admin']);
    $body = read_json_body() ?: $_POST;
    $id = (int) ($body['id'] ?? 0);
    $name = clean_str($body['name'] ?? '', 150);
    $desc = clean_str($body['description'] ?? '', 255);
    if (!$id || $name === '') json_response(['error' => 'Id and name required.'], 422);
    db()->prepare('UPDATE geo_layers SET name = ?, description = ? WHERE id = ?')->execute([$name, $desc, $id]);
    log_action('layer_updated', "id=$id");
    json_response(['ok' => true]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_role(['admin']); // FR-12 alternate flow: deletion requires confirmation (enforced client-side)
    $body = read_json_body() ?: $_POST;
    $id = (int) ($body['id'] ?? 0);
    if (!$id) json_response(['error' => 'Id required.'], 422);
    db()->prepare('DELETE FROM geo_layers WHERE id = ?')->execute([$id]);
    log_action('layer_deleted', "id=$id");
    json_response(['ok' => true, 'message' => 'Layer deleted.']);
}

json_response(['error' => 'Not found'], 404);
