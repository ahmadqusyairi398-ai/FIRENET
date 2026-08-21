// ================= JAVASCRIPT TABEL OUTDOOR =================

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

// ================= FUNGSI TABEL =================
var cfg = window.FIRENET_CONFIG || {};
var sensorDataPHP = cfg.sensorData || [];

var sensorData = sensorDataPHP.map(function(item, index) {
    var formattedDate = item.tanggal_waktu || '-';
    var dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
    return {
        id: item.id,
        no: index + 1,
        tanggal_waktu: formattedDate,
        tanggal: dateOnly,
        asap: item.asap || '-',
        suhu: item.suhu ? parseFloat(item.suhu).toFixed(1) : '0',
        kelembapan: item.kelembapan ? parseFloat(item.kelembapan).toFixed(1) : '0',
        tegangan: item.tegangan ? parseFloat(item.tegangan).toFixed(1) : '0',
        arus: item.arus ? parseFloat(item.arus).toFixed(2) : '0',
        daya: item.daya ? parseFloat(item.daya).toFixed(1) : '0',
        kecepatan_angin: item.kecepatan_angin ? parseFloat(item.kecepatan_angin).toFixed(1) : '0',
        arah_angin: item.arah_angin || '-',
        co: item.co ? parseFloat(item.co).toFixed(1) : '0'
    };
});

var currentData = [...sensorData];
var dataTable = null;
var selectedIds = new Set(); // Menyimpan ID data sensor yang dichecklist

// Fungsi untuk menentukan status dan kelas CSS
function getStatusClass(value, type) {
    if (type === 'asap') {
        if (value === 'Tinggi' || value === 'Bahaya') return 'status-bahaya';
        if (value === 'Sedang') return 'status-waspada';
        return 'status-aman';
    }
    if (type === 'co') {
        var coValue = parseFloat(value);
        if (coValue > 50) return 'status-bahaya';
        if (coValue > 35) return 'status-waspada';
        return 'status-aman';
    }
    return '';
}

function getStatusIcon(value, type) {
    if (type === 'asap') {
        if (value === 'Tinggi' || value === 'Bahaya') return '<i class="fas fa-chart-line"></i>';
        if (value === 'Sedang') return '<i class="fas fa-minus-circle"></i>';
        return '<i class="fas fa-check"></i>';
    }
    if (type === 'co') {
        var coValue = parseFloat(value);
        if (coValue > 50) return '<i class="fas fa-exclamation-triangle"></i>';
        if (coValue > 35) return '<i class="fas fa-exclamation-circle"></i>';
        return '<i class="fas fa-check-circle"></i>';
    }
    return '';
}

function createRow(item) {
    var isChecked = item.id && selectedIds.has(String(item.id)) ? 'checked' : '';
    var checkboxHtml = item.id ? 
        `<input type="checkbox" class="row-checkbox" value="${item.id}" ${isChecked} onchange="toggleRowCheckbox(this, '${item.id}')">` : 
        `<input type="checkbox" disabled title="Data simulasi tidak dapat dipilih">`;

    return [
        checkboxHtml,
        item.no,
        item.tanggal_waktu,
        `<span class="${getStatusClass(item.asap, 'asap')}">${getStatusIcon(item.asap, 'asap')} ${item.asap}</span>`,
        `${item.suhu} °C`,
        `${item.kelembapan} %`,
        `${item.tegangan} V`,
        `${item.arus} A`,
        `${item.daya} W`,
        `${item.kecepatan_angin} m/s`,
        `${item.arah_angin}`,
        `<span class="${getStatusClass(item.co, 'co')}">${getStatusIcon(item.co, 'co')} ${item.co} ppm</span>`
    ];
}

function updateDataTable(data) {
    var rows = data.map(createRow);
    if (dataTable) {
        dataTable.clear();
        if (rows.length > 0) dataTable.rows.add(rows);
        dataTable.draw(false);
    }
    syncCheckboxStates();
}

function initDataTable(data) {
    if (dataTable) dataTable.destroy();
    var rows = data.map(createRow);
    dataTable = $('#sensorTable').DataTable({
        data: rows,
        columns: [
            { title: '<input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" title="Pilih Semua">', orderable: false, className: 'text-center', width: '40px' },
            { title: "No" }, 
            { title: "Tanggal & Waktu" }, 
            { title: "Asap" }, 
            { title: "Suhu (°C)" }, 
            { title: "Kelembapan (%)" },
            { title: "Tegangan (V)" }, 
            { title: "Arus (A)" },
            { title: "Daya (W)" },
            { title: "Kecepatan Angin (m/s)" },
            { title: "Arah Angin" },
            { title: "CO (ppm)" }
        ],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            search: "Cari:",
            paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
        },
        pageLength: 10, 
        lengthMenu: [5, 10, 25, 50, 100], 
        order: [[2, 'desc']], 
        scrollX: true
    });

    dataTable.on('draw', function() {
        syncCheckboxStates();
    });
}

