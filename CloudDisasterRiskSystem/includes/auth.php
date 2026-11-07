<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Session bootstrap + role-based access control (FR-01, FR-02).
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(array $roles): bool
{
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}

/** Redirects to login if not authenticated; call at the top of protected pages. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** Redirects/denies if the current user's role is not in $roles. */
function require_role(array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        log_action('unauthorized_access', 'Attempted to access role-restricted resource');
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

/** For API endpoints: same as require_role but returns JSON 401/403 instead of redirecting. */
function api_require_role(array $roles): array
{
    $u = current_user();
    if (!$u) {
        json_response(['error' => 'Not authenticated'], 401);
    }
    if (!in_array($u['role'], $roles, true)) {
        log_action('unauthorized_api_access', 'role=' . $u['role']);
        json_response(['error' => 'Not authorized'], 403);
    }
    return $u;
}

function log_action(string $action, ?string $details = null): void
{
    $u = current_user();
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $u['id'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Auditing must never break the request.
    }
}
