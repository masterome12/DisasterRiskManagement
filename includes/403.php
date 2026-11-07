<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>403 – Access Denied | DRRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/drrms/css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
<style>
body{display:flex;align-items:center;justify-content:center;height:100vh;text-align:center}
.ec{font-family:var(--fd);font-size:96px;font-weight:800;color:var(--c-danger);line-height:1}
.et{font-size:20px;font-weight:600;margin:12px 0 6px}
.es{color:var(--c-muted);font-size:13px;margin-bottom:24px}
</style>
</head>
<body>
<div>
  <div class="ec">403</div>
  <div class="et"><i class="bi bi-shield-lock"></i> Access Denied</div>
  <div class="es">You do not have permission to access this resource.</div>
  <a href="/drrms/dashboard.php" class="btn btn-primary"><i class="bi bi-house"></i> Back to Dashboard</a>
</div>
</body>
</html>
