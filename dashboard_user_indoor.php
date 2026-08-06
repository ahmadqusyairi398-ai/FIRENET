<?php
// Mulai session untuk user (simulasi login)
session_start();

// Jika tipe dashboard adalah outdoor, alihkan ke dashboard_user.php
if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'outdoor') {
    header("Location: dashboard_user.php");
    exit();
}
$_SESSION['dashboard_type'] = 'indoor';

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['username'])) {
    header("Location: login.php?redirect=indoor");
    exit();
}

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// Koneksi Database & Query Data Sensor (Database Indoor)
require_once 'koneksi.php';
$conn = isset($conn_indoor) ? $conn_indoor : null;

$db_locations = [];
if ($conn) {
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_monitoring'");
    if ($checkTable && mysqli_num_rows($checkTable) > 0) {
        $checkNamaLokasiCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_monitoring LIKE 'nama_lokasi'");
        if (!$checkNamaLokasiCol || mysqli_num_rows($checkNamaLokasiCol) == 0) {
            @mysqli_query($conn, "ALTER TABLE lokasi_monitoring ADD COLUMN nama_lokasi VARCHAR(100) DEFAULT NULL AFTER id_alat");
        }
        
        $q_loc = mysqli_query($conn, "SELECT id, id_alat, nama_lokasi, latitude, longitude, updated_at AS last_update FROM lokasi_monitoring ORDER BY id ASC");
        if ($q_loc) {
            while ($r = mysqli_fetch_assoc($q_loc)) {
                $db_locations[] = [
                    'id' => (int)$r['id'],
                    'id_alat' => $r['id_alat'],
                    'nama_lokasi' => $r['nama_lokasi'] ?? '',
                    'latitude' => (float)$r['latitude'],
                    'longitude' => (float)$r['longitude'],
                    'last_update' => $r['last_update']
                ];
            }
        }
    }
}

if (empty($db_locations)) {
    $db_locations = [
        [
            'id' => 1,
            'id_alat' => 'IND-001',
            'nama_lokasi' => 'Gedung Elektro Poltekba',
            'latitude' => -1.202490,
            'longitude' => 116.887080,
            'last_update' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'id_alat' => 'IND-002',
            'nama_lokasi' => 'Ruang Server Gedung Elektro Poltekba',
            'latitude' => -1.203100,
            'longitude' => 116.887500,
            'last_update' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 3,
            'id_alat' => 'IND-003',
            'nama_lokasi' => 'Lab Komputer Lt. 2 Gedung Elektro',
            'latitude' => -1.201800,
            'longitude' => 116.886400,
            'last_update' => date('Y-m-d H:i:s')
        ]
    ];
}

// Tentukan lokasi awal yang difokuskan ke Gedung Elektro Poltekba saat pertama kali dibuka
$primary_loc = null;
foreach ($db_locations as $loc) {
    if (!empty($loc['nama_lokasi']) && (stripos($loc['nama_lokasi'], 'elektro') !== false || stripos($loc['nama_lokasi'], 'poltekba') !== false)) {
        $primary_loc = $loc;
        break;
    }
}
if (!$primary_loc && !empty($db_locations)) {
    $primary_loc = $db_locations[0];
}
if (!$primary_loc) {
    $primary_loc = [
        'id' => 1,
        'id_alat' => 'IND-001',
        'nama_lokasi' => 'Gedung Elektro Poltekba',
        'latitude' => -1.202490,
        'longitude' => 116.887080,
        'last_update' => date('Y-m-d H:i:s')
    ];
}

$latest_sensor = [
    'waktu' => '-',
    'api' => 'Aman',
    'asap' => 'Normal',
    'suhu' => '-',
    'kelembapan' => '-',
    'tegangan' => '-',
    'arus' => '-',
    'rssi' => '-',
    'ip' => '-',
    'status' => 'Offline',
    'isDanger' => false,
    'apiValue' => 0
];

$chart_labels = [];
$chart_suhu = [];
$chart_kelembapan = [];
$chart_tegangan = [];
$chart_arus = [];
$chart_api = [];
$chart_asap = [];

