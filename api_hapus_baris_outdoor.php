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

// Ambil input JSON atau POST
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

$ids = [];
if (isset($input['ids']) && is_array($input['ids'])) {
    $ids = array_filter(array_map('intval', $input['ids']), function($v) { return $v > 0; });
} elseif (isset($input['id'])) {
    $singleId = intval($input['id']);
    if ($singleId > 0) {
        $ids = [$singleId];
    }
}

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid atau belum ada data yang dipilih.']);
    exit;
}

try {
    $inClause = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo_outdoor->prepare("DELETE FROM data_sensor WHERE id IN ($inClause)");
    $stmt->execute(array_values($ids));
    $deletedCount = $stmt->rowCount();
    
    if ($deletedCount > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => "$deletedCount data berhasil dihapus.",
            'deleted_count' => $deletedCount
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Data tidak ditemukan atau sudah dihapus sebelumnya.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
