<?php
require_once 'koneksi.php';
header('Content-Type: application/json');

$device = isset($_GET['device']) ? strtolower($_GET['device']) : 'outdoor';
if ($device === 'indoor' && isset($pdo_indoor)) {
    $current_pdo = $pdo_indoor;
} else {
    $current_pdo = isset($pdo_outdoor) ? $pdo_outdoor : null;
}

if (!$current_pdo) {
    echo json_encode([
        'waktu' => date('H:i:s'),
        'api' => 'Aman',
        'asap' => 'Normal',
        'co' => 0.0,
        'suhu' => '0.0',
        'kelembapan' => '0.0',
        'tegangan' => '0.0',
        'arus' => '0.00',
        'daya' => '0.0',
        'angin' => '0.0',
        'arah' => '-',
        'status' => 'Offline (No Connection)',
        'rssi' => '-',
        'ip' => '-',
        'isDanger' => false
    ]);
    exit();
}

$sql = "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1";

try {
    $stmt = $current_pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $co = isset($data['co']) ? (float)$data['co'] : 0.0;
        $raw_asap = isset($data['asap']) ? $data['asap'] : "Normal";
        $asap = "Normal";

        if (is_numeric($raw_asap)) {
            $f_asap = (float)$raw_asap;
            if ($f_asap > ($f_asap > 1 ? 50 : 0.5)) {
                $asap = "Tinggi";
            } else if ($f_asap > ($f_asap > 1 ? 25 : 0.25)) {
                $asap = "Sedang";
            } else {
                $asap = "Normal";
            }
        } else {
            $str_asap = trim((string)$raw_asap);
            if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) {
                $asap = "Tinggi";
            } else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) {
                $asap = "Sedang";
            } else {
                $asap = "Normal";
            }
        }

        $api_val = isset($data['api']) ? $data['api'] : 'Aman';
        if (is_numeric($api_val)) {
            $apiStatus = ((float)$api_val > 0.5) ? "Terdeteksi Api" : "Aman";
        } else {
            $apiStatus = (strcasecmp(trim($api_val), 'Terdeteksi Api') === 0 || strcasecmp(trim($api_val), 'Bahaya') === 0) ? "Terdeteksi Api" : "Aman";
        }

        $isDanger = ($apiStatus === "Terdeteksi Api" || $asap === "Tinggi" || $co > 50);

        echo json_encode([
            'waktu'      => isset($data['timestamp']) ? date('H:i:s', strtotime($data['timestamp'])) : date('H:i:s'),
            'api'        => $apiStatus,
            'asap'       => $asap,
            'suhu'       => isset($data['suhu']) ? number_format((float)$data['suhu'], 1) : "0.0",
            'kelembapan' => isset($data['kelembapan']) ? number_format((float)$data['kelembapan'], 1) : "0.0",
            'tegangan'   => isset($data['tegangan']) ? number_format((float)$data['tegangan'], 1) : "0.0",
            'arus'       => isset($data['arus']) ? number_format((float)$data['arus'], 2) : "0.00",
            'daya'       => isset($data['daya']) ? number_format((float)$data['daya'], 1) : "0.0",
            'angin'      => isset($data['kecepatan_angin']) ? number_format((float)$data['kecepatan_angin'], 1) : "0.0",
            'arah'       => isset($data['arah_angin']) ? $data['arah_angin'] : "-",
            'co'         => $co,
            'status'     => 'Online',
            'rssi'       => isset($data['rssi']) ? $data['rssi'] : "-",
            'ip'         => !empty($data['ip_address']) ? $data['ip_address'] : "-",
            'isDanger'   => $isDanger
        ]);
        } else {
            // Jika tabel ada tapi belum ada datanya
            echo json_encode([
                'waktu'      => date('H:i:s'),
                'asap'       => 'Normal',
                'suhu'       => '0.0',
                'kelembapan' => '0.0',
                'tegangan'   => '0.0',
                'arus'       => '0.0',
                'daya'       => '0.0',
                'angin'      => '0.0',
                'arah'       => '-',
                'co'         => 0.0,
                'status'     => 'Offline (No Data)',
                'rssi'       => 0,
                'ip'         => '-',
                'isDanger'   => false
            ]);
        }
    } catch (PDOException $e) {
        // Jika tabel tidak ditemukan, return error gracefully
        echo json_encode([
            'waktu'      => date('H:i:s'),
            'asap'       => 'Normal',
            'suhu'       => '0.0',
            'kelembapan' => '0.0',
            'tegangan'   => '0.0',
            'arus'       => '0.0',
            'daya'       => '0.0',
            'angin'      => '0.0',
            'arah'       => '-',
            'co'         => 0.0,
            'status'     => 'Error: Tabel Belum Ada',
            'rssi'       => 0,
            'ip'         => '-',
            'isDanger'   => false
        ]);
    }
    ?>