<?php
/**
 * History Log — read access to the audit_log table (populated by
 * includes/auth.php's log_action() throughout the app: logins/failed
 * logins/lockouts, registrations, incident submissions/moderation, layer
 * uploads/edits/deletes, user account changes, unauthorized access
 * attempts, report exports). Admin-only.
 *
 * GET ?action=meta                       -> distinct action types + total count
 * GET ?action=list&type=&user_id=&date_from=&date_to=&q=&limit=
 *                                         -> filtered, most recent first
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

api_require_role(['admin']);
$pdo = db();
$action = $_GET['action'] ?? 'list';

if ($action === 'meta') {
    $types = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    $total = (int) $pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
    $users = $pdo->query(
        "SELECT DISTINCT u.id, u.full_name, u.username FROM audit_log a
         JOIN users u ON u.id = a.user_id ORDER BY u.full_name"
    )->fetchAll();
    json_response(['ok' => true, 'types' => $types, 'users' => $users, 'total' => $total]);
}

if ($action === 'list') {
    $where = ['1=1'];
    $params = [];
    if (($t = $_GET['type'] ?? '') !== '') { $where[] = 'a.action = ?'; $params[] = $t; }
    if (($uid = $_GET['user_id'] ?? '') !== '') { $where[] = 'a.user_id = ?'; $params[] = (int) $uid; }
    if (($df = $_GET['date_from'] ?? '') !== '') { $where[] = 'a.created_at >= ?'; $params[] = $df . ' 00:00:00'; }
    if (($dt = $_GET['date_to'] ?? '') !== '') { $where[] = 'a.created_at <= ?'; $params[] = $dt . ' 23:59:59'; }
    if (($q = trim($_GET['q'] ?? '')) !== '') {
        $where[] = '(a.details LIKE ? OR a.action LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)';
        $like = "%$q%";
        array_push($params, $like, $like, $like, $like);
    }
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));

    $countSql = 'SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT a.id, a.action, a.details, a.ip_address, a.created_at,
                   u.full_name, u.username, u.role
            FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.created_at DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['ok' => true, 'total' => $total, 'shown' => $limit, 'rows' => $stmt->fetchAll()]);
}

json_response(['error' => 'Not found'], 404);
