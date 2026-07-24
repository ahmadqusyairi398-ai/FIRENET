<?php
// Tentukan header agar output dibaca sebagai JSON
header('Content-Type: application/json');

// Aktifkan error reporting untuk debugging (opsional, bisa dihapus jika sudah production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================================================
// 1. KONEKSI DATABASE
// ================================================

// Panggil file koneksi.php untuk mendapatkan koneksi database
require_once 'koneksi.php';

// Gunakan koneksi indoor (variabel $conn_indoor dari koneksi.php)
$conn = isset($conn_indoor) ? $conn_indoor : null;

// Cek apakah koneksi berhasil
if (!$conn) {
    echo json_encode([
        "error" => true,
        "message" => "Koneksi database gagal. Pastikan database sudah terkonfigurasi dengan benar."
    ]);
    exit();
}

// ================================================
// 2. CEK APAKAH TABEL LOKASI_MONITORING ADA
// ================================================

$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_monitoring'");

if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
    // Jika tabel tidak ada, buat tabel terlebih dahulu
    $createTable = "CREATE TABLE IF NOT EXISTS lokasi_monitoring (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_alat VARCHAR(50) NOT NULL,
        latitude DECIMAL(10,8) NOT NULL,
        longitude DECIMAL(11,8) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $createTable)) {
        // Insert data default jika tabel baru dibuat
        $defaultLocations = [
            ['001', -1.20249, 116.88708],
            ['002', -1.20250, 116.88710],
        ];
        
        foreach ($defaultLocations as $loc) {
            $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_monitoring (id_alat, latitude, longitude) VALUES (?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sdd", $loc[0], $loc[1], $loc[2]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// ================================================
// 3. AMBIL SEMUA DATA LOKASI
// ================================================

$query = "SELECT id, id_alat, nama_lokasi, latitude, longitude, updated_at as last_update 
          FROM lokasi_monitoring 
          ORDER BY id ASC";

$result = mysqli_query($conn, $query);

// Cek apakah query berhasil
if (!$result) {
    echo json_encode([
        "error" => true,
        "message" => "Query gagal: " . mysqli_error($conn)
    ]);
    exit();
}

// Proses data hasil query
$locations = [];

while ($row = mysqli_fetch_assoc($result)) {
    $locations[] = [
        'id' => (int)$row['id'],
        'id_alat' => $row['id_alat'],
        'nama_lokasi' => $row['nama_lokasi'] ?? '',
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'last_update' => $row['last_update']
    ];
}

// ================================================
// 4. KIRIM RESPONSE JSON
// ================================================

echo json_encode([
    "error" => false,
    "data" => $locations,
    "total" => count($locations)
]);

// Tutup koneksi database
mysqli_close($conn);
?>