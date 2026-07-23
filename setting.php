<?php
session_start();

// Jika tipe dashboard adalah indoor, alihkan ke setting_indoor.php
if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'indoor') {
    header("Location: setting_indoor.php");
    exit();
}
$_SESSION['dashboard_type'] = 'outdoor';

// PROTEKSI: Hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard_admin.php");
    exit();
}

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "Admin";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "admin";

// Koneksi Database
require_once 'koneksi.php';

// Gunakan koneksi outdoor secara ketat
$conn = isset($conn_outdoor) ? $conn_outdoor : null;

if (!$conn) {
    die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 6px; margin: 20px;'>
        <h3>Error: Koneksi ke Database OUTDOOR ('outdoor') Gagal.</h3>
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
        "DAYA" => "solar-panel",
        "KECEPATAN ANGIN" => "wind",
        "ARAH ANGIN" => "compass",
        "CO" => "skull-crossbones"
    ];
    return $icons[$nama] ?? "microchip";
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

        $defaultSensors = [
            ['ASAP', 70, '%', 0, 100, 'Deteksi asap (0=Normal, 100=Tinggi)'],
            ['SUHU', 45, '°C', 20, 60, 'Suhu lingkungan'],
            ['KELEMBAPAN', 85, '%', 30, 95, 'Kelembapan udara'],
            ['TEGANGAN', 190, 'V', 150, 250, 'Tegangan listrik'],
            ['ARUS', 15, 'A', 0, 20, 'Arus listrik'],
            ['DAYA', 100, 'W', 0, 500, 'Daya listrik'],
            ['KECEPATAN ANGIN', 15, 'm/s', 0, 30, 'Kecepatan angin'],
            ['ARAH ANGIN', 0, '°', 0, 360, 'Arah angin dalam derajat'],
            ['CO', 35, 'ppm', 0, 100, 'Karbon Monoksida (0-35=Normal, 35-50=Waspada, >50=Berbahaya)']
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
        $existingSensors = [];
        $checkExisting = mysqli_query($conn, "SELECT nama_sensor FROM batas_sensor");
        if ($checkExisting) {
            while ($row = mysqli_fetch_assoc($checkExisting)) {
                $existingSensors[] = $row['nama_sensor'];
            }
        }
        
        $newSensors = [
            ['DAYA', 100, 'W', 0, 500, 'Daya listrik'],
            ['KECEPATAN ANGIN', 15, 'm/s', 0, 30, 'Kecepatan angin'],
            ['ARAH ANGIN', 0, '°', 0, 360, 'Arah angin dalam derajat'],
            ['CO', 35, 'ppm', 0, 100, 'Karbon Monoksida (0-35=Normal, 35-50=Waspada, >50=Berbahaya)']
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
    }

    // 2. Cek & Buat tabel pengguna jika belum ada
    $checkPenggunaTable = mysqli_query($conn, "SHOW TABLES LIKE 'pengguna'");
    if (!$checkPenggunaTable || mysqli_num_rows($checkPenggunaTable) == 0) {
        $createPenggunaTable = "CREATE TABLE pengguna (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') DEFAULT 'user',
            status ENUM('pending','approved','rejected') DEFAULT 'approved',
            created_at DATETIME,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $createPenggunaTable);
    }

    // Cek kolom tambahan tabel pengguna
    $checkRole = mysqli_query($conn, "SHOW COLUMNS FROM pengguna LIKE 'role'");
    if (!$checkRole || mysqli_num_rows($checkRole) == 0) {
        mysqli_query($conn, "ALTER TABLE pengguna ADD COLUMN role ENUM('admin','user') DEFAULT 'user'");
    }
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM pengguna LIKE 'status'");
    if (!$checkStatus || mysqli_num_rows($checkStatus) == 0) {
        mysqli_query($conn, "ALTER TABLE pengguna ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'approved'");
    }
    $checkUpdatedAt = mysqli_query($conn, "SHOW COLUMNS FROM pengguna LIKE 'updated_at'");
    if (!$checkUpdatedAt || mysqli_num_rows($checkUpdatedAt) == 0) {
        mysqli_query($conn, "ALTER TABLE pengguna ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // 3. Cek dan buat tabel lokasi_alat jika belum ada
    $checkLokasiTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_alat'");
    if (!$checkLokasiTable || mysqli_num_rows($checkLokasiTable) == 0) {
        $createLokasiTable = "CREATE TABLE lokasi_alat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_alat VARCHAR(50) NOT NULL,
            nama_lokasi VARCHAR(100) DEFAULT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $createLokasiTable);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_alat (id_alat, nama_lokasi, latitude, longitude) VALUES (?, ?, ?, ?)");
        $defaultAlat = 'OUT-001';
        $defaultNama = 'Lokasi Utama';
        $defaultLat = -1.20249;
        $defaultLng = 116.88708;
        mysqli_stmt_bind_param($stmt, "ssdd", $defaultAlat, $defaultNama, $defaultLat, $defaultLng);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // Pastikan kolom nama_lokasi ada jika tabel sudah terlanjur dibuat sebelumnya
        $checkNamaLokasiCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_alat LIKE 'nama_lokasi'");
        if (!$checkNamaLokasiCol || mysqli_num_rows($checkNamaLokasiCol) == 0) {
            mysqli_query($conn, "ALTER TABLE lokasi_alat ADD COLUMN nama_lokasi VARCHAR(100) DEFAULT NULL AFTER id_alat");
        }
    }

    // 4. Cek dan hapus kolom device di batas_sensor jika ada
    $checkDevice = mysqli_query($conn, "SHOW COLUMNS FROM batas_sensor LIKE 'device'");
    if ($checkDevice && mysqli_num_rows($checkDevice) > 0) {
        mysqli_query($conn, "ALTER TABLE batas_sensor DROP COLUMN device");
    }

} catch (Throwable $e) {
    error_log("Database initialization error (outdoor): " . $e->getMessage());
}

