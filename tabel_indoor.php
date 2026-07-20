<?php
// Mulai session untuk user (simulasi login)
session_start();

// Jika tipe dashboard adalah outdoor, alihkan ke tabel.php
if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'outdoor') {
    header("Location: tabel.php");
    exit();
}
$_SESSION['dashboard_type'] = 'indoor';

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// Koneksi ke database
require_once 'koneksi.php';

if (!$pdo_indoor) {
    die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 6px; margin: 20px;'>
        <h3>Error: Koneksi ke Database INDOOR ('firenet') Gagal.</h3>
        <p>Pastikan Anda telah mengaktifkan MySQL di XAMPP Control Panel, membuat database <strong>firenet</strong> di phpMyAdmin, dan mengimpor tabel-tabel yang diperlukan.</p>
    </div>");
}

echo "<script>console.log('Koneksi database berhasil');</script>";

// Ambil data dari tabel data_sensor sesuai dengan struktur yang ada
try {
    // Cek kolom apa saja yang tersedia di tabel data_sensor
    $checkColumns = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor");
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
    
    // Jika tidak ada kolom tanggal/waktu, gunakan kolom pertama yang tersedia untuk sorting
    if ($dateColumn === null && !empty($existingColumns)) {
        $dateColumn = $existingColumns[0]; // Gunakan kolom pertama sebagai default
    }
    
    // Bangun query berdasarkan kolom yang tersedia - TANPA SENSOR API
    $selectColumns = [];
    $selectColumns[] = 'id';
    
    // Tambahkan kolom tanggal/waktu jika ada
    if ($dateColumn) {
        $selectColumns[] = "$dateColumn as tanggal_waktu";
    } else {
        $selectColumns[] = "'' as tanggal_waktu";
    }
    
    // Tambahkan kolom lainnya - HANYA KOLOM YANG DIPERLUKAN
    $otherColumns = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus'];
    foreach ($otherColumns as $col) {
        if (in_array($col, $existingColumns)) {
            $selectColumns[] = $col;
        } else {
            $selectColumns[] = "'' as $col"; // Default kosong jika kolom tidak ada
        }
    }
    
    $query = "SELECT " . implode(", ", $selectColumns) . " FROM data_sensor";
    
    if ($dateColumn) {
        $query .= " ORDER BY $dateColumn DESC";
    }
    
    echo "<script>console.log('Query: " . addslashes($query) . "');</script>";
    
    $stmt = $pdo_indoor->prepare($query);
    $stmt->execute();
    $sensorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<script>console.log('Jumlah data: " . count($sensorData) . "');</script>";
    
} catch(PDOException $e) {
    echo "<script>console.log('Error: " . addslashes($e->getMessage()) . "');</script>";
    
    // Jika tabel tidak ada, buat tabel baru dengan semua kolom yang diperlukan - TANPA API
    if (strpos($e->getMessage(), "Table") !== false) {
        $createTable = "
        CREATE TABLE IF NOT EXISTS data_sensor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal_dan_waktu DATETIME NOT NULL,
            asap VARCHAR(20) DEFAULT 'Normal',
            suhu DECIMAL(5,2) DEFAULT 0,
            kelembapan DECIMAL(5,2) DEFAULT 0,
            tegangan DECIMAL(6,2) DEFAULT 0,
            arus DECIMAL(6,2) DEFAULT 0
        )";
        $pdo_indoor->exec($createTable);
        
        // Ambil data setelah tabel dibuat
        $query = "SELECT id, tanggal_dan_waktu, asap, suhu, kelembapan, tegangan, arus 
                  FROM data_sensor 
                  ORDER BY tanggal_dan_waktu DESC";
        $stmt = $pdo_indoor->prepare($query);
        $stmt->execute();
        $sensorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    background-image: url('https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2');
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

.menu-btn i {
    width: 24px;
    font-size: 18px;
}

.menu-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateX(5px);
}

.menu-btn.active {
    background: linear-gradient(135deg, #00b4db, #0083b0);
}

.logout {
    margin-top: 40px;
    background: rgba(220, 53, 69, 0.8);
}

.logout:hover {
    background: #dc3545;
}

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

.header h2 {
    color: #1e3c72;
    font-size: 24px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

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

.user-info i {
    font-size: 20px;
}

.btn-home-header {
    background: rgba(34, 6, 244, 0.2);
    color: #1e3c72;
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

.btn-home-header:hover {
    background: rgba(255, 255, 255, 0.45);
    transform: translateY(-2px);
}

.card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.card h3 {
    color: #1e3c72;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card h3 i {
    color: #00b4db;
}

.filter-section {
    background: rgba(248, 249, 250, 0.8);
    backdrop-filter: blur(5px);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    color: #333;
    font-weight: 500;
    font-size: 14px;
}

.filter-group input, .filter-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}

.filter-group input:focus, .filter-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.4);
}

