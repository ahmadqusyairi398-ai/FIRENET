<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debugging setting_indoor.php</h1>";

echo "<h2>Step 1: Checking session...</h2>";
session_start();
echo "Session active. Username: " . ($_SESSION['username'] ?? 'NULL') . ", Role: " . ($_SESSION['role'] ?? 'NULL') . "<br>";

echo "<h2>Step 2: Including koneksi.php...</h2>";
try {
    require_once 'koneksi.php';
    echo "koneksi.php included successfully.<br>";
} catch (Exception $e) {
    echo "<span style='color:red;'>Failed including koneksi.php: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h2>Step 3: Checking DB Connections...</h2>";
if (isset($pdo_indoor)) {
    echo "pdo_indoor is defined.<br>";
    try {
        $pdo_indoor->query("SELECT 1");
        echo "pdo_indoor query test: <span style='color:green;'>SUCCESS</span><br>";
    } catch (Exception $e) {
        echo "pdo_indoor query test: <span style='color:red;'>FAILED - " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
} else {
    echo "pdo_indoor is NULL or undefined.<br>";
}

if (isset($conn_indoor)) {
    echo "conn_indoor is defined.<br>";
    if ($conn_indoor) {
        echo "conn_indoor status: <span style='color:green;'>CONNECTED</span><br>";
    } else {
        echo "conn_indoor status: <span style='color:red;'>FAILED</span><br>";
    }
} else {
    echo "conn_indoor is undefined.<br>";
}

echo "<h2>Step 4: Simulating setting_indoor.php database initialization...</h2>";
try {
    echo "Checking batas_sensor table...<br>";
    $checkTable = mysqli_query($conn_indoor, "SHOW TABLES LIKE 'batas_sensor'");
    if (!$checkTable) {
        throw new Exception(mysqli_error($conn_indoor));
    }
    echo "batas_sensor table check: " . mysqli_num_rows($checkTable) . " found.<br>";

    echo "Checking login table...<br>";
    $checkLoginTable = mysqli_query($conn_indoor, "SHOW TABLES LIKE 'login'");
    if (!$checkLoginTable) {
        throw new Exception(mysqli_error($conn_indoor));
    }
    echo "login table check: " . mysqli_num_rows($checkLoginTable) . " found.<br>";

    if (mysqli_num_rows($checkLoginTable) > 0) {
        echo "Checking role column in login...<br>";
        $checkRole = mysqli_query($conn_indoor, "SHOW COLUMNS FROM login LIKE 'role'");
        if (!$checkRole) {
            throw new Exception(mysqli_error($conn_indoor));
        }
        echo "role column check: " . mysqli_num_rows($checkRole) . " found.<br>";
    } else {
        echo "login table does not exist, skipping column checks.<br>";
    }
    
    echo "<span style='color:green;'>DB Initialization simulation passed!</span><br>";

} catch (Throwable $e) {
    echo "<span style='color:red; font-weight:bold;'>Error during DB Initialization: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre><br>";
}

echo "<h2>Step 5: Simulating location folder check...</h2>";
try {
    $locationDataFile = __DIR__ . '/data/locations.json';
    echo "Data folder: " . __DIR__ . "/data<br>";
    if (!is_dir(__DIR__ . '/data')) {
        echo "Creating data folder...<br>";
        if (mkdir(__DIR__ . '/data', 0777, true)) {
            echo "Data folder created successfully.<br>";
        } else {
            echo "<span style='color:red;'>Failed to create data folder!</span><br>";
        }
    } else {
        echo "Data folder already exists.<br>";
    }
    
    echo "Checking locations.json file readability/writeability...<br>";
    if (file_exists($locationDataFile)) {
        echo "locations.json exists. Writeable? " . (is_writable($locationDataFile) ? 'YES' : 'NO') . "<br>";
    } else {
        echo "locations.json does not exist. Creating default dummy...<br>";
        if (file_put_contents($locationDataFile, '[]') !== false) {
            echo "Default locations.json created successfully.<br>";
        } else {
            echo "<span style='color:red;'>Failed to create locations.json!</span><br>";
        }
    }
} catch (Throwable $e) {
    echo "<span style='color:red;'>Error during location check: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h2>Step 6: Simulating data fetches...</h2>";
try {
    echo "Fetching users count...<br>";
    $query = mysqli_query($conn_indoor, "SELECT COUNT(*) as total FROM login WHERE role = 'admin'");
    if (!$query) {
        throw new Exception(mysqli_error($conn_indoor));
    }
    $row = mysqli_fetch_assoc($query);
    echo "Active admins: " . $row['total'] . "<br>";
} catch (Throwable $e) {
    echo "<span style='color:red;'>Error during data fetch: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h2>End of Debugging.</h2>";
?>
