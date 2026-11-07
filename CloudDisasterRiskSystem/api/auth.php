<?php
/**
 * FR-01 User Authentication, FR-02 Role-Based Access Control (session issuance).
 * Actions: ?action=login | logout | register  (POST, JSON or form body)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_session();

$action = $_GET['action'] ?? '';
$in = $_SERVER['CONTENT_TYPE'] ?? '';
$body = (strpos($in, 'application/json') !== false) ? read_json_body() : $_POST;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

switch ($action) {
    case 'login':
        $identifier = clean_str($body['identifier'] ?? '', 150); // email or username
        $password   = (string) ($body['password'] ?? '');
        $remember   = !empty($body['remember']);

        if ($identifier === '' || $password === '') {
            json_response(['error' => 'Email/username and password are required.'], 422);
        }

        $stmt = db()->prepare(
            'SELECT id, full_name, email, username, password_hash, role, is_active,
                    failed_logins, locked_until
             FROM users WHERE email = ? OR username = ? LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $u = $stmt->fetch();

        if (!$u) {
            log_action('login_failed', "unknown identifier: $identifier");
            json_response(['error' => 'Invalid credentials.'], 401);
        }

        if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
            log_action('login_blocked_locked', "username: {$u['username']}");
            json_response(['error' => 'Account temporarily locked due to repeated failed attempts. Try again later.'], 423);
        }

        if (!password_verify($password, $u['password_hash'])) {
            $failed = (int) $u['failed_logins'] + 1;
            $lockUntil = null;
            if ($failed >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
                $failed = 0;
                log_action('account_locked', "username: {$u['username']} for " . LOCKOUT_MINUTES . " min");
            } else {
                log_action('login_failed', "wrong password for: {$u['username']} (attempt $failed/" . MAX_LOGIN_ATTEMPTS . ')');
            }
            $upd = db()->prepare('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?');
            $upd->execute([$failed, $lockUntil, $u['id']]);
            json_response(['error' => 'Invalid credentials.'], 401);
        }

        if (!$u['is_active']) {
            log_action('login_blocked_inactive', "username: {$u['username']}");
            json_response(['error' => 'This account has been deactivated. Contact an administrator.'], 403);
        }

        // Success: reset failed-attempt counters, stamp the login time
        // (server clock — the one authoritative "system time" every
        // account's activity is measured against), establish session
        // (FR-01 postcondition).
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$u['id']]);
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int) $u['id'],
            'full_name' => $u['full_name'],
            'email'     => $u['email'],
            'username'  => $u['username'],
            'role'      => $u['role'],
        ];

        // "Remember me": the session cookie normally expires when the
        // browser closes (lifetime 0, set in start_session()). Re-issuing
        // it here with a future expiry — same session id, just a longer-
        // lived cookie — keeps the user signed in across browser restarts
        // without a separate remember-token table/mechanism.
        if ($remember) {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + REMEMBER_ME_DAYS * 86400,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
        log_action('login_success', "username: {$u['username']} role: {$u['role']}" . ($remember ? ' (remembered)' : ''));

        $landing = match ($u['role']) {
            'admin' => '/admin/index.php',
            'drrmo' => '/dashboard.php',
            default => '/index.php',
        };
        json_response(['ok' => true, 'redirect' => $landing, 'role' => $u['role']]);
        break;

    case 'register':
        $fullName = clean_str($body['full_name'] ?? '', 120);
        $email    = clean_str($body['email'] ?? '', 150);
        $username = clean_str($body['username'] ?? '', 60);
        $password = (string) ($body['password'] ?? '');
        $barangay = clean_str($body['barangay'] ?? '', 100);
        $role     = ($body['role'] ?? 'resident') === 'drrmo' ? 'drrmo' : 'resident'; // admin never self-registers

        if ($fullName === '' || $email === '' || $username === '' || strlen($password) < 8) {
            json_response(['error' => 'All fields are required; password must be at least 8 characters.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Invalid email address.'], 422);
        }
        if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
            json_response(['error' => 'Username must be 3-30 characters: letters, numbers, underscore, or period only.'], 422);
        }

        $chk = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $chk->execute([$email, $username]);
        if ($chk->fetch()) {
            json_response(['error' => 'An account with that email or username already exists.'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = db()->prepare(
            'INSERT INTO users (full_name, email, username, password_hash, role, barangay)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$fullName, $email, $username, $hash, $role, $barangay ?: null]);
        log_action('register', "new $role account: $username");

        json_response(['ok' => true, 'message' => 'Account created. You may now log in.']);
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        json_response(['ok' => true, 'redirect' => '/index.php']);
        break;

    default:
        json_response(['error' => 'Unknown action.'], 400);
}
