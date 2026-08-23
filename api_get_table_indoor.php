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
    $fromDate = trim($_GET['from'] ?? ($_GET['start_date'] ?? ($_POST['from'] ?? '')));
    $toDate = trim($_GET['to'] ?? ($_GET['end_date'] ?? ($_POST['to'] ?? '')));
    $isDummyParam = isset($_GET['is_dummy']) ? intval($_GET['is_dummy']) : (isset($_POST['is_dummy']) ? intval($_POST['is_dummy']) : 0);
    $location = trim($_GET['location'] ?? ($_POST['location'] ?? ''));
    $withStorage = isset($_GET['with_storage']) && $_GET['with_storage'] == '1';

    // 4. Susun kolom SELECT
    $selectFields = ['id'];
    if ($dateColumn) {
        $selectFields[] = "$dateColumn as tanggal_waktu";
    } else {
        $selectFields[] = "'' as tanggal_waktu";
    }

    $sensorFields = ['asap', 'suhu', 'kelembapan', 'tegangan', 'arus', 'api', 'rssi', 'ip_address'];
    foreach ($sensorFields as $sf) {
        if (in_array($sf, $columns)) {
            $selectFields[] = $sf;
        } else {
            $selectFields[] = "'' as $sf";
        }
    }

    if (in_array('is_dummy', $columns)) {
        $selectFields[] = 'is_dummy';
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

    // Filter tanggal Mulai & Akhir
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
    // Jika ada filter tanggal, ambil seluruh data dalam rentang tersebut (limit hingga 10.000)
    // Jika tidak ada filter tanggal, ambil 1.000 data riwayat terbaru
    $limit = (!empty($fromDate) || !empty($toDate)) ? 10000 : 1000;
    
    $query = "SELECT " . implode(", ", $selectFields) . " FROM data_sensor" . $whereSql;
    if ($dateColumn) {
        $query .= " ORDER BY $dateColumn DESC LIMIT $limit";
    } else {
        $query .= " ORDER BY id DESC LIMIT $limit";
    }

    $stmt = $pdo_indoor->prepare($query);
    $stmt->execute($params);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Format data untuk Tabel
    $formattedRows = [];
    foreach ($rawRows as $r) {
        $waktuRaw = $r['tanggal_waktu'] ?? '';
        $waktuFormatted = !empty($waktuRaw) ? date('Y-m-d H:i:s', strtotime($waktuRaw)) : '-';

        $rawAsap = $r['asap'] ?? '0';
        $asapVal = is_numeric($rawAsap) ? number_format((float)$rawAsap, 2, '.', '') : (string)$rawAsap;

        $rawApi = $r['api'] ?? '0';
        $strApi = trim(strtolower((string)$rawApi));
        if ($strApi === 'terdeteksi api' || $strApi === 'dekat' || $strApi === 'tinggi' || (is_numeric($rawApi) && (float)$rawApi >= 1)) {
            $apiVal = "100";
        } else {
            $apiVal = "0";
        }

        $formattedRows[] = [
            'id' => (int)$r['id'],
            'tanggal_waktu' => $waktuFormatted,
            'api' => $apiVal,
            'asap' => $asapVal,
            'suhu' => isset($r['suhu']) && is_numeric($r['suhu']) ? number_format((float)$r['suhu'], 1, '.', '') : '0.0',
            'kelembapan' => isset($r['kelembapan']) && is_numeric($r['kelembapan']) ? number_format((float)$r['kelembapan'], 1, '.', '') : '0.0',
            'tegangan' => isset($r['tegangan']) && is_numeric($r['tegangan']) ? number_format((float)$r['tegangan'], 1, '.', '') : '0.0',
            'arus' => isset($r['arus']) && is_numeric($r['arus']) ? number_format((float)$r['arus'], 2, '.', '') : '0.00',
            'rssi' => !empty($r['rssi']) ? (string)$r['rssi'] : '0',
            'is_dummy' => (int)($r['is_dummy'] ?? 0)
        ];
    }

    $response = [
        'status' => 'success',
        'total' => count($formattedRows),
        'data' => $formattedRows
    ];

    // Info storage jika diminta
    if ($withStorage) {
        $indoor_storage = get_sensor_storage_info($pdo_indoor, 'indoor');
        $response['storage'] = [
            'real' => $indoor_storage['real_formatted'],
            'dummy' => $indoor_storage['dummy_formatted']
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
