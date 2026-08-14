// ================= JAVASCRIPT CHART OUTDOOR =================

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

// ================= FUNGSI CHART =================
var cfg = window.FIRENET_CONFIG || {};
var rawData = cfg.chartData || [];

// Konfigurasi sensor
var sensorConfig = [
    { id: 'co', label: 'Karbon Monoksida (CO)', color: '#9c27b0', unit: 'ppm', group: 'bahaya', min: 0, max: 100, yMax: 120, icon: 'fas fa-industry' },
    { id: 'asap', label: 'Sensor Asap', color: '#ffa502', unit: '%', group: 'bahaya', min: 0, max: 100, yMax: 100, icon: 'fas fa-smog' },
    { id: 'suhu', label: 'Suhu', color: '#ff6b6b', unit: '°C', group: 'env', min: 20, max: 60, yMax: 70, icon: 'fas fa-temperature-high' },
    { id: 'kelembapan', label: 'Kelembapan', color: '#4ecdc4', unit: '%', group: 'env', min: 30, max: 95, yMax: 100, icon: 'fas fa-tint' },
    { id: 'tegangan', label: 'Tegangan', color: '#ffe66d', unit: 'V', group: 'listrik', min: 200, max: 230, yMax: 250, icon: 'fas fa-bolt' },
    { id: 'arus', label: 'Arus', color: '#a8e6cf', unit: 'A', group: 'listrik', min: 0.5, max: 5.5, yMax: 10, icon: 'fas fa-charging-station' },
    { id: 'daya', label: 'Daya', color: '#ff9800', unit: 'W', group: 'listrik', min: 0, max: 1000, yMax: 1200, icon: 'fas fa-solar-panel' },
    { id: 'kecepatan_angin', label: 'Kecepatan Angin', color: '#2196F3', unit: 'm/s', group: 'angin', min: 0, max: 30, yMax: 35, icon: 'fas fa-wind' }
];

var currentMode = "all";
var datasets = [];
var myChart = null;
var fullData = [];
var filteredData = [];

