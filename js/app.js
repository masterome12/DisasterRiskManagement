/* ============================================================
   DRRMS South Caloocan — app.js  (WAMP Edition)
   Fetch API · Leaflet GIS · Marker Clustering · Role-Based UI
   ============================================================ */
'use strict';
const API  = window.D.api;
const CSRF = window.D.csrf;
const RID  = window.D.rid;

/* ── FETCH HELPERS ───────────────────────────────────────── */
async function jget(mod, params = {}) {
  const qs = new URLSearchParams({ mod, ...params }).toString();
  const r  = await fetch(`${API}?${qs}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

async function jpost(mod, act, body, id = null) {
  const fd = body instanceof FormData
    ? body
    : (() => { const f = new FormData(); Object.entries(body || {}).forEach(([k,v]) => f.append(k,v)); return f; })();
  fd.set('csrf_token', CSRF);
  let qs = `mod=${mod}&act=${act}`;
  if (id !== null) qs += `&id=${id}`;
  const r = await fetch(`${API}?${qs}`, {
    method: 'POST',
    body: fd,
    headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  });
  return r.json();
}

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML;
}

/* ── FORM VALIDATION ─────────────────────────────────────────
   Usage: validateForm(formEl, { fieldName: [[testFn, message], ...] })
   Returns true/false and paints inline errors under each bad field. */
const V = {
  required: v => v !== '',
  min:      n => v => v === '' || v.length >= n,
  pattern:  re => v => v === '' || re.test(v),
  email:    v => v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
  numRange: (lo, hi) => v => v === '' || (!isNaN(v) && +v >= lo && +v <= hi),
  intMin:   n => v => v === '' || (Number.isFinite(+v) && +v >= n),
};

function clearFieldErrors(form) {
  form.querySelectorAll('.fv-err').forEach(e => e.remove());
  form.querySelectorAll('.invalid').forEach(e => e.classList.remove('invalid'));
}

function fieldError(form, name, msg) {
  const el = form.querySelector(`[name="${name}"]`);
  if (!el) return;
  el.classList.add('invalid');
  if (!el.dataset.fvBound) {
    el.dataset.fvBound = '1';
    el.addEventListener('input', () => {
      el.classList.remove('invalid');
      el.parentElement?.querySelector('.fv-err')?.remove();
    }, { once: false });
  }
  const d = document.createElement('div');
  d.className = 'fe fv-err';
  d.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${esc(msg)}`;
  el.insertAdjacentElement('afterend', d);
}

function validateForm(form, rules) {
  clearFieldErrors(form);
  const fd = new FormData(form);
  let ok = true, firstBad = null;
  for (const [name, tests] of Object.entries(rules)) {
    const val = (fd.get(name) ?? '').toString().trim();
    for (const [test, msg] of tests) {
      if (!test(val, fd)) {
        fieldError(form, name, msg);
        ok = false;
        if (!firstBad) firstBad = form.querySelector(`[name="${name}"]`);
        break;
      }
    }
  }
  if (firstBad) firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
  return ok;
}

/* ── DIALOG MANAGER ──────────────────────────────────────────
   Custom confirm dialog replacing native confirm()/prompt(), styled to
   match the app's own modal system. Returns a Promise<boolean>. */
function showConfirm({ title = 'Please Confirm', message = 'Are you sure?', danger = false, confirmLabel = 'Confirm', cancelLabel = 'Cancel' } = {}) {
  return new Promise(resolve => {
    const wrap = document.createElement('div');
    wrap.className = 'mo open';
    wrap.innerHTML = `
      <div class="mb2" style="width:400px">
        <div class="mh"><i class="bi ${danger ? 'bi-exclamation-triangle td' : 'bi-question-circle ta'}"></i><h2>${esc(title)}</h2></div>
        <div class="mbody"><p style="font-size:13px;color:var(--c-muted);line-height:1.5">${esc(message)}</p></div>
        <div class="mf">
          <button type="button" class="btn btn-outline" data-act="cancel">${esc(cancelLabel)}</button>
          <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-act="ok">${esc(confirmLabel)}</button>
        </div>
      </div>`;
    document.body.appendChild(wrap);
    const cleanup = val => { document.removeEventListener('keydown', onKey); wrap.remove(); resolve(val); };
    wrap.addEventListener('click', e => { if (e.target === wrap) cleanup(false); });
    wrap.querySelector('[data-act="cancel"]').onclick = () => cleanup(false);
    wrap.querySelector('[data-act="ok"]').onclick = () => cleanup(true);
    const onKey = e => { if (e.key === 'Escape') cleanup(false); };
    document.addEventListener('keydown', onKey);
    requestAnimationFrame(() => wrap.querySelector('[data-act="ok"]').focus());
  });
}

/* Status-update dialog (replaces the old prompt()-based flow). Returns
   Promise<{status, remarks}|null> — null means the user cancelled. */
function showStatusUpdate(current) {
  const sts = ['Pending', 'Active', 'Responding', 'Monitoring', 'Resolved', 'Archived'];
  return new Promise(resolve => {
    const wrap = document.createElement('div');
    wrap.className = 'mo open';
    wrap.innerHTML = `
      <div class="mb2" style="width:420px">
        <div class="mh"><i class="bi bi-pencil-square ta"></i><h2>Update Incident Status</h2></div>
        <div class="mbody">
          <div class="fg"><label class="form-label">Status</label>
            <select class="form-select" name="status">${sts.map(s => `<option ${s === current ? 'selected' : ''}>${s}</option>`).join('')}</select>
          </div>
          <div class="fg"><label class="form-label">Remarks (optional)</label>
            <textarea class="form-control" name="remarks" maxlength="1000" placeholder="What changed?"></textarea>
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn btn-outline" data-act="cancel">Cancel</button>
          <button type="button" class="btn btn-primary" data-act="ok"><i class="bi bi-check-lg"></i> Update</button>
        </div>
      </div>`;
    document.body.appendChild(wrap);
    const cleanup = val => { document.removeEventListener('keydown', onKey); wrap.remove(); resolve(val); };
    wrap.addEventListener('click', e => { if (e.target === wrap) cleanup(null); });
    wrap.querySelector('[data-act="cancel"]').onclick = () => cleanup(null);
    wrap.querySelector('[data-act="ok"]').onclick = () => cleanup({
      status: wrap.querySelector('[name="status"]').value,
      remarks: wrap.querySelector('[name="remarks"]').value.trim(),
    });
    const onKey = e => { if (e.key === 'Escape') cleanup(null); };
    document.addEventListener('keydown', onKey);
  });
}

/* ── NAVIGATION ──────────────────────────────────────────── */
const TITLES = {
  dashboard:'Dashboard', map:'Interactive GIS Map', incidents:'Incident Reports',
  hazards:'Hazard Zone Management', alerts:'Alert Management',
  reports:'Reports & Analytics', users:'User Management',
  settings:'System Settings', logs:'Activity Logs'
};
const LOADERS = {
  dashboard: loadDash,
  map:       () => { setTimeout(() => { if (lmap) { lmap.invalidateSize(); refreshMap(); } }, 120); },
  incidents: loadInc,
  hazards:   loadHz,
  alerts:    loadAl,
  reports:   loadReports,
  users:     loadUsers,
  settings:  loadSettings,
  logs:      loadLogs
};

function nav(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.ni[data-page]').forEach(n => n.classList.remove('active'));
  const pg = document.getElementById('page-' + page);
  if (!pg) return;
  pg.classList.add('active');
  const navEl = document.querySelector(`.ni[data-page="${page}"]`);
  if (navEl) navEl.classList.add('active');
  document.getElementById('pgtitle').textContent = TITLES[page] || page;
  LOADERS[page]?.();
  if (window.innerWidth <= 768) document.getElementById('sb').classList.remove('mob');
}

document.querySelectorAll('.ni[data-page]').forEach(item => {
  item.addEventListener('click', e => { e.preventDefault(); nav(item.dataset.page); });
});

function toggleSB() {
  const sb = document.getElementById('sb');
  if (window.innerWidth <= 768) sb.classList.toggle('mob');
  else sb.classList.toggle('col');
}

/* ── TOAST ───────────────────────────────────────────────── */
function toast(msg, type = 'info') {
  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const el = document.createElement('div');
  el.className = 'toast';
  el.innerHTML = `<span class="tico"><i class="bi ${icons[type] || icons.info}"></i></span><div class="tbody"><h5>${esc(msg)}</h5></div>`;
  document.getElementById('tc').appendChild(el);
  setTimeout(() => { el.classList.add('tout'); setTimeout(() => el.remove(), 320); }, 4000);
}

/* ── MODALS ──────────────────────────────────────────────── */
function openM(id) {
  document.getElementById(id).classList.add('open');
  if (id === 'inc-modal') {
    const dt = document.querySelector('#inc-form [name=incident_datetime]');
    if (dt && !dt.value) dt.value = new Date().toISOString().slice(0, 16);
  }
}
function closeM(id) {
  document.getElementById(id)?.classList.remove('open');
  const f = document.querySelector(`#${id} form`);
  if (f) { f.reset(); f.querySelectorAll('.fe').forEach(e => e.textContent = ''); }
  if (id === 'hz-modal') {
    document.getElementById('hz-id').value = '';
    document.getElementById('hz-mtit').textContent = 'Add Hazard Zone';
  }
  if (id === 'usr-modal') {
    document.getElementById('usr-id').value = '';
    document.getElementById('usr-mtit').textContent = 'Add User';
    document.getElementById('pw-grp').style.display = 'block';
    document.getElementById('u-pw').required = true;
    document.getElementById('u-email')?.removeAttribute('disabled');
    document.getElementById('u-uname')?.removeAttribute('disabled');
  }
}
document.querySelectorAll('.mo').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

/* ── THEME ───────────────────────────────────────────────── */
function toggleTheme() {
  const h = document.documentElement;
  const isDark = h.getAttribute('data-theme') === 'dark';
  h.setAttribute('data-theme', isDark ? 'light' : 'dark');
  document.getElementById('thico').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
  localStorage.setItem('drrms_theme', isDark ? 'light' : 'dark');
  if (window._donutData) renderDonut(window._donutData);
}
(function initTheme() {
  const s = localStorage.getItem('drrms_theme');
  if (s) {
    document.documentElement.setAttribute('data-theme', s);
    document.getElementById('thico').className = s === 'dark' ? 'bi bi-moon' : 'bi bi-sun';
  }
})();

/* ── USER MENU ───────────────────────────────────────────── */
function toggleUMenu() { document.getElementById('umenu').classList.toggle('open'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.ndw'))   document.getElementById('ndd')?.classList.remove('open');
  if (!e.target.closest('.uc'))    document.getElementById('umenu')?.classList.remove('open');
});

/* ── NOTIFICATIONS (from active alerts) ──────────────────── */
function toggleNotif() {
  const d = document.getElementById('ndd');
  d.classList.toggle('open');
  if (d.classList.contains('open')) loadNotif();
}
async function loadNotif() {
  try {
    const r = await jget('alerts', { act: 'active' });
    const list = document.getElementById('nlist');
    const dot  = document.getElementById('ndot');
    if (!r.success || !r.data.length) {
      list.innerHTML = '<div class="ndi"><p>No active alerts.</p></div>';
      dot.style.display = 'none'; return;
    }
    dot.style.display = 'block';
    list.innerHTML = r.data.map(a => `
      <div class="ndi">
        <h5><i class="bi bi-broadcast td"></i> ${esc(a.title)}</h5>
        <p>${esc((a.message || '').slice(0, 80))}${(a.message || '').length > 80 ? '…' : ''}</p>
      </div>`).join('');
  } catch(e) { console.error('notif error', e); }
}
function clearNotifs() {
  document.getElementById('ndot').style.display = 'none';
  document.getElementById('nlist').innerHTML = '<div class="ndi"><p>No active alerts.</p></div>';
  toast('Notifications dismissed.', 'success');
}

/* ── CLOCK ───────────────────────────────────────────────── */
(function () {
  const el = document.getElementById('clk');
  const tick = () => { el.textContent = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'medium' }); };
  tick(); setInterval(tick, 1000);
})();

