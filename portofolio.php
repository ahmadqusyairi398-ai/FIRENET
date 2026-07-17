<?php
session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
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

/* ========== MENU HAMBURGER (GARIS 3) ========== */
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

/* ========== SIDE MENU (SLIDE FROM RIGHT) ========== */
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

/* Header Side Menu */
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

/* Menu Items */
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

/* ========== DROPDOWN MENU DI SIDE MENU ========== */
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

/* ========== NAV MENU UTAMA (DESKTOP) - SEMBUNYIKAN ========== */
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

/* Floating Search Bar */
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

/* Info koordinat di bawah peta */
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

.side-menu-items a:nth-child(1) { animation-delay: 0.05s; }  /* Beranda */
.side-dropdown { animation-delay: 0.10s; }                   /* Alat */
.side-dropdown .dropbtn { animation-delay: 0.10s; }
.side-menu-items a:nth-child(3) { animation-delay: 0.15s; }  /* Portofolio */
.side-menu-items a:nth-child(4) { animation-delay: 0.20s; }  /* Dashboard Indoor */
.side-menu-items a:nth-child(5) { animation-delay: 0.25s; }  /* Dashboard Outdoor */

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
    
    <!-- IKON HAMBURGER (GARIS 3) -->
    <div class="menu-toggle" id="menuToggle" onclick="openSideMenu()">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Menu Desktop - SEMBUNYIKAN -->
    <div class="nav-menu">
        <a href="home.php">Beranda</a>
        <a href="portofolio.php" class="active">Portofolio</a>
    </div>
</nav>

<!-- ========== SIDE MENU OVERLAY ========== -->
<div class="side-menu-overlay" id="sideMenuOverlay" onclick="closeSideMenu()"></div>

<!-- ========== SIDE MENU (SLIDE FROM RIGHT) ========== -->
<div class="side-menu" id="sideMenu">
    <!-- Header -->
    <div class="side-menu-header">
        <div class="logo-side">
            <i class="fas fa-fire"></i>
            <span>FIRE<span>DETECTOR</span></span>
        </div>
        <button class="side-menu-close" onclick="closeSideMenu()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Menu Items -->
    <div class="side-menu-items">
        <!-- Menu Label -->
        <div class="menu-label">Menu Utama</div>
        
        <!-- ===== 1. BERANDA ===== -->
        <a href="home.php">
            <i class="fas fa-home"></i>
            Beranda
        </a>
        
        <!-- ===== 2. ALAT (DROPDOWN) ===== -->
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
        
        <!-- ===== 3. PORTOFOLIO ===== -->
        <a href="portofolio.php" class="active">
            <i class="fas fa-briefcase"></i>
            Portofolio
        </a>
        
        <!-- ===== 4. DASHBOARD INDOOR ===== -->
        <a href="login.php?redirect=indoor">
            <i class="fas fa-building"></i>
            Dashboard Indoor
            <span class="badge">Login</span>
        </a>
        
        <!-- ===== 5. DASHBOARD OUTDOOR ===== -->
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
        <p>Koordinat pemasangan alat deteksi kebakaran</p>
    </div>
    <div class="maps-container">
        <!-- Floating Search Bar -->
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
    <!-- Menampilkan nama lokasi dan koordinat di bawah peta -->
    <div class="location-info">
        <div class="location-info-item">
            <i class="fas fa-map-marker-alt"></i>
            <span class="label">Lokasi:</span>
            <span class="value" id="locationName">-</span>
        </div>
        <div class="location-info-item">
            <i class="fas fa-globe"></i>
            <span class="label">Koordinat:</span>
            <span class="value" id="coordValue">-1.202490, 116.887080</span>
        </div>
    </div>
</section>

<!-- ========== FOOTER ========== -->
<div class="footer">
    <p>&copy; <?= date('Y') ?> <strong>FIREDETECTOR</strong> - Smart Monitoring Solution</p>
    <p>Dibangun dengan <i class="fas fa-heart" style="color: #e85d04;"></i> untuk keselamatan</p>
</div>

<script>
// ========== OPEN SIDE MENU ==========
function openSideMenu() {
    const overlay = document.getElementById('sideMenuOverlay');
    const menu = document.getElementById('sideMenu');
    
    overlay.classList.add('active');
    menu.classList.add('open');
    document.body.style.overflow = 'hidden';
}

// ========== CLOSE SIDE MENU ==========
function closeSideMenu() {
    const overlay = document.getElementById('sideMenuOverlay');
    const menu = document.getElementById('sideMenu');
    
    overlay.classList.remove('active');
    menu.classList.remove('open');
    document.body.style.overflow = '';
}

// ========== TOGGLE DROPDOWN ==========
function toggleDropdown(event) {
    event.stopPropagation();
    const btn = event.currentTarget;
    const content = btn.nextElementSibling;
    const arrow = btn.querySelector('.arrow i');
    
    content.classList.toggle('open');
    
    if (content.classList.contains('open')) {
        arrow.style.transform = 'rotate(180deg)';
    } else {
        arrow.style.transform = 'rotate(0deg)';
    }
}

// ========== TUTUP SIDE MENU DENGAN ESC ==========
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSideMenu();
    }
});

// ========== TUTUP DROPDOWN SAAT KLIK DI LUAR ==========
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.side-dropdown');
    dropdowns.forEach(function(dropdown) {
        if (!dropdown.contains(event.target)) {
            const content = dropdown.querySelector('.dropdown-content');
            const arrow = dropdown.querySelector('.arrow i');
            if (content) {
                content.classList.remove('open');
                if (arrow) {
                    arrow.style.transform = 'rotate(0deg)';
                }
            }
        }
    });
});

