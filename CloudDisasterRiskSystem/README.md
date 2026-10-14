# Cloud-Based Disaster and Risk Detection System — South Caloocan

A web-based platform for collecting, managing, analyzing, and visualizing
geospatial disaster/risk data for South Caloocan, built directly from the
project's SRS and FRS documents.

- **Frontend:** HTML, CSS, vanilla JavaScript, Bootstrap 5 (CSS framework),
  Leaflet.js (map), Leaflet.markercluster, Leaflet.heat, Chart.js.
- **Backend:** Native PHP (no framework), PDO + prepared statements.
- **Database:** MySQL.
- **AI features:**
  - **FR-19 — Geospatial Hotspot / Anomaly Detection**: a from-scratch DBSCAN
    implementation ([`includes/Dbscan.php`](includes/Dbscan.php)) clusters
    approved incident reports by geographic density to surface emerging
    hotspots, rendered as a heatmap + ranked list ([`admin/hotspots.php`](admin/hotspots.php)).
  - **FR-20 — Predictive Risk Modeling (Phase 2)**: a linear-trend forecaster
    ([`includes/Forecast.php`](includes/Forecast.php)) that **always backtests
    itself** against held-out historical months before showing a forecast,
    per NFR-15 — if the backtest doesn't meet the accuracy threshold, the
    forecast is withheld and the UI says so explicitly.

## 1. Requirements

- PHP 8.0+ with `pdo_mysql`, `fileinfo`, and `session` extensions (all
  standard). No Composer packages needed.
- MySQL 5.7+ / 8.0+ (or MariaDB 10.3+).
- Any local server: PHP's built-in server, XAMPP/WAMP, or Apache/Nginx.

## 2. Setup

1. **Create the database.**
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   This creates `disaster_risk_db` with all tables (users, incident_reports,
   geo_layers, barangays, notifications, hotspot_runs, forecast_runs, audit_log)
   and seeds `barangays` with the complete, official list of all 188 Caloocan
   barangays (both North and South Caloocan), each with a real coordinate —
   see `database/seed_caloocan_barangays.php` for the sourcing method. If
   your database predates this (created before this list existed), run that
   script once to upgrade it in place: `php database/seed_caloocan_barangays.php`.
   Likewise, a database created before `users.last_login_at` existed needs
   `php database/migrate_last_login.php` run once (safe to re-run).

2. **Configure credentials.** Edit [`config/config.php`](config/config.php)
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`), or set the equivalent
   environment variables.

3. **Create the admin account.** Visit `install.php` once (or run it via
   CLI: `php install.php`). It prints the default login:
   - username: `admin`
   - password: `Admin@12345`

   **Delete `install.php` immediately afterward** and change the password
   from the app once account-editing is exercised.

4. **(Optional) Seed demo data.** For a populated dashboard/map instead of
   an empty one — and enough data for the AI features (FR-19/FR-20) to
   actually have something to analyze — run:
   ```bash
   php database/seed_demo_data.php
   ```
   This creates 3 demo reporter accounts (`demo_maria`, `demo_pedro`,
   `demo_ana` — password `Demo@12345`) and ~125 incident reports across
   every hazard tag, spread across 8 months so FR-20's forecast has enough
   history to backtest, with a few tight geographic clusters so FR-19's
   hotspot detection has real clusters to find on first run. Safe to
   re-run — it only ever clears/recreates rows attributed to those 3 demo
   accounts, never real user data.

5. **(Optional) Import a historical case dataset.** The "Reported Cases"
   tab on the AI Analytics page (`admin/hotspots.php`) browses a long-run
   historical multi-hazard dataset (independent of live incident reports)
   and predicts next-year case volume per barangay from it. Import one via:
   ```bash
   php database/import_historical_cases.php path/to/dataset.csv
   ```
   Expects columns `Incident_ID, Date, Year, Month, District, Barangay,
   Hazard_Type, Severity, Latitude, Longitude, Affected_Population,
   Casualties, Injuries, Estimated_Damage_PHP, Response_Time_Minutes,
   Weather_Condition, Magnitude, Crime_Category, Fire_Cause` — hazard/severity
   labels are normalized to this app's own tags on import. Idempotent
   (upserts on the CSV's own `Incident_ID`).

6. **Run the app.**
   ```bash
   php -S localhost:8000
   ```
   or point Apache/XAMPP's document root at this folder. Then open
   `http://localhost:8000/index.php`.