/* ═══════════════════════════════════════════════════════════
   DASHBOARD
═══════════════════════════════════════════════════════════ */
async function loadDash() {
  try {
    const r = await jget('dashboard');
    if (!r.success) { toast(r.message, 'danger'); return; }
    const { inc, hz, al, us, ev, rec, log, bt, bb, bm, aa } = r.data;

    // Stat cards
    document.getElementById('stat-grid').innerHTML = `
      <div class="sc sc-d"><span class="sl">Active Incidents</span><span class="sv td">${inc.active ?? 0}</span><span class="ss2">${inc.today ?? 0} reported today</span><i class="bi bi-exclamation-triangle sico td"></i></div>
      <div class="sc sc-w"><span class="sl">Hazard Zones</span><span class="sv tw2">${hz.total ?? 0}</span><span class="ss2">${hz.critical ?? 0} critical, ${hz.high ?? 0} high</span><i class="bi bi-geo-alt sico tw2"></i></div>
      <div class="sc sc-i"><span class="sl">Active Alerts</span><span class="sv ti">${al.total ?? 0}</span><span class="ss2">${al.critical ?? 0} critical level</span><i class="bi bi-bell sico ti"></i></div>
      <div class="sc sc-s"><span class="sl">Evacuees</span><span class="sv ts">${ev.evacuees ?? 0}</span><span class="ss2">${ev.open ?? 0} of ${ev.total ?? 0} centers open</span><i class="bi bi-house sico ts"></i></div>
      <div class="sc sc-a"><span class="sl">Registered Users</span><span class="sv ta">${us.total ?? 0}</span><span class="ss2">${us.active_users ?? 0} active accounts</span><i class="bi bi-people sico ta"></i></div>
      <div class="sc sc-i"><span class="sl">Affected Persons</span><span class="sv ti">${inc.ap ?? 0}</span><span class="ss2">${inc.af ?? 0} families</span><i class="bi bi-file-text sico ti"></i></div>`;

    // Nav badges
    const ni = document.getElementById('n-inc'); if (ni) ni.textContent = inc.active ?? 0;
    const na = document.getElementById('n-al');  if (na) na.textContent = al.total ?? 0;

    // Recent incidents
    const IC = { Fire: ['ift','bi-fire'], Flood: ['ifl','bi-droplet-fill'], Earthquake: ['ieq','bi-activity'] };
    const SB = { Critical:'bd', High:'bw', Moderate:'bi2', Low:'bs' };
    document.getElementById('rec-inc').innerHTML = rec.length
      ? rec.map(i => {
          const [cls, ic] = IC[i.type] || ['ioth', 'bi-exclamation-triangle-fill'];
          return `<div class="ir"><div class="ir-ic ${cls}"><i class="bi ${ic}"></i></div>
            <div class="ir-info flex1"><h4>${esc(i.type)} — ${esc(i.barangay)}</h4>
            <p>${esc(i.title)} · ${esc(i.reporter || 'Unknown')}</p></div>
            <span class="badge ${SB[i.severity] || 'bm'}">${i.severity}</span></div>`;
        }).join('')
      : '<div class="ir"><p class="tm">No incidents reported yet.</p></div>';

    // Activity log
    document.getElementById('act-tb').innerHTML = log.length
      ? log.map(a => `<tr>
          <td style="font-size:12px;color:var(--c-muted)">${fmtTime(a.created_at)}</td>
          <td style="font-weight:500">${esc(a.username || 'system')}</td>
          <td style="font-size:12px">${esc(a.description || a.action)}</td>
          <td><span class="badge bm">${esc(a.module)}</span></td></tr>`).join('')
      : '<tr class="er"><td colspan="4">No activity yet.</td></tr>';

    // Charts
    barChart('ch-type', bt.map(r => ({ l: r.type, v: +r.cnt })), ['#ef4444','#3b82f6','#eab308','#f97316','#6b7280','#8b5cf6']);
    barChart('ch-brgy', bb.map(r => ({ l: r.barangay, v: +r.cnt })), ['#f97316','#ef4444','#3b82f6','#eab308','#8b5cf6']);

    // Donut
    window._donutData = { critical: +hz.critical || 0, high: +hz.high || 0, total: +hz.total || 0 };
    renderDonut(window._donutData);

    // Alerts panel + ticker
    const AC = { Critical:'critical', High:'high', Moderate:'high', Advisory:'info' };
    const AI = { Flood:'bi-droplet-fill', Fire:'bi-fire', Typhoon:'bi-cloud-rain', Earthquake:'bi-activity' };
    const dashAl = document.getElementById('dash-alerts');
    if (!aa.length) {
      dashAl.innerHTML = '<p class="tm" style="font-size:13px">No active alerts.</p>';
    } else {
      dashAl.innerHTML = aa.slice(0, 4).map(a => `
        <div class="ai ${AC[a.level] || 'info'}">
          <h4><i class="bi ${AI[a.type] || 'bi-bell'}"></i> ${esc(a.title)}</h4>
          <p>${esc(a.affected_areas || '')}</p>
          <time>Issued ${fmtRel(a.created_at)}</time>
        </div>`).join('');
      const tk = document.getElementById('tkrtxt');
      const LVC = { Critical:'#ef4444', High:'#f97316', Moderate:'#eab308', Advisory:'#3b82f6' };
      tk.innerHTML = aa.map(a => {
        const c = LVC[a.level] || LVC.Advisory;
        return `<i class="bi bi-circle-fill" style="color:${c};font-size:8px;vertical-align:middle"></i> ${a.title.toUpperCase()}: ${(a.message || '').slice(0, 100)}`;
      }).join(' &nbsp;&nbsp;&nbsp;&nbsp; ');
      document.getElementById('tkr').classList.remove('hidden');
    }
  } catch(e) { toast('Failed to load dashboard.', 'danger'); console.error(e); }
}

