// FR-09 Geotagged Incident Reporting.
(function () {
  const map = L.map('pinMap').setView(window.APP.center, 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
  }).addTo(map);

  const hazardInput = document.getElementById('hazardTypeInput');
  const picker = renderHazardPicker(
    document.getElementById('hazardGrid'),
    document.getElementById('hazardMore'),
    hazardInput,
    (type) => { if (marker) marker.setIcon(hazardDivIcon(type, currentSeverity())); }
  );
  function currentSeverity() {
    return document.querySelector('#reportForm [name=severity]')?.value || 'low';
  }
  document.querySelector('#reportForm [name=severity]').addEventListener('change', () => {
    if (marker && hazardInput.value) marker.setIcon(hazardDivIcon(hazardInput.value, currentSeverity()));
  });

  let marker = null;
  function setPin(lat, lng) {
    const icon = hazardDivIcon(hazardInput.value || 'Other', currentSeverity());
    if (marker) marker.setLatLng([lat, lng]).setIcon(icon);
    else marker = L.marker([lat, lng], { draggable: true, icon }).addTo(map)
      .on('dragend', (e) => setPin(e.target.getLatLng().lat, e.target.getLatLng().lng));
    document.getElementById('latitude').value = lat.toFixed(7);
    document.getElementById('longitude').value = lng.toFixed(7);
    document.getElementById('pinStatus').textContent = `Pinned at ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  }

  map.on('click', (e) => setPin(e.latlng.lat, e.latlng.lng));

  document.getElementById('useGpsBtn').addEventListener('click', () => {
    if (!navigator.geolocation) { alert('Geolocation not supported by this browser.'); return; }
    navigator.geolocation.getCurrentPosition(
      (pos) => { setPin(pos.coords.latitude, pos.coords.longitude); map.setView([pos.coords.latitude, pos.coords.longitude], 16); },
      () => alert('Could not get your location. Please pin manually on the map.')
    );
  });

  // Populate barangay dropdown (all 188, grouped North/South Caloocan).
  populateBarangaySelect(document.getElementById('barangaySelect'), 'Select…');

  const form = document.getElementById('reportForm');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errBox = document.getElementById('reportError');
    const okBox = document.getElementById('reportSuccess');
    errBox.classList.add('d-none'); okBox.classList.add('d-none');

    if (!document.getElementById('latitude').value) {
      errBox.textContent = 'Please pin a location on the map first.';
      errBox.classList.remove('d-none');
      return;
    }
    if (!hazardInput.value) {
      errBox.textContent = 'Please choose a hazard type.';
      errBox.classList.remove('d-none');
      return;
    }

    const res = await fetch(window.BASE_URL + '/api/incidents.php?action=submit', { method: 'POST', body: new FormData(form) });
    const data = await res.json();
    if (!res.ok) { errBox.textContent = data.error || 'Submission failed.'; errBox.classList.remove('d-none'); return; }
    okBox.textContent = data.message;
    okBox.classList.remove('d-none');
    form.reset();
    picker.select('');
    if (marker) { map.removeLayer(marker); marker = null; }
    document.getElementById('pinStatus').textContent = 'No location pinned yet — click the map.';
  });
})();
