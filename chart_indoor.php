<?php
session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// Koneksi database
require_once 'koneksi.php';

// Ambil struktur tabel data_sensor
$columns = [];
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

// Bangun query dengan sensor baru
$selectFields = ['id'];
if ($dateColumn) $selectFields[] = "$dateColumn as waktu";
else $selectFields[] = "'' as waktu";

// Daftar sensor yang ditampilkan
$sensorFields = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'daya', 'api'];
foreach ($sensorFields as $sf) {
    if (in_array($sf, $columns)) $selectFields[] = $sf;
    else $selectFields[] = "'' as $sf";
}

$query = "SELECT " . implode(", ", $selectFields) . " FROM data_sensor";
if ($dateColumn) $query .= " ORDER BY $dateColumn ASC";
$stmt = $pdo_indoor->prepare($query);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika tabel tidak ada atau kolom baru belum ada, buat/update tabel
if (empty($columns) || !in_array('daya', $columns) || !in_array('api', $columns)) {
    $create = "CREATE TABLE IF NOT EXISTS data_sensor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal_dan_waktu DATETIME NOT NULL,
        asap VARCHAR(20) DEFAULT 'Normal',
        suhu DECIMAL(5,2) DEFAULT 0,
        kelembapan DECIMAL(5,2) DEFAULT 0,
        tegangan DECIMAL(6,2) DEFAULT 0,
        arus DECIMAL(6,2) DEFAULT 0,
        daya DECIMAL(6,2) DEFAULT 0,
        api VARCHAR(20) DEFAULT 'Aman'
    )";
    $pdo_indoor->exec($create);
    
    // Cek dan tambahkan kolom baru jika belum ada
    $checkColumns = ['daya', 'api'];
    foreach ($checkColumns as $col) {
        $check = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE '$col'");
        if ($check->rowCount() == 0) {
            if ($col === 'api') {
                $pdo_indoor->exec("ALTER TABLE data_sensor ADD COLUMN api VARCHAR(20) DEFAULT 'Aman'");
            } else {
                $pdo_indoor->exec("ALTER TABLE data_sensor ADD COLUMN $col DECIMAL(6,2) DEFAULT 0");
            }
        }
    }
    $rows = [];
}

