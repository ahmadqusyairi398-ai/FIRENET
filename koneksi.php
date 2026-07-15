<?php
$host = "localhost";
$username = "ta_user";
$password = "rahasiaTA123!";

// Koneksi ke database OUTDOOR (Punya Anda)
$dbname_outdoor = "outdoor";
try {
    $pdo_outdoor = new PDO("mysql:host=$host;dbname=$dbname_outdoor;charset=utf8mb4", $username, $password);
    $pdo_outdoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn_outdoor = mysqli_connect($host, $username, $password, $dbname_outdoor);
} catch(PDOException $e) {
    die("Koneksi PDO Outdoor gagal: " . $e->getMessage());
}

// Koneksi ke database INDOOR (Punya Teman - FIRENET)
$dbname_indoor = "firenet";
try {
    $pdo_indoor = new PDO("mysql:host=$host;dbname=$dbname_indoor;charset=utf8mb4", $username, $password);
    $pdo_indoor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn_indoor = mysqli_connect($host, $username, $password, $dbname_indoor);
} catch(PDOException $e) {
    die("Koneksi PDO Indoor gagal: " . $e->getMessage());
}

// Untuk kompatibilitas file lama (login, tabel, dsb), set default ke outdoor
$pdo = $pdo_outdoor;
$conn = $conn_outdoor;
?>
