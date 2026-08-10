<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'koneksi.php';

$conn = isset($conn_indoor) ? $conn_indoor : null;

if (!$conn) {
    echo json_encode([
        "error" => true,
        "message" => "Koneksi database Indoor gagal."
    ]);
    exit();
}

$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_monitoring'");

if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS lokasi_monitoring (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_alat VARCHAR(50) NOT NULL,
        nama_lokasi VARCHAR(100) DEFAULT NULL,
        latitude DECIMAL(10,8) NOT NULL,
        longitude DECIMAL(11,8) NOT NULL,
        interval_kirim INT DEFAULT 15,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $createTable)) {
        $defaultLocations = [
            ['LOK-001', 'Gedung Elektro Poltekba', -1.20249, 116.88708],
            ['LOK-002', 'Ruang Server Gedung Elektro Poltekba', -1.20250, 116.88710],
        ];
        
        foreach ($defaultLocations as $loc) {
            $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_monitoring (id_alat, nama_lokasi, latitude, longitude, interval_kirim) VALUES (?, ?, ?, ?, 15)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssdd", $loc[0], $loc[1], $loc[2], $loc[3]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$query = "SELECT id, id_alat, nama_lokasi, latitude, longitude, interval_kirim, updated_at as last_update 
          FROM lokasi_monitoring 
          ORDER BY id ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        "error" => true,
        "message" => "Query lokasi indoor gagal: " . mysqli_error($conn)
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
        'interval_kirim' => (int)($row['interval_kirim'] ?? 15),
        'interval_detik' => (int)($row['interval_kirim'] ?? 15),
        'last_update' => $row['last_update']
    ];
}

echo json_encode([
    "error" => false,
    "data" => $locations,
    "total" => count($locations)
]);
?>
