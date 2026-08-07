<?php
date_default_timezone_set('Asia/Makassar');
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

$device = $_GET['device'] ?? $_POST['device'] ?? 'outdoor';

/** @var PDO $pdo_indoor */
/** @var PDO $pdo_outdoor */

$targetPdo = ($device === 'indoor') ? $pdo_indoor : $pdo_outdoor;

if (!$targetPdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}

try {
    // 1. Tentukan nama kolom tanggal/waktu yang tersedia di tabel data_sensor
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    // 2. Pastikan kolom is_dummy ada
    $colCheckDummy = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    $hasDummyCol = ($colCheckDummy && $colCheckDummy->rowCount() > 0);

    // =========================================================================
    // EKSEKUSI PENGHAPUSAN OTOMATIS (AUTO-CLEAN)
    // =========================================================================
    $dummyDeleted = 0;
    $realDeleted = 0;

    // Aturan 1: Hapus data Dummy (is_dummy = 1) jika usia > 3 hari
    if ($hasDummyCol) {
        $sqlCleanDummy = "DELETE FROM data_sensor WHERE is_dummy = 1 AND $dateCol < DATE_SUB(NOW(), INTERVAL 3 DAY)";
        $stmtCleanDummy = $targetPdo->prepare($sqlCleanDummy);
        $stmtCleanDummy->execute();
        $dummyDeleted = $stmtCleanDummy->rowCount();
    }

    // Aturan 2: Hapus data Alat Utama (is_dummy = 0 atau NULL) jika usia > 30 hari
    $dummyCondition = $hasDummyCol ? "(is_dummy IS NULL OR is_dummy = 0)" : "1=1";
    $sqlCleanReal = "DELETE FROM data_sensor WHERE $dummyCondition AND $dateCol < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtCleanReal = $targetPdo->prepare($sqlCleanReal);
    $stmtCleanReal->execute();
    $realDeleted = $stmtCleanReal->rowCount();

    // =========================================================================
    // PERIKSA NOTIFIKASI H-1 (HARI KE-29)
    // =========================================================================
    // Cek apakah ada data Alat Utama yang usianya menginjak 29 s.d. 30 hari
    $sqlWarning = "SELECT COUNT(*) as total_records, MIN($dateCol) as oldest_date 
                   FROM data_sensor 
                   WHERE $dummyCondition 
                   AND $dateCol <= DATE_SUB(NOW(), INTERVAL 29 DAY) 
                   AND $dateCol > DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtWarn = $targetPdo->prepare($sqlWarning);
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
        'device' => $device,
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
