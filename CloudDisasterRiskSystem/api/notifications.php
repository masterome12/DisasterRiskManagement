<?php
/** FR-17 System Notifications. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

$u = api_require_role(['resident', 'drrmo', 'admin']);
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $stmt = db()->prepare(
        'SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$u['id']]);
    json_response(['ok' => true, 'notifications' => $stmt->fetchAll()]);
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body() ?: $_POST;
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?')
        ->execute([$u['id'], (int) ($body['id'] ?? 0)]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Not found'], 404);
