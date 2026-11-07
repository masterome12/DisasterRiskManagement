<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/helpers.php';
sess_start();
if (authed()) log_act('logout','Auth','User logged out');
$_SESSION=[];
if (ini_get('session.use_cookies')) {
    $p=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path']);
}
session_destroy();
header('Location: /drrms/login.php?logged_out=1');
exit;
