<?php
// Tentukan header agar output dibaca sebagai JSON
header('Content-Type: application/json');

// 1. Load koneksi database dari koneksi.php
require_once 'koneksi.php';

$device_id = isset($_GET['device']) ? strtolower($_GET['device']) : 'indoor';

if ($device_id === 'indoor') {
    $conn = isset($conn_indoor) && $conn_indoor ? $conn_indoor : null;
    if (!$conn) {
        $conn = @mysqli_connect("localhost", "root", "", "indoor");
    }

    // Default set points (batas sensor)
    $limit_suhu = 45.0;
    $limit_kelembapan = 85.0;
    $limit_tegangan = 250.0;
    $limit_arus = 15.0;

    if ($conn) {
        $q_batas = @mysqli_query($conn, "SELECT nama_sensor, nilai_alarm, batas_max, batas_min FROM batas_sensor");
        if ($q_batas && mysqli_num_rows($q_batas) > 0) {
            while ($row = mysqli_fetch_assoc($q_batas)) {
                $nama = strtoupper(trim($row['nama_sensor']));
                $val = isset($row['nilai_alarm']) && $row['nilai_alarm'] !== null && $row['nilai_alarm'] != 0 ? (float)$row['nilai_alarm'] : (float)($row['batas_max'] ?? 0);
                if ($val > 0) {
                    if ($nama === 'SUHU') {
                        $limit_suhu = $val;
                    }
                    if ($nama === 'KELEMBAPAN') {
                        $limit_kelembapan = $val;
                    }
                    if ($nama === 'TEGANGAN' || $nama === 'TEGANGAN LISTRIK') {
                        $limit_tegangan = $val;
                    }
                    if ($nama === 'ARUS' || $nama === 'ARUS LISTRIK') {
                        $limit_arus = $val;
                    }
                }
            }
        }
    }

    if (!$conn || mysqli_connect_errno()) {
        echo json_encode([
            "error" => true,
            "message" => "Koneksi database indoor gagal: " . (mysqli_connect_error() ?: 'Unknown error'),
            "limit_suhu" => $limit_suhu,
            "limit_kelembapan" => $limit_kelembapan,
            "limit_tegangan" => $limit_tegangan,
            "limit_arus" => $limit_arus,
            "batas_suhu" => $limit_suhu,
            "batas_kelembapan" => $limit_kelembapan,
            "batas_tegangan" => $limit_tegangan,
            "batas_arus" => $limit_arus
        ]);
        exit();
    }

        // Tangkap request
        $type = isset($_GET['type']) ? strtolower($_GET['type']) : 'semua';
        $history = isset($_GET['history']) ? intval($_GET['history']) : 0;
        $is_dummy_filter = isset($_GET['is_dummy']) ? $_GET['is_dummy'] : null;

        // Filter SQL (utama = asli, dummy = simulasi)
        $filter_sql = "";
        if ($type === 'utama' || ($is_dummy_filter !== null && (int)$is_dummy_filter === 0)) {
            $filter_sql = " AND (is_dummy = 0 OR is_dummy IS NULL) ";
        } elseif ($type === 'dummy' || ($is_dummy_filter !== null && (int)$is_dummy_filter === 1)) {
            $filter_sql = " AND is_dummy = 1 ";
        }

        // ---- KODE KHUSUS UNTUK MEMUNCULKAN GRAFIK INSTAN ----
        if ($history == 1) {
            $sql_hist = "SELECT * FROM (SELECT * FROM data_sensor WHERE 1=1 $filter_sql ORDER BY id DESC LIMIT 15) sub ORDER BY id ASC";
            $res_hist = @mysqli_query($conn, $sql_hist);

            $history_data = [];
            if ($res_hist && mysqli_num_rows($res_hist) > 0) {
                while ($row = mysqli_fetch_assoc($res_hist)) {
                    $waktu_raw = $row['timestamp'] ?? ($row['tanggal_dan_waktu'] ?? ($row['created_at'] ?? null));
                    $apiVal = isset($row['api']) ? (float)$row['api'] : 0;
                    $strApi = isset($row['api']) ? trim(strtolower((string)$row['api'])) : '';
                    $apiValue = ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'sedang' || $strApi === 'tinggi' || $apiVal > 0.5) ? 1 : 0;

                    $history_data[] = [
                        'waktu'      => $waktu_raw ? date('H:i:s', strtotime($waktu_raw)) : date('H:i:s'),
                        'suhu'       => (float)($row['suhu'] ?? 0),
                        'kelembapan' => (float)($row['kelembapan'] ?? 0),
                        'tegangan'   => (float)($row['tegangan'] ?? 0),
                        'arus'       => (float)($row['arus'] ?? 0),
                        'apiValue'   => $apiValue,
                        'is_dummy'   => (isset($row['is_dummy']) && $row['is_dummy'] == 1) ? true : false
                    ];
                }
            }
            echo json_encode($history_data);
            exit();
        }

        // Query Ambil Data Sensor Terbaru yang BISA DIFILTER
        $sql = "SELECT * FROM data_sensor WHERE 1=1 $filter_sql ORDER BY id DESC LIMIT 1";

        $result = @mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);

            $waktu_raw = $row['timestamp'] ?? ($row['tanggal_dan_waktu'] ?? ($row['created_at'] ?? null));

            // PERBAIKAN: Ubah batas toleransi mati/offline menjadi 45 detik agar tidak kedap-kedip
            $timeout_seconds = 45;
        $is_online = false;
        if ($waktu_raw) {
            $last_time = strtotime($waktu_raw);
            if ($last_time > 0 && (time() - $last_time) <= $timeout_seconds) {
                $is_online = true;
            }
        }

        if ($is_online) {
            $apiValue = isset($row['api']) ? (float)$row['api'] : 0;
            $rawAsap = isset($row['asap']) ? $row['asap'] : 0;
            
            $strApi = isset($row['api']) ? trim(strtolower((string)$row['api'])) : '';
            if ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'sedang' || $strApi === 'tinggi' || $apiValue > 0.5) {
                $apiStatus = "Terdeteksi Api";
            } else {
                $apiStatus = "Aman";
            }
            
            if (is_numeric($rawAsap)) {
                $fAsap = (float)$rawAsap;
                if ($fAsap > ($fAsap > 1 ? 750 : 0.5)) {
                    $asapStatus = "Tinggi";
                } else if ($fAsap > ($fAsap > 1 ? 350 : 0.25)) {
                    $asapStatus = "Sedang";
                } else {
                    $asapStatus = "Normal";
                }
                $asapNum = $fAsap;
            } else {
                $strAsap = trim((string)$rawAsap);
                if (strcasecmp($strAsap, 'Tinggi') === 0 || strcasecmp($strAsap, 'Bahaya') === 0) {
                    $asapStatus = "Tinggi";
                    $asapNum = 1;
                } else if (strcasecmp($strAsap, 'Sedang') === 0 || strcasecmp($strAsap, 'Waspada') === 0) {
                    $asapStatus = "Sedang";
                    $asapNum = 0.5;
                } else {
                    $asapStatus = "Normal";
                    $asapNum = 0;
                }
            }
            
            $suhuVal = isset($row['suhu']) ? (float)$row['suhu'] : 0.0;
            $kelembapanVal = isset($row['kelembapan']) ? (float)$row['kelembapan'] : 0.0;
            $teganganVal = isset($row['tegangan']) ? (float)$row['tegangan'] : 0.0;
            $arusVal = isset($row['arus']) ? (float)$row['arus'] : 0.0;

            $isDanger = ($apiStatus === "Terdeteksi Api" || $asapStatus === "Tinggi" 
                || ($suhuVal > $limit_suhu) 
                || ($kelembapanVal > $limit_kelembapan) 
                || ($teganganVal > $limit_tegangan) 
                || ($arusVal > $limit_arus));

            echo json_encode([
                "error"            => false,
                "waktu"            => date('H:i:s', strtotime($waktu_raw)),
                "api"              => $apiStatus,
                "asap"             => $asapStatus,
                "asap_value"       => $asapNum,
                "suhu"             => number_format($suhuVal, 1),
                "kelembapan"       => number_format($kelembapanVal, 1),
                "tegangan"         => number_format($teganganVal, 1),
                "arus"             => number_format($arusVal, 2),
                "rssi"             => $row['rssi'] ?? '-',
                "ip"               => !empty($row['ip_address']) ? $row['ip_address'] : '-',
                "latitude"         => isset($row['latitude']) ? (float)$row['latitude'] : null,
                "longitude"        => isset($row['longitude']) ? (float)$row['longitude'] : null,
                "isDanger"         => $isDanger,
                "apiValue"         => ($apiStatus === "Terdeteksi Api") ? 1 : 0,
                "status"           => "Online",
                "limit_suhu"       => $limit_suhu,
                "limit_kelembapan" => $limit_kelembapan,
                "limit_tegangan"   => $limit_tegangan,
                "limit_arus"       => $limit_arus,
                "batas_suhu"       => $limit_suhu,
                "batas_kelembapan" => $limit_kelembapan,
                "batas_tegangan"   => $limit_tegangan,
                "batas_arus"       => $limit_arus
            ]);
            exit();
        }
    }

    echo json_encode([
        "error"            => false,
        "waktu"            => date('H:i:s'),
        "api"              => "Aman",
        "asap"             => "Normal",
        "asap_value"       => 0,
        "suhu"             => "0.0",
        "kelembapan"       => "0.0",
        "tegangan"         => "0.0",
        "arus"             => "0.00",
        "rssi"             => '-',
        "ip"               => '-',
        "isDanger"         => false,
        "apiValue"         => 0,
        "status"           => "Offline",
        "limit_suhu"       => $limit_suhu,
        "limit_kelembapan" => $limit_kelembapan,
        "limit_tegangan"   => $limit_tegangan,
        "limit_arus"       => $limit_arus,
        "batas_suhu"       => $limit_suhu,
        "batas_kelembapan" => $limit_kelembapan,
        "batas_tegangan"   => $limit_tegangan,
        "batas_arus"       => $limit_arus
    ]);
    exit();
} else {
    // Fallback device outdoor jika dipanggil
    if (file_exists('get_latest_data.php')) {
        include 'get_latest_data.php';
        exit();
    }
    echo json_encode(["error" => true, "message" => "Device tidak dikenal."]);
}
?>
