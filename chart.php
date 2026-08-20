<?php
date_default_timezone_set('Asia/Makassar');
session_start();

// Jika tipe dashboard adalah indoor, alihkan ke chart_indoor.php
if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'indoor') {
    header("Location: chart_indoor.php");
    exit();
}
$_SESSION['dashboard_type'] = 'outdoor';

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// Koneksi database
require_once 'koneksi.php';
$rows = [];

// Ambil lokasi alat dari database outdoor
$all_locations = [];
if (isset($conn_outdoor) && $conn_outdoor) {
    $q_loc = @mysqli_query($conn_outdoor, "SELECT id, nama_lokasi, id_alat FROM lokasi_alat ORDER BY id ASC");
    if ($q_loc && mysqli_num_rows($q_loc) > 0) {
        while ($row = mysqli_fetch_assoc($q_loc)) {
            $all_locations[] = $row;
        }
    }
}
if (empty($all_locations)) {
    $all_locations = [
        ['id' => 1, 'nama_lokasi' => 'Politeknik Negeri Balikpapan', 'id_alat' => 'OUT-001'],
        ['id' => 2, 'nama_lokasi' => 'Area Hutan Sektor A', 'id_alat' => 'OUT-002'],
        ['id' => 3, 'nama_lokasi' => 'Area Hutan Sektor B', 'id_alat' => 'OUT-003'],
        ['id' => 4, 'nama_lokasi' => 'Bukit Rawan Kebakaran', 'id_alat' => 'OUT-004'],
        ['id' => 5, 'nama_lokasi' => 'Pos Pantau Karhutla 1', 'id_alat' => 'OUT-005'],
        ['id' => 6, 'nama_lokasi' => 'Kawasan Hutan Lindung', 'id_alat' => 'OUT-006'],
        ['id' => 7, 'nama_lokasi' => 'Zona Merah Perkebunan', 'id_alat' => 'OUT-007']
    ];
}

try {
    $colQuery = $pdo->query("SHOW COLUMNS FROM data_sensor");
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

    // Bangun query dengan sensor baru
    $selectFields = ['id'];
    if ($dateColumn) $selectFields[] = "$dateColumn as waktu";
    else $selectFields[] = "'' as waktu";

    // Daftar sensor yang ditampilkan
    $sensorFields = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'daya', 'kecepatan_angin', 'arah_angin', 'co'];
    foreach ($sensorFields as $sf) {
        if (in_array($sf, $columns)) $selectFields[] = $sf;
        else $selectFields[] = "'' as $sf";
    }

    $query = "SELECT " . implode(", ", $selectFields) . " FROM data_sensor";
    if ($dateColumn) $query .= " ORDER BY $dateColumn ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Jika tabel tidak ada atau bermasalah, buat tabel baru
    $create = "CREATE TABLE IF NOT EXISTS data_sensor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal_dan_waktu DATETIME NOT NULL,
        asap VARCHAR(20) DEFAULT 'Normal',
        suhu DECIMAL(5,2) DEFAULT 0,
        kelembapan DECIMAL(5,2) DEFAULT 0,
        tegangan DECIMAL(6,2) DEFAULT 0,
        arus DECIMAL(6,2) DEFAULT 0,
        daya DECIMAL(6,2) DEFAULT 0,
        kecepatan_angin DECIMAL(5,2) DEFAULT 0,
        arah_angin VARCHAR(20) DEFAULT '-',
        co DECIMAL(6,2) DEFAULT 0
    )";
    $pdo->exec($create);
    
    // Ambil ulang kolom setelah tabel dibuat
    try {
        $colQuery = $pdo->query("SHOW COLUMNS FROM data_sensor");
        while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['Field'];
        }
    } catch(Exception $ex) {
        // Abaikan jika gagal
    }
}

// Cek dan tambahkan kolom baru jika belum ada
if (!empty($columns)) {
    $checkColumns = ['daya', 'kecepatan_angin', 'arah_angin', 'co'];
    foreach ($checkColumns as $col) {
        if (!in_array($col, $columns)) {
            try {
                if ($col === 'arah_angin') {
                    $pdo->exec("ALTER TABLE data_sensor ADD COLUMN arah_angin VARCHAR(20) DEFAULT '-'");
                } else {
                    $pdo->exec("ALTER TABLE data_sensor ADD COLUMN $col DECIMAL(6,2) DEFAULT 0");
                }
            } catch(Exception $ex) {
                // Abaikan jika gagal
            }
        }
    }
}

