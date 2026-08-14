<?php
header('Content-Type: application/json');
include 'koneksi.php'; // Sesuaikan dengan file koneksi Anda

// Enforce koneksi outdoor
$conn = isset($conn_outdoor) && $conn_outdoor ? $conn_outdoor : null;

// 1. Ambil data sensor terbaru (prioritaskan data real-time alat utama: is_dummy = 0)
$query_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor WHERE is_dummy = 0 OR is_dummy IS NULL ORDER BY timestamp DESC LIMIT 1");
if (!$query_sensor || mysqli_num_rows($query_sensor) == 0) {
    $query_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor WHERE is_dummy = 0 OR is_dummy IS NULL ORDER BY id DESC LIMIT 1");
}
if (!$query_sensor || mysqli_num_rows($query_sensor) == 0) {
    $query_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY timestamp DESC LIMIT 1");
}
if (!$query_sensor || mysqli_num_rows($query_sensor) == 0) {
    $query_sensor = @mysqli_query($conn, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
}
$data_sensor = ($query_sensor && mysqli_num_rows($query_sensor) > 0) ? mysqli_fetch_assoc($query_sensor) : null;

// 2. Ambil lokasi alat utama (id=1)
$query_lokasi = @mysqli_query($conn, "SELECT * FROM lokasi_alat WHERE id = 1 LIMIT 1");
if (!$query_lokasi || mysqli_num_rows($query_lokasi) == 0) {
    $query_lokasi = @mysqli_query($conn, "SELECT * FROM lokasi_alat ORDER BY id ASC LIMIT 1");
}
$data_lokasi = ($query_lokasi && mysqli_num_rows($query_lokasi) > 0) ? mysqli_fetch_assoc($query_lokasi) : null;

// Batas waktu offline (detik). Menyesuaikan dengan interval alat (minimal 45 detik)
$interval_setting = intval($data_lokasi['interval_detik'] ?? 30);
$timeout_seconds = max(45, ($interval_setting * 2) + 5);
$is_online = false;

if ($data_sensor) {
    $time_str = $data_sensor['timestamp'] ?? ($data_sensor['tanggal_dan_waktu'] ?? ($data_sensor['created_at'] ?? null));
    if ($time_str) {
        $last_time = strtotime($time_str);
        if ($last_time > 0 && (time() - $last_time) <= $timeout_seconds) {
            $is_online = true;
        }
    }
}

// 3. Logika penentuan status Asap & CO jika alat Online
$status_asap = "Normal";
if ($is_online && $data_sensor) {
    $raw_asap = $data_sensor['asap'] ?? 0;
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
}

// Gabungkan response (Jika alat mati/offline, set nilai sensor ke 0)
if ($is_online && $data_sensor) {
    $cur_tegangan = floatval($data_sensor['tegangan'] ?? 0);
    $cur_arus = floatval($data_sensor['arus'] ?? 0);
    $cur_daya = floatval($data_sensor['daya'] ?? 0);

    // Proteksi: jika arus > 20 (terbaca dalam mA), konversi otomatis ke A
    if ($cur_arus > 20) {
        $cur_arus = round($cur_arus / 1000.0, 2);
    }
    // Proteksi: hitung ulang daya jika tidak valid atau > 500 W
    if ($cur_daya <= 0 || $cur_daya > 500) {
        $cur_daya = round($cur_tegangan * $cur_arus, 2);
    }

    // Ambil info penggunaan storage / kuota data database outdoor
    $outdoor_storage = get_sensor_storage_info($conn, 'outdoor');
    $total_storage_bytes = ($outdoor_storage['real_bytes'] ?? 0) + ($outdoor_storage['dummy_bytes'] ?? 0);
    $kuota_data_formatted = format_storage_size($total_storage_bytes);

    $response = [
        'waktu'      => date('H:i:s', strtotime($data_sensor['timestamp'] ?? ($data_sensor['tanggal_dan_waktu'] ?? 'now'))),
        'tegangan'   => $cur_tegangan,
        'arus'       => $cur_arus,
        'daya'       => $cur_daya,
        'arah'       => $data_sensor['arah_angin'] ?? 'Utara',
        'angin'      => $data_sensor['kecepatan_angin'] ?? 0,
        'asap'       => $status_asap,
        'suhu'       => $data_sensor['suhu'] ?? 0,
        'kelembapan' => $data_sensor['kelembapan'] ?? 0,
        'co'         => $data_sensor['co'] ?? 0,
        'rssi'       => $data_sensor['rssi'] ?? 0,
        'ip'         => $data_sensor['ip_address'] ?? '127.0.0.1',
        'status'     => 'Online',
        'lat'            => $data_lokasi['latitude'] ?? -1.20249,
        'lng'            => $data_lokasi['longitude'] ?? 116.88708,
        'is_dummy'       => (int)($data_sensor['is_dummy'] ?? 0),
        'interval_detik' => (int)($data_lokasi['interval_detik'] ?? 30),
        'kuota_data'     => $kuota_data_formatted,
        'storage_real'   => $outdoor_storage['real_formatted'] ?? '0 B',
        'storage_dummy'  => $outdoor_storage['dummy_formatted'] ?? '0 B'
    ];
} else {
    $outdoor_storage = get_sensor_storage_info($conn, 'outdoor');
    $total_storage_bytes = ($outdoor_storage['real_bytes'] ?? 0) + ($outdoor_storage['dummy_bytes'] ?? 0);
    $kuota_data_formatted = format_storage_size($total_storage_bytes);

    $response = [
        'waktu'          => date('H:i:s'),
        'tegangan'       => 0,
        'arus'           => 0,
        'daya'           => 0,
        'arah'           => 'Utara',
        'angin'          => 0,
        'asap'           => 'Normal',
        'suhu'           => 0,
        'kelembapan'     => 0,
        'co'             => 0,
        'rssi'           => '-',
        'ip'             => '-',
        'status'         => 'Offline',
        'lat'            => $data_lokasi['latitude'] ?? -1.20249,
        'lng'            => $data_lokasi['longitude'] ?? 116.88708,
        'is_dummy'       => 0,
        'interval_detik' => (int)($data_lokasi['interval_detik'] ?? 30),
        'kuota_data'     => $kuota_data_formatted,
        'storage_real'   => $outdoor_storage['real_formatted'] ?? '0 B',
        'storage_dummy'  => $outdoor_storage['dummy_formatted'] ?? '0 B'
    ];
}

echo json_encode($response);
?>