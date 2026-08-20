<?php
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

// Proteksi: Hanya admin indoor
$is_admin_indoor = (isset($_SESSION['login_indoor']) && $_SESSION['login_indoor'] === true && isset($_SESSION['indoor_role']) && $_SESSION['indoor_role'] === 'admin');
if (!$is_admin_indoor && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

/** @var PDO $pdo_indoor */
if (!$pdo_indoor) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database Indoor tidak tersedia.']);
    exit;
}

try {
    // Cek apakah kolom is_dummy ada di tabel data_sensor database indoor
    $colCheck = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if ($colCheck && $colCheck->rowCount() > 0) {
        $stmt = $pdo_indoor->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
        echo json_encode(['status' => 'success', 'device' => 'indoor', 'message' => "$deletedRows data dummy Indoor berhasil dihapus."]);
    } else {
        echo json_encode(['status' => 'success', 'device' => 'indoor', 'message' => "0 data dummy Indoor berhasil dihapus (tidak ada data dummy)."]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
