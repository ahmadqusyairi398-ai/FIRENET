<?php
// Mulai session untuk user
session_start();

// Jika tipe dashboard adalah indoor, alihkan ke dashboard_user_indoor.php
if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'indoor') {
    header("Location: dashboard_user_indoor.php");
    exit();
}
$_SESSION['dashboard_type'] = 'outdoor';

// Proteksi: Jika belum login, redirect ke halaman login
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// ================= TAMBAHAN KODE DATABASE =================
// 1. Hubungkan ke database
require_once 'koneksi.php';

// Gunakan koneksi outdoor
$conn = isset($conn_outdoor) ? $conn_outdoor : null;

if ($conn) {
    $query_lokasi = mysqli_query($conn, "SELECT latitude, longitude FROM lokasi_alat WHERE id = 1 LIMIT 1");
    if (!$query_lokasi || mysqli_num_rows($query_lokasi) == 0) {
        $query_lokasi = mysqli_query($conn, "SELECT latitude, longitude FROM lokasi_alat ORDER BY id ASC LIMIT 1");
    }
    if ($query_lokasi && mysqli_num_rows($query_lokasi) > 0) {
        $row_lokasi = mysqli_fetch_assoc($query_lokasi);
        $db_lat = (float)$row_lokasi['latitude'];
        $db_lng = (float)$row_lokasi['longitude'];
    }
}

// 3. Ambil data sensor terbaru murni dari tabel data_sensor (database outdoor)
$latest_sensor = [
    'waktu' => '-',
    'tegangan' => '0.0',
    'arus' => '0.00',
    'daya' => '0.0',
    'arah' => 'Utara',
    'angin' => '0.0',
    'asap' => 'Normal',
    'suhu' => '0.0',
    'kelembapan' => '0.0',
    'co' => 0,
    'rssi' => '-',
    'ip' => '-',
    'status' => 'Offline'
];

if ($conn) {
    $q_sensor = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 1");
    if ($q_sensor && mysqli_num_rows($q_sensor) > 0) {
        $s = mysqli_fetch_assoc($q_sensor);
        $asap_val = (isset($s['asap']) && ($s['asap'] === 'Tinggi' || (is_numeric($s['asap']) && (float)$s['asap'] > 0.5))) ? "Tinggi" : "Normal";
        $co_val = isset($s['co']) ? (float)$s['co'] : 0;
        
        $latest_sensor = [
            'waktu' => date('H:i:s', strtotime($s['timestamp'])),
            'tegangan' => isset($s['tegangan']) ? number_format((float)$s['tegangan'], 1) : "0.0",
            'arus' => isset($s['arus']) ? number_format((float)$s['arus'], 2) : "0.0",
            'daya' => isset($s['daya']) ? number_format((float)$s['daya'], 1) : "0.0",
            'arah' => !empty($s['arah_angin']) ? $s['arah_angin'] : "Utara",
            'angin' => isset($s['kecepatan_angin']) ? number_format((float)$s['kecepatan_angin'], 1) : "0.0",
            'asap' => $asap_val,
            'suhu' => isset($s['suhu']) ? number_format((float)$s['suhu'], 1) : "0.0",
            'kelembapan' => isset($s['kelembapan']) ? number_format((float)$s['kelembapan'], 1) : "0.0",
            'co' => $co_val,
            'rssi' => isset($s['rssi']) ? $s['rssi'] : "-",
            'ip' => !empty($s['ip_address']) ? $s['ip_address'] : "-",
            'status' => 'Online'
        ];
    }
}

// 4. Ambil 20 data sensor riwayat terbaru untuk grafik awal dari database outdoor
$chart_labels = [];
$chart_tegangan = [];
$chart_arus = [];
$chart_daya = [];
$chart_suhu = [];
$chart_kelembapan = [];
$chart_angin = [];
$chart_co = [];

