/**
 * Script Logika Frontend Dashboard Umum Indoor
 * FireNetWork Indoor System
 */

// ================= PETA & LOKASI DINAMIS DARI DATABASE INDOOR =================
const pageConfig = window.INDOOR_UMUM_DATA || {};
var defaultLat = pageConfig.defaultLat || -6.2088;
var defaultLng = pageConfig.defaultLng || 106.8456;
var initialLocations = pageConfig.locations || [];
var currentSuhu = pageConfig.currentSuhu || '-';
var activeSelectedLocationId = pageConfig.primaryLocId || 1;
var hasFitBounds = false;
var currentLocationsData = [];

var map = L.map('map').setView([defaultLat, defaultLng], 16);
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

function flyToLocation(lat, lng, nama, idAlat, locId, event) {
    if (locId) activeSelectedLocationId = locId;
    const rawIdAlat = String(idAlat || locId || '').toUpperCase();
    const isLiveLoc = (rawIdAlat === 'LOK-002' || rawIdAlat === 'IND-002' || rawIdAlat === '002' || rawIdAlat.includes('002') || rawIdAlat.includes('UTAMA') || locId === 2);
    const selectedType = isLiveLoc ? 'utama' : 'dummy';
    if (currentType !== selectedType) {
        currentType = selectedType;
        loadChartHistory(currentType);
    }
    map.flyTo([lat, lng], 17, { duration: 1.5 });
    
    const locNameElem = document.getElementById('location-name-val');
    if (locNameElem) locNameElem.innerText = nama;
    
    const locIdElem = document.getElementById('location-id-val');
    if (locIdElem) locIdElem.innerText = idAlat;

    const coordElem = document.getElementById('coordinates');
    if (coordElem) coordElem.innerHTML = `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;

    markers.forEach(m => {
        const mLatLng = m.getLatLng();
        if (Math.abs(mLatLng.lat - parseFloat(lat)) < 0.0001 && Math.abs(mLatLng.lng - parseFloat(lng)) < 0.0001) {
            m.openPopup();
        }
    });

    document.querySelectorAll('.btn-loc-select').forEach(btn => {
        btn.style.background = 'white';
        btn.style.color = '#333';
        btn.classList.remove('active');
    });
    const activeBtn = (event && event.currentTarget) || (locId ? document.getElementById('btn-loc-' + locId) : null);
    if (activeBtn) {
        activeBtn.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
        activeBtn.style.color = 'white';
        activeBtn.classList.add('active');
    }
    fetchDataFromDB();
}

async function updateLocationStatus(statusText, isDanger) {
    if (typeof statusText === 'boolean') {
        const temp = isDanger;
        isDanger = statusText;
        statusText = typeof temp === 'string' ? temp : (isDanger ? 'Kebakaran' : 'Aman');
    }
    if (!statusText) statusText = 'Aman';
    if (typeof isDanger === 'undefined') isDanger = (statusText !== 'Aman');

    const locations = await fetchLocationsFromDB();
    currentLocationsData = locations;
    
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
    
    if (statusElem) {
        if (statusText && statusText !== 'Aman') {
            statusElem.innerHTML = statusText;
            statusElem.style.color = (statusText === 'Kebakaran') ? '#dc3545' : '#f59e0b';
        } else if (isDanger) {
            statusElem.innerHTML = 'Kebakaran';
            statusElem.style.color = '#dc3545';
        } else {
            statusElem.innerHTML = 'Aman';
            statusElem.style.color = '#28a745';
        }
    }

    if (zoneElem) {
        if (statusText === 'Kebakaran' || (isDanger && !statusText)) {
            zoneElem.innerHTML = 'Zona Merah (Deteksi Kebakaran)';
        } else if (statusText === 'lingkungan tidak normal' || statusText === 'Lingkungan tidak normal') {
            zoneElem.innerHTML = 'Zona Waspada (Lingkungan Tidak Normal)';
        } else if (statusText === 'Gangguan listrik') {
            zoneElem.innerHTML = 'Zona Waspada (Gangguan Listrik)';
        } else {
            zoneElem.innerHTML = 'Zona Indoor (Gedung)';
        }
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
        
        const isLocDanger = (loc.id === activeSelectedLocationId) ? isDanger : false;

        const icon = createIndoorIcon(idAlat, isLocDanger);
        const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
        
        const statusBadge = isLocDanger 
            ? '<span style="color: white; background: #dc2626; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-exclamation-triangle"></i> BAHAYA</span>' 
            : '<span style="color: white; background: #28a745; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="fas fa-check-circle"></i> Aman</span>';
        
        marker.bindPopup(`
            <div style="font-family: 'Segoe UI', sans-serif; padding: 4px; min-width: 190px;">
                <b style="color: #1e3c72; font-size: 14px; display: block; margin-bottom: 2px;"><i class="fas fa-building" style="color: #00b4db;"></i> ${namaLokasi}</b>
                <small style="color: #666; display: block; margin-bottom: 6px;">ID Alat: <strong>${idAlat}</strong> &nbsp;|&nbsp; <i class="fas fa-temperature-high" style="color:#ff6b6b;"></i> Suhu: <strong class="loc-suhu-val">${currentSuhu}</strong></small>
                <div style="font-size: 12px; color: #444; margin-bottom: 4px;"><i class="fas fa-map-marker-alt" style="color: #dc2626;"></i> <b>Koordinat:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
                <div style="font-size: 11px; color: #777; margin-bottom: 6px;"><i class="fas fa-clock"></i> <b>Update:</b> ${loc.last_update || '-'}</div>
                <div style="font-size: 12px; margin-top: 6px;"><b>Status:</b> ${statusBadge}</div>
            </div>
        `);
        
        marker.on('click', function() {
            flyToLocation(lat, lng, namaLokasi, idAlat, loc.id);
        });
        
        const circleColor = isLocDanger ? '#dc2626' : '#e85d04';
        const circleOpacity = isLocDanger ? 0.3 : 0.15;
        const zone = L.circle([lat, lng], {
            color: circleColor,
            fillColor: circleColor,
            fillOpacity: circleOpacity,
            radius: 300
        }).addTo(map);
        
        markers.push(marker);
        dangerZones.push(zone);

        if (!activeSelectedLocationId && idx === 0) {
            activeSelectedLocationId = loc.id;
        }

        if (activeSelectedLocationId === loc.id) {
            const locNameElem = document.getElementById('location-name-val');
            if (locNameElem) locNameElem.innerText = namaLokasi;
            const locIdElem = document.getElementById('location-id-val');
            if (locIdElem) locIdElem.innerText = idAlat;
            const coordElem = document.getElementById('coordinates');
            if (coordElem) coordElem.innerHTML = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
    });

    if (activeSelectedLocationId) {
        const selBtn = document.getElementById('btn-loc-' + activeSelectedLocationId);
        if (selBtn) {
            document.querySelectorAll('.btn-loc-select').forEach(btn => {
                btn.style.background = 'white';
                btn.style.color = '#333';
                btn.classList.remove('active');
            });
            selBtn.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
            selBtn.style.color = 'white';
            selBtn.classList.add('active');
        }
        const selectedLoc = locations.find(l => l.id === activeSelectedLocationId);
        if (selectedLoc) {
            markers.forEach(m => {
                const mLatLng = m.getLatLng();
                if (Math.abs(mLatLng.lat - parseFloat(selectedLoc.latitude)) < 0.0001 && Math.abs(mLatLng.lng - parseFloat(selectedLoc.longitude)) < 0.0001) {
                    m.openPopup();
                }
            });
        }
    }

    if (!hasFitBounds && markers.length > 0) {
        if (markers.length === 1) {
            map.setView(markers[0].getLatLng(), 16);
        } else {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.2));
        }
        hasFitBounds = true;
    }
}

// Render awal titik lokasi peta
updateLocationStatus(false);

// ================= CHART (REAL TIME INDOOR SENSOR) =================
const chartLabels = pageConfig.chartLabels || [];
const chartSuhu = pageConfig.chartSuhu || [];
const chartKelembapan = pageConfig.chartKelembapan || [];
const chartAsap = pageConfig.chartAsap || [];
const chartApi = pageConfig.chartApi || [];

const ctx = document.getElementById('myChart').getContext('2d');
let dataChart = {
    labels: chartLabels,
    datasets: [
        { label: 'Suhu (°C)', data: chartSuhu, borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Kelembapan (%)', data: chartKelembapan, borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Status Asap', data: chartAsap, borderColor: '#ff9f43', backgroundColor: 'rgba(255,159,67,0.1)', borderWidth: 2, tension: 0.4, fill: true },
        { label: 'Status Api', data: chartApi, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', borderWidth: 2, tension: 0.4, fill: true }
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
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'line'
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let value = context.raw;
                        let unit = '';
                        if (label.includes('Suhu')) unit = ' °C';
                        else if (label.includes('Kelembapan')) unit = ' %';
                        else if (label.includes('Status Asap')) {
                            if (typeof value === 'number' && value > 1) {
                                let status = value > 750 ? '⚠️ Asap Tinggi' : (value > 350 ? '⚡ Asap Sedang' : '✅ Normal');
                                return `${label}: ${value} % (${status})`;
                            } else {
                                let status = (value === 1 || value === 'Tinggi') ? '⚠️ Asap Tinggi' : (value === 0.5 || value === 'Sedang' ? '⚡ Asap Sedang' : '✅ Normal');
                                return `${label}: ${status}`;
                            }
                        }
                        else if (label.includes('Status Api')) {
                            let status = value === 1 ? '🔥 Terdeteksi Api' : '✅ Aman';
                            return `${label}: ${status}`;
                        }
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

let currentType = 'utama';

function loadChartHistory(type) {
    if (!myChart || !dataChart) return;

    fetch('api_get_data.php?device=indoor&history=1&type=' + type)
    .then(res => res.json())
    .then(historyData => {
        if (!Array.isArray(historyData)) return;

        dataChart.labels = [];
        dataChart.datasets.forEach(ds => {
            ds.data = [];
            if (ds.pointBackgroundColor) ds.pointBackgroundColor = [];
            if (ds.pointRadius) ds.pointRadius = [];
        });

        historyData.forEach(data => {
            let labelWaktu = data.is_dummy ? data.waktu + " (Dummy)" : data.waktu;
            dataChart.labels.push(labelWaktu);

            dataChart.datasets.forEach(ds => {
                if (data.is_dummy) {
                    if (!ds.pointBackgroundColor) ds.pointBackgroundColor = [];
                    if (!ds.pointRadius) ds.pointRadius = [];
                    ds.pointBackgroundColor.push('#ff0000');
                    ds.pointRadius.push(4);
                } else {
                    if (ds.pointBackgroundColor) ds.pointBackgroundColor.push(ds.borderColor);
                    if (ds.pointRadius) ds.pointRadius.push(0);
                }
            });

            if (dataChart.datasets[0]) dataChart.datasets[0].data.push(parseFloat(data.suhu) || 0);
            if (dataChart.datasets[1]) dataChart.datasets[1].data.push(parseFloat(data.kelembapan) || 0);
            if (dataChart.datasets[2]) dataChart.datasets[2].data.push(data.asap_value !== undefined ? parseFloat(data.asap_value) : (data.asap === "Tinggi" ? 1 : (data.asap === "Waspada" ? 0.5 : 0)));
            if (dataChart.datasets[3]) dataChart.datasets[3].data.push(data.apiValue !== undefined ? data.apiValue : (data.api === "Terdeteksi Api" ? 1 : 0));
        });

        myChart.update();
    })
    .catch(err => console.error('Error loadChartHistory:', err));
}

loadChartHistory(currentType);

// ================= GENERATE DATA =================
let dummyState = 0;

function generateData() {
    let apiStatus = "Aman";
    let asapStatus = "Normal";
    let suhu = 28;
    let kelembapan = 60;
    let tegangan = 220;
    let arus = 2.5;
    let isDanger = false;
    let isWarning = false;

    if (dummyState === 0) {
        apiStatus = "Aman";
        asapStatus = "Normal";
        suhu = (Math.random() * 5 + 25).toFixed(1);
        kelembapan = (Math.random() * 15 + 50).toFixed(1);
        tegangan = 220;
        arus = (Math.random() * 1 + 2).toFixed(2);
    } else if (dummyState === 1) {
        apiStatus = "Aman";
        asapStatus = "Normal";
        suhu = (Math.random() * 5 + 42).toFixed(1);
        kelembapan = (Math.random() * 5 + 12).toFixed(1);
        tegangan = 220;
        arus = (Math.random() * 1 + 2).toFixed(2);
        isWarning = true;
    } else if (dummyState === 2) {
        apiStatus = "Aman";
        asapStatus = "Normal";
        suhu = (Math.random() * 5 + 25).toFixed(1);
        kelembapan = (Math.random() * 15 + 50).toFixed(1);
        tegangan = (Math.random() * 10 + 245).toFixed(1);
        arus = (Math.random() * 3 + 6.5).toFixed(2);
        isWarning = true;
    } else {
        apiStatus = "Terdeteksi Api";
        asapStatus = "Tinggi";
        suhu = (Math.random() * 15 + 48).toFixed(1);
        kelembapan = (Math.random() * 10 + 15).toFixed(1);
        tegangan = 210;
        arus = (Math.random() * 5 + 10).toFixed(2);
        isDanger = true;
    }

    dummyState = (dummyState + 1) % 4;

    return {
        waktu: new Date().toLocaleTimeString(),
        api: apiStatus,
        asap: asapStatus,
        asap_value: asapStatus === "Tinggi" ? 1 : (asapStatus === "Waspada" ? 0.5 : 0),
        suhu: suhu,
        kelembapan: kelembapan,
        tegangan: tegangan,
        arus: arus,
        status: 'Online',
        rssi: Math.floor(Math.random() * 40 + -80),
        ip: '192.168.1.' + Math.floor(Math.random() * 255),
        isDanger: isDanger,
        isWarning: isWarning,
        apiValue: apiStatus === "Terdeteksi Api" ? 1 : 0,
        limit_suhu: 45,
        limit_kelembapan: 85,
        limit_tegangan: 250,
        limit_arus: 15,
        batas_suhu: 45,
        batas_kelembapan: 85,
        batas_tegangan: 250,
        batas_arus: 15
    };
}

async function fetchSensorData() {
    try {
        const response = await fetch('api_get_data.php?device=indoor');
        const data = await response.json();
        if (data.error) return null;
        return data;
    } catch (error) {
        return null;
    }
}

async function fetchDataFromDB() {
    const locationsList = (currentLocationsData && currentLocationsData.length > 0) ? currentLocationsData : initialLocations;
    const lokasiAktif = locationsList.find(l => l.id === activeSelectedLocationId) || locationsList[0];
    const rawIdAlat = String(lokasiAktif.id_alat || '').toUpperCase();
    const isLive = (rawIdAlat === 'LOK-002' || rawIdAlat === 'IND-002' || rawIdAlat === '002' || rawIdAlat.includes('002') || rawIdAlat.includes('UTAMA') || lokasiAktif.id === 2);

    let data;
    if (isLive) {
        data = await fetchSensorData();
    } else {
        data = generateData();
    }

    if (!data) return;

    var nowClock = data.waktu || new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });

    var statusElem = document.getElementById("status");
    if (statusElem) {
        if (isLive) {
            if (data.status === 'Online') {
                statusElem.innerHTML = `<i class="fas fa-circle status-online"></i> Live (Online)`;
            } else {
                statusElem.innerHTML = `<i class="fas fa-circle" style="color: #dc3545;"></i> Offline`;
            }
        } else {
            statusElem.innerHTML = `<i class="fas fa-circle" style="color: #00b4db;"></i> Simulasi (Dummy)`;
        }
    }
    var rssiElem = document.getElementById("rssi");
    if (rssiElem) {
        if (isLive) {
            rssiElem.innerHTML = (data.status === 'Online' && data.rssi && data.rssi !== '-') ? `${data.rssi} dBm` : '-';
        } else {
            rssiElem.innerHTML = (data.rssi && data.rssi !== '-') ? `${data.rssi} dBm` : `${data.rssi || '-'}`;
        }
    }
    
    var ipElem = document.getElementById("ip");
    if (ipElem) {
        if (isLive) {
            ipElem.innerHTML = (data.status === 'Online' && data.ip) ? data.ip : '-';
        } else {
            ipElem.innerHTML = data.ip || '-';
        }
    }

    var waktuElem = document.getElementById("waktu");
    if (waktuElem) waktuElem.innerHTML = `<i class="far fa-clock"></i> ${nowClock}`;
    
    const chartBadge = document.getElementById("chart-badge");
    if (chartBadge) {
        if (isLive) {
            if (data.status === 'Online') {
                chartBadge.innerHTML = '<i class="fas fa-bolt"></i> Live (Real-Time)';
                chartBadge.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
            } else {
                chartBadge.innerHTML = '<i class="fas fa-power-off"></i> Alat Offline';
                chartBadge.style.background = 'linear-gradient(135deg, #dc3545, #b91c1c)';
            }
        } else {
            chartBadge.innerHTML = '<i class="fas fa-flask"></i> Data Dummy (Simulasi)';
            chartBadge.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        }
    }
    
    const boxes = document.querySelectorAll('.grid .box');

    const isApiDanger = (data.api === "Terdeteksi Api");
    const isAsapDanger = (data.asap === "Tinggi" || data.asap === "Bahaya");

    let batasSuhu = data.batas_suhu || data.limit_suhu || 45;
    let batasKelembapan = data.batas_kelembapan || data.limit_kelembapan || 85;
    let batasTegangan = data.batas_tegangan || data.limit_tegangan || 250;
    let batasArus = data.batas_arus || data.limit_arus || 15;

    const isSuhuAbnormal = (data.suhu !== undefined && parseFloat(data.suhu) > batasSuhu);
    const isKelembapanAbnormal = (data.kelembapan !== undefined && parseFloat(data.kelembapan) > batasKelembapan);
    const isTeganganOver = (data.tegangan !== undefined && parseFloat(data.tegangan) > batasTegangan);
    const isArusOver = (data.arus !== undefined && parseFloat(data.arus) > batasArus);

    let statusText = "Aman";
    let isDangerDetected = false;

    if (isLive) {
        if (isApiDanger || isAsapDanger || data.isDanger) {
            statusText = "Kebakaran";
            isDangerDetected = true;
        } else if (isSuhuAbnormal || isKelembapanAbnormal) {
            statusText = "lingkungan tidak normal";
            isDangerDetected = true;
        } else if (isTeganganOver || isArusOver) {
            statusText = "Gangguan listrik";
            isDangerDetected = true;
        } else {
            statusText = "Aman";
            isDangerDetected = false;
        }
    } else {
        if (isApiDanger || isAsapDanger || (data.isDanger && dummyState === 0)) {
            statusText = "Kebakaran";
            isDangerDetected = true;
        } else if (isSuhuAbnormal || isKelembapanAbnormal || (data.isWarning && dummyState === 2)) {
            statusText = "lingkungan tidak normal";
            isDangerDetected = true;
        } else if (isTeganganOver || isArusOver || (data.isWarning && dummyState === 3)) {
            statusText = "Gangguan listrik";
            isDangerDetected = true;
        } else if (data.isDanger) {
            statusText = "Kebakaran";
            isDangerDetected = true;
        } else {
            statusText = "Aman";
            isDangerDetected = false;
        }
    }

    function setBoxColor(box, index, danger, warning) {
        if (!box) return;
        if (danger) {
            box.classList.add('pulse-animation');
            box.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else if (warning) {
            box.classList.remove('pulse-animation');
            box.style.background = "linear-gradient(135deg, rgba(245, 158, 11, 0.9), rgba(217, 119, 6, 0.9))";
        } else {
            box.classList.remove('pulse-animation');
            box.style.background = "linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9))";
        }
    }

    const apiValue = data.api === "Terdeteksi Api" ? '<i class="fas fa-exclamation-triangle"></i> TERDETEKSI API' : '<i class="fas fa-check-circle"></i> Aman';
    var apiElem = document.getElementById("api");
    if (apiElem) apiElem.innerHTML = apiValue;

    var asapElem = document.getElementById("asap");
    var asapVal = data.asap;
    if (typeof asapVal === 'number' || (!isNaN(asapVal) && asapVal !== null && asapVal !== '')) {
        var numAsap = parseFloat(asapVal);
        if (!isNaN(numAsap)) {
            if (numAsap > (numAsap > 1 ? 750 : 0.5)) asapVal = "Tinggi";
            else if (numAsap > (numAsap > 1 ? 350 : 0.25)) asapVal = "Waspada";
            else asapVal = "Normal";
        }
    }
    if (asapElem) {
        let asapIcon = '<i class="fas fa-check"></i> Normal';
        if (asapVal === "Waspada" || asapVal === "Sedang") asapIcon = '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)';
        if (asapVal === "Tinggi" || asapVal === "Bahaya") asapIcon = '<i class="fas fa-chart-line"></i> Tinggi (Bahaya)';
        asapElem.innerHTML = asapIcon;
    }

    const isAsapWarning = (data.asap === "Waspada" || data.asap === "Sedang");
    if(boxes.length > 0) setBoxColor(boxes[0], 0, isApiDanger, false);
    if(boxes.length > 1) setBoxColor(boxes[1], 1, isAsapDanger, isAsapWarning);

    setTimeout(() => {
        var suhuElem = document.getElementById("suhu");
        if (suhuElem && data.suhu !== undefined) {
            suhuElem.innerHTML = `${data.suhu} °C <i class="fas fa-thermometer-half"></i>`;
            currentSuhu = `${data.suhu} °C`;
            document.querySelectorAll('.loc-suhu-val').forEach(el => el.innerHTML = currentSuhu);
        }

        var kelembapanElem = document.getElementById("kelembapan");
        if (kelembapanElem && data.kelembapan !== undefined) kelembapanElem.innerHTML = `${data.kelembapan} % <i class="fas fa-tint"></i>`;

        if (isLive) {
            if(boxes.length > 2) setBoxColor(boxes[2], 2, false, isSuhuAbnormal || isKelembapanAbnormal);
            if(boxes.length > 3) setBoxColor(boxes[3], 3, false, isSuhuAbnormal || isKelembapanAbnormal);
        } else {
            if(boxes.length > 2) setBoxColor(boxes[2], 2, false, isSuhuAbnormal || isKelembapanAbnormal || (data.isWarning && dummyState === 2));
            if(boxes.length > 3) setBoxColor(boxes[3], 3, false, isSuhuAbnormal || isKelembapanAbnormal || (data.isWarning && dummyState === 2));
        }
    }, 1000);

    setTimeout(() => {
        var teganganElem = document.getElementById("tegangan");
        if (teganganElem && data.tegangan !== undefined) teganganElem.innerHTML = `${data.tegangan} V <i class="fas fa-bolt"></i>`;

        var arusElem = document.getElementById("arus");
        if (arusElem && data.arus !== undefined) arusElem.innerHTML = `${data.arus} A <i class="fas fa-charging-station"></i>`;

        if (isLive) {
            if(boxes.length > 4) setBoxColor(boxes[4], 4, false, isTeganganOver || isArusOver);
            if(boxes.length > 5) setBoxColor(boxes[5], 5, false, isTeganganOver || isArusOver);
        } else {
            if(boxes.length > 4) setBoxColor(boxes[4], 4, false, isTeganganOver || isArusOver || (data.isWarning && dummyState === 3));
            if(boxes.length > 5) setBoxColor(boxes[5], 5, false, isTeganganOver || isArusOver || (data.isWarning && dummyState === 3));
        }
    }, 2000);

    if (typeof updateLocationStatus === 'function') {
        updateLocationStatus(statusText, isDangerDetected);
    }
    
    if (data.waktu) {
        const lastLabel = dataChart.labels.length > 0 ? dataChart.labels[dataChart.labels.length - 1] : null;
        if (lastLabel !== data.waktu) {
            dataChart.labels.push(data.waktu);
            dataChart.datasets[0].data.push(parseFloat(data.suhu) || 0);
            dataChart.datasets[1].data.push(parseFloat(data.kelembapan) || 0);
            
            var numericAsap = 0;
            if (data.asap_value !== undefined) {
                numericAsap = parseFloat(data.asap_value);
            } else if (data.asap === "Tinggi" || data.asap === "Bahaya") {
                numericAsap = 1;
            } else if (data.asap === "Sedang" || data.asap === "Waspada") {
                numericAsap = 0.5;
            } else if (!isNaN(parseFloat(data.asap))) {
                numericAsap = parseFloat(data.asap);
            }
            dataChart.datasets[2].data.push(numericAsap);
            dataChart.datasets[3].data.push(data.api === "Terdeteksi Api" ? 1 : 0);

            if (dataChart.labels.length > 20) {
                dataChart.labels.shift();
                dataChart.datasets.forEach(ds => ds.data.shift());
            }
            myChart.update();
        }
    }
}

// ================= FUNGSI SEARCH LOKASI DROPDOWN =================
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function filterLocationDropdown() {
    const input = document.getElementById('search-location-input');
    const resultsContainer = document.getElementById('search-location-results');
    const clearBtn = document.getElementById('clear-search-btn');
    if (!input || !resultsContainer) return;

    const filter = input.value.toLowerCase().trim();
    if (clearBtn) {
        clearBtn.style.display = filter.length > 0 ? 'inline-block' : 'none';
    }

    if (filter.length === 0) {
        resultsContainer.style.display = 'none';
        resultsContainer.innerHTML = '';
        return;
    }

    const locationsToSearch = (currentLocationsData && currentLocationsData.length > 0) ? currentLocationsData : initialLocations;
    const filtered = locationsToSearch.filter(loc => {
        const nama = (loc.nama_lokasi || '').toLowerCase();
        const idAlat = (loc.id_alat || '').toLowerCase();
        return nama.includes(filter) || idAlat.includes(filter);
    });

    resultsContainer.innerHTML = '';
    resultsContainer.style.display = 'block';

    if (filtered.length === 0) {
        resultsContainer.innerHTML = '<div style="padding: 12px; font-size: 13px; color: #888; font-style: italic; text-align: center;"><i class="fas fa-info-circle"></i> Tidak ada lokasi yang cocok</div>';
        return;
    }

    filtered.forEach(loc => {
        const nama = loc.nama_lokasi && loc.nama_lokasi.trim() !== '' ? loc.nama_lokasi : (loc.id_alat ? `Indoor (${loc.id_alat})` : `Lokasi ${loc.id}`);
        const idAlat = loc.id_alat || `IND-${loc.id}`;
        const item = document.createElement('div');
        item.style.cssText = 'padding: 10px 16px; border-bottom: 1px solid rgba(0,0,0,0.05); cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 13px; color: #1e3c72; transition: background 0.2s;';
        item.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-building" style="color: #00b4db;"></i>
                <strong>${escapeHtml(nama)}</strong>
            </div>
            <span style="font-size: 11px; background: rgba(0,180,219,0.1); color: #0083b0; padding: 2px 8px; border-radius: 10px; font-weight: 600;">ID: ${escapeHtml(idAlat)}</span>
        `;
        item.onmouseenter = function() { item.style.background = 'rgba(0,180,219,0.08)'; };
        item.onmouseleave = function() { item.style.background = 'transparent'; };
        item.onclick = function() {
            selectSearchLocation(loc.latitude, loc.longitude, nama, idAlat, loc.id);
        };
        resultsContainer.appendChild(item);
    });
}