## 3. Project Structure

```
CloudDisasterRiskSystem/
├── database/schema.sql        MySQL schema
├── config/                    DB + app configuration
├── includes/                  Session/auth, helpers, AI classes (Dbscan, Forecast), shared UI partials
├── api/                       JSON endpoints (auth, incidents, layers, users, dashboard, search,
│                               hotspots [AI], forecast [AI], reports, notifications, barangays)
├── admin/                     Admin-only pages (moderation, data management, users, AI hotspots)
├── assets/css, assets/js      Frontend
├── uploads/                   Incident photos + uploaded datasets
├── index.php                  Public risk map (FR-03–FR-08, FR-14)
├── login.php / register.php   FR-01
├── report-incident.php        FR-09
├── dashboard.php              FR-13
├── profile.php                Own report history
└── install.php                One-time admin seeder — delete after use
```

## 4. Roles (FR-01 / FR-02)

| Role | Access |
|---|---|
| **Guest** | Public risk map, read-only |
| **Resident / DRRMO** | + submit incident reports, view own history; DRRMO also gets the dashboard and AI hotspot/forecast tools |
| **Administrator** | Full access: moderation, geospatial data (CRUD/upload), user management, all analytics |

Role checks are enforced both in the UI (`includes/auth.php` — `require_role`)
and at the API layer (`api_require_role`), per NFR-05.

The `resident` role's stored value and internal checks are unchanged, but
the UI displays it as **"User"** rather than "Resident" — see
`role_label()` in `includes/functions.php`, used everywhere a role is
shown to a user (badges, account management, the history log).

## 5. Requirements Traceability (selected)

| Feature area | FR(s) | Where |
|---|---|---|
| Login / RBAC | FR-01, FR-02 | `api/auth.php`, `includes/auth.php` |
| Map, base layers, overlays, popups, navigation | FR-03–FR-08 | `index.php`, `assets/js/map.js`, `api/incidents.php`, `api/layers.php` |
| Incident reporting & moderation | FR-09, FR-10 | `report-incident.php`, `admin/moderation.php`, `api/incidents.php` |
| Dataset upload & geodata CRUD | FR-11, FR-12 | `admin/data-management.php`, `api/layers.php` |
| Dashboard | FR-13 | `dashboard.php`, `api/dashboard.php` |
| Search | FR-14 | `assets/js/map.js`, `api/search.php` |
| Report export | FR-15 | `api/reports.php` |
| User management | FR-16 | `admin/users.php`, `api/users.php` |
| Notifications | FR-17 | `assets/js/main.js`, `api/notifications.php` |
| History Log (audit trail) | NFR-05 | `admin/logs.php`, `api/audit_log.php`, `includes/auth.php` (`log_action()`) |
| **AI: Hotspot/anomaly detection** | **FR-19** | `includes/Dbscan.php`, `api/hotspots.php`, `admin/hotspots.php` |
| **AI: Predictive risk forecast (Phase 2)** | **FR-20** | `includes/Forecast.php`, `api/forecast.php`, `admin/hotspots.php` |

## 6. Known Limitations (documented, not silently skipped)

- Shapefile upload is not implemented (needs a binary parser not available
  in a dependency-free native-PHP build); GeoJSON/CSV upload works. The UI
  and API say this explicitly rather than failing silently.
- Real-time third-party feed integration (FR-18) and native mobile apps are
  out of scope per the SRS.
- FR-20's forecast is intentionally simple (linear trend) and clearly
  labeled provisional — see SRS Section 7.2 / NFR-15 for why.
