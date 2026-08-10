<?php
$device = strtolower($_GET['device'] ?? $_POST['device'] ?? 'outdoor');

if ($device === 'indoor') {
    require __DIR__ . '/api_auto_clean_indoor.php';
} else {
    require __DIR__ . '/api_auto_clean_outdoor.php';
}
?>
