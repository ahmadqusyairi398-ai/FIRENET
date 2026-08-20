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

        // Hapus sensor ARAH ANGIN dari batas_sensor outdoor karena arah angin tidak memiliki set point / nilai alarm
        @mysqli_query($conn, "DELETE FROM batas_sensor WHERE nama_sensor IN ('ARAH ANGIN', 'ARAH_ANGIN')");
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
        // Pastikan kolom nama_lokasi & interval_detik ada jika tabel sudah terlanjur dibuat
        $checkNamaLokasiCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_alat LIKE 'nama_lokasi'");
        if (!$checkNamaLokasiCol || mysqli_num_rows($checkNamaLokasiCol) == 0) {
            mysqli_query($conn, "ALTER TABLE lokasi_alat ADD COLUMN nama_lokasi VARCHAR(100) DEFAULT NULL AFTER id_alat");
        }
        $checkIntervalCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_alat LIKE 'interval_detik'");
        if (!$checkIntervalCol || mysqli_num_rows($checkIntervalCol) == 0) {
            mysqli_query($conn, "ALTER TABLE lokasi_alat ADD COLUMN interval_detik INT DEFAULT 30");
        }
    }

    // 4. Cek dan hapus kolom device di batas_sensor jika ada
    $checkDevice = mysqli_query($conn, "SHOW COLUMNS FROM batas_sensor LIKE 'device'");
    if ($checkDevice && mysqli_num_rows($checkDevice) > 0) {
        mysqli_query($conn, "ALTER TABLE batas_sensor DROP COLUMN device");
    }

    // 5. Cek dan tambah kolom last_update di batas_sensor jika belum ada
    $checkLastUpdate = mysqli_query($conn, "SHOW COLUMNS FROM batas_sensor LIKE 'last_update'");
    if (!$checkLastUpdate || mysqli_num_rows($checkLastUpdate) == 0) {
        mysqli_query($conn, "ALTER TABLE batas_sensor ADD COLUMN last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
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
                'interval_detik' => (int)($row['interval_detik'] ?? 30),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    return $locations;
}

// ========== FUNGSI TAMBAH LOKASI BARU ==========
function addLocation($conn, $id_alat, $nama_lokasi, $latitude, $longitude) {
    $id_alat = mysqli_real_escape_string($conn, $id_alat);
    $nama_lokasi = mysqli_real_escape_string($conn, $nama_lokasi);
    $latitude = floatval($latitude);
    $longitude = floatval($longitude);
    return mysqli_query($conn, "INSERT INTO lokasi_alat (id_alat, nama_lokasi, latitude, longitude) VALUES ('$id_alat', '$nama_lokasi', $latitude, $longitude)");
}

// ========== FUNGSI HAPUS LOKASI ==========
function deleteLocationById($conn, $id) {
    $id = intval($id);
    return mysqli_query($conn, "DELETE FROM lokasi_alat WHERE id = $id");
}

// ========== FUNGSI USER ==========
function getUsers($conn)
{
    $users = [];
    $query = mysqli_query($conn, "SELECT id, username, role, updated_at as last_update FROM pengguna ORDER BY id DESC");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $users[] = $row;
        }
    }
    return $users;
}

function countActiveAdmins($conn)
{
    $query = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna WHERE role = 'admin'");
    if ($query) {
        $row = mysqli_fetch_assoc($query);
        return intval($row['total'] ?? 0);
    }
    return 0;
}

// ========== FUNGSI UNTUK BATAS SENSOR ==========
function getSensorAlarmData($conn)
{
    $sensors = [];
    $sql = "SELECT * FROM batas_sensor ORDER BY id ASC";
    $query = mysqli_query($conn, $sql);
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $sensors[] = $row;
        }
    }
    return $sensors;
}

