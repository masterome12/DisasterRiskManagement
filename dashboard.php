<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/helpers.php';
sess_start(); require_auth();
$fn = fname(); $rl = rslug(); $ri = rid();
$ini = strtoupper(mb_substr($fn,0,1).mb_substr(strrchr($fn,' ')?:$fn,1,1));
$rlNames=['super_admin'=>'Super Admin','drrmo_admin'=>'DRRMO Admin','drrmo_staff'=>'DRRMO Staff','barangay_officer'=>'Barangay Officer','public'=>'Public User'];
$rlLabel=$rlNames[$rl]??'User';
$tok=csrf_tok();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – DRRMS South Caloocan</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/drrms/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<script>
window.D={
  api:'/drrms/api/handler.php',
  csrf:<?=json_encode($tok)?>,
  uid:<?=(int)uid()?>,
  rid:<?=(int)$ri?>,
  role:<?=json_encode($rl)?>,
  name:<?=json_encode($fn)?>,
  ini:<?=json_encode($ini)?>,
};
</script>

<div id="app">

<!-- SIDEBAR -->
<nav id="sb">
  <div class="sbb">
    <div class="sb-ic"><i class="bi bi-shield-exclamation"></i></div>
    <div class="sb-tx"><strong>DRRMS</strong><span>South Caloocan</span></div>
  </div>
  <div class="sc2">
    <div class="ns">Monitor</div>
    <a href="#" class="ni active" data-page="dashboard"><i class="bi bi-grid-3x3-gap nic"></i><span class="nl">Dashboard</span></a>
    <a href="#" class="ni" data-page="map"><i class="bi bi-map nic"></i><span class="nl">GIS Map</span></a>
    <a href="#" class="ni" data-page="incidents"><i class="bi bi-exclamation-triangle nic"></i><span class="nl">Incidents</span><span class="nb" id="n-inc">0</span></a>
    <a href="#" class="ni" data-page="alerts"><i class="bi bi-bell nic"></i><span class="nl">Alerts</span><span class="nb" id="n-al">0</span></a>
    <div class="ns">Data</div>
    <a href="#" class="ni" data-page="hazards"><i class="bi bi-geo-alt nic"></i><span class="nl">Hazard Zones</span></a>
    <?php if($ri>=2):?><a href="#" class="ni" data-page="reports"><i class="bi bi-file-earmark-bar-graph nic"></i><span class="nl">Reports</span></a><?php endif;?>
    <?php if($ri>=4):?>
    <div class="ns">Admin</div>
    <a href="#" class="ni" data-page="users"><i class="bi bi-people nic"></i><span class="nl">Users</span></a>
    <a href="#" class="ni" data-page="settings"><i class="bi bi-sliders nic"></i><span class="nl">Settings</span></a>
    <a href="#" class="ni" data-page="logs"><i class="bi bi-clock-history nic"></i><span class="nl">Activity Logs</span></a>
    <?php endif;?>
  </div>
  <div class="sbf">
    <div class="uc" onclick="toggleUMenu()" style="position:relative">
      <div class="uav"><?=hsc($ini)?></div>
      <div class="ui"><strong><?=hsc($fn)?></strong><span><?=hsc($rlLabel)?></span></div>
      <div class="ndd" id="umenu" style="left:0;right:auto;bottom:calc(100% + 8px);top:auto;width:100%">
        <a href="/drrms/logout.php" class="ndi" style="display:flex;align-items:center;gap:8px;color:var(--c-text)"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>
</nav>

