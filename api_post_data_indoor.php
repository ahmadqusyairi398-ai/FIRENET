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
    $suhu = floatval($input['suhu'] ?? 0);
    $kelembapan = floatval($input['kelembapan'] ?? 0);
    $tegangan = floatval($input['tegangan'] ?? 0);
    $arus = floatval($input['arus'] ?? 0);
    $lat = floatval($input['lat'] ?? ($input['latitude'] ?? 0));
    $lng = floatval($input['lng'] ?? ($input['longitude'] ?? 0));
    $is_dummy = isset($input['is_dummy']) ? intval($input['is_dummy']) : 0;
    $waktu = date('Y-m-d H:i:s');

    // Tangkap variasi parameter interval dari alat
    $raw_intv = $input['interval_dari_alat'] 
             ?? $input['interval_kirim'] 
             ?? $input['interval_detik'] 
             ?? $input['interval'] 
             ?? $input['delay'] 
             ?? $input['interval_sec'] 
             ?? $_POST['interval_dari_alat'] 
             ?? $_POST['interval_kirim'] 
             ?? $_POST['interval_detik'] 
             ?? $_POST['interval'] 
             ?? $_GET['interval_dari_alat'] 
             ?? $_GET['interval_kirim'] 
             ?? $_GET['interval_detik'] 
             ?? $_GET['interval'] 
             ?? null;

    $interval_dari_alat = null;
    if ($raw_intv !== null && is_numeric($raw_intv)) {
        $val = intval($raw_intv);
        if ($val > 500) {
            $val = intval(round($val / 1000)); // Konversi dari milidetik (ms) ke detik
        }
        if ($val > 0) {
            $interval_dari_alat = $val;
        }
    }

    // Tangkap IP Address
    $ip_address = $input['ip'] ?? ($input['ip_address'] ?? null);
    if (empty($ip_address)) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '-';
        }
    }

    $colCheckIp = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'ip_address'");
    if (!$colCheckIp || $colCheckIp->rowCount() == 0) {
        @$pdo_indoor->exec("ALTER TABLE data_sensor ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL");
    }

    // 3. Tentukan nama kolom tanggal/waktu yang tersedia di tabel data_sensor
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    // 4. Update sinkronisasi interval & updated_at ke lokasi_monitoring
    if ($interval_dari_alat !== null && $interval_dari_alat > 0) {
        $stmt_intv = $pdo_indoor->prepare("
            UPDATE lokasi_monitoring 
            SET interval_kirim = :intv, updated_at = NOW() 
            WHERE id_alat = :id_alat OR id_alat = 'LOK-002' OR id_alat LIKE '%002%' OR id = 2 OR id = 1
        ");
        $stmt_intv->execute([':intv' => $interval_dari_alat, ':id_alat' => $id_alat]);
    } else if ($is_dummy == 0) {
        // Update waktu update lokasi utama bila ada data fisik masuk
        @$pdo_indoor->exec("UPDATE lokasi_monitoring SET updated_at = NOW() WHERE id_alat = '$id_alat' OR id_alat = 'LOK-002' OR id = 2");
    }

    // 5. Simpan data sensor ke tabel data_sensor (Indoor)
    $sql = "INSERT INTO data_sensor ($dateCol, api, asap, suhu, kelembapan, tegangan, arus, ip_address, is_dummy)
            VALUES (:waktu, :api, :asap, :suhu, :kelembapan, :tegangan, :arus, :ip_address, :is_dummy)";

    $stmt = $pdo_indoor->prepare($sql);
    $stmt->execute([
        ':waktu' => $waktu,
        ':api' => $api,
        ':asap' => $asap,
        ':suhu' => $suhu,
        ':kelembapan' => $kelembapan,
        ':tegangan' => $tegangan,
        ':arus' => $arus,
        ':ip_address' => $ip_address,
        ':is_dummy' => $is_dummy
    ]);

    $insertedId = $pdo_indoor->lastInsertId();

    // 6. Jika ESP32 mengirim koordinat GPS valid, update lokasi monitoring indoor
    if ($lat != 0 && $lng != 0) {
        try {
            $stmtGps = $pdo_indoor->prepare("UPDATE lokasi_monitoring SET latitude = :lat, longitude = :lng WHERE id_alat = :id_alat OR id = 2");
            $stmtGps->execute([':lat' => $lat, ':lng' => $lng, ':id_alat' => $id_alat]);
        } catch (Throwable $eGps) {}
    }

    // 7. Ambil setting interval_kirim alat terbaru dari tabel lokasi_monitoring
    $intervalDetik = 15;
    try {
        $qLoc = $pdo_indoor->prepare("SELECT interval_kirim FROM lokasi_monitoring WHERE id_alat = :id_alat OR id_alat = 'LOK-002' OR id = 2 ORDER BY id ASC LIMIT 1");
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
