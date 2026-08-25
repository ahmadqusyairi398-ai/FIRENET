<?php
date_default_timezone_set('Asia/Makassar');
header('Content-Type: application/json');

require_once 'koneksi.php';

/** @var PDO $pdo_indoor */
if (!$pdo_indoor) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Koneksi database Indoor tidak tersedia.'
    ]);
    exit;
}

try {
    // 1. Dapatkan daftar kolom di tabel data_sensor
    $columns = [];
    $colQuery = $pdo_indoor->query("SHOW COLUMNS FROM data_sensor");
    while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }

    // 2. Tentukan kolom tanggal/waktu yang valid
    $dateColumn = null;
    $possibleDate = ['timestamp', 'tanggal_dan_waktu', 'created_at', 'tanggal', 'waktu', 'datetime'];
    foreach ($possibleDate as $col) {
        if (in_array($col, $columns)) {
            $dateColumn = $col;
            break;
        }
    }
    if (!$dateColumn && !empty($columns)) {
        $dateColumn = $columns[0];
    }

    // 3. Tangkap parameter filter (GET / POST)
    $fromDate = trim($_GET['from'] ?? ($_POST['from'] ?? ''));
    $toDate = trim($_GET['to'] ?? ($_POST['to'] ?? ''));
    $isDummyParam = isset($_GET['is_dummy']) ? intval($_GET['is_dummy']) : (isset($_POST['is_dummy']) ? intval($_POST['is_dummy']) : 0);
    $location = trim($_GET['location'] ?? ($_POST['location'] ?? ''));

    // Pengaman server-side: Koreksi jika rentang tanggal terbalik
    if (!empty($fromDate) && !empty($toDate) && $fromDate > $toDate) {
        $temp = $fromDate;
        $fromDate = $toDate;
        $toDate = $temp;
    }

    // 4. Susun kolom SELECT
    $selectFields = ['id'];
    if ($dateColumn) {
        $selectFields[] = "$dateColumn as waktu";
    } else {
        $selectFields[] = "'' as waktu";
    }

    $sensorFields = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'api'];
    foreach ($sensorFields as $sf) {
        if (in_array($sf, $columns)) {
            $selectFields[] = $sf;
        } else {
            $selectFields[] = "0 as $sf";
        }
    }

    // 5. Susun kondisi WHERE
    $whereClauses = [];
    $params = [];

    // Filter data asli vs dummy
    if (in_array('is_dummy', $columns)) {
        if ($isDummyParam === 1) {
            $whereClauses[] = "is_dummy = 1";
        } else {
            $whereClauses[] = "(is_dummy = 0 OR is_dummy IS NULL)";
        }
    }

    // Filter tanggal Dari & Sampai
    if (!empty($fromDate) && $dateColumn) {
        $whereClauses[] = "$dateColumn >= :from_datetime";
        $params[':from_datetime'] = $fromDate . ' 00:00:00';
    }
    if (!empty($toDate) && $dateColumn) {
        $whereClauses[] = "$dateColumn <= :to_datetime";
        $params[':to_datetime'] = $toDate . ' 23:59:59';
    }

    $whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

    // 6. Jalankan Query
    // Jika ada rentang tanggal, ambil semua data pada rentang tersebut secara kronologis (ASC)
    // Jika tidak ada rentang tanggal, ambil 200 data riwayat terbaru (DESC) lalu balik urutannya (ASC)
    if (!empty($fromDate) || !empty($toDate)) {
        $query = "SELECT " . implode(", ", $selectFields) . " FROM data_sensor" . $whereSql;
        if ($dateColumn) {
            $query .= " ORDER BY $dateColumn ASC LIMIT 5000";
        } else {
            $query .= " ORDER BY id ASC LIMIT 5000";
        }
        $stmt = $pdo_indoor->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $query = "SELECT " . implode(", ", $selectFields) . " FROM data_sensor" . $whereSql;
        if ($dateColumn) {
            $query .= " ORDER BY $dateColumn DESC LIMIT 200";
        } else {
            $query .= " ORDER BY id DESC LIMIT 200";
        }
        $stmt = $pdo_indoor->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_reverse($rows);
    }

    // 7. Normalisasi format data untuk Chart.js
    $chartData = [];
    foreach ($rows as $row) {
        $timestamp = isset($row['waktu']) ? $row['waktu'] : '';

        // Normalisasi sensor asap
        $rawAsap = isset($row['asap']) ? $row['asap'] : 0;
        if (is_numeric($rawAsap)) {
            $fAsap = (float)$rawAsap;
            if ($fAsap > 100) {
                $asapVal = round(($fAsap / 1023) * 100, 1);
            } else if ($fAsap > 0) {
                $asapVal = $fAsap;
            } else {
                $asapVal = 15;
            }
        } else {
            $strAsap = trim((string)$rawAsap);
            if (strcasecmp($strAsap, 'Tinggi') === 0 || strcasecmp($strAsap, 'Bahaya') === 0) $asapVal = 85;
            else if (strcasecmp($strAsap, 'Sedang') === 0 || strcasecmp($strAsap, 'Waspada') === 0) $asapVal = 50;
            else $asapVal = 15;
        }

        // Normalisasi sensor api
        $rawApi = isset($row['api']) ? $row['api'] : 0;
        $strApi = isset($row['api']) ? trim(strtolower((string)$row['api'])) : '';
        if ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'tinggi' || (is_numeric($rawApi) && (float)$rawApi >= 1)) {
            $apiVal = 100;
        } else {
            $apiVal = 0;
        }

        $chartData[] = [
            'waktu' => $timestamp,
            'asap' => $asapVal,
            'suhu' => isset($row['suhu']) ? floatval($row['suhu']) : 0,
            'kelembapan' => isset($row['kelembapan']) ? floatval($row['kelembapan']) : 0,
            'tegangan' => isset($row['tegangan']) ? floatval($row['tegangan']) : 0,
            'arus' => isset($row['arus']) ? floatval($row['arus']) : 0,
            'api' => $apiVal
        ];
    }

    echo json_encode([
        'status' => 'success',
        'total' => count($chartData),
        'data' => $chartData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
