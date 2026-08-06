<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

// Pastikan MURNI menggunakan koneksi database OUTDOOR
$conn = isset($conn_outdoor) && $conn_outdoor ? $conn_outdoor : null;

if (!$conn) {
    // Fallback manual murni ke database outdoor jika kredensial terpisah
    $conn = @mysqli_connect("localhost", "ta_user", "rahasiaTA123!", "outdoor");
    if (!$conn) {
        $conn = @mysqli_connect("localhost", "root", "", "outdoor");
    }
}

if (!$conn || mysqli_connect_errno()) {
    echo json_encode([
        "error" => true,
        "message" => "Koneksi database outdoor gagal"
    ]);
    exit();
}

// Ambil data sensor terbaru murni dari database OUTDOOR
$query_sensor = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 1");
$data_sensor = ($query_sensor && mysqli_num_rows($query_sensor) > 0) ? mysqli_fetch_assoc($query_sensor) : [];

// Ambil lokasi alat utama (id=1) dari database OUTDOOR (tabel lokasi_alat)
$query_lokasi = mysqli_query($conn, "SELECT * FROM lokasi_alat WHERE id = 1 LIMIT 1");
if (!$query_lokasi || mysqli_num_rows($query_lokasi) == 0) {
    $query_lokasi = mysqli_query($conn, "SELECT * FROM lokasi_alat ORDER BY id ASC LIMIT 1");
}
$data_lokasi = ($query_lokasi && mysqli_num_rows($query_lokasi) > 0) ? mysqli_fetch_assoc($query_lokasi) : [];

// 3. Logika penentuan status Asap yang lebih aman
$raw_asap = $data_sensor['asap'] ?? 0;
$status_asap = "Normal";

if (is_numeric($raw_asap)) {
    $f_asap = (float)$raw_asap;
    if ($f_asap > ($f_asap > 1 ? 50 : 0.5)) {
        $status_asap = "Tinggi";
    } else if ($f_asap > ($f_asap > 1 ? 25 : 0.25)) {
        $status_asap = "Sedang";
    }
} else {
    $str_asap = trim((string)$raw_asap);
    if (strcasecmp($str_asap, 'Tinggi') === 0 || strcasecmp($str_asap, 'Bahaya') === 0) {
        $status_asap = "Tinggi";
    } else if (strcasecmp($str_asap, 'Sedang') === 0 || strcasecmp($str_asap, 'Waspada') === 0) {
        $status_asap = "Sedang";
    }
}

// Gabungkan response
$response = [
    'waktu'      => date('H:i:s', strtotime($data_sensor['timestamp'] ?? 'now')),
    'tegangan'   => $data_sensor['tegangan'] ?? 0,
    'arus'       => $data_sensor['arus'] ?? 0,
    'daya'       => $data_sensor['daya'] ?? 0,
    'arah'       => $data_sensor['arah_angin'] ?? 'Utara',
    'angin'      => $data_sensor['kecepatan_angin'] ?? 0,
    'asap'       => $status_asap, // Menggunakan hasil logika di atas
    'suhu'       => $data_sensor['suhu'] ?? 0,
    'kelembapan' => $data_sensor['kelembapan'] ?? 0,
    'co'         => $data_sensor['co'] ?? 0,
    'rssi'       => $data_sensor['rssi'] ?? 0,
    'ip'         => $data_sensor['ip_address'] ?? '127.0.0.1',
    'status'     => 'Online',
    
    'lat'        => $data_lokasi['latitude'] ?? -1.20249,
    'lng'        => $data_lokasi['longitude'] ?? 116.88708
];

echo json_encode($response);
?>