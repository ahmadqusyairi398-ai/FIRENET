<?php
// Deteksi secara otomatis apakah sedang berjalan di Localhost atau di Domain/Hosting Live
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_localhost) {
    // ==========================================
    // 1. KREDENSIAL DATABASE LOCALHOST (LOKAL)
    // ==========================================
    $host = "localhost";
    $username = "ta_user";
    $password = "rahasiaTA123!";
    $dbname_outdoor = "outdoor";
    $dbname_indoor = "indoor";
} else if (strpos($_SERVER['HTTP_HOST'], 'inovasijre.com') !== false) {
    // ==========================================================
    // 2. KREDENSIAL DATABASE LIVE DOMAIN (inovasijre.com)
    // ==========================================================
    // Silakan sesuaikan dengan database yang Anda buat di cPanel/Hosting Anda.
    // Biasanya di cPanel terdapat prefix nama pengguna, contoh: inovasij_firenet
    $host = "localhost"; 
    $username = "ta_user"; // UBAH: Sesuaikan dengan nama user database Anda di cPanel
    $password = "rahasiaTA123!";   // UBAH: Masukkan password user database Anda
    $dbname_outdoor = "outdoor"; // UBAH: Sesuaikan dengan nama database outdoor Anda
    $dbname_indoor = "firenet";   // UBAH: Sesuaikan dengan nama database indoor Anda
} else {
    // ==========================================================
    // 3. KREDENSIAL DATABASE DOMAIN LAIN (PRODUCTION)
    // ==========================================================
    $host = "localhost"; 
    $username = "ta_user"; 
    $password = "rahasiaTA123!"; 
    $dbname_outdoor = "outdoor"; 
    $dbname_indoor = "firenet"; 
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
?>
