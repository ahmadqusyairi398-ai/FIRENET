<?php
// Set timezone default ke Asia/Makassar (WITA / UTC+8)
date_default_timezone_set('Asia/Makassar');

// Deteksi secara otomatis apakah sedang berjalan di Localhost atau di Domain/Hosting Live
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_localhost = ($http_host === 'localhost' || $http_host === '127.0.0.1');

if ($is_localhost) {
    // ==========================================
    // 1. KREDENSIAL DATABASE LOCALHOST (LOKAL)
    // ==========================================
    $host = "localhost";
    $username = "ta_user";
    $password = "rahasiaTA123!";
    $dbname_outdoor = "outdoor";
    $dbname_indoor = "indoor";
} else if (strpos($http_host, 'inovasijre.com') !== false) {
    // ==========================================================
    // 2. KREDENSIAL DATABASE LIVE DOMAIN (inovasijre.com)
    // ==========================================================
    // Silakan sesuaikan dengan database yang Anda buat di cPanel/Hosting Anda.
    // Biasanya di cPanel terdapat prefix nama pengguna, contoh: inovasij_firenet
    $host = "localhost"; 
    $username = "ta_user"; // UBAH: Sesuaikan dengan nama user database Anda di cPanel
    $password = "rahasiaTA123!";   // UBAH: Masukkan password user database Anda
    $dbname_outdoor = "outdoor"; // UBAH: Sesuaikan dengan nama database outdoor Anda
    $dbname_indoor = "indoor";   // UBAH: Sesuaikan dengan nama database indoor Anda
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
    @$pdo_outdoor->exec("SET time_zone = '+08:00'");
    
    $conn_outdoor = mysqli_connect($host, $username, $password, $dbname_outdoor);
    if ($conn_outdoor) {
        @mysqli_query($conn_outdoor, "SET time_zone = '+08:00'");
    }
} catch(Exception $e) {
    // Koneksi outdoor dibiarkan null jika gagal, agar tidak mematikan program jika hanya mengakses indoor
}

// 2. KONEKSI DATABASE INDOOR
try {
    $pdo_indoor = new PDO("mysql:host=$host;dbname=$dbname_indoor;charset=utf8mb4", $username, $password);
    $pdo_indoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    @$pdo_indoor->exec("SET time_zone = '+08:00'");
    
    $conn_indoor = mysqli_connect($host, $username, $password, $dbname_indoor);
    if ($conn_indoor) {
        @mysqli_query($conn_indoor, "SET time_zone = '+08:00'");
    }
} catch(Exception $e) {
    // Koneksi indoor dibiarkan null jika gagal
}

// Untuk kompatibilitas file lama, set default ke outdoor jika tersedia, jika tidak ke indoor
$pdo = $pdo_outdoor ? $pdo_outdoor : $pdo_indoor;
$conn = $conn_outdoor ? $conn_outdoor : $conn_indoor;

// Cek jika kedua koneksi gagal sama sekali
if (!$pdo_outdoor && !$pdo_indoor) {
    die("Error: Semua koneksi database gagal. Silakan periksa kredensial database pada file koneksi.php Anda.");
}

