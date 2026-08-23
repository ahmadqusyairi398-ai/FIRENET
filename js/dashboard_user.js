// ================= JAVASCRIPT DASHBOARD USER OUTDOOR =================

// ================= FUNGSI MODAL LOGOUT =================
function openLogoutModal() {
    var modal = document.getElementById('logoutModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeLogoutModal() {
    var modal = document.getElementById('logoutModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

var logoutModalElem = document.getElementById('logoutModal');
if (logoutModalElem) {
    logoutModalElem.addEventListener('click', function(e) {
        if (e.target === this) {
            closeLogoutModal();
        }
    });
}

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

var homeModalElem = document.getElementById('homeModal');
if (homeModalElem) {
    homeModalElem.addEventListener('click', function(e) {
        if (e.target === this) {
            closeHomeModal();
        }
    });
}

// Tutup modal dengan tombol ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var lModal = document.getElementById('logoutModal');
        var hModal = document.getElementById('homeModal');
        if (lModal && lModal.style.display === 'flex') closeLogoutModal();
        if (hModal && hModal.style.display === 'flex') closeHomeModal();
    }
});

// ================= KOORDINAT DINAMIS DARI DATABASE =================
var cfg = window.FIRENET_CONFIG || {};
var fixedLat = cfg.fixedLat || -1.20249;
var fixedLng = cfg.fixedLng || 116.88708;
var allLocations = cfg.allLocations || [];
var currentSuhu = cfg.currentSuhu || '-';
var initialRealChartData = cfg.initialRealChartData || {
    labels: [], tegangan: [], arus: [], daya: [], suhu: [], kelembapan: [], angin: [], co: []
};

var activeSelectedLocationId = 1;

// Inisialisasi peta
var map = L.map('map').setView([fixedLat, fixedLng], 14);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 3
}).addTo(map);

// Icon marker - AMAN / Standar Lokasi
var safeIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-location-dot" style="color: white; font-size: 14px;"></i></div>',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16],
    className: 'safe-marker'
});

// Icon marker - BAHAYA (Merah)
var dangerIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #dc3545, #b91c1c); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; animation: blink 1s infinite;"><i class="fas fa-exclamation-triangle" style="color: white; font-size: 20px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20],
    className: 'danger-marker'
});

// Icon marker untuk lokasi titik tambahan lainnya (Biru)
var otherIcon = L.divIcon({
    html: '<div style="background: linear-gradient(135deg, #00b4db, #0083b0); width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="fas fa-location-dot" style="color: white; font-size: 14px;"></i></div>',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16],
    className: 'other-marker'
});

var locationMarkers = {};
var sensorMarker = null;
var dangerZone = null;

// Render semua titik lokasi dari database outdoor
if (allLocations.length > 0) {
    allLocations.forEach(function(loc) {
        var popupContent = `
            <div style="min-width: 210px; font-family: 'Segoe UI', sans-serif; text-align: center; padding: 4px;">
                <i class="fas fa-map-marker-alt" style="color: #e85d04; font-size: 20px; margin-bottom: 5px;"></i>
                <div style="font-weight: 700; font-size: 14px; color: #1e3c72;">${loc.nama_lokasi}</div>
                <div style="font-size: 12px; color: #e85d04; font-weight: 600; margin-top: 2px;">
                    ID: ${loc.id_alat} &nbsp;|&nbsp; <i class="fas fa-temperature-high" style="color:#ff6b6b;"></i> Suhu: <span class="loc-suhu-val">${currentSuhu}</span>
                </div>
                <div style="font-size: 12px; background: rgba(0,0,0,0.05); padding: 5px 8px; border-radius: 8px; margin-top: 6px; color: #333;">
                    <i class="fas fa-globe"></i> ${loc.lat.toFixed(6)}, ${loc.lng.toFixed(6)}
                </div>
            </div>
        `;

        if (loc.id === 1) {
            sensorMarker = L.marker([loc.lat, loc.lng], { icon: safeIcon, draggable: false }).addTo(map);
            sensorMarker.bindPopup(popupContent).openPopup();
            
            dangerZone = L.circle([loc.lat, loc.lng], {
                color: '#28a745',
                fillColor: '#28a745',
                fillOpacity: 0.1,
                radius: 500
            }).addTo(map);
            
            locationMarkers[loc.id] = sensorMarker;
        } else {
            var marker = L.marker([loc.lat, loc.lng], { icon: otherIcon }).addTo(map);
            marker.bindPopup(popupContent);
            locationMarkers[loc.id] = marker;
        }

        locationMarkers[loc.id].on('click', function() {
            flyToLocation(loc.lat, loc.lng, loc.id);
        });
    });
}

