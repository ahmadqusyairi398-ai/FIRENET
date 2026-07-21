<?php
session_start();
require_once 'koneksi.php'; // Hubungkan ke database

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";

// ========== AMBIL DATA LOKASI DARI DATABASE ==========
$locations_db = [];
if ($pdo) {
    try {
        // Cek apakah tabel lokasi_alat ada
        $stmt = $pdo->query("SHOW TABLES LIKE 'lokasi_alat'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Ambil data dari tabel lokasi_alat
            $stmt = $pdo->query("SELECT id, id_alat, nama_lokasi, latitude, longitude FROM lokasi_alat ORDER BY id ASC");
            $locations_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Jika tabel belum ada, gunakan data default
            $locations_db = [];
        }
    } catch (PDOException $e) {
        // Jika error, gunakan data default
        $locations_db = [];
    }
}

// ========== DATA LOKASI DEFAULT (FALLBACK) ==========
$default_locations = [
    // ===== POLITEKNIK NEGERI BALIKPAPAN (3 titik) =====
    [
        "id" => 1,
        "id_alat" => "OUT-001",
        "nama_lokasi" => "Politeknik Negeri Balikpapan - Kampus Utama",
        "latitude" => -1.201888,
        "longitude" => 116.886997
    ],
    [
        "id" => 2,
        "id_alat" => "OUT-002",
        "nama_lokasi" => "Gedung A - Kampus Utama",
        "latitude" => -1.201700,
        "longitude" => 116.886944
    ],
    [
        "id" => 3,
        "id_alat" => "OUT-003",
        "nama_lokasi" => "Laboratorium Komputer - Kampus Utama",
        "latitude" => -1.202000,
        "longitude" => 116.886800
    ],
    
    // ===== PENAJAM PASER UTARA (5 titik) =====
    [
        "id" => 4,
        "id_alat" => "OUT-004",
        "nama_lokasi" => "Kantor Bupati Penajam Paser Utara",
        "latitude" => -1.309914,
        "longitude" => 116.727563
    ],
    [
        "id" => 5,
        "id_alat" => "OUT-005",
        "nama_lokasi" => "Pelabuhan Penajam",
        "latitude" => -1.242074,
        "longitude" => 116.776876
    ],
    [
        "id" => 6,
        "id_alat" => "OUT-006",
        "nama_lokasi" => "RSUD Ratu Aji Putri Botung PPU",
        "latitude" => -1.308893,
        "longitude" => 116.734787
    ],
    [
        "id" => 7,
        "id_alat" => "OUT-007",
        "nama_lokasi" => "Alun-Alun Penajam",
        "latitude" => -1.309383,
        "longitude" => 116.728334
    ],
    [
        "id" => 8,
        "id_alat" => "OUT-008",
        "nama_lokasi" => "Kawasan Titik Nol IKN (Sepaku, PPU)",
        "latitude" => -0.966113,
        "longitude" => 116.702781
    ],
    
    // ===== SAMARINDA (2 titik) =====
    [
        "id" => 9,
        "id_alat" => "OUT-009",
        "nama_lokasi" => "Kantor Gubernur Kaltim (Samarinda)",
        "latitude" => 0.501219,
        "longitude" => 117.139391
    ],
    [
        "id" => 10,
        "id_alat" => "OUT-010",
        "nama_lokasi" => "Masjid Islamic Center Samarinda",
        "latitude" => -0.502952,
        "longitude" => 117.120259
    ]
];

// Gunakan data dari database jika ada, jika tidak gunakan default
$locations = (count($locations_db) > 0) ? $locations_db : $default_locations;

// Pastikan semua lokasi memiliki field yang dibutuhkan
foreach ($locations as &$loc) {
    if (!isset($loc['id_alat'])) {
        $loc['id_alat'] = 'ALAT-' . str_pad($loc['id'], 3, '0', STR_PAD_LEFT);
    }
    if (!isset($loc['nama_lokasi'])) {
        $loc['nama_lokasi'] = 'Lokasi ' . $loc['id'];
    }
}
unset($loc);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portofolio - FIREDETECTOR Maps</title>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ========== STYLE SAMA SEPERTI SEBELUMNYA ========== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background-image: url('https://i.pinimg.com/736x/ea/7c/ca/ea7cca792d193c0a4599fbcf96f21fa3.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    color: #333;
    position: relative;
    min-height: 100vh;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: -1;
}