<!-- MAIN -->
<div id="main">
  <div id="tb">
    <button class="ib" onclick="toggleSB()"><i class="bi bi-list"></i></button>
    <span class="pt" id="pgtitle">Dashboard</span>
    <div class="tba">
      <button class="ib" onclick="toggleTheme()"><i class="bi bi-moon" id="thico"></i></button>
      <div class="ndw">
        <button class="ib" onclick="toggleNotif()"><i class="bi bi-bell"></i><span class="ndot" id="ndot" style="display:none"></span></button>
        <div class="ndd" id="ndd">
          <div class="nh">Notifications<button class="btn btn-xs btn-outline" onclick="clearNotifs()">Dismiss all</button></div>
          <div id="nlist"><div class="ndi"><p>Loading…</p></div></div>
        </div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="openM('inc-modal')"><i class="bi bi-plus-lg"></i> Report Incident</button>
    </div>
  </div>

  <!-- Ticker -->
  <div id="tkr" class="hidden">
    <span class="tl"><i class="bi bi-broadcast"></i> LIVE ALERTS</span>
    <div class="tt"><div class="ti2" id="tkrtxt"></div></div>
    <button onclick="this.parentElement.classList.add('hidden')" style="background:none;border:none;color:#fff;font-size:16px;cursor:pointer"><i class="bi bi-x"></i></button>
  </div>

  <div id="cnt">

    <!-- DASHBOARD -->
    <div class="page active" id="page-dashboard">
      <div class="sg" id="stat-grid"></div>
      <div class="g2">
        <div class="card"><div class="ch"><i class="bi bi-exclamation-circle td"></i><h3>Recent Incidents</h3><button class="btn btn-outline btn-xs" onclick="nav('incidents')">View All</button></div><div style="padding:8px 0" id="rec-inc"></div></div>
        <div class="card"><div class="ch"><i class="bi bi-broadcast td"></i><h3>Active Alerts</h3><button class="btn btn-outline btn-xs" onclick="nav('alerts')">Manage</button></div><div class="cb" id="dash-alerts"><p class="tm">Loading…</p></div></div>
      </div>
      <div class="g2 mt4">
        <div class="card"><div class="ch"><i class="bi bi-bar-chart ta"></i><h3>Incidents by Type (This Year)</h3></div><div class="cb"><div class="bch" id="ch-type"></div></div></div>
        <div class="card"><div class="ch"><i class="bi bi-pin-map ti"></i><h3>Top Affected Barangays</h3></div><div class="cb"><div class="bch" id="ch-brgy"></div></div></div>
      </div>
      <div class="g31 mt4" style="margin-bottom:20px">
        <div class="card"><div class="ch"><i class="bi bi-activity ts"></i><h3>Recent Activity</h3></div>
          <div class="tw"><table class="dt"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th></tr></thead><tbody id="act-tb"><tr class="er"><td colspan="4">Loading…</td></tr></tbody></table></div>
        </div>
        <div class="card"><div class="ch"><i class="bi bi-pie-chart tw2"></i><h3>Hazard Distribution</h3></div>
          <div class="cb" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="dw"><canvas id="donut" width="120" height="120"></canvas><div class="dc"><strong id="donut-n">0</strong><span>Zones</span></div></div>
            <div class="dleg" id="dleg"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- GIS MAP -->
    <div class="page" id="page-map">
      <div id="maptb">
        <button class="mlt on" id="ml-osm" onclick="setLayer('osm')"><i class="bi bi-map"></i> Street</button>
        <button class="mlt" id="ml-topo" onclick="setLayer('topo')"><i class="bi bi-layers"></i> Topo</button>
        <button class="mlt" id="ml-sat" onclick="setLayer('sat')"><i class="bi bi-globe"></i> Satellite</button>
        <div style="width:1px;height:24px;background:var(--c-border);margin:0 2px"></div>
        <label class="mlt"><input type="checkbox" id="tog-inc" checked onchange="togLay('inc',this.checked)"><i class="bi bi-exclamation-triangle td"></i> Incidents</label>
        <label class="mlt"><input type="checkbox" id="tog-hz" checked onchange="togLay('hz',this.checked)"><i class="bi bi-geo-alt tw2"></i> Hazard Zones</label>
        <label class="mlt"><input type="checkbox" id="tog-ev" checked onchange="togLay('ev',this.checked)"><i class="bi bi-house ts"></i> Evac Centers</label>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <select class="form-select" style="width:140px" id="map-tf" onchange="reloadInc()">
            <option value="">All Types</option>
            <option value="Fire">Fire</option><option value="Flood">Flood</option>
            <option value="Earthquake">Earthquake</option><option value="Landslide">Landslide</option>
          </select>
          <button class="btn btn-outline btn-sm" onclick="refreshMap()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
          <button class="btn btn-primary btn-sm" onclick="openM('inc-modal')"><i class="bi bi-plus-lg"></i> Add Incident</button>
        </div>
      </div>
      <div id="mc">
        <div id="map"></div>
        <div class="msp">
          <div class="mp">
            <div class="mpt">Legend</div>
            <div class="li2"><div class="ls2" style="background:#ef4444"></div> Fire Incident</div>
            <div class="li2"><div class="ls2" style="background:#3b82f6"></div> Flood Incident</div>
            <div class="li2"><div class="ls2" style="background:#eab308"></div> Earthquake</div>
            <div class="li2"><div class="ls2" style="background:rgba(239,68,68,.3);border:2px solid #ef4444"></div> High Risk Zone</div>
            <div class="li2"><div class="ls2" style="background:rgba(234,179,8,.3);border:2px solid #eab308"></div> Moderate Zone</div>
            <div class="li2"><div class="ls2" style="background:#22c55e;border-radius:50%"></div> Evac Center</div>
          </div>
          <div class="mp" style="font-size:11px;color:var(--c-muted)">
            <div class="mpt">Cursor Position</div>
            <div id="m-lat">Lat: –</div><div id="m-lng">Lng: –</div><div id="m-zoom">Zoom: –</div>
          </div>
        </div>
        <div class="mlo" id="mload"><div class="spn"></div></div>
      </div>
    </div>

    <!-- INCIDENTS -->
    <div class="page" id="page-incidents">
      <div class="flex aic g10 mb4 fw">
        <input type="text" class="form-control" placeholder="Search…" style="max-width:260px" id="inc-s" oninput="debInc()">
        <select class="form-select" style="width:130px" id="inc-ft" onchange="loadInc()"><option value="">All Types</option><option>Fire</option><option>Flood</option><option>Earthquake</option><option>Landslide</option><option>Other</option></select>
        <select class="form-select" style="width:140px" id="inc-fs" onchange="loadInc()"><option value="">All Status</option><option>Pending</option><option>Active</option><option>Responding</option><option>Monitoring</option><option>Resolved</option></select>
        <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="openM('inc-modal')"><i class="bi bi-plus-lg"></i> New Incident</button>
      </div>
      <div class="card"><div class="ch"><i class="bi bi-exclamation-triangle td"></i><h3>Incident Reports</h3><span class="badge bd" id="inc-ct" style="margin-left:auto">0 records</span></div>
        <div class="tw"><table class="dt"><thead><tr><th>Code</th><th>Date/Time</th><th>Barangay</th><th>Address</th><th>Reporter</th><th>Type</th><th>Severity</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="inc-tb"><tr class="er"><td colspan="9">Loading…</td></tr></tbody></table></div>
      </div>
    </div>

    <!-- HAZARD ZONES -->
    <div class="page" id="page-hazards">
      <div class="flex aic g10 mb4 fw">
        <h2 style="font-family:var(--fd);font-size:22px;font-weight:800;flex:1">Hazard Zone Management</h2>
        <?php if($ri>=3):?><button class="btn btn-primary btn-sm" onclick="openM('hz-modal')"><i class="bi bi-plus-lg"></i> Add Hazard Zone</button><?php endif;?>
      </div>
      <div id="hz-banner"></div>
      <div id="hz-cards"><p class="tm">Loading…</p></div>
    </div>

    <!-- ALERTS -->
    <div class="page" id="page-alerts">
      <div class="flex aic g10 mb4">
        <h2 style="font-family:var(--fd);font-size:22px;font-weight:800;flex:1">Alert Management</h2>
        <?php if($ri>=3):?><button class="btn btn-primary btn-sm" onclick="openM('al-modal')"><i class="bi bi-plus-lg"></i> Issue Alert</button><?php endif;?>
      </div>
      <div class="card"><div class="ch"><i class="bi bi-broadcast td"></i><h3>All Alerts</h3></div>
        <div class="tw"><table class="dt"><thead><tr><th>Code</th><th>Level</th><th>Type</th><th>Title</th><th>Area Affected</th><th>Issued By</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="al-tb"><tr class="er"><td colspan="9">Loading…</td></tr></tbody></table></div>
      </div>
    </div>

    <!-- REPORTS -->
    <?php if($ri>=2):?>
    <div class="page" id="page-reports">
      <div class="flex aic g10 mb4">
        <h2 style="font-family:var(--fd);font-size:22px;font-weight:800;flex:1">Reports &amp; Analytics</h2>
        <button class="btn btn-outline btn-sm" onclick="exportCSV()"><i class="bi bi-download"></i> Export CSV</button>
      </div>
      <div class="g2 mb4">
        <div class="card"><div class="ch"><i class="bi bi-calendar3 ta"></i><h3>Monthly Incidents (This Year)</h3></div><div class="cb"><div class="cch" id="mon-ch"></div></div></div>
        <div class="card"><div class="ch"><i class="bi bi-bar-chart ti"></i><h3>Incident Type Distribution</h3></div><div class="cb"><div class="bch" id="type-ch"></div></div></div>
      </div>
      <div class="g2 mb4">
        <div class="card"><div class="ch"><i class="bi bi-geo-alt tw2"></i><h3>Top Affected Barangays</h3></div><div class="cb"><div class="bch" id="brgy-ch"></div></div></div>
        <div class="card"><div class="ch"><i class="bi bi-exclamation-triangle td"></i><h3>Incident Severity Breakdown</h3></div><div class="cb"><div class="bch" id="sev-ch"></div></div></div>
      </div>
      <div class="g2 mb4">
        <div class="card"><div class="ch"><i class="bi bi-list-check ts"></i><h3>Incident Status Breakdown</h3></div><div class="cb"><div class="bch" id="stat-ch"></div></div></div>
        <div class="card"><div class="ch"><i class="bi bi-shield-exclamation tw2"></i><h3>Hazard Zones by Risk Level</h3></div><div class="cb"><div class="bch" id="hzrisk-ch"></div></div></div>
      </div>
      <div class="g2 mb4">
        <div class="card"><div class="ch"><i class="bi bi-pin-map ta"></i><h3>Hazard Zones by Type</h3></div><div class="cb"><div class="bch" id="hztype-ch"></div></div></div>
      </div>
    </div>
    <?php endif;?>

    <!-- USERS -->
    <?php if($ri>=4):?>
    <div class="page" id="page-users">
      <div class="flex aic g10 mb4 fw">
        <input type="text" class="form-control" placeholder="Search users…" style="max-width:260px" id="usr-s" oninput="debUsr()">
        <select class="form-select" style="width:150px" id="usr-fr" onchange="loadUsers()"><option value="">All Roles</option><option value="5">Super Admin</option><option value="4">DRRMO Admin</option><option value="3">DRRMO Staff</option><option value="2">Barangay Officer</option><option value="1">Public User</option></select>
        <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="openM('usr-modal')"><i class="bi bi-person-plus"></i> Add User</button>
      </div>
      <div class="card"><div class="ch"><i class="bi bi-people ta"></i><h3>System Users</h3></div>
        <div class="tw"><table class="dt"><thead><tr><th>Name</th><th>Email</th><th>Barangay</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody id="usr-tb"><tr class="er"><td colspan="7">Loading…</td></tr></tbody></table></div>
      </div>
    </div>

    <!-- SETTINGS -->
    <div class="page" id="page-settings">
      <h2 style="font-family:var(--fd);font-size:22px;font-weight:800;margin-bottom:20px">System Settings</h2>
      <div class="g2" id="set-cont"><p class="tm">Loading…</p></div>
    </div>

    <!-- LOGS -->
    <div class="page" id="page-logs">
      <h2 style="font-family:var(--fd);font-size:22px;font-weight:800;margin-bottom:20px">Activity Logs</h2>
      <div class="card"><div class="ch"><i class="bi bi-clock-history ta"></i><h3>Audit Trail</h3></div>
        <div class="tw"><table class="dt"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead>
        <tbody id="log-tb"><tr class="er"><td colspan="6">Loading…</td></tr></tbody></table></div>
      </div>
    </div>
    <?php endif;?>

  </div><!-- /cnt -->

  <div id="stbar">
    <div class="sbi"><span class="sbd on"></span> System Online</div>
    <div class="sbi"><i class="bi bi-database"></i> DB Connected</div>
    <div class="sbi"><i class="bi bi-shield-check"></i> Secure</div>
    <div class="sbi" style="margin-left:auto" id="clk"></div>
  </div>
