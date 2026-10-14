<?php
/**
 * FR-03 marker feed, FR-08 popup detail, FR-09 submission, FR-10 moderation.
 *
 * GET    ?action=list              -> approved incidents for the public map (Guest+)
 * GET    ?action=mine               -> current user's own submitted reports
 * GET    ?action=pending            -> Admin: reports awaiting moderation
 * POST   ?action=submit             -> Resident/DRRMO/Admin: submit new report
 *                                       (Admin submissions auto-approve — an
 *                                       admin reporting directly doesn't need
 *                                       to review themselves; Resident/DRRMO
 *                                       reports still go through FR-10.)
 * POST   ?action=moderate           -> Admin: approve/decline a report
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

// Canonical hazard-type tags the map/report UI offers, each with its own
// marker icon (see assets/js/hazard-icons.js). Kept as a whitelist so every
// submitted report maps to a known icon rather than an arbitrary string.
const HAZARD_TYPES = ['Flooding', 'Earthquake', 'Fire', 'Accident', 'Crime', 'Landslide', 'Storm/Typhoon', 'Road Hazard', 'Other'];

$action = $_GET['action'] ?? 'list';

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Available to Guests (FR-03 precondition: none).
    $stmt = db()->query(
        "SELECT ir.id, ir.hazard_type, ir.severity, ir.description, ir.latitude, ir.longitude,
                ir.photo_path, ir.created_at, b.name AS barangay, u.full_name AS reporter
         FROM incident_reports ir
         LEFT JOIN barangays b ON b.id = ir.barangay_id
         LEFT JOIN users u ON u.id = ir.reporter_id
         WHERE ir.status = 'approved'
         ORDER BY ir.created_at DESC"
    );
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        // FR-03 alternate flow: friendly "no data available" rather than silent failure.
        json_response(['ok' => true, 'count' => 0, 'message' => 'No approved incident data available yet.', 'incidents' => []]);
    }
    json_response(['ok' => true, 'count' => count($rows), 'incidents' => $rows]);
}

if ($action === 'mine' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $u = api_require_role(['resident', 'drrmo', 'admin']);
    $stmt = db()->prepare(
        "SELECT id, hazard_type, severity, description, latitude, longitude, status,
                moderation_note, created_at
         FROM incident_reports WHERE reporter_id = ? ORDER BY created_at DESC"
    );
    $stmt->execute([$u['id']]);
    json_response(['ok' => true, 'incidents' => $stmt->fetchAll()]);
}

if ($action === 'pending' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    api_require_role(['admin']);
    $stmt = db()->query(
        "SELECT ir.id, ir.hazard_type, ir.severity, ir.description, ir.latitude, ir.longitude,
                ir.photo_path, ir.created_at, b.name AS barangay, u.full_name AS reporter, u.email AS reporter_email
         FROM incident_reports ir
         LEFT JOIN barangays b ON b.id = ir.barangay_id
         LEFT JOIN users u ON u.id = ir.reporter_id
         WHERE ir.status = 'pending'
         ORDER BY ir.created_at ASC"
    );
    json_response(['ok' => true, 'incidents' => $stmt->fetchAll()]);
}

if ($action === 'all' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Admin: every report regardless of status, for the Alerts detail view
    // (not just the pending-approval queue). Optional ?status= filter.
    api_require_role(['admin']);
    $status = $_GET['status'] ?? '';
    $where = in_array($status, ['pending', 'approved', 'declined'], true) ? 'WHERE ir.status = ?' : '';
    $params = $where ? [$status] : [];
    $stmt = db()->prepare(
        "SELECT ir.id, ir.hazard_type, ir.severity, ir.description, ir.latitude, ir.longitude,
                ir.photo_path, ir.status, ir.barangay_id, ir.created_at, ir.moderated_at, ir.moderation_note,
                b.name AS barangay, u.full_name AS reporter, u.email AS reporter_email,
                m.full_name AS moderator
         FROM incident_reports ir
         LEFT JOIN barangays b ON b.id = ir.barangay_id
         LEFT JOIN users u ON u.id = ir.reporter_id
         LEFT JOIN users m ON m.id = ir.moderated_by
         $where
         ORDER BY ir.created_at DESC
         LIMIT 500"
    );
    $stmt->execute($params);
    json_response(['ok' => true, 'incidents' => $stmt->fetchAll()]);
}

if ($action === 'detail' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Admin: single-report detail for the Alerts detail view — same real
    // fields as action=all, just for one id.
    api_require_role(['admin']);
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare(
        "SELECT ir.id, ir.hazard_type, ir.severity, ir.description, ir.latitude, ir.longitude,
                ir.photo_path, ir.status, ir.barangay_id, ir.created_at, ir.moderated_at, ir.moderation_note,
                b.name AS barangay, u.full_name AS reporter, u.email AS reporter_email,
                m.full_name AS moderator
         FROM incident_reports ir
         LEFT JOIN barangays b ON b.id = ir.barangay_id
         LEFT JOIN users u ON u.id = ir.reporter_id
         LEFT JOIN users m ON m.id = ir.moderated_by
         WHERE ir.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Report not found.'], 404);
    json_response(['ok' => true, 'incident' => $row]);
}

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = api_require_role(['resident', 'drrmo', 'admin']);

    $hazardType  = clean_str($_POST['hazard_type'] ?? '', 60);
    $severity    = in_array($_POST['severity'] ?? '', ['low','moderate','high','critical'], true)
                    ? $_POST['severity'] : 'low';
    $description = clean_str($_POST['description'] ?? '', 1000);
    $lat = (float) ($_POST['latitude'] ?? 0);
    $lng = (float) ($_POST['longitude'] ?? 0);
    $barangayId = ($_POST['barangay_id'] ?? '') !== '' ? (int) $_POST['barangay_id'] : null;

    if ($hazardType === '' || !in_array($hazardType, HAZARD_TYPES, true)) {
        json_response(['error' => 'Please choose a valid hazard type.'], 422);
    }
    if ($lat === 0.0 || $lng === 0.0) {
        json_response(['error' => 'A map location is required.'], 422);
    }
    // FR-09 alternate flow: reject if location falls outside South Caloocan.
    if (!is_within_south_caloocan($lat, $lng)) {
        json_response(['error' => 'The pinned location is outside South Caloocan and cannot be submitted.'], 422);
    }

    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > MAX_PHOTO_BYTES) {
                json_response(['error' => 'Photo exceeds the 5MB limit.'], 422);
            }
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!isset($allowed[$mime])) {
                json_response(['error' => 'Only JPG, PNG, or WEBP photos are allowed.'], 422);
            }
            $fname = 'inc_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (!is_dir(UPLOAD_PHOTO_DIR)) mkdir(UPLOAD_PHOTO_DIR, 0755, true);
            move_uploaded_file($file['tmp_name'], UPLOAD_PHOTO_DIR . $fname);
            $photoPath = 'uploads/photos/' . $fname;
        }
    }

    // An Administrator reporting directly on the map is already the approval
    // authority, so their own reports publish immediately instead of sitting
    // in the queue they themselves would review (FR-10 still applies in full
    // to Resident/DRRMO submissions).
    $isAdmin = $u['role'] === 'admin';
    $status = $isAdmin ? 'approved' : 'pending';

    $stmt = db()->prepare(
        "INSERT INTO incident_reports
            (reporter_id, hazard_type, severity, description, latitude, longitude, barangay_id, photo_path, status,
             moderated_by, moderated_at, moderation_note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $u['id'], $hazardType, $severity, $description, $lat, $lng, $barangayId, $photoPath, $status,
        $isAdmin ? $u['id'] : null,
        $isAdmin ? date('Y-m-d H:i:s') : null,
        $isAdmin ? 'Auto-approved (reported by an Administrator).' : null,
    ]);
    $newId = (int) db()->lastInsertId();
    log_action('incident_submitted', "id=$newId status=$status");

    // FR-17: in-app confirmation notification for the reporter.
    notify_user($u['id'], $isAdmin
        ? "Your $hazardType report was published to the map."
        : "Your $hazardType report was submitted and is pending review.");

    // Admins get their own notification for anything they still need to
    // act on — a Resident/DRRMO report is always pending review, but a
    // critical-severity report is flagged as urgent so it doesn't sit in
    // the queue unnoticed alongside routine ones.
    if (!$isAdmin) {
        $barangayName = null;
        if ($barangayId) {
            $bStmt = db()->prepare('SELECT name FROM barangays WHERE id = ?');
            $bStmt->execute([$barangayId]);
            $barangayName = $bStmt->fetchColumn() ?: null;
        }
        $where = $barangayName ? " in $barangayName" : '';
        $urgent = in_array($severity, ['high', 'critical'], true);
        notify_admins(
            ($urgent ? "URGENT: " : "New ") . "$hazardType report$where"
            . ($urgent ? " ({$severity})" : '') . " — pending review."
        );
    }

    json_response([
        'ok' => true,
        'status' => $status,
        'message' => $isAdmin
            ? 'Report published to the map.'
            : 'Report submitted and is now pending Administrator review.',
    ]);
}

if ($action === 'moderate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = api_require_role(['admin']);
    $body = read_json_body() ?: $_POST;

    $id = (int) ($body['id'] ?? 0);
    $decision = ($body['decision'] ?? '') === 'approve' ? 'approved' : (($body['decision'] ?? '') === 'decline' ? 'declined' : null);
    $note = clean_str($body['note'] ?? '', 255);

    if (!$id || !$decision) {
        json_response(['error' => 'Report id and a valid decision (approve/decline) are required.'], 422);
    }

    $stmt = db()->prepare('SELECT reporter_id, hazard_type FROM incident_reports WHERE id = ? AND status = "pending"');
    $stmt->execute([$id]);
    $report = $stmt->fetch();
    if (!$report) {
        json_response(['error' => 'Report not found or already moderated.'], 404);
    }

    $upd = db()->prepare(
        'UPDATE incident_reports SET status = ?, moderation_note = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?'
    );
    $upd->execute([$decision, $note ?: null, $admin['id'], $id]);
    log_action('incident_moderated', "id=$id decision=$decision");

    notify_user((int) $report['reporter_id'], "Your {$report['hazard_type']} report was {$decision}" . ($note ? ": $note" : '.'));

    json_response(['ok' => true, 'message' => "Report {$decision}."]);
}

json_response(['error' => 'Not found'], 404);
