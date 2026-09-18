1. Perancangan Basis Data (Database ERD)
Struktur tabel dirancang efisien untuk menangani data master titik pantau (monitoring nodes), pencatatan log telemetri periodik, serta audit riwayat insiden bahaya lengkap dengan bukti gambar kamera.

┌───────────────────────────┐         1:M         ┌───────────────────────────┐
│           users           │ ──────────────────< │      incident_actions     │
├───────────────────────────┤                     ├───────────────────────────┤
│ id (PK)                   │                     │ id (PK)                   │
│ name                      │                     │ incident_id (FK)          │
│ email                     │                     │ user_id (FK)              │
│ role (admin/geotech/op)   │                     │ action_taken (text)       │
│ password                  │                     │ created_at                │
└───────────────────────────┘                     └───────────────────────────┘
              │                                                 │
              │ 1:M (responsible for)                           │ M:1
              ▼                                                 ▼
┌───────────────────────────┐         1:M         ┌───────────────────────────┐
│     monitoring_nodes      │ ──────────────────< │         incidents         │
├───────────────────────────┤                     ├───────────────────────────┤
│ id (PK)                   │                     │ id (PK)                   │
│ node_code (e.g. PIT-01)   │                     │ node_id (FK)              │
│ name                      │                     │ triggered_at              │
│ latitude                  │                     │ trigger_type (Tilt/Vib)   │
│ longitude                 │                     │ severity (NOISE/CRITICAL) │
│ elevation (m)             │                     │ max_tilt_angle (deg)      │
│ status (ACTIVE/ALERT/OFF) │                     │ ai_confidence (%)         │
│ created_at                │                     │ snapshot_path (image URI) │
└───────────────────────────┘                     │ status (OPEN/RESOLVED)    │
              │                                   └───────────────────────────┘
              │ 1:M
              ▼
┌───────────────────────────┐
│      telemetry_logs       │
├───────────────────────────┤
│ id (PK, BigInt)           │
│ node_id (FK)              │
│ recorded_at               │
│ acc_x, acc_y, acc_z       │
│ pitch, roll (degrees)     │
│ vibration_freq (Hz)       │
│ ppv_value (mm/s)          │
│ ai_classification         │
└───────────────────────────┘
Spesifikasi Kamus Data Tabel Kunci
monitoring_nodes: Menyimpan titik koordinat spasial lereng tambang/diorama untuk plotting peta Leaflet.

telemetry_logs: Menyimpan cuplikan metrik pergerakan lereng teragregasi (disimpan berkala misal tiap 5–10 detik oleh ingestion service agar database tidak bloated).

incidents: Tabel utama pencatat trigger bahaya. Menyimpan nilai kemiringan puncak, skor keyakinan AI, serta path file foto kamera bukti longsor.

incident_actions: Log penanganan audit evakuasi oleh operator lapangan.

2. Master Prompt Codex (Laravel Web Platform)
Salin prompt di bawah ini ke Codex untuk membuat arsitektur aplikasi web Laravel:

Markdown
Role: Senior Full-Stack Laravel Architect.
Task: Generate a robust, feature-rich Geotechnical Monitoring & Landslide Early Warning System (EWS) Web Application using Laravel 11, Tailwind CSS, and Alpine.js / Blade.

Core Requirements & Architecture:

1. Database Schema & Migrations:
   - `users`: id, name, email, password, role ('admin', 'geotech_engineer', 'safety_officer').
   - `monitoring_nodes`: id, node_code (string, unique), name, latitude (decimal 10,7), longitude (decimal 10,7), elevation (float), status (enum: 'STABLE', 'WARNING', 'CRITICAL', 'OFFLINE'), timestamps.
   - `telemetry_logs`: id, node_id (foreign key), recorded_at (timestamp), acc_x (float), acc_y (float), acc_z (float), pitch (float), roll (float), vibration_freq (float), ppv_value (float), ai_classification (string), timestamps. Include indexes on (node_id, recorded_at).
   - `incidents`: id, node_id (foreign key), triggered_at (timestamp), trigger_type (string), severity ('WARNING', 'CRITICAL'), max_tilt_angle (float), ai_confidence (float), snapshot_path (string, nullable), status ('UNRESOLVED', 'ACKNOWLEDGED', 'RESOLVED'), timestamps.
   - `incident_actions`: id, incident_id (foreign key), user_id (foreign key), action_notes (text), timestamps.

2. Ingestion REST API (for Python Hardware/AI Daemon):
   - Endpoint `POST /api/v1/telemetry`: Ingests batched telemetry data points from Python daemon with API token authentication.
   - Endpoint `POST /api/v1/incidents`: Accepts incident alerts with multipart/form-data image upload (camera snapshot), creates an `incidents` record, updates the corresponding `monitoring_nodes` status to 'CRITICAL', and stores the snapshot in `storage/app/public/snapshots`.

3. Web Interface (Blade + Tailwind CSS - Industrial Dark Mode Theme):
   - Layout: Clean enterprise dashboard with dark theme palette (slate-900 / zinc-950), high-contrast data cards, and persistent emergency status indicator.
   - Dashboard Overview (`/dashboard`):
     * Node Status Summary Cards (Total Nodes, Stable, Warning, Critical).
     * Interactive Geospatial Pit Map (embedded Leaflet.js): Displaying node pins that pulse red if status is CRITICAL. Clicking a pin opens a popup with real-time stats.
     * Real-Time Telemetry Trend: Visualizing rolling Pitch/Roll tilt and 3-axis acceleration using Chart.js.
     * Latest Critical Incident Alert Banner with snapshot preview.
   - Incident Management View (`/incidents`):
     * Filterable table (by severity, node, and date range).
     * Modal inspection view showing full-resolution camera snapshot, AI confidence score, and incident timeline.
     * Action Log form allowing safety officers to mark incidents as "Acknowledged" or "Evacuated / Resolved".
   - Export Module (`/incidents/export`): Generates and downloads audit CSV report for geotechnical compliance.

Deliverables:
- Complete Laravel migration files with proper constraints and indexes.
- Eloquent Models with relationships defined (`hasMany`, `belongsTo`).
- API Controller (`TelemetryApiController`) with input validation and file storage handling.
- Web Controller (`DashboardController`, `IncidentController`) and clean Blade views styled with Tailwind CSS.