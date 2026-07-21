<?php
// ============================================================
// FILE KONEKSI DATABASE - FIREDETECTOR
// ============================================================

// Deteksi secara otomatis apakah sedang berjalan di Localhost atau di Domain/Hosting Live
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

// ============================================================
// 1. KREDENSIAL BERDASARKAN LOKASI
// ============================================================
if ($is_localhost) {
    // ==========================================
    // KREDENSIAL DATABASE LOCALHOST (XAMPP)
    // ==========================================
    $host = "localhost";
    $user = "ta_user";          // Default XAMPP
    $pass = "rahasiaTA123!";    // Default XAMPP kosong
    $dbname_outdoor = "outdoor";
    $dbname_indoor = "indoor";
    
} else if (strpos($_SERVER['HTTP_HOST'], 'inovasijre.com') !== false) {
    // ==========================================================
    // KREDENSIAL DATABASE LIVE DOMAIN (inovasijre.com)
    // ==========================================================
    $host = "localhost"; 
    $user = "ta_user";       // Sesuaikan dengan user database Anda di cPanel
    $pass = "rahasiaTA123!"; // Masukkan password user database Anda
    $dbname_outdoor = "outdoor";
    $dbname_indoor = "indoor";
    
} else {
    // ==========================================================
    // KREDENSIAL DATABASE DOMAIN LAIN (PRODUCTION)
    // ==========================================================
    $host = "localhost"; 
    $user = "ta_user"; 
    $pass = "rahasiaTA123!"; 
    $dbname_outdoor = "outdoor"; 
    $dbname_indoor = "indoor"; 
}

// ============================================================
// 2. INISIALISASI VARIABEL KONEKSI
// ============================================================
$pdo_outdoor = null;
$conn_outdoor = null;
$pdo_indoor = null;
$conn_indoor = null;  // <-- VARIABEL UTAMA UNTUK KONEKSI INDOOR (WAJIB)

// ============================================================
// 3. KONEKSI DATABASE OUTDOOR
// ============================================================
try {
    $pdo_outdoor = new PDO("mysql:host=$host;dbname=$dbname_outdoor;charset=utf8mb4", $user, $pass);
    $pdo_outdoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn_outdoor = mysqli_connect($host, $user, $pass, $dbname_outdoor);
    if ($conn_outdoor) {
        mysqli_set_charset($conn_outdoor, "utf8mb4");
    }
} catch(Exception $e) {
    // Koneksi outdoor dibiarkan null jika gagal
    error_log("Koneksi outdoor gagal: " . $e->getMessage());
}

// ============================================================
// 4. KONEKSI DATABASE INDOOR (UTAMA)
// ============================================================
try {
    // Koneksi PDO untuk keperluan query kompleks
    $pdo_indoor = new PDO("mysql:host=$host;dbname=$dbname_indoor;charset=utf8mb4", $user, $pass);
    $pdo_indoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ==========================================================
    // KONEKSI MYSQLI UNTUK KOMPATIBILITAS (WAJIB $conn_indoor)
    // ==========================================================
    $conn_indoor = mysqli_connect($host, $user, $pass, $dbname_indoor);
    
    // Cek koneksi mysqli
    if (mysqli_connect_errno()) {
        error_log("Koneksi database indoor gagal (mysqli): " . mysqli_connect_error());
        $conn_indoor = null;
    } else {
        // Set charset ke UTF-8
        mysqli_set_charset($conn_indoor, "utf8mb4");
    }
    
} catch(Exception $e) {
    // Koneksi indoor dibiarkan null jika gagal
    error_log("Koneksi indoor gagal (PDO): " . $e->getMessage());
    $conn_indoor = null;
}

// ============================================================
// 5. KOMPATIBILITAS UNTUK FILE LAMA
// ============================================================
// Untuk kompatibilitas file lama, set default ke outdoor jika tersedia, jika tidak ke indoor
$pdo = $pdo_outdoor ? $pdo_outdoor : $pdo_indoor;
$conn = $conn_outdoor ? $conn_outdoor : $conn_indoor;