.btn-reset {
    background: #6c757d;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-reset:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-excel {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-excel:hover {
    background: #218838;
    transform: translateY(-2px);
}

.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.data-table th {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}

.data-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    background: rgba(255,255,255,0.7);
}

.data-table tr:hover td {
    background: rgba(255,255,255,0.9);
}

.status-aman {
    color: #28a745;
    font-weight: bold;
}

.status-bahaya {
    color: #dc3545;
    font-weight: bold;
}

.status-waspada {
    color: #ff9800;
    font-weight: bold;
}

/* ========== MODAL LOGOUT ========== */
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

@media (max-width: 768px) {
    .sidebar {
        width: 80px;
        padding: 20px 10px;
    }
    .sidebar h3 {
        font-size: 12px;
    }
    .menu-btn span {
        display: none;
    }
    .menu-btn i {
        margin: 0;
    }
    .main {
        padding: 15px;
    }
    .filter-section {
        flex-direction: column;
    }
    .filter-group {
        width: 100%;
    }
    .header-right {
        flex-direction: column;
        gap: 8px;
    }
    .btn-home-header {
        padding: 6px 12px;
        font-size: 12px;
    }
    .modal-box {
        padding: 30px 20px;
    }
    .modal-buttons {
        flex-direction: column;
    }
    .btn-modal {
        justify-content: center;
    }
}

.dataTables_wrapper {
    padding: 10px 0;
}

.dataTables_length select, 
.dataTables_filter input {
    padding: 5px 10px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: white;
}

.dt-buttons {
    margin-bottom: 15px;
}

.dt-button {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 8px;
    margin-right: 5px;
    cursor: pointer;
}

.dt-button:hover {
    opacity: 0.9;
}

.dataTables_info {
    color: #333;
    font-weight: 500;
}

.dataTables_paginate .paginate_button {
    background: rgba(255,255,255,0.8);
    border-radius: 8px;
    margin: 0 2px;
}

.dataTables_paginate .paginate_button:hover {
    background: rgba(255,255,255,0.9);
}
</style>
</head>

<body>

<!-- SIDEBAR - Disesuaikan dengan role pengguna -->
<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireNetWork</h3>
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
        <h2><i class="fas fa-table"></i> Tabel Data Sensor</h2>
        <div class="header-right">
            <a href="home.php" class="btn-home-header">
                <i class="fas fa-home"></i> HOME
            </a>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>Halo, <?= htmlspecialchars($user) ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fas fa-database"></i> Riwayat Data Sensor Lengkap</h3>

        <div class="filter-section">
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tanggal Mulai</label>
                <input type="date" id="start_date" class="date-filter">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tanggal Akhir</label>
                <input type="date" id="end_date" class="date-filter">
            </div>
            <div class="filter-group">
                <button class="btn-filter" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button class="btn-reset" onclick="resetFilter()">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button class="btn-excel" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="sensorTable" class="data-table" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th><i class="fas fa-calendar"></i> Tanggal & Waktu</th>
                        <th><i class="fas fa-smog"></i> Asap</th>
                        <th><i class="fas fa-thermometer-half"></i> Suhu (°C)</th>
                        <th><i class="fas fa-tint"></i> Kelembapan (%)</th>
                        <th><i class="fas fa-bolt"></i> Tegangan (V)</th>
                        <th><i class="fas fa-charging-station"></i> Arus (A)</th>
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
            <a href="logout.php" class="btn-modal btn-logout-confirm">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
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

// Tutup modal jika klik di luar modal
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});

// Tutup modal dengan tombol ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('logoutModal').style.display === 'flex') {
        closeLogoutModal();
    }
});

// ================= FUNGSI TABEL =================
// Data dari database PHP
const sensorDataPHP = <?php echo json_encode($sensorData); ?>;
console.log('Data dari database:', sensorDataPHP);

let sensorData = sensorDataPHP.map((item, index) => {
    let formattedDate = item.tanggal_waktu || '-';
    let dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
    return {
        id: item.id,
        no: index + 1,
        tanggal_waktu: formattedDate,
        tanggal: dateOnly,
        asap: item.asap || '-',
        suhu: item.suhu ? parseFloat(item.suhu).toFixed(1) : '0',
        kelembapan: item.kelembapan ? parseFloat(item.kelembapan).toFixed(1) : '0',
        tegangan: item.tegangan ? parseFloat(item.tegangan).toFixed(1) : '0',
        arus: item.arus ? parseFloat(item.arus).toFixed(2) : '0'
    };
});

let currentData = [...sensorData];
let dataTable;

