<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) { header('Location: ' . BASE_URL . '/index.php'); exit; }
$pageTitle = 'Login — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* Split-screen login — scoped to this page only (.lo-*), so it can't
   affect the public map, admin console, or any other page's styling. */
.lo-shell { display: flex; min-height: calc(100vh - 64px); }
.lo-art {
  flex: 1 1 46%; background: linear-gradient(160deg, #0a4a1d 0%, #063614 100%); color: #fff;
  padding: 3rem 3rem; display: flex; flex-direction: column; justify-content: center; gap: 1.75rem;
  position: relative; overflow: hidden;
}
.lo-art::before, .lo-art::after {
  content: ""; position: absolute; border-radius: 50%; background: rgba(255,255,255,.05);
}
.lo-art::before { width: 340px; height: 340px; top: -120px; right: -100px; }
.lo-art::after { width: 220px; height: 220px; bottom: -80px; left: -60px; }
.lo-brand { display: flex; align-items: center; gap: .6rem; font-weight: 700; font-size: 1.15rem; position: relative; }
.lo-brand .lo-brand-mark {
  width: 42px; height: 42px; border-radius: 999px; background: rgba(255,255,255,.12);
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;
}
.lo-art h2 { font-size: 1.9rem; font-weight: 700; letter-spacing: -.02em; margin: 0; position: relative; max-width: 22ch; }
.lo-art p { color: rgba(255,255,255,.78); max-width: 42ch; margin: 0; position: relative; }
.lo-stats { display: flex; gap: 2rem; position: relative; flex-wrap: wrap; }
.lo-stat-label { font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.6); }
.lo-stat-value { font-family: "IBM Plex Mono", ui-monospace, monospace; font-size: 1.35rem; font-weight: 600; margin-top: .15rem; }
.lo-form-panel { flex: 1 1 54%; display: flex; align-items: center; justify-content: center; padding: 3rem 1.5rem; background: #fff; }
.lo-form-inner { width: 100%; max-width: 400px; }
@media (max-width: 860px) { .lo-art { display: none; } .lo-shell { min-height: auto; } }
</style>
<div class="lo-shell">
  <div class="lo-art">
    <div class="lo-brand"><span class="lo-brand-mark"><img src="<?= asset_url('/assets/img/logo-seal.png') ?>" alt="" width="30" height="30" style="border-radius:50%;object-fit:cover;"></span> South Caloocan DRRMS</div>
    <div>
      <h2>Cloud-Based Disaster &amp; Risk Detection System</h2>
      <p class="mt-2">City Disaster Risk Reduction and Management Office — multi-hazard monitoring and AI-assisted analysis for South Caloocan.</p>
    </div>
    <div class="lo-stats">
      <div><div class="lo-stat-label">Barangays monitored</div><div class="lo-stat-value">188</div></div>
      <div><div class="lo-stat-label">Hazard types</div><div class="lo-stat-value">8</div></div>
      <div><div class="lo-stat-label">AI models</div><div class="lo-stat-value">DBSCAN + LR</div></div>
    </div>
  </div>
  <div class="lo-form-panel">
    <div class="lo-form-inner" id="loginCard">
      <h4 class="mb-3"><i class="bi bi-shield-lock"></i> Log In</h4>
      <div id="loginError" class="alert alert-danger d-none" role="alert"></div>
      <form id="loginForm" novalidate>
        <div class="mb-3">
          <label class="form-label" for="loginIdentifier">Email or Username</label>
          <input type="text" class="form-control" id="loginIdentifier" name="identifier" required autofocus autocomplete="username">
          <div class="invalid-feedback">Enter your email or username.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="loginPassword">Password</label>
          <div class="input-group has-validation">
            <input type="password" class="form-control" id="loginPassword" name="password" required autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1" aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
            <div class="invalid-feedback">Enter your password.</div>
          </div>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
          <label class="form-check-label" for="rememberMe">Remember me on this device</label>
        </div>
        <button type="submit" class="btn btn-success w-100" id="loginSubmit">
          <span class="spinner-border spinner-border-sm d-none" id="loginSpinner"></span>
          <span id="loginSubmitLabel">Log In</span>
        </button>
      </form>
      <p class="text-center text-muted mt-3 mb-0">No account? <a href="<?= BASE_URL ?>/register.php">Register</a></p>
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('loginForm');
  const identifier = document.getElementById('loginIdentifier');
  const password = document.getElementById('loginPassword');
  const errBox = document.getElementById('loginError');
  const submitBtn = document.getElementById('loginSubmit');
  const spinner = document.getElementById('loginSpinner');
  const submitLabel = document.getElementById('loginSubmitLabel');

  // ---- Password visibility toggle -----------------------------------------
  document.getElementById('togglePassword').addEventListener('click', () => {
    const showing = password.type === 'text';
    password.type = showing ? 'password' : 'text';
    const btn = document.getElementById('togglePassword');
    btn.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
  });

  // ---- Live field validation (mirrors the server's own required checks) --
  function validateField(field) {
    const valid = field.checkValidity();
    field.classList.toggle('is-invalid', !valid);
    field.classList.toggle('is-valid', valid && field.value !== '');
    return valid;
  }
  [identifier, password].forEach(field => {
    field.addEventListener('blur', () => validateField(field));
    field.addEventListener('input', () => { if (field.classList.contains('is-invalid')) validateField(field); });
  });

  function setBusy(busy) {
    submitBtn.disabled = busy;
    spinner.classList.toggle('d-none', !busy);
    submitLabel.textContent = busy ? 'Logging in…' : 'Log In';
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.classList.add('d-none');

    const validIdentifier = validateField(identifier);
    const validPassword = validateField(password);
    if (!validIdentifier || !validPassword) {
      (validIdentifier ? password : identifier).focus();
      return;
    }

    setBusy(true);
    try {
      const body = {
        identifier: identifier.value.trim(),
        password: password.value,
        remember: document.getElementById('rememberMe').checked,
      };
      const res = await fetch(window.BASE_URL + '/api/auth.php?action=login', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) {
        showServerError(data.error || 'Login failed.', res.status);
        return;
      }
      window.location.href = window.BASE_URL + data.redirect;
    } catch (err) {
      errBox.textContent = 'Could not reach the server. Please check your connection and try again.';
      errBox.classList.remove('d-none');
    } finally {
      setBusy(false);
    }
  });

  // ---- Server-rejected credentials: surface it clearly and let the user
  // immediately retype without extra clicks ------------------------------
  const card = document.getElementById('loginCard');
  function showServerError(message, status) {
    errBox.textContent = message;
    errBox.classList.remove('d-none');

    // We can't know which field was wrong (the API deliberately doesn't
    // say, to avoid leaking whether an identifier exists) — a 401 marks
    // both credential fields invalid; a 423/403 (locked/deactivated) only
    // needs the identifier called out, since the password wasn't the issue.
    if (status === 401) {
      identifier.classList.add('is-invalid');
      password.classList.add('is-invalid');
    } else {
      identifier.classList.add('is-invalid');
    }
    identifier.classList.remove('is-valid');
    password.classList.remove('is-valid');
    password.value = '';

    card.classList.remove('shake');
    void card.offsetWidth; // restart the animation if it's already mid-run
    card.classList.add('shake');
    password.focus();
  }

  // Once the user starts correcting things, drop the stale server-side
  // error rather than leaving it displayed alongside fresh input.
  [identifier, password].forEach(field => {
    field.addEventListener('input', () => { if (!errBox.classList.contains('d-none')) errBox.classList.add('d-none'); });
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
