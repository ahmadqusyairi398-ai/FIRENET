<?php
date_default_timezone_set('Asia/Makassar');
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

/** @var PDO $pdo_indoor */
if (!$pdo_indoor) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database Indoor tidak tersedia.']);
    exit;
}

try {
    // 1. Cek & pastikan kolom is_dummy ada di tabel data_sensor database indoor
    $colCheck = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if (!$colCheck || $colCheck->rowCount() == 0) {
        @$pdo_indoor->exec("ALTER TABLE data_sensor ADD COLUMN is_dummy INT DEFAULT 0");
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

    $id_alat = trim($input['id_alat'] ?? 'LOK-002');
    $asap = $input['asap'] ?? 'Normal';
    $api = $input['api'] ?? 'Aman';
    $interval_dari_alat = isset($input['interval_dari_alat']) ? intval($input['interval_dari_alat']) : (isset($input['interval']) ? intval($input['interval']) : null);
    $suhu = floatval($input['suhu'] ?? 0);
    $kelembapan = floatval($input['kelembapan'] ?? 0);
    $tegangan = floatval($input['tegangan'] ?? 0);
    $arus = floatval($input['arus'] ?? 0);
    $lat = floatval($input['lat'] ?? ($input['latitude'] ?? 0));
    $lng = floatval($input['lng'] ?? ($input['longitude'] ?? 0));
    $is_dummy = isset($input['is_dummy']) ? intval($input['is_dummy']) : 0;
    $waktu = date('Y-m-d H:i:s');

    // 3. Tentukan nama kolom tanggal/waktu yang tersedia di tabel data_sensor
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    // 4. Update sinkronisasi interval JIKA ada kiriman dari alat
    if ($interval_dari_alat !== null && $interval_dari_alat > 0) {
        $stmt_intv = $pdo_indoor->prepare("UPDATE lokasi_monitoring SET interval_kirim = :intv WHERE id_alat = :id_alat");
        $stmt_intv->execute([':intv' => $interval_dari_alat, ':id_alat' => $id_alat]);
        if ($stmt_intv->rowCount() == 0) {
            // Fallback: update id=2 (LOK-002) atau id=1 jika id_alat tidak terdeteksi persis
            $stmt_intv_fb = $pdo_indoor->prepare("UPDATE lokasi_monitoring SET interval_kirim = :intv WHERE id = 2 OR id = 1 ORDER BY id DESC LIMIT 1");
            $stmt_intv_fb->execute([':intv' => $interval_dari_alat]);
        }
    }

    // 5. Simpan data sensor ke tabel data_sensor (Indoor)
    $sql = "INSERT INTO data_sensor ($dateCol, api, asap, suhu, kelembapan, tegangan, arus, is_dummy)
            VALUES (:waktu, :api, :asap, :suhu, :kelembapan, :tegangan, :arus, :is_dummy)";

    $stmt = $pdo_indoor->prepare($sql);
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

    $insertedId = $pdo_indoor->lastInsertId();

    // 6. Jika ESP32 mengirim koordinat GPS valid, update lokasi monitoring indoor
    if ($lat != 0 && $lng != 0) {
        try {
            $stmtGps = $pdo_indoor->prepare("UPDATE lokasi_monitoring SET latitude = :lat, longitude = :lng WHERE id_alat = :id_alat");
            $stmtGps->execute([':lat' => $lat, ':lng' => $lng, ':id_alat' => $id_alat]);
            if ($stmtGps->rowCount() == 0) {
                $stmtGpsFb = $pdo_indoor->prepare("UPDATE lokasi_monitoring SET latitude = :lat, longitude = :lng WHERE id = 2 OR id = 1 LIMIT 1");
                $stmtGpsFb->execute([':lat' => $lat, ':lng' => $lng]);
            }
        } catch (Throwable $eGps) {}
    }

    // 7. Ambil setting interval_kirim alat terbaru dari tabel lokasi_monitoring
    $intervalDetik = 15;
    try {
        $qLoc = $pdo_indoor->prepare("SELECT interval_kirim FROM lokasi_monitoring WHERE id_alat = :id_alat LIMIT 1");
        $qLoc->execute([':id_alat' => $id_alat]);
        $rowLoc = $qLoc->fetch(PDO::FETCH_ASSOC);

        if (!$rowLoc) {
            $qLocFb = $pdo_indoor->query("SELECT interval_kirim FROM lokasi_monitoring ORDER BY id ASC LIMIT 1");
            $rowLoc = $qLocFb ? $qLocFb->fetch(PDO::FETCH_ASSOC) : null;
        }

        if ($rowLoc) {
            $intervalDetik = intval($rowLoc['interval_kirim'] ?? 15);
            if ($intervalDetik < 1) $intervalDetik = 15;
        }
    } catch(Throwable $eLoc) {}

    echo json_encode([
        'status' => 'success',
        'message' => 'Data sensor Indoor berhasil disimpan ke database.',
        'id' => $insertedId,
        'interval_detik' => $intervalDetik,
        'interval_kirim' => $intervalDetik,
        'timestamp' => $waktu
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
