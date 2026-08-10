<?php
date_default_timezone_set('Asia/Makassar');
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

$device = $_POST['device'] ?? $_GET['device'] ?? 'outdoor';

/** @var PDO $pdo_indoor */
/** @var PDO $pdo_outdoor */

$targetPdo = ($device === 'indoor') ? $pdo_indoor : $pdo_outdoor;

if (!$targetPdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}

try {
    // 1. Cek & pastikan kolom is_dummy ada di tabel data_sensor
    $colCheck = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if (!$colCheck || $colCheck->rowCount() == 0) {
        @$targetPdo->exec("ALTER TABLE data_sensor ADD COLUMN is_dummy INT DEFAULT 0");
    }

    // 2. Ambil parameter masukan (support JSON payload, POST, atau GET)
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input) || empty($input)) {
        $input = $_POST;
    }
    if (empty($input)) {
        $input = $_GET;
    }

    $asap = $input['asap'] ?? 'Normal';
    $api = $input['api'] ?? 'Aman'; // Penangkap sensor api
    $interval_dari_alat = isset($input['interval_dari_alat']) ? intval($input['interval_dari_alat']) : null; // Penangkap sinkronisasi interval
    $suhu = floatval($input['suhu'] ?? 0);
    $kelembapan = floatval($input['kelembapan'] ?? 0);
    $tegangan = floatval($input['tegangan'] ?? 0);
    $arus = floatval($input['arus'] ?? 0);
    $daya = floatval($input['daya'] ?? 0);
    $kecepatan_angin = floatval($input['angin'] ?? ($input['kecepatan_angin'] ?? 0));
    $arah_angin = $input['arah'] ?? ($input['arah_angin'] ?? 'Utara');
    $co = floatval($input['co'] ?? ($input['mq7'] ?? 0));
    $lat = floatval($input['lat'] ?? 0);
    $lng = floatval($input['lng'] ?? 0);
    $is_dummy = isset($input['is_dummy']) ? intval($input['is_dummy']) : 0;
    $waktu = date('Y-m-d H:i:s');

    // 3. Tentukan nama kolom tanggal/waktu yang tersedia di tabel
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    // 4. Simpan data sensor dummy ke database MySQL
    if ($device === 'indoor') {
        // A. Update sinkronisasi interval JIKA ada kiriman dari alat
        if ($interval_dari_alat !== null && $interval_dari_alat > 0) {
            $sql_interval = "UPDATE lokasi_monitoring SET interval_kirim = :intv WHERE id = 1";
            $stmt_intv = $targetPdo->prepare($sql_interval);
            $stmt_intv->execute([':intv' => $interval_dari_alat]);
        }

        // B. Query khusus Indoor (memakai api, tanpa daya/angin/co)
        $sql = "INSERT INTO data_sensor ($dateCol, api, asap, suhu, kelembapan, tegangan, arus, is_dummy)
                VALUES (:waktu, :api, :asap, :suhu, :kelembapan, :tegangan, :arus, :is_dummy)";

        $stmt = $targetPdo->prepare($sql);
        $stmt->execute([
            ':waktu' => $waktu,
            ':api' => $api,
            ':asap' => $asap,
            ':suhu' => $suhu,
            ':kelembapan' => $kelembapan,
            ':tegangan' => $tegangan,
            ':arus' => $arus,
            ':is_dummy' => $is_dummy
        ]);
    } else {
        // Query aslinya untuk Outdoor
        $sql = "INSERT INTO data_sensor ($dateCol, asap, suhu, kelembapan, tegangan, arus, daya, kecepatan_angin, arah_angin, co, is_dummy)
                VALUES (:waktu, :asap, :suhu, :kelembapan, :tegangan, :arus, :daya, :kecepatan_angin, :arah_angin, :co, :is_dummy)";

        $stmt = $targetPdo->prepare($sql);
        $stmt->execute([
            ':waktu' => $waktu,
            ':asap' => $asap,
            ':suhu' => $suhu,
            ':kelembapan' => $kelembapan,
            ':tegangan' => $tegangan,
            ':arus' => $arus,
            ':daya' => $daya,
            ':kecepatan_angin' => $kecepatan_angin,
            ':arah_angin' => $arah_angin,
            ':co' => $co,
            ':is_dummy' => $is_dummy
        ]);
    }

    $insertedId = $targetPdo->lastInsertId();

    // Jika ESP32 mengirim koordinat GPS valid, update lokasi alat utama
    if ($lat != 0 && $lng != 0) {
        try {
            $tableLoc = ($device === 'indoor') ? 'lokasi_monitoring' : 'lokasi_alat';
            $stmtGps = $targetPdo->prepare("UPDATE $tableLoc SET latitude = :lat, longitude = :lng WHERE id = 1");
            $stmtGps->execute([':lat' => $lat, ':lng' => $lng]);
        } catch (Throwable $eGps) {}
    }

    // Ambil setting interval_detik alat terbaru dari database
    $intervalDetik = 30;
    try {
        $qLoc = $targetPdo->query("SELECT interval_detik FROM lokasi_alat ORDER BY id ASC LIMIT 1");
        if ($qLoc && $rowLoc = $qLoc->fetch(PDO::FETCH_ASSOC)) {
            $intervalDetik = intval($rowLoc['interval_detik'] ?? 30);
            if ($intervalDetik < 3) $intervalDetik = 3;
        }
    } catch(Throwable $eLoc) {}

    echo json_encode([
        'status' => 'success',
        'message' => 'Data sensor berhasil disimpan ke database.',
        'id' => $insertedId,
        'interval_detik' => $intervalDetik,
        'timestamp' => $waktu
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
