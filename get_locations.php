<?php
date_default_timezone_set('Asia/Makassar');

$device = strtolower($_GET['device'] ?? $_POST['device'] ?? 'indoor');

if ($device === 'outdoor') {
    require __DIR__ . '/get_locations_outdoor.php';
} else {
    require __DIR__ . '/get_locations_indoor.php';
}
?>