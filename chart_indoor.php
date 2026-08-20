<?php
date_default_timezone_set('Asia/Makassar');
session_start();

// Proteksi: Hanya user indoor yang bisa mengakses halaman ini
if (!isset($_SESSION['login_indoor']) || $_SESSION['login_indoor'] !== true) {
    header("Location: login.php?redirect=indoor");
    exit();
}
$_SESSION['dashboard_type'] = 'indoor';

$user = isset($_SESSION['indoor_username']) ? $_SESSION['indoor_username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "User");
$role = isset($_SESSION['indoor_role']) ? $_SESSION['indoor_role'] : "user";

// Koneksi database
require_once 'koneksi.php';

// Ambil struktur tabel data_sensor
if (!$pdo_indoor) {
    die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 6px; margin: 20px;'>
        <h3>Error: Koneksi ke Database INDOOR ('indoor') Gagal.</h3>
        <p>Pastikan Anda telah mengaktifkan MySQL di XAMPP Control Panel, membuat database <strong>indoor</strong> di phpMyAdmin, dan mengimpor tabel-tabel yang diperlukan.</p>
    </div>");
}

// --- TAMBAHAN BARU: Ambil daftar lokasi dari database ---
$db_locations = [];
try {
    $stmt_loc = $pdo_indoor->query("SELECT * FROM lokasi_monitoring ORDER BY id ASC");
    $db_locations = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Abaikan jika tabel belum dibuat
}
// ---------------------------------------------------------

$columns = [];
$rows = [];

try {
    $colQuery = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor");
    while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }

    // Tentukan kolom tanggal/waktu
    $dateColumn = null;
    $possibleDate = ['tanggal_dan_waktu', 'timestamp', 'created_at', 'tanggal', 'waktu', 'datetime'];
    foreach ($possibleDate as $col) {
        if (in_array($col, $columns)) {
            $dateColumn = $col;
            break;
        }
    }
    if (!$dateColumn && !empty($columns)) {
        $dateColumn = $columns[0];
    }

    // Bangun query dengan sensor
    $selectFields = ['id'];
    if ($dateColumn) $selectFields[] = "$dateColumn as waktu";
    else $selectFields[] = "'' as waktu";

    // Daftar sensor yang ditampilkan - SESUAI DENGAN indoor.sql (TANPA DAYA)
    $sensorFields = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'api'];
    foreach ($sensorFields as $sf) {
        if (in_array($sf, $columns)) $selectFields[] = $sf;
        else $selectFields[] = "0 as $sf";
    }

    $dummyFilter = in_array('is_dummy', $columns) ? " WHERE (is_dummy = 0 OR is_dummy IS NULL)" : "";
    $query = "SELECT " . implode(", ", $selectFields) . " FROM data_sensor" . $dummyFilter;

    // Ambil 200 data riwayat terbaru dari database untuk tampilan rapi, jelas, dan tanpa lag
    if ($dateColumn) {
        $query .= " ORDER BY $dateColumn DESC LIMIT 200";
    } else {
        $query .= " ORDER BY id DESC LIMIT 200";
    }

    $stmt = $pdo_indoor->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Balik urutan datanya agar kembali maju (kronologis) dari kiri ke kanan di chart
    $rows = array_reverse($rows);
} catch(Exception $e) {
    // Jika tabel tidak ada, buat tabel baru sesuai struktur indoor.sql
    $create = "CREATE TABLE IF NOT EXISTS data_sensor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal_dan_waktu DATETIME NOT NULL,
        asap FLOAT DEFAULT 0,
        suhu FLOAT DEFAULT 0,
        kelembapan FLOAT DEFAULT 0,
        tegangan FLOAT DEFAULT 0,
        arus FLOAT DEFAULT 0,
        api FLOAT DEFAULT 0,
        ip_address VARCHAR(45) DEFAULT NULL,
        rssi INT DEFAULT NULL,
        latitude DECIMAL(10,8) DEFAULT NULL,
        longitude DECIMAL(11,8) DEFAULT NULL
    )";
    $pdo_indoor->exec($create);
    
    // Ambil ulang kolom setelah tabel dibuat
    try {
        $colQuery = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor");
        while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['Field'];
        }
    } catch(Exception $ex) {
        // Abaikan jika gagal
    }
}

