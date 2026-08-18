/**
 * Script Logika Frontend Tabel Indoor
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

// ================= FUNGSI TABEL =================
// Mengambil data awal dari window.INDOOR_SENSOR_DATA yang di-inject dari PHP
const rawDataPHP = (typeof window.INDOOR_SENSOR_DATA !== 'undefined') ? window.INDOOR_SENSOR_DATA : [];

let sensorData = rawDataPHP.map((item, index) => {
    let formattedDate = item.tanggal_waktu || '-';
    let dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
    return {
        id: item.id,
        no: index + 1,
        tanggal_waktu: formattedDate,
        tanggal: dateOnly,
        api: item.api !== null && item.api !== undefined ? item.api : '0',
        asap: item.asap !== null && item.asap !== undefined ? parseFloat(item.asap).toFixed(2) : '0',
        suhu: item.suhu !== null && item.suhu !== undefined ? parseFloat(item.suhu).toFixed(1) : '0',
        kelembapan: item.kelembapan !== null && item.kelembapan !== undefined ? parseFloat(item.kelembapan).toFixed(1) : '0',
        tegangan: item.tegangan !== null && item.tegangan !== undefined ? parseFloat(item.tegangan).toFixed(1) : '0',
        arus: item.arus !== null && item.arus !== undefined ? parseFloat(item.arus).toFixed(2) : '0',
        rssi: item.rssi !== null && item.rssi !== undefined ? item.rssi : '0',
        is_dummy: parseInt(item.is_dummy || 0)
    };
});

let currentData = [...sensorData];
let dataTable = null;

function getStatusClass(value, type) {
    let num = parseFloat(value);
    let strVal = String(value || '').trim().toLowerCase();
    if (type === 'api') {
        if ((!isNaN(num) && num > 0.5) || strVal === 'terdeteksi api' || strVal === 'dekat' || strVal === 'sedang' || strVal === 'bahaya' || strVal === 'tinggi') return 'status-bahaya';
        return 'status-aman';
    }
    if (type === 'asap') {
        if ((!isNaN(num) && num > 750) || strVal === 'tinggi' || strVal === 'bahaya') return 'status-bahaya';
        if ((!isNaN(num) && num > 350) || strVal === 'sedang' || strVal === 'waspada') return 'status-waspada';
        return 'status-aman';
    }
    return '';
}

function getStatusIcon(value, type) {
    let num = parseFloat(value);
    let strVal = String(value || '').trim().toLowerCase();
    if (type === 'api') {
        if ((!isNaN(num) && num > 0.5) || strVal === 'terdeteksi api' || strVal === 'dekat' || strVal === 'sedang' || strVal === 'bahaya' || strVal === 'tinggi') return '<i class="fas fa-exclamation-triangle"></i>';
        return '<i class="fas fa-check-circle"></i>';
    }
    if (type === 'asap') {
        if ((!isNaN(num) && num > 750) || strVal === 'tinggi' || strVal === 'bahaya') return '<i class="fas fa-exclamation-triangle"></i>';
        if ((!isNaN(num) && num > 350) || strVal === 'sedang' || strVal === 'waspada') return '<i class="fas fa-exclamation-circle"></i>';
        return '<i class="fas fa-check-circle"></i>';
    }
    return '';
}

function getStatusText(value, type) {
    let num = parseFloat(value);
    let strVal = String(value || '').trim().toLowerCase();
    if (type === 'api') {
        if ((!isNaN(num) && num > 0.5) || strVal === 'terdeteksi api' || strVal === 'dekat' || strVal === 'sedang' || strVal === 'bahaya' || strVal === 'tinggi') return 'Terdeteksi Api';
        return 'Aman';
    }
    if (type === 'asap') {
        if ((!isNaN(num) && num > 750) || strVal === 'tinggi' || strVal === 'bahaya') return 'Tinggi';
        if ((!isNaN(num) && num > 350) || strVal === 'sedang' || strVal === 'waspada') return 'Sedang';
        return 'Normal';
    }
    return '';
}

function createRow(item) {
    let apiText = getStatusText(item.api, 'api');
    let apiNumber = (apiText === "Aman") ? "0" : "100";
    let apiDisplay = `${getStatusIcon(item.api, 'api')} ${apiText} (${apiNumber})`;

    return [
        item.no,
        item.tanggal_waktu,
        `<span class="${getStatusClass(item.api, 'api')}">${apiDisplay}</span>`,
        `<span class="${getStatusClass(item.asap, 'asap')}">${getStatusIcon(item.asap, 'asap')} ${getStatusText(item.asap, 'asap')} (${item.asap})</span>`,
        `${item.suhu} °C`,
        `${item.kelembapan} %`,
        `${item.tegangan} V`,
        `${item.arus} A`,
        `${item.rssi} dBm`,
        item.id ? `<button onclick="hapusBaris(${item.id})" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;"><i class="fas fa-trash"></i> Hapus</button>` : `<span style="color:#aaa; font-size:12px;">(Simulasi)</span>`
    ];
}

function updateDataTable(data) {
    const rows = data.map(createRow);
    if (dataTable) {
        dataTable.clear();
        if (rows.length > 0) dataTable.rows.add(rows);
        dataTable.draw(false);
    }
}

function initDataTable(data) {
    if (dataTable) dataTable.destroy();
    const rows = data.map(createRow);
    
    const tableColumns = [
        { title: "No" }, 
        { title: "Tanggal & Waktu" }, 
        { title: "Api" }, 
        { title: "Asap" }, 
        { title: "Suhu (°C)" }, 
        { title: "Kelembapan (%)" }, 
        { title: "Tegangan (V)" }, 
        { title: "Arus (A)" }, 
        { title: "RSSI (dBm)" },
        { title: "Aksi" }
    ];

    dataTable = $('#sensorTable').DataTable({
        data: rows,
        columns: tableColumns,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json",
            emptyTable: "Tidak ada data sensor yang tersedia. Silakan tambahkan data terlebih dahulu."
        },
        pageLength: 10, 
        lengthMenu: [5, 10, 25, 50, 100], 
        order: [[1, 'desc']], 
        scrollX: true,
        columnDefs: [
            { width: "5%", targets: 0 },
            { width: "14%", targets: 1 },
            { width: "11%", targets: 2 },
            { width: "11%", targets: 3 },
            { width: "9%", targets: 4 },
            { width: "9%", targets: 5 },
            { width: "9%", targets: 6 },
            { width: "9%", targets: 7 },
            { width: "8%", targets: 8 },
            { width: "15%", targets: 9, orderable: false }
        ]
    });
}

function generateDummyTable(count) {
    let dummyTable = [];
    let now = new Date();
    for (let i = 0; i < count; i++) {
        let timeObj = new Date(now.getTime() - ((count - 1 - i) * 10000));
        let timeStr = timeObj.getFullYear() + "-" +
                      String(timeObj.getMonth()+1).padStart(2,'0') + "-" +
                      String(timeObj.getDate()).padStart(2,'0') + " " +
                      String(timeObj.getHours()).padStart(2,'0') + ":" +
                      String(timeObj.getMinutes()).padStart(2,'0') + ":" +
                      String(timeObj.getSeconds()).padStart(2,'0');
        let dateOnly = timeStr.split(' ')[0];

        let api = Math.random() > 0.9 ? 100 : 0;
        let asap = Math.floor(Math.random() * 80 + 10);
        let suhu = Math.floor(Math.random() * 20 + 25);
        let kelembapan = Math.floor(Math.random() * 40 + 30);
        let tegangan = (Math.random() * 15 + 215).toFixed(1);
        let arus = (Math.random() * 5 + 2).toFixed(2);

        if (api > 0) { suhu += 20; kelembapan -= 15; asap += 40; }

        dummyTable.push({
            tanggal_waktu: timeStr,
            tanggal: dateOnly,
            api: api.toString(),
            asap: asap.toFixed(2),
            suhu: suhu.toFixed(1),
            kelembapan: kelembapan.toFixed(1),
            tegangan: tegangan,
            arus: arus,
            rssi: Math.floor(Math.random() * 20 - 70).toString()
        });
    }
    return dummyTable.reverse();
}

function applyFilter() {
    const locSelect = document.getElementById('locationSelect');
    const locationVal = locSelect ? locSelect.value : 'LOK-002';

    const tableBadge = document.getElementById('table-badge');
    if (tableBadge) {
        if (locationVal === 'LOK-002') {
            tableBadge.innerHTML = '<i class="fas fa-bolt"></i> Live (Real-Time)';
            tableBadge.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        } else {
            tableBadge.innerHTML = '<i class="fas fa-flask"></i> Data Dummy (Simulasi)';
            tableBadge.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        }
    }

    let sourceData = [];
    if (locationVal === 'LOK-002') {
        sourceData = sensorData.filter(item => !item.is_dummy || item.is_dummy === 0);
    } else {
        sourceData = sensorData.filter(item => item.is_dummy === 1);
        if (sourceData.length === 0) {
            sourceData = generateDummyTable(50);
        }
    }

    let filteredData = [...sourceData];
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    if (startDate) filteredData = filteredData.filter(item => item.tanggal >= startDate);
    if (endDate) filteredData = filteredData.filter(item => item.tanggal <= endDate);

    filteredData.forEach((item, idx) => item.no = idx + 1);

    currentData = filteredData;
    updateDataTable(currentData);

    if (filteredData.length === 0) alert('Tidak ada data yang sesuai dengan filter!');
}

function resetFilter() {
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    applyFilter();
}

function exportToExcel() {
    if (currentData.length === 0) { 
        alert('Tidak ada data untuk diexport!'); 
        return; 
    }
    
    let csv = "No,Tanggal & Waktu,Api,Asap,Suhu (°C),Kelembapan (%),Tegangan (V),Arus (A),RSSI (dBm)\n";
    currentData.forEach((item, idx) => {
        let statusApi = getStatusText(item.api, 'api');
        let statusAsap = getStatusText(item.asap, 'asap');
        csv += `"${idx+1}","${item.tanggal_waktu}","${statusApi} (${item.api})","${statusAsap} (${item.asap})","${item.suhu}","${item.kelembapan}","${item.tegangan}","${item.arus}","${item.rssi}"\n`;
    });
    
    const blob = new Blob(["\uFEFF" + csv], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `data_sensor_indoor_${new Date().toISOString().slice(0,10)}.xls`;
    document.body.appendChild(a); 
    a.click(); 
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    alert(`Berhasil mengexport ${currentData.length} data ke Excel!`);
}

function hapusBaris(idData) {
    if (confirm("Apakah Anda yakin ingin menghapus data ini? Aksi ini permanen.")) {
        fetch('api_hapus_baris_indoor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: idData })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert("Data berhasil dihapus!");
                location.reload();
            } else {
                alert("Gagal: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Terjadi kesalahan sistem saat menghapus data.");
        });
    }
}

$(document).ready(function() {
    if (sensorData && sensorData.length > 0) {
        initDataTable(sensorData);
        applyFilter();
    } else {
        $('#sensorTable').DataTable({
            data: [],
            columns: [
                { title: "No" }, 
                { title: "Tanggal & Waktu" }, 
                { title: "Api" }, 
                { title: "Asap" }, 
                { title: "Suhu (°C)" }, 
                { title: "Kelembapan (%)" }, 
                { title: "Tegangan (V)" }, 
                { title: "Arus (A)" }, 
                { title: "RSSI (dBm)" },
                { title: "Aksi" }
            ],
            language: { 
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json",
                emptyTable: "Tidak ada data sensor yang tersedia. Silakan tambahkan data terlebih dahulu." 
            },
            scrollX: true
        });
        applyFilter();
    }

    function fetchTableDataRealtime() {
        fetch('get_table_data.php?device=indoor&with_storage=1')
            .then(response => response.json())
            .then(res => {
                let data = Array.isArray(res) ? res : (res.data || []);
                
                if (res && res.storage) {
                    const realEl = document.getElementById('storageRealVal');
                    const dummyEl = document.getElementById('storageDummyVal');
                    if (realEl && res.storage.real) realEl.textContent = res.storage.real;
                    if (dummyEl && res.storage.dummy) dummyEl.textContent = res.storage.dummy;
                }

                if (!Array.isArray(data)) return;
                
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const locSelect = document.getElementById('locationSelect');
                const locationVal = locSelect ? locSelect.value : 'LOK-002';
                
                let newData = data.map((item, index) => {
                    let formattedDate = item.tanggal_waktu || '-';
                    let dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
                    return {
                        id: item.id,
                        no: index + 1,
                        tanggal_waktu: formattedDate,
                        tanggal: dateOnly,
                        api: item.api !== undefined ? item.api : '0',
                        asap: item.asap_raw !== undefined && !isNaN(parseFloat(item.asap_raw)) ? parseFloat(item.asap_raw).toFixed(2) : (item.asap !== undefined ? item.asap : '0'),
                        suhu: item.suhu,
                        kelembapan: item.kelembapan,
                        tegangan: item.tegangan,
                        arus: item.arus,
                        rssi: item.rssi,
                        is_dummy: parseInt(item.is_dummy || 0)
                    };
                });

                sensorData = newData;
                applyFilter();
            })
            .catch(err => console.error("Error updating indoor table data:", err));
    }

    let indoorTableTimer = null;
    function scheduleNextIndoorTableUpdate() {
        if (indoorTableTimer) clearTimeout(indoorTableTimer);
        const intervalMs = 30000;
        indoorTableTimer = setTimeout(function() {
            fetchTableDataRealtime();
            scheduleNextIndoorTableUpdate();
        }, intervalMs);
    }

    scheduleNextIndoorTableUpdate();
});