// Fallback jika tidak ada marker utama
if (!sensorMarker) {
    sensorMarker = L.marker([fixedLat, fixedLng], { icon: safeIcon, draggable: false }).addTo(map);
    dangerZone = L.circle([fixedLat, fixedLng], { color: '#28a745', fillColor: '#28a745', fillOpacity: 0.1, radius: 500 }).addTo(map);
}

var latestRealSensorData = null;

function getDummyDataForLocation(locId, realData) {
    if (!realData) {
        realData = { suhu: 30, kelembapan: 70, asap: 'Normal', api: 'Aman', angin: 3.2, co: 15, tegangan: 220, arus: 1.5, daya: 330, lat: fixedLat, lng: fixedLng };
    }
    
    if (locId === 1 || locId === '1' || locId === 'out_1' || locId === 'out_def_1') {
        return realData;
    }

    var numId = typeof locId === 'number' ? locId : (parseInt(String(locId).replace(/\D/g, '')) || 2);
    var stepIndex = Math.floor(Date.now() / 15000);
    var conditionStep = (stepIndex + numId) % 3;
    var seconds = new Date().getSeconds();
    var noiseSuhu = parseFloat((Math.sin(seconds) * 0.5).toFixed(1));
    
    var suhuVal, humiVal, asapVal, coVal, isDanger;

    if (conditionStep === 0) {
        suhuVal = (26.0 + (numId % 3) + noiseSuhu).toFixed(1);
        humiVal = Math.round(68 + (numId % 4));
        asapVal = "Normal";
        coVal = Math.round(15 + (numId % 5));
        isDanger = false;
    } else if (conditionStep === 1) {
        suhuVal = (42.0 + (numId % 3) + noiseSuhu).toFixed(1);
        humiVal = Math.round(48 - (numId % 3));
        asapVal = "Sedang";
        coVal = Math.round(42 + (numId % 5));
        isDanger = false;
    } else {
        suhuVal = (65.0 + (numId % 3) + noiseSuhu).toFixed(1);
        humiVal = Math.round(28 - (numId % 3));
        asapVal = "Tinggi";
        coVal = Math.round(85 + (numId % 10));
        isDanger = true;
    }

    var windVal = (2.0 + (numId % 4) * 0.9).toFixed(1);

    return {
        suhu: suhuVal,
        kelembapan: humiVal,
        asap: asapVal,
        api: isDanger ? "Terdeteksi Api" : "Aman",
        angin: windVal,
        arah: (numId % 2 === 0) ? "Utara" : "Timur",
        co: coVal,
        tegangan: (219 + (numId % 3)).toFixed(1),
        arus: (1.2 + (numId % 4) * 0.1).toFixed(2),
        daya: (250 + (numId % 6) * 20).toFixed(1),
        lat: realData.lat,
        lng: realData.lng,
        isDanger: isDanger,
        is_dummy: 1
    };
}

