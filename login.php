<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/helpers.php';
sess_start();
if (authed()) { header('Location: /drrms/dashboard.php'); exit; }

$error=''; $info='';
if (isset($_GET['logged_out'])) $info='You have been signed out successfully.';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok()) { $error='Security token invalid. Please refresh.'; }
    else {
        $id = ss($_POST['identifier']??'',150);
        $pw = $_POST['password']??'';
        if (!$id||!$pw) { $error='Username/email and password are required.'; }
        else {
            $u = DB::run(
                "SELECT u.*,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE (u.email=? OR u.username=?) AND u.status='active' LIMIT 1",
                [$id,$id])->fetch();
            if (!$u) { $error='Invalid credentials. Please try again.'; }
            elseif ($u['locked_until'] && strtotime($u['locked_until'])>time()) {
                $left=ceil((strtotime($u['locked_until'])-time())/60);
                $error="Account locked. Try again in {$left} minute(s).";
            } elseif (!password_verify($pw,$u['password_hash'])) {
                $att=$u['failed_attempts']+1;
                if ($att>=MAX_ATTEMPTS) {
                    $lock=date('Y-m-d H:i:s',time()+LOCKOUT_MINS*60);
                    DB::run("UPDATE users SET failed_attempts=?,locked_until=? WHERE id=?",[$att,$lock,$u['id']]);
                    $error="Too many failed attempts. Account locked for ".LOCKOUT_MINS." minutes.";
                } else {
                    DB::run("UPDATE users SET failed_attempts=? WHERE id=?",[$att,$u['id']]);
                    $error='Invalid credentials. '.(MAX_ATTEMPTS-$att).' attempt(s) remaining.';
                }
            } else {
                DB::run("UPDATE users SET failed_attempts=0,locked_until=NULL,last_login_at=NOW(),last_login_ip=? WHERE id=?",
                    [$_SERVER['REMOTE_ADDR']??'',$u['id']]);
                sess_set($u);
                log_act('login','Auth','User logged in');
                header('Location: /drrms/dashboard.php?welcome=1'); exit;
            }
        }
    }
}
$tok=csrf_tok();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In – DRRMS South Caloocan</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/drrms/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<style>
body{display:flex;height:100vh;overflow:hidden}
.lmap{flex:1;position:relative;background:#0d1520;overflow:hidden}
#lbg{position:absolute;inset:0}
.lov{position:absolute;inset:0;background:linear-gradient(135deg,rgba(11,15,26,.75),rgba(11,15,26,.25));z-index:2}
.lbadge{position:absolute;top:20px;left:20px;z-index:3;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);
  padding:8px 14px;border-radius:6px;border:1px solid rgba(255,255,255,.12);font-size:12px;color:#94a3b8;display:flex;align-items:center;gap:6px}
.lstats{position:absolute;bottom:20px;left:20px;z-index:3;display:flex;gap:10px;flex-wrap:wrap}
.lpill{background:rgba(0,0,0,.55);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.12);
  border-radius:20px;padding:5px 12px;font-size:11px;color:#e2e8f0;display:flex;align-items:center;gap:5px}
.lpanel{width:420px;flex-shrink:0;background:var(--c-surface);border-left:1px solid var(--c-border);
  display:flex;flex-direction:column;justify-content:center;padding:44px 38px;overflow-y:auto}
.logo-row{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.logo-ico{width:46px;height:46px;background:var(--c-accent);border-radius:11px;
  display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0}
.logo-text strong{display:block;font-family:var(--fd);font-size:20px;font-weight:800}
.logo-text span{font-size:11px;color:var(--c-muted)}
.ltitle{font-family:var(--fd);font-size:26px;font-weight:800;margin-bottom:4px;line-height:1.1}
.lsub{font-size:13px;color:var(--c-muted);margin-bottom:26px}
.iw{position:relative;margin-bottom:14px}
.iw .ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--c-muted);font-size:15px;z-index:1}
.iw .eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--c-muted);cursor:pointer;font-size:14px}
.iw input{padding-left:36px;padding-right:34px}
.rem-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;font-size:13px}
.rem-row label{display:flex;align-items:center;gap:7px;cursor:pointer}
.btn-login{width:100%;padding:11px;font-size:14px;font-weight:700;border-radius:8px}
.msg-box{padding:9px 13px;border-radius:6px;font-size:12px;margin-bottom:14px;border-left:3px solid;display:flex;align-items:center;gap:7px}
.msg-err{background:rgba(239,68,68,.1);border-color:var(--c-danger);color:#f87171}
.msg-ok {background:rgba(34,197,94,.1); border-color:var(--c-success);color:#4ade80}
.lfooter{margin-top:20px;text-align:center;font-size:11px;color:var(--c-muted);line-height:1.7}
@media(max-width:768px){.lmap{display:none}.lpanel{width:100%;border:none;padding:32px 20px}}
</style>
</head>
<body>
<div class="lmap">
  <div id="lbg"></div>
  <div class="lov"></div>
  <div class="lbadge"><i class="bi bi-geo-alt-fill" style="color:#f97316"></i> South Caloocan, Metro Manila</div>
  <div class="lstats">
    <div class="lpill"><i class="bi bi-broadcast" style="color:#ef4444"></i> Live DRRM Monitoring</div>
    <div class="lpill"><i class="bi bi-shield-check" style="color:#22c55e"></i> System Online</div>
  </div>
</div>

<div class="lpanel">
  <div class="logo-row">
    <div class="logo-ico"><i class="bi bi-shield-exclamation"></i></div>
    <div class="logo-text"><strong>DRRMS</strong><span>South Caloocan City</span></div>
  </div>
  <div class="ltitle">Disaster Risk &amp; Resource<br>Management System</div>
  <div class="lsub">Sign in to access the monitoring dashboard</div>

  <?php if($error):?><div class="msg-box msg-err"><i class="bi bi-exclamation-circle"></i><?=hsc($error)?></div><?php endif;?>
  <?php if($info): ?><div class="msg-box msg-ok"><i class="bi bi-check-circle"></i><?=hsc($info)?></div><?php endif;?>

  <form method="POST" novalidate>
    <?=csrf_field()?>
    <label class="form-label">Username or Email</label>
    <div class="iw">
      <i class="bi bi-person ico"></i>
      <input type="text" class="form-control" name="identifier" value="<?=hsc($_POST['identifier']??'')?>"
             placeholder="Enter username or email" autocomplete="username" required>
    </div>
    <label class="form-label">Password</label>
    <div class="iw">
      <i class="bi bi-lock ico"></i>
      <input type="password" class="form-control" name="password" id="pw"
             placeholder="Enter your password" autocomplete="current-password" required>
      <i class="bi bi-eye eye" id="eye" onclick="togglePw()"></i>
    </div>
    <div class="rem-row">
      <label><input type="checkbox" name="remember" style="accent-color:var(--c-accent)"> Keep me signed in</label>
      <a href="#" style="font-size:12px;color:var(--c-accent)">Forgot password?</a>
    </div>
    <button type="submit" class="btn btn-primary btn-login">
      <i class="bi bi-box-arrow-in-right"></i> Sign In to System
    </button>
  </form>

  <div class="lfooter">
    No account? <a href="/drrms/register.php">Register here</a><br>
    <strong>Demo:</strong> admin / password &nbsp;|&nbsp; m.santos / password<br>
    Protected under the Philippine Data Privacy Act of 2012
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const m=L.map('lbg',{zoomControl:false,attributionControl:false,dragging:false,
  scrollWheelZoom:false,keyboard:false,center:[14.75,120.9833],zoom:13});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(m);
[[14.752,120.982,'#ef4444'],[14.748,120.985,'#3b82f6'],[14.746,120.981,'#ef4444'],[14.754,120.987,'#eab308']].forEach(([a,b,c])=>
  L.circleMarker([a,b],{radius:8,color:c,fillColor:c,fillOpacity:.85,weight:2}).addTo(m));
L.circle([14.746,120.981],{radius:500,color:'#3b82f6',fillColor:'#3b82f6',fillOpacity:.1,weight:1}).addTo(m);
L.circle([14.752,120.982],{radius:300,color:'#ef4444',fillColor:'#ef4444',fillOpacity:.1,weight:1}).addTo(m);
function togglePw(){const p=document.getElementById('pw'),e=document.getElementById('eye');p.type=p.type==='password'?'text':'password';e.className='bi '+(p.type==='password'?'bi-eye':'bi-eye-slash')+' eye';}

/* ── Client-side validation (form has novalidate; this provides real feedback) ── */
(function(){
  const form = document.querySelector('form[method="POST"]');
  if (!form) return;
  function clearErr(el){ el.classList.remove('invalid'); el.parentElement?.querySelector('.fv-err')?.remove(); }
  function showErr(el, msg){
    el.classList.add('invalid');
    const d = document.createElement('div');
    d.className = 'fe fv-err';
    d.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + msg;
    el.closest('.iw')?.insertAdjacentElement('afterend', d) || el.insertAdjacentElement('afterend', d);
  }
  form.addEventListener('submit', e => {
    const id = form.querySelector('[name="identifier"]');
    const pw = form.querySelector('[name="password"]');
    [id, pw].forEach(clearErr);
    let ok = true, first = null;
    if (!id.value.trim()) { showErr(id, 'Username or email is required.'); ok = false; first = first || id; }
    if (!pw.value) { showErr(pw, 'Password is required.'); ok = false; first = first || pw; }
    if (!ok) { e.preventDefault(); first.focus(); }
  });
  [form.querySelector('[name="identifier"]'), form.querySelector('[name="password"]')].forEach(el => {
    el?.addEventListener('input', () => clearErr(el));
  });
})();
</script>
</body>
</html>