// =========================================================================
// HELPER KAPASITAS & UKURAN DATA (Real vs Dummy)
// =========================================================================
if (!function_exists('format_storage_size')) {
    function format_storage_size($bytes, $precision = 2) {
        if ($bytes <= 0) return "0 B";
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        if ($pow === 0) {
            return number_format($bytes, 0) . ' B';
        }
        return number_format($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('get_sensor_storage_info')) {
    function get_sensor_storage_info($db_handle, $dbname = 'indoor') {
        $info = [
            'real_bytes' => 0,
            'dummy_bytes' => 0,
            'count_real' => 0,
            'count_dummy' => 0,
            'real_formatted' => '0 B',
            'dummy_formatted' => '0 B'
        ];
        if (!$db_handle) return $info;

        try {
            $is_pdo = ($db_handle instanceof PDO);
            
            // 1. Dapatkan Rata-rata ukuran baris (Avg Row Length) & total data length
            $sql_info = "SELECT AVG_ROW_LENGTH, DATA_LENGTH, INDEX_LENGTH, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'data_sensor'";
            $avg_row_length = 0;
            $total_bytes = 0;

            if ($is_pdo) {
                $q_info = $db_handle->query($sql_info);
                $table_stat = $q_info ? $q_info->fetch(PDO::FETCH_ASSOC) : null;
                if ($table_stat) {
                    $avg_row_length = (int)($table_stat['AVG_ROW_LENGTH'] ?? 0);
                    $total_bytes = (int)($table_stat['DATA_LENGTH'] ?? 0) + (int)($table_stat['INDEX_LENGTH'] ?? 0);
                }
            } else {
                $q_info = mysqli_query($db_handle, $sql_info);
                if ($q_info && $table_stat = mysqli_fetch_assoc($q_info)) {
                    $avg_row_length = (int)($table_stat['AVG_ROW_LENGTH'] ?? 0);
                    $total_bytes = (int)($table_stat['DATA_LENGTH'] ?? 0) + (int)($table_stat['INDEX_LENGTH'] ?? 0);
                }
            }

            // 2. Cek kolom is_dummy
            $hasDummyCol = false;
            $sql_col = "SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'";
            if ($is_pdo) {
                $colCheck = $db_handle->query($sql_col);
                $hasDummyCol = ($colCheck && $colCheck->rowCount() > 0);
            } else {
                $colCheck = mysqli_query($db_handle, $sql_col);
                $hasDummyCol = ($colCheck && mysqli_num_rows($colCheck) > 0);
            }

            $count_dummy = 0;
            $count_real = 0;

            if ($hasDummyCol) {
                $sql_dummy = "SELECT COUNT(*) FROM data_sensor WHERE is_dummy = 1";
                $sql_real = "SELECT COUNT(*) FROM data_sensor WHERE is_dummy = 0 OR is_dummy IS NULL";
                
                if ($is_pdo) {
                    $count_dummy = (int)($db_handle->query($sql_dummy)->fetchColumn() ?: 0);
                    $count_real = (int)($db_handle->query($sql_real)->fetchColumn() ?: 0);
                } else {
                    $q_dum = mysqli_query($db_handle, $sql_dummy);
                    $count_dummy = $q_dum ? (int)(mysqli_fetch_row($q_dum)[0] ?? 0) : 0;
                    $q_rel = mysqli_query($db_handle, $sql_real);
                    $count_real = $q_rel ? (int)(mysqli_fetch_row($q_rel)[0] ?? 0) : 0;
                }
            } else {
                $sql_real = "SELECT COUNT(*) FROM data_sensor";
                if ($is_pdo) {
                    $count_real = (int)($db_handle->query($sql_real)->fetchColumn() ?: 0);
                } else {
                    $q_rel = mysqli_query($db_handle, $sql_real);
                    $count_real = $q_rel ? (int)(mysqli_fetch_row($q_rel)[0] ?? 0) : 0;
                }
            }

            $total_rows = $count_dummy + $count_real;

            if ($avg_row_length <= 0 && $total_rows > 0 && $total_bytes > 0) {
                $avg_row_length = $total_bytes / $total_rows;
            } elseif ($avg_row_length <= 0 && $total_rows > 0) {
                $avg_row_length = 160;
            }

            $real_bytes = $count_real * $avg_row_length;
            $dummy_bytes = $count_dummy * $avg_row_length;

            $info['real_bytes'] = $real_bytes;
            $info['dummy_bytes'] = $dummy_bytes;
            $info['count_real'] = $count_real;
            $info['count_dummy'] = $count_dummy;
            $info['real_formatted'] = format_storage_size($real_bytes);
            $info['dummy_formatted'] = format_storage_size($dummy_bytes);
        } catch (Exception $e) {}

        return $info;
    }
}
?>
