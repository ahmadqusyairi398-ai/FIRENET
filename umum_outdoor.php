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
            'waktu' => date('H:i:s'),
            'tegangan' => number_format($raw_tegangan, 1),
            'arus' => number_format($raw_arus, 2),
            'daya' => number_format($raw_daya, 1),
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

// Ambil info kapasitas storage / kuota data outdoor
$outdoor_storage = get_sensor_storage_info($conn ?: $pdo_outdoor, 'outdoor');
$storage_bytes = ($outdoor_storage['real_bytes'] ?? 0) + ($outdoor_storage['dummy_bytes'] ?? 0);
$kuota_data_formatted = format_storage_size($storage_bytes);

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

<!-- Dashboard Umum Outdoor Custom CSS -->
<link rel="stylesheet" href="css/umum_outdoor.css">
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
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>User</span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 4 SENSOR UTAMA ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3>
            <i class="fas fa-microchip"></i> Data Sensor 
            <span id="data-type-tag" class="data-type-badge <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'dummy-badge' : 'realtime-badge' ?>">
                <i class="fas <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'fa-flask' : 'fa-satellite-dish' ?>"></i> <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'Data Dummy' : 'Data Real Time' ?>
            </span>
            <span id="waktu" style="font-size:12px; color:#666; margin-left: auto;"><i class="far fa-clock"></i> <?= htmlspecialchars($latest_sensor['waktu']) ?></span>
        </h3>
        <div class="grid">
            <!-- Sensor Daya Panel Surya -->
            <div class="box solar-box">
                <i class="fas fa-solar-panel"></i>
                <div class="sensor-label">Daya Panel Surya</div>
                <b id="daya"><?= htmlspecialchars($latest_sensor['daya']) ?> W</b>
                <small>Watt</small>
            </div>
            
            <!-- Sensor Suhu -->
            <div class="box">
                <i class="fas fa-temperature-high"></i>
                <div class="sensor-label">Suhu</div>
                <b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?> °C <i class="fas fa-thermometer-half"></i></b>
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
            <div class="box">
                <i class="fas fa-tint"></i>
                <div class="sensor-label">Kelembapan</div>
                <b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?> % <i class="fas fa-tint"></i></b>
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
        <h3>
            <i class="fas fa-chart-line"></i> Grafik Sensor 
            <span id="chart-data-type-tag" class="data-type-badge <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'dummy-badge' : 'realtime-badge' ?>">
                <i class="fas <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'fa-flask' : 'fa-satellite-dish' ?>"></i> <?= (($latest_sensor['is_dummy'] ?? 0) == 1) ? 'Data Dummy' : 'Data Real Time' ?>
            </span>
        </h3>
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
// Konfigurasi data dinamis dari PHP ke JavaScript
window.FIRENET_CONFIG = {
    fixedLat: <?= json_encode((float)($db_lat ?? -1.20249)); ?>,
    fixedLng: <?= json_encode((float)($db_lng ?? 116.88708)); ?>,
    allLocations: <?= json_encode($all_locations); ?>,
    currentSuhu: <?= json_encode(($latest_sensor['suhu'] ?? '-') . ((isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '')); ?>,
    initialRealChartData: {
        labels: <?= json_encode($chart_labels); ?>,
        daya: <?= json_encode($chart_daya); ?>,
        suhu: <?= json_encode($chart_suhu); ?>,
        kelembapan: <?= json_encode($chart_kelembapan); ?>,
        asap: <?= json_encode($chart_asap); ?>
    }
};
</script>
<!-- External JavaScript Logic -->
<script src="js/umum_outdoor.js"></script>
</body>
</html>