if ($conn) {
    $q_chart = mysqli_query($conn, "SELECT * FROM (SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 20) Var1 ORDER BY timestamp ASC");
    if ($q_chart) {
        while ($row = mysqli_fetch_assoc($q_chart)) {
            $chart_labels[] = date('H:i:s', strtotime($row['timestamp']));
            $chart_tegangan[] = (float)($row['tegangan'] ?? 0);
            $chart_arus[] = (float)($row['arus'] ?? 0);
            $chart_daya[] = (float)($row['daya'] ?? 0);
            $chart_suhu[] = (float)($row['suhu'] ?? 0);
            $chart_kelembapan[] = (float)($row['kelembapan'] ?? 0);
            $chart_angin[] = (float)($row['kecepatan_angin'] ?? 0);
            $chart_co[] = (float)($row['co'] ?? 0);
        }
    }
}

// 5. Ambil SEMUA titik lokasi alat dari tabel lokasi_alat (database outdoor)
$all_locations = [];
if ($conn) {
    $q_all_loc = mysqli_query($conn, "SELECT * FROM lokasi_alat ORDER BY id ASC");
    if ($q_all_loc) {
        while ($r_loc = mysqli_fetch_assoc($q_all_loc)) {
            $loc_id = (int)$r_loc['id'];
            $raw_id_alat = isset($r_loc['id_alat']) ? trim($r_loc['id_alat']) : '';
            $raw_nama = isset($r_loc['nama_lokasi']) ? trim($r_loc['nama_lokasi']) : '';
            
            // Format Nama Tempat & ID Alat (seperti di Portofolio: ID: OUT-001)
            if (!empty($raw_nama)) {
                $nama_tempat = $raw_nama;
                $code_alat = !empty($raw_id_alat) ? $raw_id_alat : 'OUT-' . str_pad($loc_id, 3, '0', STR_PAD_LEFT);
            } else if (preg_match('/^OUT-\d+/i', $raw_id_alat)) {
                $code_alat = strtoupper($raw_id_alat);
                $nama_tempat = 'Lokasi ' . $loc_id;
            } else {
                $nama_tempat = !empty($raw_id_alat) ? $raw_id_alat : 'Lokasi ' . $loc_id;
                $code_alat = 'OUT-' . str_pad($loc_id, 3, '0', STR_PAD_LEFT);
            }

            $all_locations[] = [
                'id' => $loc_id,
                'id_alat' => $code_alat,
                'nama_lokasi' => $nama_tempat,
                'lat' => (float)$r_loc['latitude'],
                'lng' => (float)$r_loc['longitude']
            ];
        }
    }
}
// ==========================================================
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
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    background-image: url('https://bpbd.limapuluhkotakab.go.id/assets/img/berita/91kebakaran.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    position: relative;
}
body::before {
    content: '';
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
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
.main { flex: 1; padding: 20px 30px; overflow-y: auto; height: 100vh; }

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
.header-left { display: flex; align-items: center; gap: 15px; }
.header-left h2 { color: #1e3c72; font-size: 20px; }
.header-left h2 i { color: #e85d04; }

.node-status-header {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(0, 0, 0, 0.05);
    padding: 5px 15px;
    border-radius: 50px;
    flex-wrap: wrap;
}
.status-item-header { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #555; }
.status-item-header .value { font-weight: 600; color: #1e3c72; font-size: 12px; }
.status-online { color: #28a745; }

.header-right { display: flex; align-items: center; gap: 12px; }
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
.user-tag { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 20px; font-size: 9px; margin-left: 5px; }
.btn-home-header {
    background: rgba(34, 6, 244, 0.15); color: #1e3c72; border: none; padding: 6px 14px;
    border-radius: 50px; cursor: pointer; font-weight: 600; font-size: 12px; transition: all 0.3s;
    text-decoration: none; display: flex; align-items: center; gap: 6px;
}
.btn-home-header:hover { background: rgba(34, 6, 244, 0.3); transform: translateY(-2px); }

.card {
    background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
    border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.card h3 {
    color: #1e3c72; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid rgba(0,0,0,0.1);
    display: flex; align-items: center; gap: 10px;
}
.card h3 i { color: #00b4db; }

.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.box {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
    padding: 20px; border-radius: 12px; text-align: center; color: white; backdrop-filter: blur(5px);
}
.box i { font-size: 32px; margin-bottom: 10px; display: block; }
.box .sensor-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
.box b { display: block; font-size: 20px; margin-top: 5px; }
.box small { display: block; font-size: 11px; opacity: 0.8; margin-top: 2px; }
.box.solar-box { background: linear-gradient(135deg, rgba(255, 193, 7, 0.9), rgba(255, 107, 0, 0.9)); }
.box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); }
.box.co-box { background: linear-gradient(135deg, rgba(156, 39, 176, 0.9), rgba(103, 58, 183, 0.9)); }
.box.angin-box { background: linear-gradient(135deg, rgba(33, 150, 243, 0.9), rgba(25, 118, 210, 0.9)); }

@keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); } }
.pulse-animation { animation: pulse 1s ease-in-out infinite; }
.status-aman { color: #28a745; font-weight: bold; }
.status-bahaya { color: #dc3545; font-weight: bold; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

/* ========== MAP ========== */
.map-container { margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid rgba(224, 224, 224, 0.5); }
#map { height: 350px; width: 100%; border-radius: 12px; z-index: 1; }
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
    <a href="dashboard_user.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
        <span class="user-badge">USER</span>
    </a>
    <a href="chart.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
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
            <h2><i class="fas fa-fire-extinguisher"></i> Dashboard Monitoring</h2>
            
            <!-- Status Node di dalam Header -->
            <div class="node-status-header">
                <div class="status-item-header">
                    <span>Status:</span>
                    <span class="value" id="status"><i class="fas fa-circle <?= ($latest_sensor['status'] === 'Online') ? 'status-online' : '' ?>"></i> <?= htmlspecialchars($latest_sensor['status']) ?></span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-signal"></i>
                    <span>RSSI:</span>
                    <span class="value" id="rssi"><?= htmlspecialchars($latest_sensor['rssi']) ?> dBm</span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-network-wired"></i>
                    <span>IP:</span>
                    <span class="value" id="ip"><?= htmlspecialchars($latest_sensor['ip']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="header-right">
            <!-- PERBAIKAN: Tombol HOME dengan Modal -->
            <a href="#" class="btn-home-header" onclick="openHomeModal(); return false;"><i class="fas fa-home"></i> HOME</a>
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
        <h3><i class="fas fa-solar-panel"></i> Data Sensor <span id="waktu" style="font-size:12px; color:#666;"><i class="far fa-clock"></i> <?= htmlspecialchars($latest_sensor['waktu']) ?></span></h3>
        <div class="grid">
            <!-- Solar Panel Sensors -->
            <div class="box solar-box"><i class="fas fa-bolt"></i><div class="sensor-label">Tegangan Panel Surya</div><b id="tegangan"><?= htmlspecialchars($latest_sensor['tegangan']) ?> V</b><small>V DC</small></div>
            <div class="box solar-box"><i class="fas fa-charging-station"></i><div class="sensor-label">Arus Panel Surya</div><b id="arus"><?= htmlspecialchars($latest_sensor['arus']) ?> A</b><small>A DC</small></div>
            <div class="box solar-box"><i class="fas fa-solar-panel"></i><div class="sensor-label">Daya Panel Surya</div><b id="daya"><?= htmlspecialchars($latest_sensor['daya']) ?> W</b><small>Watt</small></div>
            
            <!-- Wind Sensors -->
            <div class="box angin-box"><i class="fas fa-compass"></i><div class="sensor-label">Arah Angin</div><b id="arah"><i class="fas fa-arrow-right"></i> <?= htmlspecialchars($latest_sensor['arah']) ?></b></div>
            <div class="box angin-box"><i class="fas fa-wind"></i><div class="sensor-label">Kecepatan Angin</div><b id="kecepatan_angin"><?= htmlspecialchars($latest_sensor['angin']) ?> m/s <i class="fas fa-wind"></i></b></div>
            
            <!-- Asap Sensor -->
            <div class="box asap-box <?= ($latest_sensor['asap'] === 'Tinggi') ? 'pulse-animation' : '' ?>" id="asap-box" style="<?= ($latest_sensor['asap'] === 'Tinggi') ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : '' ?>">
                <i class="fas fa-smog"></i>
                <div class="sensor-label">Asap</div>
                <b id="asap">
                    <?php if ($latest_sensor['asap'] === 'Tinggi'): ?>
                        <i class="fas fa-exclamation-triangle"></i> Tinggi (Berbahaya)
                    <?php else: ?>
                        <i class="fas fa-check"></i> Normal
                    <?php endif; ?>
                </b>
            </div>
            
            <!-- Environment Sensors -->
            <div class="box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Suhu</div><b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?> °C <i class="fas fa-thermometer-half"></i></b></div>
            <div class="box"><i class="fas fa-tint"></i><div class="sensor-label">Kelembapan</div><b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?> % <i class="fas fa-tint"></i></b></div>
            
            <!-- Gas Sensor -->
            <div class="box co-box" id="co-box"><i class="fas fa-industry"></i><div class="sensor-label">Gas CO</div><b id="co"><?= htmlspecialchars($latest_sensor['co']) ?> ppm <i class="fas fa-industry"></i></b></div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-info-circle" style="color: #0083b0;"></i>
            <span style="color: #1e3c72; font-size: 13px;"><strong>Sistem Deteksi Dini Kebakaran</strong> - Sensor terpasang di area rawan kebakaran.</span>
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
    <!-- ========== 4. MAPS / LOKASI (DIPERBAIKI SEPERTI DASHBOARD ADMIN) ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; padding: 0; border: none;"><i class="fas fa-map-marker-alt"></i> Lokasi Alat Monitoring</h3>
            <span style="font-size: 12px; background: rgba(0, 180, 219, 0.1); color: #0083b0; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                Total: <?= count($all_locations) ?> Titik Lokasi
            </span>
        </div>

        <?php if (!empty($all_locations)): ?>
        <div class="location-buttons" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px;">
            <?php foreach ($all_locations as $loc): ?>
            <button type="button" class="btn-loc-select <?= ($loc['id'] == 1) ? 'active' : '' ?>" 
                    onclick="flyToLocation(<?= $loc['lat'] ?>, <?= $loc['lng'] ?>, <?= $loc['id'] ?>)" 
                    style="padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(0,0,0,0.15); background: <?= ($loc['id'] == 1) ? 'linear-gradient(135deg, #00b4db, #0083b0)' : 'white' ?>; color: <?= ($loc['id'] == 1) ? 'white' : '#333' ?>; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 6px;" 
                    id="btn-loc-<?= $loc['id'] ?>">
                <i class="fas fa-location-dot"></i> 
                <span><?= htmlspecialchars($loc['nama_lokasi']) ?></span>
                <span style="opacity: 0.85; font-size: 11px; background: rgba(0,0,0,0.08); padding: 2px 6px; border-radius: 10px;">ID: <?= htmlspecialchars($loc['id_alat']) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="map-container"><div id="map"></div></div>
        <div class="location-info">
            <div class="location-info-item">
                <i class="fas fa-map-pin"></i>
                <span class="label">Nama Tempat:</span>
                <span class="value" id="location-name-val"><?= htmlspecialchars($all_locations[0]['nama_lokasi'] ?? 'Lokasi') ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-microchip"></i>
                <span class="label">ID Alat:</span>
                <span class="value" id="location-id-val" style="color: #e85d04; font-weight: 700;">ID: <?= htmlspecialchars($all_locations[0]['id_alat'] ?? 'OUT-001') ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-globe"></i>
                <span class="label">Koordinat:</span>
                <span class="value" id="coordinates"><?= $db_lat ?>, <?= $db_lng ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-tree"></i>
                <span class="label">Zona:</span>
                <span class="value" id="zone">Zona Monitoring</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-flag-checkered"></i>
                <span class="label">Status:</span>
                <span class="value" id="location-status" style="color: #28a745;">Aman</span>
            </div>
        </div>
    </div>

</div>

<!-- MODAL LOGOUT -->
<div class="modal-overlay" id="logoutModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
        <h2>Apakah Anda yakin keluar?</h2>
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="closeLogoutModal()"><i class="fas fa-times"></i> CANCEL</button>
            <a href="logout.php" class="btn-modal btn-logout-confirm"><i class="fas fa-sign-out-alt"></i> LOGOUT</a>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- ========== MODAL HOME SEDERHANA (TAMBAHAN) ========== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="homeModal">
    <div class="modal-box">
        <div class="modal-icon" style="background: rgba(0, 180, 219, 0.1); color: #00b4db;">
            <i class="fas fa-home"></i>
        </div>
        
        <h2>Kembali ke Halaman Utama?</h2>
        
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="closeHomeModal()">
                <i class="fas fa-times"></i> CANCEL
            </button>
            <a href="home.php" class="btn-modal" style="background: linear-gradient(135deg, #00b4db, #0083b0); color: white;">
                <i class="fas fa-check"></i> YA, KEMBALI
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

// ================= FUNGSI MODAL HOME (TAMBAHAN) =================
function openHomeModal() {
    document.getElementById('homeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeHomeModal() {
    document.getElementById('homeModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Tutup modal Home jika area luar modal diklik
document.getElementById('homeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeHomeModal();
    }
});

// Update fungsi tombol ESC agar bisa menutup modal Home maupun Logout sekaligus
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('logoutModal').style.display === 'flex') {
            closeLogoutModal();
        }
        if (document.getElementById('homeModal').style.display === 'flex') {
            closeHomeModal();
        }
    }
});

// ================= KOORDINAT DINAMIS DARI DATABASE =================
// Memasukkan nilai PHP langsung ke variabel JavaScript
var fixedLat = <?= $db_lat ?? '-1.20249'; ?>;
var fixedLng = <?= $db_lng ?? '116.88708'; ?>;
var allLocations = <?= json_encode($all_locations); ?>;

// Variabel untuk melacak ID lokasi yang sedang aktif dilihat
var activeSelectedLocationId = 1;

// Inisialisasi peta
var map = L.map('map').setView([fixedLat, fixedLng], 14);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

// Icon marker - AMAN (Hijau)
var safeIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #28a745, #20c997); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-check-circle" style="color: white; font-size: 20px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'safe-marker'
});

// Icon marker - BAHAYA (Merah)
var dangerIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; animation: blink 1s infinite;"><i class="fas fa-exclamation-triangle" style="color: white; font-size: 20px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'danger-marker'
});

