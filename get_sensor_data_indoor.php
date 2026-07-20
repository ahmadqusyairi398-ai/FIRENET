<?php
// Tentukan header agar output dibaca sebagai JSON
header('Content-Type: application/json');

// ========================================================
// 1. Konfigurasi Database
// Sesuaikan dengan pengaturan database server Anda (biasanya localhost/root)
// ========================================================
$host     = "localhost";
$username = "root";       // Default XAMPP biasanya "root"
$password = "";           // Default XAMPP biasanya kosong
$dbname   = "indoor";     // Sesuai dengan nama database Anda

// Membuat koneksi ke database menggunakan MySQLi
$conn = new mysqli($host, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    // Jika gagal, kirim pesan error dalam bentuk JSON
    echo json_encode([
        "error" => true,
        "message" => "Koneksi database gagal: " . $conn->connect_error
    ]);
    exit();
}

// ========================================================
// 2. Query Ambil Data Sensor Terbaru
// Mengambil 1 baris terakhir berdasarkan ID tertinggi (terbaru)
// ========================================================
$sql = "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Jika data ditemukan, ambil datanya
    $row = $result->fetch_assoc();
    
    // Kita juga perlu memformat data agar sesuai dengan yang diharapkan oleh JavaScript di dashboard
    // Misalnya menentukan apakah statusnya "Terdeteksi Api" atau "Aman" berdasarkan nilai dari sensor
    
    $apiValue = (float)$row['api'];
    $asapValue = (float)$row['asap'];
    
    // Asumsi: jika sensor api bernilai lebih dari 0.5 (atau logika batas Anda), maka terdeteksi
    $apiStatus = ($apiValue > 0.5) ? "Terdeteksi Api" : "Aman";
    
    // Asumsi: jika sensor asap bernilai tinggi
    $asapStatus = ($asapValue > 100) ? "Tinggi" : "Normal"; 
    
    $isDanger = ($apiStatus === "Terdeteksi Api" || $asapStatus === "Tinggi");
    
    // Susun data yang akan dikirimkan ke frontend (dashboard)
    $data = [
        "error"      => false,
        "waktu"      => date('H:i:s', strtotime($row['timestamp'])), // Format jam dari timestamp database
        "api"        => $apiStatus,
        "asap"       => $asapStatus,
        "suhu"       => $row['suhu'],
        "kelembapan" => $row['kelembapan'],
        "tegangan"   => $row['tegangan'],
        "arus"       => $row['arus'],
        "rssi"       => $row['rssi'],
        "ip"         => $row['ip_address'],
        "latitude"   => $row['latitude'],
        "longitude"  => $row['longitude'],
        "isDanger"   => $isDanger,
        "apiValue"   => ($apiStatus === "Terdeteksi Api") ? 1 : 0
    ];
    
    echo json_encode($data);
} else {
    // Jika tabel data_sensor masih kosong
    echo json_encode([
        "error" => true,
        "message" => "Belum ada data sensor di database."
    ]);
}

// Tutup koneksi
$conn->close();
?>