</div>
</div><!-- /app -->

<!-- ══ MODALS ══ -->
<div class="mo" id="inc-modal">
  <div class="mb2"><div class="mh"><i class="bi bi-exclamation-triangle td"></i><h2>Report Incident</h2><button class="ib" onclick="closeM('inc-modal')"><i class="bi bi-x"></i></button></div>
    <form id="inc-form" enctype="multipart/form-data"><div class="mbody">
      <div class="fr"><div class="fg"><label class="form-label">Type *</label><select class="form-select" name="type" required><option value="">Select…</option><option>Fire</option><option>Flood</option><option>Earthquake</option><option>Landslide</option><option>Typhoon</option><option>Infrastructure</option><option>Other</option></select></div>
      <div class="fg"><label class="form-label">Severity *</label><select class="form-select" name="severity" required><option value="">Select…</option><option>Critical</option><option>High</option><option>Moderate</option><option>Low</option></select></div></div>
      <div class="fg"><label class="form-label">Title *</label><input type="text" class="form-control" name="title" required maxlength="200" placeholder="Brief incident title"></div>
      <div class="fr"><div class="fg"><label class="form-label">Barangay *</label><select class="form-select" name="barangay" required><option value="">Select…</option><option>Brgy. 139</option><option>Brgy. 140</option><option>Brgy. 141</option><option>Brgy. 163</option><option>Brgy. 164</option><option>Brgy. 165</option><option>Dagat-dagatan</option><option>Tullahan</option><option>Bagong Barrio</option><option>Deparo</option></select></div>
      <div class="fg"><label class="form-label">Date &amp; Time *</label><input type="datetime-local" class="form-control" name="incident_datetime" required></div></div>
      <div class="fg"><label class="form-label">Address *</label><input type="text" class="form-control" name="address" required maxlength="255" placeholder="e.g. 123 Rizal Ave."></div>
      <div class="fr"><div class="fg"><label class="form-label">Latitude *</label><input type="number" class="form-control" name="latitude" step="0.0000001" min="-90" max="90" required placeholder="14.7500"></div>
      <div class="fg"><label class="form-label">Longitude *</label><input type="number" class="form-control" name="longitude" step="0.0000001" min="-180" max="180" required placeholder="120.9833"></div></div>
      <button type="button" class="btn btn-outline btn-sm mb4" onclick="getMyLoc()"><i class="bi bi-geo-alt"></i> Use My Location</button>
      <div class="fg"><label class="form-label">Description *</label><textarea class="form-control" name="description" required maxlength="2000" placeholder="Describe the incident…"></textarea></div>
      <div class="fr"><div class="fg"><label class="form-label">Affected Families</label><input type="number" class="form-control" name="affected_families" min="0" value="0"></div>
      <div class="fg"><label class="form-label">Affected Persons</label><input type="number" class="form-control" name="affected_persons" min="0" value="0"></div></div>
      <div class="fg"><label class="form-label">Photo (JPG/PNG/WebP, max 5MB)</label><input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp"></div>
      <div class="fe" id="inc-err"></div>
    </div>
    <div class="mf"><button type="button" class="btn btn-outline" onclick="closeM('inc-modal')">Cancel</button><button type="submit" class="btn btn-primary" id="inc-sub"><i class="bi bi-send"></i> Submit Report</button></div></form>
  </div>
