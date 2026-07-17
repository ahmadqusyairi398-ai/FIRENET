<?php
session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FIREDETECTOR - Smart Monitoring Solution</title>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
    background-image: url('https://i.pinimg.com/1200x/eb/1a/58/eb1a58bec150c7bcbb0e1332cdabc697.jpg');
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
    background: rgba(0, 0, 0, 0.55);
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

/* ========== HERO SECTION ========== */
.hero {
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(8px);
    padding: 100px 50px;
    text-align: center;
    margin: 30px 50px;
    border-radius: 20px;
}

.hero h2 {
    font-size: 48px;
    font-weight: 800;
    background: linear-gradient(135deg, #e85d04, #f48c06);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 20px;
}

.hero p {
    font-size: 18px;
    color: #f0f0f0;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
}

/* ========== FEATURES SECTION ========== */
.features {
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(10px);
    padding: 60px 50px;
    color: white;
    margin: 30px 50px;
    border-radius: 20px;
}

.features-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: center;
    gap: 80px;
    flex-wrap: wrap;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 18px;
    font-weight: 500;
}

.feature-item i {
    font-size: 32px;
    color: #e85d04;
}

/* ========== CHAT BUTTON ========== */
.chat-button {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.chat-button a {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: white;
    padding: 15px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    transition: all 0.3s;
}

.chat-button a:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.chat-button i {
    font-size: 24px;
}

/* ========== FOOTER ========== */
.footer {
    background: rgba(20, 15, 12, 0.95);
    backdrop-filter: blur(10px);
    color: #aaa;
    padding: 40px 50px;
    text-align: center;
    margin-top: 30px;
}

.footer p {
    margin: 10px 0;
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
    
    .hero {
        padding: 50px 20px;
        margin: 20px;
    }
    
    .hero h2 {
        font-size: 32px;
    }
    
    .hero p {
        font-size: 16px;
    }
    
    .features {
        padding: 40px 20px;
        margin: 20px;
    }
    
    .features-container {
        gap: 30px;
        flex-direction: column;
        align-items: center;
    }
    
    .feature-item {
        font-size: 16px;
    }
    
    .chat-button a span {
        display: none;
    }
    
    .chat-button a {
        padding: 15px;
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
    
    .hero h2 {
        font-size: 26px;
    }
    
    .hero p {
        font-size: 14px;
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
        <a href="home.php" class="active">Beranda</a>
        <a href="portofolio.php">Portofolio</a>
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
        <a href="home.php" class="active">
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
        <a href="portofolio.php">
            <i class="fas fa-map-marked-alt"></i>
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

<!-- ========== HERO SECTION ========== -->
<section class="hero">
    <h2>Deteksi Dini Kebakaran</h2>
    <p>Optimalkan keamanan dan kualitas lingkungan Anda dengan sistem deteksi dini kebakaran dan pemantauan lingkungan yang andal! Pantau kondisi secara real-time melalui berbagai sensor canggih seperti suhu, kelembapan, asap, api, tegangan, hingga arus listrik. Solusi terintegrasi ini dirancang untuk memberikan peringatan cepat dan akurat terhadap potensi kebakaran, sekaligus memantau kondisi lingkungan sekitar secara berkelanjutan.</p>
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
</script>

</body>
</html>