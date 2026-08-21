<?php
date_default_timezone_set('Asia/Makassar');
// Mulai session untuk user (simulasi login)
session_start();

// Proteksi: Hanya user outdoor yang bisa mengakses halaman ini
if (!isset($_SESSION['login_outdoor']) || $_SESSION['login_outdoor'] !== true) {
    header("Location: login.php?redirect=outdoor");
    exit();
}
$_SESSION['dashboard_type'] = 'outdoor';

$user = isset($_SESSION['outdoor_username']) ? $_SESSION['outdoor_username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "User");
$role = isset($_SESSION['outdoor_role']) ? $_SESSION['outdoor_role'] : "user";

// Koneksi ke database
require_once 'koneksi.php';

// ========== PASTIKAN KONEKSI MENGGUNAKAN PDO OUTDOOR ==========
if (!isset($pdo) && isset($pdo_outdoor)) {
    $pdo = $pdo_outdoor;
}

// ========== CEK KONEKSI DATABASE ==========
if (!$pdo) {
    die("<div style='padding: 20px; background: #fee2e2; color: #dc2626; border-radius: 10px; margin: 20px;'>
            <h3><i class='fas fa-database'></i> Koneksi Database Gagal!</h3>
            <p>Pastikan database 'outdoor' sudah ada dan konfigurasi koneksi sudah benar.</p>
            <p>Error: " . htmlspecialchars($e->getMessage() ?? 'Koneksi gagal') . "</p>
         </div>");
}

echo "<script>console.log('Koneksi database berhasil (Outdoor)');</script>";

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

// ========== AMBIL DATA DARI TABEL DATA_SENSOR ==========
try {
    // Cek apakah tabel data_sensor ada
    $checkTable = $pdo->query("SHOW TABLES LIKE 'data_sensor'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<script>console.log('Tabel data_sensor belum ada, akan dibuat...');</script>";
        // Buat tabel data_sensor
        $createTable = "
        CREATE TABLE IF NOT EXISTS data_sensor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal_dan_waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            asap VARCHAR(20) DEFAULT 'Normal',
            suhu DECIMAL(5,2) DEFAULT 0,
            kelembapan DECIMAL(5,2) DEFAULT 0,
            tegangan DECIMAL(6,2) DEFAULT 0,
            arus DECIMAL(6,2) DEFAULT 0,
            daya DECIMAL(6,2) DEFAULT 0,
            kecepatan_angin DECIMAL(5,2) DEFAULT 0,
            arah_angin VARCHAR(20) DEFAULT '-',
            co DECIMAL(6,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($createTable);
        echo "<script>console.log('Tabel data_sensor berhasil dibuat');</script>";
    }
    
    // Cek kolom apa saja yang tersedia di tabel data_sensor
    $checkColumns = $pdo->query("SHOW COLUMNS FROM data_sensor");
    $existingColumns = [];
    while($col = $checkColumns->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['Field'];
    }
    
    echo "<script>console.log('Kolom yang tersedia: " . json_encode($existingColumns) . "');</script>";
    
    // Tentukan kolom tanggal/waktu berdasarkan yang tersedia
    $dateColumn = null;
    $possibleDateColumns = ['tanggal_dan_waktu', 'timestamp', 'created_at', 'tanggal', 'waktu', 'date', 'datetime'];
    
    foreach ($possibleDateColumns as $col) {
        if (in_array($col, $existingColumns)) {
            $dateColumn = $col;
            break;
        }
    }
    
    // Jika tidak ada kolom tanggal/waktu, gunakan 'id' untuk sorting
    if ($dateColumn === null) {
        $dateColumn = 'id';
    }
    
    // Bangun query berdasarkan kolom yang tersedia - TANPA SENSOR API
    $selectColumns = [];
    $selectColumns[] = 'id';
    
    // Tambahkan kolom tanggal/waktu jika ada
    if ($dateColumn) {
        if ($dateColumn === 'id') {
            $selectColumns[] = "'' as tanggal_waktu";
        } else {
            $selectColumns[] = "$dateColumn as tanggal_waktu";
        }
    } else {
        $selectColumns[] = "'' as tanggal_waktu";
    }
    
    // Tambahkan kolom lainnya - TANPA API
    $otherColumns = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'daya', 'kecepatan_angin', 'arah_angin', 'co'];
    foreach ($otherColumns as $col) {
        if (in_array($col, $existingColumns)) {
            $selectColumns[] = $col;
        } else {
            $selectColumns[] = "'' as $col"; // Default kosong jika kolom tidak ada
        }
    }
    
    $query = "SELECT " . implode(", ", $selectColumns) . " FROM data_sensor";
    
    if ($dateColumn && $dateColumn !== 'id') {
        $query .= " ORDER BY $dateColumn DESC";
    } else {
        $query .= " ORDER BY id DESC";
    }
    
    echo "<script>console.log('Query: " . addslashes($query) . "');</script>";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $sensorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<script>console.log('Jumlah data: " . count($sensorData) . "');</script>";
    
} catch(PDOException $e) {
    echo "<script>console.log('Error: " . addslashes($e->getMessage()) . "');</script>";
    $sensorData = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tabel Data Sensor - FireNetWork</title>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- Tabel Outdoor Custom CSS -->
<link rel="stylesheet" href="css/tabel_outdoor.css">
</head>

<body>

<!-- SIDEBAR - Disesuaikan dengan role pengguna -->
<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireNetWork</h3>
    <!-- Dashboard link mengarah ke halaman yang sesuai dengan role -->
    <a href="<?php echo ($role == 'admin') ? 'dashboard_admin.php' : 'dashboard_user.php'; ?>" class="menu-btn">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>
    <a href="chart.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel.php" class="menu-btn active">
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
        <h2><i class="fas fa-table"></i> Tabel Data Sensor</h2>
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

    <div class="card">
        <h3>
            <i class="fas fa-database"></i> Riwayat Data Sensor Lengkap
            <span id="data-type-tag" class="data-type-badge realtime-badge">
                <i class="fas fa-satellite-dish"></i> Data Real Time
            </span>
        </h3>

        <div class="filter-section">
            <div class="filter-group">
                <label><i class="fas fa-map-marker-alt"></i> Lokasi</label>
                <select id="locationSelect" class="location-select" onchange="changeTableLocation(this.value)">
                    <?php foreach ($all_locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>">
                            <?= htmlspecialchars($loc['nama_lokasi']) ?> (<?= (int)$loc['id'] === 1 ? 'Real-Time IoT' : 'Dummy' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tanggal Mulai</label>
                <input type="date" id="start_date" class="date-filter">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tanggal Akhir</label>
                <input type="date" id="end_date" class="date-filter">
            </div>
            <div class="filter-group filter-actions">
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button class="btn-reset" onclick="resetFilter()">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button class="btn-excel" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn-delete-selected" id="btnDeleteSelected" onclick="hapusTerpilih()" disabled>
                    <i class="fas fa-trash-alt"></i> Hapus Terpilih (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="sensorTable" class="data-table" style="width:100%">
                <thead>
                    <tr>
                        <th style="text-align:center; width:40px;">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" title="Pilih Semua">
                        </th>
                        <th>No</th>
                        <th><i class="fas fa-calendar"></i> Tanggal & Waktu</th>
                        <th><i class="fas fa-smog"></i> Asap</th>
                        <th><i class="fas fa-thermometer-half"></i> Suhu (°C)</th>
                        <th><i class="fas fa-tint"></i> Kelembapan (%)</th>
                        <th><i class="fas fa-bolt"></i> Tegangan (V)</th>
                        <th><i class="fas fa-charging-station"></i> Arus (A)</th>
                        <th><i class="fas fa-solar-panel"></i> Daya (W)</th>
                        <th><i class="fas fa-wind"></i> Kecepatan Angin (m/s)</th>
                        <th><i class="fas fa-compass"></i> Arah Angin</th>
                        <th><i class="fas fa-skull-crossbones"></i> CO (ppm)</th>
                        <th style="text-align:center;"><i class="fas fa-trash-alt"></i> Aksi</th>
                    </tr>
                </thead>
                <tbody id="table-body"></tbody>
            </table>
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
    sensorData: <?php echo json_encode($sensorData); ?>
};
</script>
<!-- External JavaScript Logic -->
<script src="js/tabel_outdoor.js"></script>
</body>
</html>