</div>

<?php if($ri>=3):?>
<div class="mo" id="hz-modal">
  <div class="mb2"><div class="mh"><i class="bi bi-geo-alt tw2"></i><h2 id="hz-mtit">Add Hazard Zone</h2><button class="ib" onclick="closeM('hz-modal')"><i class="bi bi-x"></i></button></div>
    <form id="hz-form"><input type="hidden" name="id" id="hz-id"><div class="mbody">
      <div class="fr"><div class="fg"><label class="form-label">Zone Name *</label><input type="text" class="form-control" name="name" required maxlength="150" placeholder="e.g. Tullahan River Flood Zone"></div>
      <div class="fg"><label class="form-label">Type *</label><select class="form-select" name="type" required><option value="">Select…</option><option>Flood</option><option>Fire</option><option>Earthquake</option><option>Landslide</option><option>Storm Surge</option><option>Tsunami</option><option>Volcanic</option><option>Other</option></select></div></div>
      <div class="fr"><div class="fg"><label class="form-label">Risk Level *</label><select class="form-select" name="risk_level" required><option value="">Select…</option><option>Critical</option><option>High</option><option>Moderate</option><option>Low</option></select></div>
      <div class="fg"><label class="form-label">Area (sq. km)</label><input type="number" class="form-control" name="area_sqkm" step="0.0001" min="0" placeholder="0.00"></div></div>
      <div class="fr"><div class="fg"><label class="form-label">Center Latitude *</label><input type="number" class="form-control" name="center_lat" step="0.0000001" required placeholder="14.7500"></div>
      <div class="fg"><label class="form-label">Center Longitude *</label><input type="number" class="form-control" name="center_lng" step="0.0000001" required placeholder="120.9833"></div></div>
      <div class="fr"><div class="fg"><label class="form-label">Radius (meters)</label><input type="number" class="form-control" name="radius_meters" min="0" placeholder="300"></div>
      <div class="fg"><label class="form-label">Zone Color</label><input type="color" class="form-control" name="color" value="#ef4444" style="height:36px;padding:4px"></div></div>
      <div class="fg"><label class="form-label">Affected Barangays</label><input type="text" class="form-control" name="barangays" maxlength="500" placeholder="e.g. Brgy. 139, Brgy. 140"></div>
      <div class="fg"><label class="form-label">Description</label><textarea class="form-control" name="description" maxlength="2000" placeholder="Describe the hazard zone…"></textarea></div>
      <div class="fg"><label class="form-label">Population at Risk</label><input type="number" class="form-control" name="population_risk" min="0" placeholder="0"></div>
      <div class="fe" id="hz-err"></div>
    </div>
    <div class="mf"><button type="button" class="btn btn-outline" onclick="closeM('hz-modal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-floppy"></i> Save Zone</button></div></form>
  </div>
