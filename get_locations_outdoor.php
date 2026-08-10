<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'koneksi.php';

$conn = isset($conn_outdoor) ? $conn_outdoor : null;

if (!$conn) {
    echo json_encode([
        "error" => true,
        "message" => "Koneksi database Outdoor gagal."
    ]);
    exit();
}

$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_alat'");

if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS lokasi_alat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_alat VARCHAR(50) NOT NULL,
        nama_lokasi VARCHAR(100) DEFAULT NULL,
        latitude DECIMAL(10,8) NOT NULL,
        longitude DECIMAL(11,8) NOT NULL,
        interval_detik INT DEFAULT 30,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $createTable)) {
        $defaultLocations = [
            ['OUT-001', 'Area Hutan Poltekba (Alat Utama)', -1.20249, 116.88708, 30],
            ['OUT-002', 'Zona Utara Poltekba', -1.20250, 116.88710, 15],
        ];
        
        foreach ($defaultLocations as $loc) {
            $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_alat (id_alat, nama_lokasi, latitude, longitude, interval_detik) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssddi", $loc[0], $loc[1], $loc[2], $loc[3], $loc[4]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
} else {
    // Pastikan kolom interval_detik ada
    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_alat LIKE 'interval_detik'");
    if (!$checkCol || mysqli_num_rows($checkCol) == 0) {
        @mysqli_query($conn, "ALTER TABLE lokasi_alat ADD COLUMN interval_detik INT DEFAULT 30");
    }
}

$query = "SELECT id, id_alat, nama_lokasi, latitude, longitude, interval_detik, updated_at as last_update 
          FROM lokasi_alat 
          ORDER BY id ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        "error" => true,
        "message" => "Query lokasi outdoor gagal: " . mysqli_error($conn)
    ]);
    exit();
}

$locations = [];

while ($row = mysqli_fetch_assoc($result)) {
    $locations[] = [
        'id' => (int)$row['id'],
        'id_alat' => $row['id_alat'],
        'nama_lokasi' => $row['nama_lokasi'] ?? '',
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'interval_detik' => (int)($row['interval_detik'] ?? 30),
        'interval_kirim' => (int)($row['interval_detik'] ?? 30),
        'last_update' => $row['last_update']
    ];
}

echo json_encode([
    "error" => false,
    "data" => $locations,
    "total" => count($locations)
]);
?>