// Fungsi format waktu untuk label sumbu X (HH:MM:SS)
function formatWaktu(waktuStr) {
    if (!waktuStr) return '-';
    try {
        var d = new Date(waktuStr);
        if (isNaN(d.getTime())) return waktuStr;
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch(e) {
        return waktuStr;
    }
}

// Fungsi format waktu lengkap untuk tooltip
function formatWaktuLengkap(waktuStr) {
    if (!waktuStr) return '-';
    try {
        var d = new Date(waktuStr);
        if (isNaN(d.getTime())) return waktuStr;
        return d.toLocaleString('id-ID', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
    } catch(e) {
        return waktuStr;
    }
}

function initDatasets() {
    datasets = [];
    sensorConfig.forEach(function(sensor) {
        datasets.push({
            label: sensor.label,
            data: [],
            borderColor: sensor.color,
            backgroundColor: sensor.color + '20',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 8,
            hidden: currentMode === 'all' ? false : sensor.group !== currentMode,
            yAxisID: sensor.id === 'tegangan' || sensor.id === 'arus' || sensor.id === 'daya' ? 'y-listrik' : 
                     (sensor.id === 'suhu' || sensor.id === 'kelembapan' ? 'y-env' : 
                     (sensor.id === 'kecepatan_angin' ? 'y-angin' :
                     (sensor.id === 'co' || sensor.id === 'asap' ? 'y-bahaya' : 'y-bahaya')))
        });
    });
}

function createChart(labels, dataPoints) {
    var canvasElem = document.getElementById('myChart');
    if (!canvasElem) return;
    var ctx = canvasElem.getContext('2d');
    if (myChart) myChart.destroy();
    initDatasets();
    datasets.forEach(function(ds, idx) {
        var sensorId = sensorConfig[idx].id;
        ds.data = dataPoints.map(function(row) { return row[sensorId]; });
    });
    
    var xLabels = labels.map(function(w) { return formatWaktu(w); });
    
    myChart = new Chart(ctx, {
        type: 'line',
        data: { labels: xLabels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(tooltipItems) {
                            var index = tooltipItems[0].dataIndex;
                            var waktuLengkap = formatWaktuLengkap(labels[index]);
                            return '🕐 ' + waktuLengkap;
                        },
                        label: function(context) {
                            var label = context.dataset.label || '';
                            var value = context.raw;
                            var sensor = sensorConfig.find(function(s) { return s.label === label; });
                            var unit = sensor ? sensor.unit : '';
                            if (sensor && sensor.id === 'co') {
                                var status = value > 50 ? '🔥 BAHAYA' : (value > 35 ? '⚠️ WASPADA' : '✅ AMAN');
                                return `${label}: ${value} ${unit} - ${status}`;
                            }
                            if (sensor && sensor.id === 'asap') {
                                var status = value > 70 ? '🔥 TINGGI' : (value > 40 ? '⚠️ SEDANG' : '✅ NORMAL');
                                return `${label}: ${value} ${unit} - ${status}`;
                            }
                            return `${label}: ${value} ${unit}`;
                        }
                    }
                }
            },
            scales: {
                x: { 
                    display: true,
                    grid: { display: true },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 30,
                        font: { size: 10 },
                        autoSkip: true,
                        maxTicksLimit: 15
                    }
                },
                'y-bahaya': {
                    position: 'left', 
                    grace: '10%',
                    grid: { color: 'rgba(255,107,107,0.2)', drawOnChartArea: true },
                    title: { display: true, text: 'CO (ppm) / Asap (%)', color: '#ff6b6b' },
                    ticks: { callback: function(v) { return v; } },
                    display: true
                },
                'y-env': {
                    position: 'right', 
                    grace: '5%',
                    grid: { color: 'rgba(78,205,196,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Suhu (°C) / Kelembapan (%)', color: '#4ecdc4' },
                    ticks: { callback: function(v) { return v + (v > 50 ? '%' : '°C'); } },
                    display: false
                },
                'y-listrik': {
                    position: 'right', 
                    min: 0,
                    max: 100,
                    grid: { color: 'rgba(255,152,0,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Tegangan (V) / Arus (A) / Daya (W)', color: '#ff9800' },
                    ticks: { callback: function(v) { return v + (v > 50 ? 'W' : (v > 10 ? 'V' : 'A')); } },
                    display: false
                },
                'y-angin': {
                    position: 'right', 
                    grace: '10%',
                    grid: { color: 'rgba(33,150,243,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Kecepatan Angin (m/s)', color: '#2196F3' },
                    ticks: { callback: function(v) { return v + ' m/s'; } },
                    display: false
                }
            }
        }
    });
    updateYAxisVisibility();
    updateLegend();
}

function updateYAxisVisibility() {
    if (!myChart) return;
    var yBahaya = myChart.options.scales['y-bahaya'];
    var yEnv = myChart.options.scales['y-env'];
    var yListrik = myChart.options.scales['y-listrik'];
    var yAngin = myChart.options.scales['y-angin'];
    
    yBahaya.display = false;
    yEnv.display = false;
    yListrik.display = false;
    yAngin.display = false;
    
    if (currentMode === 'bahaya') {
        yBahaya.display = true;
    } else if (currentMode === 'env') {
        yEnv.display = true;
    } else if (currentMode === 'listrik') {
        yListrik.display = true;
    } else if (currentMode === 'angin') {
        yAngin.display = true;
    } else {
        var hasBahaya = false, hasEnv = false, hasListrik = false, hasAngin = false;
        datasets.forEach(function(ds) {
            if (ds.hidden) return;
            var sensor = sensorConfig.find(function(s) { return s.label === ds.label; });
            if (sensor) {
                if (sensor.group === 'bahaya') hasBahaya = true;
                else if (sensor.group === 'env') hasEnv = true;
                else if (sensor.group === 'listrik') hasListrik = true;
                else if (sensor.group === 'angin') hasAngin = true;
            }
        });
        if (hasBahaya) yBahaya.display = true;
        if (hasEnv) yEnv.display = true;
        if (hasListrik) yListrik.display = true;
        if (hasAngin) yAngin.display = true;
    }
    
    yBahaya.grid.drawOnChartArea = yBahaya.display;
    yEnv.grid.drawOnChartArea = yEnv.display;
    yListrik.grid.drawOnChartArea = yListrik.display;
    yAngin.grid.drawOnChartArea = yAngin.display;
    
    myChart.update();
}

