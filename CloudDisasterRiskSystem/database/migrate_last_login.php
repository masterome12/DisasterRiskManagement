<?php
/**
 * One-time migration for databases created before `users.last_login_at`
 * existed (added so every account's most recent login is tracked and
 * shown in admin/users.php — "system time" for accounts, so it can
 * actually be used to spot dormant accounts, confirm someone signed in,
 * etc., rather than only ever showing when the account was created).
 *
 * Safe to re-run: only adds the column if it isn't already there.
 * Run once via CLI: php database/migrate_last_login.php
 */
require __DIR__ . '/../config/db.php';

$pdo = db();
$exists = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login_at'"
)->fetchColumn();

if ($exists) {
    fwrite(STDOUT, "users.last_login_at already exists — nothing to do.\n");
    exit(0);
}

$pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER locked_until");
fwrite(STDOUT, "Added users.last_login_at.\n");
