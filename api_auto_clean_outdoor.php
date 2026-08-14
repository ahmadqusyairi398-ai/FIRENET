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
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $pdo_outdoor->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    $colCheckDummy = $pdo_outdoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    $hasDummyCol = ($colCheckDummy && $colCheckDummy->rowCount() > 0);

    $dummyDeleted = 0;
    $realDeleted = 0;

    // Aturan 1: Hapus data Dummy (is_dummy = 1) jika usia > 3 hari
    if ($hasDummyCol) {
        $sqlCleanDummy = "DELETE FROM data_sensor WHERE is_dummy = 1 AND $dateCol < DATE_SUB(NOW(), INTERVAL 3 DAY)";
        $stmtCleanDummy = $pdo_outdoor->prepare($sqlCleanDummy);
        $stmtCleanDummy->execute();
        $dummyDeleted = $stmtCleanDummy->rowCount();
    }

    // Aturan 2: Hapus data Alat Utama (is_dummy = 0 atau NULL) jika usia > 30 hari
    $dummyCondition = $hasDummyCol ? "(is_dummy IS NULL OR is_dummy = 0)" : "1=1";
    $sqlCleanReal = "DELETE FROM data_sensor WHERE $dummyCondition AND $dateCol < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtCleanReal = $pdo_outdoor->prepare($sqlCleanReal);
    $stmtCleanReal->execute();
    $realDeleted = $stmtCleanReal->rowCount();

    // Aturan 3: Perbaiki/Koreksi data lama yang memiliki arus/daya tidak realistis (misal satuan mA terbaca sebagai A)
    @$pdo_outdoor->exec("UPDATE data_sensor SET arus = arus / 1000.0, daya = ROUND(tegangan * (arus / 1000.0), 2) WHERE arus > 20");
    @$pdo_outdoor->exec("UPDATE data_sensor SET daya = ROUND(tegangan * arus, 2) WHERE daya > 500");

    // PERIKSA NOTIFIKASI H-1 (HARI KE-29)
    $sqlWarning = "SELECT COUNT(*) as total_records, MIN($dateCol) as oldest_date 
                   FROM data_sensor 
                   WHERE $dummyCondition 
                   AND $dateCol <= DATE_SUB(NOW(), INTERVAL 29 DAY) 
                   AND $dateCol > DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtWarn = $pdo_outdoor->prepare($sqlWarning);
    $stmtWarn->execute();
    $warnRow = $stmtWarn->fetch(PDO::FETCH_ASSOC);

    $hasWarning = false;
    $warningCount = 0;
    $oldestDate = null;

    if ($warnRow && intval($warnRow['total_records']) > 0) {
        $hasWarning = true;
        $warningCount = intval($warnRow['total_records']);
        $oldestDate = $warnRow['oldest_date'];
    }

    echo json_encode([
        'status' => 'success',
        'device' => 'outdoor',
        'has_warning' => $hasWarning,
        'warning_count' => $warningCount,
        'oldest_date' => $oldestDate,
        'dummy_deleted' => $dummyDeleted,
        'real_deleted' => $realDeleted,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