function updateSensorDisplayCards(displayData) {
    if (!displayData) return;
    
    var teg = document.getElementById("tegangan");
    if (teg) teg.innerHTML = `${displayData.tegangan || 0} V`;
    var arus = document.getElementById("arus");
    if (arus) arus.innerHTML = `${displayData.arus || 0} A`;
    var daya = document.getElementById("daya");
    if (daya) daya.innerHTML = `${displayData.daya || 0} W`;

    var arahIcon = {
        'Utara': 'up', 'Selatan': 'down', 'Timur': 'right', 'Barat': 'left',
        'Timur Laut': 'up-right', 'Barat Daya': 'down-left', 'Tenggara': 'down-right', 'Barat Laut': 'up-left'
    };
    var arahValue = displayData.arah || 'Timur';
    var arahElem = document.getElementById("arah");
    if (arahElem) arahElem.innerHTML = `<i class="fas fa-arrow-${arahIcon[arahValue] || 'right'}"></i> ${arahValue}`;
    var anginElem = document.getElementById("kecepatan_angin");
    if (anginElem) anginElem.innerHTML = `${displayData.angin || 0} m/s <i class="fas fa-wind"></i>`;

    var asapElement = document.getElementById("asap");
    var asapBox = document.getElementById('asap-box');
    if (asapElement && asapBox) {
        var asapVal = displayData.asap;
        if (asapVal === "Tinggi" || asapVal === "Bahaya") {
            asapElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tinggi (Bahaya)';
            asapElement.className = 'status-bahaya';
            asapBox.classList.add('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else if (asapVal === "Sedang" || asapVal === "Waspada") {
            asapElement.innerHTML = '<i class="fas fa-exclamation-circle"></i> Sedang (Waspada)';
            asapElement.className = 'status-waspada';
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95))";
        } else {
            asapElement.innerHTML = '<i class="fas fa-check-circle"></i> Normal (Aman)';
            asapElement.className = 'status-aman';
            asapBox.classList.remove('pulse-animation');
            asapBox.style.background = "linear-gradient(135deg, rgba(40,167,69,0.95), rgba(32,201,151,0.95))";
        }
    }

    var coElement = document.getElementById("co");
    var coBox = document.getElementById('co-box');
    if (coElement && coBox) {
        var coValue = parseFloat(displayData.co) || 0;
        var coRawStr = String(displayData.co || '');
        if (coValue > 50 || coRawStr === "Tinggi" || coRawStr === "Bahaya") {
            coElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${coValue} ppm (Bahaya)`;
            coElement.className = 'status-bahaya';
            coBox.classList.add('pulse-animation');
            coBox.style.background = "linear-gradient(135deg, rgba(220,38,38,0.95), rgba(185,28,28,0.95))";
        } else if (coValue > 25 || coRawStr === "Sedang" || coRawStr === "Waspada") {
            coElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${coValue} ppm (Waspada)`;
            coElement.className = 'status-waspada';
            coBox.classList.remove('pulse-animation');
            coBox.style.background = "linear-gradient(135deg, rgba(245,158,11,0.95), rgba(217,119,6,0.95))";
        } else {
            coElement.innerHTML = `<i class="fas fa-check-circle"></i> ${coValue} ppm (Aman)`;
            coElement.className = 'status-aman';
            coBox.classList.remove('pulse-animation');
            coBox.style.background = "linear-gradient(135deg, rgba(40,167,69,0.95), rgba(32,201,151,0.95))";
        }
    }

    var suhuElem = document.getElementById("suhu");
    if (suhuElem) suhuElem.innerHTML = `${displayData.suhu || 0} °C <i class="fas fa-thermometer-half"></i>`;
    var humiElem = document.getElementById("kelembapan");
    if (humiElem) humiElem.innerHTML = `${displayData.kelembapan || 0} % <i class="fas fa-tint"></i>`;
    
    currentSuhu = `${displayData.suhu || 0} °C`;
    document.querySelectorAll('.loc-suhu-val').forEach(el => el.innerHTML = currentSuhu);
}

