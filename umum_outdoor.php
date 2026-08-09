<?php
date_default_timezone_set('Asia/Makassar');
session_start();

// Set tipe dashboard sebagai outdoor
$_SESSION['dashboard_type'] = 'outdoor';

// Ambil data user dari session
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
    $q_sensor = mysqli_query($conn, "SELECT * FROM data_sensor WHERE is_dummy = 0 OR is_dummy IS NULL ORDER BY timestamp DESC LIMIT 1");
    if (!$q_sensor || mysqli_num_rows($q_sensor) == 0) {
        $q_sensor = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 1");
    }
    if ($q_sensor && mysqli_num_rows($q_sensor) > 0) {
        $s = mysqli_fetch_assoc($q_sensor);
        $asap_val = (isset($s['asap']) && ($s['asap'] === 'Tinggi' || (is_numeric($s['asap']) && (float)$s['asap'] > 0.5))) ? "Tinggi" : "Normal";
        $co_val = isset($s['co']) ? (float)$s['co'] : 0;
        
        $latest_sensor = [
            'waktu' => date('H:i:s'),
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
$chart_daya = [];
$chart_suhu = [];
$chart_kelembapan = [];
$chart_asap = [];

if ($conn) {
    $q_chart = mysqli_query($conn, "SELECT * FROM (SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 20) Var1 ORDER BY timestamp ASC");
    if ($q_chart) {
        while ($row = mysqli_fetch_assoc($q_chart)) {
            $chart_labels[] = date('d/m/Y H:i:s', strtotime($row['timestamp']));
            $chart_daya[] = (float)($row['daya'] ?? 0);
            $chart_suhu[] = (float)($row['suhu'] ?? 0);
            $chart_kelembapan[] = (float)($row['kelembapan'] ?? 0);
            
            // Konversi asap ke numerik (1 untuk Tinggi, 0 untuk Normal)
            $asap_val = isset($row['asap']) ? $row['asap'] : 'Normal';
            $chart_asap[] = ($asap_val === 'Tinggi' || (is_numeric($asap_val) && (float)$asap_val > 0.5)) ? 1 : 0;
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
<title>Dashboard Outdoor - FIREDETECTOR</title>

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
    background-image: url('https://i.pinimg.com/736x/ea/7c/ca/ea7cca792d193c0a4599fbcf96f21fa3.jpg');
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
    flex-wrap: wrap;
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

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
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

/* ========== GRID SENSOR (4 SENSOR) ========== */
.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.box {
    padding: 12px 10px;
    border-radius: 10px;
    text-align: center;
    color: white;
    transition: transform 0.2s;
    backdrop-filter: blur(5px);
}
.box:hover { transform: scale(1.02); }
.box i { font-size: 22px; margin-bottom: 6px; display: block; }
.box .sensor-label { font-size: 12px; opacity: 0.9; margin-bottom: 4px; }
.box b { display: block; font-size: 16px; margin-top: 3px; }
.box small { display: block; font-size: 10px; opacity: 0.8; margin-top: 2px; }

/* Warna khusus untuk masing-masing sensor (4 Sensor Utama) */
.box.daya-box, .box.solar-box { background: linear-gradient(135deg, rgba(255, 193, 7, 0.9), rgba(255, 107, 0, 0.9)); }
.box.suhu-box { background: linear-gradient(135deg, rgba(255, 99, 132, 0.9), rgba(255, 59, 48, 0.9)); }
.box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); }
.box.kelembapan-box { background: linear-gradient(135deg, rgba(78, 205, 196, 0.9), rgba(52, 152, 219, 0.9)); }

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); }
}
.pulse-animation { animation: pulse 1s ease-in-out infinite; }
.status-aman { color: #28a745; font-weight: bold; }
.status-waspada { color: #f59e0b; font-weight: bold; }
.status-bahaya { color: #dc3545; font-weight: bold; animation: blink 1s infinite; }
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Memastikan teks dalam kotak sensor berlatar warna memiliki kontras tinggi & putih terang */
.box .status-aman,
.box .status-waspada,
.box .status-bahaya,
.box b,
.box .sensor-label,
.box i,
.box small {
    color: #ffffff !important;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
}

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
.chart-container { margin-top: 10px; max-height: 240px; }
canvas {
    max-height: 240px;
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
@media (max-width: 992px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .sidebar { width: 80px; padding: 20px 10px; }
    .sidebar h3 { font-size: 12px; }
    .menu-btn span { display: none; }
    .menu-btn i { margin: 0; }
    .main { padding: 15px; }
    .grid { grid-template-columns: 1fr; }
    #map { height: 250px; }
    .location-info { flex-direction: column; align-items: flex-start; gap: 10px; }
    .header { flex-direction: column; align-items: stretch; gap: 10px; }
    .header-left { flex-direction: column; align-items: stretch; }
    .node-status-header { justify-content: center; }
    .header-right { justify-content: center; flex-wrap: wrap; }
    .modal-box { padding: 30px 20px; }
    .modal-buttons { flex-direction: column; }
    .btn-modal { justify-content: center; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-tree"></i> Outdoor</h3>
    <a href="umum_outdoor.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard Outdoor</span>
        <span class="user-badge">OUTDOOR</span>
    </a>
    <!-- PERBAIKAN: Tombol Home dengan Modal -->
    <a href="#" class="menu-btn logout" onclick="openHomeModal(); return false;">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    <!-- ============================================================ -->
    <!-- ========== HEADER + NODE STATUS GABUNGAN ========== -->
    <!-- ============================================================ -->
    <div class="header">
        <div class="header-left">
            <h2><i class="fas fa-tree"></i> Dashboard Outdoor</h2>
            
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
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($user) ?><span class="user-tag">User</span></span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 4 SENSOR UTAMA ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3><i class="fas fa-microchip"></i> Data Sensor <span id="waktu" style="font-size:12px; color:#666;"><i class="far fa-clock"></i> <?= htmlspecialchars($latest_sensor['waktu']) ?></span></h3>
        <div class="grid">
            <!-- Sensor Daya Panel Surya -->
            <div class="box daya-box">
                <i class="fas fa-solar-panel"></i>
                <div class="sensor-label">Daya Panel Surya</div>
                <b id="daya"><?= htmlspecialchars($latest_sensor['daya']) ?> W</b>
                <small>Watt</small>
            </div>
            
            <!-- Sensor Suhu -->
            <div class="box suhu-box">
                <i class="fas fa-temperature-high"></i>
                <div class="sensor-label">Suhu</div>
                <b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?> °C</b>
                <small>°C</small>
            </div>
            
            <!-- Sensor Asap -->
            <?php 
                $asap_status = $latest_sensor['asap'] ?? 'Normal';
                $asap_bg = 'background: linear-gradient(135deg, rgba(40,167,69,0.95), rgba(32,201,151,0.95));';
                $asap_icon = '<i class="fas fa-check-circle"></i> Normal (Aman)';
                $asap_pulse = '';
                if ($asap_status === 'Tinggi' || $asap_status === 'Bahaya') {
                    $asap_bg = 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));';
                    $asap_icon = '<i class="fas fa-exclamation-triangle"></i> Tinggi (Bahaya)';
                    $asap_pulse = 'pulse-animation';
                } else if ($asap_status === 'Sedang' || $asap_status === 'Waspada') {
                    $asap_bg = 'background: linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95));';
                    $asap_icon = '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)';
                }
            ?>
            <div class="box asap-box <?= $asap_pulse ?>" id="asap-box" style="<?= $asap_bg ?>">
                <i class="fas fa-smog"></i>
                <div class="sensor-label">Asap</div>
                <b id="asap"><?= $asap_icon ?></b>
            </div>
            
            <!-- Sensor Kelembapan -->
            <div class="box kelembapan-box">
                <i class="fas fa-tint"></i>
                <div class="sensor-label">Kelembapan</div>
                <b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?> %</b>
                <small>%</small>
            </div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-tree" style="color: #0083b0;"></i>
            <span style="color: #1e3c72; font-size: 13px;"><strong>Monitoring Outdoor</strong> - 4 sensor utama untuk deteksi dini kebakaran hutan/lahan.</span>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== GRAFIK REAL TIME SENSOR ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> Grafik Real Time Sensor</h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== MAPS / LOKASI (DIPERBAIKI) ========== -->
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
                <i class="fas fa-temperature-high"></i>
                <span class="label">Suhu:</span>
                <span class="value" id="location-suhu-val" style="color: #ff6b6b; font-weight: 700;"><?= htmlspecialchars($latest_sensor['suhu'] ?? '-') ?><?= (isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '' ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-tree"></i>
                <span class="label">Zona:</span>
                <span class="value" id="zone">Zona Outdoor (Area Terbuka)</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-flag-checkered"></i>
                <span class="label">Status:</span>
                <span class="value" id="location-status" style="color: #28a745;">Aman</span>
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

// ================= FUNGSI MODAL HOME (TAMBAHAN) =================
function openHomeModal() {
    document.getElementById('homeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeHomeModal() {
    document.getElementById('homeModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Tutup modal jika klik di luar modal
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});

document.getElementById('homeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeHomeModal();
    }
});

// Tutup modal dengan tombol ESC
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
var currentSuhu = "<?= htmlspecialchars($latest_sensor['suhu'] ?? '-') ?><?= (isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '' ?>";

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

// Icon marker - AMAN / Standar Lokasi (Seragam dengan lokasi lainnya)
var safeIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-location-dot" style="color: white; font-size: 14px;"></i></div>',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16],
    className: 'safe-marker'
});

// Icon marker - BAHAYA (Merah) - Outdoor
var dangerIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; animation: blink 1s infinite;"><i class="fas fa-fire" style="color: white; font-size: 18px;"></i></div>',
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
            <div style="min-width: 210px; font-family: 'Segoe UI', sans-serif; text-align: center; padding: 4px;">
                <i class="fas fa-map-marker-alt" style="color: #e85d04; font-size: 20px; margin-bottom: 5px;"></i>
                <div style="font-weight: 700; font-size: 14px; color: #1e3c72;">${loc.nama_lokasi}</div>
                <div style="font-size: 12px; color: #e85d04; font-weight: 600; margin-top: 2px;">
                    ID: ${loc.id_alat} &nbsp;|&nbsp; <i class="fas fa-temperature-high" style="color:#ff6b6b;"></i> Suhu: <span class="loc-suhu-val">${currentSuhu}</span>
                </div>
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
}var latestRealSensorData = null;

function getDummyDataForLocation(locId, realData) {
    if (!realData) {
        realData = { suhu: 30, kelembapan: 70, asap: 'Normal', api: 'Aman', angin: 3.2, co: 15, tegangan: 220, arus: 1.5, daya: 330, status: 'Online', rssi: -65, ip: '192.168.1.100', lat: fixedLat, lng: fixedLng };
    }
    
    if (locId === 1 || locId === '1' || locId === 'out_1' || locId === 'out_def_1') {
        return realData;
    }

    var numId = typeof locId === 'number' ? locId : (parseInt(String(locId).replace(/\D/g, '')) || 2);
    
    // Siklus kondisi setiap 15 detik: 0 = Aman, 1 = Waspada, 2 = Bahaya
    var stepIndex = Math.floor(Date.now() / 15000);
    var conditionStep = (stepIndex + numId) % 3;
    
    var seconds = new Date().getSeconds();
    var noiseSuhu = parseFloat((Math.sin(seconds) * 0.5).toFixed(1));
    
    var suhuVal, humiVal, asapVal, coVal, isDanger;

    if (conditionStep === 0) {
        // Kondisi AMAN
        suhuVal = (26.0 + (numId % 3) + noiseSuhu).toFixed(1);
        humiVal = Math.round(68 + (numId % 4));
        asapVal = "Normal";
        coVal = Math.round(15 + (numId % 5));
        isDanger = false;
    } else if (conditionStep === 1) {
        // Kondisi WASPADA
        suhuVal = (42.0 + (numId % 3) + noiseSuhu).toFixed(1);
        humiVal = Math.round(48 - (numId % 3));
        asapVal = "Sedang";
        coVal = Math.round(42 + (numId % 5));
        isDanger = false;
    } else {
        // Kondisi BAHAYA
        suhuVal = (65.0 + (numId % 3) + noiseSuhu).toFixed(1);
        humiVal = Math.round(28 - (numId % 3));
        asapVal = "Tinggi";
        coVal = Math.round(85 + (numId % 10));
        isDanger = true;
    }

    var windVal = (2.0 + (numId % 4) * 0.9).toFixed(1);

    return {
        status: realData.status || 'Online',
        rssi: realData.rssi || -65,
        ip: realData.ip || '192.168.1.100',
        suhu: suhuVal,
        kelembapan: humiVal,
        asap: asapVal,
        api: isDanger ? "Terdeteksi Api" : "Aman",
        angin: windVal,
        arah: (numId % 2 === 0) ? "Utara" : "Timur",
        co: coVal,
        tegangan: (219 + (numId % 3)).toFixed(1),
        arus: (1.2 + (numId % 4) * 0.1).toFixed(2),
        daya: (250 + (numId % 6) * 20).toFixed(1),
        lat: realData.lat,
        lng: realData.lng,
        isDanger: isDanger,
        is_dummy: 1
    };
}

// ================= FUNGSI FLY TO LOCATION =================
function flyToLocation(lat, lng, id) {
    var prevId = activeSelectedLocationId;
    activeSelectedLocationId = id; // Simpan ID lokasi yang sedang diklik user
    if (dangerZone) {
        dangerZone.setLatLng([lat, lng]);
    }
    if (prevId !== id && typeof fetchDataOutdoor === 'function') {
        scheduleNextUpdate();
        fetchDataOutdoor();
    }
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

    if (latestRealSensorData) {
        updateUI(getDummyDataForLocation(id, latestRealSensorData));
    }
}

// ================= FUNGSI UPDATE LOCATION STATUS =================
function updateLocationStatus(activeData, lat, lng) {
    var mainLoc = allLocations.find(l => l.id === 1) || { nama_lokasi: 'Lokasi Utama', id_alat: 'OUT-001' };

    var asapVal = activeData ? activeData.asap : 'Normal';
    var coVal = activeData ? (parseFloat(activeData.co) || 0) : 0;
    var isDanger = (asapVal === "Tinggi" || asapVal === "Bahaya" || coVal > 50 || (activeData && activeData.isDanger));
    var isWaspada = (!isDanger && (asapVal === "Sedang" || asapVal === "Waspada" || coVal > 25));

    if (isDanger) {
        if (dangerZone) {
            dangerZone.setStyle({ color: '#dc2626', fillColor: '#dc2626', fillOpacity: 0.3 });
        }
        document.getElementById('location-status').innerHTML = '⚠️ BAHAYA - Deteksi Kebakaran!';
        document.getElementById('location-status').style.color = '#dc2626';
        document.getElementById('zone').innerHTML = 'Zona Merah (Peringatan Bahaya)';
        if (activeSelectedLocationId === 1 && sensorMarker) {
            sensorMarker.setIcon(dangerIcon);
        }
    } else if (isWaspada) {
        if (dangerZone) {
            dangerZone.setStyle({ color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.2 });
        }
        document.getElementById('location-status').innerHTML = '⚠️ WASPADA - Indikasi Asap/Gas';
        document.getElementById('location-status').style.color = '#f59e0b';
        document.getElementById('zone').innerHTML = 'Zona Oranye (Waspada)';
        if (activeSelectedLocationId === 1 && sensorMarker) {
            sensorMarker.setIcon(safeIcon);
        }
    } else {
        if (dangerZone) {
            dangerZone.setStyle({ color: '#28a745', fillColor: '#28a745', fillOpacity: 0.1 });
        }
        document.getElementById('location-status').innerHTML = 'Aman';
        document.getElementById('location-status').style.color = '#28a745';
        document.getElementById('zone').innerHTML = 'Zona Hijau (Aman)';
        if (activeSelectedLocationId === 1 && sensorMarker) {
            sensorMarker.setIcon(safeIcon);
        }
    }
}

// ================= FUNGSI UPDATE UI =================
function updateUI(rawRealData) {
    latestRealSensorData = rawRealData;
    var data = getDummyDataForLocation(activeSelectedLocationId, rawRealData);

    // Update status node di header
    var nowClock = new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById("status").innerHTML = `<i class="fas fa-circle status-online"></i> ${data.status || 'Online'}`;
    document.getElementById("rssi").innerHTML = `${data.rssi || '-'} dBm`;
    document.getElementById("ip").innerHTML = data.ip || '-';
    document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${nowClock}`;

    // Update Sensor Cards (4 Sensor Utama)
    var dayaElem = document.getElementById("daya");
    if (dayaElem) dayaElem.innerHTML = `${data.daya || 0} W`;

    var suhuElem = document.getElementById("suhu");
    if (suhuElem) suhuElem.innerHTML = `${data.suhu || 0} °C`;
    
    var humiElem = document.getElementById("kelembapan");
    if (humiElem) humiElem.innerHTML = `${data.kelembapan || 0} %`;

    // Update Asap status
    var asapElement = document.getElementById("asap");
    var asapBox = document.getElementById('asap-box');
    if (asapElement && asapBox) {
        var asapVal = data.asap;
        if (asapVal === "Tinggi" || asapVal === "Bahaya") {
            asapElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tinggi (Bahaya)';
            asapElement.className = 'status-bahaya';
            asapBox.classList.add('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else if (asapVal === "Sedang" || asapVal === "Waspada") {
            asapElement.innerHTML = '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)';
            asapElement.className = 'status-waspada';
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95))";
        } else {
            asapElement.innerHTML = '<i class="fas fa-check-circle"></i> Normal (Aman)';
            asapElement.className = 'status-aman';
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(40,167,69,0.95), rgba(32,201,151,0.95))";
        }
    }
    
    if (data.suhu !== undefined) {
        currentSuhu = `${data.suhu} °C`;
        document.querySelectorAll('.loc-suhu-val').forEach(el => el.innerHTML = currentSuhu);
    }
    
    // Update Peta
    var activeLoc = allLocations.find(l => l.id === activeSelectedLocationId);
    var curLat = activeLoc ? activeLoc.lat : (rawRealData.lat || fixedLat);
    var curLng = activeLoc ? activeLoc.lng : (rawRealData.lng || fixedLng);
    
    if (dangerZone) {
        dangerZone.setLatLng([curLat, curLng]);
    }
    if (activeSelectedLocationId === 1 && sensorMarker && rawRealData.lat && rawRealData.lng) {
        sensorMarker.setLatLng([rawRealData.lat, rawRealData.lng]);
        document.getElementById('coordinates').innerHTML = `${rawRealData.lat}, ${rawRealData.lng}`;
        map.panTo(new L.LatLng(rawRealData.lat, rawRealData.lng));
    }
    
    updateLocationStatus(data, curLat, curLng);
    
    // Update Chart Grafik
    var asapValue = data.asap === "Tinggi" ? 1 : 0;
    var todayDateStr = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    var chartTimeStr = todayDateStr + ' ' + new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    dataChart.labels.push(chartTimeStr);
    dataChart.datasets[0].data.push(parseFloat(data.daya));
    dataChart.datasets[1].data.push(parseFloat(data.suhu));
    dataChart.datasets[2].data.push(parseFloat(data.kelembapan));
    dataChart.datasets[3].data.push(asapValue);
    
    if(dataChart.labels.length > 20) { 
        dataChart.labels.shift(); 
        dataChart.datasets.forEach(ds => ds.data.shift()); 
    }
    myChart.update();
}

// ================= CHART (4 SENSOR) =================
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = { 
    labels: <?= json_encode($chart_labels) ?>, 
    datasets: [
        { 
            label: 'Daya Panel Surya (W)', 
            data: <?= json_encode($chart_daya) ?>, 
            borderColor: '#ffc107', 
            backgroundColor: 'rgba(255,193,7,0.1)', 
            borderWidth: 2, 
            tension: 0.4, 
            fill: true 
        },
        { 
            label: 'Suhu (°C)', 
            data: <?= json_encode($chart_suhu) ?>, 
            borderColor: '#ff6b6b', 
            backgroundColor: 'rgba(255,107,107,0.1)', 
            borderWidth: 2, 
            tension: 0.4, 
            fill: true 
        },
        { 
            label: 'Kelembapan (%)', 
            data: <?= json_encode($chart_kelembapan) ?>, 
            borderColor: '#4ecdc4', 
            backgroundColor: 'rgba(78,205,196,0.1)', 
            borderWidth: 2, 
            tension: 0.4, 
            fill: true 
        },
        { 
            label: 'Asap', 
            data: <?= json_encode($chart_asap) ?>, 
            borderColor: '#ff9f43', 
            backgroundColor: 'rgba(255,159,67,0.1)', 
            borderWidth: 2, 
            tension: 0.4, 
            fill: true,
            borderDash: [5, 5]
        }
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
            legend: { 
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'line',
                    boxWidth: 30,
                    padding: 15,
                    font: { size: 12, weight: '600' }
                }
            }, 
            tooltip: { 
                mode: 'index', 
                intersect: false,
                callbacks: {
                    title: function(tooltipItems) {
                        let rawTitle = tooltipItems[0].label || '';
                        return '📅 ' + rawTitle;
                    },
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let value = context.raw;
                        let unit = '';
                        if (label.includes('Daya')) unit = ' W';
                        else if (label.includes('Suhu')) unit = ' °C';
                        else if (label.includes('Kelembapan')) unit = ' %';
                        else if (label.includes('Asap')) {
                            if (value === 1) return 'Asap: Tinggi ⚠️';
                            return 'Asap: Normal ✅';
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
                title: { display: true, text: 'Waktu' },
                ticks: {
                    callback: function(val, index) {
                        let label = this.getLabelForValue(val) || '';
                        if (typeof label === 'string' && label.includes(' ')) {
                            let parts = label.split(' ');
                            return parts[1] || label;
                        }
                        return label;
                    }
                }
            } 
        } 
    } 
});

// ================= DATA DARI DATABASE (IKUTI INTERVAL ADMIN OUTDOOR) =================
var autoUpdateTimer = null;
var currentMainDeviceIntervalMs = 30000;

function fetchDataOutdoor() {
    fetch('get_latest_data.php')
    .then(response => {
        if (!response.ok) throw new Error("Gagal mengambil data dari server");
        return response.json();
    })
    .then(data => {
        if (data.interval_detik && parseInt(data.interval_detik) >= 3) {
            currentMainDeviceIntervalMs = parseInt(data.interval_detik) * 1000;
        }
        updateUI(data); // Tampilkan data asli dari database
    })
    .catch(error => {
        console.warn("API tidak merespons, menggunakan data dummy/fallback:", error);
        updateUI(generateDummyData());
    });
}

// ================= FUNGSI UPDATE UI =================
function updateUI(data) {
    // Update status node di header
    var nowClock = new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById("status").innerHTML = `<i class="fas fa-circle status-online"></i> ${data.status}`;
    document.getElementById("rssi").innerHTML = `${data.rssi} dBm`;
    document.getElementById("ip").innerHTML = data.ip;
    document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${nowClock}`;
    
    // Update Sensor Daya
    document.getElementById("daya").innerHTML = `${data.daya} W`;
    
    // Update Suhu
    document.getElementById("suhu").innerHTML = `${data.suhu} °C`;
    
    // Update Asap status
    var asapElement = document.getElementById("asap");
    var asapBox = document.getElementById('asap-box');
    if (data.asap === "Tinggi") {
        asapElement.innerHTML = '⚠️ Tinggi';
        asapElement.className = 'status-bahaya';
        asapBox.classList.add('pulse-animation');
        asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
    } else {
        asapElement.innerHTML = '✅ Normal';
        asapElement.className = 'status-aman';
        asapBox.classList.remove('pulse-animation');
        asapBox.style.background = "linear-gradient(135deg, rgba(255,165,2,0.9), rgba(255,99,72,0.9))";
    }
    
    // Update Kelembapan & Suhu Lokasi
    document.getElementById("kelembapan").innerHTML = `${data.kelembapan} %`;
    if (data.suhu !== undefined) {
        currentSuhu = `${data.suhu} °C`;
        document.querySelectorAll('.loc-suhu-val').forEach(el => el.innerHTML = currentSuhu);
    }
    
    // Update Peta (Zona Merah / Hijau)
    var isDanger = data.isDanger || data.asap === "Tinggi";
    
    // Perbarui posisi marker dan zone dengan koordinat dari database jika tersedia
    var lat = data.lat || fixedLat;
    var lng = data.lng || fixedLng;
    
    // Update posisi marker dan danger zone
    sensorMarker.setLatLng([lat, lng]);
    dangerZone.setLatLng([lat, lng]);
    
    // Hanya update teks koordinat dan geser peta jika user sedang melihat Lokasi Utama (ID 1)
    if (activeSelectedLocationId === 1) {
        document.getElementById('coordinates').innerHTML = `${lat}, ${lng}`;
        map.panTo(new L.LatLng(lat, lng));
    }
    
    // Update status lokasi (bahaya/aman)
    updateLocationStatus(isDanger, lat, lng);
    
    // Update Chart Grafik
    var asapValue = data.asap === "Tinggi" ? 1 : 0;
    
    var chartTimeStr = new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    dataChart.labels.push(chartTimeStr);
    dataChart.datasets[0].data.push(parseFloat(data.daya));
    dataChart.datasets[1].data.push(parseFloat(data.suhu));
    dataChart.datasets[2].data.push(parseFloat(data.kelembapan));
    dataChart.datasets[3].data.push(asapValue);
    
    if(dataChart.labels.length > 20) { 
        dataChart.labels.shift(); 
        dataChart.datasets.forEach(ds => ds.data.shift()); 
    }
    myChart.update();
}

// ================= DATA SIMULASI (FALLBACK) =================
function generateDummyData() {
    var isSmokeHigh = Math.random() > 0.85;
    return {
        status: 'Online',
        rssi: Math.floor(Math.random() * 30 - 80),
        ip: '192.168.1.' + Math.floor(Math.random() * 100 + 10),
        waktu: new Date().toLocaleTimeString(),
        daya: (Math.random() * 80 + 40).toFixed(1),
        suhu: (Math.random() * 15 + 28).toFixed(1),
        kelembapan: (Math.random() * 30 + 50).toFixed(1),
        asap: isSmokeHigh ? "Tinggi" : "Normal",
        isDanger: isSmokeHigh,
        lat: fixedLat,
        lng: fixedLng
    };
}

function scheduleNextUpdate() {
    if (autoUpdateTimer) clearTimeout(autoUpdateTimer);

    var isMainDevice = (activeSelectedLocationId === 1 || activeSelectedLocationId === '1' || activeSelectedLocationId === 'out_1' || activeSelectedLocationId === 'out_def_1');
    var intervalMs = isMainDevice ? currentMainDeviceIntervalMs : 15000;

    autoUpdateTimer = setTimeout(function() {
        fetchDataOutdoor();
        scheduleNextUpdate();
    }, intervalMs);
}

// ================= JALANKAN FUNGSI (IKUTI INTERVAL ADMIN OUTDOOR) =================
fetchDataOutdoor();
scheduleNextUpdate();

// Restore lokasi aktif dari localStorage jika ada
try {
    var savedLocId = localStorage.getItem('activeLocationId');
    if (savedLocId) {
        var numSavedId = parseInt(savedLocId) || 1;
        var foundLoc = allLocations.find(function(l) { return l.id === numSavedId; });
        if (foundLoc) {
            flyToLocation(foundLoc.lat, foundLoc.lng, foundLoc.id);
        }
    }
} catch(e) {}

// Update koordinat awal dari database
document.getElementById('coordinates').innerHTML = `${fixedLat}, ${fixedLng}`;
</script>
</body>
</html>