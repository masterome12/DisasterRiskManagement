<?php
/**
 * Global configuration.
 * Copy this file's values from environment-specific settings before deploying.
 */

// ---- Database -----------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'disaster_risk_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---- App ------------------------------------------------------------------
define('APP_NAME', 'South Caloocan Disaster & Risk Detection System');
define('APP_TIMEZONE', 'Asia/Manila');

// ---- Base URL ---------------------------------------------------------
// Auto-detects the app's URL path prefix so it works whether it's hosted at
// the web server's document root (e.g. a dedicated vhost) or in a subfolder
// (e.g. http://localhost/CloudDisasterRiskSystem/ under XAMPP's htdocs).
// Every internal link, redirect, and asset/API reference must be prefixed
// with BASE_URL rather than hardcoded as root-absolute ("/...").
//
// This deliberately avoids comparing filesystem paths (__DIR__,
// SCRIPT_FILENAME, DOCUMENT_ROOT) against each other: when the app folder
// is reached through a symlink/junction (e.g. linked into XAMPP's htdocs),
// different PHP/Apache mechanisms resolve the junction inconsistently with
// each other (PHP's __DIR__ follows it to the real target; Apache's
// SCRIPT_FILENAME does not), so any comparison between them is unreliable.
// Instead this uses only $_SERVER['SCRIPT_NAME'] (a URL, never touched by
// filesystem resolution) together with this app's known fixed layout —
// every page lives either at the app root or one level down in api/ or
// admin/ — to strip off the app-relative suffix and recover the prefix.
if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    $prefix = in_array(basename($dir), ['api', 'admin'], true) ? dirname($dir) : $dir;
    define('BASE_URL', rtrim(str_replace('\\', '/', $prefix), '/'));
}

// Caloocan City bounding box (used to validate incident pins - FR-09).
// Sourced from OpenStreetMap/Nominatim's administrative boundary for
// Caloocan City (south 14.6356, north 14.7853, west 120.9606, east
// 121.0991), widened by a small margin so a pin right at the city edge
// isn't wrongly rejected. The city spans both South Caloocan (the
// original, compact southern district — Barangay 1-5, Sangandaan, Llano)
// and North Caloocan (Camarin, Bagumbong, Deparo, up to the Bulacan
// border) — the box below covers all of it, not just one end.
define('AOI_LAT_MIN', 14.62);
define('AOI_LAT_MAX', 14.79);
define('AOI_LNG_MIN', 120.95);
define('AOI_LNG_MAX', 121.11);
define('AOI_CENTER_LAT', 14.6513);
define('AOI_CENTER_LNG', 120.9724);

// ---- Uploads ----------------------------------------------------------
define('UPLOAD_PHOTO_DIR', __DIR__ . '/../uploads/photos/');
define('UPLOAD_DATASET_DIR', __DIR__ . '/../uploads/datasets/');
define('MAX_PHOTO_BYTES', 5 * 1024 * 1024);     // 5 MB
define('MAX_DATASET_BYTES', 20 * 1024 * 1024);  // 20 MB

// ---- Security -----------------------------------------------------------
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 10);
define('SESSION_NAME', 'drs_session');
define('REMEMBER_ME_DAYS', 30); // "Remember me" cookie lifetime on login

// ---- AI: Hotspot detection (FR-19) defaults --------------------------
define('HOTSPOT_EPS_METERS', 250);     // DBSCAN neighborhood radius
define('HOTSPOT_MIN_POINTS', 4);       // DBSCAN minimum points per cluster
define('HOTSPOT_WINDOW_DAYS', 90);     // analysis window

// ---- AI: Predictive risk forecast (FR-20, Phase 2) defaults ----------
define('FORECAST_BACKTEST_THRESHOLD_MAE', 2.0); // NFR-15 acceptance threshold
define('FORECAST_MIN_MONTHS', 6);               // minimum months of history required

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' only while developing locally
