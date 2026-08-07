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

$device = isset($_GET['device']) ? $_GET['device'] : (isset($_POST['device']) ? $_POST['device'] : 'outdoor');

/** @var PDO $pdo_indoor */
/** @var PDO $pdo_outdoor */

try {
    $targetPdo = ($device === 'indoor') ? $pdo_indoor : $pdo_outdoor;

    if (!$targetPdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak tersedia.']);
        exit;
    }

    // Cek apakah kolom is_dummy ada di tabel data_sensor
    $colCheck = $targetPdo->query("SHOW COLUMNS FROM data_sensor LIKE 'is_dummy'");
    if ($colCheck && $colCheck->rowCount() > 0) {
        $stmt = $targetPdo->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
        echo json_encode(['status' => 'success', 'message' => "$deletedRows data dummy berhasil dihapus."]);
    } else {
        echo json_encode(['status' => 'success', 'message' => "0 data dummy berhasil dihapus (tidak ada data dummy)."]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>