// Icon marker untuk lokasi titik tambahan lainnya (Biru)
var otherIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-location-dot" style="color: white; font-size: 14px;"></i></div>',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16],
    className: 'other-marker'
});

var locationMarkers = {};
var sensorMarker = null;
var dangerZone = null;

// Render semua titik lokasi dari database outdoor
if (allLocations.length > 0) {
    allLocations.forEach(function(loc) {
        var popupContent = `
            <div style="min-width: 200px; font-family: 'Segoe UI', sans-serif; text-align: center; padding: 4px;">
                <i class="fas fa-map-marker-alt" style="color: #e85d04; font-size: 20px; margin-bottom: 5px;"></i>
                <div style="font-weight: 700; font-size: 14px; color: #1e3c72;">${loc.nama_lokasi}</div>
                <div style="font-size: 12px; color: #e85d04; font-weight: 600; margin-top: 2px;">ID: ${loc.id_alat}</div>
                <div style="font-size: 12px; background: rgba(0,0,0,0.05); padding: 5px 8px; border-radius: 8px; margin-top: 6px; color: #333;">
                    <i class="fas fa-globe"></i> ${loc.lat.toFixed(6)}, ${loc.lng.toFixed(6)}
                </div>
            </div>
        `;

        if (loc.id === 1) {
            // Sensor utama aktif (ID 1)
            sensorMarker = L.marker([loc.lat, loc.lng], { icon: safeIcon, draggable: false }).addTo(map);
            sensorMarker.bindPopup(popupContent).openPopup();
            
            dangerZone = L.circle([loc.lat, loc.lng], {
                color: '#28a745',
                fillColor: '#28a745',
                fillOpacity: 0.1,
                radius: 500
            }).addTo(map);
            
            locationMarkers[loc.id] = sensorMarker;
        } else {
            // Titik lokasi lainnya dari database
            var marker = L.marker([loc.lat, loc.lng], { icon: otherIcon }).addTo(map);
            marker.bindPopup(popupContent);
            locationMarkers[loc.id] = marker;
        }

        locationMarkers[loc.id].on('click', function() {
            flyToLocation(loc.lat, loc.lng, loc.id);
        });
    });
}

