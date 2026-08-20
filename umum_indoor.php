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

    // Ambil 20 data riwayat untuk Grafik Real Time Sensor (Urut terlama ke terbaru - Hanya Alat Asli)
    $q_chart = mysqli_query($conn, "SELECT * FROM (SELECT * FROM data_sensor WHERE (is_dummy = 0 OR is_dummy IS NULL) ORDER BY id DESC LIMIT 20) Var1 ORDER BY id ASC");
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

<!-- Custom CSS Umum Indoor -->
<link rel="stylesheet" href="css/umum_indoor.css">
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
</div><!-- Inisialisasi Data dari PHP & Script Frontend Terpisah -->
<script>
    window.INDOOR_UMUM_DATA = {
        defaultLat: <?= (float)$primary_loc['latitude']; ?>,
        defaultLng: <?= (float)$primary_loc['longitude']; ?>,
        primaryLocId: <?= (int)$primary_loc['id']; ?>,
        locations: <?= json_encode($db_locations); ?>,
        currentSuhu: "<?= htmlspecialchars($latest_sensor['suhu'] ?? '-') ?><?= (isset($latest_sensor['suhu']) && $latest_sensor['suhu'] !== '-') ? ' °C' : '' ?>",
        chartLabels: <?= json_encode($chart_labels); ?>,
        chartSuhu: <?= json_encode($chart_suhu); ?>,
        chartKelembapan: <?= json_encode($chart_kelembapan); ?>,
        chartAsap: <?= json_encode($chart_asap); ?>,
        chartApi: <?= json_encode($chart_api); ?>,
        batasSensor: <?= json_encode($batas_sensor); ?>
    };
</script>
<script src="js/umum_indoor.js"></script>

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