function updateSensorAlarm($conn, $id, $nilai_alarm, $batas_min, $batas_max)
{
    $id = intval($id);
    $nilai_alarm = floatval($nilai_alarm);
    $batas_min = floatval($batas_min);
    $batas_max = floatval($batas_max);

    // Cek ketersediaan kolom last_update
    $checkLastUpdate = mysqli_query($conn, "SHOW COLUMNS FROM batas_sensor LIKE 'last_update'");
    if ($checkLastUpdate && mysqli_num_rows($checkLastUpdate) > 0) {
        $sql = "UPDATE batas_sensor SET nilai_alarm = $nilai_alarm, batas_min = $batas_min, batas_max = $batas_max, last_update = NOW() WHERE id = $id";
    } else {
        @mysqli_query($conn, "ALTER TABLE batas_sensor ADD COLUMN last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $sql = "UPDATE batas_sensor SET nilai_alarm = $nilai_alarm, batas_min = $batas_min, batas_max = $batas_max WHERE id = $id";
    }
    return mysqli_query($conn, $sql);
}

$success_message = $error_message = '';

// ========== TAMBAH SENSOR BARU ==========
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_sensor'])) {
    $nama_sensor = mysqli_real_escape_string($conn, trim($_POST['sensor_name']));
    $nilai_alarm = floatval($_POST['alarm_value']);
    $satuan = mysqli_real_escape_string($conn, trim($_POST['satuan']));
    $batas_min = floatval($_POST['batas_min']);
    $batas_max = floatval($_POST['batas_max']);
    $deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
    
    if (!empty($nama_sensor) && !empty($satuan)) {
        $stmt_cek = mysqli_query($conn, "SELECT id FROM batas_sensor WHERE nama_sensor = '$nama_sensor'");
        if ($stmt_cek && mysqli_num_rows($stmt_cek) > 0) {
            $error_message = "Sensor '$nama_sensor' sudah terdaftar!";
        } else {
            $sql = "INSERT INTO batas_sensor (nama_sensor, nilai_alarm, satuan, batas_min, batas_max, deskripsi) VALUES ('$nama_sensor', $nilai_alarm, '$satuan', $batas_min, $batas_max, '$deskripsi')";
            if (mysqli_query($conn, $sql)) {
                $success_message = "Sensor '$nama_sensor' berhasil ditambahkan!";
            } else {
                $error_message = "Gagal menambahkan sensor: " . mysqli_error($conn);
            }
        }
    } else {
        $error_message = "Nama sensor dan satuan harus diisi!";
    }
}

$maxAdmin = 2;
$adminCount = countActiveAdmins($conn);
$canAddAdmin = $adminCount < $maxAdmin;

