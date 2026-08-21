<?php
date_default_timezone_set('Asia/Makassar');
// Mulai session untuk user (simulasi login)
session_start();

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
    $q_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor WHERE is_dummy = 0 OR is_dummy IS NULL ORDER BY timestamp DESC LIMIT 1");
    if (!$q_sensor || mysqli_num_rows($q_sensor) == 0) {
        $q_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor WHERE is_dummy = 0 OR is_dummy IS NULL ORDER BY id DESC LIMIT 1");
    }
    if (!$q_sensor || mysqli_num_rows($q_sensor) == 0) {
        $q_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 1");
    }
    if (!$q_sensor || mysqli_num_rows($q_sensor) == 0) {
        $q_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
    }
    if ($q_sensor && mysqli_num_rows($q_sensor) > 0) {
        $s = mysqli_fetch_assoc($q_sensor);
        
        // Ambil setting interval lokasi utama jika ada
        $q_intv = @mysqli_query($conn, "SELECT interval_detik FROM lokasi_alat WHERE id = 1 LIMIT 1");
        $row_intv = $q_intv ? mysqli_fetch_assoc($q_intv) : null;
        $interval_setting = intval($row_intv['interval_detik'] ?? 30);
        $timeout_seconds = max(90, ($interval_setting * 3) + 30);

        // Cek selisih waktu data terakhir
        $time_str = $s['timestamp'] ?? ($s['tanggal_dan_waktu'] ?? ($s['created_at'] ?? null));
        $is_online = false;
        if ($time_str) {
            $last_time = strtotime($time_str);
            if ($last_time > 0 && (time() - $last_time) <= $timeout_seconds) {
                $is_online = true;
            }
        }

        $raw_asap = $s['asap'] ?? 'Normal';
        if (is_numeric($raw_asap)) {
            $f_asap = (float)$raw_asap;
            if ($f_asap > ($f_asap > 1 ? 50 : 0.5)) {
                $asap_val = "Tinggi";
            } else if ($f_asap > ($f_asap > 1 ? 25 : 0.25)) {
                $asap_val = "Sedang";
            } else {
                $asap_val = "Normal";
            }
        } else {
            $str_asap = trim((string)$raw_asap);
            if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) {
                $asap_val = "Tinggi";
            } else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) {
                $asap_val = "Sedang";
            } else {
                $asap_val = "Normal";
            }
        }

        $co_val = isset($s['co']) ? (float)$s['co'] : 0;
        $co_status = "Normal";
        if (is_numeric($s['co'] ?? null)) {
            if ($co_val > 50) {
                $co_status = "Tinggi";
            } else if ($co_val > 35) {
                $co_status = "Sedang";
            } else {
                $co_status = "Normal";
            }
        } else if (isset($s['co'])) {
            $str_co = trim((string)$s['co']);
            if (strcasecmp($str_co, 'Tinggi') === 0 || strcasecmp($str_co, 'Bahaya') === 0) {
                $co_status = "Tinggi";
            } else if (strcasecmp($str_co, 'Sedang') === 0 || strcasecmp($str_co, 'Waspada') === 0) {
                $co_status = "Sedang";
            } else {
                $co_status = "Normal";
            }
        }
        
        $raw_tegangan = isset($s['tegangan']) ? (float)$s['tegangan'] : 0.0;
        $raw_arus = isset($s['arus']) ? (float)$s['arus'] : 0.0;
        $raw_daya = isset($s['daya']) ? (float)$s['daya'] : 0.0;
        if ($raw_arus > 20) {
            $raw_arus = $raw_arus / 1000.0;
        }
        if ($raw_daya <= 0 || $raw_daya > 500) {
            $raw_daya = $raw_tegangan * $raw_arus;
        }

        $latest_sensor = [
            'waktu' => $time_str ? date('H:i:s', strtotime($time_str)) : date('H:i:s'),
            'tegangan' => number_format($raw_tegangan, 1),
            'arus' => number_format($raw_arus, 2),
            'daya' => number_format($raw_daya, 1),
            'arah' => !empty($s['arah_angin']) ? $s['arah_angin'] : "Utara",
            'angin' => isset($s['kecepatan_angin']) ? number_format((float)$s['kecepatan_angin'], 1) : "0.0",
            'asap' => $asap_val,
            'suhu' => isset($s['suhu']) ? number_format((float)$s['suhu'], 1) : "0.0",
            'kelembapan' => isset($s['kelembapan']) ? number_format((float)$s['kelembapan'], 1) : "0.0",
            'co' => $co_val,
            'co_status' => $co_status,
            'rssi' => isset($s['rssi']) ? $s['rssi'] : "-",
            'ip' => !empty($s['ip_address']) ? $s['ip_address'] : "-",
            'status' => $is_online ? 'Online' : 'Offline',
            'is_dummy' => (int)($s['is_dummy'] ?? 0)
        ];
    }
}