// ========== MAPS ==========
// Inisialisasi peta
var defaultLat = -1.20249;
var defaultLng = 116.88708;
var map = L.map('map', {
    zoomControl: false
}).setView([defaultLat, defaultLng], 10);

L.control.zoom({ position: 'bottomright' }).addTo(map);

// Tile layer
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

// Scale bar
L.control.scale({ metric: true, imperial: false }).addTo(map);

// Icon marker
var fireIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #e85d04, #dc2f02); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-fire" style="color: white; font-size: 18px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'fire-marker'
});

// ========== DATA LOKASI LENGKAP ==========
var locations = [
    // ===== POLITEKNIK NEGERI BALIKPAPAN (3 titik) =====
    {
        "id": 1,
        "nama_lokasi": "Politeknik Negeri Balikpapan - Kampus Utama",
        "latitude": -1.20249,
        "longitude": 116.88708
    },
    {
        "id": 2,
        "nama_lokasi": "Gedung A - Kampus Utama",
        "latitude": -1.20300,
        "longitude": 116.88750
    },
    {
        "id": 3,
        "nama_lokasi": "Laboratorium Komputer - Kampus Utama",
        "latitude": -1.20200,
        "longitude": 116.88680
    },
    
    // ===== PENAJAM PASER UTARA (5 titik - DI SEBERANG BALIKPAPAN) =====
    {
        "id": 4,
        "nama_lokasi": "Nipah-Nipah, Penajam - Kecamatan Penajam",
        "latitude": -0.3250,
        "longitude": 116.5980
    },
    {
        "id": 5,
        "nama_lokasi": "Penajam - Pusat Kota Penajam",
        "latitude": -0.3300,
        "longitude": 116.6020
    },
    {
        "id": 6,
        "nama_lokasi": "Penajam - Kawasan Industri (Nipah-Nipah)",
        "latitude": -0.3200,
        "longitude": 116.5950
    },
    {
        "id": 7,
        "nama_lokasi": "Penajam - Pelabuhan Penajam",
        "latitude": -0.3350,
        "longitude": 116.6000
    },
    {
        "id": 8,
        "nama_lokasi": "Penajam - Kawasan Perumahan",
        "latitude": -0.3280,
        "longitude": 116.6100
    },
    
    // ===== SAMARINDA (2 titik) =====
    {
        "id": 9,
        "nama_lokasi": "Samarinda - Pusat Kota",
        "latitude": -0.5022,
        "longitude": 117.1535
    },
    {
        "id": 10,
        "nama_lokasi": "Samarinda - Kawasan Bisnis",
        "latitude": -0.4980,
        "longitude": 117.1580
    }
];

var markersMap = {};

function selectLocation(id, panTo) {
    if (panTo === undefined) {
        panTo = true;
    }
    var item = markersMap[id];
    if (!item) return;
    
    // Update info panel
    document.getElementById('locationName').innerText = item.data.nama_lokasi;
    document.getElementById('coordValue').innerText = item.data.latitude.toFixed(6) + ', ' + item.data.longitude.toFixed(6);
    
    if (panTo) {
        map.flyTo([item.data.latitude, item.data.longitude], 17, {
            animate: true,
            duration: 1.5
        });
        
        // Buka popup setelah animasi terbang selesai
        setTimeout(function() {
            item.marker.openPopup();
        }, 1500);
    } else {
        item.marker.openPopup();
    }
}

function renderMarkers() {
    // Bersihkan marker lama jika ada
    for (var key in markersMap) {
        map.removeLayer(markersMap[key].marker);
        map.removeLayer(markersMap[key].circle);
    }
    markersMap = {};

    locations.forEach(function(loc) {
        // Create marker
        var marker = L.marker([loc.latitude, loc.longitude], {
            icon: fireIcon
        }).addTo(map);
        
        // Create danger circle
        var circle = L.circle([loc.latitude, loc.longitude], {
            color: '#e85d04',
            fillColor: '#e85d04',
            fillOpacity: 0.15,
            radius: 500
        }).addTo(map);
        
        // Popup
        var popupContent = `
            <div style="min-width: 200px; font-family: 'Poppins', sans-serif; text-align: center;">
                <i class="fas fa-map-marker-alt" style="color: #e85d04; font-size: 18px; margin-bottom: 5px;"></i>
                <div style="font-weight: 600; font-size: 14px;">${loc.nama_lokasi}</div>
                <div style="font-size: 13px; background: #f0f0f0; padding: 5px; border-radius: 8px; margin-top: 5px;">
                    ${loc.latitude.toFixed(6)}, ${loc.longitude.toFixed(6)}
                </div>
            </div>
        `;
        marker.bindPopup(popupContent);
        
        markersMap[loc.id] = {
            marker: marker,
            circle: circle,
            data: loc
        };
        
        marker.on('click', function() {
            selectLocation(loc.id, false);
        });
    });
}

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
        return loc.nama_lokasi.toLowerCase().includes(filter);
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
                <span class="loc-name">${loc.nama_lokasi}</span>
                <span class="loc-coords">${loc.latitude.toFixed(6)}, ${loc.longitude.toFixed(6)}</span>
            </div>
        `;
        div.onclick = function() {
            input.value = loc.nama_lokasi;
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

// Tutup search results saat klik diluar
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
    // Zoom ke area yang mencakup semua lokasi (Balikpapan, Penajam, Samarinda)
    var bounds = L.latLngBounds([
        [-1.22, 116.88],  // Balikpapan
        [-0.31, 116.59],  // Penajam (Nipah-Nipah)
        [-0.50, 117.16]   // Samarinda
    ]);
    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });
    
    if (locations.length > 0) {
        selectLocation(locations[0].id, false);
    }
}

// Jalankan saat halaman dimuat
window.addEventListener('DOMContentLoaded', initMap);
</script>

</body>
</html>