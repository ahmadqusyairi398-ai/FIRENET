<?php
// Mulai session untuk user (simulasi login)
session_start();

// Proteksi: Hanya admin indoor yang bisa mengakses halaman ini
$is_admin_indoor = (isset($_SESSION['login_indoor']) && $_SESSION['login_indoor'] === true && isset($_SESSION['indoor_role']) && $_SESSION['indoor_role'] === 'admin');
if (!$is_admin_indoor) {
    header("Location: login.php?redirect=indoor");
    exit();
}
$_SESSION['dashboard_type'] = 'indoor';

$user = isset($_SESSION['indoor_username']) ? $_SESSION['indoor_username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "Admin");
$role = isset($_SESSION['indoor_role']) ? $_SESSION['indoor_role'] : "admin";

// Tentukan tipe dashboard (selalu indoor untuk berkas ini)
$dashboard_type = 'indoor';

// Koneksi Database & Query Lokasi Monitoring serta Data Sensor
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

// Fallback jika lokasi_monitoring kosong atau belum ada data
if (empty($db_locations)) {
    $db_locations = [
        [
            'id' => 1,
            'id_alat' => 'LOK-001',
            'nama_lokasi' => 'Gedung Elektro Poltekba',
            'latitude' => -1.202490,
            'longitude' => 116.887080,
            'last_update' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'id_alat' => 'LOK-002',
            'nama_lokasi' => 'Ruang Server Gedung Elektro Poltekba',
            'latitude' => -1.203100,
            'longitude' => 116.887500,
            'last_update' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 3,
            'id_alat' => 'LOK-003',
            'nama_lokasi' => 'Lab Komputer Lt. 2 Gedung Elektro',
            'latitude' => -1.201800,
            'longitude' => 116.886400,
            'last_update' => date('Y-m-d H:i:s')
        ]
    ];
}

// Tentukan lokasi awal yang difokuskan ke LOK-002 (Alat Utama / Ruang Server Gedung Elektro Poltekba)
$primary_loc = null;
foreach ($db_locations as $loc) {
    if (!empty($loc['id_alat']) && (strtoupper($loc['id_alat']) === 'LOK-002' || $loc['id'] == 2)) {
        $primary_loc = $loc;
        break;
    }
}
if (!$primary_loc) {
    foreach ($db_locations as $loc) {
        if (!empty($loc['nama_lokasi']) && (stripos($loc['nama_lokasi'], 'elektro') !== false || stripos($loc['nama_lokasi'], 'poltekba') !== false)) {
            $primary_loc = $loc;
            break;
        }
    }
}
if (!$primary_loc && !empty($db_locations)) {
    $primary_loc = $db_locations[0];
}
if (!$primary_loc) {
    $primary_loc = [
        'id' => 2,
        'id_alat' => 'LOK-002',
        'nama_lokasi' => 'Ruang Server Gedung Elektro Poltekba',
        'latitude' => -1.203100,
        'longitude' => 116.887500,
        'last_update' => date('Y-m-d H:i:s')
    ];
}

// 2. Query Data Sensor terbaru & 20 data riwayat untuk Grafik Real Time dari database indoor (tabel data_sensor)
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
        // Ambil 1 data sensor terbaru (Hanya Data Asli)
        $q_latest = mysqli_query($conn, "SELECT * FROM data_sensor WHERE (is_dummy = 0 OR is_dummy IS NULL) ORDER BY id DESC LIMIT 1");
        if ($q_latest && mysqli_num_rows($q_latest) > 0) {
            $s = mysqli_fetch_assoc($q_latest);
            $apiVal = isset($s['api']) ? (float)$s['api'] : 0;
            $raw_asap = isset($s['asap']) ? $s['asap'] : 0;
            
            $str_api = isset($s['api']) ? trim(strtolower((string)$s['api'])) : '';
            if ($str_api === 'terdeteksi api' || $str_api === 'dekat' || $str_api === 'sedang' || $str_api === 'tinggi' || $apiVal > 0.5) {
                $apiStatus = "Terdeteksi Api";
            } else {
                $apiStatus = "Aman";
            }
            
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
                'arus' => isset($s['arus']) ? number_format((float)$s['arus'], 2) : "-",
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
<title>Dashboard Admin Indoor - Fire Detection</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Custom CSS Dashboard Admin Indoor -->
<link rel="stylesheet" href="css/dashboard_admin_indoor.css">
</head>
<body>

<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireDetector</h3>
    <a href="dashboard_admin_indoor.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
        <span class="admin-badge">ADMIN</span>
    </a>
    <a href="chart_indoor.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel_indoor.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
    <a href="setting_indoor.php" class="menu-btn">
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
            <h2>
                <i class="fas fa-building"></i> Dashboard Monitoring Indoor
            </h2>
            
            <!-- Status Node di dalam Header -->
            <div class="node-status-header">
                <div class="status-item-header">
                    <i class="fas fa-circle <?= $latest_sensor['status'] === 'Online' ? 'status-online' : '' ?>" style="<?= $latest_sensor['status'] !== 'Online' ? 'color:#dc3545;' : '' ?>"></i>
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
            <!-- Tombol HOME dengan onclick untuk membuka modal -->
            <button class="btn-home-header" onclick="openHomeModal()">
                <i class="fas fa-home"></i> HOME
            </button>
            <button type="button" class="btn-delete-dummy" onclick="deleteDummyData()"><i class="fas fa-trash-alt"></i> Hapus Dummy</button>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($user) ?><span class="admin-tag">Admin</span></span>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- BANNER PERINGATAN H-1 (Disembunyikan secara default) -->
    <!-- ============================================================ -->
    <div id="auto-clean-banner" style="display: none; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 28px;"></i>
            <div>
                <strong style="display: block; font-size: 16px;">⚠️ Peringatan: Penghapusan Data Otomatis!</strong>
                <span style="font-size: 14px;" id="auto-clean-text">Terdapat data yang sudah berusia 6 hari dan akan dihapus otomatis besok.</span>
            </div>
        </div>
        <div>
            <a href="tabel_indoor.php" style="background: white; color: #d97706; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; margin-right: 10px;"><i class="fas fa-file-excel"></i> Export Sekarang</a>
            <button onclick="document.getElementById('auto-clean-banner').style.display='none'" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px 12px; border-radius: 5px; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 2. DATA SENSOR ========== -->
    <!-- ============================================================ -->
    <div class="card">
        <h3>
            <i class="fas fa-building"></i> Data Sensor Real Time (Indoor)
            <span id="waktu" style="font-size:12px; color:#666;"><i class="far fa-clock"></i> <?= htmlspecialchars($latest_sensor['waktu']) ?></span>
        </h3>
        <div class="grid">
            <!-- Indoor Sensors -->
            <div class="box <?= $latest_sensor['api'] === 'Terdeteksi Api' ? 'pulse-animation' : '' ?>" id="api-box" style="<?= $latest_sensor['api'] === 'Terdeteksi Api' ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : '' ?>"><i class="fas fa-fire"></i><div class="sensor-label">Sensor Api</div><b id="api"><?= $latest_sensor['api'] === 'Terdeteksi Api' ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman' ?></b></div>
            <div class="box <?= $latest_sensor['asap'] === 'Tinggi' ? 'pulse-animation' : '' ?>" id="asap-box" style="<?= $latest_sensor['asap'] === 'Tinggi' ? 'background: linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95));' : ($latest_sensor['asap'] === 'Sedang' ? 'background: linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95));' : '') ?>"><i class="fas fa-smog"></i><div class="sensor-label">Sensor Asap</div><b id="asap"><?= $latest_sensor['asap'] === 'Tinggi' ? '<i class="fas fa-chart-line"></i> Tinggi (Berbahaya)' : ($latest_sensor['asap'] === 'Sedang' ? '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)' : '<i class="fas fa-check"></i> Normal') ?></b></div>
            <div class="box" id="suhu-box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Suhu</div><b id="suhu"><?= htmlspecialchars($latest_sensor['suhu']) ?><?= $latest_sensor['suhu'] !== '-' ? ' °C' : '' ?> <i class="fas fa-thermometer-half"></i></b></div>
            <div class="box" id="kelembapan-box"><i class="fas fa-tint"></i><div class="sensor-label">Kelembapan</div><b id="kelembapan"><?= htmlspecialchars($latest_sensor['kelembapan']) ?><?= $latest_sensor['kelembapan'] !== '-' ? ' %' : '' ?> <i class="fas fa-tint"></i></b></div>
            <div class="box" id="tegangan-box"><i class="fas fa-bolt"></i><div class="sensor-label">Tegangan Listrik</div><b id="tegangan"><?= htmlspecialchars($latest_sensor['tegangan']) ?><?= $latest_sensor['tegangan'] !== '-' ? ' V' : '' ?> <i class="fas fa-bolt"></i></b><small>V AC</small></div>
            <div class="box" id="arus-box"><i class="fas fa-charging-station"></i><div class="sensor-label">Arus Listrik</div><b id="arus"><?= htmlspecialchars($latest_sensor['arus']) ?><?= $latest_sensor['arus'] !== '-' ? ' A' : '' ?> <i class="fas fa-charging-station"></i></b><small>A</small></div>
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
        <h3>
            <i class="fas fa-chart-line"></i> Grafik Real Time Sensor
            <span id="chart-badge" style="font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: bold; margin-left: 10px; color: white; transition: all 0.3s;"></span>
        </h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== 4. MAPS / LOKASI ========== -->
    <!-- ============================================================ -->
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

<!-- ============================================================ -->
<!-- ========== MODAL LOGOUT ========== -->
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
            <a href="logout.php?redirect=indoor" class="btn-modal btn-logout-confirm">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
            </a>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- ========== MODAL HOME ========== -->
<!-- ============================================================ -->
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

<script>
    window.INDOOR_ADMIN_DATA = {
        defaultLat: <?= (float)$primary_loc['latitude']; ?>,
        defaultLng: <?= (float)$primary_loc['longitude']; ?>,
        primaryLocId: <?= (int)$primary_loc['id']; ?>,
        locations: <?= json_encode($db_locations); ?>,
        currentSuhu: "<?= htmlspecialchars($latest_sensor['suhu'] ?? '-') ?><?= (isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '' ?>",
        chartLabels: <?= json_encode($chart_labels); ?>,
        chartApi: <?= json_encode($chart_api); ?>,
        chartAsap: <?= json_encode($chart_asap); ?>,
        chartSuhu: <?= json_encode($chart_suhu); ?>,
        chartKelembapan: <?= json_encode($chart_kelembapan); ?>,
        chartTegangan: <?= json_encode($chart_tegangan); ?>,
        chartArus: <?= json_encode($chart_arus); ?>
    };
</script>
<script src="js/dashboard_admin_indoor.js"></script>
</body>
</html>