/* ── Time helpers ────────────────────────────────────────── */
function fmtTime(s) {
  return new Date(s.replace(' ','T')).toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
}
function fmtDT(s) {
  return new Date(s.replace(' ','T')).toLocaleString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}
function fmtRel(s) {
  const d = (Date.now() - new Date(s.replace(' ','T')).getTime()) / 60000;
  if (d < 1) return 'just now';
  if (d < 60) return Math.floor(d) + 'm ago';
  if (d < 1440) return Math.floor(d / 60) + 'h ago';
  return Math.floor(d / 1440) + 'd ago';
}

/* ── Bar chart ───────────────────────────────────────────── */
function barChart(elId, data, colors) {
  const el = document.getElementById(elId); if (!el) return;
  if (!data.length) { el.innerHTML = '<p class="tm" style="font-size:13px">No data available.</p>'; return; }
  const max = Math.max(...data.map(d => d.v), 1);
  el.innerHTML = data.map((d, i) => `
    <div class="br2">
      <div class="bl2">${esc(d.l)}</div>
      <div class="bt2"><div class="bf2" data-w="${(d.v / max * 100).toFixed(0)}" style="background:${colors[i % colors.length]}"></div></div>
      <div class="bv2">${d.v}</div>
    </div>`).join('');
  requestAnimationFrame(() => el.querySelectorAll('.bf2').forEach(f => f.style.width = f.dataset.w + '%'));
}

/* ── Donut chart ─────────────────────────────────────────── */
function renderDonut(d) {
  const canvas = document.getElementById('donut'); if (!canvas) return;
  const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, 120, 120);
  const other = Math.max(0, d.total - d.critical - d.high);
  const slices = [
    { v: d.critical, c: '#ef4444', l: 'Critical' },
    { v: d.high,     c: '#f97316', l: 'High' },
    { v: other,      c: '#eab308', l: 'Other' }
  ].filter(s => s.v > 0);
  document.getElementById('donut-n').textContent = d.total;
  if (!slices.length) { document.getElementById('dleg').innerHTML = '<p class="tm" style="font-size:12px">No hazard zones yet.</p>'; return; }
  const total = slices.reduce((a, s) => a + s.v, 0);
  let ang = -Math.PI / 2;
  slices.forEach(s => {
    const sl = (s.v / total) * Math.PI * 2;
    ctx.beginPath(); ctx.moveTo(60, 60); ctx.arc(60, 60, 52, ang, ang + sl); ctx.closePath();
    ctx.fillStyle = s.c; ctx.fill(); ang += sl;
  });
  const bg = getComputedStyle(document.documentElement).getPropertyValue('--c-surface').trim();
  ctx.beginPath(); ctx.arc(60, 60, 32, 0, Math.PI * 2); ctx.fillStyle = bg || '#111827'; ctx.fill();
  document.getElementById('dleg').innerHTML = slices.map(s =>
    `<div class="dli"><div class="ds" style="background:${s.c}"></div><span>${s.l} (${s.v})</span></div>`).join('');
}

/* ═══════════════════════════════════════════════════════════
   GIS MAP
═══════════════════════════════════════════════════════════ */
let lmap, cluster, hzLyr, evLyr;
const TILES = {};
const TC = { Fire:'#ef4444', Flood:'#3b82f6', Earthquake:'#eab308', Landslide:'#f97316', Typhoon:'#8b5cf6', Other:'#6b7280', Infrastructure:'#6b7280', Chemical:'#22c55e' };
const TE = { Fire:'bi-fire', Flood:'bi-droplet-fill', Earthquake:'bi-activity', Landslide:'bi-triangle-fill', Typhoon:'bi-cloud-rain', Other:'bi-question-circle-fill', Infrastructure:'bi-cone-striped', Chemical:'bi-exclamation-octagon-fill' };

