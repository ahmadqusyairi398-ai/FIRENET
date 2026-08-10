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
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    $colCheckDummy = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    $hasDummyCol = ($colCheckDummy && $colCheckDummy->rowCount() > 0);

    $dummyDeleted = 0;
    $realDeleted = 0;

    // Aturan 1: Hapus data Dummy (is_dummy = 1) jika usia > 3 hari
    if ($hasDummyCol) {
        $sqlCleanDummy = "DELETE FROM data_sensor WHERE is_dummy = 1 AND $dateCol < DATE_SUB(NOW(), INTERVAL 3 DAY)";
        $stmtCleanDummy = $pdo_indoor->prepare($sqlCleanDummy);
        $stmtCleanDummy->execute();
        $dummyDeleted = $stmtCleanDummy->rowCount();
    }

    // Aturan 2: Hapus data Alat Utama (is_dummy = 0 atau NULL) jika usia > 30 hari
    $dummyCondition = $hasDummyCol ? "(is_dummy IS NULL OR is_dummy = 0)" : "1=1";
    $sqlCleanReal = "DELETE FROM data_sensor WHERE $dummyCondition AND $dateCol < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtCleanReal = $pdo_indoor->prepare($sqlCleanReal);
    $stmtCleanReal->execute();
    $realDeleted = $stmtCleanReal->rowCount();

    // PERIKSA NOTIFIKASI H-1 (HARI KE-29)
    $sqlWarning = "SELECT COUNT(*) as total_records, MIN($dateCol) as oldest_date 
                   FROM data_sensor 
                   WHERE $dummyCondition 
                   AND $dateCol <= DATE_SUB(NOW(), INTERVAL 29 DAY) 
                   AND $dateCol > DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtWarn = $pdo_indoor->prepare($sqlWarning);
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
        'device' => 'indoor',
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