// Konversi data ke format numerik untuk grafik
$chartData = [];
foreach ($rows as $row) {
    $timestamp = isset($row['waktu']) ? $row['waktu'] : '';

    $asapVal = $row['asap'];
    if (is_numeric($asapVal)) $asapVal = floatval($asapVal);
    else $asapVal = (strtolower($asapVal) == 'tinggi' || strtolower($asapVal) == 'bahaya') ? 100 : 0;

    $coVal = $row['co'];
    if (!is_numeric($coVal)) $coVal = 0;

    $arahVal = isset($row['arah_angin']) ? $row['arah_angin'] : '-';
    if (is_numeric($arahVal)) {
        $deg = floatval($arahVal);
        $deg = fmod(fmod($deg, 360) + 360, 360);
        $cardinals = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
        $arahVal = $cardinals[round($deg / 45) % 8];
    }

    $chartData[] = [
        'waktu' => $timestamp,
        'asap' => $asapVal,
        'suhu' => floatval($row['suhu']),
        'kelembapan' => floatval($row['kelembapan']),
        'tegangan' => floatval($row['tegangan']),
        'arus' => floatval($row['arus']),
        'daya' => isset($row['daya']) ? floatval($row['daya']) : 0,
        'kecepatan_angin' => isset($row['kecepatan_angin']) ? floatval($row['kecepatan_angin']) : 0,
        'arah_angin' => $arahVal,
        'co' => $coVal
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

<!-- Chart Outdoor Custom CSS -->
<link rel="stylesheet" href="css/chart_outdoor.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-chart-line"></i> FireNetWork</h3>
    <a href="<?php echo ($role == 'admin') ? 'dashboard_admin.php' : 'dashboard_user.php'; ?>" class="menu-btn">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>
    <a href="chart.php" class="menu-btn active">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
    <?php if ($role == 'admin'): ?>
    <a href="setting.php" class="menu-btn">
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
        <h2><i class="fas fa-chart-line"></i> Chart Monitoring Sensor</h2>
        <div class="header-right">
            <!-- PERBAIKAN: Tombol HOME dengan Modal -->
            <a href="#" class="btn-home-header" onclick="openHomeModal(); return false;">
                <i class="fas fa-home"></i> HOME
            </a>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Halo, <?= htmlspecialchars($user) ?></span>
            </div>
        </div>
    </div>

    <div class="filter-section" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div class="filter-form">
            <label><i class="fas fa-map-marker-alt"></i> Lokasi:</label>
            <select id="locationSelect" class="location-select" onchange="changeChartLocation(this.value)">
                <?php foreach ($all_locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>">
                        <?= htmlspecialchars($loc['nama_lokasi']) ?> (<?= (int)$loc['id'] === 1 ? 'Real-Time IoT' : 'Dummy' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Dari:</label>
            <input type="date" id="dateFrom">
            <label>Sampai:</label>
            <input type="date" id="dateTo">
            <button id="btnFilter" onclick="filterData()">
                <i class="fas fa-search"></i> Tampilkan
            </button>
        </div>
        <div>
            <span id="data-type-tag" class="data-type-badge realtime-badge">
                <i class="fas fa-satellite-dish"></i> Data Real Time
            </span>
        </div>
    </div>

    <!-- TAB BUTTONS -->
    <div class="sensor-tabs">
        <button class="tab-btn active" data-mode="all" onclick="setMode('all', this)">Semua Sensor</button>
        <button class="tab-btn" data-mode="bahaya" onclick="setMode('bahaya', this)">CO & Asap</button>
        <button class="tab-btn" data-mode="env" onclick="setMode('env', this)">Suhu & Kelembapan</button>
        <button class="tab-btn" data-mode="listrik" onclick="setMode('listrik', this)">Tegangan & Arus & Daya</button>
        <button class="tab-btn" data-mode="angin" onclick="setMode('angin', this)">Kecepatan Angin</button>
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
    chartData: <?php echo $jsonData; ?>
};
</script>
<!-- External JavaScript Logic -->
<script src="js/chart_outdoor.js"></script>
</body>
</html>