if ($conn) {
    $checkSensorTable = mysqli_query($conn, "SHOW TABLES LIKE 'data_sensor'");
    if ($checkSensorTable && mysqli_num_rows($checkSensorTable) > 0) {
        // Ambil 1 data sensor terbaru
        $q_latest = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
        if ($q_latest && mysqli_num_rows($q_latest) > 0) {
            $s = mysqli_fetch_assoc($q_latest);
            $apiVal = isset($s['api']) ? (float)$s['api'] : 0;
            $raw_asap = isset($s['asap']) ? $s['asap'] : 0;
            
            $apiStatus = ($apiVal > 0.5 || (isset($s['api']) && strtolower($s['api']) === 'terdeteksi api')) ? "Terdeteksi Api" : "Aman";
            
            if (is_numeric($raw_asap)) {
                $f_asap = (float)$raw_asap;
                if ($f_asap > ($f_asap > 1 ? 750 : 0.5)) $asapStatus = "Tinggi";
                else if ($f_asap > ($f_asap > 1 ? 350 : 0.25)) $asapStatus = "Sedang";
                else $asapStatus = "Normal";
            } else {
                $str_asap = trim((string)$raw_asap);
                if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) $asapStatus = "Tinggi";
                else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) $asapStatus = "Sedang";
                else $asapStatus = "Normal";
            }
            
            $waktu_raw = $s['timestamp'] ?? ($s['tanggal_dan_waktu'] ?? 'now');
            $waktu_str = date('H:i:s', strtotime($waktu_raw));
            
            $latest_sensor = [
                'waktu' => $waktu_str,
                'api' => $apiStatus,
                'asap' => $asapStatus,
                'suhu' => isset($s['suhu']) ? number_format((float)$s['suhu'], 1) : "-",
                'kelembapan' => isset($s['kelembapan']) ? number_format((float)$s['kelembapan'], 1) : "-",
                'tegangan' => isset($s['tegangan']) ? number_format((float)$s['tegangan'], 1) : "-",
                'arus' => isset($s['arus']) ? number_format((float)$s['arus'], 3) : "-",
                'rssi' => isset($s['rssi']) ? $s['rssi'] : "-",
                'ip' => !empty($s['ip_address']) ? $s['ip_address'] : "-",
                'status' => 'Online',
                'isDanger' => ($apiStatus === "Terdeteksi Api" || $asapStatus === "Tinggi"),
                'apiValue' => ($apiStatus === "Terdeteksi Api") ? 1 : 0
            ];
        }

        // Ambil 20 data riwayat untuk Grafik Real Time Sensor (Urut terlama ke terbaru)
        $q_chart = mysqli_query($conn, "SELECT * FROM (SELECT * FROM data_sensor ORDER BY id DESC LIMIT 20) Var1 ORDER BY id ASC");
        if ($q_chart && mysqli_num_rows($q_chart) > 0) {
            while ($r = mysqli_fetch_assoc($q_chart)) {
                $waktu_raw = $r['timestamp'] ?? ($r['tanggal_dan_waktu'] ?? 'now');
                $waktu = date('H:i:s', strtotime($waktu_raw));
                $chart_labels[] = $waktu;
                $chart_suhu[] = (float)($r['suhu'] ?? 0);
                $chart_kelembapan[] = (float)($r['kelembapan'] ?? 0);
                $chart_tegangan[] = (float)($r['tegangan'] ?? 0);
                $chart_arus[] = (float)($r['arus'] ?? 0);
                
                $r_asap = $r['asap'] ?? 0;
                if (is_numeric($r_asap)) {
                    $chart_asap[] = (float)$r_asap;
                } else {
                    $str_asap = trim((string)$r_asap);
                    if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) $chart_asap[] = 1;
                    else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) $chart_asap[] = 0.5;
                    else $chart_asap[] = 0;
                }
                
                $api_raw = (float)($r['api'] ?? 0);
                $chart_api[] = ($api_raw > 0.5 || (isset($r['api']) && strtolower($r['api']) === 'terdeteksi api')) ? 1 : 0;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard User - Fire Detection</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ========== STYLE ========== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    background-image: url('https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    position: relative;
}
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: -1;
}
.sidebar {
    width: 250px;
    height: 100vh;
    background: linear-gradient(135deg, rgba(30, 60, 114, 0.9), rgba(42, 82, 152, 0.9));
    padding: 20px 15px;
    position: sticky;
    top: 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    z-index: 1;
}
.sidebar h3 {
    color: white;
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(255,255,255,0.3);
}
.menu-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 10px 0;
    padding: 12px 15px;
    border-radius: 10px;
    background: rgba(255,255,255,0.15);
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    width: 100%;
    font-size: 14px;
}
.menu-btn i { width: 24px; font-size: 18px; }
.menu-btn:hover { background: rgba(255,255,255,0.3); transform: translateX(5px); }
.menu-btn.active { background: linear-gradient(135deg, #00b4db, #0083b0); }
.logout { margin-top: 40px; background: rgba(220, 53, 69, 0.8); }
.logout:hover { background: #dc3545; }
.user-badge {
    background: linear-gradient(135deg, #28a745, #20c997);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    margin-left: auto;
}
.main {
    flex: 1;
    padding: 20px 30px;
    overflow-y: auto;
    height: 100vh;
}

/* ========== HEADER + NODE STATUS GABUNGAN ========== */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 12px 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    flex-wrap: wrap;
    gap: 10px;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}
.header-left h2 {
    color: #1e3c72;
    font-size: 20px;
}
.header-left h2 i {
    color: #e85d04;
}

/* Status Node di dalam Header */
.node-status-header {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(0, 0, 0, 0.05);
    padding: 5px 15px;
    border-radius: 50px;
    flex-wrap: wrap;
}
.status-item-header {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #555;
}
.status-item-header i { font-size: 11px; }
.status-item-header .value {
    font-weight: 600;
    color: #1e3c72;
    font-size: 12px;
}
.status-online { color: #28a745; }
.status-offline { color: #dc3545; }

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #28a745, #20c997);
    padding: 6px 16px;
    border-radius: 50px;
    color: white;
    font-weight: bold;
    font-size: 13px;
}
.user-info i { font-size: 16px; }
.user-tag {
    background: rgba(255,255,255,0.2);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
    margin-left: 5px;
}
.btn-home-header {
    background: rgba(34, 6, 244, 0.15);
    color: #1e3c72;
    border: none;
    padding: 6px 14px;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    transition: all 0.3s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-home-header:hover { background: rgba(34, 6, 244, 0.3); transform: translateY(-2px); }

.card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.card:hover { transform: translateY(-2px); }
.card h3 {
    color: #1e3c72;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}
.card h3 i { color: #00b4db; }

/* ========== GRID SENSOR ========== */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.box {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    color: white;
    transition: transform 0.2s;
    backdrop-filter: blur(5px);
}
.box:hover { transform: scale(1.02); }
.box i { font-size: 32px; margin-bottom: 10px; display: block; }
.box .sensor-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
.box b { display: block; font-size: 20px; margin-top: 5px; }
.box small { display: block; font-size: 11px; opacity: 0.8; margin-top: 2px; }
.box.api-box { background: linear-gradient(135deg, rgba(255, 107, 107, 0.9), rgba(238, 90, 36, 0.9)); }
.box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); }

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); }
}
.pulse-animation { animation: pulse 1s ease-in-out infinite; }

/* ========== MAP ========== */
.map-container {
    margin-top: 10px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(224, 224, 224, 0.5);
}
#map {
    height: 350px;
    width: 100%;
    border-radius: 12px;
    z-index: 1;
}
.location-info {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 15px;
    padding: 15px;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 12px;
    flex-wrap: wrap;
}
.location-info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}
.location-info-item i { font-size: 18px; color: #dc2626; }
.location-info-item .label { color: #555; }
.location-info-item .value { font-weight: 600; color: #1e3c72; }

/* ========== CHART ========== */
.chart-container { margin-top: 10px; }
canvas {
    max-height: 400px;
    width: 100%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    padding: 10px;
}

/* ========== MODAL LOGOUT SEDERHANA ========== */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-box {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border-radius: 20px;
    padding: 40px 35px 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateY(-30px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-icon {
    font-size: 48px;
    color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.modal-box h2 {
    color: #1e3c72;
    font-size: 22px;
    margin-bottom: 25px;
    font-weight: 600;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn-modal {
    padding: 12px 35px;
    border-radius: 50px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-cancel {
    background: #e9ecef;
    color: #495057;
}

.btn-cancel:hover {
    background: #dee2e6;
    transform: translateY(-2px);
}

.btn-logout-confirm {
    background: linear-gradient(135deg, #dc3545, #b91c1c);
    color: white;
}

.btn-logout-confirm:hover {
    background: linear-gradient(135deg, #c82333, #a71d2a);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(220, 53, 69, 0.4);
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .sidebar { width: 80px; padding: 20px 10px; }
    .sidebar h3 { font-size: 12px; }
    .menu-btn span { display: none; }
    .menu-btn i { margin: 0; }
    .main { padding: 15px; }
    .grid { grid-template-columns: repeat(2, 1fr); }
    #map { height: 250px; }
    .location-info { flex-direction: column; align-items: flex-start; gap: 10px; }
    .header { flex-direction: column; align-items: stretch; gap: 10px; }
    .header-left { flex-direction: column; align-items: stretch; }
    .node-status-header { justify-content: center; }
    .header-right { justify-content: center; flex-wrap: wrap; }
    .btn-home-header { padding: 6px 12px; font-size: 12px; }
    .modal-box { padding: 30px 20px; }
    .modal-buttons { flex-direction: column; }
    .btn-modal { justify-content: center; }
}
</style>
</head>
<body>

<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireDetector</h3>
    <a href="dashboard_user_indoor.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
        <span class="user-badge">USER</span>
    </a>
    <a href="chart_indoor.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel_indoor.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
    <!-- Tombol Logout dengan onclick untuk membuka modal -->
    <button class="menu-btn logout" onclick="openLogoutModal()">
        <i class="fas fa-sign-out-alt"></i>
        <span>LOGOUT</span>
    </button>
</div>

<div class="main">
    <!-- ============================================================ -->
    <!-- ========== HEADER + NODE STATUS GABUNGAN ========== -->
    <!-- ============================================================ -->
    <div class="header">
        <div class="header-left">
            <h2><i class="fas fa-building"></i> Dashboard Monitoring Indoor</h2>
            
            <!-- Status Node di dalam Header -->
            <div class="node-status-header">
                <div class="status-item-header">
                    <i class="fas fa-circle" id="status-icon" style="<?= $latest_sensor['status'] === 'Online' ? 'color: #28a745;' : 'color: #dc3545;' ?>"></i>
                    <span>Status:</span>
                    <span class="value" id="status"><?= htmlspecialchars($latest_sensor['status']) ?></span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-signal"></i>
                    <span>RSSI:</span>
                    <span class="value" id="rssi"><?= htmlspecialchars($latest_sensor['rssi']) ?><?= $latest_sensor['rssi'] !== '-' ? ' dBm' : '' ?></span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-network-wired"></i>
                    <span>IP:</span>
                    <span class="value" id="ip"><?= htmlspecialchars($latest_sensor['ip']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="header-right">
            <a href="home.php" class="btn-home-header"><i class="fas fa-home"></i> HOME</a>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($user) ?><span class="user-tag">User</span></span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 2. DATA SENSOR ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3><i class="fas fa-microphone-alt"></i> Data Sensor Real Time (Indoor) <span id="waktu" style="font-size:12px; color:#666;"><i class="far fa-clock"></i> <?= htmlspecialchars($latest_sensor['waktu']) ?></span></h3>
        <div class="grid">
            <div class="box <?= $latest_sensor['api'] === 'Terdeteksi Api' ? 'pulse-animation' : '' ?>" id="api-box" style="<?= $latest_sensor['api'] === 'Terdeteksi Api' ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : '' ?>"><i class="fas fa-fire"></i><div class="sensor-label">Sensor Api</div><b id="api-status"><?= $latest_sensor['api'] === 'Terdeteksi Api' ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman' ?></b></div>
            <div class="box <?= $latest_sensor['asap'] === 'Tinggi' ? 'pulse-animation' : '' ?>" id="asap-box" style="<?= $latest_sensor['asap'] === 'Tinggi' ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : ($latest_sensor['asap'] === 'Sedang' ? 'background: linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95));' : '') ?>"><i class="fas fa-smog"></i><div class="sensor-label">Sensor Asap</div><b id="asap"><?= $latest_sensor['asap'] === 'Tinggi' ? '<i class="fas fa-smog"></i> Tinggi (Berbahaya)' : ($latest_sensor['asap'] === 'Sedang' ? '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)' : '<i class="fas fa-check"></i> Normal') ?></b></div>
            <div class="box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Sensor Suhu</div><b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?><?= $latest_sensor['suhu'] !== '-' ? ' °C' : '' ?> <i class="fas fa-thermometer-half"></i></b></div>
            <div class="box"><i class="fas fa-tint"></i><div class="sensor-label">Sensor Kelembapan</div><b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?><?= $latest_sensor['kelembapan'] !== '-' ? ' %' : '' ?> <i class="fas fa-tint"></i></b></div>
            <div class="box"><i class="fas fa-bolt"></i><div class="sensor-label">Sensor Tegangan</div><b id="tegangan"><?= htmlspecialchars($latest_sensor['tegangan']) ?><?= $latest_sensor['tegangan'] !== '-' ? ' V' : '' ?> <i class="fas fa-bolt"></i></b></div>
            <div class="box"><i class="fas fa-charging-station"></i><div class="sensor-label">Sensor Arus</div><b id="arus"><?= htmlspecialchars($latest_sensor['arus']) ?><?= $latest_sensor['arus'] !== '-' ? ' A' : '' ?> <i class="fas fa-charging-station"></i></b></div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-building" style="color: #0083b0;"></i>
            <span style="color: #1e3c72; font-size: 13px;"><strong>Monitoring Indoor</strong> - Sensor terpasang di dalam gedung untuk deteksi dini kebakaran.</span>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 3. GRAFIK REAL TIME SENSOR ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> Grafik Real Time Sensor</h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 4. MAPS / LOKASI ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; padding: 0; border: none;"><i class="fas fa-map-marker-alt"></i> Lokasi Alat Monitoring</h3>
            <span style="font-size: 12px; background: rgba(0, 180, 219, 0.1); color: #0083b0; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                Total: <span id="total-locations"><?= count($db_locations); ?></span> Titik Lokasi
            </span>
        </div>

        <!-- SEARCH BAR LOKASI TANPA BUTTON -->
        <div class="search-location-wrapper" style="margin-bottom: 15px; position: relative;">
            <div style="display: flex; align-items: center; background: white; border: 1px solid rgba(0,0,0,0.15); border-radius: 25px; padding: 8px 16px; gap: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); transition: all 0.3s;">
                <i class="fas fa-search" style="color: #0083b0; font-size: 15px;"></i>
                <input type="text" id="search-location-input" placeholder="Cari nama lokasi / ID alat..." oninput="filterLocationDropdown()" onfocus="filterLocationDropdown()" autocomplete="off" style="border: none; outline: none; background: transparent; width: 100%; font-size: 13px; color: #333; font-family: inherit;">
                <button type="button" id="clear-search-btn" onclick="clearLocationSearch()" style="display: none; background: none; border: none; color: #999; cursor: pointer; font-size: 14px; padding: 0 4px;" title="Bersihkan pencarian">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <!-- DROPDOWN HASIL PENCARIAN -->
            <div id="search-location-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid rgba(0,0,0,0.15); border-radius: 12px; margin-top: 6px; max-height: 220px; overflow-y: auto; box-shadow: 0 8px 25px rgba(0,0,0,0.15); z-index: 100; backdrop-filter: blur(10px);"></div>
        </div>

        <div class="map-container"><div id="map"></div></div>
        <div class="location-info">
            <div class="location-info-item">
                <i class="fas fa-building"></i>
                <span class="label">Nama Lokasi:</span>
                <span class="value" id="location-name-val"><?= htmlspecialchars($primary_loc['nama_lokasi']) ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-microchip"></i>
                <span class="label">ID Alat:</span>
                <span class="value" id="location-id-val" style="color: #e85d04; font-weight: 700;"><?= htmlspecialchars($primary_loc['id_alat']) ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-globe"></i>
                <span class="label">Koordinat:</span>
                <span class="value" id="coordinates"><?= number_format($primary_loc['latitude'], 6) . ', ' . number_format($primary_loc['longitude'], 6) ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-temperature-high"></i>
                <span class="label">Suhu:</span>
                <span class="value" id="location-suhu-val" style="color: #ff6b6b; font-weight: 700;"><?= htmlspecialchars($latest_sensor['suhu'] ?? '-') ?><?= (isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '' ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-layer-group"></i>
                <span class="label">Zona:</span>
                <span class="value" id="zone">Zona Indoor (Gedung)</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-flag-checkered"></i>
                <span class="label">Status:</span>
                <span class="value" id="location-status" style="color: #28a745; font-weight: bold;">Aman</span>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================ -->
<!-- ========== MODAL LOGOUT SEDERHANA ========== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="logoutModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h2>Apakah Anda yakin keluar?</h2>
        
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="closeLogoutModal()">
                <i class="fas fa-times"></i> CANCEL
            </button>
            <a href="logout.php" class="btn-modal btn-logout-confirm">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
            </a>
        </div>
    </div>
</div>

<script>
// ================= FUNGSI MODAL LOGOUT =================
function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Tutup modal jika klik di luar modal
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});

// Tutup modal dengan tombol ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('logoutModal').style.display === 'flex') {
        closeLogoutModal();
    }
});

// ================= VARIABEL GLOBAL UNTUK PETA =================
var map;
var markers = []; // Array untuk menyimpan semua marker
var dangerZones = []; // Array untuk menyimpan semua circle zone
var defaultLat = <?= (float)$primary_loc['latitude']; ?>;
var defaultLng = <?= (float)$primary_loc['longitude']; ?>;
var currentSuhu = "<?= htmlspecialchars($latest_sensor['suhu'] ?? '-') ?><?= (isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '' ?>";

// ================= INISIALISASI PETA =================
function initMap() {
    // Jika map sudah ada, hapus dulu
    if (map) {
        map.remove();
        markers = [];
        dangerZones = [];
    }
    
    // Inisialisasi peta dengan koordinat default Gedung Elektro Poltekba
    map = L.map('map').setView([defaultLat, defaultLng], 16);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19,
        minZoom: 3
    }).addTo(map);
}
var activeSelectedLocationId = <?= (int)$primary_loc['id']; ?>;
var hasFitBounds = false;

function flyToLocation(lat, lng, nama, idAlat, locId, event) {
    if (locId) activeSelectedLocationId = locId;
    map.flyTo([lat, lng], 17, { duration: 1.5 });
    
    const locNameElem = document.getElementById('location-name-val');
    if (locNameElem) locNameElem.innerText = nama;
    
    const locIdElem = document.getElementById('location-id-val');
    if (locIdElem) locIdElem.innerText = idAlat;

    const coordElem = document.getElementById('coordinates');
    if (coordElem) coordElem.innerHTML = `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;

    markers.forEach(m => {
        const mLatLng = m.getLatLng();
        if (Math.abs(mLatLng.lat - parseFloat(lat)) < 0.0001 && Math.abs(mLatLng.lng - parseFloat(lng)) < 0.0001) {
            m.openPopup();
        }
    });

    document.querySelectorAll('.btn-loc-select').forEach(btn => {
        btn.style.background = 'white';
        btn.style.color = '#333';
        btn.classList.remove('active');
    });
    const activeBtn = (event && event.currentTarget) || (locId ? document.getElementById('btn-loc-' + locId) : null);
    if (activeBtn) {
        activeBtn.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
        activeBtn.style.color = 'white';
        activeBtn.classList.add('active');
    }
}

// ================= FUNGSI TAMBAH MARKER KE PETA =================
function addMarkerToMap(location, isDanger) {
    var safeIcon = L.divIcon({
        html: `<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; flex-direction: column; font-size: 8px; color: white; font-weight: bold;">
                <i class="fas fa-building" style="font-size: 14px;"></i>
                <span style="font-size: 8px; margin-top: 1px;">${location.id_alat || 'Sensor'}</span>
              </div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        popupAnchor: [0, -20],
        className: 'safe-marker'
    });
    
    var dangerIcon = L.divIcon({
        html: `<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; flex-direction: column; animation: blink 1s infinite;">
                <i class="fas fa-exclamation-triangle" style="color: white; font-size: 14px;"></i>
                <span style="font-size: 8px; margin-top: 1px; color: white; font-weight: bold;">${location.id_alat || 'Sensor'}</span>
              </div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        popupAnchor: [0, -20],
        className: 'danger-marker'
    });
    
    var icon = isDanger ? dangerIcon : safeIcon;
    
    var marker = L.marker([location.latitude, location.longitude], { 
        icon: icon, 
        draggable: false 
    }).addTo(map);
    
    var namaLokasi = location.nama_lokasi && location.nama_lokasi.trim() !== '' ? location.nama_lokasi : (location.id_alat ? `Indoor (${location.id_alat})` : 'Lokasi Gedung');
    var statusBadge = isDanger 
        ? '<span style="color: white; background: #dc2626; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-exclamation-triangle"></i> BAHAYA</span>' 
        : '<span style="color: white; background: #28a745; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-check-circle"></i> Aman</span>';

    marker.bindPopup(`
        <div style="font-family: 'Segoe UI', sans-serif; padding: 4px; min-width: 190px;">
            <b style="color: #1e3c72; font-size: 14px; display: block; margin-bottom: 2px;"><i class="fas fa-building" style="color: #00b4db;"></i> ${namaLokasi}</b>
            <small style="color: #666; display: block; margin-bottom: 6px;">ID Alat: <strong>${location.id_alat || '-'}</strong> &nbsp;|&nbsp; <i class="fas fa-temperature-high" style="color:#ff6b6b;"></i> Suhu: <strong class="loc-suhu-val">${currentSuhu}</strong></small>
            <div style="font-size: 12px; color: #444; margin-bottom: 4px;"><i class="fas fa-map-marker-alt" style="color: #dc2626;"></i> <b>Koordinat:</b> ${parseFloat(location.latitude).toFixed(6)}, ${parseFloat(location.longitude).toFixed(6)}</div>
            <div style="font-size: 11px; color: #777; margin-bottom: 6px;"><i class="fas fa-clock"></i> <b>Update:</b> ${location.last_update || '-'}</div>
            <div style="font-size: 12px; margin-top: 6px;"><b>Status:</b> ${statusBadge}</div>
        </div>
    `);
    
    marker.on('click', function() {
        flyToLocation(location.latitude, location.longitude, namaLokasi, location.id_alat || `00${location.id}`, location.id);
    });

    markers.push(marker);
    
    var circleColor = isDanger ? '#dc2626' : '#e85d04';
    var circleOpacity = isDanger ? 0.3 : 0.15;
    
    var zone = L.circle([location.latitude, location.longitude], {
        color: circleColor,
        fillColor: circleColor,
        fillOpacity: circleOpacity,
        radius: 300
    }).addTo(map);
    
    dangerZones.push(zone);
    
    return { marker, zone };
}

// ================= FUNGSI UPDATE SEMUA MARKER =================
function updateAllMarkers(locationsData) {
    currentLocationsData = locationsData;
    markers.forEach(function(marker) {
        map.removeLayer(marker);
    });
    markers = [];
    
    dangerZones.forEach(function(zone) {
        map.removeLayer(zone);
    });
    dangerZones = [];
    
    if (!locationsData || locationsData.length === 0) {
        document.getElementById('total-locations').innerHTML = '0';
        return;
    }
    
    locationsData.forEach(function(location, idx) {
        var isDanger = false;
        addMarkerToMap(location, isDanger);

        if (!activeSelectedLocationId && idx === 0) {
            activeSelectedLocationId = location.id;
        }

        if (activeSelectedLocationId === location.id) {
            const locNameElem = document.getElementById('location-name-val');
            if (locNameElem) locNameElem.innerText = location.nama_lokasi || `Indoor (${location.id_alat})`;
            const locIdElem = document.getElementById('location-id-val');
            if (locIdElem) locIdElem.innerText = location.id_alat || `00${location.id}`;
            const coordElem = document.getElementById('coordinates');
            if (coordElem) coordElem.innerHTML = `${parseFloat(location.latitude).toFixed(6)}, ${parseFloat(location.longitude).toFixed(6)}`;
        }
    });
    
    document.getElementById('total-locations').innerHTML = locationsData.length;
    
    if (activeSelectedLocationId) {
        const selBtn = document.getElementById('btn-loc-' + activeSelectedLocationId);
        if (selBtn) {
            document.querySelectorAll('.btn-loc-select').forEach(btn => {
                btn.style.background = 'white';
                btn.style.color = '#333';
                btn.classList.remove('active');
            });
            selBtn.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
            selBtn.style.color = 'white';
            selBtn.classList.add('active');
        }
        const selectedLoc = locationsData.find(l => l.id === activeSelectedLocationId);
        if (selectedLoc) {
            markers.forEach(m => {
                const mLatLng = m.getLatLng();
                if (Math.abs(mLatLng.lat - parseFloat(selectedLoc.latitude)) < 0.0001 && Math.abs(mLatLng.lng - parseFloat(selectedLoc.longitude)) < 0.0001) {
                    m.openPopup();
                }
            });
        }
    }
    
    if (!hasFitBounds && markers.length > 0) {
        var group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
        hasFitBounds = true;
    }
}

// ================= FUNGSI AMBIL DATA LOKASI =================
async function fetchLocations() {
    try {
        const response = await fetch('get_locations.php');
        const result = await response.json();
        
        if (result.error) {
            console.error('Error:', result.message);
            return [];
        }
        
        return result.data || [];
    } catch (error) {
        console.error('Fetch locations error:', error);
        return [];
    }
}

// ================= CHART (Terhubung ke Database indoor -> data_sensor) =================
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = {
    labels: <?= json_encode($chart_labels); ?>,
    datasets: [
        { label: 'Sensor Api', data: <?= json_encode($chart_api); ?>, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Sensor Asap', data: <?= json_encode($chart_asap); ?>, borderColor: '#ffa502', backgroundColor: 'rgba(255,165,2,0.1)', borderWidth: 2, tension: 0.4, fill: true, borderDash: [5, 5] },
        { label: 'Suhu (°C)', data: <?= json_encode($chart_suhu); ?>, borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kelembapan (%)', data: <?= json_encode($chart_kelembapan); ?>, borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Tegangan (V)', data: <?= json_encode($chart_tegangan); ?>, borderColor: '#ffe66d', backgroundColor: 'rgba(255,230,109,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Arus (A)', data: <?= json_encode($chart_arus); ?>, borderColor: '#a8e6cf', backgroundColor: 'rgba(168,230,207,0.1)', borderWidth: 2, tension: 0.4, fill: true }
    ]
};

const myChart = new Chart(ctx, {
    type: 'line',
    data: dataChart,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: { duration: 500 },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let value = context.raw;
                        let unit = '';
                        if (label.includes('Tegangan')) unit = ' V';
                        else if (label.includes('Arus')) unit = ' A';
                        else if (label.includes('Suhu')) unit = ' °C';
                        else if (label.includes('Kelembapan')) unit = ' %';
                        else if (label.includes('Sensor Asap')) {
                            let status = (value === 1 || value === 'Tinggi') ? '⚠️ Asap Tinggi' : (value === 0.5 || value === 'Sedang' ? '⚡ Asap Sedang' : '✅ Normal');
                            return `${label}: ${status}`;
                        }
                        else if (label.includes('Sensor Api')) {
                            let status = value === 1 ? '🔥 Terdeteksi Api' : '✅ Aman';
                            return `${label}: ${status}`;
                        }
                        return `${label}: ${value}${unit}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                title: { display: true, text: 'Nilai Sensor' }
            },
            x: {
                grid: { display: false },
                title: { display: true, text: 'Waktu' }
            }
        }
    }
});

// ================= AMBIL DATA SENSOR DARI DATABASE =================
async function fetchSensorData() {
    try {
        const response = await fetch('get_sensor_data_indoor.php');
        const data = await response.json();
        
        if (data.error) {
            console.error('Error:', data.message);
            return null;
        }
        
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        return null;
    }
}

// ================= UPDATE DASHBOARD =================
async function updateDashboard() {
    const data = await fetchSensorData();
    
    if (!data) {
        document.getElementById("status").innerHTML = `<i class="fas fa-circle" style="color: #dc3545;"></i> Offline`;
        document.getElementById("status-icon").style.color = "#dc3545";
        document.getElementById("rssi").innerHTML = '-';
        document.getElementById("ip").innerHTML = '-';
        document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> Gagal ambil data`;
        return;
    }

    const statusText = data.status || "Online";
    const isOnline = statusText.includes('Online');
    document.getElementById("status").innerHTML = `<i class="fas fa-circle ${isOnline ? 'status-online' : 'status-offline'}"></i> ${statusText}`;
    document.getElementById("status-icon").style.color = isOnline ? "#28a745" : "#dc3545";
    document.getElementById("rssi").innerHTML = `${data.rssi || '-'} dBm`;
    document.getElementById("ip").innerHTML = data.ip || '-';
    document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${data.waktu || '-'}`;
    
    const apiValue = data.api === "Terdeteksi Api" ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman';
    document.getElementById("api-status").innerHTML = apiValue;
    
    const asapVal = data.asap;
    const asapElem = document.getElementById("asap");
    const asapBox = document.getElementById("asap-box");
    
    if (asapElem && asapBox) {
        if (asapVal === "Tinggi" || asapVal === "Bahaya") {
            asapElem.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tinggi (Berbahaya)';
            asapBox.classList.add('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else if (asapVal === "Sedang" || asapVal === "Waspada") {
            asapElem.innerHTML = '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)';
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95))";
        } else {
            asapElem.innerHTML = '<i class="fas fa-check"></i> Normal';
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "";
        }
    }
    
    document.getElementById("suhu").innerHTML = `${data.suhu} °C <i class="fas fa-thermometer-half"></i>`;
    if (data.suhu !== undefined) {
        currentSuhu = `${data.suhu} °C`;
        document.querySelectorAll('.loc-suhu-val').forEach(el => el.innerHTML = currentSuhu);
    }
    document.getElementById("kelembapan").innerHTML = `${data.kelembapan} % <i class="fas fa-tint"></i>`;
    document.getElementById("tegangan").innerHTML = `${data.tegangan} V <i class="fas fa-bolt"></i>`;
    document.getElementById("arus").innerHTML = `${data.arus} A <i class="fas fa-charging-station"></i>`;
    
    const apiBox = document.getElementById('api-box');
    if (apiBox) {
        if (data.api === "Terdeteksi Api") {
            apiBox.classList.add('pulse-animation');
            apiBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else {
            apiBox.classList.remove('pulse-animation');
            apiBox.style.background = "";
        }
    }
    
    const lastTime = dataChart.labels.length > 0 ? dataChart.labels[dataChart.labels.length - 1] : null;
    if (lastTime !== data.waktu) {
        dataChart.labels.push(data.waktu);
        dataChart.datasets[0].data.push(data.apiValue !== undefined ? data.apiValue : (data.api === "Terdeteksi Api" ? 1 : 0));
        dataChart.datasets[1].data.push(data.asap_value !== undefined ? parseFloat(data.asap_value) : (data.asap === "Tinggi" ? 1 : (data.asap === "Sedang" ? 0.5 : 0)));
        dataChart.datasets[2].data.push(parseFloat(data.suhu) || 0);
        dataChart.datasets[3].data.push(parseFloat(data.kelembapan) || 0);
        dataChart.datasets[4].data.push(parseFloat(data.tegangan) || 0);
        dataChart.datasets[5].data.push(parseFloat(data.arus) || 0);
        
        if (dataChart.labels.length > 20) { 
            dataChart.labels.shift(); 
            dataChart.datasets.forEach(ds => ds.data.shift()); 
        }
        myChart.update();
    }
}

// ================= AMBIL DAN TAMPILKAN LOKASI =================
async function updateLocations() {
    const locations = await fetchLocations();
    updateAllMarkers(locations);
}

// ================= INISIALISASI PERTAMA KALI =================
// 1. Inisialisasi peta kosong terlebih dahulu
initMap();

// 2. Ambil data lokasi dan tampilkan
updateLocations();

// 3. Jalankan update dashboard (sensor data)
updateDashboard();

// 4. Jalankan update setiap 3 detik untuk sensor
setInterval(updateDashboard, 3000);

// ================= FUNGSI SEARCH LOKASI DROPDOWN =================
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function filterLocationDropdown() {
    const input = document.getElementById('search-location-input');
    const resultsContainer = document.getElementById('search-location-results');
    const clearBtn = document.getElementById('clear-search-btn');
    if (!input || !resultsContainer) return;

    const filter = input.value.toLowerCase().trim();
    if (clearBtn) {
        clearBtn.style.display = filter.length > 0 ? 'inline-block' : 'none';
    }

    if (filter.length === 0) {
        resultsContainer.style.display = 'none';
        resultsContainer.innerHTML = '';
        return;
    }

    const locationsToSearch = (currentLocationsData && currentLocationsData.length > 0) ? currentLocationsData : (typeof initialLocations !== 'undefined' ? initialLocations : []);
    const filtered = locationsToSearch.filter(loc => {
        const nama = (loc.nama_lokasi || '').toLowerCase();
        const idAlat = (loc.id_alat || '').toLowerCase();
        return nama.includes(filter) || idAlat.includes(filter);
    });

    resultsContainer.innerHTML = '';
    resultsContainer.style.display = 'block';

    if (filtered.length === 0) {
        resultsContainer.innerHTML = '<div style="padding: 12px; font-size: 13px; color: #888; font-style: italic; text-align: center;"><i class="fas fa-info-circle"></i> Tidak ada lokasi yang cocok</div>';
        return;
    }

    filtered.forEach(loc => {
        const nama = loc.nama_lokasi && loc.nama_lokasi.trim() !== '' ? loc.nama_lokasi : (loc.id_alat ? `Indoor (${loc.id_alat})` : `Lokasi ${loc.id}`);
        const idAlat = loc.id_alat || `IND-${loc.id}`;
        const item = document.createElement('div');
        item.style.cssText = 'padding: 10px 16px; border-bottom: 1px solid rgba(0,0,0,0.05); cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 13px; color: #1e3c72; transition: background 0.2s;';
        item.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-building" style="color: #00b4db;"></i>
                <strong>${escapeHtml(nama)}</strong>
            </div>
            <span style="font-size: 11px; background: rgba(0,180,219,0.1); color: #0083b0; padding: 2px 8px; border-radius: 10px; font-weight: 600;">ID: ${escapeHtml(idAlat)}</span>
        `;
        item.onmouseenter = function() { item.style.background = 'rgba(0,180,219,0.08)'; };
        item.onmouseleave = function() { item.style.background = 'transparent'; };
        item.onclick = function() {
            selectSearchLocation(loc.latitude, loc.longitude, nama, idAlat, loc.id);
        };
        resultsContainer.appendChild(item);
    });
}

function selectSearchLocation(lat, lng, nama, idAlat, locId) {
    flyToLocation(lat, lng, nama, idAlat, locId);
    const input = document.getElementById('search-location-input');
    if (input) input.value = nama;
    const resultsContainer = document.getElementById('search-location-results');
    if (resultsContainer) resultsContainer.style.display = 'none';
}

function clearLocationSearch() {
    const input = document.getElementById('search-location-input');
    if (input) {
        input.value = '';
        filterLocationDropdown();
        input.focus();
    }
}

document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.search-location-wrapper');
    const resultsContainer = document.getElementById('search-location-results');
    if (wrapper && resultsContainer && !wrapper.contains(e.target)) {
        resultsContainer.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('search-location-input');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstItem = document.querySelector('#search-location-results > div');
                if (firstItem) {
                    firstItem.click();
                }
            }
        });
    }
});

// 5. Jalankan update lokasi setiap 10 detik (lebih jarang karena jarang berubah)
setInterval(updateLocations, 10000);
</script>
</body>
</html>