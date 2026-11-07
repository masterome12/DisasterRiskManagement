<?php
/** FR-16 User Account Management (Administrator only). */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

$admin = api_require_role(['admin']);
$action = $_GET['action'] ?? 'list';

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = [];
    $params = [];

    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $where[] = '(full_name LIKE ? OR email LIKE ? OR username LIKE ?)';
        $like = "%$q%";
        array_push($params, $like, $like, $like);
    }
    $role = $_GET['role'] ?? '';
    if (in_array($role, ['resident', 'drrmo', 'admin'], true)) {
        $where[] = 'role = ?';
        $params[] = $role;
    }
    $status = $_GET['status'] ?? '';
    if ($status === 'active' || $status === 'inactive') {
        $where[] = 'is_active = ?';
        $params[] = $status === 'active' ? 1 : 0;
    }

    $sql = 'SELECT id, full_name, email, username, role, barangay, is_active, created_at, last_login_at FROM users';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    // server_time lets the UI compute "time ago" / "Never" against the
    // authoritative server clock rather than the admin's own (possibly
    // skewed) system clock.
    json_response(['ok' => true, 'users' => $stmt->fetchAll(), 'server_time' => date('c')]);
}

if ($action === 'view' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare(
        'SELECT id, full_name, email, username, role, barangay, is_active, created_at, last_login_at FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) json_response(['error' => 'User not found.'], 404);

    $counts = ['total' => 0, 'pending' => 0, 'approved' => 0, 'declined' => 0];
    $cStmt = db()->prepare('SELECT status, COUNT(*) c FROM incident_reports WHERE reporter_id = ? GROUP BY status');
    $cStmt->execute([$id]);
    foreach ($cStmt->fetchAll() as $row) {
        $counts['total'] += (int) $row['c'];
        if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['c'];
    }

    $rStmt = db()->prepare(
        'SELECT id, hazard_type, severity, status, created_at FROM incident_reports WHERE reporter_id = ? ORDER BY created_at DESC LIMIT 10'
    );
    $rStmt->execute([$id]);

    json_response(['ok' => true, 'user' => $user, 'counts' => $counts, 'recent_reports' => $rStmt->fetchAll()]);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body() ?: $_POST;
    $fullName = clean_str($body['full_name'] ?? '', 120);
    $email    = clean_str($body['email'] ?? '', 150);
    $username = clean_str($body['username'] ?? '', 60);
    $password = (string) ($body['password'] ?? '');
    $role     = in_array($body['role'] ?? '', ['resident','drrmo','admin'], true) ? $body['role'] : 'resident';

    if ($fullName === '' || $email === '' || $username === '' || strlen($password) < 8) {
        json_response(['error' => 'All fields required; password min 8 characters.'], 422);
    }
    $chk = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $chk->execute([$email, $username]);
    if ($chk->fetch()) json_response(['error' => 'Email or username already exists.'], 409);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare('INSERT INTO users (full_name, email, username, password_hash, role) VALUES (?,?,?,?,?)')
        ->execute([$fullName, $email, $username, $hash, $role]);
    log_action('user_created_by_admin', $username);
    json_response(['ok' => true, 'message' => 'User account created.']);
}

if ($action === 'update_role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body() ?: $_POST;
    $id = (int) ($body['id'] ?? 0);
    $role = $body['role'] ?? '';
    if (!$id || !in_array($role, ['resident','drrmo','admin'], true)) {
        json_response(['error' => 'Valid id and role required.'], 422);
    }
    db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
    log_action('user_role_changed', "id=$id role=$role");
    json_response(['ok' => true]);
}

if ($action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body() ?: $_POST;
    $id = (int) ($body['id'] ?? 0);
    $active = !empty($body['active']) ? 1 : 0;

    if (!$active) {
        // FR-16 alternate flow: cannot deactivate the last active Administrator.
        $stmt = db()->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target && $target['role'] === 'admin') {
            $cnt = db()->query("SELECT COUNT(*) c FROM users WHERE role='admin' AND is_active=1")->fetch();
            if ((int) $cnt['c'] <= 1) {
                json_response(['error' => 'Cannot deactivate the last remaining active Administrator account.'], 422);
            }
        }
    }

    db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active, $id]);
    log_action('user_active_toggled', "id=$id active=$active");
    if ($id) notify_user($id, $active ? 'Your account has been reactivated.' : 'Your account has been deactivated by an administrator.');
    json_response(['ok' => true]);
}

json_response(['error' => 'Not found'], 404);