// Fungsi untuk menentukan status dan kelas CSS - TANPA API
function getStatusClass(value, type) {
    if (type === 'asap') {
        if (value === 'Tinggi' || value === 'Bahaya') return 'status-bahaya';
        if (value === 'Sedang') return 'status-waspada';
        return 'status-aman';
    }
    return '';
}

function getStatusIcon(value, type) {
    if (type === 'asap') {
        if (value === 'Tinggi' || value === 'Bahaya') return '<i class="fas fa-chart-line"></i>';
        if (value === 'Sedang') return '<i class="fas fa-minus-circle"></i>';
        return '<i class="fas fa-check"></i>';
    }
    return '';
}

function updateDataTable(data) {
    const rows = data.map((item) => [
        item.no,
        item.tanggal_waktu,
        `<span class="${getStatusClass(item.asap, 'asap')}">${getStatusIcon(item.asap, 'asap')} ${item.asap}</span>`,
        `${item.suhu} °C`,
        `${item.kelembapan} %`,
        `${item.tegangan} V`,
        `${item.arus} A`
    ]);
    if (dataTable) {
        dataTable.clear();
        if (rows.length > 0) dataTable.rows.add(rows);
        dataTable.draw();
    }
}

function initDataTable(data) {
    if (dataTable) dataTable.destroy();
    const rows = data.map((item) => [
        item.no,
        item.tanggal_waktu,
        `<span class="${getStatusClass(item.asap, 'asap')}">${getStatusIcon(item.asap, 'asap')} ${item.asap}</span>`,
        `${item.suhu} °C`,
        `${item.kelembapan} %`,
        `${item.tegangan} V`,
        `${item.arus} A`
    ]);
    dataTable = $('#sensorTable').DataTable({
        data: rows,
        columns: [
            { title: "No" }, 
            { title: "Tanggal & Waktu" }, 
            { title: "Asap" }, 
            { title: "Suhu (°C)" }, 
            { title: "Kelembapan (%)" },
            { title: "Tegangan (V)" }, 
            { title: "Arus (A)" }
        ],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            search: "Cari:",
            paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
        },
        pageLength: 10, 
        lengthMenu: [5, 10, 25, 50], 
        order: [[1, 'desc']], 
        scrollX: true
    });
}

function applyFilter() {
    let filteredData = [...sensorData];
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && filteredData[0]?.tanggal) filteredData = filteredData.filter(item => item.tanggal >= startDate);
    if (endDate && filteredData[0]?.tanggal) filteredData = filteredData.filter(item => item.tanggal <= endDate);
    filteredData.forEach((item, idx) => item.no = idx + 1);
    currentData = filteredData;
    updateDataTable(currentData);
    if (filteredData.length === 0) alert('Tidak ada data yang sesuai dengan filter!');
}

function resetFilter() {
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    sensorData.forEach((item, idx) => item.no = idx + 1);
    currentData = [...sensorData];
    updateDataTable(currentData);
}

function exportToExcel() {
    let exportData = [...sensorData];
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && exportData[0]?.tanggal) exportData = exportData.filter(item => item.tanggal >= startDate);
    if (endDate && exportData[0]?.tanggal) exportData = exportData.filter(item => item.tanggal <= endDate);
    if (exportData.length === 0) { alert('Tidak ada data untuk diexport!'); return; }
    
    let csv = "No,Tanggal & Waktu,Asap,Suhu (°C),Kelembapan (%),Tegangan (V),Arus (A)\n";
    exportData.forEach((item, idx) => {
        csv += `"${idx+1}","${item.tanggal_waktu}","${item.asap}","${item.suhu}","${item.kelembapan}","${item.tegangan}","${item.arus}"\n`;
    });
    
    const blob = new Blob(["\uFEFF" + csv], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `data_sensor_${new Date().toISOString().slice(0,19)}.xls`;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
    alert(`Berhasil mengexport ${exportData.length} data ke Excel!`);
}

$(document).ready(function() {
    // Perbaikan kondisi: Cek apakah array memiliki isi terlebih dahulu secara aman
    if (sensorData && sensorData.length > 0) {
        initDataTable(sensorData);
        console.log(`Data berhasil dimuat: ${sensorData.length} record`);
    } else {
        // Jika data kosong, inisialisasi tabel kosong agar DataTables tidak rusak
        $('#sensorTable').DataTable({
            data: [],
            columns: [
                { title: "No" }, 
                { title: "Tanggal & Waktu" }, 
                { title: "Asap" }, 
                { title: "Suhu (°C)" }, 
                { title: "Kelembapan (%)" },
                { title: "Tegangan (V)" }, 
                { title: "Arus (A)" }
            ],
            language: { 
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json",
                emptyTable: "Tidak ada data sensor yang tersedia. Silakan tambahkan data terlebih dahulu." 
            },
            scrollX: true
        });
        console.log('Tidak ada data yang ditemukan di database.');
    }
});
</script>

</body>
</html>