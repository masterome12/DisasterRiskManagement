// Click-to-report on the risk map: toggle "Report Mode", tap a location,
// tag it with a hazard icon, and submit without leaving the map page.
// Requires map.js to have run first (uses window.riskMap / window.reloadIncidents)
// and hazard-icons.js for the icon picker + marker rendering.
(function () {
  const toggleBtn = document.getElementById('reportModeBtn');
  if (!toggleBtn) return; // Guests / unauthenticated users don't get this control.

  const map = window.riskMap;
  const mapEl = document.getElementById('map');
  const modalEl = document.getElementById('quickReportModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('quickReportForm');
  const hazardInput = document.getElementById('qrHazardType');
  const latInput = document.getElementById('qrLatitude');
  const lngInput = document.getElementById('qrLongitude');
  const pinLabel = document.getElementById('qrPinLabel');
  const errBox = document.getElementById('qrError');
  const okBox = document.getElementById('qrSuccess');

  const picker = renderHazardPicker(
    document.getElementById('qrHazardGrid'),
    document.getElementById('qrHazardMore'),
    hazardInput,
    (type) => { if (tempMarker) tempMarker.setIcon(hazardDivIcon(type, currentSeverity())); }
  );
  function currentSeverity() {
    return document.getElementById('qrSeverity').value || 'low';
  }
  document.getElementById('qrSeverity').addEventListener('change', () => {
    if (tempMarker && hazardInput.value) tempMarker.setIcon(hazardDivIcon(hazardInput.value, currentSeverity()));
  });

  // Barangay dropdown (all 188, grouped North/South Caloocan), populated once.
  populateBarangaySelect(document.getElementById('qrBarangay'), 'Select…');

  let reportMode = false;
  let tempMarker = null;

  function setReportMode(on) {
    reportMode = on;
    toggleBtn.classList.toggle('active', on);
    mapEl.classList.toggle('report-mode-active', on);
  }

  toggleBtn.addEventListener('click', () => setReportMode(!reportMode));

  map.on('click', (e) => {
    if (!reportMode) return;
    const { lat, lng } = e.latlng;
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
    pinLabel.textContent = `Pinned at ${lat.toFixed(5)}, ${lng.toFixed(5)}`;

    const icon = hazardDivIcon(hazardInput.value || 'Other', currentSeverity());
    if (tempMarker) tempMarker.setLatLng(e.latlng).setIcon(icon);
    else tempMarker = L.marker(e.latlng, { icon, opacity: 0.85 }).addTo(map);

    errBox.classList.add('d-none');
    okBox.classList.add('d-none');
    modal.show();
  });

  function resetForm() {
    form.reset();
    picker.select('');
    errBox.classList.add('d-none');
    okBox.classList.add('d-none');
  }

  modalEl.addEventListener('hidden.bs.modal', () => {
    // Leave the pin visible while report mode stays on (user may retry);
    // clear it once they exit report mode or after a successful submit.
  });

  document.getElementById('qrCancelBtn').addEventListener('click', () => {
    if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.classList.add('d-none');
    okBox.classList.add('d-none');

    if (!hazardInput.value) {
      errBox.textContent = 'Please choose a hazard type.';
      errBox.classList.remove('d-none');
      return;
    }

    const submitBtn = form.querySelector('button[type=submit]');
    submitBtn.disabled = true;
    try {
      const res = await fetch(window.BASE_URL + '/api/incidents.php?action=submit', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      if (!res.ok) {
        errBox.textContent = data.error || 'Submission failed.';
        errBox.classList.remove('d-none');
        return;
      }
      okBox.textContent = data.message;
      okBox.classList.remove('d-none');
      if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
      if (window.reloadIncidents) window.reloadIncidents();
      setTimeout(() => { modal.hide(); resetForm(); setReportMode(false); }, 900);
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
