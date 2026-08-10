<?php
date_default_timezone_set('Asia/Makassar');

// Ambil parameter masukan untuk mendeteksi device (JSON payload, POST, atau GET)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}
if (empty($input)) {
    $input = $_GET;
}

$device = strtolower($input['device'] ?? $_POST['device'] ?? $_GET['device'] ?? 'outdoor');

if ($device === 'indoor') {
    require __DIR__ . '/api_post_data_indoor.php';
} else {
    require __DIR__ . '/api_post_data_outdoor.php';
}
}
?>
