<?php
// Tentukan header agar output dibaca sebagai JSON
header('Content-Type: application/json');

// 1. Load koneksi database dari koneksi.php
require_once 'koneksi.php';

$conn = isset($conn_indoor) && $conn_indoor ? $conn_indoor : null;

if (!$conn) {
    // Fallback koneksi manual jika koneksi.php belum terdefinisi
    $conn = @mysqli_connect("localhost", "root", "", "indoor");
}

// Cek koneksi
if (!$conn || mysqli_connect_errno()) {
    echo json_encode([
        "error" => true,
        "message" => "Koneksi database indoor gagal: " . (mysqli_connect_error() ?: 'Unknown error')
    ]);
    exit();
}

// 2. Query Ambil Data Sensor Terbaru dari tabel data_sensor (database indoor)
$sql = "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);

// Ambil batas sensor (set points) dari database
$limit_suhu = 40.0;
$limit_kelembapan = 20.0;
$limit_tegangan = 240.0;
$limit_arus = 5.0;

$q_batas = @mysqli_query($conn, "SELECT nama_sensor, batas_max, batas_min FROM batas_sensor");
if ($q_batas && mysqli_num_rows($q_batas) > 0) {
    while ($r_b = mysqli_fetch_assoc($q_batas)) {
        $nama_b = strtolower(trim($r_b['nama_sensor']));
        if ($nama_b == 'suhu') $limit_suhu = (float)$r_b['batas_max'];
        if ($nama_b == 'kelembapan') $limit_kelembapan = (float)$r_b['batas_min'];
        if ($nama_b == 'tegangan listrik' || $nama_b == 'tegangan') $limit_tegangan = (float)$r_b['batas_max'];
        if ($nama_b == 'arus listrik' || $nama_b == 'arus') $limit_arus = (float)$r_b['batas_max'];
    }
}

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    
    // Cek selisih waktu data terakhir
    $waktu_raw = $row['timestamp'] ?? ($row['tanggal_dan_waktu'] ?? ($row['created_at'] ?? null));
    $timeout_seconds = 15;
    $is_online = false;
    if ($waktu_raw) {
        $last_time = strtotime($waktu_raw);
        if ($last_time > 0 && (time() - $last_time) <= $timeout_seconds) {
            $is_online = true;
        }
    }
    
    if ($is_online) {
        $apiValue = isset($row['api']) ? (float)$row['api'] : 0;
        $rawAsap = isset($row['asap']) ? $row['asap'] : 0;
        
        $strApi = isset($row['api']) ? trim(strtolower((string)$row['api'])) : '';
        if ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'sedang' || $strApi === 'tinggi' || $apiValue > 0.5) {
            $apiStatus = "Terdeteksi Api";
        } else {
            $apiStatus = "Aman";
        }
        
        if (is_numeric($rawAsap)) {
            $fAsap = (float)$rawAsap;
            if ($fAsap > ($fAsap > 1 ? 750 : 0.5)) {
                $asapStatus = "Tinggi";
            } else if ($fAsap > ($fAsap > 1 ? 350 : 0.25)) {
                $asapStatus = "Sedang";
            } else {
                $asapStatus = "Normal";
            }
            $asapNum = $fAsap;
        } else {
            $strAsap = trim((string)$rawAsap);
            if (strcasecmp($strAsap, 'Tinggi') === 0 || strcasecmp($strAsap, 'Bahaya') === 0) {
                $asapStatus = "Tinggi";
                $asapNum = 1;
            } else if (strcasecmp($strAsap, 'Sedang') === 0 || strcasecmp($strAsap, 'Waspada') === 0) {
                $asapStatus = "Sedang";
                $asapNum = 0.5;
            } else {
                $asapStatus = "Normal";
                $asapNum = 0;
            }
        }
        
        $suhuVal = isset($row['suhu']) ? (float)$row['suhu'] : 0.0;
        $kelembapanVal = isset($row['kelembapan']) ? (float)$row['kelembapan'] : 0.0;
        $teganganVal = isset($row['tegangan']) ? (float)$row['tegangan'] : 0.0;
        $arusVal = isset($row['arus']) ? (float)$row['arus'] : 0.0;

        $isDanger = ($apiStatus === "Terdeteksi Api" || $asapStatus === "Tinggi"
            || ($suhuVal > $limit_suhu)
            || ($kelembapanVal > $limit_kelembapan)
            || ($teganganVal > $limit_tegangan)
            || ($arusVal > $limit_arus));

        $data = [
            "error"            => false,
            "waktu"            => date('H:i:s', strtotime($waktu_raw)),
            "api"              => $apiStatus,
            "asap"             => $asapStatus,
            "asap_value"       => $asapNum,
            "suhu"             => number_format($suhuVal, 1),
            "kelembapan"       => number_format($kelembapanVal, 1),
            "tegangan"         => number_format($teganganVal, 1),
            "arus"             => number_format($arusVal, 2),
            "rssi"             => $row['rssi'] ?? '-',
            "ip"               => !empty($row['ip_address']) ? $row['ip_address'] : '-',
            "latitude"         => isset($row['latitude']) ? (float)$row['latitude'] : null,
            "longitude"        => isset($row['longitude']) ? (float)$row['longitude'] : null,
            "isDanger"         => $isDanger,
            "apiValue"         => ($apiStatus === "Terdeteksi Api") ? 1 : 0,
            "status"           => "Online",
            "limit_suhu"       => $limit_suhu,
            "limit_kelembapan" => $limit_kelembapan,
            "limit_tegangan"   => $limit_tegangan,
            "limit_arus"       => $limit_arus
        ];
    } else {
        $data = [
            "error"            => false,
            "waktu"            => date('H:i:s'),
            "api"              => "Aman",
            "asap"             => "Normal",
            "asap_value"       => 0,
            "suhu"             => "0.0",
            "kelembapan"       => "0.0",
            "tegangan"         => "0.0",
            "arus"             => "0.00",
            "rssi"             => '-',
            "ip"               => '-',
            "latitude"         => isset($row['latitude']) ? (float)$row['latitude'] : null,
            "longitude"        => isset($row['longitude']) ? (float)$row['longitude'] : null,
            "isDanger"         => false,
            "apiValue"         => 0,
            "status"           => "Offline",
            "limit_suhu"       => $limit_suhu,
            "limit_kelembapan" => $limit_kelembapan,
            "limit_tegangan"   => $limit_tegangan,
            "limit_arus"       => $limit_arus
        ];
    }
    
    echo json_encode($data);
} else {
    echo json_encode([
        "error" => true,
        "message" => "Belum ada data sensor di database indoor."
    ]);
}
?>