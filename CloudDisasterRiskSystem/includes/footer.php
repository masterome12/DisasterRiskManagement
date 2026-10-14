  <footer class="border-top mt-5 py-4 text-center text-muted small">
    <div class="container">
      &copy; <?= date('Y') ?> South Caloocan Disaster &amp; Risk Reduction Management Office &mdash;
      Cloud-Based Disaster and Risk Detection System.
      <div class="mt-1">Unofficial student project &mdash; not affiliated with or endorsed by the City Government of Caloocan.</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <?php
  // Page-specific scripts that depend on the vendor libraries above (Leaflet,
  // Chart.js) must load AFTER them. Pages set $pageScripts before including
  // this file instead of printing their own <script src> tag directly.
  foreach ((array) ($pageScripts ?? []) as $src): ?>
  <script src="<?= asset_url($src) ?>"></script>
  <?php endforeach; ?>
  <script src="<?= asset_url('/assets/js/main.js') ?>"></script>
</body>
</html>
