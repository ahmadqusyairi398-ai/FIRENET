<?php
    header('Content-Type: application/json');
    require_once 'koneksi.php';

    // Tangkap parameter device dan is_dummy
    $device = isset($_GET['device']) ? strtolower($_GET['device']) : 'indoor';
    $is_dummy = isset($_GET['is_dummy']) ? (int)$_GET['is_dummy'] : 0;

    // Pilih koneksi secara ketat sesuai device
    if ($device === 'outdoor') {
        $conn = isset($conn_outdoor) && $conn_outdoor ? $conn_outdoor : null;
    } else {
        $conn = isset($conn_indoor) && $conn_indoor ? $conn_indoor : null;
    }

    if (!$conn) {
        echo json_encode(["error" => true, "message" => "Koneksi database gagal"]);
        exit();
    }

    // Cek nama kolom waktu
    $colCheckWaktu = @mysqli_query($conn, "SHOW COLUMNS FROM data_sensor LIKE 'timestamp'");
    $dateCol = 'tanggal_dan_waktu';
    if ($colCheckWaktu && mysqli_num_rows($colCheckWaktu) > 0) {
        $dateCol = 'timestamp';
    }

    // Ambil 20 data terakhir khusus untuk alat yang diklik (is_dummy), lalu balik urutannya agar grafik berjalan
    $sql = "SELECT * FROM (SELECT * FROM data_sensor WHERE is_dummy = $is_dummy ORDER BY id DESC LIMIT 20) sub ORDER BY id ASC";
    $result = @mysqli_query($conn, $sql);

    $data = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $waktu_raw = $row['timestamp'] ?? ($row['tanggal_dan_waktu'] ?? ($row['created_at'] ?? null));

            $apiValue = isset($row['api']) ? (float)$row['api'] : 0;
            $rawAsap = isset($row['asap']) ? $row['asap'] : 0;

            // Logika status Api
            $strApi = isset($row['api']) ? trim(strtolower((string)$row['api'])) : '';
            if ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'sedang' || $strApi === 'tinggi' ||
  $apiValue > 0.5) {
                $apiStatus = "Terdeteksi Api";
            } else {
                $apiStatus = "Aman";
            }

            // Logika status Asap
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

            // Susun data untuk dikirim ke Javascript
            $data[] = [
                "waktu"      => date('H:i:s', strtotime($waktu_raw)),
                "apiValue"   => ($apiStatus === "Terdeteksi Api") ? 1 : 0,
                "api"        => $apiStatus,
                "asap"       => $asapStatus,
                "asap_value" => $asapNum,
                "suhu"       => number_format((float)($row['suhu'] ?? 0), 1),
                "kelembapan" => number_format((float)($row['kelembapan'] ?? 0), 1),
                "tegangan"   => number_format((float)($row['tegangan'] ?? 0), 1),
                "arus"       => number_format((float)($row['arus'] ?? 0), 2)
            ];
        }
    }

    echo json_encode(["error" => false, "history" => $data]);
    ?>