function selectSearchLocation(lat, lng, nama, idAlat, locId) {
    flyToLocation(lat, lng, nama, idAlat, locId);
    const input = document.getElementById('search-location-input');
    if (input) input.value = nama;
    const resultsContainer = document.getElementById('search-location-results');
    if (resultsContainer) resultsContainer.style.display = 'none';
}

function clearLocationSearch() {
    const input = document.getElementById('search-location-input');
    if (input) {
        input.value = '';
        filterLocationDropdown();
        input.focus();
    }
}

document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.search-location-wrapper');
    const resultsContainer = document.getElementById('search-location-results');
    if (wrapper && resultsContainer && !wrapper.contains(e.target)) {
        resultsContainer.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('search-location-input');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstItem = document.querySelector('#search-location-results > div');
                if (firstItem) {
                    firstItem.click();
                }
            }
        });
    }
});

// ================= FUNGSI MODAL HOME =================
function openHomeModal() {
    var modal = document.getElementById('homeModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeHomeModal() {
    var modal = document.getElementById('homeModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener('click', function(e) {
    var modal = document.getElementById('homeModal');
    if (modal && e.target === modal) {
        closeHomeModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeHomeModal();
    }
});

// Panggil pertama kali, lalu ulangi setiap 10 detik (10000 ms)
fetchDataFromDB();
setInterval(fetchDataFromDB, 10000);
