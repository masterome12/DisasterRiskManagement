<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
log_action('logout');
$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
