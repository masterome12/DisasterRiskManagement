<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/helpers.php';
sess_start();
header('Location: '.(authed()?'/drrms/dashboard.php':'/drrms/login.php'));
exit;