// ========== FUNGSI MEMBACA LOKASI DARI TABEL DB lokasi_alat ==========
function getLocations($conn) {
    $locations = [];
    $query = "SELECT * FROM lokasi_alat ORDER BY id ASC";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $locations[] = [
                'id' => (int)$row['id'],
                'id_alat' => $row['id_alat'],
                'nama_lokasi' => $row['nama_lokasi'] ?? '',
                'latitude' => (float)$row['latitude'],
                'longitude' => (float)$row['longitude'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    return $locations;
}

// ========== FUNGSI TAMBAH LOKASI BARU ==========
function addLocation($conn, $id_alat, $nama_lokasi, $latitude, $longitude) {
    $stmt = mysqli_prepare($conn, "INSERT INTO lokasi_alat (id_alat, nama_lokasi, latitude, longitude) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssdd", $id_alat, $nama_lokasi, $latitude, $longitude);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

// ========== FUNGSI HAPUS LOKASI ==========
function deleteLocationById($conn, $id) {
    $stmt = mysqli_prepare($conn, "DELETE FROM lokasi_alat WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

// ========== FUNGSI USER ==========
function getUsers($conn)
{
    $users = [];
    $query = mysqli_query($conn, "SELECT id, username, role, updated_at as last_update FROM pengguna ORDER BY id DESC");
    while ($row = mysqli_fetch_assoc($query)) {
        $users[] = $row;
    }
    return $users;
}

function countActiveAdmins($conn)
{
    $query = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna WHERE role = 'admin'");
    $row = mysqli_fetch_assoc($query);
    return $row['total'];
}

// ========== FUNGSI UNTUK BATAS SENSOR ==========
function getSensorAlarmData($conn)
{
    $sensors = [];
    $sql = "SELECT * FROM batas_sensor ORDER BY id ASC";
    $query = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $sensors[] = $row;
    }
    return $sensors;
}

function updateSensorAlarm($conn, $id, $nilai_alarm, $batas_min, $batas_max)
{
    $stmt = mysqli_prepare($conn, "UPDATE batas_sensor SET nilai_alarm = ?, batas_min = ?, batas_max = ?, last_update = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "dddi", $nilai_alarm, $batas_min, $batas_max, $id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
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
        $stmt_cek = mysqli_prepare($conn, "SELECT id FROM batas_sensor WHERE nama_sensor = ?");
        mysqli_stmt_bind_param($stmt_cek, "s", $nama_sensor);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $error_message = "Sensor '$nama_sensor' sudah terdaftar!";
        } else {
            $stmt_ins = mysqli_prepare($conn, "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ins, "sdsdds", $nama_sensor, $nilai_alarm, $satuan, $batas_min, $batas_max, $deskripsi);
            if (mysqli_stmt_execute($stmt_ins)) {
                $success_message = "Sensor '$nama_sensor' berhasil ditambahkan!";
            } else {
                $error_message = "Gagal menambahkan sensor!";
            }
            mysqli_stmt_close($stmt_ins);
        }
        mysqli_stmt_close($stmt_cek);
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
            $stmt_cek = mysqli_prepare($conn, "SELECT * FROM batas_sensor WHERE id = ?");
            mysqli_stmt_bind_param($stmt_cek, "i", $sensor_id);
            mysqli_stmt_execute($stmt_cek);
            $result = mysqli_stmt_get_result($stmt_cek);
            $sensor = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_cek);

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

    // ========== CRUD LOKASI DENGAN DATABASE ==========
    
    // TAMBAH LOKASI
    if (isset($_POST['add_location'])) {
        $id_alat = trim($_POST['id_alat']);
        $nama_lokasi = trim($_POST['nama_lokasi'] ?? '');
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        
        if (!empty($id_alat) && $latitude != 0 && $longitude != 0) {
            if (addLocation($conn, $id_alat, $nama_lokasi, $latitude, $longitude)) {
                $success_message = "Lokasi baru berhasil ditambahkan!";
            } else {
                $error_message = "Gagal menambahkan lokasi!";
            }
        } else {
            $error_message = "ID Alat, Latitude, dan Longitude harus diisi!";
        }
    }

    // EDIT LOKASI
    if (isset($_POST['edit_location'])) {
        $location_id = intval($_POST['location_id']);
        $id_alat = trim($_POST['edit_id_alat']);
        $nama_lokasi = trim($_POST['edit_nama_lokasi'] ?? '');
        $latitude = floatval($_POST['edit_latitude']);
        $longitude = floatval($_POST['edit_longitude']);
        
        if (!empty($id_alat) && $latitude != 0 && $longitude != 0) {
            $stmt = mysqli_prepare($conn, "UPDATE lokasi_alat SET id_alat = ?, nama_lokasi = ?, latitude = ?, longitude = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssddi", $id_alat, $nama_lokasi, $latitude, $longitude, $location_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Lokasi berhasil diperbarui!";
            } else {
                $error_message = "Gagal memperbarui lokasi!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "ID Alat, Latitude, dan Longitude harus diisi!";
        }
    }

    // HAPUS LOKASI
    if (isset($_POST['delete_location'])) {
        $location_id = intval($_POST['location_id']);
        
        $check = mysqli_query($conn, "SELECT id FROM lokasi_alat WHERE id = $location_id");
        if (mysqli_num_rows($check) > 0) {
            if (deleteLocationById($conn, $location_id)) {
                $success_message = "Lokasi berhasil dihapus!";
            } else {
                $error_message = "Gagal menghapus lokasi!";
            }
        } else {
            $error_message = "Lokasi tidak ditemukan!";
        }
    }

    // ========== MANAJEMEN USER DENGAN PREPARED STATEMENT ==========
    
    // TAMBAH USER
    if (isset($_POST['add_user'])) {
        $new_username = trim($_POST['new_username']);
        $new_password = trim($_POST['new_password']);
        $new_role = $_POST['new_role'] ?? 'user';
        
        if (!empty($new_username) && !empty($new_password)) {
            $stmt_cek = mysqli_prepare($conn, "SELECT id FROM pengguna WHERE username = ?");
            mysqli_stmt_bind_param($stmt_cek, "s", $new_username);
            mysqli_stmt_execute($stmt_cek);
            mysqli_stmt_store_result($stmt_cek);
            
            if (mysqli_stmt_num_rows($stmt_cek) > 0) {
                $error_message = "Username sudah terdaftar!";
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_ins = mysqli_prepare($conn, "INSERT INTO pengguna (username, password, role, status, created_at) VALUES (?, ?, ?, 'approved', NOW())");
                mysqli_stmt_bind_param($stmt_ins, "sss", $new_username, $password_hash, $new_role);
                if (mysqli_stmt_execute($stmt_ins)) {
                    $success_message = "Akun user berhasil ditambahkan!";
                } else {
                    $error_message = "Gagal menambahkan akun: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_ins);
            }
            mysqli_stmt_close($stmt_cek);
        } else {
            $error_message = "Username dan password harus diisi!";
        }
    }

    // EDIT USER
    if (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $edit_username = trim($_POST['edit_username']);
        $edit_role = $_POST['edit_role'];
        $edit_password = trim($_POST['edit_password']);
        
        if (!empty($edit_username)) {
            if (!empty($edit_password)) {
                $password_hash = password_hash($edit_password, PASSWORD_DEFAULT);
                $stmt_upd = mysqli_prepare($conn, "UPDATE pengguna SET username = ?, password = ?, role = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt_upd, "sssi", $edit_username, $password_hash, $edit_role, $user_id);
            } else {
                $stmt_upd = mysqli_prepare($conn, "UPDATE pengguna SET username = ?, role = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt_upd, "ssi", $edit_username, $edit_role, $user_id);
            }
            
            if (mysqli_stmt_execute($stmt_upd)) {
                $success_message = "Akun user berhasil diperbarui!";
            } else {
                $error_message = "Gagal memperbarui akun: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_upd);
        } else {
            $error_message = "Username harus diisi!";
        }
    }

    // HAPUS USER
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        $stmt_cek = mysqli_prepare($conn, "SELECT username FROM pengguna WHERE id = ?");
        mysqli_stmt_bind_param($stmt_cek, "i", $user_id);
        mysqli_stmt_execute($stmt_cek);
        $result = mysqli_stmt_get_result($stmt_cek);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_cek);
        
        if ($user_data && $user_data['username'] == 'admin') {
            $error_message = "Tidak dapat menghapus akun admin utama!";
        } else {
            $stmt_del = mysqli_prepare($conn, "DELETE FROM pengguna WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del, "i", $user_id);
            if (mysqli_stmt_execute($stmt_del)) {
                $success_message = "Akun user berhasil dihapus!";
            } else {
                $error_message = "Gagal menghapus akun: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_del);
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
    <style>
        /* ========== STYLE ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            background-image: url('https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, rgba(30, 60, 114, 0.9), rgba(42, 82, 152, 0.9));
            padding: 20px 15px;
            position: sticky;
            top: 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            z-index: 1;
        }

        .sidebar h3 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        }

        .menu-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 10px 0;
            padding: 12px 15px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            width: 100%;
            font-size: 14px;
        }

        .menu-btn i {
            width: 24px;
            font-size: 18px;
        }

        .menu-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }

        .menu-btn.active {
            background: linear-gradient(135deg, #00b4db, #0083b0);
        }

        .logout {
            margin-top: 40px;
            background: rgba(220, 53, 69, 0.8);
        }

        .logout:hover {
            background: #dc3545;
        }

        .main {
            flex: 1;
            padding: 20px 30px;
            overflow-y: auto;
            height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header h2 {
            color: #1e3c72;
            font-size: 24px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 8px 20px;
            border-radius: 50px;
            color: white;
            font-weight: bold;
        }

        .btn-home-header {
            background: rgba(34, 6, 244, 0.2);
            color: #1e3c72;
            border: none;
            padding: 8px 18px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-home-header:hover {
            background: rgba(255, 255, 255, 0.45);
            transform: translateY(-2px);
        }

        .tab-menu {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(224, 224, 224, 0.5);
            padding-bottom: 0;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 30px;
            background: transparent;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            color: #ddd;
            transition: all 0.3s;
            position: relative;
        }

        .tab-btn:hover {
            color: white;
        }

        .tab-btn.active {
            color: #00b4db;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(135deg, #00b4db, #0083b0);
            border-radius: 3px;
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            color: #1e3c72;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3 i {
            color: #00b4db;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead th {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }

        table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.7);
        }

        table tbody tr:hover td {
            background: rgba(255, 255, 255, 0.9);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .role-admin {
            background: #667eea;
            color: white;
        }

        .role-user {
            background: #28a745;
            color: white;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #1e3c72;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            background: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00b4db, #0083b0);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 180, 219, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #333;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(255, 193, 7, 0.4);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 25px;
            width: 90%;
            max-width: 450px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h4 {
            color: #1e3c72;
        }

        .modal-close {
            cursor: pointer;
            font-size: 24px;
            color: #999;
        }

        .warning-text {
            background: #fef3c7;
            color: #d97706;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .welcome-banner h3 {
            margin: 0;
            border: none;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-box {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-radius: 20px;
            padding: 40px 35px 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-icon {
            font-size: 48px;
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .modal-box h2 {
            color: #1e3c72;
            font-size: 22px;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-modal {
            padding: 12px 35px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-cancel {
            background: #e9ecef;
            color: #495057;
        }

        .btn-cancel:hover {
            background: #dee2e6;
            transform: translateY(-2px);
        }

        .btn-logout-confirm {
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white;
        }

        .btn-logout-confirm:hover {
            background: linear-gradient(135deg, #c82333, #a71d2a);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(220, 53, 69, 0.4);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }

            .sidebar h3 {
                font-size: 12px;
            }

            .menu-btn span {
                display: none;
            }

            .main {
                padding: 15px;
            }

            .tab-btn {
                padding: 10px 20px;
                font-size: 14px;
            }

            .header-right {
                flex-direction: column;
                gap: 8px;
            }
            
            .modal-box {
                padding: 30px 20px;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
            
            .btn-modal {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h3><i class="fas fa-cog"></i> FireNetWork</h3>
        <a href="dashboard_admin.php" class="menu-btn"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="chart.php" class="menu-btn"><i class="fas fa-chart-line"></i><span>CHART</span></a>
        <a href="tabel.php" class="menu-btn"><i class="fas fa-table"></i><span>TABEL</span></a>
        <a href="setting.php" class="menu-btn active"><i class="fas fa-cog"></i><span>SETTING</span></a>
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
                <a href="#" class="btn-home-header" onclick="openHomeModal(); return false;"><i class="fas fa-home"></i> HOME</a>
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
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <i class="fas fa-<?= getSensorIconPHP($sensor['nama_sensor']) ?>" style="color: <?= in_array($sensor['nama_sensor'], ['ASAP', 'CO']) ? '#dc3545' : '#00b4db' ?>;"></i>
                                            <strong><?= htmlspecialchars($sensor['nama_sensor']) ?></strong>
                                            <br><small style="color:#666;"><?= htmlspecialchars($sensor['deskripsi']) ?></small>
                                        </td>
                                        <td>
                                            <strong style="color: <?= in_array($sensor['nama_sensor'], ['ASAP', 'CO']) ? '#dc3545' : '#1e3c72' ?>;">
                                                <?= number_format($sensor['nilai_alarm'], 2) ?> <?= htmlspecialchars($sensor['satuan']) ?>
                                            </strong>
                                        </td>
                                        <td><?= htmlspecialchars($sensor['satuan']) ?></td>
                                        <td><?= number_format($sensor['batas_min'], 2) ?> <?= htmlspecialchars($sensor['satuan']) ?></td>
                                        <td><?= number_format($sensor['batas_max'], 2) ?> <?= htmlspecialchars($sensor['satuan']) ?></td>
                                        <td><?= $sensor['last_update'] ?></td>
                                        <td>
                                            <button type="button" class="btn-warning btn-edit-alarm" 
                                                data-id="<?= $sensor['id'] ?>"
                                                data-nama="<?= htmlspecialchars($sensor['nama_sensor']) ?>"
                                                data-nilai="<?= $sensor['nilai_alarm'] ?>"
                                                data-satuan="<?= htmlspecialchars($sensor['satuan']) ?>"
                                                data-min="<?= $sensor['batas_min'] ?>"
                                                data-max="<?= $sensor['batas_max'] ?>">
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

        <!-- TAB 2: Setting Lokasi Alat - MENGGUNAKAN DATABASE -->
        <div id="tab2" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-map-marker-alt"></i> Setting Lokasi Alat</h3>
                <p style="margin-bottom:15px; color:#666; font-size:14px;">Atur nama, ID, dan koordinat lokasi monitoring alat.</p>

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
                                <th>LATITUDE</th>
                                <th>LONGITUDE</th>
                                <th>WAKTU UPDATE</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($locations) > 0): ?>
                                <?php foreach ($locations as $index => $loc): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($loc['id_alat'] ?? '-') ?></strong></td>
                                        <td><?= htmlspecialchars($loc['nama_lokasi'] ?? '-') ?></td>
                                        <td><?= isset($loc['latitude']) ? number_format($loc['latitude'], 6) : '-' ?></td>
                                        <td><?= isset($loc['longitude']) ? number_format($loc['longitude'], 6) : '-' ?></td>
                                        <td><?= isset($loc['updated_at']) ? $loc['updated_at'] : date('Y-m-d H:i:s') ?></td>
                                        <td class="action-buttons">
                                            <?php 
                                            $id = isset($loc['id']) ? (int)$loc['id'] : 0;
                                            $id_alat = isset($loc['id_alat']) ? $loc['id_alat'] : '';
                                            $nama_lokasi = isset($loc['nama_lokasi']) ? $loc['nama_lokasi'] : '';
                                            $lat = isset($loc['latitude']) ? (float)$loc['latitude'] : 0;
                                            $lng = isset($loc['longitude']) ? (float)$loc['longitude'] : 0;
                                            ?>
                                            <button class="btn-warning" onclick="openEditLocationModal(<?= $id ?>, '<?= htmlspecialchars($id_alat) ?>', '<?= htmlspecialchars($nama_lokasi) ?>', <?= $lat ?>, <?= $lng ?>)">
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
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
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

    <!-- MODAL LOGOUT -->
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
                <a href="logout.php" class="btn-modal btn-logout-confirm">
                    <i class="fas fa-sign-out-alt"></i> LOGOUT
                </a>
            </div>
        </div>
    </div>

    <!-- MODAL HOME -->
    <div class="modal-overlay" id="homeModal">
        <div class="modal-box">
            <div class="modal-icon" style="background: rgba(0, 180, 219, 0.1); color: #00b4db;">
                <i class="fas fa-home"></i>
            </div>
            <h2>Kembali ke Halaman Utama?</h2>
            <div class="modal-buttons">
                <button class="btn-modal btn-cancel" onclick="closeHomeModal()">
                    <i class="fas fa-times"></i> CANCEL
                </button>
                <a href="home.php" class="btn-modal" style="background: linear-gradient(135deg, #00b4db, #0083b0); color: white;">
                    <i class="fas fa-check"></i> YA, KEMBALI
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
                </div>
                <div class="form-group">
                    <label>Batas Maksimum</label>
                    <input type="number" name="batas_max" id="edit_batas_max" step="any" required>
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
                    <input type="text" name="id_alat" placeholder="Contoh: 001 atau OUT-001" required>
                </div>
                <div class="form-group">
                    <label>Nama Lokasi</label>
                    <input type="text" name="nama_lokasi" placeholder="Contoh: Gerbang Utama / Gedung A">
                </div>
                <div class="form-group">
                    <label>Latitude <span style="color:red;">*</span></label>
                    <input type="number" name="latitude" step="any" required placeholder="Contoh: -0.966113">
                </div>
                <div class="form-group">
                    <label>Longitude <span style="color:red;">*</span></label>
                    <input type="number" name="longitude" step="any" required placeholder="Contoh: 116.702781">
                </div>
                <button type="submit" name="add_location" class="btn-primary" style="width:100%">Simpan Lokasi</button>
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
                    <input type="text" name="edit_nama_lokasi" id="edit_nama_lokasi">
                </div>
                <div class="form-group">
                    <label>Latitude <span style="color:red;">*</span></label>
                    <input type="number" name="edit_latitude" id="edit_latitude" step="any" required>
                </div>
                <div class="form-group">
                    <label>Longitude <span style="color:red;">*</span></label>
                    <input type="number" name="edit_longitude" id="edit_longitude" step="any" required>
                </div>
                <button type="submit" name="edit_location" class="btn-primary" style="width:100%">Simpan Perubahan</button>
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
                <button type="submit" name="add_user" class="btn-primary" style="width:100%">Tambah Akun</button>
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

    <script>
        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });

        function openHomeModal() {
            document.getElementById('homeModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeHomeModal() {
            document.getElementById('homeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('homeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeHomeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('logoutModal').style.display === 'flex') {
                    closeLogoutModal();
                }
                if (document.getElementById('homeModal').style.display === 'flex') {
                    closeHomeModal();
                }
            }
        });

        function openEditAlarmModal(id, nama, nilai, satuan, min, max) {
            try {
                document.getElementById('edit_sensor_id').value = id;
                document.getElementById('edit_sensor_name').value = nama;
                document.getElementById('edit_batas_min').value = min;
                document.getElementById('edit_batas_max').value = max;
                document.getElementById('edit_alarm_value').value = nilai;
                document.getElementById('edit_satuan').value = satuan;

                var warning = document.getElementById('range_warning');
                if (warning) {
                    warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Nilai alarm harus antara ' + min + ' - ' + max + ' ' + satuan;
                    warning.style.color = '#e74c3c';
                }

                var modal = document.getElementById('editAlarmModal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.style.visibility = 'visible';
                    modal.style.opacity = '1';
                }
            } catch (e) {
                console.error('Error:', e);
            }
        }

        function openEditUserModal(id, username, role) {
            try {
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_role').value = role;
                
                var modal = document.getElementById('editUserModal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.style.visibility = 'visible';
                    modal.style.opacity = '1';
                }
            } catch (e) {
                console.error('Error in openEditUserModal:', e);
            }
        }

        function openEditLocationModal(id, id_alat, nama_lokasi, lat, lng) {
            try {
                document.getElementById('edit_location_id').value = id;
                document.getElementById('edit_id_alat').value = id_alat;
                document.getElementById('edit_nama_lokasi').value = nama_lokasi;
                document.getElementById('edit_latitude').value = lat;
                document.getElementById('edit_longitude').value = lng;
                
                var modal = document.getElementById('editLocationModal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.style.visibility = 'visible';
                    modal.style.opacity = '1';
                }
            } catch (e) {
                console.error('Error in openEditLocationModal:', e);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var editButtons = document.querySelectorAll('.btn-edit-user');
            editButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var id = this.getAttribute('data-id');
                    var username = this.getAttribute('data-username');
                    var role = this.getAttribute('data-role');
                    openEditUserModal(id, username, role);
                });
            });
            
            document.querySelectorAll('.btn-delete-user').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var userId = this.getAttribute('data-id');
                    var username = this.getAttribute('data-username');
                    deleteUser(userId, username);
                });
            });

            document.querySelectorAll('.btn-delete-location').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var locId = this.getAttribute('data-id');
                    deleteLocation(locId);
                });
            });
            
            if (typeof $ !== 'undefined') {
                $('.btn-edit-alarm').on('click', function() {
                    var id = $(this).data('id');
                    var nama = $(this).data('nama');
                    var nilai = $(this).data('nilai');
                    var satuan = $(this).data('satuan');
                    var min = $(this).data('min');
                    var max = $(this).data('max');
                    openEditAlarmModal(id, nama, nilai, satuan, min, max);
                });
            }

            document.getElementById('tab1').style.display = 'block';
            document.getElementById('tab2').style.display = 'none';
            document.getElementById('tab3').style.display = 'none';
        });

        function openAddSensorModal() {
            document.getElementById('addSensorModal').style.display = 'flex';
        }

        function openAddLocationModal() {
            var modal = document.getElementById('addLocationModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.visibility = 'visible';
                modal.style.opacity = '1';
            }
        }

        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }

        function openTab(tabName, element) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
                tab.style.display = 'none';
            });
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabName).style.display = 'block';
            document.getElementById(tabName).classList.add('active');
            element.classList.add('active');
        }

        function closeModal(modalId) {
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                modal.style.visibility = 'hidden';
                modal.style.opacity = '0';
            }
        }

        function deleteUser(userId, username) {
            Swal.fire({
                title: 'Hapus Akun?',
                text: 'Apakah Anda yakin ingin menghapus akun "' + username + '"?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_user';
                    input.value = '1';
                    let inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'user_id';
                    inputId.value = userId;
                    form.appendChild(input);
                    form.appendChild(inputId);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function deleteLocation(locationId) {
            Swal.fire({
                title: 'Hapus Lokasi?',
                text: 'Apakah Anda yakin ingin menghapus lokasi ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_location';
                    input.value = '1';
                    let inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'location_id';
                    inputId.value = locationId;
                    form.appendChild(input);
                    form.appendChild(inputId);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                event.target.style.visibility = 'hidden';
                event.target.style.opacity = '0';
            }
        }

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