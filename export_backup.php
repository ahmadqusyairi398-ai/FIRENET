<?php
date_default_timezone_set('Asia/Makassar');
session_start();
require_once 'koneksi.php';

$device = $_GET['device'] ?? 'outdoor';

/** @var PDO $pdo_indoor */
/** @var PDO $pdo_outdoor */

$targetPdo = ($device === 'indoor') ? $pdo_indoor : $pdo_outdoor;

if (!$targetPdo) {
    die("Koneksi database tidak tersedia.");
}

try {
    $dateCol = 'tanggal_dan_waktu';
    $colCheckWaktu = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    if ($colCheckWaktu && $colCheckWaktu->rowCount() > 0) {
        $dateCol = 'timestamp';
    }

    $colCheckDummy = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    $hasDummyCol = ($colCheckDummy && $colCheckDummy->rowCount() > 0);
    $dummyCondition = $hasDummyCol ? "WHERE (is_dummy IS NULL OR is_dummy = 0)" : "";

    $sql = "SELECT * FROM data_sensor $dummyCondition ORDER BY $dateCol DESC";
    $stmt = $targetPdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "rekap_backup_data_sensor_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Output BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header CSV
    fputcsv($output, ['No', 'Tanggal & Waktu', 'Asap', 'Suhu (°C)', 'Kelembapan (%)', 'Tegangan (V)', 'Arus (A)', 'Daya (W)', 'Kecepatan Angin (m/s)', 'Arah Angin', 'CO (ppm)']);

    $no = 1;
    foreach ($rows as $row) {
        $timeVal = $row[$dateCol] ?? $row['tanggal_dan_waktu'] ?? $row['timestamp'] ?? '-';
        $arahExport = $row['arah_angin'] ?? '-';
        if (is_numeric($arahExport)) {
            $deg = floatval($arahExport);
            $deg = fmod(fmod($deg, 360) + 360, 360);
            $cardinals = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
            $arahExport = $cardinals[round($deg / 45) % 8];
        }
        fputcsv($output, [
            $no++,
            $timeVal,
            $row['asap'] ?? 'Normal',
            $row['suhu'] ?? 0,
            $row['kelembapan'] ?? 0,
            $row['tegangan'] ?? 0,
            $row['arus'] ?? 0,
            $row['daya'] ?? 0,
            $row['kecepatan_angin'] ?? 0,
            $arahExport,
            $row['co'] ?? 0
        ]);
    }
    fclose($output);
    exit;
} catch (Throwable $e) {
    die("Error exporting backup: " . $e->getMessage());
}
?>
