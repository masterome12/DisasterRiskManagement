# DRRMS South Caloocan
## Disaster Risk & Resource Management System
### WAMP / XAMPP Local Development Setup Guide

---

## QUICK START

1. Copy the `drrms` folder to `C:\wamp64\www\` (WAMP) or `C:\xampp\htdocs\` (XAMPP)
2. Open phpMyAdmin → Import → choose `install.sql` → click **Go**
3. Start Apache + MySQL in WAMP/XAMPP
4. Open browser → `http://localhost/drrms`
5. Login: `admin` / `password`

---

## TECH STACK

| Layer      | Technology                                   |
|------------|----------------------------------------------|
| Backend    | Native PHP 8.0+                              |
| Database   | MySQL 8.0+ / MariaDB 10.3+                   |
| Frontend   | HTML5, CSS3, Vanilla JavaScript (ES2020)     |
| GIS Mapping| Leaflet.js 1.9.4 + OpenStreetMap             |
| Clustering | Leaflet.MarkerCluster 1.5.3                  |
| Icons      | Bootstrap Icons 1.11.3                       |
| Fonts      | Google Fonts (Barlow Condensed + Inter)      |
| Server     | Apache 2.4 (WAMP/XAMPP)                     |

> All external libraries load via CDN. Internet connection required.

---

## PROJECT STRUCTURE

```
drrms/                          ← Place inside www/ or htdocs/
├── index.php                   Root redirect
├── login.php                   Login page with background GIS map
├── logout.php                  Session destruction
├── register.php                Public user self-registration
├── dashboard.php               Main SPA (all pages in one file)
├── install.sql                 ← IMPORT THIS into phpMyAdmin first
├── .htaccess                   Apache security + performance rules
│
├── config/
│   ├── db.php                  PDO connection + app constants
│   └── helpers.php             Session, CSRF, RBAC, sanitize, response
│
├── api/
│   └── handler.php             Single API file (mod=X&act=Y routing)
│
├── css/
│   └── style.css               Full design system (dark + light mode)
│
├── js/
│   └── app.js                  SPA logic: Fetch API, Leaflet GIS, CRUD
│
├── includes/
│   └── 403.php                 Access denied error page
│
└── uploads/
    ├── .htaccess               Blocks PHP execution in uploads
    ├── incidents/              Incident photo uploads go here
    └── profiles/               (Reserved for future avatar uploads)
```

---

## STEP-BY-STEP INSTALLATION

### Step 1 — Place files
Copy the entire `drrms` folder into:
- **WAMP:** `C:\wamp64\www\drrms\`
- **XAMPP:** `C:\xampp\htdocs\drrms\`

### Step 2 — Import database
1. Start WAMP/XAMPP (ensure tray icon is **green**)
2. Go to `http://localhost/phpmyadmin`
3. Click **Import** tab at the top
4. Click **Choose File** → select `drrms/install.sql`
5. Click **Go** at the bottom
6. You should see `drrms_caloocan` database with 8 tables created

