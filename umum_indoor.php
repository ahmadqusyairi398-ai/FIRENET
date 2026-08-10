<?php
session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// Koneksi ke database indoor & ambil data lokasi dari tabel lokasi_monitoring
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
        
        @mysqli_query($conn, "UPDATE lokasi_monitoring SET nama_lokasi = 'Gedung Elektro Poltekba' WHERE nama_lokasi LIKE '%unmul%' OR nama_lokasi LIKE '%gedung utama%' OR nama_lokasi IS NULL OR nama_lokasi = ''");
        
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

// Inisialisasi variabel grafik chart
$chart_labels = [];
$chart_suhu = [];
$chart_kelembapan = [];
$chart_asap = [];
$chart_api = [];

// Data Sensor & Status Node terbaru dari database indoor (tabel data_sensor)
$latest_sensor = [
    'waktu' => '-',
    'api' => 'Aman',
    'asap' => 'Normal',
    'suhu' => '-',
    'kelembapan' => '-',
    'rssi' => '-',
    'ip' => '-',
    'status' => 'Offline'
];

if ($conn) {
    $checkSensorTable = mysqli_query($conn, "SHOW TABLES LIKE 'data_sensor'");
    if ($checkSensorTable && mysqli_num_rows($checkSensorTable) > 0) {
        $q_latest = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
        if ($q_latest && mysqli_num_rows($q_latest) > 0) {
            $s = mysqli_fetch_assoc($q_latest);

            $raw_asap = $s['asap'] ?? 'Normal';
            if (is_numeric($raw_asap)) {
                $f_asap = (float)$raw_asap;
                if ($f_asap > ($f_asap > 1 ? 750 : 0.5)) $asap_val = "Tinggi";
                else if ($f_asap > ($f_asap > 1 ? 350 : 0.25)) $asap_val = "Sedang";
                else $asap_val = "Normal";
            } else {
                $str_asap = trim((string)$raw_asap);
                if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) $asap_val = "Tinggi";
                else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) $asap_val = "Sedang";
                else $asap_val = "Normal";
            }

            $co_raw = isset($s['co']) ? $s['co'] : 0;
            $co_num = (float)$co_raw;
            if (is_numeric($co_raw)) {
                if ($co_num > 50) $co_status = "Tinggi";
                else if ($co_num > 35) $co_status = "Sedang";
                else $co_status = "Normal";
            } else {
                $str_co = trim((string)$co_raw);
                if (strcasecmp($str_co, 'Tinggi') === 0 || strcasecmp($str_co, 'Bahaya') === 0) $co_status = "Tinggi";
                else if (strcasecmp($str_co, 'Sedang') === 0 || strcasecmp($str_co, 'Waspada') === 0) $co_status = "Sedang";
                else $co_status = "Normal";
            }

            $str_api_umum = isset($s['api']) ? trim(strtolower((string)$s['api'])) : '';
            $api_status_umum = ($str_api_umum === 'terdeteksi api' || $str_api_umum === 'dekat' || $str_api_umum === 'sedang' || $str_api_umum === 'tinggi' || (float)($s['api'] ?? 0) > 0.5) ? "Terdeteksi Api" : "Aman";

            $latest_sensor = [
                'waktu' => date('H:i:s'),
                'api' => $api_status_umum,
                'asap' => $asap_val,
                'co' => is_numeric($co_raw) ? number_format((float)$co_raw, 1) : $co_raw,
                'co_status' => $co_status,
                'suhu' => isset($s['suhu']) ? number_format((float)$s['suhu'], 1) : "-",
                'kelembapan' => isset($s['kelembapan']) ? number_format((float)$s['kelembapan'], 1) : "-",
                'rssi' => isset($s['rssi']) ? $s['rssi'] : "-",
                'ip' => !empty($s['ip_address']) ? $s['ip_address'] : "-",
                'status' => 'Online'
            ];
        }
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

