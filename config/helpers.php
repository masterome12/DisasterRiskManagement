<?php
// DRRMS Shared Helpers
require_once __DIR__ . '/db.php';

/* ── SESSION ─────────────────────────────────────────── */
function sess_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESSION_NAME);
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','path'=>'/']);
    session_start();
    if (!isset($_SESSION['_init'])) { session_regenerate_id(true); $_SESSION['_init']=true; }
    if (isset($_SESSION['_last']) && (time()-$_SESSION['_last'])>SESSION_TTL) {
        session_unset(); session_destroy(); session_start(); return;
    }
    $_SESSION['_last'] = time();
}
function sess_set(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid']   = $u['id'];
    $_SESSION['uname'] = $u['username'];
    $_SESSION['rid']   = (int)$u['role_id'];
    $_SESSION['rslug'] = $u['role_slug'];
    $_SESSION['fname'] = trim($u['first_name'].' '.$u['last_name']);
    $_SESSION['email'] = $u['email'];
}
function authed():     bool   { return !empty($_SESSION['uid']); }
function uid():        int    { return (int)($_SESSION['uid']  ?? 0); }
function rid():        int    { return (int)($_SESSION['rid']  ?? 1); }
function rslug():      string { return $_SESSION['rslug'] ?? 'public'; }
function fname():      string { return $_SESSION['fname'] ?? 'User'; }
function is_staff():   bool   { return rid() >= 2; }
function is_drrmo():   bool   { return rid() >= 3; }
function is_admin():   bool   { return rid() >= 4; }
function is_super():   bool   { return rid() >= 5; }
function require_auth(): void {
    if (!authed()) { header('Location: /drrms/login.php'); exit; }
}

/* ── CSRF ────────────────────────────────────────────── */
function csrf_tok(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'.hsc(csrf_tok()).'">';
}
function csrf_ok(): bool {
    $t = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function need_csrf(): void {
    if (!csrf_ok()) jdie(false,'CSRF token invalid. Refresh and try again.',null,403);
}

/* ── OUTPUT ──────────────────────────────────────────── */
function hsc(mixed $v): string {
    return htmlspecialchars((string)($v??''), ENT_QUOTES, 'UTF-8');
}
function jok(string $msg='OK', mixed $data=null, int $code=200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'message'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function jdie(bool $ok, string $msg, mixed $data=null, int $code=400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>$ok,'message'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── SANITIZE ────────────────────────────────────────── */
function ss(mixed $v, int $max=500): string {
    return mb_substr(htmlspecialchars(strip_tags(trim((string)($v??''))),ENT_QUOTES,'UTF-8'),0,$max);
}
function si(mixed $v, int $mn=0, int $mx=PHP_INT_MAX): int {
    return max($mn, min($mx, (int)$v));
}
function sf(mixed $v): float|false { return filter_var($v, FILTER_VALIDATE_FLOAT); }
function slat(mixed $v): float|false {
    $f=filter_var($v,FILTER_VALIDATE_FLOAT);
    return ($f!==false&&$f>=-90&&$f<=90)?round($f,7):false;
}
function slng(mixed $v): float|false {
    $f=filter_var($v,FILTER_VALIDATE_FLOAT);
    return ($f!==false&&$f>=-180&&$f<=180)?round($f,7):false;
}
function semail(mixed $v): string|false {
    $e=filter_var(trim((string)($v??'')),FILTER_VALIDATE_EMAIL);
    return $e?strtolower($e):false;
}
function venum(string $v, array $a): bool { return in_array($v,$a,true); }

/* ── MISC ────────────────────────────────────────────── */
function next_code(string $pfx, string $tbl, string $col): string {
    $yr=date('Y');
    $n =(int)DB::run("SELECT COUNT(*) FROM `$tbl` WHERE `$col` LIKE ?",["$pfx-$yr-%"])->fetchColumn();
    return $pfx.'-'.$yr.'-'.str_pad($n+1,3,'0',STR_PAD_LEFT);
}
function log_act(string $act, string $mod, string $desc=''): void {
    try {
        DB::run("INSERT INTO activity_logs (user_id,username,action,module,description,ip_address) VALUES (?,?,?,?,?,?)",
            [uid() ?: null,$_SESSION['uname']??'anon',$act,$mod,$desc,$_SERVER['REMOTE_ADDR']??'']);
    } catch(Throwable $e){}
}
function upload_img(array $file, string $dir): string|false {
    if ($file['error']!==UPLOAD_ERR_OK) return false;
    if ($file['size']>MAX_UPLOAD_MB*1024*1024) return false;
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return false;
    $name=uniqid('img_',true).'.'.$allowed[$mime];
    if (!is_dir($dir)) mkdir($dir,0755,true);
    return move_uploaded_file($file['tmp_name'],rtrim($dir,'/').'/'.$name)?$name:false;
}
