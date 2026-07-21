<?php
header('Content-Type: application/json');
include 'koneksi.php'; // Sesuaikan dengan file koneksi Anda

// 1. Ambil data sensor terbaru
$query_sensor = mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 1");
$data_sensor = mysqli_fetch_assoc($query_sensor);

// 2. Ambil lokasi alat utama (id=1)
$query_lokasi = mysqli_query($conn, "SELECT * FROM lokasi_alat WHERE id = 1 LIMIT 1");
if (!$query_lokasi || mysqli_num_rows($query_lokasi) == 0) {
    $query_lokasi = mysqli_query($conn, "SELECT * FROM lokasi_alat ORDER BY id ASC LIMIT 1");
}
$data_lokasi = mysqli_fetch_assoc($query_lokasi);

// Gabungkan response
$response = [
    'waktu'      => date('H:i:s', strtotime($data_sensor['timestamp'] ?? 'now')),
    'tegangan'   => $data_sensor['tegangan'] ?? 0,
    'arus'       => $data_sensor['arus'] ?? 0,
    'daya'       => $data_sensor['daya'] ?? 0,
    'arah'       => $data_sensor['arah_angin'] ?? 'Utara',
    'angin'      => $data_sensor['kecepatan_angin'] ?? 0,
    'asap'       => ($data_sensor['asap'] > 20) ? "Tinggi" : "Normal", // Sesuai threshold
    'suhu'       => $data_sensor['suhu'] ?? 0,
    'kelembapan' => $data_sensor['kelembapan'] ?? 0,
    'co'         => $data_sensor['co'] ?? 0,
    'rssi'       => $data_sensor['rssi'] ?? 0,
    'ip'         => $data_sensor['ip_address'] ?? '127.0.0.1',
    'status'     => 'Online',
    
    // ======== BAGIAN YANG DIPERBAIKI ========
    // Sekarang murni hanya mengambil dari tabel lokasi_alat
    'lat'        => $data_lokasi['latitude'] ?? -1.20249,
    'lng'        => $data_lokasi['longitude'] ?? 116.88708
];

echo json_encode($response);
?>