/* ========== NAVBAR ========== */
.navbar {
    background: linear-gradient(135deg, rgba(40, 30, 25, 0.95), rgba(25, 18, 15, 0.95));
    padding: 15px 50px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo i {
    font-size: 32px;
    color: #e85d04;
}

.logo h1 {
    color: white;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 1px;
}

.logo span {
    color: #e85d04;
}

/* ========== MENU HAMBURGER ========== */
.menu-toggle {
    display: flex !important;
    font-size: 26px;
    color: white;
    cursor: pointer;
    padding: 12px 18px;
    border-radius: 10px;
    transition: all 0.3s;
    background: rgba(255, 255, 255, 0.08);
    border: 2px solid rgba(255, 255, 255, 0.1);
    min-width: 55px;
    min-height: 55px;
    align-items: center;
    justify-content: center;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    line-height: 1;
    z-index: 100;
}

.menu-toggle:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
}

.menu-toggle:active {
    background: rgba(232, 93, 4, 0.3);
    border-color: #e85d04;
    transform: scale(0.95);
}

.menu-toggle i {
    pointer-events: none;
    font-size: 24px;
}

/* ========== SIDE MENU ========== */
.side-menu-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 2000;
    animation: fadeIn 0.3s ease;
}

.side-menu-overlay.active {
    display: block;
}

.side-menu {
    position: fixed;
    top: 0;
    right: -400px;
    width: 380px;
    max-width: 85%;
    height: 100%;
    background: linear-gradient(180deg, rgba(40, 30, 25, 0.98), rgba(20, 15, 12, 0.98));
    backdrop-filter: blur(20px);
    z-index: 2001;
    transition: right 0.4s cubic-bezier(0.22, 1, 0.36, 1);
    box-shadow: -10px 0 40px rgba(0,0,0,0.5);
    padding: 30px 25px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.side-menu.open {
    right: 0;
}

.side-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 30px;
}

.side-menu-header .logo-side {
    display: flex;
    align-items: center;
    gap: 10px;
}

.side-menu-header .logo-side i {
    font-size: 28px;
    color: #e85d04;
}

.side-menu-header .logo-side span {
    color: white;
    font-size: 20px;
    font-weight: 700;
}

.side-menu-header .logo-side span span {
    color: #e85d04;
}