// ================= FUNGSI FLY TO LOCATION =================
function flyToLocation(lat, lng, id) {
    var prevId = activeSelectedLocationId;
    activeSelectedLocationId = id;
    try {
        localStorage.setItem('activeLocationId', id);
    } catch(e) {}
    if (dangerZone) {
        dangerZone.setLatLng([lat, lng]);
    }
    if (prevId !== id) {
        switchLocationChartData(id);
        scheduleNextUpdate();
        fetchDataFromDB();
    }
    map.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
    if (locationMarkers[id]) {
        locationMarkers[id].openPopup();
    }

    var targetLoc = allLocations.find(l => l.id === id);
    if (targetLoc) {
        document.getElementById('location-name-val').innerText = targetLoc.nama_lokasi;
        document.getElementById('location-id-val').innerText = 'ID: ' + targetLoc.id_alat;
        document.getElementById('coordinates').innerText = targetLoc.lat.toFixed(6) + ', ' + targetLoc.lng.toFixed(6);
    }

    document.querySelectorAll('.btn-loc-select').forEach(btn => {
        btn.style.background = 'white';
        btn.style.color = '#333';
    });
    var activeBtn = document.getElementById('btn-loc-' + id);
    if (activeBtn) {
        activeBtn.style.background = 'linear-gradient(135deg, #00b4db, #0083b0)';
        activeBtn.style.color = 'white';
    }

    if (latestRealSensorData) {
        updateSensorDisplayCards(getDummyDataForLocation(id, latestRealSensorData));
    }
}

// ================= FUNGSI UPDATE LOCATION STATUS =================
function updateLocationStatus(activeData, lat, lng) {
    var currentLoc = allLocations.find(l => l.id === activeSelectedLocationId) || { nama_lokasi: 'Lokasi Alat', id_alat: 'OUT-001' };

    var asapVal = activeData ? activeData.asap : 'Normal';
    var coVal = activeData ? (parseFloat(activeData.co) || 0) : 0;
    var isDanger = (asapVal === "Tinggi" || asapVal === "Bahaya" || coVal > 50 || (activeData && activeData.isDanger));
    var isWaspada = (!isDanger && (asapVal === "Sedang" || asapVal === "Waspada" || coVal > 25));

    if (isDanger) {
        if (dangerZone) {
            dangerZone.setStyle({ 
                color: '#dc2626', 
                fillColor: '#dc2626', 
                fillOpacity: 0.3 
            });
        }
        document.getElementById('location-status').innerHTML = '⚠️ BAHAYA - Deteksi Kebakaran!';
        document.getElementById('location-status').style.color = '#dc2626';
        document.getElementById('zone').innerHTML = 'Zona Merah (Peringatan Bahaya)';
        
        if (activeSelectedLocationId === 1 && sensorMarker) {
            sensorMarker.setIcon(dangerIcon);
        }
    } else if (isWaspada) {
        if (dangerZone) {
            dangerZone.setStyle({ 
                color: '#f59e0b', 
                fillColor: '#f59e0b', 
                fillOpacity: 0.2 
            });
        }
        document.getElementById('location-status').innerHTML = '⚠️ WASPADA - Indikasi Asap/Gas';
        document.getElementById('location-status').style.color = '#f59e0b';
        document.getElementById('zone').innerHTML = 'Zona Oranye (Waspada)';
        
        if (activeSelectedLocationId === 1 && sensorMarker) {
            sensorMarker.setIcon(safeIcon);
        }
    } else {
        if (dangerZone) {
            dangerZone.setStyle({ 
                color: '#28a745', 
                fillColor: '#28a745', 
                fillOpacity: 0.1 
            });
        }
        document.getElementById('location-status').innerHTML = 'Aman';
        document.getElementById('location-status').style.color = '#28a745';
        document.getElementById('zone').innerHTML = 'Zona Hijau (Aman)';
        
        if (activeSelectedLocationId === 1 && sensorMarker) {
            sensorMarker.setIcon(safeIcon);
        }
    }
}