function updateLegend() {
    var container = document.getElementById('chartLegend');
    if (!container) return;
    container.innerHTML = '';
    datasets.forEach(function(ds, idx) {
        if (ds.hidden) return;
        var sensor = sensorConfig[idx];
        var legendItem = document.createElement('div');
        legendItem.className = 'legend-item';
        legendItem.onclick = function() { toggleDataset(idx); };
        legendItem.innerHTML = `
            <div class="legend-key-icon" style="color: ${sensor.color}; background: ${sensor.color}20; border: 1.5px solid ${sensor.color};" title="Kunci Simbol Legenda: ${sensor.label}">
                <i class="${sensor.icon}"></i>
            </div>
            <span class="legend-text">${sensor.label}</span>
        `;
        container.appendChild(legendItem);
    });
}

function toggleDataset(index) {
    datasets[index].hidden = !datasets[index].hidden;
    updateLegend();
    updateYAxisVisibility();
    myChart.update();
}

function setMode(mode, element) {
    currentMode = mode;
    document.querySelectorAll('.tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
    element.classList.add('active');
    
    var visibleGroups = [];
    if (mode === 'all') visibleGroups = ['bahaya', 'env', 'listrik', 'angin'];
    else if (mode === 'bahaya') visibleGroups = ['bahaya'];
    else if (mode === 'env') visibleGroups = ['env'];
    else if (mode === 'listrik') visibleGroups = ['listrik'];
    else if (mode === 'angin') visibleGroups = ['angin'];
    
    datasets.forEach(function(ds, idx) {
        var sensor = sensorConfig[idx];
        ds.hidden = !visibleGroups.includes(sensor.group);
    });
    
    updateYAxisVisibility();
    updateLegend();
    myChart.update();
}

function filterData() {
    var fromDate = document.getElementById('dateFrom').value;
    var toDate = document.getElementById('dateTo').value;
    if (!fromDate && !toDate) {
        filteredData = [...fullData];
    } else {
        filteredData = fullData.filter(function(item) {
            if (!item.waktu) return true;
            var itemDate = item.waktu.split(' ')[0];
            var ok = true;
            if (fromDate && itemDate < fromDate) ok = false;
            if (toDate && itemDate > toDate) ok = false;
            return ok;
        });
    }
    if (filteredData.length === 0) {
        createChart([], []);
        alert('Tidak ada data dalam rentang tanggal tersebut.');
        return;
    }
    var labels = filteredData.map(function(d) { return d.waktu; });
    createChart(labels, filteredData);
}

var realDataOriginal = [];
var activeLocationId = 1;

function changeChartLocation(locId) {
    activeLocationId = parseInt(locId) || 1;
    try {
        localStorage.setItem('activeLocationId', activeLocationId);
    } catch(e) {}
    var isDummy = (activeLocationId !== 1);
    
    var tag = document.getElementById('data-type-tag');
    if (tag) {
        if (isDummy) {
            tag.className = 'data-type-badge dummy-badge';
            tag.innerHTML = '<i class="fas fa-flask"></i> Data Dummy';
        } else {
            tag.className = 'data-type-badge realtime-badge';
            tag.innerHTML = '<i class="fas fa-satellite-dish"></i> Data Real Time';
        }
    }

    if (activeLocationId === 1) {
        fullData = [...realDataOriginal];
    } else {
        fullData = generateDummyChartData(activeLocationId);
    }
    filteredData = [...fullData];
    var labels = filteredData.map(function(d) { return d.waktu; });
    createChart(labels, filteredData);
}

function generateDummyChartData(locId) {
    var numId = typeof locId === 'number' ? locId : 2;
    var now = new Date();
    var result = [];

    for (var i = 20; i >= 0; i--) {
        var t = new Date(now.getTime() - i * 15000);
        var tStr = t.getFullYear() + '-' +
                     String(t.getMonth() + 1).padStart(2, '0') + '-' +
                     String(t.getDate()).padStart(2, '0') + ' ' +
                     String(t.getHours()).padStart(2, '0') + ':' +
                     String(t.getMinutes()).padStart(2, '0') + ':' +
                     String(t.getSeconds()).padStart(2, '0');

        var stepIndex = Math.floor(t.getTime() / 15000);
        var conditionStep = (stepIndex + numId) % 3;

        var suhuVal, humiVal, asapVal, coVal;
        if (conditionStep === 0) {
            suhuVal = parseFloat((26.0 + (numId % 3)).toFixed(1));
            humiVal = 68;
            asapVal = 10;
            coVal = 15;
        } else if (conditionStep === 1) {
            suhuVal = parseFloat((42.0 + (numId % 3)).toFixed(1));
            humiVal = 48;
            asapVal = 45;
            coVal = 42;
        } else {
            suhuVal = parseFloat((65.0 + (numId % 3)).toFixed(1));
            humiVal = 28;
            asapVal = 85;
            coVal = 85;
        }

        var windVal = parseFloat((2.0 + (numId % 4) * 0.9).toFixed(1));
        var tegVal = parseFloat((219 + (numId % 3)).toFixed(1));
        var arusVal = parseFloat((1.2 + (numId % 4) * 0.1).toFixed(2));
        var dayaVal = parseFloat((250 + (numId % 6) * 20).toFixed(1));

        result.push({
            waktu: tStr,
            asap: asapVal,
            suhu: suhuVal,
            kelembapan: humiVal,
            tegangan: tegVal,
            arus: arusVal,
            daya: dayaVal,
            kecepatan_angin: windVal,
            arah_angin: (numId % 2 === 0) ? 'Utara' : 'Timur',
            co: coVal
        });
    }
    return result;
}

document.addEventListener('DOMContentLoaded', function() {
    realDataOriginal = rawData.map(function(row) {
        return {
            waktu: row.waktu || '',
            asap: typeof row.asap === 'number' ? row.asap : 0,
            suhu: typeof row.suhu === 'number' ? row.suhu : 0,
            kelembapan: typeof row.kelembapan === 'number' ? row.kelembapan : 0,
            tegangan: typeof row.tegangan === 'number' ? row.tegangan : 0,
            arus: typeof row.arus === 'number' ? row.arus : 0,
            daya: typeof row.daya === 'number' ? row.daya : 0,
            kecepatan_angin: typeof row.kecepatan_angin === 'number' ? row.kecepatan_angin : 0,
            arah_angin: row.arah_angin || '-',
            co: typeof row.co === 'number' ? row.co : 0
        };
    });
    
    fullData = [...realDataOriginal];
    
    try {
        var savedLocId = localStorage.getItem('activeLocationId');
        if (savedLocId) {
            var numSavedId = parseInt(savedLocId) || 1;
            var locSelect = document.getElementById('locationSelect');
            if (locSelect) {
                locSelect.value = numSavedId;
            }
            changeChartLocation(numSavedId);
            return;
        }
    } catch(e) {}

    if (fullData.length === 0) {
        createChart([], []);
        console.warn('Tidak ada data sensor di database. Grafik akan kosong.');
        alert('Belum ada data sensor. Grafik akan kosong, menunggu data dari database.');
        return;
    }
    filteredData = [...fullData];
    var labels = filteredData.map(function(d) { return d.waktu; });
    createChart(labels, filteredData);
});
