<?php
$device = strtolower($_GET['device'] ?? $_POST['device'] ?? 'outdoor');

if ($device === 'indoor') {
    require __DIR__ . '/api_delete_dummy_indoor.php';
} else {
    require __DIR__ . '/api_delete_dummy_outdoor.php';
}
?>