.side-menu-close {
    background: rgba(255,255,255,0.08);
    border: none;
    color: white;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.side-menu-close:hover {
    background: rgba(232, 93, 4, 0.3);
    transform: rotate(90deg);
}

.side-menu-items {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.side-menu-items .menu-label {
    color: rgba(255,255,255,0.4);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 2px;
    padding: 15px 5px 8px;
    font-weight: 600;
}

.side-menu-items a {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 18px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s;
    font-weight: 500;
    font-size: 16px;
    position: relative;
}

.side-menu-items a i {
    width: 28px;
    font-size: 20px;
    color: #e85d04;
    text-align: center;
    transition: all 0.3s;
}

.side-menu-items a:hover {
    background: rgba(232, 93, 4, 0.15);
    color: white;
    transform: translateX(5px);
}

.side-menu-items a:hover i {
    transform: scale(1.1);
}

.side-menu-items a.active {
    background: rgba(232, 93, 4, 0.2);
    color: white;
}

.side-menu-items a .badge {
    margin-left: auto;
    background: rgba(232, 93, 4, 0.3);
    color: #e85d04;
    font-size: 11px;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 600;
}

.side-menu-items .divider {
    height: 1px;
    background: linear-gradient(to right, rgba(255,255,255,0.05), rgba(255,255,255,0.15), rgba(255,255,255,0.05));
    margin: 10px 5px;
}

/* ========== DROPDOWN ========== */
.side-dropdown {
    position: relative;
}

.side-dropdown .dropbtn {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 18px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s;
    font-weight: 500;
    font-size: 16px;
    cursor: pointer;
    width: 100%;
    background: transparent;
    border: none;
    font-family: 'Poppins', sans-serif;
}

.side-dropdown .dropbtn i {
    width: 28px;
    font-size: 20px;
    color: #e85d04;
    text-align: center;
    transition: all 0.3s;
}

.side-dropdown .dropbtn .arrow {
    margin-left: auto;
    transition: transform 0.3s;
    font-size: 14px;
    color: rgba(255,255,255,0.4);
}

.side-dropdown .dropbtn:hover {
    background: rgba(232, 93, 4, 0.15);
    color: white;
}

.side-dropdown .dropbtn:hover i {
    transform: scale(1.1);
}

.side-dropdown .dropbtn.active {
    background: rgba(232, 93, 4, 0.2);
    color: white;
}

.side-dropdown .dropdown-content {
    display: none;
    flex-direction: column;
    gap: 4px;
    padding-left: 20px;
    margin-left: 10px;
    border-left: 2px solid rgba(232, 93, 4, 0.3);
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease, padding 0.3s ease;
}

.side-dropdown .dropdown-content.open {
    display: flex;
    max-height: 300px;
    padding-top: 8px;
    padding-bottom: 8px;
}

.side-dropdown .dropdown-content a {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 16px;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
    font-weight: 400;
    font-size: 14px;
}

.side-dropdown .dropdown-content a i {
    width: 22px;
    font-size: 16px;
    color: #e85d04;
    text-align: center;
}

.side-dropdown .dropdown-content a:hover {
    background: rgba(232, 93, 4, 0.1);
    color: white;
    transform: translateX(5px);
}

.side-dropdown .dropdown-content a .badge {
    margin-left: auto;
    background: rgba(232, 93, 4, 0.3);
    color: #e85d04;
    font-size: 10px;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 600;
}

.nav-menu {
    display: none;
}

/* ========== MAPS SECTION ========== */
.maps-section {
    padding: 30px 50px;
    margin: 30px 50px;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(8px);
    border-radius: 20px;
}

.maps-title {
    text-align: center;
    margin-bottom: 30px;
}

.maps-title h2 {
    font-size: 36px;
    font-weight: 700;
    background: linear-gradient(135deg, #e85d04, #f48c06);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 10px;
}

.maps-title p {
    color: #ddd;
    font-size: 16px;
}

.maps-container {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.2);
}

#map {
    height: 550px;
    width: 100%;
    z-index: 1;
}

/* ========== SEARCH BAR ========== */
.search-container {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 1100;
    width: 350px;
    max-width: calc(100% - 40px);
}

.search-box {
    display: flex;
    align-items: center;
    background: rgba(30, 25, 22, 0.9);
    border: 1.5px solid rgba(255, 255, 255, 0.2);
    border-radius: 30px;
    padding: 10px 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.search-box:focus-within {
    border-color: #e85d04;
    box-shadow: 0 4px 20px rgba(232, 93, 4, 0.4);
    background: rgba(30, 25, 22, 0.95);
}

.search-icon {
    color: #e85d04;
    font-size: 16px;
    margin-right: 12px;
}

.search-box input {
    background: transparent;
    border: none;
    outline: none;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    width: 100%;
}

.search-box input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.clear-btn {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    font-size: 14px;
    padding: 2px 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.clear-btn:hover {
    color: #e85d04;
}

.search-results {
    background: rgba(30, 25, 22, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    margin-top: 8px;
    max-height: 250px;
    overflow-y: auto;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
    display: none;
    backdrop-filter: blur(15px);
}

.search-results::-webkit-scrollbar {
    width: 6px;
}

.search-results::-webkit-scrollbar-thumb {
    background: rgba(232, 93, 4, 0.5);
    border-radius: 10px;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    color: #ddd;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: rgba(232, 93, 4, 0.15);
    color: #fff;
}

.search-result-item i {
    color: #e85d04;
    font-size: 16px;
}

.search-result-item .loc-name {
    font-weight: 500;
    font-size: 14px;
    display: block;
}

.search-result-item .loc-coords {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.4);
    display: block;
}

.no-results {
    padding: 15px;
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
    font-size: 14px;
}

/* ========== LOCATION INFO ========== */
.location-info {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding: 20px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    border-radius: 15px;
}

.location-info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
    background: rgba(255,255,255,0.1);
    padding: 10px 24px;
    border-radius: 50px;
}

.location-info-item i {
    font-size: 22px;
    color: #e85d04;
}

.location-info-item .label {
    color: #ccc;
    font-weight: 500;
}

.location-info-item .value {
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.5px;
}

/* ========== FOOTER ========== */
.footer {
    background: rgba(20, 15, 12, 0.95);
    backdrop-filter: blur(10px);
    color: #aaa;
    padding: 30px 50px;
    text-align: center;
    margin-top: 30px;
}

.footer p {
    margin: 5px 0;
    font-size: 14px;
}

.footer a {
    color: #e85d04;
    text-decoration: none;
}

/* ========== ANIMATIONS ========== */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInRight {
    from { transform: translateX(30px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.side-menu-items a,
.side-dropdown .dropbtn {
    animation: slideInRight 0.4s ease backwards;
}

.side-menu-items a:nth-child(1) { animation-delay: 0.05s; }
.side-dropdown { animation-delay: 0.10s; }
.side-dropdown .dropbtn { animation-delay: 0.10s; }
.side-menu-items a:nth-child(3) { animation-delay: 0.15s; }
.side-menu-items a:nth-child(4) { animation-delay: 0.20s; }
.side-menu-items a:nth-child(5) { animation-delay: 0.25s; }

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .navbar {
        padding: 12px 20px;
    }
    
    .maps-section {
        padding: 20px;
        margin: 20px;
    }
    
    .maps-title h2 {
        font-size: 28px;
    }
    
    #map {
        height: 400px;
    }
    
    .location-info-item {
        font-size: 14px;
        padding: 8px 18px;
    }
    
    .side-menu {
        width: 350px;
        max-width: 85%;
    }
}

@media (max-width: 480px) {
    .logo h1 {
        font-size: 20px;
    }
    
    .logo i {
        font-size: 24px;
    }
    
    .menu-toggle {
        padding: 8px 12px;
        min-width: 44px;
        min-height: 44px;
        font-size: 20px;
    }
    
    .menu-toggle i {
        font-size: 18px;
    }
    
    .maps-title h2 {
        font-size: 24px;
    }
    
    #map {
        height: 350px;
    }
    
    .location-info-item {
        font-size: 12px;
        gap: 8px;
    }
    
    .side-menu {
        width: 300px;
        padding: 20px 18px;
    }
    
    .side-menu-items a,
    .side-dropdown .dropbtn {
        padding: 12px 14px;
        font-size: 14px;
    }
    
    .side-menu-items a i,
    .side-dropdown .dropbtn i {
        font-size: 18px;
        width: 24px;
    }
    
    .side-dropdown .dropdown-content a {
        font-size: 13px;
        padding: 8px 14px;
    }
}
</style>
</head>
<body>

<!-- ========== NAVIGATION BAR ========== -->
<nav class="navbar">
    <div class="logo">
        <i class="fas fa-fire"></i>
        <h1>FIRE<span>DETECTOR</span></h1>
    </div>
    
    <div class="menu-toggle" id="menuToggle" onclick="openSideMenu()">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="nav-menu">
        <a href="home.php">Beranda</a>
        <a href="portofolio.php" class="active">Portofolio</a>
    </div>
</nav>

<!-- ========== SIDE MENU ========== -->
<div class="side-menu-overlay" id="sideMenuOverlay" onclick="closeSideMenu()"></div>

<div class="side-menu" id="sideMenu">
    <div class="side-menu-header">
        <div class="logo-side">
            <i class="fas fa-fire"></i>
            <span>FIRE<span>DETECTOR</span></span>
        </div>
        <button class="side-menu-close" onclick="closeSideMenu()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="side-menu-items">
        <div class="menu-label">Menu Utama</div>
        
        <a href="home.php">
            <i class="fas fa-home"></i>
            Beranda
        </a>
        
        <div class="side-dropdown">
            <button class="dropbtn" onclick="toggleDropdown(event)">
                <i class="fas fa-tools"></i>
                Review
                <span class="arrow"><i class="fas fa-chevron-down"></i></span>
            </button>
            <div class="dropdown-content" id="alatDropdown">
                <a href="umum_outdoor.php">
                    <i class="fas fa-tree"></i>
                    Review Dashboard Outdoor
                    <span class="badge">Review</span>
                </a>
                <a href="umum_indoor.php">
                    <i class="fas fa-building"></i>
                    Review Dashboard Indoor
                    <span class="badge">Review</span>
                </a>
            </div>
        </div>
        
        <a href="portofolio.php" class="active">
            <i class="fas fa-map-marked-alt"></i>
            Portofolio
        </a>
        
        <a href="login.php?redirect=indoor">
            <i class="fas fa-building"></i>
            Dashboard Indoor
            <span class="badge">Login</span>
        </a>
        
        <a href="login.php?redirect=outdoor">
            <i class="fas fa-tree"></i>
            Dashboard Outdoor
            <span class="badge">Login</span>
        </a>
    </div>
</div>

<!-- ========== MAPS SECTION ========== -->
<section class="maps-section">
    <div class="maps-title">
        <h2><i class="fas fa-map-marked-alt"></i> Lokasi Monitoring</h2>
        <p>Koordinat pemasangan alat deteksi kebakaran <span style="color: #e85d04; font-weight: 600;">(<?= count($locations) ?> Lokasi)</span></p>
    </div>
    <div class="maps-container">
        <div class="search-container">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="mapSearchInput" placeholder="Cari lokasi alat..." oninput="filterLocations()" autocomplete="off">
                <button class="clear-btn" id="clearSearchBtn" onclick="clearSearch()" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
        <div id="map"></div>
    </div>
    <div class="location-info">
        <div class="location-info-item">
            <i class="fas fa-map-marker-alt"></i>
            <span class="label">Lokasi:</span>
            <span class="value" id="locationName">-</span>
        </div>
        <div class="location-info-item">
            <i class="fas fa-globe"></i>
            <span class="label">Koordinat:</span>
            <span class="value" id="coordValue">-</span>
        </div>
        <div class="location-info-item">
            <i class="fas fa-qrcode"></i>
            <span class="label">ID Alat:</span>
            <span class="value" id="alatId">-</span>
        </div>
    </div>
</section>

<!-- ========== FOOTER ========== -->
<div class="footer">
    <p>&copy; <?= date('Y') ?> <strong>FIREDETECTOR</strong> - Smart Monitoring Solution</p>
    <p>Dibangun dengan <i class="fas fa-heart" style="color: #e85d04;"></i> untuk keselamatan</p>
</div>

<script>
// ========== PASS DATA PHP KE JAVASCRIPT ==========
var locations = <?= json_encode($locations) ?>;

// ========== FUNGSI SIDE MENU ==========
function openSideMenu() {
    document.getElementById('sideMenuOverlay').classList.add('active');
    document.getElementById('sideMenu').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeSideMenu() {
    document.getElementById('sideMenuOverlay').classList.remove('active');
    document.getElementById('sideMenu').classList.remove('open');
    document.body.style.overflow = '';
}

function toggleDropdown(event) {
    event.stopPropagation();
    var btn = event.currentTarget;
    var content = btn.nextElementSibling;
    var arrow = btn.querySelector('.arrow i');
    
    content.classList.toggle('open');
    arrow.style.transform = content.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSideMenu();
});

document.addEventListener('click', function(e) {
    document.querySelectorAll('.side-dropdown').forEach(function(dropdown) {
        if (!dropdown.contains(e.target)) {
            var content = dropdown.querySelector('.dropdown-content');
            var arrow = dropdown.querySelector('.arrow i');
            if (content) {
                content.classList.remove('open');
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            }
        }
    });
});

// ========== MAPS ==========
var defaultLat = -1.20249;
var defaultLng = 116.88708;
var map = L.map('map', { zoomControl: false }).setView([defaultLat, defaultLng], 10);
L.control.zoom({ position: 'bottomright' }).addTo(map);

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

L.control.scale({ metric: true, imperial: false }).addTo(map);

// Icon marker
var fireIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #e85d04, #dc2f02); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-fire" style="color: white; font-size: 18px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'fire-marker'
});

