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

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    
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
    
    $isDanger = ($apiStatus === "Terdeteksi Api" || $asapStatus === "Tinggi");

    // Format waktu dari timestamp / tanggal_dan_waktu
    $waktu_raw = $row['timestamp'] ?? ($row['tanggal_dan_waktu'] ?? 'now');
    $waktu_formatted = date('H:i:s', strtotime($waktu_raw));
    
    // Susun data JSON untuk response
    $data = [
        "error"      => false,
        "waktu"      => $waktu_formatted,
        "api"        => $apiStatus,
        "asap"       => $asapStatus,
        "asap_value" => $asapNum,
        "suhu"       => isset($row['suhu']) ? number_format((float)$row['suhu'], 1) : "0.0",
        "kelembapan" => isset($row['kelembapan']) ? number_format((float)$row['kelembapan'], 1) : "0.0",
        "tegangan"   => isset($row['tegangan']) ? number_format((float)$row['tegangan'], 1) : "0.0",
        "arus"       => isset($row['arus']) ? number_format((float)$row['arus'], 2) : "0.00",
        "rssi"       => $row['rssi'] ?? '-',
        "ip"         => !empty($row['ip_address']) ? $row['ip_address'] : '-',
        "latitude"   => isset($row['latitude']) ? (float)$row['latitude'] : null,
        "longitude"  => isset($row['longitude']) ? (float)$row['longitude'] : null,
        "isDanger"   => $isDanger,
        "apiValue"   => ($apiStatus === "Terdeteksi Api") ? 1 : 0,
        "status"     => "Online"
    ];
    
    echo json_encode($data);
} else {
    echo json_encode([
        "error" => true,
        "message" => "Belum ada data sensor di database indoor."
    ]);
}
?>