</div>

<div class="mo" id="al-modal">
  <div class="mb2"><div class="mh"><i class="bi bi-broadcast td"></i><h2>Issue Alert</h2><button class="ib" onclick="closeM('al-modal')"><i class="bi bi-x"></i></button></div>
    <form id="al-form"><div class="mbody">
      <div class="fr"><div class="fg"><label class="form-label">Alert Level *</label><select class="form-select" name="level" required><option value="">Select…</option><option value="Critical">Critical</option><option value="High">High</option><option value="Moderate">Moderate</option><option value="Advisory">Advisory</option></select></div>
      <div class="fg"><label class="form-label">Type *</label><select class="form-select" name="type" required><option value="">Select…</option><option>Flood</option><option>Fire</option><option>Typhoon</option><option>Earthquake</option><option>Landslide</option><option>Tsunami</option><option>General</option><option>Chemical</option></select></div></div>
      <div class="fg"><label class="form-label">Title *</label><input type="text" class="form-control" name="title" required maxlength="200" placeholder="e.g. Flood Advisory: Tullahan River"></div>
      <div class="fg"><label class="form-label">Affected Areas *</label><input type="text" class="form-control" name="affected_areas" required maxlength="500" placeholder="e.g. Brgy. 163, 164, 165"></div>
      <div class="fg"><label class="form-label">Source Agency</label><input type="text" class="form-control" name="source_agency" maxlength="100" placeholder="e.g. PAGASA, BFP Caloocan"></div>
      <div class="fg"><label class="form-label">Message *</label><textarea class="form-control" name="message" required maxlength="3000" placeholder="Detailed alert message…"></textarea></div>
      <div class="fr"><div class="fg"><label class="form-label">Valid From</label><input type="datetime-local" class="form-control" name="valid_from"></div>
      <div class="fg"><label class="form-label">Valid Until</label><input type="datetime-local" class="form-control" name="valid_until"></div></div>
      <div class="fe" id="al-err"></div>
    </div>
    <div class="mf"><button type="button" class="btn btn-outline" onclick="closeM('al-modal')">Cancel</button><button type="submit" class="btn btn-danger"><i class="bi bi-broadcast"></i> Broadcast Alert</button></div></form>
  </div>