### Step 3 — Verify database config
Open `config/db.php` and confirm:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // WAMP/XAMPP default
define('DB_PASS', '');       // WAMP default is blank
define('DB_NAME', 'drrms_caloocan');
```
If you set a MySQL root password, update `DB_PASS` accordingly.

### Step 4 — Open in browser
```
http://localhost/drrms
```
You will be redirected to the login page automatically.

---

## DEFAULT LOGIN CREDENTIALS

All demo accounts use password: **`password`**

| Role             | Username      | Email                          |
|------------------|---------------|--------------------------------|
| Super Admin      | `admin`       | admin@drrms.gov.ph             |
| DRRMO Admin      | `m.santos`    | maria.santos@drrmo.gov.ph      |
| DRRMO Staff      | `p.reyes`     | pedro.reyes@drrmo.gov.ph       |
| Barangay Officer | `c.bautista`  | carlos.b@brgy139.gov.ph        |
| Public User      | `ana.cruz`    | ana.cruz@gmail.com             |

> Login works with **either** username or email address.

---

## FEATURES

### Authentication
- Single unified login form (no role tabs — role detected from DB)
- BCrypt password hashing
- Account lockout after 5 failed attempts (15-minute lock)
- CSRF protection on all forms
- Session timeout (30 minutes)

### GIS Map (Leaflet.js + OpenStreetMap)
- **Tile layers:** Street (OSM), Topographic, Satellite (Esri)
- **Incidents layer:** Marker clustering with color-coded pins by type
- **Hazard zones layer:** Circle polygon overlays (generated from center + radius)
- **Evacuation centers layer:** Green map pins with occupancy info
- **Real-time GeoJSON:** All layers fetched live from MySQL
- **Layer toggles:** Show/hide each layer independently
- **Type filter:** Filter incident pins by Fire/Flood/Earthquake/Landslide
- **Popups:** Full details on marker click
- **Coordinate readout:** Live lat/lng + zoom level while panning
- **"Use My Location" button:** Browser geolocation for incident reports

### Dashboard
- Live stat cards (incidents, hazards, alerts, evacuees, users)
- Live alert ticker banner (Critical/High active alerts)
- Recent incidents list
- Active alerts panel
- Bar charts: incidents by type, top barangays
- Monthly incident trend chart
- Hazard distribution donut chart
- Activity log table

### Incident Management
- Submit incident reports with photo upload
- Filter by type, status, search
- View full incident detail modal
- Update incident status (DRRMO Staff+)
- Delete incidents (Admin+)
- Auto-verify if submitted by staff

### Hazard Zone Management
- Create, edit, delete hazard zones
- Zones displayed as circle overlays on GIS map
- Risk level color coding
- Population at risk tracking

### Alert Management
- Issue alerts (DRRMO Staff+)
- Level: Critical / High / Moderate / Advisory
- Auto-expire when valid_until passes
- Cancel active alerts

### User Management (Admin+)
- Full CRUD for user accounts
- Role assignment with privilege escalation prevention
- Status management (active/inactive/suspended)
- Search and filter by role

### Reports & Analytics
- Monthly incident trend bar chart
- Type distribution percentage chart
- CSV export button

### Settings (Admin+)
- General, Security, GIS/Map, Backup settings
- All stored in MySQL `system_settings` table
- Editable from the UI

### Activity Logs (Admin+)
- Full audit trail of all user actions
- IP address tracking

---

## API ENDPOINTS

All API calls go to `/drrms/api/handler.php?mod=X&act=Y`

| Module      | Act         | Method | Description                    |
|-------------|-------------|--------|--------------------------------|
| dashboard   | (default)   | GET    | All dashboard statistics       |
| incidents   | (list)      | GET    | Paginated incident list        |
| incidents   | geojson     | GET    | GeoJSON for Leaflet map        |
| incidents   | stats       | GET    | Charts / analytics data        |
| incidents   | single      | GET    | Single incident detail         |
| incidents   | create      | POST   | Submit new incident            |
| incidents   | update      | POST   | Update status/verify           |
| incidents   | delete      | POST   | Delete incident (Admin)        |
| hazards     | (list)      | GET    | All active hazard zones        |
| hazards     | geojson     | GET    | GeoJSON polygons for map       |
| hazards     | create      | POST   | Create new zone (Staff+)       |
| hazards     | update      | POST   | Edit zone (Staff+)             |
| hazards     | delete      | POST   | Remove zone (Admin+)           |
| evac        | (default)   | GET    | Evacuation centers GeoJSON     |
| alerts      | (list)      | GET    | All alerts                     |
| alerts      | active      | GET    | Active alerts only             |
| alerts      | create      | POST   | Issue new alert (Staff+)       |
| alerts      | cancel      | POST   | Cancel alert (Staff+)          |
| users       | (list)      | GET    | All users (Admin+)             |
| users       | create      | POST   | Create user (Admin+)           |
| users       | update      | POST   | Update user (Admin+)           |
| users       | delete      | POST   | Deactivate user (Super Admin)  |
| settings    | (get)       | GET    | Read all settings (Admin+)     |
| settings    | save        | POST   | Save settings (Admin+)         |
| logs        | (default)   | GET    | Activity logs (Admin+)         |

**JSON Response Format:**
```json
{
  "success": true,
  "message": "Incidents retrieved.",
  "data": { "items": [...], "total": 47 }
}
```

---

## ROLE PERMISSIONS

| Feature                | Public | Brgy. Officer | DRRMO Staff | DRRMO Admin | Super Admin |
|------------------------|:------:|:-------------:|:-----------:|:-----------:|:-----------:|
| View GIS Map           | ✓      | ✓             | ✓           | ✓           | ✓           |
| View verified incidents| ✓      | ✓             | ✓           | ✓           | ✓           |
| Submit incidents       | ✓      | ✓             | ✓           | ✓           | ✓           |
| Update incident status | ✗      | ✓             | ✓           | ✓           | ✓           |
| Delete incidents       | ✗      | ✗             | ✗           | ✓           | ✓           |
| View hazard zones      | ✓      | ✓             | ✓           | ✓           | ✓           |
| Create/edit hazard zones| ✗     | ✗             | ✓           | ✓           | ✓           |
| Issue/cancel alerts    | ✗      | ✗             | ✓           | ✓           | ✓           |
| View reports           | ✗      | ✓             | ✓           | ✓           | ✓           |
| Manage users           | ✗      | ✗             | ✗           | ✓           | ✓           |
| System settings        | ✗      | ✗             | ✗           | ✓           | ✓           |
| Delete users           | ✗      | ✗             | ✗           | ✗           | ✓           |
| Activity logs          | ✗      | ✗             | ✗           | ✓           | ✓           |

---

## SECURITY FEATURES

1. **BCrypt hashing** — `password_hash()` cost 10
2. **CSRF tokens** — Verified on every POST request
3. **PDO prepared statements** — Zero raw SQL, prevents injection
4. **XSS prevention** — `htmlspecialchars()` on all output
5. **Account lockout** — 5 failed attempts → 15-minute lock
6. **File upload validation** — Real MIME type check via `finfo`
7. **Session security** — `session_regenerate_id()` on login
8. **Activity logging** — All actions written to `activity_logs`
9. **Upload folder protection** — `.htaccess` blocks PHP in uploads
10. **Config folder blocked** — `.htaccess` denies direct access

---

## TROUBLESHOOTING

**"Database connection failed"**
→ Ensure WAMP/XAMPP is running (green icon). Verify `config/db.php` credentials.

**Blank white page**
→ Check WAMP error logs. Ensure PHP 8.0+ is selected in WAMP.

**Map not showing**
→ Requires internet for CDN tiles (Leaflet, OpenStreetMap). Check firewall.

**"CSRF token invalid"**
→ Cookies blocked or session expired. Clear browser cookies and retry.

**File upload not working**
→ Check `uploads/incidents/` folder has write permission. Check `php.ini` upload_max_filesize.

**"403 Access Denied" on config files**
→ This is correct — those files are protected by `.htaccess`.

**Apache not starting (port conflict)**
→ Another app uses port 80. In WAMP tray → Apache → "Use a port other than 80".

---

*DRRMS South Caloocan v1.0 — WAMP Edition*
*PHP 8+ · MySQL · Leaflet.js · OpenStreetMap · Vanilla JavaScript*
*Academic Research & Capstone Implementation*