// Konversi data ke format numerik untuk grafik
$chartData = [];
foreach ($rows as $row) {
    $timestamp = isset($row['waktu']) ? $row['waktu'] : '';

    $asapVal = $row['asap'];
    if (is_numeric($asapVal)) $asapVal = floatval($asapVal);
    else $asapVal = (strtolower($asapVal) == 'tinggi' || strtolower($asapVal) == 'bahaya') ? 100 : 0;

    $apiVal = isset($row['api']) ? $row['api'] : 'Aman';
    if (is_numeric($apiVal)) $apiVal = floatval($apiVal);
    else $apiVal = (strtolower($apiVal) == 'terdeteksi api' || strtolower($apiVal) == 'bahaya' || strtolower($apiVal) == 'api') ? 100 : 0;

    $chartData[] = [
        'waktu' => $timestamp,
        'asap' => $asapVal,
        'suhu' => floatval($row['suhu']),
        'kelembapan' => floatval($row['kelembapan']),
        'tegangan' => floatval($row['tegangan']),
        'arus' => floatval($row['arus']),
        'daya' => isset($row['daya']) ? floatval($row['daya']) : 0,
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

.filter-section {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.filter-form label {
    font-weight: 600;
    color: #1e3c72;
}

.filter-form input {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    background: white;
}

.filter-form button {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.filter-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.sensor-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    background: rgba(224, 224, 224, 0.8);
    backdrop-filter: blur(5px);
    color: #333;
}

.tab-btn:hover {
    transform: translateY(-2px);
    background: rgba(255,255,255,0.9);
}

.tab-btn.active {
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.tab-btn[data-mode="all"].active { background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9)); }
.tab-btn[data-mode="bahaya"].active { background: linear-gradient(135deg, rgba(255, 107, 107, 0.9), rgba(238, 90, 36, 0.9)); }
.tab-btn[data-mode="env"].active { background: linear-gradient(135deg, rgba(78, 205, 196, 0.9), rgba(46, 204, 113, 0.9)); }
.tab-btn[data-mode="listrik"].active { background: linear-gradient(135deg, rgba(255, 230, 109, 0.9), rgba(243, 156, 18, 0.9)); }
.tab-btn[data-mode="angin"].active { background: linear-gradient(135deg, rgba(33, 150, 243, 0.9), rgba(25, 118, 210, 0.9)); }

.chart-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.chart-container {
    position: relative;
    height: 450px;
    width: 100%;
}

canvas {
    max-height: 450px;
    width: 100%;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 10px;
    padding: 10px;
}

.chart-legend {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid rgba(0,0,0,0.1);
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    cursor: pointer;
    transition: opacity 0.3s;
}

.legend-item:hover {
    opacity: 0.7;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-text {
    color: #333;
    font-weight: 500;
}

.legend-item.disabled .legend-text {
    text-decoration: line-through;
    color: #999;
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
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .sensor-tabs {
        justify-content: center;
    }
    .tab-btn {
        padding: 8px 16px;
        font-size: 12px;
    }
    .chart-container {
        height: 300px;
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
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-chart-line"></i> FireNetWork</h3>
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
        <h2><i class="fas fa-chart-line"></i> Chart Monitoring Sensor</h2>
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

    <div class="filter-section">
        <div class="filter-form">
            <label>Dari:</label>
            <input type="date" id="dateFrom">
            <label>Sampai:</label>
            <input type="date" id="dateTo">
            <button id="btnFilter" onclick="filterData()">
                <i class="fas fa-search"></i> Tampilkan
            </button>
        </div>
    </div>

    <!-- TAB BUTTONS -->
    <div class="sensor-tabs">
        <button class="tab-btn active" data-mode="all" onclick="setMode('all', this)">Semua Sensor</button>
        <button class="tab-btn" data-mode="bahaya" onclick="setMode('bahaya', this)">Api & Asap</button>
        <button class="tab-btn" data-mode="env" onclick="setMode('env', this)">Suhu & Kelembapan</button>
        <button class="tab-btn" data-mode="listrik" onclick="setMode('listrik', this)">Tegangan & Arus & Daya</button>
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

// ================= FUNGSI CHART =================
// Data dari database
const rawData = <?php echo $jsonData; ?>;
console.log('Data dari database:', rawData);

// Konfigurasi sensor
const sensorConfig = [
    { id: 'api', label: 'Sensor Api', color: '#dc3545', unit: '%', group: 'bahaya', min: 0, max: 100, yMax: 100 },
    { id: 'asap', label: 'Sensor Asap', color: '#ffa502', unit: '%', group: 'bahaya', min: 0, max: 100, yMax: 100 },
    { id: 'suhu', label: 'Suhu', color: '#ff6b6b', unit: '°C', group: 'env', min: 20, max: 60, yMax: 70 },
    { id: 'kelembapan', label: 'Kelembapan', color: '#4ecdc4', unit: '%', group: 'env', min: 30, max: 95, yMax: 100 },
    { id: 'tegangan', label: 'Tegangan', color: '#ffe66d', unit: 'V', group: 'listrik', min: 200, max: 230, yMax: 250 },
    { id: 'arus', label: 'Arus', color: '#a8e6cf', unit: 'A', group: 'listrik', min: 0.5, max: 5.5, yMax: 10 },
    { id: 'daya', label: 'Daya', color: '#ff9800', unit: 'W', group: 'listrik', min: 0, max: 1000, yMax: 1200 }
];

let currentMode = "all";
let datasets = [];
let myChart = null;
let fullData = [];
let filteredData = [];

// Fungsi format waktu untuk label sumbu X (HH:MM:SS)
function formatWaktu(waktuStr) {
    if (!waktuStr) return '-';
    try {
        const d = new Date(waktuStr);
        if (isNaN(d.getTime())) return waktuStr;
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return waktuStr;
    }
}

// Fungsi format waktu lengkap untuk tooltip
function formatWaktuLengkap(waktuStr) {
    if (!waktuStr) return '-';
    try {
        const d = new Date(waktuStr);
        if (isNaN(d.getTime())) return waktuStr;
        return d.toLocaleString('id-ID', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
    } catch {
        return waktuStr;
    }
}

function initDatasets() {
    datasets = [];
    sensorConfig.forEach(sensor => {
        datasets.push({
            label: sensor.label,
            data: [],
            borderColor: sensor.color,
            backgroundColor: sensor.color + '20',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 8,
            hidden: sensor.group !== 'all',
            yAxisID: sensor.id === 'tegangan' || sensor.id === 'arus' || sensor.id === 'daya' ? 'y-listrik' : 
                     (sensor.id === 'suhu' || sensor.id === 'kelembapan' ? 'y-env' : 
                     (sensor.id === 'api' || sensor.id === 'asap' ? 'y-bahaya' : 'y-bahaya'))
        });
    });
}

function createChart(labels, dataPoints) {
    const ctx = document.getElementById('myChart').getContext('2d');
    if (myChart) myChart.destroy();
    initDatasets();
    datasets.forEach((ds, idx) => {
        const sensorId = sensorConfig[idx].id;
        ds.data = dataPoints.map(row => row[sensorId]);
    });
    
    // Buat label sumbu X dengan format waktu pendek (HH:MM:SS)
    const xLabels = labels.map(w => formatWaktu(w));
    
    myChart = new Chart(ctx, {
        type: 'line',
        data: { labels: xLabels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(tooltipItems) {
                            const index = tooltipItems[0].dataIndex;
                            const waktuLengkap = formatWaktuLengkap(labels[index]);
                            return '🕐 ' + waktuLengkap;
                        },
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            let sensor = sensorConfig.find(s => s.label === label);
                            let unit = sensor ? sensor.unit : '';
                            if (sensor && sensor.id === 'api') {
                                let status = value > 70 ? '🔥 TERDETEKSI API' : (value > 40 ? '⚠️ POTENSI' : '✅ AMAN');
                                return `${label}: ${value} ${unit} - ${status}`;
                            }
                            if (sensor && sensor.id === 'asap') {
                                let status = value > 70 ? '🔥 TINGGI' : (value > 40 ? '⚠️ SEDANG' : '✅ NORMAL');
                                return `${label}: ${value} ${unit} - ${status}`;
                            }
                            return `${label}: ${value} ${unit}`;
                        }
                    }
                }
            },
            scales: {
                x: { 
                    display: true,
                    grid: { display: true },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 30,
                        font: { size: 10 },
                        autoSkip: true,
                        maxTicksLimit: 15
                    }
                },
                'y-bahaya': {
                    position: 'left', 
                    beginAtZero: true, 
                    max: 120,
                    grid: { color: 'rgba(255,107,107,0.2)', drawOnChartArea: true },
                    title: { display: true, text: 'Api (%) / Asap (%)', color: '#ff6b6b' },
                    ticks: { callback: function(v) { return v; } },
                    display: true
                },
                'y-env': {
                    position: 'right', 
                    beginAtZero: true, 
                    max: 100,
                    grid: { color: 'rgba(78,205,196,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Suhu (°C) / Kelembapan (%)', color: '#4ecdc4' },
                    ticks: { callback: v => v + (v > 50 ? '%' : '°C') },
                    display: false
                },
                'y-listrik': {
                    position: 'right', 
                    beginAtZero: false, 
                    min: 0, 
                    max: 1200,
                    grid: { color: 'rgba(255,152,0,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Tegangan (V) / Arus (A) / Daya (W)', color: '#ff9800' },
                    ticks: { callback: v => v + (v > 100 ? 'W' : (v > 10 ? 'V' : 'A')) },
                }
            }
        }
    });
    updateYAxisVisibility();
    updateLegend();
}

function updateYAxisVisibility() {
    if (!myChart) return;
    const yBahaya = myChart.options.scales['y-bahaya'];
    const yEnv = myChart.options.scales['y-env'];
    const yListrik = myChart.options.scales['y-listrik'];
    
    yBahaya.display = false;
    yEnv.display = false;
    yListrik.display = false;
    
    if (currentMode === 'bahaya') {
        yBahaya.display = true;
    } else if (currentMode === 'env') {
        yEnv.display = true;
    } else if (currentMode === 'listrik') {
        yListrik.display = true;
    } else {
        let hasBahaya = false, hasEnv = false, hasListrik = false;
        datasets.forEach(ds => {
            if (ds.hidden) return;
            const sensor = sensorConfig.find(s => s.label === ds.label);
            if (sensor) {
                if (sensor.group === 'bahaya') hasBahaya = true;
                else if (sensor.group === 'env') hasEnv = true;
                else if (sensor.group === 'listrik') hasListrik = true;
            }
        });
        if (hasBahaya) yBahaya.display = true;
        if (hasEnv) yEnv.display = true;
        if (hasListrik) yListrik.display = true;
    }
    
    yBahaya.grid.drawOnChartArea = yBahaya.display;
    yEnv.grid.drawOnChartArea = yEnv.display;
    yListrik.grid.drawOnChartArea = yListrik.display;
    
    myChart.update();
}

function updateLegend() {
    const container = document.getElementById('chartLegend');
    if (!container) return;
    container.innerHTML = '';
    datasets.forEach((ds, idx) => {
        if (ds.hidden) return;
        const sensor = sensorConfig[idx];
        const legendItem = document.createElement('div');
        legendItem.className = 'legend-item';
        legendItem.onclick = () => toggleDataset(idx);
        legendItem.innerHTML = `
            <div class="legend-color" style="background: ${sensor.color}"></div>
            <span class="legend-text">${sensor.label}</span>
        `;
        container.appendChild(legendItem);
    });
}

function toggleDataset(index) {
    datasets[index].hidden = !datasets[index].hidden;
    updateLegend();
    updateYAxisVisibility();
    myChart.update();
}

function setMode(mode, element) {
    currentMode = mode;
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    element.classList.add('active');
    
    let visibleGroups = [];
    if (mode === 'all') visibleGroups = ['bahaya', 'env', 'listrik'];
    else if (mode === 'bahaya') visibleGroups = ['bahaya'];
    else if (mode === 'env') visibleGroups = ['env'];
    else if (mode === 'listrik') visibleGroups = ['listrik'];
    
    datasets.forEach((ds, idx) => {
        const sensor = sensorConfig[idx];
        ds.hidden = !visibleGroups.includes(sensor.group);
    });
    
    updateYAxisVisibility();
    updateLegend();
    myChart.update();
}

function filterData() {
    const fromDate = document.getElementById('dateFrom').value;
    const toDate = document.getElementById('dateTo').value;
    if (!fromDate && !toDate) {
        filteredData = [...fullData];
    } else {
        filteredData = fullData.filter(item => {
            if (!item.waktu) return true;
            const itemDate = item.waktu.split(' ')[0];
            let ok = true;
            if (fromDate && itemDate < fromDate) ok = false;
            if (toDate && itemDate > toDate) ok = false;
            return ok;
        });
    }
    if (filteredData.length === 0) {
        createChart([], []);
        alert('Tidak ada data dalam rentang tanggal tersebut.');
        return;
    }
    const labels = filteredData.map(d => d.waktu);
    createChart(labels, filteredData);
}

document.addEventListener('DOMContentLoaded', () => {
    fullData = rawData.map(row => ({
        waktu: row.waktu || '',
        asap: typeof row.asap === 'number' ? row.asap : 0,
        suhu: typeof row.suhu === 'number' ? row.suhu : 0,
        kelembapan: typeof row.kelembapan === 'number' ? row.kelembapan : 0,
        tegangan: typeof row.tegangan === 'number' ? row.tegangan : 0,
        arus: typeof row.arus === 'number' ? row.arus : 0,
        daya: typeof row.daya === 'number' ? row.daya : 0,
        api: typeof row.api === 'number' ? row.api : 0
    }));
    
    if (fullData.length === 0) {
        createChart([], []);
        console.warn('Tidak ada data sensor di database. Grafik akan kosong.');
        alert('Belum ada data sensor. Grafik akan kosong, menunggu data dari database.');
        return;
    }
    filteredData = [...fullData];
    const labels = filteredData.map(d => d.waktu);
    createChart(labels, filteredData);
});
</script>

</body>
</html>