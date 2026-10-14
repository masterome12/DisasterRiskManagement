<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) { header('Location: ' . BASE_URL . '/index.php'); exit; }
$pageTitle = 'Register — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* Split-screen register — scoped to this page only (.lo-*), mirroring
   login.php's page-local styling so it can't affect any other page. */
.lo-shell { display: flex; min-height: calc(100vh - 64px); }
.lo-art {
  flex: 1 1 42%; background: linear-gradient(160deg, #0a4a1d 0%, #063614 100%); color: #fff;
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
.lo-art h2 { font-size: 1.7rem; font-weight: 700; letter-spacing: -.02em; margin: 0; position: relative; max-width: 24ch; }
.lo-art p { color: rgba(255,255,255,.78); max-width: 42ch; margin: 0; position: relative; }
.lo-art ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .6rem; position: relative; }
.lo-art ul li { display: flex; align-items: center; gap: .6rem; color: rgba(255,255,255,.85); font-size: .9rem; }
.lo-art ul li i { color: #f5c518; }
.lo-form-panel { flex: 1 1 58%; display: flex; align-items: center; justify-content: center; padding: 3rem 1.5rem; background: #fff; }
.lo-form-inner { width: 100%; max-width: 520px; }
@media (max-width: 860px) { .lo-art { display: none; } .lo-shell { min-height: auto; } }
</style>
<div class="lo-shell">
  <div class="lo-art">
    <div class="lo-brand"><span class="lo-brand-mark"><img src="<?= asset_url('/assets/img/logo-seal.png') ?>" alt="" width="30" height="30" style="border-radius:50%;object-fit:cover;"></span> South Caloocan DRRMS</div>
    <div>
      <h2>Join the risk reporting network</h2>
      <p class="mt-2">Every report you submit helps DRRMO officers spot emerging hazards faster across South Caloocan's 188 barangays.</p>
    </div>
    <ul>
      <li><i class="bi bi-check-circle-fill"></i> Report incidents with photos and location</li>
      <li><i class="bi bi-check-circle-fill"></i> Track your own report history</li>
      <li><i class="bi bi-check-circle-fill"></i> Get notified when your reports are reviewed</li>
    </ul>
  </div>
  <div class="lo-form-panel">
    <div class="lo-form-inner" id="regCard">
      <h4 class="mb-3"><i class="bi bi-person-plus"></i> Create an Account</h4>
      <div id="regError" class="alert alert-danger d-none" role="alert"></div>
      <div id="regSuccess" class="alert alert-success d-none" role="alert"></div>
      <form id="regForm" novalidate>
        <div class="mb-3">
          <label class="form-label" for="regFullName">Full Name</label>
          <input type="text" class="form-control" id="regFullName" name="full_name" required minlength="2" maxlength="120" autocomplete="name">
          <div class="invalid-feedback">Enter your full name.</div>
        </div>
        <div class="row">
          <div class="col mb-3">
            <label class="form-label" for="regEmail">Email</label>
            <input type="email" class="form-control" id="regEmail" name="email" required autocomplete="email">
            <div class="invalid-feedback">Enter a valid email address.</div>
          </div>
          <div class="col mb-3">
            <label class="form-label" for="regUsername">Username</label>
            <input type="text" class="form-control" id="regUsername" name="username" required minlength="3" maxlength="30"
                   pattern="[A-Za-z0-9_.]+" autocomplete="username">
            <div class="invalid-feedback">3-30 characters: letters, numbers, underscore, or period only.</div>
          </div>
        </div>
        <div class="row">
          <div class="col mb-3">
            <label class="form-label" for="regPassword">Password <small class="text-muted">(min 8 characters)</small></label>
            <div class="input-group has-validation">
              <input type="password" class="form-control" id="regPassword" name="password" minlength="8" required autocomplete="new-password">
              <button class="btn btn-outline-secondary" type="button" data-toggle-target="regPassword" tabindex="-1" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
              <div class="invalid-feedback">Password must be at least 8 characters.</div>
            </div>
          </div>
          <div class="col mb-3">
            <label class="form-label" for="regPasswordConfirm">Confirm Password</label>
            <div class="input-group has-validation">
              <input type="password" class="form-control" id="regPasswordConfirm" required autocomplete="new-password">
              <button class="btn btn-outline-secondary" type="button" data-toggle-target="regPasswordConfirm" tabindex="-1" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
              <div class="invalid-feedback">Passwords do not match.</div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col mb-3">
            <label class="form-label">Barangay</label>
            <input type="text" class="form-control" name="barangay" maxlength="100" placeholder="e.g. Barangay 174">
          </div>
          <div class="col mb-3">
            <label class="form-label">I am a</label>
            <select class="form-select" name="role">
              <option value="resident">User</option>
              <option value="drrmo">DRRMO Personnel</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-success w-100" id="regSubmit">
          <span class="spinner-border spinner-border-sm d-none" id="regSpinner"></span>
          <span id="regSubmitLabel">Register</span>
        </button>
      </form>
      <p class="text-center text-muted mt-3 mb-0">Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in</a></p>
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('regForm');
  const fullName = document.getElementById('regFullName');
  const email = document.getElementById('regEmail');
  const username = document.getElementById('regUsername');
  const password = document.getElementById('regPassword');
  const passwordConfirm = document.getElementById('regPasswordConfirm');
  const errBox = document.getElementById('regError');
  const okBox = document.getElementById('regSuccess');
  const submitBtn = document.getElementById('regSubmit');
  const spinner = document.getElementById('regSpinner');
  const submitLabel = document.getElementById('regSubmitLabel');

  // ---- Password visibility toggles (both password fields) ----------------
  document.querySelectorAll('[data-toggle-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const field = document.getElementById(btn.dataset.toggleTarget);
      const showing = field.type === 'text';
      field.type = showing ? 'password' : 'text';
      btn.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  });

  // ---- Live field validation ----------------------------------------------
  function validateSimple(field) {
    const valid = field.checkValidity();
    field.classList.toggle('is-invalid', !valid);
    field.classList.toggle('is-valid', valid && field.value !== '');
    return valid;
  }
  function validateConfirm() {
    const match = passwordConfirm.value !== '' && passwordConfirm.value === password.value;
    passwordConfirm.setCustomValidity(match ? '' : 'Passwords do not match.');
    const valid = passwordConfirm.checkValidity() && match;
    passwordConfirm.classList.toggle('is-invalid', !valid);
    passwordConfirm.classList.toggle('is-valid', valid);
    return valid;
  }

  [fullName, email, username, password].forEach(field => {
    field.addEventListener('blur', () => validateSimple(field));
    field.addEventListener('input', () => { if (field.classList.contains('is-invalid')) validateSimple(field); });
  });
  // Re-check the confirm field whenever either password field changes, so
  // fixing the first password also clears a stale mismatch error.
  password.addEventListener('input', () => { if (passwordConfirm.value) validateConfirm(); });
  passwordConfirm.addEventListener('blur', validateConfirm);
  passwordConfirm.addEventListener('input', () => { if (passwordConfirm.classList.contains('is-invalid')) validateConfirm(); });

  function setBusy(busy) {
    submitBtn.disabled = busy;
    spinner.classList.toggle('d-none', !busy);
    submitLabel.textContent = busy ? 'Creating account…' : 'Register';
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.classList.add('d-none');
    okBox.classList.add('d-none');

    const checks = [fullName, email, username, password].map(validateSimple);
    checks.push(validateConfirm());
    if (checks.includes(false)) {
      const firstInvalid = form.querySelector('.is-invalid');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    setBusy(true);
    try {
      const fd = new FormData(form);
      const body = Object.fromEntries(fd.entries()); // password_confirm has no `name`, so it's excluded automatically
      const res = await fetch(window.BASE_URL + '/api/auth.php?action=register', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) {
        showServerError(data.error || 'Registration failed.', res.status);
        return;
      }
      okBox.textContent = data.message;
      okBox.classList.remove('d-none');
      form.reset();
      form.querySelectorAll('.is-valid, .is-invalid').forEach(f => f.classList.remove('is-valid', 'is-invalid'));
      setTimeout(() => window.location.href = window.BASE_URL + '/login.php', 1200);
    } catch (err) {
      errBox.textContent = 'Could not reach the server. Please check your connection and try again.';
      errBox.classList.remove('d-none');
    } finally {
      setBusy(false);
    }
  });

  // ---- Server-rejected input: point at the actual field(s) at fault -----
  const card = document.getElementById('regCard');
  function showServerError(message, status) {
    errBox.textContent = message;
    errBox.classList.remove('d-none');

    // 409 = duplicate email/username (api/auth.php doesn't say which, since
    // the message itself already names both possibilities) — flag both so
    // the user checks each rather than guessing.
    if (status === 409) {
      email.classList.add('is-invalid'); email.classList.remove('is-valid');
      username.classList.add('is-invalid'); username.classList.remove('is-valid');
    }

    card.classList.remove('shake');
    void card.offsetWidth;
    card.classList.add('shake');
  }

  [fullName, email, username, password].forEach(field => {
    field.addEventListener('input', () => { if (!errBox.classList.contains('d-none')) errBox.classList.add('d-none'); });
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