var markersMap = {};

function selectLocation(id, panTo) {
    if (panTo === undefined) panTo = true;
    var item = markersMap[id];
    if (!item) return;
    
    document.getElementById('locationName').innerText = item.data.nama_lokasi || 'Lokasi ' + id;
    document.getElementById('coordValue').innerText = item.data.latitude.toFixed(6) + ', ' + item.data.longitude.toFixed(6);
    document.getElementById('alatId').innerText = item.data.id_alat || '-';
    
    if (panTo) {
        map.flyTo([item.data.latitude, item.data.longitude], 17, { animate: true, duration: 1.5 });
        setTimeout(function() { item.marker.openPopup(); }, 1500);
    } else {
        item.marker.openPopup();
    }
}

function renderMarkers() {
    for (var key in markersMap) {
        map.removeLayer(markersMap[key].marker);
        map.removeLayer(markersMap[key].circle);
    }
    markersMap = {};

    locations.forEach(function(loc) {
        var marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], { icon: fireIcon }).addTo(map);
        
        var circle = L.circle([parseFloat(loc.latitude), parseFloat(loc.longitude)], {
            color: '#e85d04',
            fillColor: '#e85d04',
            fillOpacity: 0.15,
            radius: 500
        }).addTo(map);
        
        var popupContent = `
            <div style="min-width: 200px; font-family: 'Poppins', sans-serif; text-align: center;">
                <i class="fas fa-map-marker-alt" style="color: #e85d04; font-size: 18px; margin-bottom: 5px;"></i>
                <div style="font-weight: 600; font-size: 14px;">${loc.nama_lokasi || 'Lokasi ' + loc.id}</div>
                <div style="font-size: 12px; color: #666; margin-top: 2px;">ID: ${loc.id_alat || '-'}</div>
                <div style="font-size: 13px; background: #f0f0f0; padding: 5px; border-radius: 8px; margin-top: 5px;">
                    ${parseFloat(loc.latitude).toFixed(6)}, ${parseFloat(loc.longitude).toFixed(6)}
                </div>
            </div>
        `;
        marker.bindPopup(popupContent);
        
        markersMap[loc.id] = { marker: marker, circle: circle, data: loc };
        
        marker.on('click', function() { selectLocation(loc.id, false); });
    });
}

