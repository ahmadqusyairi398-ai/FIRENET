 <?php
    require_once 'koneksi.php';
    header('Content-Type: application/json');

    // Karena ini khusus outdoor, kita langsung gunakan pdo_outdoor
    $current_pdo = $pdo_outdoor;
    $sql = "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1";

    try {
        $stmt = $current_pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $co = isset($data['co']) ? (float)$data['co'] : 0.0;
            // Asap di tabel outdoor biasanya bertipe teks ("Normal", "Sedang", "Tinggi")
            $asap = isset($data['asap']) ? $data['asap'] : "Normal";

            // Cek kondisi bahaya (misal: jika asap mengandung kata "Tinggi" atau CO di atas 50)
            $isDanger = (stripos($asap, 'tinggi') !== false || $co > 50);

            echo json_encode([
                'waktu'      => date('H:i:s', strtotime($data['timestamp'])),
                'asap'       => $asap,
                'suhu'       => isset($data['suhu']) ? number_format((float)$data['suhu'], 1) : "0.0",
                'kelembapan' => isset($data['kelembapan']) ? number_format((float)$data['kelembapan'], 1) : "0.0",
                'tegangan'   => isset($data['tegangan']) ? number_format((float)$data['tegangan'], 1) : "0.0",
                'arus'       => isset($data['arus']) ? number_format((float)$data['arus'], 2) : "0.0",
                'daya'       => isset($data['daya']) ? number_format((float)$data['daya'], 1) : "0.0",
                'angin'      => isset($data['kecepatan_angin']) ? number_format((float)$data['kecepatan_angin'], 1) : "0.0",
                'arah'       => isset($data['arah_angin']) ? $data['arah_angin'] : "-",
                'co'         => $co,
                'status'     => 'Online',
                'rssi'       => isset($data['rssi']) ? $data['rssi'] : 0,
                'ip'         => isset($data['ip_address']) ? $data['ip_address'] : "-",
                'isDanger'   => $isDanger
            ]);
        } else {
            // Jika tabel ada tapi belum ada datanya
            echo json_encode([
                'waktu'      => date('H:i:s'),
                'asap'       => 'Normal',
                'suhu'       => '0.0',
                'kelembapan' => '0.0',
                'tegangan'   => '0.0',
                'arus'       => '0.0',
                'daya'       => '0.0',
                'angin'      => '0.0',
                'arah'       => '-',
                'co'         => 0.0,
                'status'     => 'Offline (No Data)',
                'rssi'       => 0,
                'ip'         => '-',
                'isDanger'   => false
            ]);
        }
    } catch (PDOException $e) {
        // Jika tabel tidak ditemukan, return error gracefully
        echo json_encode([
            'waktu'      => date('H:i:s'),
            'asap'       => 'Normal',
            'suhu'       => '0.0',
            'kelembapan' => '0.0',
            'tegangan'   => '0.0',
            'arus'       => '0.0',
            'daya'       => '0.0',
            'angin'      => '0.0',
            'arah'       => '-',
            'co'         => 0.0,
            'status'     => 'Error: Tabel Belum Ada',
            'rssi'       => 0,
            'ip'         => '-',
            'isDanger'   => false
        ]);
    }
    ?>