// Ambil data Batas Sensor dari database indoor (tabel batas_indoor atau batas_sensor)
$batas_sensor = [];
$tb_batas_name = 'batas_indoor';
if ($conn) {
    $checkBatasIndoor = mysqli_query($conn, "SHOW TABLES LIKE 'batas_indoor'");
    if ($checkBatasIndoor && mysqli_num_rows($checkBatasIndoor) > 0) {
        $tb_batas_name = 'batas_indoor';
    } else {
        $checkBatasSensor = mysqli_query($conn, "SHOW TABLES LIKE 'batas_sensor'");
        if ($checkBatasSensor && mysqli_num_rows($checkBatasSensor) > 0) {
            $tb_batas_name = 'batas_sensor';
        } else {
            // Buat tabel batas_indoor secara otomatis jika belum ada
            $createBatasTable = "CREATE TABLE IF NOT EXISTS batas_indoor (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_sensor VARCHAR(50) NOT NULL,
                nilai_alarm DECIMAL(10,2) NOT NULL,
                satuan VARCHAR(20) NOT NULL,
                batas_min DECIMAL(10,2),
                batas_max DECIMAL(10,2),
                deskripsi TEXT,
                last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            @mysqli_query($conn, $createBatasTable);
            $tb_batas_name = 'batas_indoor';
        }
    }

    $q_batas = mysqli_query($conn, "SELECT * FROM {$tb_batas_name} ORDER BY id ASC");
    if ($q_batas) {
        while ($b = mysqli_fetch_assoc($q_batas)) {
            $nama_key = strtoupper(trim($b['nama_sensor']));
            $batas_sensor[$nama_key] = [
                'nama_sensor' => $b['nama_sensor'],
                'nilai_alarm' => (float)$b['nilai_alarm'],
                'satuan' => $b['satuan'],
                'batas_min' => isset($b['batas_min']) ? (float)$b['batas_min'] : null,
                'batas_max' => isset($b['batas_max']) ? (float)$b['batas_max'] : null,
                'deskripsi' => $b['deskripsi'] ?? ''
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Indoor - FIREDETECTOR</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ========== STYLE (SAMA SEPERTI ASLI) ========== */
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
}
.menu-btn i { width: 24px; font-size: 18px; }
.menu-btn:hover { background: rgba(255,255,255,0.3); transform: translateX(5px); }
.menu-btn.active { background: linear-gradient(135deg, #00b4db, #0083b0); }
.logout { margin-top: 40px; background: rgba(220, 53, 69, 0.8); }
.logout:hover { background: #dc3545; }
.main {
    flex: 1;
    padding: 20px 30px;
    overflow-y: auto;
    height: 100vh;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 15px 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.header h2 { color: #1e3c72; font-size: 24px; }
.header-right { display: flex; align-items: center; gap: 15px; }
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    padding: 8px 20px;
    border-radius: 50px;
    color: white;
    font-weight: bold;
}
.btn-home-header {
    background: rgba(34, 6, 244, 0.2);
    color: white;
    border: none;
    padding: 8px 18px;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-home-header:hover { background: rgba(255, 255, 255, 0.45); transform: translateY(-2px); }
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
.node-status {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 10px;
}
.status-item {
    background: rgba(248, 249, 250, 0.8);
    padding: 12px;
    border-radius: 10px;
    text-align: center;
}
.status-item i { font-size: 24px; margin-bottom: 8px; display: block; }
.status-item .label { font-size: 12px; color: #555; margin-bottom: 5px; }
.status-item .value { font-size: 18px; font-weight: bold; color: #1e3c72; }
.status-online { color: #28a745; }
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
/* .box.api-box { background: linear-gradient(135deg, rgba(255, 107, 107, 0.9), rgba(238, 90, 36, 0.9)); } */
/* .box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); } */
.box.co-box { background: linear-gradient(135deg, rgba(156, 39, 176, 0.9), rgba(103, 58, 183, 0.9)); }
.status-aman { color: #28a745; font-weight: bold; }
.status-waspada { color: #f59e0b; font-weight: bold; }
.status-bahaya { color: #dc3545; font-weight: bold; animation: blink 1s infinite; }
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); }
}
.pulse-animation { animation: pulse 1s ease-in-out infinite; }
.map-container {
    margin-top: 10px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(224, 224, 224, 0.5);
}
#map {
    height: 400px;
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
.location-info-item i { font-size: 18px; color: #e85d04; }
.location-info-item .label { color: #555; }
.location-info-item .value { font-weight: 600; color: #1e3c72; }
.chart-container { margin-top: 10px; }
canvas {
    max-height: 400px;
    width: 100%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    padding: 10px;
}
/* ========== MODAL HOME ========== */
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

.modal-icon-home {
    font-size: 48px;
    color: #0083b0;
    background: rgba(0, 131, 176, 0.1);
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
    font-size: 20px;
    margin-bottom: 25px;
    font-weight: 600;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn-modal {
    padding: 12px 30px;
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

.btn-home-confirm {
    background: linear-gradient(135deg, #0083b0, #00b4db);
    color: white;
}

.btn-home-confirm:hover {
    background: linear-gradient(135deg, #007299, #0099b8);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0, 131, 176, 0.4);
}

@media (max-width: 768px) {
    .sidebar { width: 80px; padding: 20px 10px; }
    .sidebar h3 { font-size: 12px; }
    .menu-btn span { display: none; }
    .menu-btn i { margin: 0; }
    .main { padding: 15px; }
    .grid { grid-template-columns: repeat(2, 1fr); }
    .node-status { grid-template-columns: 1fr; }
    #map { height: 300px; }
    .location-info { flex-direction: column; align-items: flex-start; }
    .header-right { flex-direction: column; gap: 8px; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-building"></i> Indoor</h3>
    <a href="umum_indoor.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard Indoor</span>
    </a>
    <a href="javascript:void(0);" onclick="openHomeModal()" class="menu-btn logout">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="header">
        <h2><i class="fas fa-building"></i> Dashboard Monitoring Indoor</h2>
        <div class="header-right">
            <div class="user-info"><i class="fas fa-user-circle"></i><span>Halo <?= htmlspecialchars($user) ?></span></div>
        </div>
    </div>

    <!-- NODE STATUS -->
    <div class="card">
        <h3><i class="fas fa-microchip"></i> Status Node Indoor</h3>
        <div class="node-status">
            <div class="status-item"><i class="fas fa-circle <?= $latest_sensor['status'] === 'Online' ? 'status-online' : '' ?>" style="<?= $latest_sensor['status'] !== 'Online' ? 'color:#dc3545;' : '' ?>"></i><div class="label">Status</div><div class="value" id="status"><?= htmlspecialchars($latest_sensor['status']) ?></div></div>
            <div class="status-item"><i class="fas fa-signal"></i><div class="label">RSSI</div><div class="value" id="rssi"><?= htmlspecialchars($latest_sensor['rssi']) ?><?= $latest_sensor['rssi'] !== '-' ? ' dBm' : '' ?></div></div>
            <div class="status-item"><i class="fas fa-network-wired"></i><div class="label">IP Address</div><div class="value" id="ip"><?= htmlspecialchars($latest_sensor['ip']) ?></div></div>
        </div>
    </div>

    <!-- SENSOR DATA -->
    <div class="card">
        <h3><i class="fas fa-microphone-alt"></i> Data Sensor Real Time (Indoor) <span style="font-size: 12px; color: #666;" id="waktu"><?= htmlspecialchars($latest_sensor['waktu']) ?></span></h3>
        <div class="grid">
            <div class="box" id="api-box" style="<?= $latest_sensor['api'] === 'Terdeteksi Api' ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : '' ?>">
                <i class="fas fa-fire"></i>
                <div class="sensor-label">Sensor Api</div>
                <b id="api"><?= $latest_sensor['api'] === 'Terdeteksi Api' ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman' ?></b>
                <small id="api-threshold">Batas Alarm: <?= isset($batas_sensor['API']) ? $batas_sensor['API']['nilai_alarm'] : 1 ?></small>
            </div>
            <div class="box <?= ($latest_sensor['asap'] === 'Tinggi') ? 'pulse-animation' : '' ?>" id="asap-box" style="<?= ($latest_sensor['asap'] === 'Tinggi') ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : ($latest_sensor['asap'] === 'Sedang' ? 'background: linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95));' : '') ?>">
                <i class="fas fa-smog"></i>
                <div class="sensor-label">Sensor Asap</div>
                <b id="asap">
                    <?php if ($latest_sensor['asap'] === 'Tinggi'): ?>
                        <i class="fas fa-exclamation-triangle"></i> Tinggi (Berbahaya)
                    <?php elseif ($latest_sensor['asap'] === 'Sedang'): ?>
                        <i class="fas fa-exclamation-circle"></i> Sedang (Waspada)
                    <?php else: ?>
                        <i class="fas fa-check"></i> Normal
                    <?php endif; ?>
                </b>
                <small id="asap-threshold">Batas Alarm: <?= isset($batas_sensor['ASAP']) ? $batas_sensor['ASAP']['nilai_alarm'] . '%' : '70%' ?></small>
            </div>

            <div class="box" id="suhu-box">
                <i class="fas fa-temperature-high"></i>
                <div class="sensor-label">Sensor Suhu</div>
                <b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?><?= $latest_sensor['suhu'] !== '-' ? ' °C' : '' ?> <i class="fas fa-thermometer-half"></i></b>
                <small id="suhu-threshold">Batas Max: <?= isset($batas_sensor['SUHU']) ? $batas_sensor['SUHU']['nilai_alarm'] . ' °C' : '45 °C' ?></small>
            </div>
            <div class="box" id="kelembapan-box">
                <i class="fas fa-tint"></i>
                <div class="sensor-label">Sensor Kelembapan</div>
                <b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?><?= $latest_sensor['kelembapan'] !== '-' ? ' %' : '' ?> <i class="fas fa-tint"></i></b>
                <small id="kelembapan-threshold">Batas Alarm: <?= isset($batas_sensor['KELEMBAPAN']) ? $batas_sensor['KELEMBAPAN']['nilai_alarm'] . ' %' : '85 %' ?></small>
            </div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-building" style="color: #0083b0;"></i>
            <span style="color: #1e3c72; font-size: 13px;"><strong>Monitoring Indoor</strong> - Terhubung ke database indoor (tabel <code>data_sensor</code> &amp; <code>batas_sensor</code>).</span>
        </div>
    </div>

    <!-- CHART -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid rgba(0,0,0,0.1); padding-bottom: 10px;">
            <h3 style="margin: 0; padding: 0; border: none;">
                <i class="fas fa-chart-line"></i> Grafik Real Time Sensor
                <span id="chart-badge" style="font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: bold; margin-left: 10px; color: white; transition: all 0.3s;"></span>
            </h3>
            <span style="font-size: 12px; background: rgba(40, 167, 69, 0.1); color: #28a745; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                <i class="fas fa-sync-alt fa-spin"></i> Live Real-Time (2s)
            </span>
        </div>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>

    <!-- LOKASI / MAP CARD (DIPERBAIKI: TERHUBUNG KE TABEL lokasi_monitoring DATABASE INDOOR) -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; padding: 0; border: none;"><i class="fas fa-map-marker-alt"></i> Lokasi Alat (Indoor)</h3>
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

<script>
// ================= PETA & LOKASI DINAMIS DARI DATABASE INDOOR (lokasi_monitoring) =================
var defaultLat = <?= (float)$primary_loc['latitude']; ?>;
var defaultLng = <?= (float)$primary_loc['longitude']; ?>;
var initialLocations = <?= json_encode($db_locations); ?>;

var map = L.map('map').setView([defaultLat, defaultLng], 16);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

L.control.scale({ metric: true, imperial: false }).addTo(map);

var markers = [];
var dangerZones = [];

function createIndoorIcon(id_alat, isDanger) {
    if (isDanger) {
        return L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 42px; height: 42px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; flex-direction: column; animation: blink 1s infinite;">
                    <i class="fas fa-exclamation-triangle" style="color: white; font-size: 14px;"></i>
                    <span style="font-size: 8px; color: white; font-weight: bold; margin-top: 1px;">${id_alat || 'Indoor'}</span>
                  </div>`,
            iconSize: [42, 42],
            iconAnchor: [21, 21],
            popupAnchor: [0, -21],
            className: 'indoor-marker-danger'
        });
    } else {
        return L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 42px; height: 42px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <i class="fas fa-building" style="color: white; font-size: 14px;"></i>
                    <span style="font-size: 8px; color: white; font-weight: bold; margin-top: 1px;">${id_alat || 'Indoor'}</span>
                  </div>`,
            iconSize: [42, 42],
            iconAnchor: [21, 21],
            popupAnchor: [0, -21],
            className: 'indoor-marker'
        });
    }
}

async function fetchLocationsFromDB() {
    try {
        const response = await fetch('get_locations.php');
        const result = await response.json();
        if (!result.error && Array.isArray(result.data) && result.data.length > 0) {
            return result.data;
        }
    } catch (error) {
        console.error('Gagal mengambil data lokasi dari database:', error);
    }
    return initialLocations;
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
    fetchDataFromDB();
}

var currentLocationsData = [];

async function updateLocationStatus(statusText, isDanger) {
    if (typeof statusText === 'boolean') {
        const temp = isDanger;
        isDanger = statusText;
        statusText = typeof temp === 'string' ? temp : (isDanger ? 'Kebakaran' : 'Aman');
    }
    if (!statusText) statusText = 'Aman';
    if (typeof isDanger === 'undefined') isDanger = (statusText !== 'Aman');

    const locations = await fetchLocationsFromDB();
    currentLocationsData = locations;
    
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    dangerZones.forEach(z => map.removeLayer(z));
    dangerZones = [];
    
    const totalElem = document.getElementById('total-locations');
    if (totalElem) {
        totalElem.innerHTML = locations.length;
    }

    const statusElem = document.getElementById('location-status');
    const zoneElem = document.getElementById('zone');
    
    if (statusElem) {
        if (statusText && statusText !== 'Aman') {
            statusElem.innerHTML = statusText;
            if (statusText === 'Kebakaran') {
                statusElem.style.color = '#dc2626';
            } else {
                statusElem.style.color = '#f59e0b';
            }
        } else if (isDanger) {
            statusElem.innerHTML = 'Kebakaran';
            statusElem.style.color = '#dc2626';
        } else {
            statusElem.innerHTML = 'Aman';
            statusElem.style.color = '#28a745';
        }
    }

    if (zoneElem) {
        if (statusText === 'Kebakaran' || (isDanger && !statusText)) {
            zoneElem.innerHTML = 'Zona Merah (Deteksi Kebakaran)';
        } else if (statusText === 'lingkungan tidak normal' || statusText === 'Lingkungan tidak normal') {
            zoneElem.innerHTML = 'Zona Waspada (Lingkungan Tidak Normal)';
        } else if (statusText === 'Gangguan listrik') {
            zoneElem.innerHTML = 'Zona Waspada (Gangguan Listrik)';
        } else {
            zoneElem.innerHTML = 'Zona Indoor (Gedung)';
        }
    }

    if (!locations || locations.length === 0) {
        const icon = createIndoorIcon('001', isDanger);
        const m = L.marker([defaultLat, defaultLng], { icon: icon }).addTo(map);
        markers.push(m);
        return;
    }

    locations.forEach((loc, idx) => {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);
        const idAlat = loc.id_alat || `00${loc.id}`;
        const namaLokasi = loc.nama_lokasi && loc.nama_lokasi.trim() !== '' ? loc.nama_lokasi : `Indoor (${idAlat})`;
        
        const rawIdUpper = String(idAlat || '').toUpperCase();
        const isLocDanger = (loc.id === activeSelectedLocationId) ? isDanger : false;

        const icon = createIndoorIcon(idAlat, isLocDanger);
        const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
        
        const statusBadge = isLocDanger 
            ? '<span style="color: white; background: #dc2626; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-exclamation-triangle"></i> BAHAYA</span>' 
            : '<span style="color: white; background: #28a745; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-check-circle"></i> Aman</span>';
        
        marker.bindPopup(`
            <div style="font-family: 'Segoe UI', sans-serif; padding: 4px; min-width: 190px;">
                <b style="color: #1e3c72; font-size: 14px; display: block; margin-bottom: 2px;"><i class="fas fa-building" style="color: #00b4db;"></i> ${namaLokasi}</b>
                <small style="color: #666; display: block; margin-bottom: 6px;">ID Alat: <strong>${idAlat}</strong> &nbsp;|&nbsp; <i class="fas fa-temperature-high" style="color:#ff6b6b;"></i> Suhu: <strong class="loc-suhu-val">${currentSuhu}</strong></small>
                <div style="font-size: 12px; color: #444; margin-bottom: 4px;"><i class="fas fa-map-marker-alt" style="color: #dc2626;"></i> <b>Koordinat:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
                <div style="font-size: 11px; color: #777; margin-bottom: 6px;"><i class="fas fa-clock"></i> <b>Update:</b> ${loc.last_update || '-'}</div>
                <div style="font-size: 12px; margin-top: 6px;"><b>Status:</b> ${statusBadge}</div>
            </div>
        `);
        
        marker.on('click', function() {
            flyToLocation(lat, lng, namaLokasi, idAlat, loc.id);
        });
        
        const circleColor = isLocDanger ? '#dc2626' : '#e85d04';
        const circleOpacity = isLocDanger ? 0.3 : 0.15;
        const zone = L.circle([lat, lng], {
            color: circleColor,
            fillColor: circleColor,
            fillOpacity: circleOpacity,
            radius: 300
        }).addTo(map);
        
        markers.push(marker);
        dangerZones.push(zone);

        if (!activeSelectedLocationId && idx === 0) {
            activeSelectedLocationId = loc.id;
        }

        if (activeSelectedLocationId === loc.id) {
            const locNameElem = document.getElementById('location-name-val');
            if (locNameElem) locNameElem.innerText = namaLokasi;
            const locIdElem = document.getElementById('location-id-val');
            if (locIdElem) locIdElem.innerText = idAlat;
            const coordElem = document.getElementById('coordinates');
            if (coordElem) coordElem.innerHTML = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
    });

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
        const selectedLoc = locations.find(l => l.id === activeSelectedLocationId);
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
        if (markers.length === 1) {
            map.setView(markers[0].getLatLng(), 16);
        } else {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.2));
        }
        hasFitBounds = true;
    }
}

// Render awal titik lokasi peta saat pertama kali dimuat
updateLocationStatus(false);

// ================= CHART (REAL TIME INDOOR SENSOR - API, ASAP, SUHU, KELEMBAPAN) =================
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = {
    labels: <?= json_encode($chart_labels); ?>,
    datasets: [
        { label: 'Suhu (°C)', data: <?= json_encode($chart_suhu); ?>, borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kelembapan (%)', data: <?= json_encode($chart_kelembapan); ?>, borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Status Asap', data: <?= json_encode($chart_asap); ?>, borderColor: '#ff9f43', backgroundColor: 'rgba(255,159,67,0.1)', borderWidth: 2, tension: 0.4, fill: true, borderDash: [5, 5] },
        { label: 'Status Api', data: <?= json_encode($chart_api); ?>, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', borderWidth: 2, tension: 0.4, fill: true }
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
                        if (label.includes('Suhu')) unit = ' °C';
                        else if (label.includes('Kelembapan')) unit = ' %';
                        else if (label.includes('Status Asap')) {
                            let status = (value === 1 || value === 'Tinggi') ? '⚠️ Asap Tinggi' : (value === 0.5 || value === 'Sedang' ? '⚡ Asap Sedang' : '✅ Normal');
                            return `${label}: ${status}`;
                        }
                        else if (label.includes('Status Api')) {
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

// ================= GENERATE DATA (SIKLUS NORMAL -> WASPADA -> BAHAYA) =================
let dummyState = 0; // 0 = Normal, 1 = Waspada, 2 = Bahaya

function generateData() {
    let apiStatus = "Aman";
    let asapStatus = "Normal";
    let suhu = 28;
    let kelembapan = 60;
    let tegangan = 220;
    let arus = 2.5;
    let isDanger = false;
    let isWarning = false;

// ================= GENERATE DATA (SIKLUS NORMAL -> LINGKUNGAN TIDAK NORMAL -> GANGGUAN LISTRIK -> KEBAKARAN) =================
let dummyState = 0; // 0 = Normal, 1 = Lingkungan Tidak Normal, 2 = Gangguan Listrik, 3 = Kebakaran

function generateData() {
    let apiStatus = "Aman";
    let asapStatus = "Normal";
    let suhu = 28;
    let kelembapan = 60;
    let tegangan = 220;
    let arus = 2.5;
    let isDanger = false;
    let isWarning = false;

    if (dummyState === 0) {
        // === STATUS NORMAL ===
        apiStatus = "Aman";
        asapStatus = "Normal";
        suhu = (Math.random() * 5 + 25).toFixed(1); // Suhu 25-30 °C
        kelembapan = (Math.random() * 15 + 50).toFixed(1); // Kelembapan 50-65 %
        tegangan = 220;
        arus = (Math.random() * 1 + 2).toFixed(2); // Arus 2-3 A
    } else if (dummyState === 1) {
        // === STATUS LINGKUNGAN TIDAK NORMAL ===
        apiStatus = "Aman";
        asapStatus = "Normal";
        suhu = (Math.random() * 5 + 42).toFixed(1); // Suhu tinggi (> 40 °C)
        kelembapan = (Math.random() * 5 + 12).toFixed(1); // Kelembapan rendah (< 20 %)
        tegangan = 220;
        arus = (Math.random() * 1 + 2).toFixed(2);
        isWarning = true;
    } else if (dummyState === 2) {
        // === STATUS GANGGUAN LISTRIK ===
        apiStatus = "Aman";
        asapStatus = "Normal";
        suhu = (Math.random() * 5 + 25).toFixed(1);
        kelembapan = (Math.random() * 15 + 50).toFixed(1);
        tegangan = (Math.random() * 10 + 245).toFixed(1); // Tegangan tinggi (> 240 V)
        arus = (Math.random() * 3 + 6.5).toFixed(2); // Arus tinggi (> 5 A)
        isWarning = true;
    } else {
        // === STATUS KEBAKARAN ===
        apiStatus = "Terdeteksi Api";
        asapStatus = "Tinggi";
        suhu = (Math.random() * 15 + 48).toFixed(1);
        kelembapan = (Math.random() * 10 + 15).toFixed(1);
        tegangan = 210;
        arus = (Math.random() * 5 + 10).toFixed(2);
        isDanger = true;
    }

    // Putar state untuk perputaran berikutnya (0 -> 1 -> 2 -> 3 -> 0)
    dummyState = (dummyState + 1) % 4;

    return {
        waktu: new Date().toLocaleTimeString(),
        api: apiStatus,
        asap: asapStatus,
        asap_value: asapStatus === "Tinggi" ? 1 : (asapStatus === "Waspada" ? 0.5 : 0),
        suhu: suhu,
        kelembapan: kelembapan,
        tegangan: tegangan,
        arus: arus,
        status: 'Online',
        rssi: Math.floor(Math.random() * 40 + -80),
        ip: '192.168.1.' + Math.floor(Math.random() * 255),
        isDanger: isDanger,
        isWarning: isWarning,
        apiValue: apiStatus === "Terdeteksi Api" ? 1 : 0,
        limit_suhu: 40,
        limit_kelembapan: 20,
        limit_tegangan: 240,
        limit_arus: 5
    };
}

function generateDummyData() {
    return generateData();
}

async function fetchSensorData() {
    try {
        const response = await fetch('api_get_data.php?device=indoor');
        const data = await response.json();
        if (data.error) return null;
        return data;
    } catch (error) {
        return null;
    }
}

var batasSensorConfig = <?= json_encode($batas_sensor); ?>;

async function fetchDataFromDB() {
    const locationsList = (currentLocationsData && currentLocationsData.length > 0) ? currentLocationsData     const lokasiAktif = locationsList.find(l => l.id === activeSelectedLocationId) || locationsList[0];
    const rawIdAlat = String(lokasiAktif.id_alat || '').toUpperCase();
    const isLive = (rawIdAlat === 'LOK-002' || rawIdAlat === 'IND-002' || rawIdAlat === '002' || rawIdAlat.includes('002') || rawIdAlat.includes('UTAMA') || lokasiAktif.id === 2);

    let data;
    if (isLive) {
        data = await fetchSensorData();
    } else {
        data = generateData();
    }

    if (!data) return;

    var nowClock = data.waktu || new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });

    var statusElem = document.getElementById("status");
    if (statusElem) {
        if (isLive) {
            statusElem.innerHTML = `<i class="fas fa-circle status-online"></i> Live (Real-Time)`;
        } else {
            statusElem.innerHTML = `<i class="fas fa-circle" style="color: #00b4db;"></i> Simulasi (Dummy)`;
        }
    }
        var rssiElem = document.getElementById("rssi");
        if (rssiElem) rssiElem.innerHTML = `${data.rssi || '-'} dBm`;
        
        var ipElem = document.getElementById("ip");
        if (ipElem) ipElem.innerHTML = data.ip || '-';

        var waktuElem = document.getElementById("waktu");
    if (waktuElem) waktuElem.innerHTML = `<i class="far fa-clock"></i> ${nowClock}`;
    
    // === UPDATE LABEL/BADGE DI ATAS CHART ===
    const chartBadge = document.getElementById("chart-badge");
    if (chartBadge) {
        if (isLive) {
            // Jika LOK-002 (Alat Asli) -> Badge Hijau Live
            chartBadge.innerHTML = '<i class="fas fa-bolt"></i> Live (Real-Time)';
            chartBadge.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        } else {
            // Jika LOK-lain (Dummy) -> Badge Kuning/Oranye Dummy
            chartBadge.innerHTML = '<i class="fas fa-flask"></i> Data Dummy (Simulasi)';
            chartBadge.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        }
    }
        
        // EFEK WARNA & TEKS KOTAK BERGANTIAN SECARA ESTAFET
        const boxes = document.querySelectorAll('.grid .box');

        // Evaluasi Jenis Sensor Terdeteksi
        const isApiDanger = (data.api === "Terdeteksi Api");
        const isAsapDanger = (data.asap === "Tinggi" || data.asap === "Bahaya");

        const limitSuhu = data.limit_suhu !== undefined ? parseFloat(data.limit_suhu) : 40;
        const limitKelembapan = data.limit_kelembapan !== undefined ? parseFloat(data.limit_kelembapan) : 20;
        const isSuhuAbnormal = (data.suhu !== undefined && parseFloat(data.suhu) > limitSuhu);
        const isKelembapanAbnormal = (data.kelembapan !== undefined && parseFloat(data.kelembapan) < limitKelembapan);

        const limitTegangan = data.limit_tegangan !== undefined ? parseFloat(data.limit_tegangan) : 240;
        const limitArus = data.limit_arus !== undefined ? parseFloat(data.limit_arus) : 5;
        const isTeganganOver = (data.tegangan !== undefined && parseFloat(data.tegangan) > limitTegangan);
        const isArusOver = (data.arus !== undefined && parseFloat(data.arus) > limitArus);

        let statusText = "Aman";
        let isDangerDetected = false;

        if (isLive) {
            // Untuk LOK-002 / Live Real-Time: Hanya gunakan data murni dari alat tanpa terpengaruh dummyState
            if (isApiDanger || isAsapDanger || data.isDanger) {
                statusText = "Kebakaran";
                isDangerDetected = true;
            } else if (isSuhuAbnormal || isKelembapanAbnormal) {
                statusText = "lingkungan tidak normal";
                isDangerDetected = true;
            } else if (isTeganganOver || isArusOver) {
                statusText = "Gangguan listrik";
                isDangerDetected = true;
            } else {
                statusText = "Aman";
                isDangerDetected = false;
            }
        } else {
            // Untuk lokasi lain (Dummy/Simulasi): Gunakan perputaran dummyState
            if (isApiDanger || isAsapDanger || (data.isDanger && dummyState === 0)) {
                statusText = "Kebakaran";
                isDangerDetected = true;
            } else if (isSuhuAbnormal || isKelembapanAbnormal || (data.isWarning && dummyState === 2)) {
                statusText = "lingkungan tidak normal";
                isDangerDetected = true;
            } else if (isTeganganOver || isArusOver || (data.isWarning && dummyState === 3)) {
                statusText = "Gangguan listrik";
                isDangerDetected = true;
            } else if (data.isDanger) {
                statusText = "Kebakaran";
                isDangerDetected = true;
            } else {
                statusText = "Aman";
                isDangerDetected = false;
            }
        }

        function setBoxColor(box, index, danger, warning) {
            if (!box) return;
            if (danger) {
                box.classList.add('pulse-animation');
                box.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))"; // MERAH
            } else if (warning) {
                box.classList.remove('pulse-animation');
                box.style.background = "linear-gradient(135deg, rgba(245, 158, 11, 0.9), rgba(217, 119, 6, 0.9))"; // KUNING
            } else {
                box.classList.remove('pulse-animation');
                // Kembalikan ke warna default aman yang sama dengan box lainnya
                box.style.background = "linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9))";
            }
        }

        // Grup 1: Api & Asap (Detik ke-0)
        const apiValue = data.api === "Terdeteksi Api" ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman';
        var apiElem = document.getElementById("api");
        if (apiElem) apiElem.innerHTML = apiValue;

        var asapElem = document.getElementById("asap");
        var asapVal = data.asap;
        if (typeof asapVal === 'number' || (!isNaN(asapVal) && asapVal !== null && asapVal !== '')) {
            var numAsap = parseFloat(asapVal);
            if (!isNaN(numAsap)) {
                if (numAsap > (numAsap > 1 ? 750 : 0.5)) asapVal = "Tinggi";
                else if (numAsap > (numAsap > 1 ? 350 : 0.25)) asapVal = "Waspada";
                else asapVal = "Normal";
            }
        }
        if (asapElem) {
            let asapIcon = '<i class="fas fa-check"></i> Normal';
            if (asapVal === "Waspada" || asapVal === "Sedang") asapIcon = '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)';
            if (asapVal === "Tinggi" || asapVal === "Bahaya") asapIcon = '<i class="fas fa-chart-line"></i> Tinggi (Bahaya)';
            asapElem.innerHTML = asapIcon;
        }

        if (isLive) {
            if(boxes.length > 0) setBoxColor(boxes[0], 0, isApiDanger || isAsapDanger || data.isDanger, (data.asap === "Waspada" || data.asap === "Sedang"));
            if(boxes.length > 1) setBoxColor(boxes[1], 1, isApiDanger || isAsapDanger || data.isDanger, (data.asap === "Waspada" || data.asap === "Sedang"));
        } else {
            if(boxes.length > 0) setBoxColor(boxes[0], 0, isApiDanger || isAsapDanger || (data.isDanger && dummyState === 0), (data.asap === "Waspada"));
            if(boxes.length > 1) setBoxColor(boxes[1], 1, isApiDanger || isAsapDanger || (data.isDanger && dummyState === 0), (data.asap === "Waspada"));
        }

        // Grup 2: Suhu & Kelembapan (Setelah 1 detik / Jeda 1000ms)
        setTimeout(() => {
            var suhuElem = document.getElementById("suhu");
            if (suhuElem && data.suhu !== undefined) {
                suhuElem.innerHTML = `${data.suhu} °C <i class="fas fa-thermometer-half"></i>`;
                currentSuhu = `${data.suhu} °C`;
                document.querySelectorAll('.loc-suhu-val').forEach(el => el.innerHTML = currentSuhu);
            }

            var kelembapanElem = document.getElementById("kelembapan");
            if (kelembapanElem && data.kelembapan !== undefined) kelembapanElem.innerHTML = `${data.kelembapan} % <i class="fas fa-tint"></i>`;

            if (isLive) {
                if(boxes.length > 2) setBoxColor(boxes[2], 2, false, isSuhuAbnormal || isKelembapanAbnormal);
                if(boxes.length > 3) setBoxColor(boxes[3], 3, false, isSuhuAbnormal || isKelembapanAbnormal);
            } else {
                if(boxes.length > 2) setBoxColor(boxes[2], 2, false, isSuhuAbnormal || isKelembapanAbnormal || (data.isWarning && dummyState === 2));
                if(boxes.length > 3) setBoxColor(boxes[3], 3, false, isSuhuAbnormal || isKelembapanAbnormal || (data.isWarning && dummyState === 2));
            }
        }, 1000);

        // Grup 3: Tegangan & Arus (Setelah 2 detik / Jeda 2000ms, jika ada)
        setTimeout(() => {
            var teganganElem = document.getElementById("tegangan");
            if (teganganElem && data.tegangan !== undefined) teganganElem.innerHTML = `${data.tegangan} V <i class="fas fa-bolt"></i>`;

            var arusElem = document.getElementById("arus");
            if (arusElem && data.arus !== undefined) arusElem.innerHTML = `${data.arus} A <i class="fas fa-charging-station"></i>`;

            if (isLive) {
                if(boxes.length > 4) setBoxColor(boxes[4], 4, false, isTeganganOver || isArusOver);
                if(boxes.length > 5) setBoxColor(boxes[5], 5, false, isTeganganOver || isArusOver);
            } else {
                if(boxes.length > 4) setBoxColor(boxes[4], 4, false, isTeganganOver || isArusOver || (data.isWarning && dummyState === 3));
                if(boxes.length > 5) setBoxColor(boxes[5], 5, false, isTeganganOver || isArusOver || (data.isWarning && dummyState === 3));
            }
        }, 2000);

        if (typeof updateLocationStatus === 'function') {
            updateLocationStatus(statusText, isDangerDetected);
        }
        
        // Update Chart Data Real Time (Suhu, Kelembapan, Status Asap, Status Api)
        if (data.waktu) {
            const lastLabel = dataChart.labels.length > 0 ? dataChart.labels[dataChart.labels.length - 1] : null;
            if (lastLabel !== data.waktu) {
                dataChart.labels.push(data.waktu);
                dataChart.datasets[0].data.push(parseFloat(data.suhu) || 0);
                dataChart.datasets[1].data.push(parseFloat(data.kelembapan) || 0);
                
                var numericAsap = 0;
                if (data.asap === "Tinggi" || data.asap === "Bahaya") numericAsap = 1;
                else if (data.asap === "Sedang" || data.asap === "Waspada") numericAsap = 0.5;
                else if (!isNaN(parseFloat(data.asap))) numericAsap = parseFloat(data.asap);
                dataChart.datasets[2].data.push(numericAsap);
                dataChart.datasets[3].data.push(data.api === "Terdeteksi Api" ? 1 : 0);

                if (dataChart.labels.length > 20) {
                    dataChart.labels.shift();
                    dataChart.datasets.forEach(ds => ds.data.shift());
                }
                myChart.update();
            }
        }
}

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

    const locationsToSearch = (currentLocationsData && currentLocationsData.length > 0) ? currentLocationsData : initialLocations;
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

// ================= FUNGSI MODAL HOME =================
function openHomeModal() {
    var modal = document.getElementById('homeModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeHomeModal() {
    var modal = document.getElementById('homeModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener('click', function(e) {
    var modal = document.getElementById('homeModal');
    if (modal && e.target === modal) {
        closeHomeModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeHomeModal();
    }
});

// Panggil pertama kali, lalu ulangi setiap 10 detik (10000 ms)
fetchDataFromDB();
setInterval(fetchDataFromDB, 10000);
</script>

<!-- ========== MODAL HOME ========== -->
<div class="modal-overlay" id="homeModal">
    <div class="modal-box">
        <div class="modal-icon-home">
            <i class="fas fa-home"></i>
        </div>
        
        <h2>Apakah Anda yakin ingin kembali ke Halaman Utama?</h2>
        
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="closeHomeModal()">
                <i class="fas fa-times"></i> CANCEL
            </button>
            <a href="home.php" class="btn-modal btn-home-confirm">
                <i class="fas fa-home"></i> YES
            </a>
        </div>
    </div>
</div>

</body>
</html>