// Fallback jika tidak ada marker utama
if (!sensorMarker) {
    sensorMarker = L.marker([fixedLat, fixedLng], { icon: safeIcon, draggable: false }).addTo(map);
    dangerZone = L.circle([fixedLat, fixedLng], { color: '#28a745', fillColor: '#28a745', fillOpacity: 0.1, radius: 500 }).addTo(map);
}

// ================= FUNGSI FLY TO LOCATION =================
function flyToLocation(lat, lng, id) {
    activeSelectedLocationId = id; // Simpan ID lokasi yang sedang diklik user
    map.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
    if (locationMarkers[id]) {
        locationMarkers[id].openPopup();
    }

    var targetLoc = allLocations.find(l => l.id === id);
    if (targetLoc) {
        document.getElementById('location-name-val').innerText = targetLoc.nama_lokasi;
        document.getElementById('location-id-val').innerText = 'ID: ' + targetLoc.id_alat;
        document.getElementById('coordinates').innerText = targetLoc.lat.toFixed(6) + ', ' + targetLoc.lng.toFixed(6);
    }

    document.querySelectorAll('.btn-loc-select').forEach(btn => {
        btn.style.background = 'white';
        btn.style.color = '#333';
    });
    var activeBtn = document.getElementById('btn-loc-' + id);
    if (activeBtn) {
        activeBtn.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
        activeBtn.style.color = 'white';
    }
}

