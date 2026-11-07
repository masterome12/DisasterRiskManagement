<?php
/**
 * One-time installer: creates the default Administrator account with a
 * properly bcrypt-hashed password (schema.sql intentionally leaves this
 * out, since a fake/placeholder hash in an SQL seed file is worse than
 * none). Run this once via browser or CLI after importing database/schema.sql,
 * then DELETE this file.
 */
require_once __DIR__ . '/config/db.php';

$defaultUsername = 'admin';
$defaultPassword = 'Admin@12345'; // change immediately after first login
$defaultEmail    = 'admin@southcaloocan.gov.ph';

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$defaultUsername]);

header('Content-Type: text/plain; charset=utf-8');

if ($stmt->fetch()) {
    echo "An 'admin' account already exists. Nothing to do.\n";
    echo "If you need to reset it, delete the row in `users` and re-run this script.\n";
    exit;
}

$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
$ins = $pdo->prepare(
    'INSERT INTO users (full_name, email, username, password_hash, role, is_active)
     VALUES (?, ?, ?, ?, ?, 1)'
);
$ins->execute(['System Administrator', $defaultEmail, $defaultUsername, $hash, 'admin']);

echo "Administrator account created.\n";
echo "  username: {$defaultUsername}\n";
echo "  password: {$defaultPassword}\n";
echo "Log in and change this password immediately, then DELETE install.php.\n";
