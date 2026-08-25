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

let selectedIds = new Set();

function toggleRowCheckbox(cb) {
    let id = parseInt(cb.value);
    if (!id) return;
    if (cb.checked) {
        selectedIds.add(id);
    } else {
        selectedIds.delete(id);
    }
    updateSelectedButton();
}

function toggleSelectAll(masterCb) {
    const isChecked = masterCb.checked;
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = isChecked;
            let id = parseInt(cb.value);
            if (id) {
                if (isChecked) selectedIds.add(id);
                else selectedIds.delete(id);
            }
        }
    });
    updateSelectedButton();
}

function updateSelectedButton() {
    const count = selectedIds.size;
    const countEl = document.getElementById('selectedCount');
    const btn = document.getElementById('btnDeleteSelected');
    const selectAllCb = document.getElementById('selectAllCheckbox');

    if (countEl) countEl.textContent = count;
    if (btn) {
        if (count > 0) {
            btn.disabled = false;
            btn.style.cursor = 'pointer';
            btn.style.opacity = '1';
        } else {
            btn.disabled = true;
            btn.style.cursor = 'not-allowed';
            btn.style.opacity = '0.6';
        }
    }

    // Update state selectAllCheckbox jika semua di halaman tercentang
    const visibleCheckboxes = document.querySelectorAll('.row-checkbox:not(:disabled)');
    if (selectAllCb && visibleCheckboxes.length > 0) {
        const allChecked = Array.from(visibleCheckboxes).every(cb => cb.checked);
        selectAllCb.checked = allChecked;
    } else if (selectAllCb && visibleCheckboxes.length === 0) {
        selectAllCb.checked = false;
    }
}

