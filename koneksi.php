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
    $dbname_indoor = "firenet";
} else {
    // ==========================================
    // 2. KREDENSIAL DATABASE DOMAIN (PRODUCTION)
    // ==========================================
    // SILAKAN UBAH bagian ini sesuai dengan data database di cPanel/Hosting Anda:
    $host = "localhost"; 
    $username = "ta_user"; // Masukkan username database hosting Anda di sini
    $password = "rahasiaTA123!"; // Masukkan password database hosting Anda di sini
    $dbname_outdoor = "outdoor"; // Masukkan nama database outdoor hosting Anda di sini
    $dbname_indoor = "firenet"; // Masukkan nama database indoor hosting Anda di sini
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
