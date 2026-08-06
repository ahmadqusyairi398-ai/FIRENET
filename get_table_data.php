<?php
date_default_timezone_set('Asia/Makassar');
header('Content-Type: application/json');
require_once 'koneksi.php';

$device = isset($_GET['device']) ? strtolower($_GET['device']) : 'outdoor';
if ($device === 'indoor') {
    $conn = isset($conn_indoor) ? $conn_indoor : null;
} else {
    $conn = isset($conn_outdoor) ? $conn_outdoor : null;
}

if (!$conn) {
    echo json_encode([]);
    exit();
}

$rows = [];
$q = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 100");
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $waktu_raw = $r['timestamp'] ?? ($r['tanggal_dan_waktu'] ?? ($r['created_at'] ?? ''));
        $ts = !empty($waktu_raw) ? strtotime($waktu_raw) : time();
        $waktu_formatted = date('Y-m-d H:i:s', $ts);
        
        $raw_asap = $r['asap'] ?? 'Normal';
        if (is_numeric($raw_asap)) {
            $f_asap = (float)$raw_asap;
            if ($f_asap > ($f_asap > 1 ? 750 : 0.5)) $asap_val = "Tinggi";
            else if ($f_asap > ($f_asap > 1 ? 350 : 0.25)) $asap_val = "Sedang";
            else $asap_val = "Normal";
        } else {
            $str_asap = trim((string)$raw_asap);
            if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) $asap_val = "Tinggi";
            else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) $asap_val = "Sedang";
            else $asap_val = "Normal";
        }

        $raw_api = $r['api'] ?? 0;
        $str_api = trim(strtolower((string)$raw_api));
        $api_val = ($str_api === 'terdeteksi api' || $str_api === 'dekat' || $str_api === 'sedang' || $str_api === 'bahaya' || $str_api === 'tinggi' || (float)$raw_api > 0.5) ? 'Terdeteksi Api' : 'Aman';

        $rows[] = [
            'id' => $r['id'],
            'tanggal_waktu' => $waktu_formatted,
            'asap' => $asap_val,
            'asap_raw' => $r['asap'] ?? 0,
            'suhu' => isset($r['suhu']) ? number_format((float)$r['suhu'], 1) : '0.0',
            'kelembapan' => isset($r['kelembapan']) ? number_format((float)$r['kelembapan'], 1) : '0.0',
            'tegangan' => isset($r['tegangan']) ? number_format((float)$r['tegangan'], 1) : '0.0',
            'arus' => isset($r['arus']) ? number_format((float)$r['arus'], 2) : '0.00',
            'daya' => isset($r['daya']) ? number_format((float)$r['daya'], 1) : '0.0',
            'kecepatan_angin' => isset($r['kecepatan_angin']) ? number_format((float)$r['kecepatan_angin'], 1) : '0.0',
            'arah_angin' => !empty($r['arah_angin']) ? $r['arah_angin'] : '-',
            'co' => is_numeric($co_raw) ? number_format((float)$co_raw, 1) : $co_raw,
            'api' => $api_val,
            'api_raw' => $r['api'] ?? 0,
            'rssi' => $r['rssi'] ?? '-'
        ];
    }
}

echo json_encode($rows);
?>
