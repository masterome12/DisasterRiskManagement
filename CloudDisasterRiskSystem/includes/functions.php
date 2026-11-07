<?php
require_once __DIR__ . '/../config/config.php';

/**
 * URL for a local static asset (under the app root), with a cache-busting
 * ?v= query string derived from the file's own mtime. Without this,
 * browsers that already cached an old copy of a .js/.css file keep using
 * it indefinitely after a deploy — filemtime changes automatically the
 * moment the file is edited, so no manual version bump is ever needed.
 */
function asset_url(string $path): string
{
    $fsPath = __DIR__ . '/../' . ltrim($path, '/');
    $v = is_file($fsPath) ? filemtime($fsPath) : time();
    return BASE_URL . '/' . ltrim($path, '/') . '?v=' . $v;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * User-facing label for a role value. The underlying role string stored in
 * the database and checked everywhere in code stays 'resident' (changing
 * that would mean touching every require_role()/role check in the app for
 * no functional benefit) — this only controls what residents see
 * themselves called in the UI: "User" rather than "Resident".
 */
function role_label(string $role): string
{
    return match ($role) {
        'resident' => 'User',
        'drrmo' => 'DRRMO',
        'admin' => 'Admin',
        default => ucfirst($role),
    };
}

function clean_str(?string $s, int $maxLen = 255): string
{
    $s = trim((string) $s);
    $s = strip_tags($s);
    return mb_substr($s, 0, $maxLen);
}

/** Great-circle distance in meters between two lat/lng points (Haversine). */
function haversine_meters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371000; // Earth radius, meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

function is_within_south_caloocan(float $lat, float $lng): bool
{
    return $lat >= AOI_LAT_MIN && $lat <= AOI_LAT_MAX
        && $lng >= AOI_LNG_MIN && $lng <= AOI_LNG_MAX;
}

function notify_user(int $userId, string $message): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt->execute([$userId, $message]);
}

/**
 * Notifies every active admin — used when something needs an admin's
 * attention (a new report is pending review, a critical-severity hazard
 * was just flagged) rather than waiting for them to happen to check the
 * moderation queue.
 */
function notify_admins(string $message): void
{
    $adminIds = db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    foreach ($adminIds as $id) {
        $stmt->execute([$id, $message]);
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    start_session();
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('Invalid or expired form token. Please go back and try again.');
    }
}

function flash(string $key, ?string $value = null)
{
    start_session();
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $v;
}
