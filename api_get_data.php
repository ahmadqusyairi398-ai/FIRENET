<?php
require_once 'koneksi.php';
header('Content-Type: application/json');

$device_id = isset($_GET['device']) ? $_GET['device'] : 'indoor';

if ($device_id === 'indoor') {
    $current_pdo = $pdo_indoor;
    $sql = "SELECT * FROM data_sensor WHERE device_id = 'indoor' ORDER BY id DESC LIMIT 1";
} else {
    $current_pdo = $pdo_outdoor;
    // Database outdoor tidak punya kolom device_id
    $sql = "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1";
}

try {
    $stmt = $current_pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        if ($device_id === 'indoor') {
            echo json_encode([
                'waktu' => date('H:i:s', strtotime($data['timestamp'])),
                'api' => (isset($data['api']) && $data['api'] > 0.5) ? "Terdeteksi Api" : "Aman",
                'asap' => (isset($data['asap']) && $data['asap'] > 0.5) ? "Tinggi" : "Normal",
                'suhu' => isset($data['suhu']) ? number_format((float)$data['suhu'], 1) : "0.0",
                'kelembapan' => isset($data['kelembapan']) ? number_format((float)$data['kelembapan'], 1) : "0.0",
                'tegangan' => isset($data['tegangan']) ? number_format((float)$data['tegangan'], 1) : "0.0",
                'arus' => isset($data['arus']) ? number_format((float)$data['arus'], 2) : "0.0",
                'status' => 'Online',
                'rssi' => isset($data['rssi']) ? $data['rssi'] : 0,
                'ip' => isset($data['ip_address']) ? $data['ip_address'] : "-",
                'isDanger' => ((isset($data['api']) && $data['api'] > 0.5) || (isset($data['asap']) && $data['asap'] > 0.5))
            ]);
        } else {
            // Outdoor
            $co = isset($data['co']) ? (float)$data['co'] : 0;
            $asap = (isset($data['asap']) && $data['asap'] > 0.5) ? "Tinggi" : "Normal";
            echo json_encode([
                'waktu' => date('H:i:s', strtotime($data['timestamp'])),
                'tegangan' => isset($data['tegangan']) ? number_format((float)$data['tegangan'], 1) : "0.0",
                'arus' => isset($data['arus']) ? number_format((float)$data['arus'], 2) : "0.0",
                'daya' => isset($data['daya']) ? number_format((float)$data['daya'], 1) : "0.0",
                'arah' => isset($data['arah_angin']) ? $data['arah_angin'] : "Utara",
                'asap' => $asap,
                'suhu' => isset($data['suhu']) ? number_format((float)$data['suhu'], 1) : "0.0",
                'kelembapan' => isset($data['kelembapan']) ? number_format((float)$data['kelembapan'], 1) : "0.0",
                'angin' => isset($data['kecepatan_angin']) ? number_format((float)$data['kecepatan_angin'], 1) : "0.0",
                'co' => $co,
                'status' => 'Online',
                'rssi' => isset($data['rssi']) ? $data['rssi'] : 0,
                'ip' => isset($data['ip_address']) ? $data['ip_address'] : "-",
                'isDanger' => ($asap === "Tinggi" || $co > 50)
            ]);
        }
    } else {
        // Jika tabel ada tapi belum ada datanya
        echo json_encode([
            'waktu' => date('H:i:s'),
            'api' => 'Aman',
            'asap' => 'Normal',
            'suhu' => '0.0',
            'kelembapan' => '0.0',
            'tegangan' => '0.0',
            'arus' => '0.0',
            'daya' => '0.0',
            'arah' => 'Utara',
            'angin' => '0.0',
            'co' => 0,
            'status' => 'Offline (No Data)',
            'rssi' => 0,
            'ip' => '-',
            'isDanger' => false
        ]);
    }
} catch (PDOException $e) {
    // Jika tabel tidak ditemukan, return error gracefully
    echo json_encode([
        'waktu' => date('H:i:s'),
        'api' => 'Aman',
        'asap' => 'Normal',
        'suhu' => '0.0',
        'kelembapan' => '0.0',
        'tegangan' => '0.0',
        'arus' => '0.0',
        'daya' => '0.0',
        'arah' => 'Utara',
        'angin' => '0.0',
        'co' => 0,
        'status' => 'Error: Tabel Belum Ada',
        'rssi' => 0,
        'ip' => '-',
        'isDanger' => false
    ]);
}
?>