function createRow(item) {
    let apiText = getStatusText(item.api, 'api');
    let apiNumber = (apiText === "Aman") ? "0" : "100";
    let apiDisplay = `${getStatusIcon(item.api, 'api')} ${apiText} (${apiNumber})`;
    let isChecked = item.id && selectedIds.has(item.id) ? 'checked' : '';
    let checkboxHTML = item.id 
        ? `<input type="checkbox" class="row-checkbox" value="${item.id}" onchange="toggleRowCheckbox(this)" ${isChecked} style="cursor: pointer; width: 16px; height: 16px;">`
        : `<input type="checkbox" disabled style="opacity: 0.3; width: 16px; height: 16px;">`;

    return [
        checkboxHTML,
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
        { title: "<input type='checkbox' id='selectAllCheckbox' onchange='toggleSelectAll(this)' title='Pilih Semua' style='cursor: pointer; width: 16px; height: 16px;'>", orderable: false, width: "4%" },
        { title: "No", width: "5%" }, 
        { title: "Tanggal & Waktu", width: "14%" }, 
        { title: "Api", width: "11%" }, 
        { title: "Asap", width: "11%" }, 
        { title: "Suhu (°C)", width: "8%" }, 
        { title: "Kelembapan (%)", width: "9%" }, 
        { title: "Tegangan (V)", width: "9%" }, 
        { title: "Arus (A)", width: "8%" }, 
        { title: "RSSI (dBm)", width: "8%" }, 
        { title: "Aksi", orderable: false, width: "12%" }
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
        order: [[2, 'desc']], 
        scrollX: true,
        columnDefs: [
            { className: "text-center", targets: [0, 1, 9, 10] },
            { orderable: false, targets: [0, 10] }
        ],
        drawCallback: function() {
            // Sinkronkan state checkbox setelah ganti halaman / render ulang DataTable
            const visibleCheckboxes = document.querySelectorAll('.row-checkbox');
            visibleCheckboxes.forEach(cb => {
                let id = parseInt(cb.value);
                if (id && selectedIds.has(id)) {
                    cb.checked = true;
                }
            });
            updateSelectedButton();
        }
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
    const isLive = (locationVal === 'LOK-002' || locationVal === 'IND-002' || locationVal.includes('002') || locationVal.toUpperCase().includes('UTAMA') || locationVal === '2');

    const tableBadge = document.getElementById('table-badge');
    if (tableBadge) {
        if (isLive) {
            tableBadge.innerHTML = '<i class="fas fa-bolt"></i> Live (Real-Time)';
            tableBadge.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        } else {
            tableBadge.innerHTML = '<i class="fas fa-flask"></i> Data Dummy (Simulasi)';
            tableBadge.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
        }
    }

    let startDate = document.getElementById('start_date').value;
    let endDate = document.getElementById('end_date').value;

    // CEK DAN KOREKSI OTOMATIS JIKA TANGGAL TERBALIK (startDate > endDate)
    if (startDate && endDate && startDate > endDate) {
        const originalStart = startDate;
        const originalEnd = endDate;

        // Tukar nilainya ke urutan yang benar
        startDate = originalEnd;
        endDate = originalStart;

        // Perbaiki nilai di kotak input form secara otomatis
        document.getElementById('start_date').value = startDate;
        document.getElementById('end_date').value = endDate;

        // Tampilkan notifikasi informasi / peringatan kepada pengguna
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Rentang Tanggal Terbalik',
                html: `Anda memasukkan rentang tanggal dari <b>${originalStart}</b> sampai <b>${originalEnd}</b>.<br><br>Sistem otomatis memperbaiki dan menampilkan data dari <b>${startDate}</b> sampai <b>${endDate}</b>.`,
                confirmButtonColor: '#0083b0',
                confirmButtonText: 'Mengerti',
                timer: 4500,
                timerProgressBar: true
            });
        } else {
            alert(`Rentang tanggal terbalik (${originalStart} s/d ${originalEnd}). Sistem otomatis memperbaiki menjadi ${startDate} s/d ${endDate}.`);
        }
    }

    // JIKA LOKASI DUMMY
    if (!isLive) {
        let sourceData = sensorData.filter(item => item.is_dummy === 1);
        if (sourceData.length === 0) {
            sourceData = generateDummyTable(50);
        }

        let filteredData = [...sourceData];
        if (startDate) filteredData = filteredData.filter(item => item.tanggal >= startDate);
        if (endDate) filteredData = filteredData.filter(item => item.tanggal <= endDate);

        filteredData.forEach((item, idx) => item.no = idx + 1);

        currentData = filteredData;
        updateDataTable(currentData);

        if (filteredData.length === 0) alert('Tidak ada data yang sesuai dengan filter!');
        return;
    }

    // JIKA LOKASI ASLI (LIVE): Ambil langsung dari server via AJAX agar tidak terpotong batas LIMIT
    const btnFilter = document.querySelector('.btn-filter');
    const originalBtnHTML = btnFilter ? btnFilter.innerHTML : '';
    if (btnFilter) {
        btnFilter.disabled = true;
        btnFilter.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';
    }

    const params = new URLSearchParams();
    if (startDate) params.append('from', startDate);
    if (endDate) params.append('to', endDate);
    params.append('is_dummy', '0');
    params.append('location', locationVal);
    params.append('with_storage', '1');

    fetch('api_get_table_indoor.php?' + params.toString())
        .then(response => response.json())
        .then(res => {
            if (btnFilter) {
                btnFilter.disabled = false;
                btnFilter.innerHTML = originalBtnHTML;
            }

            if (res.storage) {
                const realEl = document.getElementById('storageRealVal');
                const dummyEl = document.getElementById('storageDummyVal');
                if (realEl && res.storage.real) realEl.textContent = res.storage.real;
                if (dummyEl && res.storage.dummy) dummyEl.textContent = res.storage.dummy;
            }

            if (res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                let mappedData = res.data.map((item, index) => {
                    let formattedDate = item.tanggal_waktu || '-';
                    let dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
                    return {
                        id: item.id,
                        no: index + 1,
                        tanggal_waktu: formattedDate,
                        tanggal: dateOnly,
                        api: item.api !== undefined ? item.api : '0',
                        asap: item.asap !== undefined ? item.asap : '0',
                        suhu: item.suhu,
                        kelembapan: item.kelembapan,
                        tegangan: item.tegangan,
                        arus: item.arus,
                        rssi: item.rssi,
                        is_dummy: parseInt(item.is_dummy || 0)
                    };
                });

                currentData = mappedData;
                updateDataTable(currentData);
            } else {
                currentData = [];
                updateDataTable([]);
                alert('Tidak ada data yang sesuai dengan filter!');
            }
        })
        .catch(err => {
            console.error('Error saat memuat data tabel indoor:', err);
            if (btnFilter) {
                btnFilter.disabled = false;
                btnFilter.innerHTML = originalBtnHTML;
            }

            // Fallback jika fetch gagal
            let sourceData = sensorData.filter(item => !item.is_dummy || item.is_dummy === 0);
            let filteredData = [...sourceData];
            if (startDate) filteredData = filteredData.filter(item => item.tanggal >= startDate);
            if (endDate) filteredData = filteredData.filter(item => item.tanggal <= endDate);
            filteredData.forEach((item, idx) => item.no = idx + 1);
            currentData = filteredData;
            updateDataTable(currentData);
        });
}

