<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/helpers.php';
sess_start();
if (authed()) { header('Location: /drrms/dashboard.php'); exit; }

$error=''; $success='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok()) { $error='Security token invalid.'; }
    else {
        $fn=ss($_POST['first_name']??'',80); $ln=ss($_POST['last_name']??'',80);
        $em=semail($_POST['email']??''); $un=preg_replace('/[^a-zA-Z0-9._\-]/','',trim($_POST['username']??''));
        $pw=$_POST['password']??''; $cpw=$_POST['confirm']??'';
        $ph=ss($_POST['phone']??'',20); $br=ss($_POST['barangay']??'',100);
        if (!$fn||!$ln)         $error='First and last name are required.';
        elseif (!$em)           $error='Valid email address is required.';
        elseif (strlen($un)<4)  $error='Username must be at least 4 characters.';
        elseif (strlen($pw)<8)  $error='Password must be at least 8 characters.';
        elseif ($pw!==$cpw)     $error='Passwords do not match.';
        elseif (!$br)           $error='Please select your barangay.';
        elseif (!isset($_POST['agree'])) $error='You must agree to the terms.';
        else {
            if (DB::run("SELECT id FROM users WHERE email=?",[$em])->fetch())  $error='Email already registered.';
            elseif (DB::run("SELECT id FROM users WHERE username=?",[$un])->fetch()) $error='Username already taken.';
            else {
                DB::run("INSERT INTO users (role_id,first_name,last_name,email,username,password_hash,phone,barangay,status) VALUES (1,?,?,?,?,?,?,?,'active')",
                    [$fn,$ln,$em,$un,password_hash($pw,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]),$ph,$br]);
                DB::run("INSERT INTO activity_logs (user_id,username,action,module,description,ip_address) VALUES (?,?,?,?,?,?)",
                    [(int)DB::lastId(),$un,'register','Auth','New account registered',$_SERVER['REMOTE_ADDR']??'']);
                $success='Account created! You can now <a href="/drrms/login.php">sign in here</a>.';
            }
        }
    }
}
$tok=csrf_tok();
$brgys=['Brgy. 139','Brgy. 140','Brgy. 141','Brgy. 163','Brgy. 164','Brgy. 165',
  'Bagong Barrio','Dagat-dagatan','Tullahan','Deparo','Kaunlaran','Maypajo'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register – DRRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/drrms/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<style>
body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px}
.rc{width:100%;max-width:560px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;overflow:hidden;box-shadow:var(--sh)}
.rh{background:linear-gradient(135deg,var(--c-accent),#b45309);padding:24px 28px;display:flex;align-items:center;gap:12px}
.rh .ri{width:44px;height:44px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0}
.rh h1{font-family:var(--fd);font-size:20px;font-weight:800;color:#fff;line-height:1.1}
.rh p{font-size:11px;color:rgba(255,255,255,.75);margin-top:2px}
.rb{padding:24px 28px}
.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--c-muted);margin:0 0 12px;padding-bottom:7px;border-bottom:1px solid var(--c-border)}
.msg{padding:9px 13px;border-radius:6px;font-size:12px;margin-bottom:14px;border-left:3px solid;display:flex;align-items:flex-start;gap:7px}
.msg.err{background:rgba(239,68,68,.1);border-color:var(--c-danger);color:#f87171}
.msg.ok{background:rgba(34,197,94,.1);border-color:var(--c-success);color:#4ade80}
.pbs{display:flex;gap:4px;margin-top:5px}.pb{flex:1;height:3px;border-radius:2px;background:var(--c-border)}
.rf{text-align:center;padding:14px 28px;border-top:1px solid var(--c-border);font-size:12px;color:var(--c-muted)}
</style>
</head>
<body>
<div class="rc">
  <div class="rh">
    <div class="ri"><i class="bi bi-shield-exclamation"></i></div>
    <div><h1>Create Your Account</h1><p>DRRMS South Caloocan – Disaster Risk Management System</p></div>
  </div>
  <div class="rb">
    <?php if($error):?><div class="msg err"><i class="bi bi-exclamation-circle"></i><span><?=hsc($error)?></span></div><?php endif;?>
    <?php if($success):?><div class="msg ok"><i class="bi bi-check-circle"></i><span><?=$success?></span></div>
    <?php else:?>
    <form method="POST" novalidate>
      <?=csrf_field()?>
      <div class="sec">Personal Information</div>
      <div class="fr mb4">
        <div class="fg"><label class="form-label">First Name *</label><input type="text" class="form-control" name="first_name" value="<?=hsc($_POST['first_name']??'')?>" required maxlength="80" id="rfn"></div>
        <div class="fg"><label class="form-label">Last Name *</label><input type="text" class="form-control" name="last_name" value="<?=hsc($_POST['last_name']??'')?>" required maxlength="80" id="rln"></div>
      </div>
      <div class="fr mb4">
        <div class="fg"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?=hsc($_POST['phone']??'')?>" maxlength="20" placeholder="+63 9XX XXX XXXX"></div>
        <div class="fg"><label class="form-label">Barangay *</label>
          <select class="form-select" name="barangay" required>
            <option value="">Select…</option>
            <?php foreach($brgys as $b):?><option value="<?=hsc($b)?>" <?=(($_POST['barangay']??'')===$b?'selected':'')?>><?=hsc($b)?></option><?php endforeach;?>
          </select>
        </div>
      </div>
      <div class="sec" style="margin-top:6px">Account Credentials</div>
      <div class="fg"><label class="form-label">Email Address *</label><input type="email" class="form-control" name="email" value="<?=hsc($_POST['email']??'')?>" required maxlength="150"></div>
      <div class="fg"><label class="form-label">Username *</label><input type="text" class="form-control" name="username" id="run" value="<?=hsc($_POST['username']??'')?>" required minlength="4" maxlength="60"></div>
      <div class="fr mb4">
        <div class="fg"><label class="form-label">Password *</label>
          <input type="password" class="form-control" name="password" id="rpw" required minlength="8" placeholder="Min. 8 characters" oninput="checkPw(this.value)">
          <div class="pbs"><div class="pb" id="pb1"></div><div class="pb" id="pb2"></div><div class="pb" id="pb3"></div><div class="pb" id="pb4"></div></div>
          <div style="font-size:11px;color:var(--c-muted);margin-top:3px" id="pbl">Enter a password</div>
        </div>
        <div class="fg"><label class="form-label">Confirm Password *</label><input type="password" class="form-control" name="confirm" required minlength="8" placeholder="Repeat password"></div>
      </div>
      <label style="display:flex;gap:8px;font-size:13px;cursor:pointer;margin-bottom:16px">
        <input type="checkbox" name="agree" style="accent-color:var(--c-accent);flex-shrink:0;margin-top:2px" <?=isset($_POST['agree'])?'checked':''?>>
        <span>I agree to the Terms of Use and the <strong>Philippine Data Privacy Act of 2012</strong>.</span>
      </label>
      <button type="submit" class="btn btn-primary" style="width:100%;padding:11px;font-size:14px;font-weight:700">
        <i class="bi bi-person-plus"></i> Create Account
      </button>
    </form>
    <?php endif;?>
  </div>
  <div class="rf">Already have an account? <a href="/drrms/login.php">Sign in here</a></div>
</div>
<script>
function checkPw(pw){
  let s=0;if(pw.length>=8)s++;if(pw.length>=12)s++;if(/[A-Z]/.test(pw)&&/[a-z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;
  const c=s<=1?'#ef4444':s<=3?'#eab308':'#22c55e',l=s<=1?'Weak':s<=3?'Fair':'Strong';
  ['pb1','pb2','pb3','pb4'].forEach((id,i)=>{const b=document.getElementById(id);b.style.background=i<s?c:'var(--c-border)';});
  const el=document.getElementById('pbl');el.textContent=pw.length?l+' password':'Enter a password';el.style.color=c;
}
['rfn','rln'].forEach(id=>document.getElementById(id)?.addEventListener('input',()=>{
  const f=(document.getElementById('rfn')?.value||'').toLowerCase().replace(/[^a-z0-9]/g,'');
  const l=(document.getElementById('rln')?.value||'').toLowerCase().replace(/[^a-z0-9]/g,'');
  const u=document.getElementById('run');if(u&&!u.dataset.edited)u.value=(f+(l?'.'+l:'')).slice(0,60);
}));
document.getElementById('run')?.addEventListener('input',function(){this.dataset.edited='1';});

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
    el.insertAdjacentElement('afterend', d);
  }
  const fields = ['first_name','last_name','email','username','password','confirm','barangay'];
  form.addEventListener('submit', e => {
    fields.forEach(n => { const el = form.querySelector(`[name="${n}"]`); if (el) clearErr(el); });
    let ok = true, first = null;
    const mark = (name, bad, msg) => {
      if (!bad) return;
      const el = form.querySelector(`[name="${name}"]`);
      showErr(el, msg); ok = false; first = first || el;
    };
    const v = n => (form.querySelector(`[name="${n}"]`)?.value || '').trim();
    mark('first_name', !v('first_name'), 'First name is required.');
    mark('last_name',  !v('last_name'),  'Last name is required.');
    mark('email',      !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email')), 'Enter a valid email address.');
    mark('username',   v('username').length < 4 || !/^[A-Za-z0-9._-]+$/.test(v('username')), 'Username must be 4+ characters (letters, numbers, . _ - only).');
    mark('password',   v('password').length < 8, 'Password must be at least 8 characters.');
    mark('confirm',    v('confirm') !== v('password'), 'Passwords do not match.');
    mark('barangay',   !v('barangay'), 'Please select your barangay.');
    if (!form.querySelector('[name="agree"]').checked) {
      toast_agree(); ok = false;
    }
    if (!ok) { e.preventDefault(); first?.focus(); }
  });
  function toast_agree(){
    const cb = form.querySelector('[name="agree"]');
    let d = cb.parentElement.querySelector('.fv-err');
    if (!d) { d = document.createElement('div'); d.className = 'fe fv-err'; cb.parentElement.insertAdjacentElement('afterend', d); }
    d.innerHTML = '<i class="bi bi-exclamation-circle"></i> You must agree to the terms to continue.';
  }
  fields.forEach(n => form.querySelector(`[name="${n}"]`)?.addEventListener('input', function(){ clearErr(this); }));
  form.querySelector('[name="agree"]')?.addEventListener('change', function(){
    if (this.checked) this.parentElement.querySelector('.fv-err')?.remove();
  });
})();
</script>
</body>
</html>
