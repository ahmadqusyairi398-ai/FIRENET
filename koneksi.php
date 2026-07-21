<?php
// Deteksi secara otomatis apakah sedang berjalan di Localhost atau di Domain/Hosting Live
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_localhost) {
    // ==========================================
    // 1. KREDENSIAL DATABASE LOCALHOST (LOKAL)
    // ==========================================
    $host = "localhost";
    $username = "root";           // Default XAMPP adalah "root"
    $password = "";               // Default XAMPP kosong
    $dbname_outdoor = "outdoor";
    $dbname_indoor = "indoor";    // Nama database sesuai dengan indoor.sql
} else if (strpos($_SERVER['HTTP_HOST'], 'inovasijre.com') !== false) {
    // ==========================================================
    // 2. KREDENSIAL DATABASE LIVE DOMAIN (inovasijre.com)
    // ==========================================================
    $host = "localhost"; 
    $username = "ta_user";        // Sesuaikan dengan user database Anda di cPanel
    $password = "rahasiaTA123!";  // Masukkan password user database Anda
    $dbname_outdoor = "outdoor";  // Sesuaikan dengan nama database outdoor Anda
    $dbname_indoor = "indoor";    // Sesuaikan dengan nama database indoor Anda
} else {
    // ==========================================================
    // 3. KREDENSIAL DATABASE DOMAIN LAIN (PRODUCTION)
    // ==========================================================
    $host = "localhost"; 
    $username = "ta_user"; 
    $password = "rahasiaTA123!"; 
    $dbname_outdoor = "outdoor"; 
    $dbname_indoor = "indoor"; 
}

$pdo_outdoor = null;
$conn_outdoor = null;
$pdo_indoor = null;
$conn_indoor = null;

// 1. KONEKSI DATABASE OUTDOOR
try {
    $pdo_outdoor = new PDO("mysql:host=$host;dbname=$dbname_outdoor;charset=utf8mb4", $username, $password);
    $pdo_outdoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn_outdoor = mysqli_connect($host, $username, $password, $dbname_outdoor);
} catch(Exception $e) {
    // Koneksi outdoor dibiarkan null jika gagal, agar tidak mematikan program jika hanya mengakses indoor
}

// 2. KONEKSI DATABASE INDOOR
try {
    $pdo_indoor = new PDO("mysql:host=$host;dbname=$dbname_indoor;charset=utf8mb4", $username, $password);
    $pdo_indoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn_indoor = mysqli_connect($host, $username, $password, $dbname_indoor);
    
    // Tambahkan pengecekan untuk memastikan koneksi indoor berhasil
    if ($conn_indoor) {
        // Set karakter set ke UTF-8
        mysqli_set_charset($conn_indoor, "utf8mb4");
    }
} catch(Exception $e) {
    // Koneksi indoor dibiarkan null jika gagal
    error_log("Koneksi indoor gagal: " . $e->getMessage());
}

// Untuk kompatibilitas file lama, set default ke outdoor jika tersedia, jika tidak ke indoor
$pdo = $pdo_outdoor ? $pdo_outdoor : $pdo_indoor;
$conn = $conn_outdoor ? $conn_outdoor : $conn_indoor;

// Cek jika kedua koneksi gagal sama sekali
if (!$pdo_outdoor && !$pdo_indoor) {
    die("Error: Semua koneksi database gagal. Silakan periksa kredensial database pada file koneksi.php Anda.");
}

// ============================================================
// FUNGSI UNTUK MEMASTIKAN TABEL data_sensor SESUAI DENGAN indoor.sql
// ============================================================
function ensureIndoorTableStructure($conn_indoor) {
    if (!$conn_indoor) return false;
    
    // Cek apakah tabel data_sensor ada
    $checkTable = mysqli_query($conn_indoor, "SHOW TABLES LIKE 'data_sensor'");
    if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
        // Buat tabel sesuai dengan struktur indoor.sql
        $createTable = "CREATE TABLE IF NOT EXISTS data_sensor (
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
        return mysqli_query($conn_indoor, $createTable);
    }
    
    // Cek dan tambahkan kolom jika ada yang hilang
    $columns = [];
    $colQuery = mysqli_query($conn_indoor, "SHOW COLUMNS FROM data_sensor");
    while ($col = mysqli_fetch_assoc($colQuery)) {
        $columns[] = $col['Field'];
    }
    
    $requiredColumns = ['api', 'asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'rssi', 'ip_address', 'latitude', 'longitude', 'timestamp'];
    $altered = false;
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $type = 'FLOAT DEFAULT NULL';
            if ($col === 'rssi') $type = 'INT(11) DEFAULT NULL';
            else if ($col === 'ip_address') $type = 'VARCHAR(50) DEFAULT NULL';
            else if ($col === 'latitude') $type = 'DECIMAL(10,8) DEFAULT NULL';
            else if ($col === 'longitude') $type = 'DECIMAL(11,8) DEFAULT NULL';
            else if ($col === 'timestamp') $type = 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
            
            mysqli_query($conn_indoor, "ALTER TABLE data_sensor ADD COLUMN $col $type");
            $altered = true;
        }
    }
    
    return true;
}