function initMap() {
  if (lmap) return;
  lmap = L.map('map', { center: [14.7500, 120.9833], zoom: 14 });

  TILES.osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors', maxZoom: 19 });
  TILES.topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { attribution: '© OpenTopoMap', maxZoom: 17 });
  TILES.sat  = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '© Esri', maxZoom: 19 });
  TILES.osm.addTo(lmap);

  lmap.on('mousemove', e => {
    document.getElementById('m-lat').textContent = 'Lat: ' + e.latlng.lat.toFixed(5);
    document.getElementById('m-lng').textContent = 'Lng: ' + e.latlng.lng.toFixed(5);
  });
  lmap.on('zoomend', () => { document.getElementById('m-zoom').textContent = 'Zoom: ' + lmap.getZoom(); });
  lmap.fire('zoomend');

  cluster = L.markerClusterGroup({ maxClusterRadius: 60, spiderfyOnMaxZoom: true, showCoverageOnHover: false });
  lmap.addLayer(cluster);
  hzLyr = L.layerGroup().addTo(lmap);
  evLyr = L.layerGroup().addTo(lmap);
  refreshMap();
}

function setLayer(t) {
  Object.entries(TILES).forEach(([k, l]) => {
    if (lmap.hasLayer(l)) lmap.removeLayer(l);
    document.getElementById('ml-' + k)?.classList.remove('on');
  });
  TILES[t]?.addTo(lmap);
  document.getElementById('ml-' + t)?.classList.add('on');
}

function togLay(t, show) {
  if (t === 'inc') show ? lmap.addLayer(cluster) : lmap.removeLayer(cluster);
  if (t === 'hz')  show ? lmap.addLayer(hzLyr)   : lmap.removeLayer(hzLyr);
  if (t === 'ev')  show ? lmap.addLayer(evLyr)    : lmap.removeLayer(evLyr);
}

async function refreshMap() {
  const lo = document.getElementById('mload'); lo.style.display = 'flex';
  await Promise.all([loadIncMarkers(), loadHzLayer(), loadEvLayer()]);
  lo.style.display = 'none';
}
function reloadInc() { loadIncMarkers(); }

async function loadIncMarkers() {
  const type = document.getElementById('map-tf')?.value || '';
  const r = await jget('incidents', { act: 'geojson', type });
  if (!r.type) return;
  cluster.clearLayers();
  r.features.forEach(f => {
    const p = f.properties;
    const [lng, lat] = f.geometry.coordinates;
    const color = TC[p.type] || '#6b7280';
    const ic    = TE[p.type] || 'bi-exclamation-triangle-fill';
    const icon  = L.divIcon({
      html: `<div style="background:${color};width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center"><i class="bi ${ic}" style="transform:rotate(45deg);font-size:13px;color:#fff"></i></div>`,
      className: '', iconSize: [28, 28], iconAnchor: [14, 28]
    });
    const m = L.marker([lat, lng], { icon }).bindPopup(`
      <div style="min-width:210px;font-family:system-ui;font-size:13px">
        <div style="font-weight:700;font-size:14px;margin-bottom:6px"><i class="bi ${ic}" style="color:${color}"></i> ${esc(p.type)} ${p.is_verified ? '' : '<span style="color:#eab308">(Unverified)</span>'}</div>
        <div><b>Code:</b> ${esc(p.incident_code)}</div>
        <div><b>Title:</b> ${esc(p.title)}</div>
        <div><b>Barangay:</b> ${esc(p.barangay)}</div>
        <div><b>Address:</b> ${esc(p.address)}</div>
        <div><b>Severity:</b> <span style="color:${color};font-weight:700">${p.severity}</span></div>
        <div><b>Status:</b> ${p.status}</div>
        <div><b>Reported:</b> ${p.datetime}</div>
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #eee"><b>Reporter:</b> ${esc(p.reporter || 'Unknown')}</div>
        <button onclick="viewInc(${p.id})" style="margin-top:8px;width:100%;padding:6px;background:${color};color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:600">View Details</button>
      </div>`);
    cluster.addLayer(m);
  });
}

async function loadHzLayer() {
  const r = await jget('hazards', { act: 'geojson' });
  if (!r.type) return;
  hzLyr.clearLayers();
  r.features.forEach(f => {
    const p = f.properties;
    L.geoJSON(f, { style: { color: p.color, fillColor: p.color, fillOpacity: p.fill_opacity || 0.18, weight: 2 } })
      .bindPopup(`<div style="min-width:190px;font-family:system-ui;font-size:13px">
        <div style="font-weight:700;font-size:14px;margin-bottom:6px">${esc(p.name)}</div>
        <div><b>Type:</b> ${esc(p.type)}</div>
        <div><b>Risk Level:</b> <span style="color:${p.color};font-weight:700">${p.risk_level}</span></div>
        <div><b>Area:</b> ${p.area_sqkm ? p.area_sqkm + ' km²' : 'N/A'}</div>
        <div><b>Population at Risk:</b> ${Number(p.population_risk).toLocaleString()}</div>
        ${p.description ? `<div style="margin-top:6px;font-size:11px;color:#666">${esc(p.description)}</div>` : ''}
      </div>`)
      .addTo(hzLyr);
  });
}

async function loadEvLayer() {
  const r = await jget('evac');
  if (!r.type) return;
  evLyr.clearLayers();
  const icon = L.divIcon({
    html: '<div style="background:#22c55e;width:26px;height:26px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:13px"><i class="bi bi-house-fill" style="color:#fff"></i></div>',
    className: '', iconSize: [26, 26], iconAnchor: [13, 13]
  });
  r.features.forEach(f => {
    const p = f.properties;
    const [lng, lat] = f.geometry.coordinates;
    const sc = { Open:'#22c55e', Full:'#ef4444', Standby:'#3b82f6', Closed:'#6b7280' }[p.status] || '#6b7280';
    L.marker([lat, lng], { icon }).bindPopup(`
      <div style="min-width:200px;font-family:system-ui;font-size:13px">
        <div style="font-weight:700;font-size:14px;margin-bottom:6px"><i class="bi bi-house-fill"></i> ${esc(p.name)}</div>
        <div><b>Status:</b> <span style="color:${sc};font-weight:700">${p.status}</span></div>
        <div><b>Address:</b> ${esc(p.address)}</div>
        <div><b>Occupancy:</b> ${p.current_count}/${p.capacity} (${p.occupancy_pct}%)</div>
        ${p.contact_person ? `<div><b>Contact:</b> ${esc(p.contact_person)} ${p.contact_phone ? '· ' + p.contact_phone : ''}</div>` : ''}
      </div>`).addTo(evLyr);
  });
}

function getMyLoc() {
  if (!navigator.geolocation) { toast('Geolocation not supported.', 'warning'); return; }
  navigator.geolocation.getCurrentPosition(
    pos => {
      document.querySelector('#inc-form [name=latitude]').value  = pos.coords.latitude.toFixed(7);
      document.querySelector('#inc-form [name=longitude]').value = pos.coords.longitude.toFixed(7);
      toast('Location captured successfully.', 'success');
    },
    () => toast('Unable to retrieve your location.', 'danger')
  );
}

/* ═══════════════════════════════════════════════════════════
   INCIDENTS
═══════════════════════════════════════════════════════════ */
const SB = { Critical:'bd', High:'bw', Moderate:'bi2', Low:'bs' };
const STB = { Pending:'bm', Active:'bd', Responding:'bw', Monitoring:'bi2', Resolved:'bs', Archived:'bm' };
let incDeb;
function debInc() { clearTimeout(incDeb); incDeb = setTimeout(loadInc, 350); }