</div>
<?php endif;?>

<?php if($ri>=4):?>
<div class="mo" id="usr-modal">
  <div class="mb2"><div class="mh"><i class="bi bi-person-plus ta"></i><h2 id="usr-mtit">Add User</h2><button class="ib" onclick="closeM('usr-modal')"><i class="bi bi-x"></i></button></div>
    <form id="usr-form"><input type="hidden" name="id" id="usr-id"><div class="mbody">
      <div class="fr"><div class="fg"><label class="form-label">First Name *</label><input type="text" class="form-control" name="first_name" required maxlength="80"></div>
      <div class="fg"><label class="form-label">Last Name *</label><input type="text" class="form-control" name="last_name" required maxlength="80"></div></div>
      <div class="fg"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" required maxlength="150" id="u-email"></div>
      <div class="fg"><label class="form-label">Username *</label><input type="text" class="form-control" name="username" required minlength="4" maxlength="60" id="u-uname"></div>
      <div class="fr"><div class="fg"><label class="form-label">Role *</label><select class="form-select" name="role_id" required><?php for($i=1;$i<=min(5,$ri);$i++){$rn=['','Public User','Barangay Officer','DRRMO Staff','DRRMO Admin','Super Admin'][$i];echo "<option value=\"$i\">".hsc($rn)."</option>";}?></select></div>
      <div class="fg"><label class="form-label">Barangay</label><select class="form-select" name="barangay"><option value="">N/A</option><option>Brgy. 139</option><option>Brgy. 140</option><option>Brgy. 163</option><option>Dagat-dagatan</option><option>Tullahan</option><option>Bagong Barrio</option></select></div></div>
      <div class="fg"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" maxlength="20" placeholder="+63 9XX XXX XXXX"></div>
      <div class="fg" id="pw-grp"><label class="form-label">Password * (min. 8 chars)</label><input type="password" class="form-control" name="password" minlength="8" id="u-pw"></div>
      <div class="fe" id="usr-err"></div>
    </div>
    <div class="mf"><button type="button" class="btn btn-outline" onclick="closeM('usr-modal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i> Save User</button></div></form>
  </div>
</div>
<?php endif;?>

<div class="mo" id="inc-det-modal">
  <div class="mb2 wide"><div class="mh"><i class="bi bi-info-circle ti"></i><h2>Incident Details</h2><button class="ib" onclick="closeM('inc-det-modal')"><i class="bi bi-x"></i></button></div>
    <div class="mbody" id="inc-det-body"><div style="text-align:center;padding:40px"><div class="spn" style="margin:0 auto"></div></div></div>
  </div>
</div>

<div id="tc"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.min.js"></script>
<script src="/drrms/js/app.js?v=<?= filemtime(__DIR__ . '/js/app.js') ?>"></script>
</body>
</html>