// ================= FUNGSI UPDATE LOCATION STATUS =================
function updateLocationStatus(isDanger, lat, lng) {
    // Ambil data lokasi utama (ID 1) dari array allLocations untuk mendapatkan nama & ID alatnya
    var mainLoc = allLocations.find(l => l.id === 1) || { nama_lokasi: 'Lokasi Utama', id_alat: 'OUT-001' };

    if (isDanger) {
        dangerZone.setStyle({ 
            color: '#dc2626', 
            fillColor: '#dc2626', 
            fillOpacity: 0.3 
        });
        document.getElementById('location-status').innerHTML = '⚠️ BAHAYA - Deteksi Kebakaran!';
        document.getElementById('location-status').style.color = '#dc2626';
        document.getElementById('zone').innerHTML = 'Zona Merah (Peringatan Bahaya)';
        
        sensorMarker.setIcon(dangerIcon);
        
        // Format popup bahaya (Nama Tempat, ID, dan Koordinat)
        sensorMarker.bindPopup(`
            <div style="min-width: 200px; font-family: 'Segoe UI', sans-serif; text-align: center; padding: 4px;">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626; font-size: 20px; margin-bottom: 5px;"></i>
                <div style="font-weight: 700; font-size: 14px; color: #dc2626;">${mainLoc.nama_lokasi}</div>
                <div style="font-size: 12px; color: #dc2626; font-weight: 600; margin-top: 2px;">ID: ${mainLoc.id_alat} (BAHAYA!)</div>
                <div style="font-size: 12px; background: rgba(220,38,38,0.1); padding: 5px 8px; border-radius: 8px; margin-top: 6px; color: #333;">
                    <i class="fas fa-globe"></i> ${lat}, ${lng}
                </div>
            </div>
        `);
        
        if (activeSelectedLocationId === 1) {
            sensorMarker.openPopup();
        }
    } else {
        dangerZone.setStyle({ 
            color: '#28a745', 
            fillColor: '#28a745', 
            fillOpacity: 0.1 
        });
        document.getElementById('location-status').innerHTML = 'Aman';
        document.getElementById('location-status').style.color = '#28a745';
        document.getElementById('zone').innerHTML = 'Zona Hijau (Aman)';
        
        sensorMarker.setIcon(safeIcon);
        
        // Format popup normal (aman)
        sensorMarker.bindPopup(`
            <div style="min-width: 200px; font-family: 'Segoe UI', sans-serif; text-align: center; padding: 4px;">
                <i class="fas fa-map-marker-alt" style="color: #e85d04; font-size: 20px; margin-bottom: 5px;"></i>
                <div style="font-weight: 700; font-size: 14px; color: #1e3c72;">${mainLoc.nama_lokasi}</div>
                <div style="font-size: 12px; color: #e85d04; font-weight: 600; margin-top: 2px;">ID: ${mainLoc.id_alat}</div>
                <div style="font-size: 12px; background: rgba(0,0,0,0.05); padding: 5px 8px; border-radius: 8px; margin-top: 6px; color: #333;">
                    <i class="fas fa-globe"></i> ${lat}, ${lng}
                </div>
            </div>
        `);
        
        if (activeSelectedLocationId === 1) {
            sensorMarker.openPopup();
        }
    }
}