// ========== SEARCH ==========
function filterLocations() {
    var input = document.getElementById('mapSearchInput');
    var filter = input.value.toLowerCase();
    var resultsContainer = document.getElementById('searchResults');
    var clearBtn = document.getElementById('clearSearchBtn');
    
    if (filter.length > 0) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
    }
    
    var filtered = locations.filter(function(loc) {
        var nama = (loc.nama_lokasi || '').toLowerCase();
        var idAlat = (loc.id_alat || '').toLowerCase();
        return nama.includes(filter) || idAlat.includes(filter);
    });
    
    resultsContainer.innerHTML = '';
    
    if (filter === '') {
        resultsContainer.style.display = 'none';
        return;
    }
    
    resultsContainer.style.display = 'block';
    
    if (filtered.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">Lokasi tidak ditemukan</div>';
        return;
    }
    
    filtered.forEach(function(loc) {
        var div = document.createElement('div');
        div.className = 'search-result-item';
        div.innerHTML = `
            <i class="fas fa-map-marker-alt"></i>
            <div>
                <span class="loc-name">${loc.nama_lokasi || 'Lokasi ' + loc.id}</span>
                <span class="loc-coords">${loc.id_alat || ''} - ${parseFloat(loc.latitude).toFixed(6)}, ${parseFloat(loc.longitude).toFixed(6)}</span>
            </div>
        `;
        div.onclick = function() {
            input.value = loc.nama_lokasi || '';
            resultsContainer.style.display = 'none';
            selectLocation(loc.id, true);
        };
        resultsContainer.appendChild(div);
    });
}

function clearSearch() {
    var input = document.getElementById('mapSearchInput');
    input.value = '';
    document.getElementById('clearSearchBtn').style.display = 'none';
    document.getElementById('searchResults').style.display = 'none';
}

document.addEventListener('click', function(e) {
    var container = document.querySelector('.search-container');
    var results = document.getElementById('searchResults');
    if (container && !container.contains(e.target)) {
        results.style.display = 'none';
    }
});

// ========== INISIALISASI ==========
function initMap() {
    renderMarkers();
    
    if (locations.length > 0) {
        var bounds = L.latLngBounds([]);
        locations.forEach(function(loc) {
            bounds.extend([parseFloat(loc.latitude), parseFloat(loc.longitude)]);
        });
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });
        selectLocation(locations[0].id, false);
    }
}

document.addEventListener('DOMContentLoaded', initMap);
</script>

</body>
</html>