// Jalankan fungsi untuk memastikan struktur tabel indoor
if ($conn_indoor) {
    ensureIndoorTableStructure($conn_indoor);
}

// ============================================================
// FUNGSI UNTUK MEMASTIKAN TABEL batas_sensor ADA
// ============================================================
function ensureSensorLimitTable($conn_indoor) {
    if (!$conn_indoor) return false;
    
    $checkTable = mysqli_query($conn_indoor, "SHOW TABLES LIKE 'batas_sensor'");
    if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
        $createTable = "CREATE TABLE batas_sensor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_sensor VARCHAR(50) NOT NULL,
            nilai_alarm DECIMAL(10,2) NOT NULL,
            satuan VARCHAR(20) NOT NULL,
            batas_min DECIMAL(10,2),
            batas_max DECIMAL(10,2),
            deskripsi TEXT,
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn_indoor, $createTable);
        
        // Insert data default sensor
        $defaultSensors = [
            ['API', 1, 'Status', 0, 1, 'Deteksi api (0=Aman, 1=Terdeteksi Api)'],
            ['ASAP', 70, '%', 0, 100, 'Deteksi asap (0=Normal, 100=Tinggi)'],
            ['SUHU', 45, '°C', 20, 60, 'Suhu lingkungan'],
            ['KELEMBAPAN', 85, '%', 30, 95, 'Kelembapan udara'],
            ['TEGANGAN', 190, 'V', 150, 250, 'Tegangan listrik'],
            ['ARUS', 15, 'A', 0, 20, 'Arus listrik']
        ];
        
        foreach ($defaultSensors as $sensor) {
            $stmt = mysqli_prepare($conn_indoor, "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sdsdds", $sensor[0], $sensor[1], $sensor[2], $sensor[3], $sensor[4], $sensor[5]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        return true;
    }
    return true;
}

// Jalankan fungsi untuk memastikan tabel batas_sensor
if ($conn_indoor) {
    ensureSensorLimitTable($conn_indoor);
}

// ============================================================
// FUNGSI UNTUK MEMASTIKAN TABEL login ADA
// ============================================================
function ensureLoginTable($conn_indoor) {
    if (!$conn_indoor) return false;
    
    $checkTable = mysqli_query($conn_indoor, "SHOW TABLES LIKE 'login'");
    if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
        $createTable = "CREATE TABLE login (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') DEFAULT 'user',
            status ENUM('pending','approved','rejected') DEFAULT 'approved',
            created_at DATETIME,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn_indoor, $createTable);
        
        // Insert default admin jika belum ada
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($conn_indoor, "INSERT INTO login (username, password, role, status, created_at) VALUES ('admin', '$defaultPassword', 'admin', 'approved', NOW())");
        return true;
    }
    return true;
}

// Jalankan fungsi untuk memastikan tabel login
if ($conn_indoor) {
    ensureLoginTable($conn_indoor);
}

// ============================================================
// FUNGSI UNTUK MENDAPATKAN DATA SENSOR TERBARU (INDOOR)
// ============================================================
function getLatestSensorDataIndoor($conn_indoor) {
    if (!$conn_indoor) return null;
    
    $query = "SELECT 
                id, 
                timestamp as waktu, 
                api, 
                asap, 
                suhu, 
                kelembapan, 
                tegangan, 
                arus, 
                rssi,
                ip_address,
                latitude,
                longitude
              FROM data_sensor 
              ORDER BY timestamp DESC 
              LIMIT 1";
    
    $result = mysqli_query($conn_indoor, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// ============================================================
// FUNGSI UNTUK MENDAPATKAN SEMUA DATA SENSOR (INDOOR)
// ============================================================
function getAllSensorDataIndoor($conn_indoor, $limit = 100) {
    if (!$conn_indoor) return [];
    
    $query = "SELECT 
                id, 
                timestamp as waktu, 
                api, 
                asap, 
                suhu, 
                kelembapan, 
                tegangan, 
                arus, 
                rssi,
                ip_address,
                latitude,
                longitude
              FROM data_sensor 
              ORDER BY timestamp DESC 
              LIMIT $limit";
    
    $result = mysqli_query($conn_indoor, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}
?>