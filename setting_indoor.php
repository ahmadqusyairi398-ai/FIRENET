<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// PROTEKSI: Hanya admin indoor yang bisa mengakses halaman ini
if (!isset($_SESSION['login_indoor']) || $_SESSION['login_indoor'] !== true || ($_SESSION['indoor_role'] ?? '') !== 'admin') {
    header("Location: login.php?redirect=indoor");
    exit();
}
$_SESSION['dashboard_type'] = 'indoor';

$user = isset($_SESSION['indoor_username']) ? $_SESSION['indoor_username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "Admin");
$role = isset($_SESSION['indoor_role']) ? $_SESSION['indoor_role'] : "admin";

// Koneksi Database
require_once 'koneksi.php';

// Gunakan koneksi indoor secara ketat
$conn = isset($conn_indoor) ? $conn_indoor : null;

if (!$conn) {
    die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 6px; margin: 20px;'>
        <h3>Error: Koneksi ke Database INDOOR ('indoor') Gagal.</h3>
        <p>Silakan periksa konfigurasi database Anda pada file <code>koneksi.php</code>.</p>
    </div>");
}

// ========== FUNGSI GET ICON SENSOR (PHP) ==========
function getSensorIconPHP($nama)
{
    $icons = [
        "ASAP" => "smog",
        "SUHU" => "thermometer-half",
        "KELEMBAPAN" => "tint",
        "TEGANGAN" => "bolt",
        "ARUS" => "charging-station",
        "API" => "fire"
    ];
    return isset($icons[$nama]) ? $icons[$nama] : "microchip";
}

// ========== CEK DAN BUAT TABEL LOKASI (DISESUAIKAN DENGAN SQL) ==========
function ensureLocationTable($conn) {
    if (!$conn) return false;
    
    // Mengecek tabel lokasi_monitoring
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_monitoring'");
    if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
        $createTable = "CREATE TABLE lokasi_monitoring (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_alat VARCHAR(50) NOT NULL,
            nama_lokasi VARCHAR(100) DEFAULT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            interval_kirim INT DEFAULT 15,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $createTable);
        
        // Insert default location (Sertakan id_alat & nama_lokasi)
        $defaultLocations = [
            ['id_alat' => 'LOK-001', 'nama_lokasi' => 'Gedung Elektro Poltekba', 'latitude' => -1.20249, 'longitude' => 116.88708],
            ['id_alat' => 'LOK-002', 'nama_lokasi' => 'Ruang Server Gedung Elektro Poltekba', 'latitude' => -1.20250, 'longitude' => 116.88710],
        ];
        
        foreach ($defaultLocations as $loc) {
            $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_monitoring (id_alat, nama_lokasi, latitude, longitude, interval_kirim) VALUES (?, ?, ?, ?, 15)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssdd", $loc['id_alat'], $loc['nama_lokasi'], $loc['latitude'], $loc['longitude']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        return true;
    } else {
        // Pastikan kolom nama_lokasi ada jika tabel sudah ada sebelumnya
        $checkNamaLokasiCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_monitoring LIKE 'nama_lokasi'");
        if (!$checkNamaLokasiCol || mysqli_num_rows($checkNamaLokasiCol) == 0) {
            mysqli_query($conn, "ALTER TABLE lokasi_monitoring ADD COLUMN nama_lokasi VARCHAR(100) DEFAULT NULL AFTER id_alat");
        }
        // Pastikan kolom interval_kirim ada jika belum ada
        $checkIntervalCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_monitoring LIKE 'interval_kirim'");
        if (!$checkIntervalCol || mysqli_num_rows($checkIntervalCol) == 0) {
            mysqli_query($conn, "ALTER TABLE lokasi_monitoring ADD COLUMN interval_kirim INT DEFAULT 15 AFTER longitude");
        }
    }
    return true;
}

// Jalankan fungsi untuk memastikan tabel lokasi_monitoring ada
ensureLocationTable($conn);

// ========== FUNGSI CRUD UNTUK DATA LOKASI ==========

function getLocations($conn) {
    $locations = [];
    if ($conn) {
        $query = mysqli_query($conn, "SELECT id, id_alat, nama_lokasi, latitude, longitude, interval_kirim, updated_at as last_update FROM lokasi_monitoring ORDER BY id ASC");
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $locations[] = $row;
            }
        }
    }
    return $locations;
}

