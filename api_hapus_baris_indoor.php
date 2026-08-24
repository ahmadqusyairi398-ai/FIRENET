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

// Ambil input JSON atau POST
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

$idsToDelete = [];

// Cek apakah input berupa array 'ids' (bulk delete) atau single 'id'
if (isset($input['ids']) && is_array($input['ids'])) {
    foreach ($input['ids'] as $v) {
        $val = intval($v);
        if ($val > 0) $idsToDelete[] = $val;
    }
} elseif (isset($input['id'])) {
    $val = intval($input['id']);
    if ($val > 0) $idsToDelete[] = $val;
}

$idsToDelete = array_unique($idsToDelete);

if (empty($idsToDelete)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada ID data valid yang dipilih untuk dihapus.']);
    exit;
}

try {
    // Buat placeholder untuk query IN (?, ?, ...)
    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    $stmt = $pdo_indoor->prepare("DELETE FROM data_sensor WHERE id IN ($placeholders)");
    $stmt->execute(array_values($idsToDelete));
    
    $deletedCount = $stmt->rowCount();
    if ($deletedCount > 0) {
        // Ambil info storage terbaru setelah penghapusan
        $indoor_storage = get_sensor_storage_info($pdo_indoor, 'indoor');
        echo json_encode([
            'status' => 'success',
            'message' => count($idsToDelete) === 1 ? 'Data berhasil dihapus.' : "Berhasil menghapus $deletedCount data.",
            'deleted_count' => $deletedCount,
            'storage' => [
                'real' => $indoor_storage['real_formatted'],
                'dummy' => $indoor_storage['dummy_formatted']
            ]
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