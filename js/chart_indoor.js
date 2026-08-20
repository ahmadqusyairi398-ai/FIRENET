/**
 * Script Logika Frontend Chart Indoor
 * FireNetWork Indoor System
 */

// ================= FUNGSI MODAL LOGOUT =================
function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

document.getElementById('logoutModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});

// ================= FUNGSI MODAL HOME =================
function openHomeModal() {
    document.getElementById('homeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeHomeModal() {
    document.getElementById('homeModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

document.getElementById('homeModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeHomeModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const logoutM = document.getElementById('logoutModal');
        const homeM = document.getElementById('homeModal');
        if (logoutM && logoutM.style.display === 'flex') {
            closeLogoutModal();
        }
        if (homeM && homeM.style.display === 'flex') {
            closeHomeModal();
        }
    }
});

// ================= FUNGSI CHART =================
const rawData = (typeof window.INDOOR_CHART_DATA !== 'undefined') ? window.INDOOR_CHART_DATA : [];

const sensorConfig = [
    { id: 'api', label: 'Sensor Api', color: '#dc3545', unit: '', group: 'bahaya', min: 0, max: 100, yMax: 120 },
    { id: 'asap', label: 'Sensor Asap', color: '#ffa502', unit: '', group: 'bahaya', min: 0, max: 100, yMax: 120 },
    { id: 'suhu', label: 'Suhu', color: '#ff6b6b', unit: '°C', group: 'env', min: 20, max: 60, yMax: 70 },
    { id: 'kelembapan', label: 'Kelembapan', color: '#4ecdc4', unit: '%', group: 'env', min: 30, max: 95, yMax: 100 },
    { id: 'tegangan', label: 'Tegangan', color: '#ffe66d', unit: 'V', group: 'listrik', min: 200, max: 230, yMax: 250 },
    { id: 'arus', label: 'Arus', color: '#a8e6cf', unit: 'A', group: 'listrik', min: 0.5, max: 5.5, yMax: 10 }
];

let currentMode = "all";
let datasets = [];
let myChart = null;
let fullData = [];
let filteredData = [];

function formatWaktu(waktuStr) {
    if (!waktuStr) return '-';
    try {
        const d = new Date(waktuStr);
        if (isNaN(d.getTime())) return waktuStr;
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return waktuStr;
    }
}

function formatWaktuLengkap(waktuStr) {
    if (!waktuStr) return '-';
    try {
        const d = new Date(waktuStr);
        if (isNaN(d.getTime())) return waktuStr;
        return d.toLocaleString('id-ID', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
    } catch {
        return waktuStr;
    }
}

function initDatasets() {
    datasets = [];
    sensorConfig.forEach(sensor => {
        datasets.push({
            label: sensor.label,
            data: [],
            borderColor: sensor.color,
            backgroundColor: sensor.color + '20',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 6,
            hidden: sensor.group !== 'all',
            yAxisID: sensor.id === 'tegangan' || sensor.id === 'arus' ? 'y-listrik' : 
                     (sensor.id === 'suhu' || sensor.id === 'kelembapan' ? 'y-env' : 'y-bahaya')
        });
    });
}

function createChart(labels, dataPoints) {
    const chartEl = document.getElementById('myChart');
    if (!chartEl) return;
    const ctx = chartEl.getContext('2d');
    if (myChart) myChart.destroy();
    initDatasets();
    datasets.forEach((ds, idx) => {
        const sensorId = sensorConfig[idx].id;
        ds.data = dataPoints.map(row => row[sensorId]);
    });
    
    const xLabels = labels.map(w => formatWaktu(w));
    
    myChart = new Chart(ctx, {
        type: 'line',
        data: { labels: xLabels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 300 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(tooltipItems) {
                            const index = tooltipItems[0].dataIndex;
                            const waktuLengkap = formatWaktuLengkap(labels[index]);
                            return '🕐 ' + waktuLengkap;
                        },
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            let sensor = sensorConfig.find(s => s.label === label);
                            let unit = sensor ? sensor.unit : '';
                            if (sensor && sensor.id === 'api') {
                                let status = value > 70 ? '🔥 TERDETEKSI API' : (value > 40 ? '⚠️ POTENSI' : '✅ AMAN');
                                return `${label}: ${value} ${unit} - ${status}`;
                            }
                            if (sensor && sensor.id === 'asap') {
                                let status = value > 70 ? '🔥 TINGGI' : (value > 40 ? '⚠️ SEDANG' : '✅ NORMAL');
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
                    beginAtZero: true, 
                    max: 120,
                    grid: { color: 'rgba(255,107,107,0.2)', drawOnChartArea: true },
                    title: { display: true, text: 'Api / Asap', color: '#ff6b6b' },
                    ticks: { callback: function(v) { return v; } },
                    display: true
                },
                'y-env': {
                    position: 'right', 
                    beginAtZero: true, 
                    max: 100,
                    grid: { color: 'rgba(78,205,196,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Suhu (°C) / Kelembapan (%)', color: '#4ecdc4' },
                    ticks: { callback: function(v) { return v; } },
                    display: false
                },
                'y-listrik': {
                    position: 'right', 
                    beginAtZero: false, 
                    min: 0, 
                    max: 250,
                    grid: { color: 'rgba(255,152,0,0.2)', drawOnChartArea: false },
                    title: { display: true, text: 'Tegangan (V) / Arus (A)', color: '#ff9800' },
                    ticks: { callback: function(v) { return v; } },
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
    const yBahaya = myChart.options.scales['y-bahaya'];
    const yEnv = myChart.options.scales['y-env'];
    const yListrik = myChart.options.scales['y-listrik'];
    
    yBahaya.display = false;
    yEnv.display = false;
    yListrik.display = false;
    
    if (currentMode === 'bahaya') {
        yBahaya.display = true;
    } else if (currentMode === 'env') {
        yEnv.display = true;
    } else if (currentMode === 'listrik') {
        yListrik.display = true;
    } else {
        let hasBahaya = false, hasEnv = false, hasListrik = false;
        datasets.forEach(ds => {
            if (ds.hidden) return;
            const sensor = sensorConfig.find(s => s.label === ds.label);
            if (sensor) {
                if (sensor.group === 'bahaya') hasBahaya = true;
                else if (sensor.group === 'env') hasEnv = true;
                else if (sensor.group === 'listrik') hasListrik = true;
            }
        });
        if (hasBahaya) yBahaya.display = true;
        if (hasEnv) yEnv.display = true;
        if (hasListrik) yListrik.display = true;
    }
    
    yBahaya.grid.drawOnChartArea = yBahaya.display;
    yEnv.grid.drawOnChartArea = yEnv.display;
    yListrik.grid.drawOnChartArea = yListrik.display;
    
    myChart.update();
}

function updateLegend() {
    const container = document.getElementById('chartLegend');
    if (!container) return;
    container.innerHTML = '';
    datasets.forEach((ds, idx) => {
        if (ds.hidden) return;
        const sensor = sensorConfig[idx];
        const legendItem = document.createElement('div');
        legendItem.className = 'legend-item';
        legendItem.onclick = () => toggleDataset(idx);
        legendItem.innerHTML = `
            <div class="legend-color" style="background: ${sensor.color}"></div>
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
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    if (element) element.classList.add('active');
    
    let visibleGroups = [];
    if (mode === 'all') visibleGroups = ['bahaya', 'env', 'listrik'];
    else if (mode === 'bahaya') visibleGroups = ['bahaya'];
    else if (mode === 'env') visibleGroups = ['env'];
    else if (mode === 'listrik') visibleGroups = ['listrik'];
    
    datasets.forEach((ds, idx) => {
        const sensor = sensorConfig[idx];
        ds.hidden = !visibleGroups.includes(sensor.group);
    });
    
    updateYAxisVisibility();
    updateLegend();
    myChart.update();
}

function generateDummyHistory(count) {
    let dummyHistory = [];
    let now = new Date();
    for (let i = 0; i < count; i++) {
        let timeObj = new Date(now.getTime() - ((count - 1 - i) * 10000));
        let timeStr = timeObj.getFullYear() + "-" +
                      String(timeObj.getMonth()+1).padStart(2,'0') + "-" +
                      String(timeObj.getDate()).padStart(2,'0') + " " +
                      String(timeObj.getHours()).padStart(2,'0') + ":" +
                      String(timeObj.getMinutes()).padStart(2,'0') + ":" +
                      String(timeObj.getSeconds()).padStart(2,'0');

        let api = Math.random() > 0.9 ? 100 : 0;
        let asap = Math.floor(Math.random() * 80 + 10);
        let suhu = Math.floor(Math.random() * 20 + 25);
        let kelembapan = Math.floor(Math.random() * 40 + 30);
        let tegangan = Math.floor(Math.random() * 15 + 215);
        let arus = (Math.random() * 5 + 2).toFixed(2);

        if (api > 0) { suhu += 20; kelembapan -= 15; asap += 40; }

        dummyHistory.push({
            waktu: timeStr, api: api, asap: asap, suhu: suhu,
            kelembapan: kelembapan, tegangan: tegangan, arus: parseFloat(arus)
        });
    }
    return dummyHistory;
}

function filterData() {
    const locSelect = document.getElementById('locationSelect');
    const locationVal = locSelect ? locSelect.value : 'LOK-002';

    const chartBadge = document.getElementById('chart-badge');
    if (chartBadge) {
        if (locationVal === 'LOK-002') {
            chartBadge.innerHTML = '<i class="fas fa-bolt"></i> Live (Real-Time)';
            chartBadge.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        } else {
            chartBadge.innerHTML = '<i class="fas fa-flask"></i> Data Dummy (Simulasi)';
            chartBadge.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        }
    }

    let sourceData = fullData;
    if (locationVal !== 'LOK-002') {
        sourceData = generateDummyHistory(100);
    }

    const fromDate = document.getElementById('dateFrom').value;
    const toDate = document.getElementById('dateTo').value;

    if (!fromDate && !toDate) {
        filteredData = [...sourceData];
    } else {
        filteredData = sourceData.filter(item => {
            if (!item.waktu) return true;
            const itemDate = item.waktu.split(' ')[0];
            let ok = true;
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
    const labels = filteredData.map(d => d.waktu);
    createChart(labels, filteredData);

    const activeTab = document.querySelector('.tab-btn.active');
    if (activeTab) {
        setMode(currentMode, activeTab);
    }
}

function resetFilter() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    filterData();
}

document.addEventListener('DOMContentLoaded', () => {
    fullData = rawData.map(row => ({
        waktu: row.waktu || '',
        asap: typeof row.asap === 'number' ? row.asap : 0,
        suhu: typeof row.suhu === 'number' ? row.suhu : 0,
        kelembapan: typeof row.kelembapan === 'number' ? row.kelembapan : 0,
        tegangan: typeof row.tegangan === 'number' ? row.tegangan : 0,
        arus: typeof row.arus === 'number' ? row.arus : 0,
        api: typeof row.api === 'number' ? row.api : 0
    }));
    
    filterData();
});
