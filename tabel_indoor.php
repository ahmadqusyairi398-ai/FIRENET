<?php
date_default_timezone_set('Asia/Makassar');
// Mulai session untuk user (simulasi login)
session_start();

// Proteksi: Hanya user indoor yang bisa mengakses halaman ini
if (!isset($_SESSION['login_indoor']) || $_SESSION['login_indoor'] !== true) {
    header("Location: login.php?redirect=indoor");
    exit();
}
$_SESSION['dashboard_type'] = 'indoor';

$user = isset($_SESSION['indoor_username']) ? $_SESSION['indoor_username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "User");
$role = isset($_SESSION['indoor_role']) ? $_SESSION['indoor_role'] : "user";

// Koneksi ke database
require_once 'koneksi.php';

if (!$pdo_indoor) {
    die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 6px; margin: 20px;'>
        <h3>Error: Koneksi ke Database INDOOR ('indoor') Gagal.</h3>
        <p>Pastikan Anda telah mengaktifkan MySQL di XAMPP Control Panel, membuat database <strong>indoor</strong> di phpMyAdmin, dan mengimpor tabel-tabel yang diperlukan.</p>
    </div>");
}

// Pastikan kolom is_dummy ada di tabel data_sensor
try {
    $colCheck = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if (!$colCheck || $colCheck->rowCount() == 0) {
        @$pdo_indoor->exec("ALTER TABLE data_sensor ADD COLUMN is_dummy INT DEFAULT 0");
    }

    // Cek jika data dummy di database masih kosong (0 baris), otomatis buatkan 50 data dummy awal ke database MySQL
    $q_chk_dummy = $pdo_indoor->query("SELECT COUNT(*) FROM data_sensor WHERE is_dummy = 1");
    $total_dummy_db = $q_chk_dummy ? (int)$q_chk_dummy->fetchColumn() : 0;
    if ($total_dummy_db === 0) {
        $now = time();
        for ($i = 49; $i >= 0; $i--) {
            $t = date('Y-m-d H:i:s', $now - ($i * 60));
            $d_api = (rand(1, 100) > 90) ? 100 : 0;
            $d_asap = rand(15, 85);
            $d_suhu = rand(26, 34);
            $d_kelembapan = rand(50, 75);
            $d_tegangan = rand(2180, 2220) / 10;
            $d_arus = rand(20, 45) / 10;
            $d_rssi = rand(-75, -55);
            if ($d_api > 0) { 
                $d_suhu += 15; 
                $d_kelembapan -= 20; 
                $d_asap += 40; 
            }

            $stmt_ins = $pdo_indoor->prepare("
                INSERT INTO data_sensor (timestamp, api, asap, suhu, kelembapan, tegangan, arus, rssi, ip_address, is_dummy)
                VALUES (:waktu, :api, :asap, :suhu, :kelembapan, :tegangan, :arus, :rssi, '127.0.0.1 (Simulasi)', 1)
            ");
            $stmt_ins->execute([
                ':waktu' => $t,
                ':api' => $d_api,
                ':asap' => $d_asap,
                ':suhu' => $d_suhu,
                ':kelembapan' => $d_kelembapan,
                ':tegangan' => $d_tegangan,
                ':arus' => $d_arus,
                ':rssi' => $d_rssi
            ]);
        }
    }
} catch (Exception $e) {}

// --- TAMBAHAN: Hitung Estimasi Kapasitas Data Dinamis (Indoor) ---
$indoor_storage = get_sensor_storage_info($pdo_indoor, 'indoor');
$kapasitas_real_formatted = $indoor_storage['real_formatted'];
$kapasitas_dummy_formatted = $indoor_storage['dummy_formatted'];
// -----------------------------------------------------------------

// --- TAMBAHAN BARU: Ambil daftar lokasi dari database ---
$db_locations = [];
try {
    $stmt_loc = $pdo_indoor->query("SELECT * FROM lokasi_monitoring ORDER BY id ASC");
    $db_locations = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
// ---------------------------------------------------------

// ============================================================
// AMBIL DATA DARI TABEL data_sensor SESUAI DENGAN indoor.sql
// ============================================================
try {
    // Query disesuaikan persis dengan kolom yang ada di database indoor.sql
    $query = "SELECT 
                id, 
                timestamp as tanggal_waktu, 
                api, 
                asap, 
                suhu, 
                kelembapan, 
                tegangan, 
                arus, 
                rssi,
                ip_address,
                latitude,
                longitude,
                is_dummy
              FROM data_sensor 
              ORDER BY timestamp DESC LIMIT 5000";
              
    $stmt = $pdo_indoor->prepare($query);
    $stmt->execute();
    $sensorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<script>console.log('Jumlah data: " . count($sensorData) . "');</script>";
    
} catch(PDOException $e) {
    echo "<script>console.log('Error: " . addslashes($e->getMessage()) . "');</script>";
    
    // Jika tabel tidak ada, buat tabel sesuai dengan indoor.sql
    if (strpos($e->getMessage(), "Table") !== false) {
        $createTable = "
        CREATE TABLE IF NOT EXISTS data_sensor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api FLOAT DEFAULT NULL,
            asap FLOAT DEFAULT NULL,
            suhu FLOAT DEFAULT NULL,
            kelembapan FLOAT DEFAULT NULL,
            tegangan FLOAT DEFAULT NULL,
            arus FLOAT DEFAULT NULL,
            rssi INT(11) DEFAULT NULL,
            ip_address VARCHAR(50) DEFAULT NULL,
            latitude DECIMAL(10,8) DEFAULT NULL,
            longitude DECIMAL(11,8) DEFAULT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo_indoor->exec($createTable);
        $sensorData = [];
    } else {
        $sensorData = [];
    }
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

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom CSS Tabel Indoor -->
<link rel="stylesheet" href="css/tabel_indoor.css">
</head>

<body>

<!-- SIDEBAR - Disesuaikan dengan role pengguna -->
<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireDetector</h3>
    <!-- Dashboard link mengarah ke halaman yang sesuai dengan role -->
    <a href="<?php echo ($role == 'admin') ? 'dashboard_admin_indoor.php' : 'dashboard_user_indoor.php'; ?>" class="menu-btn">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>
    <a href="chart_indoor.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel_indoor.php" class="menu-btn active">
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
            <i class="fas fa-table"></i> Tabel Data Sensor Indoor
            <span id="table-badge" style="font-size: 13px; padding: 5px 12px; border-radius: 20px; font-weight: bold; margin-left: 15px; color: white; background: #28a745; transition: all 0.3s;">
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

    <div class="card">
        <!-- Modifikasi Judul Tabel dengan Kapasitas di Kanan -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0;"><i class="fas fa-database"></i> Riwayat Data Sensor Lengkap</h3>

            <div style="font-size: 13px; font-weight: 600; background: #f8f9fa; padding: 8px 12px; border-radius: 6px; border: 1px solid #e0e0e0; color: #495057;">
                <i class="fas fa-hdd" style="color: #6c757d; margin-right: 5px;"></i> Storage:
                <span style="color: #007bff; margin-left: 5px;">Real <span id="storageRealVal"><?= htmlspecialchars($kapasitas_real_formatted) ?></span> / 29 GB</span>
                <span style="color: #ccc; margin: 0 5px;">|</span>
                <span style="color: #dc3545;">Dummy <span id="storageDummyVal"><?= htmlspecialchars($kapasitas_dummy_formatted) ?></span> / 29 GB</span>
            </div>
        </div>

        <div class="filter-section" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">

            <div class="filter-group">
                <label><i class="fas fa-map-marker-alt"></i> Lokasi Alat</label>
                <select id="locationSelect" onchange="applyFilter()" style="padding: 8px 12px; border-radius: 5px; border: 1px solid #ccc; font-weight: bold; cursor: pointer; min-width: 200px;">
                    <?php foreach ($db_locations as $loc):
                        $idAlat = !empty($loc['id_alat']) ? $loc['id_alat'] : "LOK-".$loc['id'];
                        $namaLokasi = !empty($loc['nama_lokasi']) ? $loc['nama_lokasi'] : $idAlat;
                        $isLive = (strtoupper($idAlat) === 'LOK-002' || $loc['id'] == 2);
                        $labelStatus = $isLive ? "(Live)" : "(Dummy)";
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
            </div>

            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tanggal Mulai</label>
                <input type="date" id="start_date" class="date-filter">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tanggal Akhir</label>
                <input type="date" id="end_date" class="date-filter">
            </div>
            <div class="filter-group" style="display: flex; gap: 8px; margin-top: 22px;">
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button class="btn-reset" onclick="resetFilter()" style="background: #6c757d; color: white;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button class="btn-excel" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn-delete-selected" id="btnDeleteSelected" onclick="hapusTerpilih()" disabled style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 5px; font-weight: 600; cursor: not-allowed; opacity: 0.6; display: flex; align-items: center; gap: 6px; transition: all 0.3s;">
                    <i class="fas fa-trash-alt"></i> Hapus (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="sensorTable" class="data-table" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" title="Pilih Semua" style="cursor: pointer; width: 16px; height: 16px;">
                        </th>
                        <th>No</th>
                        <th><i class="fas fa-calendar"></i> Tanggal & Waktu</th>
                        <th><i class="fas fa-fire"></i> Api</th>
                        <th><i class="fas fa-smog"></i> Asap</th>
                        <th><i class="fas fa-thermometer-half"></i> Suhu (°C)</th>
                        <th><i class="fas fa-tint"></i> Kelembapan (%)</th>
                        <th><i class="fas fa-bolt"></i> Tegangan (V)</th>
                        <th><i class="fas fa-charging-station"></i> Arus (A)</th>
                        <th><i class="fas fa-signal"></i> RSSI (dBm)</th>
                        <th><i class="fas fa-cogs"></i> Aksi</th>
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
    window.INDOOR_SENSOR_DATA = <?php echo json_encode($sensorData); ?>;
</script>
<script src="js/tabel_indoor.js"></script>

</body>
</html>