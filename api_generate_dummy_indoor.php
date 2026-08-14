<?php
date_default_timezone_set('Asia/Makassar');
require_once 'koneksi.php';
header('Content-Type: application/json');

/** @var PDO $pdo_indoor */
if (!$pdo_indoor) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database Indoor tidak tersedia.']);
    exit;
}

try {
    // 1. Pastikan kolom is_dummy ada di tabel data_sensor
    $colCheck = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if (!$colCheck || $colCheck->rowCount() == 0) {
        @$pdo_indoor->exec("ALTER TABLE data_sensor ADD COLUMN is_dummy INT DEFAULT 0");
    }

    $count = isset($_GET['count']) ? max(1, min(100, intval($_GET['count']))) : 1;
    $now = time();
    $inserted = 0;

    for ($i = $count - 1; $i >= 0; $i--) {
        $t = date('Y-m-d H:i:s', $now - ($i * 15));
        $d_api = (rand(1, 100) > 92) ? 100 : 0;
        $d_asap = rand(15, 85);
        $d_suhu = rand(26, 33);
        $d_kelembapan = rand(50, 75);
        $d_tegangan = rand(2180, 2220) / 10;
        $d_arus = rand(20, 45) / 10;
        $d_rssi = rand(-75, -55);
        if ($d_api > 0) { 
            $d_suhu += 15; 
            $d_kelembapan -= 20; 
            $d_asap += 40; 
        }

        $stmt_ins = $pdo_indoor->prepare("
            INSERT INTO data_sensor (timestamp, api, asap, suhu, kelembapan, tegangan, arus, rssi, ip_address, is_dummy)
            VALUES (:waktu, :api, :asap, :suhu, :kelembapan, :tegangan, :arus, :rssi, '127.0.0.1 (Simulasi)', 1)
        ");
        $stmt_ins->execute([
            ':waktu' => $t,
            ':api' => $d_api,
            ':asap' => $d_asap,
            ':suhu' => $d_suhu,
            ':kelembapan' => $d_kelembapan,
            ':tegangan' => $d_tegangan,
            ':arus' => $d_arus,
            ':rssi' => $d_rssi
        ]);
        $inserted++;
    }

    // Ambil info storage terbaru
    $storage = get_sensor_storage_info($pdo_indoor, 'indoor');

    echo json_encode([
        'status' => 'success',
        'inserted' => $inserted,
        'storage' => $storage
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