// ================= FUNGSI CHECKLIST / MULTI-DELETE =================
function toggleRowCheckbox(checkboxElem, id) {
    if (!id) return;
    var idStr = String(id);
    if (checkboxElem.checked) {
        selectedIds.add(idStr);
    } else {
        selectedIds.delete(idStr);
    }
    updateSelectedCount();
    updateSelectAllCheckboxState();
}

function toggleSelectAll(masterCheckbox) {
    var isChecked = masterCheckbox.checked;
    currentData.forEach(function(item) {
        if (item.id) {
            var idStr = String(item.id);
            if (isChecked) {
                selectedIds.add(idStr);
            } else {
                selectedIds.delete(idStr);
            }
        }
    });
    syncCheckboxStates();
    updateSelectedCount();
}

function syncCheckboxStates() {
    $('.row-checkbox').each(function() {
        var id = $(this).val();
        if (id && selectedIds.has(String(id))) {
            this.checked = true;
        } else {
            this.checked = false;
        }
    });
    updateSelectAllCheckboxState();
}

function updateSelectAllCheckboxState() {
    var master = document.getElementById('selectAllCheckbox');
    if (!master) return;

    var validCurrentItems = currentData.filter(function(item) { return item.id; });
    if (validCurrentItems.length === 0) {
        master.checked = false;
        master.indeterminate = false;
        return;
    }

    var countSelected = validCurrentItems.filter(function(item) {
        return selectedIds.has(String(item.id));
    }).length;

    if (countSelected === 0) {
        master.checked = false;
        master.indeterminate = false;
    } else if (countSelected === validCurrentItems.length) {
        master.checked = true;
        master.indeterminate = false;
    } else {
        master.checked = false;
        master.indeterminate = true;
    }
}

function updateSelectedCount() {
    var count = selectedIds.size;
    var countElem = document.getElementById('selectedCount');
    if (countElem) countElem.innerText = count;

    var btn = document.getElementById('btnDeleteSelected');
    if (btn) {
        btn.disabled = (count === 0);
        if (count > 0) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    }
}

