<?php
// Mulai session untuk user
session_start();

// Jika tipe dashboard adalah indoor, alihkan ke dashboard_user_indoor.php
if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'indoor') {
    header("Location: dashboard_user_indoor.php");
    exit();
}
$_SESSION['dashboard_type'] = 'outdoor';

// Proteksi: Jika belum login, redirect ke halaman login
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard User - Fire Detection</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ========== STYLE ========== */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    background-image: url('https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    position: relative;
}
body::before {
    content: '';
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
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
    cursor: pointer;
    border: none;
    width: 100%;
    font-size: 14px;
}
.menu-btn i { width: 24px; font-size: 18px; }
.menu-btn:hover { background: rgba(255,255,255,0.3); transform: translateX(5px); }
.menu-btn.active { background: linear-gradient(135deg, #00b4db, #0083b0); }
.logout { margin-top: 40px; background: rgba(220, 53, 69, 0.8); }
.logout:hover { background: #dc3545; }
.user-badge {
    background: linear-gradient(135deg, #28a745, #20c997);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    margin-left: auto;
}
.main { flex: 1; padding: 20px 30px; overflow-y: auto; height: 100vh; }

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 12px 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    flex-wrap: wrap;
    gap: 10px;
}
.header-left { display: flex; align-items: center; gap: 15px; }
.header-left h2 { color: #1e3c72; font-size: 20px; }
.header-left h2 i { color: #e85d04; }

.node-status-header {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(0, 0, 0, 0.05);
    padding: 5px 15px;
    border-radius: 50px;
    flex-wrap: wrap;
}
.status-item-header { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #555; }
.status-item-header .value { font-weight: 600; color: #1e3c72; font-size: 12px; }
.status-online { color: #28a745; }

.header-right { display: flex; align-items: center; gap: 12px; }
.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #28a745, #20c997);
    padding: 6px 16px;
    border-radius: 50px;
    color: white;
    font-weight: bold;
    font-size: 13px;
}
.user-tag { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 20px; font-size: 9px; margin-left: 5px; }
.btn-home-header {
    background: rgba(34, 6, 244, 0.15); color: #1e3c72; border: none; padding: 6px 14px;
    border-radius: 50px; cursor: pointer; font-weight: 600; font-size: 12px; transition: all 0.3s;
    text-decoration: none; display: flex; align-items: center; gap: 6px;
}
.btn-home-header:hover { background: rgba(34, 6, 244, 0.3); transform: translateY(-2px); }

.card {
    background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
    border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.card h3 {
    color: #1e3c72; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid rgba(0,0,0,0.1);
    display: flex; align-items: center; gap: 10px;
}

.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.box {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
    padding: 20px; border-radius: 12px; text-align: center; color: white; backdrop-filter: blur(5px);
}
.box i { font-size: 32px; margin-bottom: 10px; display: block; }
.box .sensor-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
.box b { display: block; font-size: 20px; margin-top: 5px; }
.box small { display: block; font-size: 11px; opacity: 0.8; margin-top: 2px; }
.box.solar-box { background: linear-gradient(135deg, rgba(255, 193, 7, 0.9), rgba(255, 107, 0, 0.9)); }
.box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); }
.box.co-box { background: linear-gradient(135deg, rgba(156, 39, 176, 0.9), rgba(103, 58, 183, 0.9)); }
.box.angin-box { background: linear-gradient(135deg, rgba(33, 150, 243, 0.9), rgba(25, 118, 210, 0.9)); }

@keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); } }
.pulse-animation { animation: pulse 1s ease-in-out infinite; }
.status-aman { color: #28a745; font-weight: bold; }
.status-bahaya { color: #dc3545; font-weight: bold; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

.map-container { margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid rgba(224, 224, 224, 0.5); }
#map { height: 350px; width: 100%; border-radius: 12px; z-index: 1; }
.location-info { display: flex; align-items: center; gap: 20px; margin-top: 15px; padding: 15px; background: rgba(248, 249, 250, 0.8); border-radius: 12px; flex-wrap: wrap; }
.location-info-item { display: flex; align-items: center; gap: 10px; font-size: 14px; }
.location-info-item i { font-size: 18px; color: #dc2626; }
.location-info-item .value { font-weight: 600; color: #1e3c72; }

/* MODAL LOGOUT */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 9999; justify-content: center; align-items: center; }
.modal-box { background: #ffffff; border-radius: 20px; padding: 40px 35px 30px; max-width: 400px; width: 90%; text-align: center; }
.modal-icon { font-size: 48px; color: #dc3545; background: rgba(220, 53, 69, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.modal-buttons { display: flex; gap: 12px; justify-content: center; }
.btn-modal { padding: 12px 35px; border-radius: 50px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; }
.btn-cancel { background: #e9ecef; color: #495057; }
.btn-logout-confirm { background: #dc3545; color: white; }
</style>
</head>
<body>

<div class="sidebar">
    <h3><i class="fas fa-fire"></i> FireDetector</h3>
    <a href="dashboard_user.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
        <span class="user-badge">USER</span>
    </a>
    <a href="chart.php" class="menu-btn">
        <i class="fas fa-chart-line"></i>
        <span>CHART</span>
    </a>
    <a href="tabel.php" class="menu-btn">
        <i class="fas fa-table"></i>
        <span>TABEL</span>
    </a>
    <button class="menu-btn logout" onclick="openLogoutModal()">
        <i class="fas fa-sign-out-alt"></i>
        <span>LOGOUT</span>
    </button>
</div>

<div class="main">
    <div class="header">
        <div class="header-left">
            <h2><i class="fas fa-fire-extinguisher"></i> Dashboard Monitoring</h2>
            <div class="node-status-header">
                <div class="status-item-header">
                    <i class="fas fa-circle status-online"></i>
                    <span>Status:</span>
                    <span class="value" id="status">-</span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-signal"></i>
                    <span>RSSI:</span>
                    <span class="value" id="rssi">-</span>
                </div>
                <div class="status-item-header">
                    <i class="fas fa-network-wired"></i>
                    <span>IP:</span>
                    <span class="value" id="ip">-</span>
                </div>
            </div>
        </div>
        
        <div class="header-right">
            <a href="home.php" class="btn-home-header"><i class="fas fa-home"></i> HOME</a>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($user) ?><span class="user-tag">User</span></span>
            </div>
        </div>
    </div>

    <!-- DATA SENSOR REALTIME -->
    <div class="card">
        <h3><i class="fas fa-solar-panel"></i> Data Sensor <span id="waktu" style="font-size:12px; color:#666;">-</span></h3>
        <div class="grid">
            <div class="box solar-box"><i class="fas fa-bolt"></i><div class="sensor-label">Tegangan Panel Surya</div><b id="tegangan">-</b><small>V DC</small></div>
            <div class="box solar-box"><i class="fas fa-charging-station"></i><div class="sensor-label">Arus Panel Surya</div><b id="arus">-</b><small>A DC</small></div>
            <div class="box solar-box"><i class="fas fa-solar-panel"></i><div class="sensor-label">Daya Panel Surya</div><b id="daya">-</b><small>Watt</small></div>
            
            <div class="box angin-box"><i class="fas fa-compass"></i><div class="sensor-label">Arah Angin</div><b id="arah">-</b></div>
            <div class="box angin-box"><i class="fas fa-wind"></i><div class="sensor-label">Kecepatan Angin</div><b id="kecepatan_angin">-</b></div>
            
            <div class="box asap-box" id="asap-box"><i class="fas fa-smog"></i><div class="sensor-label">Asap</div><b id="asap">-</b></div>
            
            <div class="box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Suhu</div><b id="suhu">-</b></div>
            <div class="box"><i class="fas fa-tint"></i><div class="sensor-label">Kelembapan</div><b id="kelembapan">-</b></div>
            
            <div class="box co-box" id="co-box"><i class="fas fa-industry"></i><div class="sensor-label">Gas CO</div><b id="co">-</b></div>
        </div>
    </div>

    <!-- GRAFIK -->
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> Grafik Real Time Sensor</h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>

    <!-- MAPS -->
    <div class="card">
        <h3><i class="fas fa-map-marker-alt"></i> Lokasi Alat Monitoring</h3>
        <div class="map-container"><div id="map"></div></div>
        <div class="location-info">
            <div class="location-info-item">
                <i class="fas fa-globe"></i>
                <span class="label">Koordinat:</span>
                <span class="value" id="coordinates">-</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-tree"></i>
                <span class="label">Zona:</span>
                <span class="value" id="zone">Zona Monitoring</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-flag-checkered"></i>
                <span class="label">Status:</span>
                <span class="value" id="location-status" style="color: #28a745;">Aman</span>
            </div>
        </div>
    </div>

</div>

<!-- MODAL LOGOUT -->
<div class="modal-overlay" id="logoutModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
        <h2 style="color: #1e3c72; margin-bottom: 20px;">Apakah Anda yakin keluar?</h2>
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="closeLogoutModal()"><i class="fas fa-times"></i> CANCEL</button>
            <a href="logout.php" class="btn-modal btn-logout-confirm"><i class="fas fa-sign-out-alt"></i> LOGOUT</a>
        </div>
    </div>
</div>

<script>
// FUNGSI MODAL LOGOUT
function openLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }

// PETA LEAFLET
var fixedLat = -1.20249;
var fixedLng = 116.88708;
var map = L.map('map').setView([fixedLat, fixedLng], 15);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(map);

var safeIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #28a745, #20c997); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; display: flex; align-items: center; justify-content: center;"><i class="fas fa-check-circle" style="color: white; font-size: 20px;"></i></div>',
    iconSize: [40, 40], iconAnchor: [20, 20]
});
var dangerIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; display: flex; align-items: center; justify-content: center; animation: blink 1s infinite;"><i class="fas fa-exclamation-triangle" style="color: white; font-size: 20px;"></i></div>',
    iconSize: [40, 40], iconAnchor: [20, 20]
});

var sensorMarker = L.marker([fixedLat, fixedLng], { icon: safeIcon }).addTo(map);
var dangerZone = L.circle([fixedLat, fixedLng], { color: '#28a745', fillColor: '#28a745', fillOpacity: 0.1, radius: 500 }).addTo(map);

// CHART JS
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = { 
    labels: [], 
    datasets: [
        { label: 'Tegangan (V)', data: [], borderColor: '#ffc107', tension: 0.4 },
        { label: 'Arus (A)', data: [], borderColor: '#ff8c00', tension: 0.4 },
        { label: 'Daya (W)', data: [], borderColor: '#28a745', tension: 0.4 },
        { label: 'Suhu (°C)', data: [], borderColor: '#ff6b6b', tension: 0.4 },
        { label: 'Kelembapan (%)', data: [], borderColor: '#4ecdc4', tension: 0.4 },
        { label: 'Angin (m/s)', data: [], borderColor: '#3399ff', tension: 0.4 },
        { label: 'CO (ppm)', data: [], borderColor: '#aa96da', tension: 0.4 }
    ] 
};
const myChart = new Chart(ctx, { type: 'line', data: dataChart, options: { responsive: true } });

// FETCH DATA DARI DATABASE MYSQL REALTIME
function fetchDataFromDB() {
    fetch('get_latest_data.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById("status").innerHTML = `<i class="fas fa-circle status-online"></i> ${data.status}`;
            document.getElementById("rssi").innerHTML = `${data.rssi} dBm`;
            document.getElementById("ip").innerHTML = data.ip;
            document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${data.waktu}`;

            document.getElementById("tegangan").innerHTML = `${data.tegangan} V`;
            document.getElementById("arus").innerHTML = `${data.arus} A`;
            document.getElementById("daya").innerHTML = `${data.daya} W`;

            document.getElementById("arah").innerHTML = `<i class="fas fa-compass"></i> ${data.arah}`;
            document.getElementById("kecepatan_angin").innerHTML = `${data.angin} m/s <i class="fas fa-wind"></i>`;

            // Asap Status
            var asapElement = document.getElementById("asap");
            var asapBox = document.getElementById('asap-box');
            if (data.asap === "Tinggi") {
                asapElement.innerHTML = 'Tinggi (Berbahaya)';
                asapElement.className = 'status-bahaya';
                asapBox.classList.add('pulse-animation');
            } else {
                asapElement.innerHTML = 'Normal';
                asapElement.className = 'status-aman';
                asapBox.classList.remove('pulse-animation');
            }

            document.getElementById("co").innerHTML = `${data.co} ppm`;
            document.getElementById("suhu").innerHTML = `${data.suhu} °C`;
            document.getElementById("kelembapan").innerHTML = `${data.kelembapan} %`;

            // Update Peta
            if(data.lat && data.lng) {
                document.getElementById('coordinates').innerHTML = `${data.lat}, ${data.lng}`;
                sensorMarker.setLatLng([data.lat, data.lng]);
                dangerZone.setLatLng([data.lat, data.lng]);
            }

            // Update Chart
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
        })
        .catch(err => console.error(err));
}

setInterval(fetchDataFromDB, 3000);
fetchDataFromDB();
</script>
</body>
</html>