// ========== PROSES POST LAINNYA ==========
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // UPDATE NILAI ALARM SENSOR
        if (isset($_POST['update_alarm_value'])) {
            $sensor_id = intval($_POST['sensor_id']);
            $new_value = floatval($_POST['alarm_value'] ?? 0);
            $batas_min = floatval($_POST['batas_min'] ?? 0);
            $batas_max = floatval($_POST['batas_max'] ?? 0);

            $check_query = mysqli_query($conn, "SELECT * FROM batas_sensor WHERE id = $sensor_id");
            $sensor = $check_query ? mysqli_fetch_assoc($check_query) : null;

            if ($sensor) {
                if ($batas_min >= $batas_max) {
                    $error_message = "Batas minimum harus lebih kecil dari batas maksimum!";
                } else {
                    if ($new_value >= $batas_min && $new_value <= $batas_max) {
                        if (updateSensorAlarm($conn, $sensor_id, $new_value, $batas_min, $batas_max)) {
                            $success_message = "Nilai alarm dan batas range {$sensor['nama_sensor']} berhasil diupdate!";
                        } else {
                            $error_message = "Gagal mengupdate nilai alarm: " . mysqli_error($conn);
                        }
                    } else {
                        $error_message = "Nilai alarm harus antara {$batas_min} - {$batas_max} {$sensor['satuan']}!";
                    }
                }
            } else {
                $error_message = "Sensor tidak ditemukan!";
            }
        }

        // UPDATE INTERVAL PENGIRIMAN DATA ALAT (KONSEP B: Real-Time HTTP cURL ke Node-RED)
        if (isset($_POST['update_device_interval'])) {
            $loc_id = intval($_POST['interval_location_id']);
            $new_interval = intval($_POST['interval_detik']);
            if ($new_interval < 3) $new_interval = 3;

            $checkIntervalCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_alat LIKE 'interval_detik'");
            if (!$checkIntervalCol || mysqli_num_rows($checkIntervalCol) == 0) {
                @mysqli_query($conn, "ALTER TABLE lokasi_alat ADD COLUMN interval_detik INT DEFAULT 30");
            }

            $sql = "UPDATE lokasi_alat SET interval_detik = $new_interval WHERE id = $loc_id";
            if (mysqli_query($conn, $sql)) {
                $success_message = "Interval pengiriman data alat berhasil diubah menjadi $new_interval detik!";

                // Ambil id_alat dari database untuk payload
                $qTool = mysqli_query($conn, "SELECT id_alat FROM lokasi_alat WHERE id = $loc_id LIMIT 1");
                $id_alat_val = "OUT-001";
                if ($qTool && $rTool = mysqli_fetch_assoc($qTool)) {
                    $id_alat_val = !empty($rTool['id_alat']) ? $rTool['id_alat'] : 'OUT-001';
                }

                // Kirim perintah real-time (HTTP cURL) ke Node-RED (Port 1881) -> Konsep B
                $url_nodered = "http://localhost:1881/set_interval_outdoor";
                $payload_nodered = json_encode(array(
                    "id_alat"  => $id_alat_val,
                    "interval" => $new_interval,
                    "perintah" => "ubah_interval",
                    "nilai"    => $new_interval
                ));
                $ch = curl_init($url_nodered);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_nodered);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                @curl_exec($ch);
                @curl_close($ch);
            } else {
                $error_message = "Gagal mengubah interval: " . mysqli_error($conn);
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
                    $error_message = "Gagal menambahkan lokasi: " . mysqli_error($conn);
                }
            } else {
                $error_message = "ID Alat, Latitude, dan Longitude harus diisi!";
            }
        }

        // EDIT LOKASI
        if (isset($_POST['edit_location'])) {
            $location_id = intval($_POST['location_id']);
            $id_alat = mysqli_real_escape_string($conn, trim($_POST['edit_id_alat']));
            $nama_lokasi = mysqli_real_escape_string($conn, trim($_POST['edit_nama_lokasi'] ?? ''));
            $latitude = floatval($_POST['edit_latitude']);
            $longitude = floatval($_POST['edit_longitude']);
            
            if (!empty($id_alat) && $latitude != 0 && $longitude != 0) {
                $sql = "UPDATE lokasi_alat SET id_alat = '$id_alat', nama_lokasi = '$nama_lokasi', latitude = $latitude, longitude = $longitude WHERE id = $location_id";
                if (mysqli_query($conn, $sql)) {
                    $success_message = "Lokasi berhasil diperbarui!";
                } else {
                    $error_message = "Gagal memperbarui lokasi: " . mysqli_error($conn);
                }
            } else {
                $error_message = "ID Alat, Latitude, dan Longitude harus diisi!";
            }
        }

        // HAPUS LOKASI
        if (isset($_POST['delete_location'])) {
            $location_id = intval($_POST['location_id']);
            
            $check = mysqli_query($conn, "SELECT id FROM lokasi_alat WHERE id = $location_id");
            if ($check && mysqli_num_rows($check) > 0) {
                if (deleteLocationById($conn, $location_id)) {
                    $success_message = "Lokasi berhasil dihapus!";
                } else {
                    $error_message = "Gagal menghapus lokasi: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Lokasi tidak ditemukan!";
            }
        }

        // ========== MANAJEMEN USER ==========
        
        // TAMBAH USER
        if (isset($_POST['add_user'])) {
            $new_username = mysqli_real_escape_string($conn, trim($_POST['new_username']));
            $new_password = trim($_POST['new_password']);
            $new_role = mysqli_real_escape_string($conn, $_POST['new_role'] ?? 'user');
            
            if (!empty($new_username) && !empty($new_password)) {
                $stmt_cek = mysqli_query($conn, "SELECT id FROM pengguna WHERE username = '$new_username'");
                if ($stmt_cek && mysqli_num_rows($stmt_cek) > 0) {
                    $error_message = "Username sudah terdaftar!";
                } else {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO pengguna (username, password, role, status, created_at) VALUES ('$new_username', '$password_hash', '$new_role', 'approved', NOW())";
                    if (mysqli_query($conn, $sql)) {
                        $success_message = "Akun user berhasil ditambahkan!";
                    } else {
                        $error_message = "Gagal menambahkan akun: " . mysqli_error($conn);
                    }
                }
            } else {
                $error_message = "Username dan password harus diisi!";
            }
        }

        // EDIT USER
        if (isset($_POST['edit_user'])) {
            $user_id = intval($_POST['user_id']);
            $edit_username = mysqli_real_escape_string($conn, trim($_POST['edit_username']));
            $edit_role = mysqli_real_escape_string($conn, $_POST['edit_role']);
            $edit_password = trim($_POST['edit_password']);
            
            if (!empty($edit_username)) {
                if (!empty($edit_password)) {
                    $password_hash = password_hash($edit_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE pengguna SET username = '$edit_username', password = '$password_hash', role = '$edit_role', updated_at = NOW() WHERE id = $user_id";
                } else {
                    $sql = "UPDATE pengguna SET username = '$edit_username', role = '$edit_role', updated_at = NOW() WHERE id = $user_id";
                }
                
                if (mysqli_query($conn, $sql)) {
                    $success_message = "Akun user berhasil diperbarui!";
                } else {
                    $error_message = "Gagal memperbarui akun: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Username harus diisi!";
            }
        }

        // HAPUS USER
        if (isset($_POST['delete_user'])) {
            $user_id = intval($_POST['user_id']);
            
            $check_query = mysqli_query($conn, "SELECT username FROM pengguna WHERE id = $user_id");
            $user_data = $check_query ? mysqli_fetch_assoc($check_query) : null;
            
            if ($user_data && $user_data['username'] == 'admin') {
                $error_message = "Tidak dapat menghapus akun admin utama!";
            } else {
                if (mysqli_query($conn, "DELETE FROM pengguna WHERE id = $user_id")) {
                    $success_message = "Akun user berhasil dihapus!";
                } else {
                    $error_message = "Gagal menghapus akun: " . mysqli_error($conn);
                }
            }
        }
    } catch (Throwable $e) {
        $error_message = "Terjadi kesalahan sistem: " . $e->getMessage();
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
    <!-- Setting Outdoor Custom CSS -->
    <link rel="stylesheet" href="css/setting_outdoor.css">
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
                                                <?= (float)$sensor['nilai_alarm'] ?> <?= htmlspecialchars($sensor['satuan']) ?>
                                            </strong>
                                        </td>
                                        <td><?= htmlspecialchars($sensor['satuan']) ?></td>
                                        <td><?= (float)$sensor['batas_min'] ?> <?= htmlspecialchars($sensor['satuan']) ?></td>
                                        <td><?= (float)$sensor['batas_max'] ?> <?= htmlspecialchars($sensor['satuan']) ?></td>
                                        <td><?= $sensor['last_update'] ?></td>
                                        <td>
                                            <button type="button" class="btn-warning btn-edit-alarm" 
                                                data-id="<?= $sensor['id'] ?>"
                                                data-nama="<?= htmlspecialchars($sensor['nama_sensor']) ?>"
                                                data-nilai="<?= (float)$sensor['nilai_alarm'] ?>"
                                                data-satuan="<?= htmlspecialchars($sensor['satuan']) ?>"
                                                data-min="<?= (float)$sensor['batas_min'] ?>"
                                                data-max="<?= (float)$sensor['batas_max'] ?>">
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
            <!-- Form Pengaturan Kecepatan / Interval Pengiriman Data Alat -->
            <div class="card" style="margin-bottom: 22px; border-left: 4px solid #00b4db;">
                <h3 style="color: #0083b0;"><i class="fas fa-stopwatch"></i> Kecepatan Pengiriman Data Alat Outdoor (Interval)</h3>
                <p style="margin-bottom:15px; color:#666; font-size:13.5px; line-height: 1.5;">
                    Atur interval pengiriman data dari alat IoT / LoRa ke database. Nilai ini akan otomatis dikirimkan ke mikrokontroler (ESP32/Arduino) setiap kali mengirim data.
                </p>
                <form method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 220px;">
                        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px; color: #333;">Pilih Alat / Lokasi</label>
                        <select name="interval_location_id" class="form-control" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid #ccc; font-weight: 600;">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['nama_lokasi']) ?> (<?= htmlspecialchars($loc['id_alat']) ?>) - Kecepatan: <?= (int)($loc['interval_detik'] ?? 30) ?> Detik</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px; color: #333;">Interval Pengiriman (Detik)</label>
                        <input type="number" name="interval_detik" value="<?= (int)($locations[0]['interval_detik'] ?? 30) ?>" min="3" max="3600" required class="form-control" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid #ccc; font-weight: 600;">
                    </div>
                    <button type="submit" name="update_device_interval" class="btn-primary" style="padding: 10px 22px; font-weight: 700; border-radius: 8px;">
                        <i class="fas fa-save"></i> Simpan Interval Alat
                    </button>
                </form>
            </div>

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
                                <th>INTERVAL</th>
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
                                        <td><span class="badge" style="background: rgba(0,180,219,0.15); color: #0083b0; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 11px;"><i class="fas fa-clock"></i> <?= (int)($loc['interval_detik'] ?? 30) ?>s</span></td>
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
    // Konfigurasi data dinamis dari PHP ke JavaScript
    window.FIRENET_CONFIG = {
        successMessage: <?= json_encode($success_message) ?>,
        errorMessage: <?= json_encode($error_message) ?>
    };
    </script>
    <!-- External JavaScript Logic -->
    <script src="js/setting_outdoor.js"></script>
</body>

</html>