// ============================================================
// KONVERSI DATA KE FORMAT NUMERIK UNTUK GRAFIK (TANPA DAYA)
// ============================================================
$chartData = [];
foreach ($rows as $row) {
    $timestamp = isset($row['waktu']) ? $row['waktu'] : '';

    // Normalisasi nilai sensor asap
    $rawAsap = isset($row['asap']) ? $row['asap'] : 0;
    if (is_numeric($rawAsap)) {
        $asapVal = (float)$rawAsap;
    } else {
        $strAsap = trim((string)$rawAsap);
        if (strcasecmp($strAsap, 'Tinggi') === 0 || strcasecmp($strAsap, 'Bahaya') === 0) $asapVal = 100;
        else if (strcasecmp($strAsap, 'Sedang') === 0 || strcasecmp($strAsap, 'Waspada') === 0) $asapVal = 50;
        else $asapVal = 0;
    }

    // Normalisasi nilai sensor api (skala 0 - 100 agar lonjakan terlihat jelas)
    $rawApi = isset($row['api']) ? $row['api'] : 0;
    $strApi = isset($row['api']) ? trim(strtolower((string)$row['api'])) : '';
    if ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'tinggi' || (is_numeric($rawApi) && (float)$rawApi >= 1)) {
        $apiVal = 100;
    } else {
        $apiVal = (is_numeric($rawApi) && (float)$rawApi > 0 && (float)$rawApi < 1) ? (float)$rawApi * 100 : 0;
    }

    // Ambil nilai tegangan dan arus
    $teganganVal = isset($row['tegangan']) ? floatval($row['tegangan']) : 0;
    $arusVal = isset($row['arus']) ? floatval($row['arus']) : 0;

    $chartData[] = [
        'waktu' => $timestamp,
        'asap' => $asapVal,
        'suhu' => isset($row['suhu']) ? floatval($row['suhu']) : 0,
        'kelembapan' => isset($row['kelembapan']) ? floatval($row['kelembapan']) : 0,
        'tegangan' => $teganganVal,
        'arus' => $arusVal,
        'api' => $apiVal
    ];
}
$jsonData = json_encode($chartData);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chart Monitoring - FIREDETECTOR</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Custom CSS Chart Indoor -->
<link rel="stylesheet" href="css/chart_indoor.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireDetector</h3>
    <a href="<?php echo ($role == 'admin') ? 'dashboard_admin_indoor.php' : 'dashboard_user_indoor.php'; ?>" class="menu-btn">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>
    <a href="chart_indoor.php" class="menu-btn active">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel_indoor.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
    <?php if ($role == 'admin'): ?>
    <a href="setting_indoor.php" class="menu-btn">
        <i class="fas fa-cog"></i>
        <span>SETTING</span>
    </a>
    <?php endif; ?>
    <!-- Tombol Logout dengan onclick untuk membuka modal -->
    <button class="menu-btn logout" onclick="openLogoutModal()">
        <i class="fas fa-sign-out-alt"></i>
        <span>LOGOUT</span>
    </button>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="header">
        <h2>
            <i class="fas fa-chart-line"></i> Chart Monitoring Sensor Indoor
            <span id="chart-badge" style="font-size: 13px; padding: 5px 12px; border-radius: 20px; font-weight: bold; margin-left: 15px; color: white; background: #28a745; transition: all 0.3s;">
                <i class="fas fa-bolt"></i> Live (Real-Time)
            </span>
        </h2>
        <div class="header-right">
            <!-- Tombol HOME dengan onclick untuk membuka modal -->
            <button class="btn-home-header" onclick="openHomeModal()">
                <i class="fas fa-home"></i> HOME
            </button>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Halo, <?= htmlspecialchars($user) ?></span>
            </div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">

            <label><i class="fas fa-map-marker-alt"></i> Lokasi Alat:</label>
            <select id="locationSelect" onchange="filterData()" style="padding: 8px 12px; border-radius: 5px; border: 1px solid #ccc; font-weight: bold; cursor: pointer;">
                <?php foreach ($db_locations as $loc):
                    $idAlat = !empty($loc['id_alat']) ? $loc['id_alat'] : "LOK-".$loc['id'];
                    $namaLokasi = !empty($loc['nama_lokasi']) ? $loc['nama_lokasi'] : $idAlat;
                    $isLive = (strtoupper($idAlat) === 'LOK-002' || $loc['id'] == 2);
                    $labelStatus = $isLive ? "(Alat Utama / Live)" : "(Dummy)";
                ?>
                    <option value="<?= htmlspecialchars($idAlat) ?>">
                        <?= htmlspecialchars($idAlat) ?> - <?= htmlspecialchars($namaLokasi) ?> <?= $labelStatus ?>
                    </option>
                <?php endforeach; ?>
                <?php if (empty($db_locations)): ?>
                    <option value="LOK-002">LOK-002 (Alat Utama / Live)</option>
                    <option value="LOK-001">LOK-001 (Dummy)</option>
                <?php endif; ?>
            </select>

            <label style="margin-left: 15px;"><i class="fas fa-calendar-alt"></i> Dari:</label>
            <input type="date" id="dateFrom">
            <label>Sampai:</label>
            <input type="date" id="dateTo">
            <button id="btnFilter" onclick="filterData()">
                <i class="fas fa-search"></i> Tampilkan
            </button>
            <button onclick="resetFilter()" style="background: #6c757d;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </div>

    <!-- TAB BUTTONS -->
    <div class="sensor-tabs">
        <button class="tab-btn active" data-mode="all" onclick="setMode('all', this)">Semua Sensor</button>
        <button class="tab-btn" data-mode="bahaya" onclick="setMode('bahaya', this)">Api & Asap</button>
        <button class="tab-btn" data-mode="env" onclick="setMode('env', this)">Suhu & Kelembapan</button>
        <button class="tab-btn" data-mode="listrik" onclick="setMode('listrik', this)">Tegangan & Arus</button>
    </div>

    <div class="chart-card">
        <div class="chart-container">
            <canvas id="myChart"></canvas>
        </div>
        <div class="chart-legend" id="chartLegend"></div>
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

<!-- Inisialisasi Data dari PHP & Script Frontend Terpisah -->
<script>
    window.INDOOR_CHART_DATA = <?php echo $jsonData; ?>;
</script>
<script src="js/chart_indoor.js"></script>

</body>
</html>