// ================= CHART =================
var ctxElem = document.getElementById('myChart');
var ctx = ctxElem ? ctxElem.getContext('2d') : null;
var dataChart = { 
    labels: initialRealChartData.labels, 
    datasets: [
        { label: 'Tegangan Panel Surya (V)', data: initialRealChartData.tegangan, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yListrik' },
        { label: 'Arus Panel Surya (A)', data: initialRealChartData.arus, borderColor: '#ff8c00', backgroundColor: 'rgba(255,140,0,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yKecil' },
        { label: 'Daya Panel Surya (W)', data: initialRealChartData.daya, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yListrik' },
        { label: 'Suhu (°C)', data: initialRealChartData.suhu, borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yEnv' },
        { label: 'Kelembapan (%)', data: initialRealChartData.kelembapan, borderColor: '#4ecdc4', backgroundColor: 'rgba(78,205,196,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yEnv' },
        { label: 'Kecepatan Angin (m/s)', data: initialRealChartData.angin, borderColor: '#3399ff', backgroundColor: 'rgba(51,153,255,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yKecil' },
        { label: 'CO (ppm)', data: initialRealChartData.co, borderColor: '#aa96da', backgroundColor: 'rgba(170,150,218,0.1)', borderWidth: 2, tension: 0.4, fill: true, yAxisID: 'yKecil' }
    ] 
};

function switchLocationChartData(id) {
    if (typeof dataChart === 'undefined' || typeof myChart === 'undefined') return;

    if (id === 1 || id === '1' || id === 'out_1' || id === 'out_def_1') {
        dataChart.labels = [...initialRealChartData.labels];
        dataChart.datasets[0].data = [...initialRealChartData.tegangan];
        dataChart.datasets[1].data = [...initialRealChartData.arus];
        dataChart.datasets[2].data = [...initialRealChartData.daya];
        dataChart.datasets[3].data = [...initialRealChartData.suhu];
        dataChart.datasets[4].data = [...initialRealChartData.kelembapan];
        dataChart.datasets[5].data = [...initialRealChartData.angin];
        dataChart.datasets[6].data = [...initialRealChartData.co];
    } else {
        var numId = typeof id === 'number' ? id : (parseInt(String(id).replace(/\D/g, '')) || 2);
        var labels = [];
        var tegArr = [], arusArr = [], dayaArr = [], suhuArr = [], humiArr = [], anginArr = [], coArr = [];
        var now = new Date();

        for (var i = 10; i >= 0; i--) {
            var t = new Date(now.getTime() - i * 15000);
            var tStr = t.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + t.toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            labels.push(tStr);

            var stepIndex = Math.floor(t.getTime() / 15000);
            var conditionStep = (stepIndex + numId) % 3;

            var suhuVal, humiVal, coVal;
            if (conditionStep === 0) {
                suhuVal = parseFloat((26.0 + (numId % 3)).toFixed(1));
                humiVal = 68;
                coVal = 5.0;
            } else if (conditionStep === 1) {
                suhuVal = parseFloat((42.0 + (numId % 3)).toFixed(1));
                humiVal = 48;
                coVal = 30.0;
            } else {
                suhuVal = parseFloat((65.0 + (numId % 3)).toFixed(1));
                humiVal = 28;
                coVal = 65.0;
            }

            var tegVal = parseFloat((12.0 + (numId % 5) * 1.5).toFixed(1));
            var arusVal = parseFloat((1.2 + (numId % 4) * 0.4).toFixed(2));
            var dayaVal = parseFloat((tegVal * arusVal).toFixed(1));
            var anginVal = parseFloat((2.0 + (numId % 5) * 1.2).toFixed(1));

            tegArr.push(tegVal);
            arusArr.push(arusVal);
            dayaArr.push(dayaVal);
            suhuArr.push(suhuVal);
            humiArr.push(humiVal);
            anginArr.push(anginVal);
            coArr.push(coVal);
        }

        dataChart.labels = labels;
        dataChart.datasets[0].data = tegArr;
        dataChart.datasets[1].data = arusArr;
        dataChart.datasets[2].data = dayaArr;
        dataChart.datasets[3].data = suhuArr;
        dataChart.datasets[4].data = humiArr;
        dataChart.datasets[5].data = anginArr;
        dataChart.datasets[6].data = coArr;
    }
    myChart.update();
}

var myChart = ctx ? new Chart(ctx, { 
    type: 'line', 
    data: dataChart, 
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        animation: { duration: 500 }, 
        plugins: { 
            legend: { 
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'line',
                    boxWidth: 30,
                    padding: 15,
                    font: { size: 12, weight: '600' }
                }
            }, 
            tooltip: { 
                mode: 'index', 
                intersect: false,
                callbacks: {
                    title: function(tooltipItems) {
                        let rawTitle = tooltipItems[0].label || '';
                        return '📅 ' + rawTitle;
                    },
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
            yListrik: { 
                type: 'linear',
                display: true,
                position: 'left',
                min: 0,
                max: 100,
                title: { display: true, text: 'Tegangan (V) / Daya (W)', color: '#28a745' },
                grid: { color: 'rgba(0,0,0,0.05)' }
            }, 
            yEnv: { 
                type: 'linear',
                display: true,
                position: 'right',
                min: 0,
                max: 100,
                title: { display: true, text: 'Suhu (°C) / Kelembapan (%)', color: '#4ecdc4' },
                grid: { drawOnChartArea: false }
            }, 
            yKecil: { 
                type: 'linear',
                display: true,
                position: 'right',
                min: 0,
                max: 100,
                title: { display: true, text: 'Arus (A) / Angin (m/s) / CO (ppm)', color: '#ff8c00' },
                grid: { drawOnChartArea: false }
            }, 
            x: { 
                grid: { display: false }, 
                title: { display: true, text: 'Waktu' },
                ticks: {
                    callback: function(val, index) {
                        let label = this.getLabelForValue(val) || '';
                        if (typeof label === 'string' && label.includes(' ')) {
                            let parts = label.split(' ');
                            return parts[1] || label;
                        }
                        return label;
                    }
                }
            } 
        } 
    } 
}) : null;

// ================= FUNGSI FETCH DATA DARI DATABASE =================
function fetchDataFromDB() {
    fetch('get_latest_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            latestRealSensorData = data;
            if (data.interval_detik && parseInt(data.interval_detik) >= 3) {
                currentMainDeviceIntervalMs = parseInt(data.interval_detik) * 1000;
            }
            var activeData = getDummyDataForLocation(activeSelectedLocationId, data);

            // Update badge tipe data grafik
            var tagElem = document.getElementById('chart-data-type-tag');
            if (tagElem) {
                var isDummy = (data.is_dummy == 1);
                if (isDummy) {
                    tagElem.className = 'data-type-badge dummy-badge';
                    tagElem.innerHTML = '<i class="fas fa-flask"></i> Data Dummy';
                } else {
                    tagElem.className = 'data-type-badge realtime-badge';
                    tagElem.innerHTML = '<i class="fas fa-satellite-dish"></i> Data Real Time';
                }
            }

            // 1. Update status header
            var nowClock = new Date().toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            var stElem = document.getElementById("status");
            if (stElem) stElem.innerHTML = `<i class="fas fa-circle ${data.status === 'Online' ? 'status-online' : ''}"></i> ${data.status || 'Offline'}`;
            var rssiElem = document.getElementById("rssi");
            if (rssiElem) rssiElem.innerHTML = `${data.rssi || '-'} dBm`;
            var ipElem = document.getElementById("ip");
            if (ipElem) ipElem.innerHTML = data.ip || '-';
            var kuotaElem = document.getElementById("kuota-data");
            if (kuotaElem && data.kuota_data) kuotaElem.innerHTML = data.kuota_data;
            var waktuElem = document.getElementById("waktu");
            if (waktuElem) waktuElem.innerHTML = `<i class="far fa-clock"></i> ${nowClock}`;

            // 2. Update Sensor Cards
            updateSensorDisplayCards(activeData);

            // 3. Update Peta & Koordinat
            var activeLoc = allLocations.find(l => l.id === activeSelectedLocationId);
            var curLat = activeLoc ? activeLoc.lat : (data.lat || fixedLat);
            var curLng = activeLoc ? activeLoc.lng : (data.lng || fixedLng);

            if (dangerZone) {
                dangerZone.setLatLng([curLat, curLng]);
            }
            if (activeSelectedLocationId === 1 && sensorMarker && data.lat && data.lng) {
                sensorMarker.setLatLng([data.lat, data.lng]);
                var coordElem = document.getElementById('coordinates');
                if (coordElem) coordElem.innerHTML = `${data.lat}, ${data.lng}`;
                map.panTo(new L.LatLng(data.lat, data.lng));
            }

            // 4. Deteksi Bahaya untuk lokasi yang sedang aktif
            updateLocationStatus(activeData, curLat, curLng);

            // 5. Update Grafik dengan data lokasi aktif
            if (dataChart && myChart) {
                var todayDateStr = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
                var labelWithDate = data.waktu ? (todayDateStr + ' ' + data.waktu) : new Date().toLocaleString('id-ID');
                dataChart.labels.push(labelWithDate);
                dataChart.datasets[0].data.push(parseFloat(activeData.tegangan) || 0);
                dataChart.datasets[1].data.push(parseFloat(activeData.arus) || 0);
                dataChart.datasets[2].data.push(parseFloat(activeData.daya) || 0);
                dataChart.datasets[3].data.push(parseFloat(activeData.suhu) || 0);
                dataChart.datasets[4].data.push(parseFloat(activeData.kelembapan) || 0);
                dataChart.datasets[5].data.push(parseFloat(activeData.angin) || 0);
                dataChart.datasets[6].data.push(parseFloat(activeData.co) || 0);

                if(dataChart.labels.length > 20) {
                    dataChart.labels.shift();
                    dataChart.datasets.forEach(ds => ds.data.shift());
                }
                myChart.update();
            }
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            var stElem = document.getElementById("status");
            if (stElem) stElem.innerHTML = `<i class="fas fa-times-circle" style="color:#dc3545;"></i> Offline`;
        });
}

// ================= JALANKAN FUNGSI (INTERVAL INTERAKTIF) =================
var autoUpdateTimer = null;
var currentMainDeviceIntervalMs = 30000;

function scheduleNextUpdate() {
    if (autoUpdateTimer) clearTimeout(autoUpdateTimer);

    var isMainDevice = (activeSelectedLocationId === 1 || activeSelectedLocationId === '1' || activeSelectedLocationId === 'out_1' || activeSelectedLocationId === 'out_def_1');
    var intervalMs = isMainDevice ? currentMainDeviceIntervalMs : 15000;

    autoUpdateTimer = setTimeout(function() {
        fetchDataFromDB();
        scheduleNextUpdate();
    }, intervalMs);
}

fetchDataFromDB();
scheduleNextUpdate();

// Restore lokasi aktif dari localStorage jika ada
try {
    var savedLocId = localStorage.getItem('activeLocationId');
    if (savedLocId) {
        var numSavedId = parseInt(savedLocId) || 1;
        var foundLoc = allLocations.find(function(l) { return l.id === numSavedId; });
        if (foundLoc) {
            flyToLocation(foundLoc.lat, foundLoc.lng, foundLoc.id);
        }
    }
} catch(e) {}

var coordElem = document.getElementById('coordinates');
if (coordElem) coordElem.innerHTML = `${fixedLat}, ${fixedLng}`;