function resetFilter() {
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    selectedIds.clear();
    updateSelectedButton();
    applyFilter();
}

function exportToExcel() {
    if (!currentData || currentData.length === 0) { 
        alert('Tidak ada data untuk diexport!'); 
        return; 
    }

    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const locSelect = document.getElementById('locationSelect');
    const locationText = locSelect ? locSelect.options[locSelect.selectedIndex].text : 'Indoor';
    const tglExport = new Date().toLocaleString('id-ID', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });

    let periodeStr = 'Semua Riwayat Data';
    if (startDate && endDate) {
        periodeStr = `${startDate} s.d. ${endDate}`;
    } else if (startDate) {
        periodeStr = `Mulai ${startDate}`;
    } else if (endDate) {
        periodeStr = `Sampai ${endDate}`;
    }

    let rowsHTML = '';
    currentData.forEach((item, idx) => {
        let statusApi = getStatusText(item.api, 'api');
        let statusAsap = getStatusText(item.asap, 'asap');
        let bgStyle = (idx % 2 === 0) ? 'background-color: #ffffff;' : 'background-color: #f8f9fa;';
        
        let apiStyle = (statusApi === 'Terdeteksi Api') ? 'color: #dc3545; font-weight: bold;' : 'color: #28a745;';
        let asapStyle = (statusAsap === 'Tinggi') ? 'color: #dc3545; font-weight: bold;' : (statusAsap === 'Sedang' ? 'color: #fd7e14; font-weight: bold;' : 'color: #28a745;');

        rowsHTML += `
            <tr style="${bgStyle}">
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${idx + 1}</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${item.tanggal_waktu}</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px; ${apiStyle}">${statusApi} (${item.api})</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px; ${asapStyle}">${statusAsap} (${item.asap})</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${item.suhu}</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${item.kelembapan}</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${item.tegangan}</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${item.arus}</td>
                <td style="text-align: center; border: 1px solid #d3d3d3; padding: 6px;">${item.rssi}</td>
            </tr>
        `;
    });

    const excelTemplate = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Data Sensor Indoor</x:Name>
                            <x:WorksheetOptions>
                                <x:DisplayGridlines/>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11pt; }
                table { border-collapse: collapse; width: 100%; }
                th { background-color: #1e3c72; color: #ffffff; font-weight: bold; border: 1px solid #000000; padding: 8px; text-align: center; }
                td { border: 1px solid #d3d3d3; padding: 6px; vertical-align: middle; }
                .title-header { font-size: 14pt; font-weight: bold; color: #1e3c72; text-align: left; }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <td colspan="9" class="title-header" style="font-size: 14pt; font-weight: bold; color: #1e3c72; border: none;">
                        LAPORAN DATA SENSOR INDOOR - FIRENETWORK
                    </td>
                </tr>
                <tr>
                    <td colspan="9" style="border: none; color: #555; font-size: 10pt;">
                        <strong>Lokasi:</strong> ${locationText} | <strong>Periode:</strong> ${periodeStr} | <strong>Waktu Export:</strong> ${tglExport} | <strong>Total Data:</strong> ${currentData.length} Baris
                    </td>
                </tr>
                <tr><td colspan="9" style="border: none; height: 10px;"></td></tr>
            </table>

            <table border="1" style="border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #1e3c72; color: #ffffff;">
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 50px;">No</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 160px;">Tanggal & Waktu</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 140px;">Status Api</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 140px;">Status Asap</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 100px;">Suhu (°C)</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 110px;">Kelembapan (%)</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 110px;">Tegangan (V)</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 90px;">Arus (A)</th>
                        <th style="background-color: #1e3c72; color: #ffffff; border: 1px solid #000000; padding: 8px; width: 100px;">RSSI (dBm)</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHTML}
                </tbody>
            </table>
        </body>
        </html>
    `;

    const blob = new Blob(["\uFEFF" + excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const fileSuffix = startDate && endDate ? `${startDate}_sd_${endDate}` : (startDate ? `dari_${startDate}` : new Date().toISOString().slice(0, 10));
    a.download = `data_sensor_indoor_${fileSuffix}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    alert(`Berhasil mengexport ${currentData.length} data ke Excel dengan format rapi!`);
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
                selectedIds.delete(idData);
                updateSelectedButton();
                if (data.storage) {
                    const realEl = document.getElementById('storageRealVal');
                    const dummyEl = document.getElementById('storageDummyVal');
                    if (realEl && data.storage.real) realEl.textContent = data.storage.real;
                    if (dummyEl && data.storage.dummy) dummyEl.textContent = data.storage.dummy;
                }
                applyFilter();
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

function hapusTerpilih() {
    const idsArray = Array.from(selectedIds);
    if (idsArray.length === 0) {
        alert('Silakan pilih data yang ingin dihapus terlebih dahulu.');
        return;
    }

    if (confirm(`Apakah Anda yakin ingin menghapus ${idsArray.length} data yang dipilih? Aksi ini permanen.`)) {
        const btn = document.getElementById('btnDeleteSelected');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
        }

        fetch('api_hapus_baris_indoor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: idsArray })
        })
        .then(response => response.json())
        .then(data => {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus (<span id="selectedCount">0</span>)';
            }
            if (data.status === 'success') {
                alert(data.message || 'Data berhasil dihapus!');
                selectedIds.clear();
                updateSelectedButton();
                
                // Update info storage
                if (data.storage) {
                    const realEl = document.getElementById('storageRealVal');
                    const dummyEl = document.getElementById('storageDummyVal');
                    if (realEl && data.storage.real) realEl.textContent = data.storage.real;
                    if (dummyEl && data.storage.dummy) dummyEl.textContent = data.storage.dummy;
                }

                // Muat ulang data tabel
                applyFilter();
            } else {
                alert('Gagal: ' + data.message);
                updateSelectedButton();
            }
        })
        .catch(error => {
            console.error('Error saat hapus massal:', error);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus (<span id="selectedCount">0</span>)';
                updateSelectedButton();
            }
            alert('Terjadi kesalahan sistem saat menghapus data.');
        });
    }
}

