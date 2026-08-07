 <?php
    session_start();
    require_once 'koneksi.php';
    header('Content-Type: application/json');

    // Proteksi: Hanya admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $device = isset($_GET['device']) ? $_GET['device'] : 'outdoor';

    try {
        if ($device === 'indoor') {
            $stmt = $pdo_indoor->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        } else {
            $stmt = $pdo_outdoor->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        }

        $stmt->execute();
        $deletedRows = $stmt->rowCount();

        echo json_encode(['status' => 'success', 'message' => "$deletedRows data dummy berhasil dihapus."]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    ?>