// Ambil info kapasitas storage / kuota data outdoor
$outdoor_storage = get_sensor_storage_info($conn ?: $pdo_outdoor, 'outdoor');
$storage_bytes = ($outdoor_storage['real_bytes'] ?? 0) + ($outdoor_storage['dummy_bytes'] ?? 0);
$kuota_data_formatted = format_storage_size($storage_bytes);

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
            $chart_labels[] = date('d/m/Y H:i:s', strtotime($row['timestamp']));
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

// Proteksi: Hanya admin outdoor yang bisa mengakses halaman ini
$is_admin_outdoor = (isset($_SESSION['login_outdoor']) && $_SESSION['login_outdoor'] === true && isset($_SESSION['outdoor_role']) && $_SESSION['outdoor_role'] === 'admin');
if (!$is_admin_outdoor) {
    header("Location: login.php?redirect=outdoor");
    exit();
}
$_SESSION['dashboard_type'] = 'outdoor';

$user = isset($_SESSION['outdoor_username']) ? $_SESSION['outdoor_username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "Admin");
$role = isset($_SESSION['outdoor_role']) ? $_SESSION['outdoor_role'] : "admin";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Fire Detection</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Dashboard Admin Outdoor Custom CSS -->
<link rel="stylesheet" href="css/dashboard_admin.css">
</head>
<body>

<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireDetector</h3>
    <a href="dashboard_admin.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
        <span class="admin-badge">ADMIN</span>
    </a>
    <a href="chart.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
    <a href="setting.php" class="menu-btn">
        <i class="fas fa-cog"></i>
        <span>SETTING</span>
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
                <div class="status-item-header">
                    <i class="fas fa-hdd" style="color: #00b4db;"></i>
                    <span>Kuota Data:</span>
                    <span class="value" id="kuota-data"><?= htmlspecialchars($kuota_data_formatted ?? '0 B') ?></span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-database"></i>
                    <span>Data:</span>
                    <span id="header-data-type-tag" class="data-type-badge <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'dummy-badge' : 'realtime-badge' ?>">
                        <i class="fas <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'fa-flask' : 'fa-satellite-dish' ?>"></i> <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'Data Dummy' : 'Data Real Time' ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="header-right">
            <!-- PERBAIKAN: Tombol HOME dengan Modal -->
            <a href="#" class="btn-home-header" onclick="openHomeModal(); return false;"><i class="fas fa-home"></i> HOME</a>
            <button type="button" class="btn-delete-dummy" onclick="deleteDummyData()"><i class="fas fa-trash-alt"></i> Hapus Dummy</button>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($user) ?><span class="admin-tag">Admin</span></span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== BANNER PERINGATAN H-1 PENGHAPUSAN OTOMATIS ========== -->
    <!-- ============================================================ -->
    <div id="auto-clean-warning-banner" class="auto-clean-banner" style="display: none;">
        <div class="banner-left">
            <i class="fas fa-exclamation-triangle banner-warning-icon"></i>
            <div>
                <strong style="font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px;">⚠️ PERHATIAN: PENGHAPUSAN OTOMATIS BESOK!</strong>
                <p id="auto-clean-warning-msg" style="margin: 3px 0 0 0; font-size: 13px; opacity: 0.95; line-height: 1.4;">
                    Ada data sensor alat utama yang usianya hampir mencapai batas 30 hari dan akan dihapus otomatis besok malam. Silakan klik tombol di bawah ini jika Anda perlu mengamankan rekap bulanan.
                </p>
            </div>
        </div>
        <div class="banner-right">
            <a href="export_backup.php?device=outdoor" class="btn-banner-export">
                <i class="fas fa-file-excel"></i> Export Data Bulan Lalu ke Excel
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 2. DATA SENSOR ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3>
            <i class="fas fa-solar-panel"></i> Data Sensor 
            <span id="data-type-tag" class="data-type-badge <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'dummy-badge' : 'realtime-badge' ?>">
                <i class="fas <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'fa-flask' : 'fa-satellite-dish' ?>"></i> <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'Data Dummy' : 'Data Real Time' ?>
            </span>
            <span id="waktu" style="font-size:12px; color:#666; margin-left: auto;"><i class="far fa-clock"></i> <?= htmlspecialchars($latest_sensor['waktu']) ?></span>
        </h3>
        <div class="grid">
            <!-- Solar Panel Sensors -->
            <div class="box solar-box"><i class="fas fa-bolt"></i><div class="sensor-label">Tegangan Panel Surya</div><b id="tegangan"><?= htmlspecialchars($latest_sensor['tegangan']) ?> V</b><small>V DC</small></div>
            <div class="box solar-box"><i class="fas fa-charging-station"></i><div class="sensor-label">Arus Panel Surya</div><b id="arus"><?= htmlspecialchars($latest_sensor['arus']) ?> A</b><small>A DC</small></div>
            <div class="box solar-box"><i class="fas fa-solar-panel"></i><div class="sensor-label">Daya Panel Surya</div><b id="daya"><?= htmlspecialchars($latest_sensor['daya']) ?> W</b><small>Watt</small></div>
            
            <!-- Wind Sensors -->
            <div class="box angin-box"><i class="fas fa-compass"></i><div class="sensor-label">Arah Angin</div><b id="arah"><i class="fas fa-arrow-right"></i> <?= htmlspecialchars($latest_sensor['arah']) ?></b></div>
            <div class="box angin-box"><i class="fas fa-wind"></i><div class="sensor-label">Kecepatan Angin</div><b id="kecepatan_angin"><?= htmlspecialchars($latest_sensor['angin']) ?> m/s <i class="fas fa-wind"></i></b></div>
            
            <!-- Asap Sensor -->
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
            
            <!-- Environment Sensors -->
            <div class="box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Suhu</div><b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?> °C <i class="fas fa-thermometer-half"></i></b></div>
            <div class="box"><i class="fas fa-tint"></i><div class="sensor-label">Kelembapan</div><b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?> % <i class="fas fa-tint"></i></b></div>
            
            <!-- Gas Sensor -->
            <?php 
                $co_val = (float)($latest_sensor['co'] ?? 0);
                $co_str = (string)($latest_sensor['co'] ?? '');
                $co_bg = 'background: linear-gradient(135deg, rgba(40,167,69,0.95), rgba(32,201,151,0.95));';
                $co_icon = '<i class="fas fa-check-circle"></i> ' . htmlspecialchars($co_val) . ' ppm (Aman)';
                $co_pulse = '';
                if ($co_val > 50 || $co_str === 'Tinggi' || $co_str === 'Bahaya') {
                    $co_bg = 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));';
                    $co_icon = '<i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($co_val) . ' ppm (Bahaya)';
                    $co_pulse = 'pulse-animation';
                } else if ($co_val > 25 || $co_str === 'Sedang' || $co_str === 'Waspada') {
                    $co_bg = 'background: linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95));';
                    $co_icon = '<i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($co_val) . ' ppm (Waspada)';
                }
            ?>
            <div class="box co-box <?= $co_pulse ?>" id="co-box" style="<?= $co_bg ?>">
                <i class="fas fa-industry"></i>
                <div class="sensor-label">Gas CO</div>
                <b id="co"><?= $co_icon ?></b>
            </div>
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
        <h3>
            <i class="fas fa-chart-line"></i> Grafik Sensor 
            <span id="chart-data-type-tag" class="data-type-badge <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'dummy-badge' : 'realtime-badge' ?>">
                <i class="fas <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'fa-flask' : 'fa-satellite-dish' ?>"></i> <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'Data Dummy' : 'Data Real Time' ?>
            </span>
        </h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 4. MAPS / LOKASI ========== -->
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
            <a href="logout.php?redirect=outdoor" class="btn-modal btn-logout-confirm">
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
// Konfigurasi data dinamis dari PHP ke JavaScript
window.FIRENET_CONFIG = {
    fixedLat: <?= json_encode((float)$db_lat); ?>,
    fixedLng: <?= json_encode((float)$db_lng); ?>,
    allLocations: <?= json_encode($all_locations); ?>,
    currentSuhu: <?= json_encode(($latest_sensor['suhu'] ?? '-') . ((isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '')); ?>,
    initialRealChartData: {
        labels: <?= json_encode($chart_labels); ?>,
        tegangan: <?= json_encode($chart_tegangan); ?>,
        arus: <?= json_encode($chart_arus); ?>,
        daya: <?= json_encode($chart_daya); ?>,
        suhu: <?= json_encode($chart_suhu); ?>,
        kelembapan: <?= json_encode($chart_kelembapan); ?>,
        angin: <?= json_encode($chart_angin); ?>,
        co: <?= json_encode($chart_co); ?>
    }
};
</script>
<!-- External JavaScript Logic -->
<script src="js/dashboard_admin.js"></script>
</body>
</html>