async function loadInc() {
  try {
    const params = {
      search: document.getElementById('inc-s')?.value || '',
      type:   document.getElementById('inc-ft')?.value || '',
      status: document.getElementById('inc-fs')?.value || '',
      limit:  50
    };
    const r  = await jget('incidents', params);
    const tb = document.getElementById('inc-tb');
    if (!r.success) { tb.innerHTML = `<tr class="er"><td colspan="9">${esc(r.message)}</td></tr>`; return; }
    const { items, total } = r.data;
    document.getElementById('inc-ct').textContent = total + ' record' + (total === 1 ? '' : 's');
    tb.innerHTML = items.length
      ? items.map(i => `
        <tr>
          <td><code>${esc(i.incident_code)}</code></td>
          <td style="font-size:12px">${fmtDT(i.incident_datetime)}</td>
          <td>${esc(i.barangay)}</td>
          <td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(i.address)}">${esc(i.address)}</td>
          <td>${esc(i.reporter_name || '—')}</td>
          <td><span class="badge ${SB[i.severity] || 'bm'}"><i class="bi ${TE[i.type] || 'bi-exclamation-triangle-fill'}"></i> ${esc(i.type)}</span></td>
          <td><span class="sd s-${i.severity.toLowerCase()}"></span>${i.severity}</td>
          <td><span class="badge ${STB[i.status] || 'bm'}">${i.status}</span>${i.is_verified ? '' : ' <i class="bi bi-question-circle tw2" title="Unverified"></i>'}</td>
          <td><div style="display:flex;gap:4px">
            <button class="btn btn-outline btn-xs" onclick="viewInc(${i.id})" title="View"><i class="bi bi-eye"></i></button>
            ${RID >= 2 ? `<button class="btn btn-outline btn-xs" onclick="quickUpd(${i.id},'${i.status}')" title="Update"><i class="bi bi-pencil"></i></button>` : ''}
            ${RID >= 4 ? `<button class="btn btn-xs" style="background:rgba(239,68,68,.1);color:#f87171;border:none" onclick="delInc(${i.id},'${esc(i.incident_code)}')" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
          </div></td>
        </tr>`).join('')
      : '<tr class="er"><td colspan="9">No incidents found.</td></tr>';
  } catch(e) { toast('Failed to load incidents.', 'danger'); }
}

document.getElementById('inc-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('inc-sub');
  const err = document.getElementById('inc-err');
  err.textContent = '';
  const valid = validateForm(form, {
    type:               [[V.required, 'Please select a hazard type.']],
    severity:           [[V.required, 'Please select a severity.']],
    title:              [[V.required, 'Title is required.'], [V.min(5), 'Title must be at least 5 characters.']],
    barangay:           [[V.required, 'Please select a barangay.']],
    incident_datetime:  [[V.required, 'Date & time is required.']],
    address:            [[V.required, 'Address is required.'], [V.min(5), 'Address looks too short.']],
    latitude:           [[V.required, 'Latitude is required.'], [V.numRange(14.0, 15.0), 'Latitude must be within the service area (14.0–15.0).']],
    longitude:          [[V.required, 'Longitude is required.'], [V.numRange(120.5, 121.5), 'Longitude must be within the service area (120.5–121.5).']],
    description:        [[V.required, 'Description is required.'], [V.min(10), 'Please provide more detail (10+ characters).']],
  });
  if (!valid) { toast('Please fix the highlighted fields.', 'warning'); return; }
  btn.disabled = true;
  btn.innerHTML = '<span class="spns"></span> Submitting…';
  try {
    const r = await jpost('incidents', 'create', new FormData(e.target));
    if (r.success) {
      toast(`Incident ${r.data.code} submitted successfully!`, 'success');
      closeM('inc-modal');
      if (document.getElementById('page-incidents')?.classList.contains('active')) loadInc();
      if (document.getElementById('page-dashboard')?.classList.contains('active')) loadDash();
      if (document.getElementById('page-map')?.classList.contains('active')) loadIncMarkers();
    } else {
      err.textContent = r.message;
      toast(r.message, 'danger');
    }
  } catch(ex) { err.textContent = 'Network error. Please try again.'; }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-send"></i> Submit Report';
});

async function viewInc(id) {
  openM('inc-det-modal');
  const body = document.getElementById('inc-det-body');
  body.innerHTML = '<div style="text-align:center;padding:40px"><div class="spn" style="margin:0 auto"></div></div>';
  try {
    const r = await jget('incidents', { act: 'single', id });
    if (!r.success) { body.innerHTML = `<p class="td">${esc(r.message)}</p>`; return; }
    const i = r.data;
    body.innerHTML = `
      <div class="g2 mb4">
        <div><span class="form-label">Code</span><div><code style="font-size:13px">${esc(i.incident_code)}</code></div></div>
        <div><span class="form-label">Status</span><div><span class="badge ${STB[i.status] || 'bm'}">${i.status}</span> ${i.is_verified ? '<span class="badge bs">Verified</span>' : '<span class="badge bw">Unverified</span>'}</div></div>
      </div>
      <h3 style="font-size:16px;margin-bottom:6px"><i class="bi ${TE[i.type] || 'bi-exclamation-triangle-fill'}"></i> ${esc(i.title)}</h3>
      <p class="tm" style="font-size:13px;margin-bottom:14px">${esc(i.description)}</p>
      <div class="g2 mb4">
        <div><span class="form-label">Type</span><div>${esc(i.type)}</div></div>
        <div><span class="form-label">Severity</span><div><span class="sd s-${i.severity.toLowerCase()}"></span>${i.severity}</div></div>
        <div><span class="form-label">Barangay</span><div>${esc(i.barangay)}</div></div>
        <div><span class="form-label">Address</span><div>${esc(i.address)}</div></div>
        <div><span class="form-label">Coordinates</span><div>${parseFloat(i.latitude).toFixed(6)}, ${parseFloat(i.longitude).toFixed(6)}</div></div>
        <div><span class="form-label">Date/Time</span><div>${fmtDT(i.incident_datetime)}</div></div>
        <div><span class="form-label">Reporter</span><div>${esc(i.reporter_name || 'Unknown')}</div></div>
        <div><span class="form-label">Affected</span><div>${i.affected_families} families / ${i.affected_persons} persons</div></div>
      </div>
      ${i.photo ? `<div class="mb4"><span class="form-label">Photo</span><div style="margin-top:6px"><img src="/drrms/${esc(i.photo)}" style="max-width:260px;border-radius:8px;border:1px solid var(--c-border)"></div></div>` : ''}
      ${RID >= 2 ? `<div class="mb4"><button class="btn btn-outline btn-sm" onclick="quickUpd(${i.id},'${i.status}')"><i class="bi bi-pencil"></i> Update Status</button></div>` : ''}`;
  } catch(ex) { body.innerHTML = '<p class="td">Failed to load incident details.</p>'; }
}

async function quickUpd(id, cur) {
  const res = await showStatusUpdate(cur);
  if (!res) return;
  const r = await jpost('incidents', 'update', { status: res.status, remarks: res.remarks, verify: 1 }, id);
  if (r.success) {
    toast('Incident updated successfully.', 'success');
    loadInc(); loadDash();
    if (document.getElementById('inc-det-modal')?.classList.contains('open')) viewInc(id);
  } else toast(r.message, 'danger');
}

async function delInc(id, code) {
  const ok = await showConfirm({ title: 'Delete Incident', message: `Delete incident ${code}? This cannot be undone.`, danger: true, confirmLabel: 'Delete' });
  if (!ok) return;
  const r = await jpost('incidents', 'delete', {}, id);
  if (r.success) { toast('Incident deleted.', 'success'); loadInc(); loadDash(); }
  else toast(r.message, 'danger');
}

/* ═══════════════════════════════════════════════════════════
   HAZARD ZONES
═══════════════════════════════════════════════════════════ */
const RC  = { Critical:'#ef4444', High:'#f97316', Moderate:'#eab308', Low:'#22c55e' };
const HZI = { Flood:'bi-droplet-fill', Fire:'bi-fire', Earthquake:'bi-activity', Landslide:'bi-triangle-fill', 'Storm Surge':'bi-water', Tsunami:'bi-water', Volcanic:'bi-triangle', Other:'bi-question-circle-fill' };

async function loadHz() {
  const r = await jget('hazards');
  const c = document.getElementById('hz-cards');
  if (!r.success) { c.innerHTML = `<p class="td">${esc(r.message)}</p>`; return; }
  const data = r.data;
  const crit = data.filter(h => h.risk_level === 'Critical').length;

  document.getElementById('hz-banner').innerHTML = crit > 0 ? `
    <div class="ai critical mb4" style="display:flex;align-items:center;gap:14px;padding:14px 18px">
      <i class="bi bi-exclamation-octagon-fill" style="font-size:28px"></i>
      <div class="flex1"><h4 style="font-size:16px">${crit} Critical Hazard Zone${crit > 1 ? 's' : ''} Active</h4><p>Immediate attention required.</p></div>
      <button class="btn btn-danger btn-sm" onclick="nav('map')"><i class="bi bi-map"></i> View on Map</button>
    </div>` : '';

  c.innerHTML = data.length ? data.map(h => `
    <div class="hzc">
      <div class="hzh">
        <i class="bi ${HZI[h.type] || 'bi-question-circle-fill'}" style="font-size:20px"></i>
        <h4>${esc(h.name)}</h4>
        <span class="badge" style="background:${RC[h.risk_level]}20;color:${RC[h.risk_level]}">${h.risk_level}</span>
      </div>
      <p class="hzd">${esc(h.description || 'No description provided.')}</p>
      <div class="hzst">
        <div class="hzsi"><strong style="color:${RC[h.risk_level]}">${h.area_sqkm ? h.area_sqkm + ' km²' : '—'}</strong><span>Coverage</span></div>
        <div class="hzsi"><strong>${Number(h.population_risk).toLocaleString()}</strong><span>At Risk</span></div>
        <div class="hzsi"><strong>${(h.barangays || '').split(',').filter(Boolean).length}</strong><span>Barangays</span></div>
      </div>
      <div class="hzft">
        <i class="bi bi-geo-alt"></i> ${esc(h.barangays || 'N/A')}
        <div style="margin-left:auto;display:flex;gap:6px">
          <button class="btn btn-outline btn-xs" onclick="nav('map')"><i class="bi bi-map"></i> Map</button>
          ${RID >= 3 ? `<button class="btn btn-outline btn-xs" onclick='editHz(${JSON.stringify(h).replace(/'/g,"&#39;")})' ><i class="bi bi-pencil"></i> Edit</button>` : ''}
          ${RID >= 5 ? `<button class="btn btn-xs" style="background:rgba(239,68,68,.1);color:#f87171;border:none" onclick="delHz(${h.id},'${esc(h.name)}')"><i class="bi bi-trash"></i></button>` : ''}
        </div>
      </div>
    </div>`).join('') : '<p class="tm">No hazard zones defined yet.</p>';
}

function editHz(h) {
  document.getElementById('hz-mtit').textContent = 'Edit Hazard Zone';
  const f = document.getElementById('hz-form');
  document.getElementById('hz-id').value = h.id;
  f.name.value         = h.name;
  f.type.value         = h.type;
  f.risk_level.value   = h.risk_level;
  f.area_sqkm.value    = h.area_sqkm || '';
  f.center_lat.value   = h.center_lat;
  f.center_lng.value   = h.center_lng;
  f.radius_meters.value= h.radius_meters || '';
  f.color.value        = h.color || '#ef4444';
  f.barangays.value    = h.barangays || '';
  f.description.value  = h.description || '';
  f.population_risk.value = h.population_risk || '';
  openM('hz-modal');
}

document.getElementById('hz-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const id  = document.getElementById('hz-id').value;
  const err = document.getElementById('hz-err');
  err.textContent = '';
  const valid = validateForm(form, {
    name:          [[V.required, 'Zone name is required.'], [V.min(3), 'Name must be at least 3 characters.']],
    type:          [[V.required, 'Please select a hazard type.']],
    risk_level:    [[V.required, 'Please select a risk level.']],
    center_lat:    [[V.required, 'Center latitude is required.'], [V.numRange(14.0, 15.0), 'Latitude must be within the service area (14.0–15.0).']],
    center_lng:    [[V.required, 'Center longitude is required.'], [V.numRange(120.5, 121.5), 'Longitude must be within the service area (120.5–121.5).']],
    radius_meters: [[V.intMin(0), 'Radius cannot be negative.']],
  });
  if (!valid) { toast('Please fix the highlighted fields.', 'warning'); return; }
  const r   = await jpost('hazards', id ? 'update' : 'create', new FormData(e.target), id || null);
  if (r.success) {
    toast(`Hazard zone ${id ? 'updated' : 'created'}!`, 'success');
    closeM('hz-modal'); loadHz();
    if (document.getElementById('page-map')?.classList.contains('active')) loadHzLayer();
  } else err.textContent = r.message;
});

async function delHz(id, name) {
  const ok = await showConfirm({ title: 'Remove Hazard Zone', message: `Remove hazard zone "${name}"? It will no longer appear on the map.`, danger: true, confirmLabel: 'Remove' });
  if (!ok) return;
  const r = await jpost('hazards', 'delete', {}, id);
  if (r.success) { toast('Hazard zone removed.', 'success'); loadHz(); }
  else toast(r.message, 'danger');
}

/* ═══════════════════════════════════════════════════════════
   ALERTS
═══════════════════════════════════════════════════════════ */
const ALB = { Critical:'bd', High:'bw', Moderate:'bi2', Advisory:'bs' };
const ASB = { Active:'bd', Expired:'bm', Cancelled:'bm', Draft:'bw' };

async function loadAl() {
  const r  = await jget('alerts');
  const tb = document.getElementById('al-tb');
  if (!r.success) { tb.innerHTML = `<tr class="er"><td colspan="9">${esc(r.message)}</td></tr>`; return; }
  tb.innerHTML = r.data.length ? r.data.map(a => `
    <tr>
      <td><code>${esc(a.alert_code)}</code></td>
      <td><span class="badge ${ALB[a.level] || 'bm'}">${a.level}</span></td>
      <td><i class="bi ${TE[a.type] || 'bi-info-circle-fill'}"></i> ${esc(a.type)}</td>
      <td style="max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(a.title)}">${esc(a.title)}</td>
      <td style="font-size:12px">${esc(a.affected_areas || '—')}</td>
      <td>${esc(a.issuer || '—')}</td>
      <td style="font-size:12px">${a.valid_until ? fmtDT(a.valid_until) : '—'}</td>
      <td><span class="badge ${ASB[a.status] || 'bm'}">${a.status}</span></td>
      <td>${(RID >= 3 && a.status === 'Active') ? `<button class="btn btn-xs" style="background:rgba(239,68,68,.1);color:#f87171;border:none" onclick="cancelAl(${a.id},'${esc(a.alert_code)}')"><i class="bi bi-x-circle"></i> Cancel</button>` : '—'}</td>
    </tr>`).join('') : '<tr class="er"><td colspan="9">No alerts found.</td></tr>';
}

document.getElementById('al-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const err = document.getElementById('al-err');
  err.textContent = '';
  const valid = validateForm(form, {
    level:           [[V.required, 'Please select an alert level.']],
    type:            [[V.required, 'Please select a hazard type.']],
    title:           [[V.required, 'Title is required.'], [V.min(5), 'Title must be at least 5 characters.']],
    affected_areas:  [[V.required, 'Affected areas are required.']],
    message:         [[V.required, 'Message is required.'], [V.min(10), 'Please provide more detail (10+ characters).']],
  });
  const vf = form.valid_from.value, vu = form.valid_until.value;
  if (valid && vf && vu && new Date(vu) <= new Date(vf)) {
    fieldError(form, 'valid_until', '"Valid Until" must be after "Valid From".');
    toast('Please fix the highlighted fields.', 'warning');
    return;
  }
  if (!valid) { toast('Please fix the highlighted fields.', 'warning'); return; }
  const r   = await jpost('alerts', 'create', new FormData(e.target));
  if (r.success) {
    toast(`Alert ${r.data.code} broadcasted!`, 'success');
    closeM('al-modal'); loadAl();
    if (document.getElementById('page-dashboard')?.classList.contains('active')) loadDash();
  } else err.textContent = r.message;
});

async function cancelAl(id, code) {
  const ok = await showConfirm({ title: 'Cancel Alert', message: `Cancel alert ${code}? Residents will no longer see it as active.`, danger: true, confirmLabel: 'Cancel Alert' });
  if (!ok) return;
  const r = await jpost('alerts', 'cancel', {}, id);
  if (r.success) { toast('Alert cancelled.', 'success'); loadAl(); }
  else toast(r.message, 'danger');
}

/* ═══════════════════════════════════════════════════════════
   REPORTS
═══════════════════════════════════════════════════════════ */
async function loadReports() {
  const r = await jget('incidents', { act: 'stats' });
  if (!r.success) return;
  const { bt, bm, bb, bs, bst } = r.data;
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const mm = {}; bm.forEach(x => mm[x.m] = +x.cnt);
  const md  = months.map((m, i) => ({ l: m, v: mm[i+1] || 0 }));
  const max = Math.max(...md.map(m => m.v), 1);
  document.getElementById('mon-ch').innerHTML = md.map(m =>
    `<div class="ci"><div class="cv2">${m.v}</div><div class="cb2" style="height:${(m.v/max*100).toFixed(0)}%"></div><div class="cl2">${m.l}</div></div>`).join('');
  const total = bt.reduce((a, x) => a + +x.cnt, 0) || 1;
  barChart('type-ch', bt.map(x => ({ l: x.type, v: Math.round(x.cnt/total*100) })),
    ['#ef4444','#3b82f6','#eab308','#f97316','#6b7280','#8b5cf6']);
  document.querySelectorAll('#type-ch .bv2').forEach((el, i) => {
    if (bt[i]) el.textContent = Math.round(bt[i].cnt/total*100) + '%';
  });

  // Top affected barangays
  barChart('brgy-ch', (bb || []).map(x => ({ l: x.barangay, v: +x.cnt })),
    ['#f97316','#ef4444','#3b82f6','#eab308','#8b5cf6','#22c55e','#6b7280','#3b82f6']);

  // Severity breakdown
  const SEVC = { Critical:'#ef4444', High:'#f97316', Moderate:'#eab308', Low:'#22c55e' };
  barChart('sev-ch', (bs || []).map(x => ({ l: x.severity, v: +x.cnt })),
    (bs || []).map(x => SEVC[x.severity] || '#6b7280'));

  // Status breakdown
  const STC = { Pending:'#eab308', Active:'#ef4444', Responding:'#f97316', Monitoring:'#3b82f6', Resolved:'#22c55e', Archived:'#6b7280' };
  barChart('stat-ch', (bst || []).map(x => ({ l: x.status, v: +x.cnt })),
    (bst || []).map(x => STC[x.status] || '#6b7280'));

  // Hazard zones: risk level + type breakdown (computed client-side from the live zone list)
  const hr = await jget('hazards');
  if (hr.success) {
    const zones = hr.data;
    const RC2 = { Critical:'#ef4444', High:'#f97316', Moderate:'#eab308', Low:'#22c55e' };
    const riskCounts = {};
    zones.forEach(z => { riskCounts[z.risk_level] = (riskCounts[z.risk_level] || 0) + 1; });
    const riskOrder = ['Critical','High','Moderate','Low'].filter(k => riskCounts[k]);
    barChart('hzrisk-ch', riskOrder.map(k => ({ l: k, v: riskCounts[k] })), riskOrder.map(k => RC2[k]));

    const typeCounts = {};
    zones.forEach(z => { typeCounts[z.type] = (typeCounts[z.type] || 0) + 1; });
    const typeOrder = Object.keys(typeCounts).sort((a, b) => typeCounts[b] - typeCounts[a]);
    const TC2 = { Flood:'#3b82f6', Fire:'#ef4444', Earthquake:'#eab308', Landslide:'#f97316', 'Storm Surge':'#8b5cf6', Tsunami:'#8b5cf6', Volcanic:'#f97316', Other:'#6b7280' };
    barChart('hztype-ch', typeOrder.map(k => ({ l: k, v: typeCounts[k] })), typeOrder.map(k => TC2[k] || '#6b7280'));
  }
}

function exportCSV() {
  window.open(`${API}?mod=incidents&act=export&format=csv`, '_blank');
  toast('CSV export initiated.', 'info');
}

/* ═══════════════════════════════════════════════════════════
   USERS
═══════════════════════════════════════════════════════════ */
const RB = { super_admin:'bd', drrmo_admin:'bw', drrmo_staff:'bi2', barangay_officer:'ba', public:'bm' };
let usrDeb;
function debUsr() { clearTimeout(usrDeb); usrDeb = setTimeout(loadUsers, 350); }

async function loadUsers() {
  const params = {
    search:  document.getElementById('usr-s')?.value || '',
    role_id: document.getElementById('usr-fr')?.value || ''
  };
  const r  = await jget('users', params);
  const tb = document.getElementById('usr-tb');
  if (!r.success) { tb.innerHTML = `<tr class="er"><td colspan="7">${esc(r.message)}</td></tr>`; return; }
  tb.innerHTML = r.data.length ? r.data.map(u => {
    const ini = (u.first_name[0] || '') + (u.last_name[0] || '');
    return `<tr>
      <td><div style="display:flex;align-items:center;gap:8px">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--c-accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">${ini.toUpperCase()}</div>
        <span style="font-weight:500">${esc(u.first_name + ' ' + u.last_name)}</span>
      </div></td>
      <td style="font-size:12px">${esc(u.email)}</td>
      <td>${esc(u.barangay || '—')}</td>
      <td><span class="badge ${RB[u.role_slug] || 'bm'}">${esc(u.role_name)}</span></td>
      <td><span class="badge ${u.status === 'active' ? 'bs' : u.status === 'pending' ? 'bw' : 'bm'}">${u.status}</span></td>
      <td style="font-size:12px">${u.last_login_at ? fmtDT(u.last_login_at) : 'Never'}</td>
      <td><div style="display:flex;gap:4px">
        <button class="btn btn-outline btn-xs" onclick='editUsr(${JSON.stringify(u)})'><i class="bi bi-pencil"></i></button>
        ${RID >= 5 ? `<button class="btn btn-xs" style="background:rgba(239,68,68,.1);color:#f87171;border:none" onclick="delUsr(${u.id},'${esc(u.first_name)}')"><i class="bi bi-trash"></i></button>` : ''}
      </div></td>
    </tr>`;
  }).join('') : '<tr class="er"><td colspan="7">No users found.</td></tr>';
}

const rmap = { public:1, barangay_officer:2, drrmo_staff:3, drrmo_admin:4, super_admin:5 };

function editUsr(u) {
  document.getElementById('usr-mtit').textContent = 'Edit User';
  const f = document.getElementById('usr-form');
  document.getElementById('usr-id').value = u.id;
  f.first_name.value = u.first_name; f.last_name.value = u.last_name;
  f.email.value    = u.email;    document.getElementById('u-email').disabled = true;
  f.username.value = u.username; document.getElementById('u-uname').disabled = true;
  f.role_id.value  = rmap[u.role_slug] || 1;
  f.barangay.value = u.barangay || ''; f.phone.value = u.phone || '';
  document.getElementById('pw-grp').style.display = 'none';
  document.getElementById('u-pw').required = false;
  openM('usr-modal');
}

document.getElementById('usr-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const id  = document.getElementById('usr-id').value;
  const err = document.getElementById('usr-err');
  err.textContent = '';
  const rules = {
    first_name: [[V.required, 'First name is required.']],
    last_name:  [[V.required, 'Last name is required.']],
    role_id:    [[V.required, 'Please select a role.']],
  };
  if (!id) {
    // Creating a new account — email/username/password are editable and required.
    rules.email    = [[V.required, 'Email is required.'], [V.email, 'Enter a valid email address.']];
    rules.username = [[V.required, 'Username is required.'], [V.min(4), 'Username must be at least 4 characters.'], [V.pattern(/^[A-Za-z0-9._-]+$/), 'Only letters, numbers, dots, dashes and underscores allowed.']];
    rules.password = [[V.required, 'Password is required.'], [V.min(8), 'Password must be at least 8 characters.']];
  }
  const valid = validateForm(form, rules);
  if (!valid) { toast('Please fix the highlighted fields.', 'warning'); return; }
  const r   = await jpost('users', id ? 'update' : 'create', new FormData(e.target), id || null);
  if (r.success) {
    toast(`User ${id ? 'updated' : 'created'} successfully!`, 'success');
    document.getElementById('u-email')?.removeAttribute('disabled');
    document.getElementById('u-uname')?.removeAttribute('disabled');
    closeM('usr-modal'); loadUsers();
  } else err.textContent = r.message;
});

async function delUsr(id, name) {
  const ok = await showConfirm({ title: 'Deactivate User', message: `Deactivate account for ${name}? They will no longer be able to sign in.`, danger: true, confirmLabel: 'Deactivate' });
  if (!ok) return;
  const r = await jpost('users', 'delete', {}, id);
  if (r.success) { toast('User deactivated.', 'success'); loadUsers(); }
  else toast(r.message, 'danger');
}

/* ═══════════════════════════════════════════════════════════
   SETTINGS
═══════════════════════════════════════════════════════════ */
async function loadSettings() {
  const r = await jget('settings');
  const c = document.getElementById('set-cont');
  if (!r.success) { c.innerHTML = `<p class="td">${esc(r.message)}</p>`; return; }
  const gt = {
    general:  ['bi-gear ta',         'General Settings'],
    security: ['bi-shield-lock ts',  'Security Settings'],
    map:      ['bi-map ti',          'GIS / Map Settings'],
    backup:   ['bi-database tw2',    'Database & Backup']
  };
  c.innerHTML = Object.entries(r.data).map(([g, settings]) => {
    const [ic, t] = gt[g] || ['bi-gear', g];
    return `<div class="card">
      <div class="ch"><i class="bi ${ic}"></i><h3>${t}</h3></div>
      <div class="cb">
        ${Object.entries(settings).map(([k, s]) => `
          <div class="fg"><label class="form-label">${esc(s.label)}</label>
            <input class="form-control set-inp" data-grp="${g}" data-key="${k}" value="${esc(s.value || '')}">
          </div>`).join('')}
        <button class="btn btn-primary" onclick="saveSettings('${g}')"><i class="bi bi-floppy"></i> Save ${t}</button>
      </div>
    </div>`;
  }).join('');
}

async function saveSettings(g) {
  const inputs = document.querySelectorAll(`.set-inp[data-grp="${g}"]`);
  const fd = new FormData();
  inputs.forEach(i => fd.append(`settings[${i.dataset.key}]`, i.value));
  const r = await jpost('settings', 'save', fd);
  if (r.success) toast('Settings saved successfully.', 'success');
  else toast(r.message, 'danger');
}

/* ═══════════════════════════════════════════════════════════
   ACTIVITY LOGS
═══════════════════════════════════════════════════════════ */
async function loadLogs() {
  const r  = await jget('logs');
  const tb = document.getElementById('log-tb');
  if (!r.success) { tb.innerHTML = `<tr class="er"><td colspan="6">${esc(r.message)}</td></tr>`; return; }
  tb.innerHTML = r.data.items.length ? r.data.items.map(l => `
    <tr>
      <td style="font-size:12px">${fmtDT(l.created_at)}</td>
      <td>${esc(l.username || 'system')}</td>
      <td style="font-size:12px">${esc(l.action)}</td>
      <td><span class="badge bm">${esc(l.module)}</span></td>
      <td style="font-size:12px">${esc(l.description || '')}</td>
      <td style="font-size:11px;color:var(--c-muted)">${esc(l.ip_address || '')}</td>
    </tr>`).join('') : '<tr class="er"><td colspan="6">No logs found.</td></tr>';
}

/* ═══════════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  if (new URLSearchParams(location.search).get('welcome') === '1') {
    toast(`Welcome back, ${window.D.name.split(' ')[0]}! Signed in successfully.`, 'success');
    history.replaceState(null, '', location.pathname);
  }
  loadDash();
  loadNotif();
  initMap();

  // Auto-refresh every 60 seconds when on dashboard
  setInterval(() => {
    if (document.getElementById('page-dashboard')?.classList.contains('active')) loadDash();
  }, 60000);

  // Notifications every 30 seconds
  setInterval(loadNotif, 30000);

  // Map refresh every 2 minutes
  setInterval(() => {
    if (document.getElementById('page-map')?.classList.contains('active')) refreshMap();
  }, 120000);
});