// Export fungsi ke window agar dapat dipanggil langsung dari onclick HTML
window.toggleRowCheckbox = toggleRowCheckbox;
window.toggleSelectAll = toggleSelectAll;
window.hapusTerpilih = hapusTerpilih;
window.hapusBaris = hapusBaris;
window.applyFilter = applyFilter;
window.resetFilter = resetFilter;
window.exportToExcel = exportToExcel;

$(document).ready(function() {
    if (sensorData && sensorData.length > 0) {
        initDataTable(sensorData);
        applyFilter();
    } else {
        $('#sensorTable').DataTable({
            data: [],
            columns: [
                { title: "<input type='checkbox' id='selectAllCheckbox' onchange='toggleSelectAll(this)' title='Pilih Semua' style='cursor: pointer; width: 16px; height: 16px;'>", orderable: false, width: "4%" },
                { title: "No", width: "5%" }, 
                { title: "Tanggal & Waktu", width: "14%" }, 
                { title: "Api", width: "11%" }, 
                { title: "Asap", width: "11%" }, 
                { title: "Suhu (°C)", width: "8%" }, 
                { title: "Kelembapan (%)", width: "9%" }, 
                { title: "Tegangan (V)", width: "9%" }, 
                { title: "Arus (A)", width: "8%" }, 
                { title: "RSSI (dBm)", width: "8%" }, 
                { title: "Aksi", orderable: false, width: "12%" }
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
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;

        // Jika pengguna sedang memfilter tanggal tertentu, jangan timpa data tabel dengan update live!
        if (startDate || endDate) {
            fetch('get_table_data.php?device=indoor&with_storage=1')
                .then(response => response.json())
                .then(res => {
                    if (res && res.storage) {
                        const realEl = document.getElementById('storageRealVal');
                        const dummyEl = document.getElementById('storageDummyVal');
                        if (realEl && res.storage.real) realEl.textContent = res.storage.real;
                        if (dummyEl && res.storage.dummy) dummyEl.textContent = res.storage.dummy;
                    }
                })
                .catch(err => console.error("Error updating storage stats:", err));
            return;
        }

        fetch('api_get_table_indoor.php?with_storage=1&is_dummy=0')
            .then(response => response.json())
            .then(res => {
                let data = Array.isArray(res.data) ? res.data : [];
                
                if (res && res.storage) {
                    const realEl = document.getElementById('storageRealVal');
                    const dummyEl = document.getElementById('storageDummyVal');
                    if (realEl && res.storage.real) realEl.textContent = res.storage.real;
                    if (dummyEl && res.storage.dummy) dummyEl.textContent = res.storage.dummy;
                }

                if (!Array.isArray(data) || data.length === 0) return;
                
                let newData = data.map((item, index) => {
                    let formattedDate = item.tanggal_waktu || '-';
                    let dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
                    return {
                        id: item.id,
                        no: index + 1,
                        tanggal_waktu: formattedDate,
                        tanggal: dateOnly,
                        api: item.api !== undefined ? item.api : '0',
                        asap: item.asap !== undefined ? item.asap : '0',
                        suhu: item.suhu,
                        kelembapan: item.kelembapan,
                        tegangan: item.tegangan,
                        arus: item.arus,
                        rssi: item.rssi,
                        is_dummy: parseInt(item.is_dummy || 0)
                    };
                });

                sensorData = newData;
                currentData = newData;
                updateDataTable(currentData);
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

    // Event listener langsung saat pengguna selesai memilih kedua tanggal
    function handleDateInputChange() {
        const sEl = document.getElementById('start_date');
        const eEl = document.getElementById('end_date');
        if (!sEl || !eEl) return;
        const sVal = sEl.value;
        const eVal = eEl.value;
        if (sVal && eVal && sVal > eVal) {
            const originalStart = sVal;
            const originalEnd = eVal;
            sEl.value = originalEnd;
            eEl.value = originalStart;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Rentang Tanggal Terbalik',
                    html: `Anda memasukkan rentang tanggal dari <b>${originalStart}</b> sampai <b>${originalEnd}</b>.<br><br>Sistem otomatis memperbaiki menjadi <b>${originalEnd}</b> sampai <b>${originalStart}</b>.`,
                    confirmButtonColor: '#0083b0',
                    confirmButtonText: 'Mengerti',
                    timer: 4500,
                    timerProgressBar: true
                });
            } else {
                alert(`Rentang tanggal terbalik (${originalStart} s/d ${originalEnd}). Sistem otomatis memperbaiki menjadi ${originalEnd} s/d ${originalStart}.`);
            }
        }
    }

    document.getElementById('start_date')?.addEventListener('change', handleDateInputChange);
    document.getElementById('end_date')?.addEventListener('change', handleDateInputChange);
});
