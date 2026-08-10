<?php
date_default_timezone_set('Asia/Makassar');
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

/** @var PDO $pdo_outdoor */
if (!$pdo_outdoor) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database Outdoor tidak tersedia.']);
    exit;
}

try {
    // 1. Cek & pastikan kolom is_dummy ada di tabel data_sensor database outdoor
    $colCheck = $pdo_outdoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if (!$colCheck || $colCheck->rowCount() == 0) {
        @$pdo_outdoor->exec("ALTER TABLE data_sensor ADD COLUMN is_dummy INT DEFAULT 0");
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

    $id_alat = trim($input['id_alat'] ?? 'OUT-001');
    $asap = $input['asap'] ?? 'Normal';
    $interval_dari_alat = isset($input['interval_dari_alat']) ? intval($input['interval_dari_alat']) : (isset($input['interval']) ? intval($input['interval']) : null);
    $suhu = floatval($input['suhu'] ?? 0);
    $kelembapan = floatval($input['kelembapan'] ?? 0);
    $tegangan = floatval($input['tegangan'] ?? 0);
    $arus = floatval($input['arus'] ?? 0);
    $daya = floatval($input['daya'] ?? 0);
    $kecepatan_angin = floatval($input['angin'] ?? ($input['kecepatan_angin'] ?? 0));
    $arah_angin = $input['arah'] ?? ($input['arah_angin'] ?? 'Utara');
    $co = floatval($input['co'] ?? ($input['mq7'] ?? 0));
    $lat = floatval($input['lat'] ?? ($input['latitude'] ?? 0));
    $lng = floatval($input['lng'] ?? ($input['longitude'] ?? 0));
    $is_dummy = isset($input['is_dummy']) ? intval($input['is_dummy']) : 0;
    $waktu = date('Y-m-d H:i:s');

    // 3. Tentukan nama kolom tanggal/waktu yang tersedia di tabel data_sensor
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $pdo_outdoor->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    // 4. Update sinkronisasi interval JIKA ada kiriman dari alat
    if ($interval_dari_alat !== null && $interval_dari_alat > 0) {
        $stmt_intv = $pdo_outdoor->prepare("UPDATE lokasi_alat SET interval_detik = :intv WHERE id_alat = :id_alat");
        $stmt_intv->execute([':intv' => $interval_dari_alat, ':id_alat' => $id_alat]);
        if ($stmt_intv->rowCount() == 0) {
            $stmt_intv_fb = $pdo_outdoor->prepare("UPDATE lokasi_alat SET interval_detik = :intv WHERE id = 1 LIMIT 1");
            $stmt_intv_fb->execute([':intv' => $interval_dari_alat]);
        }
    }

    // 5. Simpan data sensor ke tabel data_sensor (Outdoor)
    $sql = "INSERT INTO data_sensor ($dateCol, asap, suhu, kelembapan, tegangan, arus, daya, kecepatan_angin, arah_angin, co, is_dummy)
            VALUES (:waktu, :asap, :suhu, :kelembapan, :tegangan, :arus, :daya, :kecepatan_angin, :arah_angin, :co, :is_dummy)";

    $stmt = $pdo_outdoor->prepare($sql);
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

    $insertedId = $pdo_outdoor->lastInsertId();

    // 6. Jika ESP32 mengirim koordinat GPS valid, update lokasi alat utama outdoor
    if ($lat != 0 && $lng != 0) {
        try {
            $stmtGps = $pdo_outdoor->prepare("UPDATE lokasi_alat SET latitude = :lat, longitude = :lng WHERE id_alat = :id_alat");
            $stmtGps->execute([':lat' => $lat, ':lng' => $lng, ':id_alat' => $id_alat]);
            if ($stmtGps->rowCount() == 0) {
                $stmtGpsFb = $pdo_outdoor->prepare("UPDATE lokasi_alat SET latitude = :lat, longitude = :lng WHERE id = 1 LIMIT 1");
                $stmtGpsFb->execute([':lat' => $lat, ':lng' => $lng]);
            }
        } catch (Throwable $eGps) {}
    }

    // 7. Ambil setting interval_detik alat terbaru dari tabel lokasi_alat
    $intervalDetik = 30;
    try {
        $qLoc = $pdo_outdoor->prepare("SELECT interval_detik FROM lokasi_alat WHERE id_alat = :id_alat LIMIT 1");
        $qLoc->execute([':id_alat' => $id_alat]);
        $rowLoc = $qLoc->fetch(PDO::FETCH_ASSOC);

        if (!$rowLoc) {
            $qLocFb = $pdo_outdoor->query("SELECT interval_detik FROM lokasi_alat ORDER BY id ASC LIMIT 1");
            $rowLoc = $qLocFb ? $qLocFb->fetch(PDO::FETCH_ASSOC) : null;
        }

        if ($rowLoc) {
            $intervalDetik = intval($rowLoc['interval_detik'] ?? 30);
            if ($intervalDetik < 3) $intervalDetik = 3;
        }
    } catch(Throwable $eLoc) {}

    echo json_encode([
        'status' => 'success',
        'message' => 'Data sensor Outdoor berhasil disimpan ke database.',
        'id' => $insertedId,
        'interval_detik' => $intervalDetik,
        'timestamp' => $waktu
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