function addLocation($conn, $id_alat, $nama_lokasi, $latitude, $longitude) {
    if (!$conn) return false;
    $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_monitoring (id_alat, nama_lokasi, latitude, longitude) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssdd", $id_alat, $nama_lokasi, $latitude, $longitude);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function updateLocation($conn, $id, $id_alat, $nama_lokasi, $latitude, $longitude) {
    if (!$conn) return false;
    $stmt = mysqli_prepare($conn, "UPDATE lokasi_monitoring SET id_alat = ?, nama_lokasi = ?, latitude = ?, longitude = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssddi", $id_alat, $nama_lokasi, $latitude, $longitude, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function deleteLocation($conn, $id) {
    if (!$conn) return false;
    $stmt = mysqli_prepare($conn, "DELETE FROM lokasi_monitoring WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function getLocationById($conn, $id) {
    if (!$conn) return null;
    $id = intval($id);
    $query = mysqli_query($conn, "SELECT id, id_alat, nama_lokasi, latitude, longitude, updated_at AS last_update FROM lokasi_monitoring WHERE id = $id");
    return ($query && mysqli_num_rows($query) > 0) ? mysqli_fetch_assoc($query) : null;
}

// ========== CEK DAN DIAGNOSA STRUKTUR DATABASE ==========
try {
    // 1. Cek & Buat tabel batas_sensor jika belum ada
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'batas_sensor'");
    if (!$checkTable || mysqli_num_rows($checkTable) == 0) {
        $createTable = "CREATE TABLE batas_sensor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_sensor VARCHAR(50) NOT NULL,
            nilai_alarm DECIMAL(10,2) NOT NULL,
            satuan VARCHAR(20) NOT NULL,
            batas_min DECIMAL(10,2),
            batas_max DECIMAL(10,2),
            deskripsi TEXT,
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $createTable);

        // Insert data default sensor - HANYA SENSOR YANG ADA DI indoor.sql
        $defaultSensors = [
            ['API', 1, 'Status', 0, 1, 'Deteksi api (0=Aman, 1=Terdeteksi Api)'],
            ['ASAP', 70, '%', 0, 100, 'Deteksi asap (0=Normal, 100=Tinggi)'],
            ['SUHU', 45, '°C', 20, 60, 'Suhu lingkungan'],
            ['KELEMBAPAN', 85, '%', 30, 95, 'Kelembapan udara'],
            ['TEGANGAN', 190, 'V', 150, 250, 'Tegangan listrik'],
            ['ARUS', 15, 'A', 0, 20, 'Arus listrik']
        ];

        foreach ($defaultSensors as $sensor) {
            $stmt = mysqli_prepare($conn, "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sdsdds", $sensor[0], $sensor[1], $sensor[2], $sensor[3], $sensor[4], $sensor[5]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        // Cek dan tambahkan sensor baru jika belum ada
        $existingSensors = [];
        $checkExisting = mysqli_query($conn, "SELECT nama_sensor FROM batas_sensor");
        if ($checkExisting) {
            while ($row = mysqli_fetch_assoc($checkExisting)) {
                $existingSensors[] = $row['nama_sensor'];
            }
        }
        
        // Hanya tambahkan sensor yang ada di indoor.sql
        $newSensors = [
            ['API', 1, 'Status', 0, 1, 'Deteksi api (0=Aman, 1=Terdeteksi Api)']
        ];
        
        foreach ($newSensors as $sensor) {
            if (!in_array($sensor[0], $existingSensors)) {
                $stmt = mysqli_prepare($conn, "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sdsdds", $sensor[0], $sensor[1], $sensor[2], $sensor[3], $sensor[4], $sensor[5]);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        // Hapus sensor yang tidak relevan (DAYA, CO, dll)
        $sensorsToRemove = ['DAYA', 'CO', 'KECEPATAN_ANGIN', 'ARAH_ANGIN'];
        foreach ($sensorsToRemove as $sensor) {
            mysqli_query($conn, "DELETE FROM batas_sensor WHERE nama_sensor = '$sensor'");
        }
    }

    // 2. Cek & Buat tabel login jika belum ada
    $checkLoginTable = mysqli_query($conn, "SHOW TABLES LIKE 'login'");
    if (!$checkLoginTable || mysqli_num_rows($checkLoginTable) == 0) {
        $createLoginTable = "CREATE TABLE login (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') DEFAULT 'user',
            status ENUM('pending','approved','rejected') DEFAULT 'approved',
            created_at DATETIME,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $createLoginTable);
        
        // Insert default admin if not exists
        $checkAdmin = mysqli_query($conn, "SELECT id FROM login WHERE username = 'admin'");
        if (!$checkAdmin || mysqli_num_rows($checkAdmin) == 0) {
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO login (username, password, role, status, created_at) VALUES ('admin', '$defaultPassword', 'admin', 'approved', NOW())");
        }
    }

    // 3. Cek dan tambahkan kolom role, status, updated_at di tabel login jika belum ada
    $checkRole = mysqli_query($conn, "SHOW COLUMNS FROM login LIKE 'role'");
    if (!$checkRole || mysqli_num_rows($checkRole) == 0) {
        mysqli_query($conn, "ALTER TABLE login ADD COLUMN role ENUM('admin','user') DEFAULT 'user'");
    }
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM login LIKE 'status'");
    if (!$checkStatus || mysqli_num_rows($checkStatus) == 0) {
        mysqli_query($conn, "ALTER TABLE login ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'approved'");
    }
    $checkUpdatedAt = mysqli_query($conn, "SHOW COLUMNS FROM login LIKE 'updated_at'");
    if (!$checkUpdatedAt || mysqli_num_rows($checkUpdatedAt) == 0) {
        mysqli_query($conn, "ALTER TABLE login ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // 4. Cek dan tambahkan kolom last_update di tabel batas_sensor jika belum ada
    $checkLastUpdate = mysqli_query($conn, "SHOW COLUMNS FROM batas_sensor LIKE 'last_update'");
    if (!$checkLastUpdate || mysqli_num_rows($checkLastUpdate) == 0) {
        mysqli_query($conn, "ALTER TABLE batas_sensor ADD COLUMN last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

} catch (Throwable $e) {
    // Log error secara internal agar tidak menghasilkan HTTP 500 ke user
    error_log("Database initialization error: " . $e->getMessage());
}

// ========== FUNGSI USER ==========
function getUsers($conn)
{
    $users = [];
    if ($conn) {
        $query = mysqli_query($conn, "SELECT id, username, role, updated_at as last_update FROM login ORDER BY id DESC");
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $users[] = $row;
            }
        }
    }
    return $users;
}

function countActiveAdmins($conn)
{
    if ($conn) {
        $query = mysqli_query($conn, "SELECT COUNT(*) as total FROM login WHERE role = 'admin'");
        if ($query) {
            $row = mysqli_fetch_assoc($query);
            return isset($row['total']) ? $row['total'] : 0;
        }
    }
    return 0;
}

// ========== FUNGSI UNTUK BATAS SENSOR ==========
function getSensorAlarmData($conn)
{
    $sensors = [];
    if ($conn) {
        $sql = "SELECT * FROM batas_sensor ORDER BY id ASC";
        $query = mysqli_query($conn, $sql);
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                // Pastikan semua nilai memiliki default jika NULL
                $sensors[] = [
                    'id' => isset($row['id']) ? $row['id'] : 0,
                    'nama_sensor' => isset($row['nama_sensor']) ? $row['nama_sensor'] : '-',
                    'nilai_alarm' => isset($row['nilai_alarm']) ? floatval($row['nilai_alarm']) : 0,
                    'satuan' => isset($row['satuan']) ? $row['satuan'] : '',
                    'batas_min' => isset($row['batas_min']) ? floatval($row['batas_min']) : 0,
                    'batas_max' => isset($row['batas_max']) ? floatval($row['batas_max']) : 100,
                    'deskripsi' => isset($row['deskripsi']) ? $row['deskripsi'] : '',
                    'last_update' => isset($row['last_update']) ? $row['last_update'] : date('Y-m-d H:i:s')
                ];
            }
        }
    }
    return $sensors;
}

function updateSensorAlarm($conn, $id, $nilai_alarm, $batas_min, $batas_max)
{
    $stmt = mysqli_prepare($conn, "UPDATE batas_sensor SET nilai_alarm = ?, batas_min = ?, batas_max = ?, last_update = NOW() WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "dddi", $nilai_alarm, $batas_min, $batas_max, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

// ========== TAMBAH SENSOR BARU ==========
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_sensor'])) {
    $nama_sensor = trim($_POST['sensor_name']);
    $nilai_alarm = floatval($_POST['alarm_value']);
    $satuan = trim($_POST['satuan']);
    $batas_min = floatval($_POST['batas_min']);
    $batas_max = floatval($_POST['batas_max']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    if (!empty($nama_sensor) && !empty($satuan)) {
        $check = mysqli_query($conn, "SELECT id FROM batas_sensor WHERE nama_sensor = '$nama_sensor'");
        if ($check && mysqli_num_rows($check) > 0) {
            $error_message = "Sensor '$nama_sensor' sudah terdaftar!";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sdsdds", $nama_sensor, $nilai_alarm, $satuan, $batas_min, $batas_max, $deskripsi);
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Sensor '$nama_sensor' berhasil ditambahkan!";
                } else {
                    $error_message = "Gagal menambahkan sensor!";
                }
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        $error_message = "Nama sensor dan satuan harus diisi!";
    }
}

$maxAdmin = 2;
$adminCount = countActiveAdmins($conn);
$canAddAdmin = $adminCount < $maxAdmin;

// ========== PROSES POST ==========
$success_message = $error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // UPDATE NILAI ALARM SENSOR
    if (isset($_POST['update_alarm_value'])) {
        $sensor_id = intval($_POST['sensor_id']);
        $new_value = floatval($_POST['alarm_value']);
        $batas_min = floatval($_POST['batas_min']);
        $batas_max = floatval($_POST['batas_max']);

        if ($batas_min >= $batas_max) {
            $error_message = "Batas minimum harus lebih kecil dari batas maksimum!";
        } else {
            $checkQuery = mysqli_query($conn, "SELECT * FROM batas_sensor WHERE id = $sensor_id");
            if ($checkQuery) {
                $sensor = mysqli_fetch_assoc($checkQuery);
                if ($sensor) {
                    if ($new_value >= $batas_min && $new_value <= $batas_max) {
                        if (updateSensorAlarm($conn, $sensor_id, $new_value, $batas_min, $batas_max)) {
                            $success_message = "Nilai alarm dan batas range {$sensor['nama_sensor']} berhasil diupdate!";
                        } else {
                            $error_message = "Gagal mengupdate nilai alarm!";
                        }
                    } else {
                        $error_message = "Nilai alarm harus antara {$batas_min} - {$batas_max} {$sensor['satuan']}!";
                    }
                } else {
                    $error_message = "Sensor tidak ditemukan!";
                }
            }
        }
    }

    // CRUD Lokasi via MySQL Database
    if (isset($_POST['add_location'])) {
        $id_alat = trim($_POST['id_alat']);
        $nama_lokasi = trim($_POST['nama_lokasi'] ?? '');
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $interval_kirim = isset($_POST['interval_kirim']) ? intval($_POST['interval_kirim']) : 15;

        if (!empty($id_alat) && $latitude != 0 && $longitude != 0 && $interval_kirim > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_monitoring (id_alat, nama_lokasi, latitude, longitude, interval_kirim) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssddi", $id_alat, $nama_lokasi, $latitude, $longitude, $interval_kirim);
                if(mysqli_stmt_execute($stmt)) {
                    $success_message = "Lokasi baru berhasil ditambahkan!";
                    // Kirim instruksi/perintah (HTTP POST) ke Node-RED Indoor (Port 1881)
                    $url = "http://localhost:1881/set_interval";
                    $payload = json_encode(array("id_alat" => $id_alat, "interval" => $interval_kirim));
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                    @curl_exec($ch);
                    @curl_close($ch);
                } else {
                    $error_message = "Gagal menambahkan lokasi!";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $error_message = "ID Alat, Latitude, Longitude, dan Interval harus diisi!";
        }
    }

    if (isset($_POST['edit_location'])) {
        $location_id = intval($_POST['location_id']);
        $id_alat = trim($_POST['edit_id_alat']);
        $nama_lokasi = trim($_POST['edit_nama_lokasi'] ?? '');
        $latitude = floatval($_POST['latitude'] ?? $_POST['edit_latitude']);
        $longitude = floatval($_POST['longitude'] ?? $_POST['edit_longitude']);
        $interval_kirim = isset($_POST['edit_interval_kirim']) ? intval($_POST['edit_interval_kirim']) : 15;

        if (!empty($id_alat) && $latitude != 0 && $longitude != 0 && $interval_kirim > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE lokasi_monitoring SET id_alat = ?, nama_lokasi = ?, latitude = ?, longitude = ?, interval_kirim = ?, updated_at = current_timestamp() WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssddii", $id_alat, $nama_lokasi, $latitude, $longitude, $interval_kirim, $location_id);
                if(mysqli_stmt_execute($stmt)) {
                    $success_message = "Lokasi berhasil diperbarui!";
                    // Kirim instruksi/perintah (HTTP POST) ke Node-RED Indoor (Port 1881)
                    $url = "http://localhost:1881/set_interval";
                    $payload = json_encode(array("id_alat" => $id_alat, "interval" => $interval_kirim));
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                    @curl_exec($ch);
                    @curl_close($ch);
                } else {
                    $error_message = "Gagal memperbarui lokasi!";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $error_message = "ID Alat, Latitude, Longitude, dan Interval harus diisi!";
        }
    }

    if (isset($_POST['delete_location'])) {
        $location_id = intval($_POST['location_id']);
        if (deleteLocation($conn, $location_id)) {
            $success_message = "Lokasi berhasil dihapus!";
        } else {
            $error_message = "Gagal menghapus lokasi!";
        }
    }

    // Manajemen User
    if (isset($_POST['add_user'])) {
        $new_username = trim($_POST['new_username']);
        $new_password = trim($_POST['new_password']);
        $new_role = $_POST['new_role'] ?? 'user';
        if (!empty($new_username) && !empty($new_password)) {
            $cek = mysqli_query($conn, "SELECT id FROM login WHERE username = '$new_username'");
            if ($cek && mysqli_num_rows($cek) > 0) {
                $error_message = "Username sudah terdaftar!";
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_query($conn, "INSERT INTO login (username, password, role, status, created_at) VALUES ('$new_username', '$password_hash', '$new_role', 'approved', NOW())");
                $success_message = "Akun user berhasil ditambahkan!";
            }
        } else {
            $error_message = "Username dan password harus diisi!";
        }
    }

    if (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $edit_username = trim($_POST['edit_username']);
        $edit_role = $_POST['edit_role'];
        $edit_password = trim($_POST['edit_password']);
        if (!empty($edit_password)) {
            $password_hash = password_hash($edit_password, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE login SET username='$edit_username', password='$password_hash', role='$edit_role', updated_at=NOW() WHERE id='$user_id'");
        } else {
            mysqli_query($conn, "UPDATE login SET username='$edit_username', role='$edit_role', updated_at=NOW() WHERE id='$user_id'");
        }
        $success_message = "Akun user berhasil diperbarui!";
    }

    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        $checkAdmin = mysqli_query($conn, "SELECT username FROM login WHERE id = $user_id AND username = 'admin'");
        if ($checkAdmin && mysqli_num_rows($checkAdmin) > 0) {
            $error_message = "Tidak dapat menghapus akun admin utama!";
        } else {
            $delete = mysqli_query($conn, "DELETE FROM login WHERE id = $user_id");
            if ($delete) {
                $success_message = "Akun user berhasil dihapus!";
            } else {
                $error_message = "Gagal menghapus akun: " . mysqli_error($conn);
            }
        }
    }
}

// Ambil data terbaru
$users = getUsers($conn);
$locations = getLocations($conn);
$sensorAlarmData = getSensorAlarmData($conn);
$adminCount = countActiveAdmins($conn);
$canAddAdmin = $adminCount < $maxAdmin;
$totalUsers = count($users);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setting - FIREDETECTOR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- Custom CSS Setting Indoor -->
    <link rel="stylesheet" href="css/setting_indoor.css">
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h3><i class="fas fa-cog"></i> FireDetector</h3>
        <a href="dashboard_admin_indoor.php" class="menu-btn"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="chart_indoor.php" class="menu-btn"><i class="fas fa-chart-line"></i><span>CHART</span></a>
        <a href="tabel_indoor.php" class="menu-btn"><i class="fas fa-table"></i><span>TABEL</span></a>
        <a href="setting_indoor.php" class="menu-btn active"><i class="fas fa-cog"></i><span>SETTING</span></a>
        <!-- Tombol Logout dengan onclick untuk membuka modal -->
        <button class="menu-btn logout" onclick="openLogoutModal()">
            <i class="fas fa-sign-out-alt"></i>
            <span>LOGOUT</span>
        </button>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="header">
            <h2><i class="fas fa-cog"></i> Setting</h2>
            <div class="header-right">
                <!-- Tombol HOME dengan onclick untuk membuka modal -->
                <button class="btn-home-header" onclick="openHomeModal()">
                    <i class="fas fa-home"></i> HOME
                </button>
                <div class="user-info"><i class="fas fa-user-circle"></i><span>Halo, <?= htmlspecialchars($user) ?></span></div>
            </div>
        </div>

        <!-- TAB MENU -->
        <div class="tab-menu">
            <button class="tab-btn active" onclick="openTab('tab1', this)"><i class="fas fa-sliders-h"></i> Ubah Nilai Alarm</button>
            <button class="tab-btn" onclick="openTab('tab2', this)"><i class="fas fa-map-marker-alt"></i> Setting Lokasi Alat</button>
            <button class="tab-btn" onclick="openTab('tab3', this)"><i class="fas fa-users"></i> Daftar Akun User</button>
        </div>

        <!-- TAB 1: Ubah Nilai Alarm -->
        <div id="tab1" class="tab-content active">
            <div class="card">
                <h3><i class="fas fa-exclamation-triangle"></i> Ubah Nilai Alarm Sensor</h3>
                <p style="margin-bottom:15px; color:#666; font-size:14px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Total Sensor: <?= count($sensorAlarmData) ?></strong> | 
                    Atur nilai alarm dan batas range untuk setiap sensor
                </p>

                <div class="btn-group">
                    <button class="btn-primary" onclick="openAddSensorModal()">
                        <i class="fas fa-plus"></i> Tambah Sensor
                    </button>
                </div>

                <div class="table-container">
                    <table id="alarmTable" class="data-table">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>NAMA SENSOR</th>
                                <th>NILAI ALARM</th>
                                <th>SATUAN</th>
                                <th>BATAS MIN</th>
                                <th>BATAS MAX</th>
                                <th>WAKTU UPDATE</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sensorAlarmData) > 0): ?>
                                <?php foreach ($sensorAlarmData as $index => $sensor): ?>
                                    <?php 
                                    // Handle null values dengan aman
                                    $nama_sensor = isset($sensor['nama_sensor']) ? htmlspecialchars($sensor['nama_sensor']) : '-';
                                    $deskripsi = isset($sensor['deskripsi']) ? htmlspecialchars($sensor['deskripsi']) : '';
                                    $satuan = isset($sensor['satuan']) ? htmlspecialchars($sensor['satuan']) : '';
                                    $nilai_alarm = isset($sensor['nilai_alarm']) ? number_format(floatval($sensor['nilai_alarm']), 0) : '0';
                                    $batas_min = isset($sensor['batas_min']) ? number_format(floatval($sensor['batas_min']), 0) : '0';
                                    $batas_max = isset($sensor['batas_max']) ? number_format(floatval($sensor['batas_max']), 0) : '0';
                                    $last_update = isset($sensor['last_update']) ? $sensor['last_update'] : '-';
                                    $sensor_id = isset($sensor['id']) ? $sensor['id'] : 0;
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <i class="fas fa-<?= getSensorIconPHP($nama_sensor) ?>" style="color: <?= in_array($nama_sensor, ['ASAP', 'API']) ? '#dc3545' : '#00b4db' ?>;"></i>
                                            <strong><?= $nama_sensor ?></strong>
                                            <br><small style="color:#666;"><?= $deskripsi ?></small>
                                        </td>
                                        <td>
                                            <strong style="color: <?= in_array($nama_sensor, ['ASAP', 'API']) ? '#dc3545' : '#1e3c72' ?>;">
                                                <?= $nilai_alarm ?> <?= $satuan ?>
                                            </strong>
                                        </td>
                                        <td><?= $satuan ?></td>
                                        <td><?= $batas_min ?> <?= $satuan ?></td>
                                        <td><?= $batas_max ?> <?= $satuan ?></td>
                                        <td><?= $last_update ?></td>
                                        <td>
                                            <button type="button" class="btn-warning btn-edit-alarm" 
                                                data-id="<?= $sensor_id ?>"
                                                data-nama="<?= $nama_sensor ?>"
                                                data-nilai="<?= isset($sensor['nilai_alarm']) ? $sensor['nilai_alarm'] : 0 ?>"
                                                data-satuan="<?= $satuan ?>"
                                                data-min="<?= isset($sensor['batas_min']) ? $sensor['batas_min'] : 0 ?>"
                                                data-max="<?= isset($sensor['batas_max']) ? $sensor['batas_max'] : 0 ?>">
                                                <i class="fas fa-edit"></i> EDIT
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 10px;"></i>
                                        Tidak ada data sensor
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: Setting Lokasi Alat -->
        <div id="tab2" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-map-marker-alt"></i> Setting Lokasi Alat</h3>
                <p style="margin-bottom:15px; color:#666; font-size:14px;">Atur koordinat dan interval pengiriman data lokasi monitoring.</p>

                <div style="margin-bottom:20px;">
                    <button class="btn-primary" onclick="openAddLocationModal()"><i class="fas fa-plus"></i> Tambah Lokasi</button>
                </div>
                <div class="table-container">
                    <table class="data-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>ID ALAT</th>
                                <th>NAMA LOKASI</th>
                                <th>KETERANGAN</th>
                                <th>KOORDINAT</th>
                                <th>INTERVAL</th>
                                <th>WAKTU UPDATE</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="location-table-body">
                            <?php if (count($locations) > 0): ?>
                                <?php foreach ($locations as $index => $loc): ?>
                                    <?php
                                    $loc_id = isset($loc['id']) ? (int)$loc['id'] : 0;
                                    $id_alat_display = htmlspecialchars($loc['id_alat']);
                                    $nama_lokasi_display = isset($loc['nama_lokasi']) && $loc['nama_lokasi'] !== '' ? htmlspecialchars($loc['nama_lokasi']) : '-';

                                    // ----------------------------------------------------
                                    // LOGIKA OTOMATIS: LOK-002 Adalah Fisik Utama, Sisanya Dummy
                                    $id_alat_utama = 'LOK-002';
                                    // ----------------------------------------------------

                                    $is_utama = ($id_alat_display === $id_alat_utama);

                                    // Jika Utama = Hijau, Jika selain Utama = Kuning (Dummy)
                                    $badge = $is_utama
                                        ? '<span style="background:#28a745; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; color:#fff;"><i class="fas fa-microchip"></i> Alat Utama (Fisik)</span>'
                                        : '<span style="background:#ffc107; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; color:#000;"><i class="fas fa-robot"></i> Simulasi (Dummy)</span>';
                                    ?>
                                    <tr id="loc-row-<?= $loc_id ?>">
                                        <td><?= $index + 1 ?></td>
                                        <td><strong><?= $id_alat_display ?></strong></td>
                                        <td><?= $nama_lokasi_display ?></td>
                                        <td><?= $badge ?></td>
                                        <td>
                                            Lat: <?= isset($loc['latitude']) ? number_format($loc['latitude'], 6) : '-' ?><br>
                                            Lng: <?= isset($loc['longitude']) ? number_format($loc['longitude'], 6) : '-' ?>
                                        </td>
                                        <td class="loc-interval-col"><span style="font-weight:bold; color:#1e3c72;"><?= isset($loc['interval_kirim']) ? $loc['interval_kirim'] : '15' ?></span> detik</td>
                                        <td class="loc-update-col"><?= isset($loc['last_update']) ? $loc['last_update'] : date('Y-m-d H:i:s') ?></td>
                                        <td class="action-buttons">
                                            <?php
                                            $id = isset($loc['id']) ? (int)$loc['id'] : 0;
                                            $nama_lokasi_val = isset($loc['nama_lokasi']) ? htmlspecialchars($loc['nama_lokasi'], ENT_QUOTES) : '';
                                            $lat = isset($loc['latitude']) ? (float)$loc['latitude'] : 0;
                                            $lng = isset($loc['longitude']) ? (float)$loc['longitude'] : 0;
                                            $interval = isset($loc['interval_kirim']) ? (int)$loc['interval_kirim'] : 15;
                                            ?>
                                            <button class="btn-warning" onclick="openEditLocationModal(<?= $id ?>, '<?= $id_alat_display ?>', '<?= $nama_lokasi_val ?>', <?= $lat ?>, <?= $lng ?>, <?= $interval ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-danger btn-delete-location" data-id="<?= $id ?>">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 30px; display: block; margin-bottom: 10px;"></i>
                                        Tidak ada data lokasi
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: DAFTAR AKUN USER -->
        <div id="tab3" class="tab-content">
            <div class="welcome-banner">
                <h3><i class="fas fa-user-shield"></i> HALO, Admin</h3>
                <button class="btn-primary" onclick="openAddUserModal()"><i class="fas fa-user-plus"></i> TAMBAH AKUN</button>
            </div>
            <?php if (!$canAddAdmin): ?>
                <div class="warning-text"><i class="fas fa-exclamation-triangle"></i> <strong>Perhatian!</strong> Batas maksimal akun admin (<?= $maxAdmin ?>) telah tercapai.</div>
            <?php endif; ?>
            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="background: #d1fae5; padding: 8px 15px; border-radius: 10px;"><i class="fas fa-users"></i> Total akun: <?= $totalUsers ?></div>
                <div style="background: #e0e7ff; padding: 8px 15px; border-radius: 10px;"><i class="fas fa-user-shield"></i> Admin Aktif: <?= $adminCount ?> / <?= $maxAdmin ?></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-check-circle"></i> Daftar Akun User</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>USERNAME</th>
                                <th>ROLE</th>
                                <th>WAKTU UPDATE</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php $no = 1;
                                foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><i class="fas fa-user-circle"></i> <?= htmlspecialchars($u['username']) ?></td>
                                        <td><span class="role-badge <?= $u['role'] == 'admin' ? 'role-admin' : 'role-user' ?>">
                                                <i class="fas <?= $u['role'] == 'admin' ? 'fa-crown' : 'fa-user' ?>"></i> <?= strtoupper($u['role']) ?>
                                            </span></td>
                                        <td><?= $u['last_update'] ?></td>
                                        <td>
                                            <button type="button" class="btn-warning btn-edit-user" 
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                                data-role="<?= $u['role'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($u['username'] != 'admin'): ?>
                                                <button class="btn-danger btn-delete-user" data-id="<?= $u['id'] ?>" data-username="<?= $u['username'] ?>">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            <?php else: ?>
                                                <span style="color:#999; font-size:12px;">(Admin Utama)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;">Belum ada user</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== MODAL LOGOUT ========== -->
    <!-- ============================================================ -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-box">
            <div class="modal-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h2>Apakah Anda yakin keluar?</h2>
            
            <div class="modal-buttons">
                <button class="btn-modal btn-cancel" onclick="closeLogoutModal()">
                    <i class="fas fa-times"></i> CANCEL
                </button>
                <a href="logout.php?redirect=indoor" class="btn-modal btn-logout-confirm">
                    <i class="fas fa-sign-out-alt"></i> LOGOUT
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ========== MODAL HOME ========== -->
    <!-- ============================================================ -->
    <div class="modal-overlay" id="homeModal">
        <div class="modal-box">
            <div class="modal-icon-home">
                <i class="fas fa-home"></i>
            </div>
            
            <h2>Apakah Anda yakin ingin kembali ke Halaman Utama?</h2>
            
            <div class="modal-buttons">
                <button class="btn-modal btn-cancel" onclick="closeHomeModal()">
                    <i class="fas fa-times"></i> CANCEL
                </button>
                <a href="home.php" class="btn-modal btn-home-confirm">
                    <i class="fas fa-home"></i> YES
                </a>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT NILAI ALARM -->
    <div id="editAlarmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-edit"></i> Edit Nilai Alarm & Batas Sensor</h4>
                <span class="modal-close" onclick="closeModal('editAlarmModal')">&times;</span>
            </div>
            <form method="POST" id="editAlarmForm">
                <input type="hidden" name="sensor_id" id="edit_sensor_id">
                <input type="hidden" name="update_alarm_value" value="1">
                <div class="form-group">
                    <label>Nama Sensor</label>
                    <input type="text" id="edit_sensor_name" readonly style="background:#f5f5f5">
                </div>
                <div class="form-group">
                    <label>Batas Minimum</label>
                    <input type="number" name="batas_min" id="edit_batas_min" step="any" required>
                    <small style="color:#666; display:block; margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Nilai terendah yang diperbolehkan untuk sensor ini
                    </small>
                </div>
                <div class="form-group">
                    <label>Batas Maksimum</label>
                    <input type="number" name="batas_max" id="edit_batas_max" step="any" required>
                    <small style="color:#666; display:block; margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Nilai tertinggi yang diperbolehkan untuk sensor ini
                    </small>
                </div>
                <div class="form-group">
                    <label>Satuan</label>
                    <input type="text" id="edit_satuan" readonly style="background:#f5f5f5">
                </div>
                <div class="form-group">
                    <label>Nilai Alarm</label>
                    <input type="number" name="alarm_value" id="edit_alarm_value" step="any" required>
                    <small id="range_warning" style="color:#e74c3c; display:block; margin-top:5px;"></small>
                </div>
                <button type="submit" name="update_alarm_value" class="btn-primary" style="width:100%">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH SENSOR -->
    <div id="addSensorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-plus"></i> Tambah Sensor Baru</h4>
                <span class="modal-close" onclick="closeModal('addSensorModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="add_sensor" value="1">
                <div class="form-group">
                    <label>Nama Sensor <span style="color:red;">*</span></label>
                    <input type="text" name="sensor_name" placeholder="Contoh: CO2, O2, dll" required>
                    <small style="color:#666; display:block; margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Masukkan nama sensor baru (huruf kapital)
                    </small>
                </div>
                <div class="form-group">
                    <label>Nilai Alarm <span style="color:red;">*</span></label>
                    <input type="number" name="alarm_value" step="any" required>
                </div>
                <div class="form-group">
                    <label>Satuan <span style="color:red;">*</span></label>
                    <input type="text" name="satuan" placeholder="Contoh: %, °C, ppm, V, A" required>
                </div>
                <div class="form-group">
                    <label>Batas Minimum</label>
                    <input type="number" name="batas_min" step="any" value="0">
                </div>
                <div class="form-group">
                    <label>Batas Maksimum</label>
                    <input type="number" name="batas_max" step="any" value="100">
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <input type="text" name="deskripsi" placeholder="Deskripsi sensor">
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Tambah Sensor
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH LOKASI -->
    <div id="addLocationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-plus"></i> Tambah Lokasi</h4>
                <span class="modal-close" onclick="closeModal('addLocationModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>ID Alat <span style="color:red;">*</span></label>
                    <input type="text" name="id_alat" id="add_id_alat" placeholder="Contoh: LOK-001, LOK-002, LOK-003" required>
                    <small style="color:#666; display:block; margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Masukkan ID unik untuk alat monitoring
                    </small>
                </div>
                <div class="form-group">
                    <label>Nama Lokasi</label>
                    <input type="text" name="nama_lokasi" id="add_nama_lokasi" placeholder="Contoh: Gedung Elektro Poltekba">
                    <small style="color:#666; display:block; margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Masukkan nama lokasi tempat alat dipasang
                    </small>
                </div>
                <div class="form-group">
                    <label>Latitude <span style="color:red;">*</span></label>
                    <input type="number" name="latitude" id="add_latitude" step="any" required>
                    <small>Contoh: -1.20249 (negatif untuk selatan)</small>
                </div>
                <div class="form-group">
                    <label>Longitude <span style="color:red;">*</span></label>
                    <input type="number" name="longitude" id="add_longitude" step="any" required>
                    <small>Contoh: 116.88708</small>
                </div>
                <div class="form-group">
                    <label>Interval Kirim (detik) <span style="color:red;">*</span></label>
                    <input type="number" name="interval_kirim" min="1" value="15" required>
                    <small>Waktu jeda pengiriman data sensor (Default: 15)</small>
                </div>
                <div style="background:#e0f2fe; padding:10px; border-radius:8px; margin-bottom:15px; font-size:12px; color:#0369a1;">
                    <strong><i class="fas fa-info-circle"></i> Catatan Sistem:</strong><br>
                    ID <b>LOK-002</b> secara otomatis ditetapkan sebagai <b>Alat Utama (Fisik)</b>. ID alat lain yang Anda tambahkan akan otomatis dianggap sebagai lokasi <b>Simulasi (Dummy)</b>.
                </div>
                <button type="submit" name="add_location" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Simpan Lokasi
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT LOKASI -->
    <div id="editLocationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-edit"></i> Edit Lokasi</h4>
                <span class="modal-close" onclick="closeModal('editLocationModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="location_id" id="edit_location_id">
                <div class="form-group">
                    <label>ID Alat <span style="color:red;">*</span></label>
                    <input type="text" name="edit_id_alat" id="edit_id_alat" required>
                </div>
                <div class="form-group">
                    <label>Nama Lokasi</label>
                    <input type="text" name="edit_nama_lokasi" id="edit_nama_lokasi" placeholder="Contoh: Gedung Elektro Poltekba">
                </div>
                <div class="form-group">
                    <label>Latitude <span style="color:red;">*</span></label>
                    <input type="number" name="edit_latitude" id="edit_latitude" step="any" required>
                </div>
                <div class="form-group">
                    <label>Longitude <span style="color:red;">*</span></label>
                    <input type="number" name="edit_longitude" id="edit_longitude" step="any" required>
                </div>
                <div class="form-group">
                    <label>Interval Kirim (detik) <span style="color:red;">*</span></label>
                    <input type="number" name="edit_interval_kirim" id="edit_interval_kirim" min="1" required>
                </div>
                <button type="submit" name="edit_location" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH USER -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-user-plus"></i> Tambah Akun</h4>
                <span class="modal-close" onclick="closeModal('addUserModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="new_username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="new_role">
                        <option value="user">User</option>
                        <option value="admin" <?= !$canAddAdmin ? 'disabled' : '' ?>><?= !$canAddAdmin ? 'Admin (Batas tercapai)' : 'Admin' ?></option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn-primary">Tambah Akun</button>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT USER -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-user-edit"></i> Edit Akun</h4>
                <span class="modal-close" onclick="closeModal('editUserModal')">&times;</span>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="edit_user" value="1">
                <div class="form-group">
                    <label>Username <span style="color:red;">*</span></label>
                    <input type="text" name="edit_username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>Password (kosongkan jika tidak diubah)</label>
                    <input type="password" name="edit_password" id="edit_password" placeholder="Kosongkan jika tidak diubah">
                </div>
                <div class="form-group">
                    <label>Role <span style="color:red;">*</span></label>
                    <select name="edit_role" id="edit_role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- External JS Setting Indoor -->
    <script src="js/setting_indoor.js"></script>
    <script>
        <?php if ($success_message): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= addslashes($success_message) ?>',
                timer: 2000,
                showConfirmButton: false
            });
        <?php elseif ($error_message): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '<?= addslashes($error_message) ?>'
            });
        <?php endif; ?>
    </script>
</body>

</html>