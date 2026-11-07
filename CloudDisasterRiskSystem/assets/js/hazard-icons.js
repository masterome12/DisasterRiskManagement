// Shared hazard-type catalogue: icon, color, and marker/picker rendering
// used by the risk map (map.js), the quick-report-on-map flow
// (quick-report.js), and the full report form (report.js). Keeping this in
// one file means the map markers and the report pickers can never drift out
// of sync with each other.
const HAZARD_PRIMARY = [
  { key: 'Flooding',   icon: 'bi-water',                 color: '#2563eb' },
  { key: 'Earthquake', icon: 'bi-exclamation-octagon-fill', color: '#92400e' },
  { key: 'Fire',       icon: 'bi-fire',                  color: '#dc2626' },
  { key: 'Accident',   icon: 'bi-car-front-fill',        color: '#7c3aed' },
  { key: 'Crime',      icon: 'bi-shield-exclamation',    color: '#111827' },
];
const HAZARD_MORE = [
  { key: 'Landslide',      icon: 'bi-triangle-fill',              color: '#78350f' },
  { key: 'Storm/Typhoon',  icon: 'bi-cloud-lightning-rain-fill',  color: '#4338ca' },
  { key: 'Road Hazard',    icon: 'bi-cone-striped',               color: '#f97316' },
  { key: 'Other',          icon: 'bi-geo-alt-fill',                color: '#6b7280' },
];
const HAZARD_ALL = [...HAZARD_PRIMARY, ...HAZARD_MORE];

const SEVERITY_RING = { low: '#6c757d', moderate: '#f97316', high: '#dc3545', critical: '#7d0a0a' };

function hazardMeta(type) {
  return HAZARD_ALL.find(h => h.key === type) || HAZARD_MORE[HAZARD_MORE.length - 1];
}

/** Leaflet divIcon for a map marker: hazard-colored disc + icon glyph, ringed by severity color. */
function hazardDivIcon(type, severity, size = 30) {
  const meta = hazardMeta(type);
  const ring = SEVERITY_RING[severity] || '#6c757d';
  return L.divIcon({
    className: '',
    html: `<div class="hazard-marker" style="width:${size}px;height:${size}px;background:${meta.color};box-shadow:0 0 0 3px ${ring};font-size:${Math.round(size * 0.5)}px;">
             <i class="bi ${meta.icon}"></i>
           </div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2],
  });
}

/**
 * Fetches the full barangay list (all 188, North + South Caloocan) and
 * fills a <select> with them grouped into <optgroup>s by district — a flat
 * 188-item list would otherwise be unusable. Shared by the report form,
 * the map's quick-report modal, and the AI forecast barangay filter so
 * they can never show a different/incomplete list from one another.
 * @param {HTMLSelectElement} selectEl
 * @param {string} [placeholder] first, disabled/blank option's label
 */
function populateBarangaySelect(selectEl, placeholder) {
  fetch(window.BASE_URL + '/api/barangays.php').then(r => r.json()).then(data => {
    const groups = {};
    (data.barangays || []).forEach(b => {
      if (!groups[b.district]) groups[b.district] = [];
      groups[b.district].push(b);
    });
    let html = placeholder ? `<option value="">${placeholder}</option>` : '';
    Object.keys(groups).sort().forEach(district => {
      html += `<optgroup label="${district}">` +
        groups[district].map(b => `<option value="${b.id}">${b.name}</option>`).join('') +
        `</optgroup>`;
    });
    selectEl.innerHTML = html;
  }).catch(() => {});
}

/**
 * Renders a tap-friendly icon grid (5 primary hazard types) plus a "More"
 * dropdown for the rest, wiring both to keep a hidden <input> in sync.
 * @param {HTMLElement} gridEl   container for the icon buttons
 * @param {HTMLSelectElement} moreEl  <select> for secondary hazard types
 * @param {HTMLInputElement} hiddenInput  holds the chosen hazard_type value
 * @param {(type:string)=>void} [onChange]
 */
function renderHazardPicker(gridEl, moreEl, hiddenInput, onChange) {
  gridEl.innerHTML = HAZARD_PRIMARY.map(h => `
    <button type="button" class="hazard-pick" data-hazard="${h.key}" style="--hazard-color:${h.color}">
      <i class="bi ${h.icon}"></i>
      <span>${h.key}</span>
    </button>`).join('');

  moreEl.innerHTML = '<option value="">More…</option>' +
    HAZARD_MORE.map(h => `<option value="${h.key}">${h.key}</option>`).join('');

  function select(type) {
    hiddenInput.value = type;
    gridEl.querySelectorAll('.hazard-pick').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.hazard === type);
    });
    moreEl.value = HAZARD_MORE.some(h => h.key === type) ? type : '';
    if (onChange) onChange(type);
  }

  gridEl.querySelectorAll('.hazard-pick').forEach(btn => {
    btn.addEventListener('click', () => select(btn.dataset.hazard));
  });
  moreEl.addEventListener('change', () => { if (moreEl.value) select(moreEl.value); });

  return { select };
}
