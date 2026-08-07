<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

// Gunakan koneksi PDO indoor dari koneksi.php
$pdo = isset($pdo_indoor) ? $pdo_indoor : null;

if (!$pdo) {
    // Fallback koneksi manual jika $pdo_indoor belum terdefinisi
    $host = "localhost";
    $username = "ta_user";
    $password = "rahasiaTA123!";
    $dbname = "indoor";
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $ex) {
            echo json_encode(['waktu' => [], 'suhu' => [], 'error' => true, 'message' => $ex->getMessage()]);
            exit();
        }
    }
}

// Tangkap ID Lokasi & Parameter Dummy (Default 1 jika tidak ada parameter)
$id_lokasi = isset($_GET['id_lokasi']) ? $_GET['id_lokasi'] : 1;
$is_dummy = isset($_GET['is_dummy']) ? (int)$_GET['is_dummy'] : 0;

$response = [
    'waktu' => [],
    'suhu' => []
];

// 1. Jika ini data dummy, hasilkan data simulasi grafik
if ($is_dummy == 1) {
    $now = time();
    for ($i = 14; $i >= 0; $i--) {
        $t = $now - ($i * 3);
        $response['waktu'][] = date('H:i:s', $t);
        $response['suhu'][] = round(24.0 + (mt_rand(0, 300) / 100), 1); // Acak 24.0 - 27.0 °C
    }
    echo json_encode($response);
    exit();
}

// 2. Jika ini data asli (Live), ambil 15 data sensor riwayat dari tabel data_sensor
try {
    $sql = "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 15";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Di-reverse agar tampil di grafik berjalan dari kiri ke kanan (waktu lama ke baru)
    $data = array_reverse($data);

    foreach ($data as $row) {
        $waktuRaw = $row['timestamp'] ?? ($row['tanggal_dan_waktu'] ?? ($row['waktu'] ?? 'now'));
        $response['waktu'][] = date('H:i:s', strtotime($waktuRaw));
        $response['suhu'][] = isset($row['suhu']) ? (float)$row['suhu'] : 0;
    }
} catch (Exception $e) {
    // Fallback jika query bermasalah
    $now = time();
    for ($i = 14; $i >= 0; $i--) {
        $t = $now - ($i * 3);
        $response['waktu'][] = date('H:i:s', $t);
        $response['suhu'][] = 25.0;
    }
}

echo json_encode($response);
?>