// ================= CHART =================
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = { 
    labels: <?= json_encode($chart_labels) ?>, 
    datasets: [
        { label: 'Tegangan Panel Surya (V)', data: <?= json_encode($chart_tegangan) ?>, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Arus Panel Surya (A)', data: <?= json_encode($chart_arus) ?>, borderColor: '#ff8c00', backgroundColor: 'rgba(255,140,0,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Daya Panel Surya (W)', data: <?= json_encode($chart_daya) ?>, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Suhu (°C)', data: <?= json_encode($chart_suhu) ?>, borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kelembapan (%)', data: <?= json_encode($chart_kelembapan) ?>, borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kecepatan Angin (m/s)', data: <?= json_encode($chart_angin) ?>, borderColor: '#3399ff', backgroundColor: 'rgba(51,153,255,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'CO (ppm)', data: <?= json_encode($chart_co) ?>, borderColor: '#aa96da', backgroundColor: 'rgba(170,150,218,0.1)', borderWidth: 2, tension: 0.4, fill: true }
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
                        else if (label.includes('Daya')) unit = ' W';
                        else if (label.includes('Suhu')) unit = ' °C';
                        else if (label.includes('Kelembapan')) unit = ' %';
                        else if (label.includes('Angin')) unit = ' m/s';
                        else if (label.includes('CO')) unit = ' ppm';
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

// ================= FUNGSI FETCH DATA DARI DATABASE (DIPERBAIKI) =================
function fetchDataFromDB() {
    fetch('get_latest_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            // 1. Update status header
            document.getElementById("status").innerHTML = `<i class="fas fa-circle status-online"></i> ${data.status || 'Online'}`;
            document.getElementById("rssi").innerHTML = `${data.rssi || '-'} dBm`;
            document.getElementById("ip").innerHTML = data.ip || '-';
            document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${data.waktu || '-'}`;

            // 2. Update sensor panel surya
            document.getElementById("tegangan").innerHTML = `${data.tegangan || 0} V`;
            document.getElementById("arus").innerHTML = `${data.arus || 0} A`;
            document.getElementById("daya").innerHTML = `${data.daya || 0} W`;

            // 3. Update angin
            var arahIcon = {
                'Utara': 'up',
                'Selatan': 'down',
                'Timur': 'right',
                'Barat': 'left',
                'Timur Laut': 'up-right',
                'Barat Daya': 'down-left',
                'Tenggara': 'down-right',
                'Barat Laut': 'up-left'
            };
            var arahValue = data.arah || 'Timur';
            document.getElementById("arah").innerHTML = `<i class="fas fa-arrow-${arahIcon[arahValue] || 'right'}"></i> ${arahValue}`;
            document.getElementById("kecepatan_angin").innerHTML = `${data.angin || 0} m/s <i class="fas fa-wind"></i>`;

            // 4. Update status Asap
            var asapElement = document.getElementById("asap");
            var asapBox = document.getElementById('asap-box');
            if (data.asap === "Tinggi") {
                asapElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tinggi (Berbahaya)';
                asapElement.className = 'status-bahaya';
                asapBox.classList.add('pulse-animation');
                asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
            } else {
                asapElement.innerHTML = '<i class="fas fa-check"></i> Normal';
                asapElement.className = 'status-aman';
                asapBox.classList.remove('pulse-animation');
                asapBox.style.background = "linear-gradient(135deg, rgba(255,165,2,0.9), rgba(255,99,72,0.9))";
            }

            // 5. Update CO, Suhu, Kelembapan
            var coElement = document.getElementById("co");
            var coBox = document.getElementById('co-box');
            var coValue = parseFloat(data.co) || 0;
            if (coValue > 50) {
                coElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${coValue} ppm (BAHAYA!)`;
                coElement.className = 'status-bahaya';
                coBox.classList.add('pulse-animation');
                coBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
            } else if (coValue > 35) {
                coElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${coValue} ppm (Waspada)`;
                coElement.className = 'status-bahaya';
                coBox.classList.remove('pulse-animation');
                coBox.style.background = "linear-gradient(135deg, rgba(255,165,2,0.9), rgba(255,99,72,0.9))";
            } else {
                coElement.innerHTML = `${coValue} ppm <i class="fas fa-industry"></i>`;
                coElement.className = 'status-aman';
                coBox.classList.remove('pulse-animation');
                coBox.style.background = "linear-gradient(135deg, rgba(156,39,176,0.9), rgba(103,58,183,0.9))";
            }

            document.getElementById("suhu").innerHTML = `${data.suhu || 0} °C <i class="fas fa-thermometer-half"></i>`;
            document.getElementById("kelembapan").innerHTML = `${data.kelembapan || 0} % <i class="fas fa-tint"></i>`;

            // ================= 6. Update Peta & Koordinat dari Database =================
            if(data.lat && data.lng) {
                // Update posisi marker dan danger zone (tetap berjalan di background)
                sensorMarker.setLatLng([data.lat, data.lng]);
                dangerZone.setLatLng([data.lat, data.lng]);
                
                // HANYA update teks koordinat dan geser peta jika user sedang melihat Lokasi Utama (ID 1)
                if (activeSelectedLocationId === 1) {
                    document.getElementById('coordinates').innerHTML = `${data.lat}, ${data.lng}`;
                    map.panTo(new L.LatLng(data.lat, data.lng));
                }
            }

            // ================= 7. Deteksi Bahaya =================
            var isDanger = (data.asap === "Tinggi" || coValue > 50);
            
            // Panggil updateLocationStatus dengan koordinat
            updateLocationStatus(isDanger, data.lat, data.lng);

            // 8. Update Grafik
            dataChart.labels.push(data.waktu || new Date().toLocaleTimeString());
            dataChart.datasets[0].data.push(parseFloat(data.tegangan) || 0);
            dataChart.datasets[1].data.push(parseFloat(data.arus) || 0);
            dataChart.datasets[2].data.push(parseFloat(data.daya) || 0);
            dataChart.datasets[3].data.push(parseFloat(data.suhu) || 0);
            dataChart.datasets[4].data.push(parseFloat(data.kelembapan) || 0);
            dataChart.datasets[5].data.push(parseFloat(data.angin) || 0);
            dataChart.datasets[6].data.push(parseFloat(data.co) || 0);

            if(dataChart.labels.length > 20) {
                dataChart.labels.shift();
                dataChart.datasets.forEach(ds => ds.data.shift());
            }
            myChart.update();
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            // Data gagal ditarik, beri notifikasi error
            document.getElementById("status").innerHTML = `<i class="fas fa-times-circle" style="color:#dc3545;"></i> Offline`;
        });
}

// ================= JALANKAN FUNGSI =================
// Jalankan penarikan data dari DB setiap 3 detik
setInterval(fetchDataFromDB, 3000);
fetchDataFromDB(); // Jalankan pertama kali saat load

// Update koordinat awal dari database
document.getElementById('coordinates').innerHTML = `${fixedLat}, ${fixedLng}`;
</script>
</body>
</html>