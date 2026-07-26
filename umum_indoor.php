<?php
session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "user";

// Koneksi ke database indoor & ambil data lokasi dari tabel lokasi_monitoring
require_once 'koneksi.php';
$conn = isset($conn_indoor) ? $conn_indoor : null;

$db_locations = [];
if ($conn) {
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'lokasi_monitoring'");
    if ($checkTable && mysqli_num_rows($checkTable) > 0) {
        $checkNamaLokasiCol = mysqli_query($conn, "SHOW COLUMNS FROM lokasi_monitoring LIKE 'nama_lokasi'");
        if (!$checkNamaLokasiCol || mysqli_num_rows($checkNamaLokasiCol) == 0) {
            @mysqli_query($conn, "ALTER TABLE lokasi_monitoring ADD COLUMN nama_lokasi VARCHAR(100) DEFAULT NULL AFTER id_alat");
        }
        
        $q_loc = mysqli_query($conn, "SELECT id, id_alat, nama_lokasi, latitude, longitude, updated_at AS last_update FROM lokasi_monitoring ORDER BY id ASC");
        if ($q_loc) {
            while ($r = mysqli_fetch_assoc($q_loc)) {
                $db_locations[] = [
                    'id' => (int)$r['id'],
                    'id_alat' => $r['id_alat'],
                    'nama_lokasi' => $r['nama_lokasi'] ?? '',
                    'latitude' => (float)$r['latitude'],
                    'longitude' => (float)$r['longitude'],
                    'last_update' => $r['last_update']
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Indoor - FIREDETECTOR</title>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ========== STYLE (SAMA SEPERTI ASLI) ========== */
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
.box.api-box { background: linear-gradient(135deg, rgba(255, 107, 107, 0.9), rgba(238, 90, 36, 0.9)); }
.box.asap-box { background: linear-gradient(135deg, rgba(255, 165, 2, 0.9), rgba(255, 99, 72, 0.9)); }
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.02); opacity: 0.9; box-shadow: 0 0 20px rgba(220, 38, 38, 0.5); }
}
.pulse-animation { animation: pulse 1s ease-in-out infinite; }
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
    .location-info { flex-direction: column; align-items: flex-start; }
    .header-right { flex-direction: column; gap: 8px; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3><i class="fas fa-building"></i> Indoor</h3>
    <a href="umum_indoor.php" class="menu-btn active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard Indoor</span>
    </a>
    <a href="home.php" class="menu-btn logout">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="header">
        <h2><i class="fas fa-building"></i> Dashboard Monitoring Indoor</h2>
        <div class="header-right">
            <a href="home.php" class="btn-home-header"><i class="fas fa-home"></i> HOME</a>
            <div class="user-info"><i class="fas fa-user-circle"></i><span>Halo </span></div>
        </div>
    </div>

    <!-- NODE STATUS -->
    <div class="card">
        <h3><i class="fas fa-microchip"></i> Status Node Indoor</h3>
        <div class="node-status">
            <div class="status-item"><i class="fas fa-circle status-online"></i><div class="label">Status</div><div class="value" id="status">-</div></div>
            <div class="status-item"><i class="fas fa-signal"></i><div class="label">RSSI</div><div class="value" id="rssi">-</div></div>
            <div class="status-item"><i class="fas fa-network-wired"></i><div class="label">IP Address</div><div class="value" id="ip">-</div></div>
        </div>
    </div>

    <!-- LOKASI / MAP CARD (DIPERBAIKI: TERHUBUNG KE TABEL lokasi_monitoring DATABASE INDOOR) -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; padding: 0; border: none;"><i class="fas fa-map-marker-alt"></i> Lokasi Alat (Indoor)</h3>
            <span style="font-size: 12px; background: rgba(0, 180, 219, 0.1); color: #0083b0; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                Total: <span id="total-locations"><?= count($db_locations); ?></span> Titik Lokasi
            </span>
        </div>

        <?php if (!empty($db_locations)): ?>
        <div class="location-buttons" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px;">
            <?php foreach ($db_locations as $index => $loc): 
                $nama_loc = !empty($loc['nama_lokasi']) ? $loc['nama_lokasi'] : ($loc['id_alat'] ? "Indoor ({$loc['id_alat']})" : "Lokasi {$loc['id']}");
                $code_alat = $loc['id_alat'] ? $loc['id_alat'] : "IND-" . str_pad($loc['id'], 3, '0', STR_PAD_LEFT);
            ?>
            <button type="button" class="btn-loc-select <?= ($index == 0) ? 'active' : '' ?>" 
                    onclick="flyToLocation(<?= $loc['latitude'] ?>, <?= $loc['longitude'] ?>, '<?= htmlspecialchars($nama_loc, ENT_QUOTES) ?>', '<?= htmlspecialchars($code_alat, ENT_QUOTES) ?>', event)" 
                    style="padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(0,0,0,0.15); background: <?= ($index == 0) ? 'linear-gradient(135deg, #00b4db, #0083b0)' : 'white' ?>; color: <?= ($index == 0) ? 'white' : '#333' ?>; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 6px;" 
                    id="btn-loc-<?= $loc['id'] ?>">
                <i class="fas fa-location-dot"></i> 
                <span><?= htmlspecialchars($nama_loc) ?></span>
                <span style="opacity: 0.85; font-size: 11px; background: rgba(0,0,0,0.08); padding: 2px 6px; border-radius: 10px;">ID: <?= htmlspecialchars($code_alat) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="map-container"><div id="map"></div></div>
        <div class="location-info">
            <div class="location-info-item">
                <i class="fas fa-building"></i>
                <span class="label">Nama Lokasi:</span>
                <span class="value" id="location-name-val"><?= htmlspecialchars(!empty($db_locations[0]['nama_lokasi']) ? $db_locations[0]['nama_lokasi'] : 'Indoor Sensor') ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-microchip"></i>
                <span class="label">ID Alat:</span>
                <span class="value" id="location-id-val" style="color: #e85d04; font-weight: 700;"><?= htmlspecialchars($db_locations[0]['id_alat'] ?? '001') ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-globe"></i>
                <span class="label">Koordinat:</span>
                <span class="value" id="coordinates"><?= !empty($db_locations) ? number_format($db_locations[0]['latitude'], 6) . ', ' . number_format($db_locations[0]['longitude'], 6) : '-1.202490, 116.887080' ?></span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-layer-group"></i>
                <span class="label">Zona:</span>
                <span class="value" id="zone">Zona Indoor (Gedung)</span>
            </div>
            <div class="location-info-item">
                <i class="fas fa-flag-checkered"></i>
                <span class="label">Status:</span>
                <span class="value" id="location-status" style="color: #28a745; font-weight: bold;">Aman</span>
            </div>
        </div>
    </div>

    <!-- SENSOR DATA -->
    <div class="card">
        <h3><i class="fas fa-microphone-alt"></i> Data Sensor Real Time (Indoor) <span style="font-size: 12px; color: #666;" id="waktu">-</span></h3>
        <div class="grid">
            <div class="box api-box" id="api-box"><i class="fas fa-fire"></i><div class="sensor-label">Sensor Api</div><b id="api">-</b></div>
            <div class="box asap-box" id="asap-box"><i class="fas fa-smog"></i><div class="sensor-label">Sensor Asap</div><b id="asap">-</b></div>
            <div class="box"><i class="fas fa-temperature-high"></i><div class="sensor-label">Sensor Suhu</div><b id="suhu">-</b></div>
            <div class="box"><i class="fas fa-tint"></i><div class="sensor-label">Sensor Kelembapan</div><b id="kelembapan">-</b></div>
            <div class="box"><i class="fas fa-bolt"></i><div class="sensor-label">Sensor Tegangan</div><b id="tegangan">-</b></div>
            <div class="box"><i class="fas fa-charging-station"></i><div class="sensor-label">Sensor Arus</div><b id="arus">-</b></div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-building" style="color: #0083b0;"></i>
            <span style="color: #1e3c72; font-size: 13px;"><strong>Monitoring Indoor</strong> - Sensor terpasang di dalam gedung untuk deteksi dini kebakaran.</span>
        </div>
    </div>

    <!-- CHART -->
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> Grafik Real Time Sensor</h3>
        <div class="chart-container"><canvas id="myChart"></canvas></div>
    </div>
</div>

<script>
// ================= PETA & LOKASI DINAMIS DARI DATABASE INDOOR (lokasi_monitoring) =================
var defaultLat = <?= !empty($db_locations) ? (float)$db_locations[0]['latitude'] : -1.20249 ?>;
var defaultLng = <?= !empty($db_locations) ? (float)$db_locations[0]['longitude'] : 116.88708 ?>;
var initialLocations = <?= json_encode($db_locations); ?>;

var map = L.map('map').setView([defaultLat, defaultLng], 15);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

L.control.scale({ metric: true, imperial: false }).addTo(map);

var markers = [];
var dangerZones = [];

function createIndoorIcon(id_alat, isDanger) {
    if (isDanger) {
        return L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 42px; height: 42px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; flex-direction: column; animation: blink 1s infinite;">
                    <i class="fas fa-exclamation-triangle" style="color: white; font-size: 14px;"></i>
                    <span style="font-size: 8px; color: white; font-weight: bold; margin-top: 1px;">${id_alat || 'Indoor'}</span>
                  </div>`,
            iconSize: [42, 42],
            iconAnchor: [21, 21],
            popupAnchor: [0, -21],
            className: 'indoor-marker-danger'
        });
    } else {
        return L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 42px; height: 42px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <i class="fas fa-building" style="color: white; font-size: 14px;"></i>
                    <span style="font-size: 8px; color: white; font-weight: bold; margin-top: 1px;">${id_alat || 'Indoor'}</span>
                  </div>`,
            iconSize: [42, 42],
            iconAnchor: [21, 21],
            popupAnchor: [0, -21],
            className: 'indoor-marker'
        });
    }
}

async function fetchLocationsFromDB() {
    try {
        const response = await fetch('get_locations.php');
        const result = await response.json();
        if (!result.error && Array.isArray(result.data) && result.data.length > 0) {
            return result.data;
        }
    } catch (error) {
        console.error('Gagal mengambil data lokasi dari database:', error);
    }
    return initialLocations;
}

function flyToLocation(lat, lng, nama, idAlat, event) {
    map.flyTo([lat, lng], 17, { duration: 1.5 });
    
    const locNameElem = document.getElementById('location-name-val');
    if (locNameElem) locNameElem.innerText = nama;
    
    const locIdElem = document.getElementById('location-id-val');
    if (locIdElem) locIdElem.innerText = idAlat;

    const coordElem = document.getElementById('coordinates');
    if (coordElem) coordElem.innerHTML = `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;

    document.querySelectorAll('.btn-loc-select').forEach(btn => {
        btn.style.background = 'white';
        btn.style.color = '#333';
        btn.classList.remove('active');
    });
    if (event && event.currentTarget) {
        event.currentTarget.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
        event.currentTarget.style.color = 'white';
        event.currentTarget.classList.add('active');
    }
}

async function updateLocationStatus(isDanger) {
    const locations = await fetchLocationsFromDB();
    
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    dangerZones.forEach(z => map.removeLayer(z));
    dangerZones = [];
    
    const totalElem = document.getElementById('total-locations');
    if (totalElem) {
        totalElem.innerHTML = locations.length;
    }

    const statusElem = document.getElementById('location-status');
    const zoneElem = document.getElementById('zone');
    
    if (isDanger) {
        if (statusElem) {
            statusElem.innerHTML = '⚠️ BAHAYA - Deteksi Kebakaran!';
            statusElem.style.color = '#dc2626';
        }
        if (zoneElem) zoneElem.innerHTML = 'Zona Merah (Peringatan Bahaya)';
    } else {
        if (statusElem) {
            statusElem.innerHTML = 'Aman';
            statusElem.style.color = '#28a745';
        }
        if (zoneElem) zoneElem.innerHTML = 'Zona Indoor (Gedung)';
    }

    if (!locations || locations.length === 0) {
        const icon = createIndoorIcon('001', isDanger);
        const m = L.marker([defaultLat, defaultLng], { icon: icon }).addTo(map);
        markers.push(m);
        return;
    }

    locations.forEach((loc, idx) => {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);
        const idAlat = loc.id_alat || `00${loc.id}`;
        const namaLokasi = loc.nama_lokasi && loc.nama_lokasi.trim() !== '' ? loc.nama_lokasi : `Indoor (${idAlat})`;
        
        const icon = createIndoorIcon(idAlat, isDanger);
        const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
        
        const statusBadge = isDanger 
            ? '<span style="color: white; background: #dc2626; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-exclamation-triangle"></i> BAHAYA</span>' 
            : '<span style="color: white; background: #28a745; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-check-circle"></i> Aman</span>';
        
        marker.bindPopup(`
            <div style="font-family: 'Segoe UI', sans-serif; padding: 4px; min-width: 190px;">
                <b style="color: #1e3c72; font-size: 14px; display: block; margin-bottom: 2px;"><i class="fas fa-building" style="color: #00b4db;"></i> ${namaLokasi}</b>
                <small style="color: #666; display: block; margin-bottom: 6px;">ID Alat: <strong>${idAlat}</strong></small>
                <div style="font-size: 12px; color: #444; margin-bottom: 4px;"><i class="fas fa-map-marker-alt" style="color: #dc2626;"></i> <b>Koordinat:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
                <div style="font-size: 11px; color: #777; margin-bottom: 6px;"><i class="fas fa-clock"></i> <b>Update:</b> ${loc.last_update || '-'}</div>
                <div style="font-size: 12px; margin-top: 6px;"><b>Status:</b> ${statusBadge}</div>
            </div>
        `);
        
        marker.on('click', function() {
            const locNameElem = document.getElementById('location-name-val');
            if (locNameElem) locNameElem.innerText = namaLokasi;

            const locIdElem = document.getElementById('location-id-val');
            if (locIdElem) locIdElem.innerText = idAlat;
            
            const coordElem = document.getElementById('coordinates');
            if (coordElem) coordElem.innerHTML = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        });
        
        const circleColor = isDanger ? '#dc2626' : '#e85d04';
        const circleOpacity = isDanger ? 0.3 : 0.15;
        const zone = L.circle([lat, lng], {
            color: circleColor,
            fillColor: circleColor,
            fillOpacity: circleOpacity,
            radius: 300
        }).addTo(map);
        
        markers.push(marker);
        dangerZones.push(zone);

        if (idx === 0) {
            const locNameElem = document.getElementById('location-name-val');
            if (locNameElem && (!locNameElem.innerText || locNameElem.innerText === '-')) {
                locNameElem.innerText = namaLokasi;
            }
            const locIdElem = document.getElementById('location-id-val');
            if (locIdElem && (!locIdElem.innerText || locIdElem.innerText === '-')) {
                locIdElem.innerText = idAlat;
            }
            const coordElem = document.getElementById('coordinates');
            if (coordElem && (!coordElem.innerHTML || coordElem.innerHTML === '-')) {
                coordElem.innerHTML = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            }
        }
    });
}

// ================= CHART =================
const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = {
    labels: [],
    datasets: [
        { label: 'Suhu (°C)', data: [], borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kelembapan (%)', data: [], borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Tegangan (V)', data: [], borderColor: '#ffe66d', backgroundColor: 'rgba(255,230,109,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Arus (A)', data: [], borderColor: '#a8e6cf', backgroundColor: 'rgba(168,230,207,0.1)', borderWidth: 2, tension: 0.4, fill: true }
    ]
};
const myChart = new Chart(ctx, {
    type: 'line',
    data: dataChart,
    options: { responsive: true, maintainAspectRatio: true, animation: { duration: 500 },
        plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } },
        scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
    }
});

// ================= DATA DARI DATABASE (FETCH API) =================
function fetchDataFromDB() {
    fetch('api_get_data.php?device=indoor')
    .then(response => response.json())
    .then(data => {
        document.getElementById("status").innerHTML = `<i class="fas fa-circle status-online"></i> ${data.status}`;
        document.getElementById("rssi").innerHTML = `${data.rssi} dBm`;
        document.getElementById("ip").innerHTML = data.ip;
        document.getElementById("waktu").innerHTML = `<i class="far fa-clock"></i> ${data.waktu}`;
        
        const apiValue = data.api === "Terdeteksi Api" ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman';
        document.getElementById("api").innerHTML = apiValue;
        const asapValue = data.asap === "Tinggi" ? '<i class="fas fa-chart-line"></i> Tinggi (Berbahaya)' : '<i class="fas fa-check"></i> Normal';
        document.getElementById("asap").innerHTML = asapValue;
        document.getElementById("suhu").innerHTML = `${data.suhu} °C <i class="fas fa-thermometer-half"></i>`;
        document.getElementById("kelembapan").innerHTML = `${data.kelembapan} % <i class="fas fa-tint"></i>`;
        document.getElementById("tegangan").innerHTML = `${data.tegangan} V <i class="fas fa-bolt"></i>`;
        document.getElementById("arus").innerHTML = `${data.arus} A <i class="fas fa-charging-station"></i>`;
        
        updateLocationStatus(data.isDanger);
        
        const apiBox = document.getElementById('api-box');
        const asapBox = document.getElementById('asap-box');
        if (data.api === "Terdeteksi Api") {
            apiBox.classList.add('pulse-animation');
            apiBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else {
            apiBox.classList.remove('pulse-animation');
            apiBox.style.background = "linear-gradient(135deg, rgba(255,107,107,0.9), rgba(238,90,36,0.9))";
        }
        if (data.asap === "Tinggi") {
            asapBox.classList.add('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else {
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(255,165,2,0.9), rgba(255,99,72,0.9))";
        }
        
        dataChart.labels.push(data.waktu);
        dataChart.datasets[0].data.push(parseFloat(data.suhu));
        dataChart.datasets[1].data.push(parseFloat(data.kelembapan));
        dataChart.datasets[2].data.push(parseFloat(data.tegangan));
        dataChart.datasets[3].data.push(parseFloat(data.arus));
        if(dataChart.labels.length > 15) {
            dataChart.labels.shift();
            dataChart.datasets.forEach(ds => ds.data.shift());
        }
        myChart.update();
    })
    .catch(error => console.error("Error fetching data:", error));
}

// Panggil pertama kali, lalu ulangi setiap 2 detik
fetchDataFromDB();
setInterval(fetchDataFromDB, 2000);
</script>
</body>
</html>