function hapusTerpilih() {
    var idsArray = Array.from(selectedIds);
    if (idsArray.length === 0) {
        alert("Pilih setidaknya satu data yang ingin dihapus dengan mencentang kotak checklist.");
        return;
    }

    var msg = `Apakah Anda yakin ingin menghapus ${idsArray.length} data yang dipilih? Aksi ini permanen dan tidak dapat dibatalkan.`;
    if (confirm(msg)) {
        var btn = document.getElementById('btnDeleteSelected');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Menghapus...`;
        }

        fetch('api_hapus_baris_outdoor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: idsArray })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                var deletedSet = new Set(idsArray.map(String));
                sensorData = sensorData.filter(function(item) {
                    return !item.id || !deletedSet.has(String(item.id));
                });
                sensorData.forEach(function(item, idx) { item.no = idx + 1; });

                selectedIds.clear();
                updateSelectedCount();
                updateSelectAllCheckboxState();

                var startDate = document.getElementById('start_date').value;
                var endDate = document.getElementById('end_date').value;
                if (startDate || endDate) {
                    applyFilter();
                } else {
                    currentData = [...sensorData];
                    updateDataTable(currentData);
                }

                alert(data.message || `Berhasil menghapus ${idsArray.length} data.`);
            } else {
                alert("Gagal menghapus data: " + (data.message || 'Terjadi kesalahan.'));
            }
        })
        .catch(function(err) {
            console.error("Error batch deleting:", err);
            alert("Terjadi kesalahan sistem saat menghapus data.");
        })
        .finally(function() {
            var btn = document.getElementById('btnDeleteSelected');
            if (btn) {
                btn.innerHTML = `<i class="fas fa-trash-alt"></i> Hapus Terpilih (<span id="selectedCount">${selectedIds.size}</span>)`;
                btn.disabled = (selectedIds.size === 0);
            }
        });
    }
}

// FUNGSI JAVASCRIPT HAPUS SATU BARIS DATA OUTDOOR
function hapusBaris(idData) {
    if (confirm("Apakah Anda yakin ingin menghapus data ini? Aksi ini permanen.")) {
        fetch('api_hapus_baris_outdoor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: idData })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                selectedIds.delete(String(idData));
                updateSelectedCount();
                sensorData = sensorData.filter(function(item) { return item.id != idData; });
                sensorData.forEach(function(item, idx) { item.no = idx + 1; });
                
                var startDate = document.getElementById('start_date').value;
                var endDate = document.getElementById('end_date').value;
                if (startDate || endDate) {
                    applyFilter();
                } else {
                    currentData = [...sensorData];
                    updateDataTable(currentData);
                }
                updateSelectAllCheckboxState();
                alert("Data berhasil dihapus!");
            } else {
                alert("Gagal menghapus data: " + (data.message || 'Terjadi kesalahan.'));
            }
        })
        .catch(function(err) {
            console.error("Error deleting row:", err);
            alert("Terjadi kesalahan sistem saat menghapus data.");
        });
    }
}

function applyFilter() {
    var filteredData = [...sensorData];
    var startDate = document.getElementById('start_date').value;
    var endDate = document.getElementById('end_date').value;
    if (startDate && filteredData[0] && filteredData[0].tanggal) filteredData = filteredData.filter(function(item) { return item.tanggal >= startDate; });
    if (endDate && filteredData[0] && filteredData[0].tanggal) filteredData = filteredData.filter(function(item) { return item.tanggal <= endDate; });
    filteredData.forEach(function(item, idx) { item.no = idx + 1; });
    currentData = filteredData;
    updateDataTable(currentData);
    if (filteredData.length === 0) alert('Tidak ada data yang sesuai dengan filter!');
}

function resetFilter() {
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    sensorData.forEach(function(item, idx) { item.no = idx + 1; });
    currentData = [...sensorData];
    updateDataTable(currentData);
}

function exportToExcel() {
    var exportData = [...sensorData];
    var startDate = document.getElementById('start_date').value;
    var endDate = document.getElementById('end_date').value;
    if (startDate && exportData[0] && exportData[0].tanggal) exportData = exportData.filter(function(item) { return item.tanggal >= startDate; });
    if (endDate && exportData[0] && exportData[0].tanggal) exportData = exportData.filter(function(item) { return item.tanggal <= endDate; });
    if (exportData.length === 0) { alert('Tidak ada data untuk diexport!'); return; }
    
    var csv = "No,Tanggal & Waktu,Asap,Suhu (°C),Kelembapan (%),Tegangan (V),Arus (A),Daya (W),Kecepatan Angin (m/s),Arah Angin,CO (ppm)\n";
    exportData.forEach(function(item, idx) {
        csv += `"${idx+1}","${item.tanggal_waktu}","${item.asap}","${item.suhu}","${item.kelembapan}","${item.tegangan}","${item.arus}","${item.daya}","${item.kecepatan_angin}","${item.arah_angin}","${item.co}"\n`;
    });
    
    var blob = new Blob(["\uFEFF" + csv], { type: 'application/vnd.ms-excel' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = `data_sensor_${new Date().toISOString().slice(0,19)}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    alert(`Berhasil mengexport ${exportData.length} data ke Excel!`);
}

// Ekspor fungsi ke window agar dapat dipanggil dari HTML onclick/onchange
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
        console.log(`Data berhasil dimuat: ${sensorData.length} record`);
    } else {
        $('#sensorTable').DataTable({
            data: [],
            columns: [
                { title: '<input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" title="Pilih Semua">', orderable: false, className: 'text-center', width: '40px' },
                { title: "No" }, 
                { title: "Tanggal & Waktu" }, 
                { title: "Asap" }, 
                { title: "Suhu (°C)" }, 
                { title: "Kelembapan (%)" },
                { title: "Tegangan (V)" }, 
                { title: "Arus (A)" },
                { title: "Daya (W)" },
                { title: "Kecepatan Angin (m/s)" },
                { title: "Arah Angin" },
                { title: "CO (ppm)" }
            ],
            language: { 
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json",
                emptyTable: "Tidak ada data sensor yang tersedia. Silakan tambahkan data terlebih dahulu." 
            },
            scrollX: true
        });
    }

    var activeTableLocationId = 1;
    var tableUpdateTimer = null;

    function scheduleNextTableUpdate() {
        if (tableUpdateTimer) clearTimeout(tableUpdateTimer);
        var isMainDevice = (activeTableLocationId === 1);
        var intervalMs = isMainDevice ? 30000 : 15000;

        tableUpdateTimer = setTimeout(function() {
            fetchTableDataRealtime();
            scheduleNextTableUpdate();
        }, intervalMs);
    }

    window.changeTableLocation = function(locId) {
        activeTableLocationId = parseInt(locId) || 1;
        try {
            localStorage.setItem('activeLocationId', activeTableLocationId);
        } catch(e) {}
        var isDummy = (activeTableLocationId !== 1);

        // Reset checklist ketika berpindah lokasi
        selectedIds.clear();
        updateSelectedCount();
        updateSelectAllCheckboxState();

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
        fetchTableDataRealtime();
        scheduleNextTableUpdate();
    };

    function generateDummyTableRows(locId) {
        var numId = typeof locId === 'number' ? locId : 2;
        var now = new Date();
        var rows = [];

        for (var i = 0; i < 20; i++) {
            var t = new Date(now.getTime() - i * 15000);
            var dateStr = t.getFullYear() + '-' +
                            String(t.getMonth() + 1).padStart(2, '0') + '-' +
                            String(t.getDate()).padStart(2, '0');
            var timeStr = String(t.getHours()).padStart(2, '0') + ':' +
                            String(t.getMinutes()).padStart(2, '0') + ':' +
                            String(t.getSeconds()).padStart(2, '0');
            var fullStr = dateStr + ' ' + timeStr;

            var stepIndex = Math.floor(t.getTime() / 15000);
            var conditionStep = (stepIndex + numId) % 3;

            var suhuVal, humiVal, asapVal, coVal;
            if (conditionStep === 0) {
                suhuVal = (26.0 + (numId % 3)).toFixed(1);
                humiVal = '68.0';
                asapVal = 'Normal';
                coVal = '15.0';
            } else if (conditionStep === 1) {
                suhuVal = (42.0 + (numId % 3)).toFixed(1);
                humiVal = '48.0';
                asapVal = 'Sedang';
                coVal = '42.0';
            } else {
                suhuVal = (65.0 + (numId % 3)).toFixed(1);
                humiVal = '28.0';
                asapVal = 'Tinggi';
                coVal = '85.0';
            }

            var windVal = (2.0 + (numId % 4) * 0.9).toFixed(1);
            var tegVal = (219 + (numId % 3)).toFixed(1);
            var arusVal = (1.2 + (numId % 4) * 0.1).toFixed(2);
            var dayaVal = (250 + (numId % 6) * 20).toFixed(1);

            rows.push({
                id: null,
                no: i + 1,
                tanggal_waktu: fullStr,
                tanggal: dateStr,
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
        return rows;
    }

    function fetchTableDataRealtime() {
        if (activeTableLocationId !== 1) {
            var dummyRows = generateDummyTableRows(activeTableLocationId);
            sensorData = dummyRows;
            var startDate = document.getElementById('start_date').value;
            var endDate = document.getElementById('end_date').value;
            if (startDate || endDate) {
                applyFilter();
            } else {
                currentData = [...sensorData];
                updateDataTable(currentData);
            }
            return;
        }

        fetch('get_table_data.php?device=outdoor')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!Array.isArray(data)) return;
                
                var startDate = document.getElementById('start_date').value;
                var endDate = document.getElementById('end_date').value;
                
                var newData = data.map(function(item, index) {
                    var formattedDate = item.tanggal_waktu || '-';
                    var dateOnly = formattedDate !== '-' ? formattedDate.split(' ')[0] : '';
                    return {
                        id: item.id,
                        no: index + 1,
                        tanggal_waktu: formattedDate,
                        tanggal: dateOnly,
                        asap: item.asap || '-',
                        suhu: item.suhu,
                        kelembapan: item.kelembapan,
                        tegangan: item.tegangan,
                        arus: item.arus,
                        daya: item.daya,
                        kecepatan_angin: item.kecepatan_angin,
                        arah_angin: item.arah_angin,
                        co: item.co
                    };
                });

                sensorData = newData;
                
                if (startDate || endDate) {
                    applyFilter();
                } else {
                    currentData = [...sensorData];
                    updateDataTable(currentData);
                }
            })
            .catch(function(err) { console.error("Error updating table data:", err); });
    }

    try {
        var savedLocId = localStorage.getItem('activeLocationId');
        if (savedLocId) {
            var numSavedId = parseInt(savedLocId) || 1;
            var locSelect = document.getElementById('locationSelect');
            if (locSelect) {
                locSelect.value = numSavedId;
            }
            changeTableLocation(numSavedId);
        } else {
            scheduleNextTableUpdate();
        }
    } catch(e) {
        scheduleNextTableUpdate();
    }
});
