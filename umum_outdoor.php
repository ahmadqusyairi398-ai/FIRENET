<?php
session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Outdoor - FIREDETECTOR</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ========== STYLE ========== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    background-image: url('https://i.pinimg.com/736x/ea/7c/ca/ea7cca792d193c0a4599fbcf96f21fa3.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    position: relative;
}
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: -1;
}
.sidebar {
    width: 250px;
    height: 100vh;
    background: linear-gradient(135deg, rgba(30, 60, 114, 0.9), rgba(42, 82, 152, 0.9));
    padding: 20px 15px;
    position: sticky;
    top: 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    z-index: 1;
}
.sidebar h3 {
    color: white;
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(255,255,255,0.3);
}
.menu-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 10px 0;
    padding: 12px 15px;
    border-radius: 10px;
    background: rgba(255,255,255,0.15);
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}
.menu-btn i { width: 24px; font-size: 18px; }
.menu-btn:hover { background: rgba(255,255,255,0.3); transform: translateX(5px); }
.menu-btn.active { background: linear-gradient(135deg, #00b4db, #0083b0); }
.logout { margin-top: 40px; background: rgba(220, 53, 69, 0.8); }
.logout:hover { background: #dc3545; }
.main {
    flex: 1;
    padding: 20px 30px;
    overflow-y: auto;
    height: 100vh;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 15px 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.header h2 { color: #1e3c72; font-size: 24px; }
.header-right { display: flex; align-items: center; gap: 15px; }
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    padding: 8px 20px;
    border-radius: 50px;
    color: white;
    font-weight: bold;
}
.btn-home-header {
    background: rgba(34, 6, 244, 0.2);
    color: white;
    border: none;
    padding: 8px 18px;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-home-header:hover { background: rgba(255, 255, 255, 0.45); transform: translateY(-2px); }
.card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.card:hover { transform: translateY(-2px); }
.card h3 {
    color: #1e3c72;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}
.card h3 i { color: #00b4db; }
.node-status {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 10px;
}
.status-item {
    background: rgba(248, 249, 250, 0.8);
    padding: 12px;
    border-radius: 10px;
    text-align: center;
}
.status-item i { font-size: 24px; margin-bottom: 8px; display: block; }
.status-item .label { font-size: 12px; color: #555; margin-bottom: 5px; }
.status-item .value { font-size: 18px; font-weight: bold; color: #1e3c72; }
.status-online { color: #28a745; }
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.box {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    color: white;
    transition: transform 0.2s;
    backdrop-filter: blur(5px);
}
.box:hover { transform: scale(1.02); }
.box i { font-size: 32px; margin-bottom: 10px; display: block; }
.box .sensor-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
.box b { display: block; font-size: 20px; margin-top: 5px; }
.box small { display: block; font-size: 11px; opacity: 0.8; margin-top: 2px; }
.box.solar-box { background: linear-gradient(135deg, rgba(255, 193, 7, 0.9), rgba(255, 107, 0, 0.9)); }
.box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); }
.box.co-box { background: linear-gradient(135deg, rgba(156, 39, 176, 0.9), rgba(103, 58, 183, 0.9)); }
.box.angin-box { background: linear-gradient(135deg, rgba(33, 150, 243, 0.9), rgba(25, 118, 210, 0.9)); }
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); }
}
.pulse-animation { animation: pulse 1s ease-in-out infinite; }
.status-aman { color: #28a745; font-weight: bold; }
.status-bahaya { color: #dc3545; font-weight: bold; animation: blink 1s infinite; }
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.map-container {
    margin-top: 10px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(224, 224, 224, 0.5);
}
#map {
    height: 400px;
    width: 100%;
    border-radius: 12px;
    z-index: 1;
}
.location-info {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 15px;
    padding: 15px;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 12px;
    flex-wrap: wrap;
}
.location-info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}
.location-info-item i { font-size: 18px; color: #e85d04; }
.location-info-item .label { color: #555; }
.location-info-item .value { font-weight: 600; color: #1e3c72; }
.chart-container { margin-top: 10px; }
canvas {
    max-height: 400px;
    width: 100%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    padding: 10px;
}
@media (max-width: 768px) {
    .sidebar { width: 80px; padding: 20px 10px; }
    .sidebar h3 { font-size: 12px; }
    .menu-btn span { display: none; }
    .menu-btn i { margin: 0; }
    .main { padding: 15px; }
    .grid { grid-template-columns: repeat(2, 1fr); }
    .node-status { grid-template-columns: 1fr; }
    #map { height: 300px; }
    .location-info { flex-direction: column; align-items: flex-start; gap: 10px; }
    .header-right { flex-direction: column; gap: 8px; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-tree"></i> Outdoor</h3>
    <a href="umum_outdoor.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard Outdoor</span>
    </a>
    <a href="home.php" class="menu-btn logout">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="header">
        <h2><i class="fas fa-tree"></i> Dashboard Monitoring Outdoor</h2>
        <div class="header-right">
            <!-- BUTTON HOME DIHAPUS -->
            <div class="user-info"><i class="fas fa-user-circle"></i><span>Halo <?= htmlspecialchars($user) ?></span></div>
        </div>
    </div>

    <!-- NODE STATUS -->
    <div class="card">
        <h3><i class="fas fa-microchip"></i> Status Node Outdoor</h3>
        <div class="node-status">
            <div class="status-item"><i class="fas fa-circle status-online"></i><div class="label">Status</div><div class="value" id="status">-</div></div>
            <div class="status-item"><i class="fas fa-signal"></i><div class="label">RSSI</div><div class="value" id="rssi">-</div></div>
            <div class="status-item"><i class="fas fa-network-wired"></i><div class="label">IP Address</div><div class="value" id="ip">-</div></div>
        </div>
    </div>

    <!-- LOKASI / MAP CARD -->
    <div class="card">
        <h3><i class="fas fa-map-marker-alt"></i> Lokasi Alat (Outdoor) <span style="font-size: 12px; color: #666; margin-left: auto;">Area Terbuka</span></h3>
        <div class="map-container"><div id="map"></div></div>
        <div class="location-info">
            <div class="location-info-item">
                <i class="fas fa-globe"></i>
                <span class="label">Koordinat:</span>
                <span class="value" id="coordinates">-1.202490, 116.887080</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-tree"></i>
                <span class="label">Zona:</span>
                <span class="value" id="zone">Zona Outdoor (Area Terbuka)</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-flag-checkered"></i>
                <span class="label">Status:</span>
                <span class="value" id="location-status" style="color: #28a745;">Aman</span>
            </div>
        </div>
    </div>

    <!-- SENSOR DATA - TANPA SENSOR API -->
    <div class="card">
        <h3><i class="fas fa-solar-panel"></i> Data Sensor Outdoor <span id="waktu" style="font-size:12px; color:#666;">-</span></h3>
        <div class="grid">
            <!-- Solar Panel Sensors -->
            <div class="box solar-box"><i class="fas fa-bolt"></i><div class="sensor-label">Tegangan Panel Surya</div><b id="tegangan">-</b><small>V DC</small></div>
            <div class="box solar-box"><i class="fas fa-charging-station"></i><div class="sensor-label">Arus Panel Surya</div><b id="arus">-</b><small>A DC</small></div>
            <div class="box solar-box"><i class="fas fa-solar-panel"></i><div class="sensor-label">Daya Panel Surya</div><b id="daya">-</b><small>Watt</small></div>
            
            <!-- Wind Sensors -->
            <div class="box angin-box"><i class="fas fa-compass"></i><div class="sensor-label">Arah Angin</div><b id="arah">-</b></div>
            <div class="box angin-box"><i class="fas fa-wind"></i><div class="sensor-label">Kecepatan Angin</div><b id="kecepatan_angin">-</b></div>
            
            <!-- Asap Sensor (API dihapus) -->
            <div class="box asap-box" id="asap-box"><i class="fas fa-smog"></i><div class="sensor-label">Asap</div><b id="asap">-</b></div>
            
            <!-- Environment Sensors -->
            <div class="box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Suhu</div><b id="suhu">-</b></div>
            <div class="box"><i class="fas fa-tint"></i><div class="sensor-label">Kelembapan</div><b id="kelembapan">-</b></div>
            
            <!-- Gas Sensor -->
            <div class="box co-box" id="co-box"><i class="fas fa-industry"></i><div class="sensor-label">Gas CO</div><b id="co">-</b></div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-tree" style="color: #0083b0;"></i>
            <span style="color: #1e3c72; font-size: 13px;"><strong>Monitoring Outdoor</strong> - Sensor terpasang di area terbuka untuk deteksi dini kebakaran hutan/lahan.</span>
        </div>
    </div>

    <!-- CHART -->
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> Grafik Real Time Sensor</h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>
</div>

<script>
// ================= KOORDINAT STATIS (1 LOKASI) =================
var fixedLat = -1.20249;
var fixedLng = 116.88708;

// Inisialisasi peta
var map = L.map('map').setView([fixedLat, fixedLng], 15);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

// Icon marker Outdoor - AMAN (Hijau)
var safeIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #2e7d32, #1b5e20); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-tree" style="color: white; font-size: 18px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'outdoor-marker'
});

// Icon marker Outdoor - BAHAYA (Merah)
var dangerIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #dc2626, #b91c1c); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; animation: blink 1s infinite;"><i class="fas fa-fire" style="color: white; font-size: 18px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'outdoor-marker'
});

// Marker awal dengan icon aman
var sensorMarker = L.marker([fixedLat, fixedLng], { icon: safeIcon, draggable: false }).addTo(map);

// POPUP
sensorMarker.bindPopup(`
    <b>🌳 Outdoor Sensor</b><br>
    <i class="fas fa-map-marker-alt"></i> Koordinat: ${fixedLat}, ${fixedLng}<br>
    Status: <span style="color: #28a745;">Aktif - Normal</span>
`).openPopup();

// Circle zone - AMAN (Hijau)
var dangerZone = L.circle([fixedLat, fixedLng], {
    color: '#2e7d32',
    fillColor: '#2e7d32',
    fillOpacity: 0.15,
    radius: 500
}).addTo(map);

L.control.scale({ metric: true, imperial: false }).addTo(map);

function updateLocationStatus(isDanger) {
    if (isDanger) {
        // Mode BAHAYA - Merah
        dangerZone.setStyle({ color: '#dc2626', fillColor: '#dc2626', fillOpacity: 0.3 });
        document.getElementById('location-status').innerHTML = '⚠️ BAHAYA - Deteksi Kebakaran!';
        document.getElementById('location-status').style.color = '#dc2626';
        document.getElementById('zone').innerHTML = 'Zona Merah (Peringatan Bahaya)';
        
        // Ganti marker ke icon bahaya
        sensorMarker.setIcon(dangerIcon);
        
        sensorMarker.bindPopup(`
            <b>🔥 PERINGATAN KEBAKARAN!</b><br>
            <i class="fas fa-map-marker-alt"></i> Koordinat: ${fixedLat}, ${fixedLng}<br>
            Status: <span style="color: #dc2626;">BAHAYA - Deteksi Kebakaran!</span>
        `).openPopup();
    } else {
        // Mode AMAN - Hijau
        dangerZone.setStyle({ color: '#2e7d32', fillColor: '#2e7d32', fillOpacity: 0.15 });
        document.getElementById('location-status').innerHTML = 'Aman';
        document.getElementById('location-status').style.color = '#28a745';
        document.getElementById('zone').innerHTML = 'Zona Outdoor (Area Terbuka)';
        
        // Ganti marker ke icon aman
        sensorMarker.setIcon(safeIcon);
        
        sensorMarker.bindPopup(`
            <b>🌳 Outdoor Sensor</b><br>
            <i class="fas fa-map-marker-alt"></i> Koordinat: ${fixedLat}, ${fixedLng}<br>
            Status: <span style="color: #28a745;">Aktif - Normal</span>
        `).openPopup();
    }
}

// ================= CHART =================
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = { 
    labels: [], 
    datasets: [
        { label: 'Tegangan Panel Surya (V)', data: [], borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Arus Panel Surya (A)', data: [], borderColor: '#ff8c00', backgroundColor: 'rgba(255,140,0,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Daya Panel Surya (W)', data: [], borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Suhu (°C)', data: [], borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kelembapan (%)', data: [], borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kecepatan Angin (m/s)', data: [], borderColor: '#3399ff', backgroundColor: 'rgba(51,153,255,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'CO (ppm)', data: [], borderColor: '#aa96da', backgroundColor: 'rgba(170,150,218,0.1)', borderWidth: 2, tension: 0.4, fill: true }
    ] 
};

const myChart = new Chart(ctx, { 
    type: 'line', 
    data: dataChart, 
    options: { 
        responsive: true, 
        maintainAspectRatio: true, 
        animation: { duration: 500 }, 
        plugins: { 
            legend: { position: 'top' }, 
            tooltip: { 
                mode: 'index', 
                intersect: false,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let value = context.raw;
                        let unit = '';
                        if (label.includes('Tegangan')) unit = ' V';
                        else if (label.includes('Arus')) unit = ' A';
                        else if (label.includes('Daya')) unit = ' W';
                        else if (label.includes('Suhu')) unit = ' °C';
                        else if (label.includes('Kelembapan')) unit = ' %';
                        else if (label.includes('Angin')) unit = ' m/s';
                        else if (label.includes('CO')) unit = ' ppm';
                        return `${label}: ${value}${unit}`;
                    }
                }
            } 
        }, 
        scales: { 
            y: { 
                beginAtZero: true, 
                grid: { color: 'rgba(0,0,0,0.05)' }, 
                title: { display: true, text: 'Nilai Sensor' } 
            }, 
            x: { 
                grid: { display: false }, 
                title: { display: true, text: 'Waktu' } 
            } 
        } 
    } 
});

// ================= GENERATE DATA =================
function generateSolarData() {
    var hour = new Date().getHours();
    var teganganBase = (hour >= 6 && hour <= 18) ? 12 + (Math.sin((hour - 12) * Math.PI / 12) + 1) * 6 : Math.random() * 2;
    var tegangan = Math.max(0, teganganBase + (Math.random() - 0.5) * 1.5).toFixed(1);
    var arusBase = teganganBase / 2.5;
    var arus = Math.max(0, arusBase + (Math.random() - 0.5) * 1).toFixed(2);
    var daya = (parseFloat(tegangan) * parseFloat(arus)).toFixed(1);
    var arahArray = ['Utara', 'Timur', 'Selatan', 'Barat', 'Timur Laut', 'Barat Daya', 'Tenggara', 'Barat Laut'];
    var arah = arahArray[Math.floor(Math.random() * arahArray.length)];
    var asapStatus = Math.random() > 0.85 ? "Tinggi" : "Normal";
    var co = Math.floor(Math.random() * 50 + 10);
    
    // Jika asap terdeteksi, tingkatkan CO
    if (asapStatus === "Tinggi") {
        co = Math.floor(Math.random() * 100 + 50);
    }
    
    // Jika asap tinggi, suhu naik, kelembapan turun
    var suhu = (Math.random() * 35 + 20).toFixed(1);
    var kelembapan = (Math.random() * 60 + 40).toFixed(1);
    
    if (asapStatus === "Tinggi") {
        suhu = (Math.random() * 30 + 40).toFixed(1);
        kelembapan = (Math.random() * 30 + 20).toFixed(1);
    }
    
    var angin = (Math.random() * 20 + 5).toFixed(1);
    
    // Deteksi bahaya jika asap tinggi atau CO > 50
    var isDanger = (asapStatus === "Tinggi" || co > 50);
    
    return {
        waktu: new Date().toLocaleTimeString(),
        tegangan: tegangan,
        arus: arus,
        daya: daya,
        arah: arah,
        asap: asapStatus,
        suhu: suhu,
        kelembapan: kelembapan,
        angin: angin,
        co: co,
        status: 'Online',
        rssi: Math.floor(Math.random() * 40 + -80),
        ip: '192.168.' + Math.floor(Math.random() * 255) + '.' + Math.floor(Math.random() * 255),
        isDanger: isDanger
    };
}

// ================= UPDATE DATA =================
setInterval(() => {
    let data = generateSolarData();
    
    // Update status node
    document.getElementById("status").innerHTML = `<i class="fas fa-circle status-online"></i> ${data.status}`;
    document.getElementById("rssi").innerHTML = `${data.rssi} dBm`;
    document.getElementById("ip").innerHTML = data.ip;
    document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${data.waktu}`;
    
    // Update solar panel data
    document.getElementById("tegangan").innerHTML = `${data.tegangan} V`;
    document.getElementById("arus").innerHTML = `${data.arus} A`;
    document.getElementById("daya").innerHTML = `${data.daya} W`;
    
    // Update wind data
    var arahIcon = {
        'Utara': 'up',
        'Selatan': 'down',
        'Timur': 'right',
        'Barat': 'left',
        'Timur Laut': 'up-right',
        'Barat Daya': 'down-left',
        'Tenggara': 'down-right',
        'Barat Laut': 'up-left'
    };
    document.getElementById("arah").innerHTML = `<i class="fas fa-arrow-${arahIcon[data.arah] || 'right'}"></i> ${data.arah}`;
    document.getElementById("kecepatan_angin").innerHTML = `${data.angin} m/s <i class="fas fa-wind"></i>`;
    
    // Update Asap status
    var asapElement = document.getElementById("asap");
    var asapBox = document.getElementById('asap-box');
    if (data.asap === "Tinggi") {
        asapElement.innerHTML = '<i class="fas fa-chart-line"></i> Tinggi (Berbahaya)';
        asapElement.className = 'status-bahaya';
        asapBox.classList.add('pulse-animation');
        asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
    } else {
        asapElement.innerHTML = '<i class="fas fa-check"></i> Normal';
        asapElement.className = 'status-aman';
        asapBox.classList.remove('pulse-animation');
        asapBox.style.background = "linear-gradient(135deg, rgba(255,165,2,0.9), rgba(255,99,72,0.9))";
    }
    
    // Update CO status
    var coElement = document.getElementById("co");
    var coBox = document.getElementById('co-box');
    var coValue = data.co;
    if (coValue > 50) {
        coElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${coValue} ppm (BAHAYA!)`;
        coElement.className = 'status-bahaya';
        coBox.classList.add('pulse-animation');
        coBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
    } else if (coValue > 35) {
        coElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${coValue} ppm (Waspada)`;
        coElement.className = 'status-bahaya';
        coBox.classList.remove('pulse-animation');
        coBox.style.background = "linear-gradient(135deg, rgba(255,165,2,0.9), rgba(255,99,72,0.9))";
    } else {
        coElement.innerHTML = `${coValue} ppm <i class="fas fa-industry"></i>`;
        coElement.className = 'status-aman';
        coBox.classList.remove('pulse-animation');
        coBox.style.background = "linear-gradient(135deg, rgba(156,39,176,0.9), rgba(103,58,183,0.9))";
    }
    
    // Update environment data
    document.getElementById("suhu").innerHTML = `${data.suhu} °C <i class="fas fa-thermometer-half"></i>`;
    document.getElementById("kelembapan").innerHTML = `${data.kelembapan} % <i class="fas fa-tint"></i>`;
    
    // Update location status - HANYA 1 LOKASI
    if (data.isDanger) {
        updateLocationStatus(true);
    } else {
        updateLocationStatus(false);
    }
    
    // Update chart
    dataChart.labels.push(data.waktu);
    dataChart.datasets[0].data.push(parseFloat(data.tegangan));
    dataChart.datasets[1].data.push(parseFloat(data.arus));
    dataChart.datasets[2].data.push(parseFloat(data.daya));
    dataChart.datasets[3].data.push(parseFloat(data.suhu));
    dataChart.datasets[4].data.push(parseFloat(data.kelembapan));
    dataChart.datasets[5].data.push(parseFloat(data.angin));
    dataChart.datasets[6].data.push(data.co);
    
    if(dataChart.labels.length > 20) { 
        dataChart.labels.shift(); 
        dataChart.datasets.forEach(ds => ds.data.shift()); 
    }
    myChart.update();
}, 3000);

document.getElementById('coordinates').innerHTML = `${fixedLat}, ${fixedLng}`;
</script>
</body>
</html>