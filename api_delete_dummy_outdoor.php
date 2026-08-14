<?php
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

// Proteksi: Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

/** @var PDO $pdo_outdoor */
if (!$pdo_outdoor) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database Outdoor tidak tersedia.']);
    exit;
}

try {
    // Cek apakah kolom is_dummy ada di tabel data_sensor database outdoor
    $colCheck = $pdo_outdoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if ($colCheck && $colCheck->rowCount() > 0) {
        $stmt = $pdo_outdoor->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        $stmt->execute();
        $deletedRows = $stmt->rowCount();

        // Bersihkan atau perbaiki juga data dengan nilai arus / daya ekstrem yang masuk sebagai data uji coba
        @$pdo_outdoor->exec("UPDATE data_sensor SET arus = arus / 1000.0, daya = ROUND(tegangan * (arus / 1000.0), 2) WHERE arus > 20");
        @$pdo_outdoor->exec("UPDATE data_sensor SET daya = ROUND(tegangan * arus, 2) WHERE daya > 500");

        echo json_encode(['status' => 'success', 'device' => 'outdoor', 'message' => "$deletedRows data dummy Outdoor berhasil dihapus."]);
    } else {
        echo json_encode(['status' => 'success', 'device' => 'outdoor', 'message' => "0 data dummy Outdoor berhasil dihapus (tidak ada data dummy)."]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