// ============================================================
// 6. CEK KONEKSI
// ============================================================
if (!$pdo_outdoor && !$pdo_indoor) {
    die("Error: Semua koneksi database gagal. Silakan periksa kredensial database pada file koneksi.php Anda.");
}

// Cek khusus koneksi indoor (yang paling penting)
if (!$conn_indoor) {
    // Tampilkan pesan error yang lebih informatif
    die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 6px; margin: 20px;'>
        <h3>⚠️ Error: Koneksi ke Database INDOOR Gagal!</h3>
        <p><strong>Detail:</strong></p>
        <ul>
            <li><strong>Host:</strong> {$host}</li>
            <li><strong>Database:</strong> {$dbname_indoor}</li>
            <li><strong>Username:</strong> {$user}</li>
            <li><strong>Password:</strong> " . ($pass ? '********' : '(kosong)') . "</li>
        </ul>
        <p>Pastikan:</p>
        <ol>
            <li>MySQL sedang berjalan (untuk XAMPP: nyalakan MySQL di Control Panel)</li>
            <li>Database <strong>{$dbname_indoor}</strong> sudah dibuat di phpMyAdmin</li>
            <li>Username dan password sesuai dengan yang ada di phpMyAdmin</li>
            <li>Tabel <strong>data_sensor</strong> sudah di-import dari file indoor.sql</li>
        </ol>
        <hr>
        <p style='font-size: 12px; color: #666;'>Error MySQL: " . mysqli_connect_error() . "</p>
    </div>");
}

// ============================================================
// 7. FUNGSI UNTUK MEMASTIKAN STRUKTUR TABEL
// ============================================================

/**
 * Memastikan tabel data_sensor memiliki struktur yang benar sesuai indoor.sql
 */
function ensureIndoorTableStructure($conn) {
    if (!$conn) return false;
    
    // Cek apakah tabel data_sensor ada
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'data_sensor'");
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
        return mysqli_query($conn, $createTable);
    }
    
    // Cek dan tambahkan kolom jika ada yang hilang
    $columns = [];
    $colQuery = mysqli_query($conn, "SHOW COLUMNS FROM data_sensor");
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
            
            mysqli_query($conn, "ALTER TABLE data_sensor ADD COLUMN $col $type");
            $altered = true;
        }
    }
    
    return true;
}

/**
 * Memastikan tabel batas_sensor ada dengan data default
 */
function ensureSensorLimitTable($conn) {
    if (!$conn) return false;
    
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batas_sensor'");
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
        mysqli_query($conn, $createTable);
        
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
            $stmt = mysqli_prepare($conn, "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
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

/**
 * Memastikan tabel login ada dengan akun admin default
 */
function ensureLoginTable($conn) {
    if (!$conn) return false;
    
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'login'");
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
        mysqli_query($conn, $createTable);
        
        // Insert default admin
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO login (username, password, role, status, created_at) VALUES ('admin', '$defaultPassword', 'admin', 'approved', NOW())");
        return true;
    }
    return true;
}

// ============================================================
// 8. JALANKAN FUNGSI UNTUK MEMASTIKAN TABEL
// ============================================================
if ($conn_indoor) {
    ensureIndoorTableStructure($conn_indoor);
    ensureSensorLimitTable($conn_indoor);
    ensureLoginTable($conn_indoor);
}

// ============================================================
// 9. FUNGSI HELPER UNTUK AKSES DATA
// ============================================================

/**
 * Mendapatkan data sensor terbaru dari database indoor
 */
function getLatestSensorDataIndoor($conn) {
    if (!$conn) return null;
    
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
    
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Mendapatkan semua data sensor dari database indoor
 */
function getAllSensorDataIndoor($conn, $limit = 100) {
    if (!$conn) return [];
    
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
    
    $result = mysqli_query($conn, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

// ============================================================
// 10. STATUS KONEKSI (UNTUK DEBUGGING)
// ============================================================
// Uncomment untuk debugging
// echo "Status Koneksi Indoor: " . ($conn_indoor ? 'Berhasil ✅' : 'Gagal ❌');
?>