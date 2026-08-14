/**
 * Script Logika Frontend Setting Indoor
 * FireNetWork Indoor System
 */

// ========== FUNGSI MODAL LOGOUT ==========
function openLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.getElementById('logoutModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});

// ========== FUNGSI MODAL HOME ==========
function openHomeModal() {
    const modal = document.getElementById('homeModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeHomeModal() {
    const modal = document.getElementById('homeModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.getElementById('homeModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeHomeModal();
    }
});

// Tutup modal dengan tombol ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('logoutModal')?.style.display === 'flex') {
            closeLogoutModal();
        }
        if (document.getElementById('homeModal')?.style.display === 'flex') {
            closeHomeModal();
        }
    }
});

// ========== FUNGSI OPEN EDIT ALARM MODAL ==========
function openEditAlarmModal(id, nama, nilai, satuan, min, max) {
    try {
        id = id || 0;
        nama = nama || '-';
        nilai = nilai || 0;
        satuan = satuan || '';
        min = min || 0;
        max = max || 100;
        
        document.getElementById('edit_sensor_id').value = id;
        document.getElementById('edit_sensor_name').value = nama;
        document.getElementById('edit_batas_min').value = min;
        document.getElementById('edit_batas_max').value = max;
        document.getElementById('edit_alarm_value').value = nilai;
        document.getElementById('edit_satuan').value = satuan;

        var warning = document.getElementById('range_warning');
        if (warning) {
            warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Nilai alarm harus antara ' + min + ' - ' + max + ' ' + satuan;
            warning.style.color = '#e74c3c';
        }

        var modal = document.getElementById('editAlarmModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
        }
    } catch (e) {
        console.error('Error:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + e.message,
            confirmButtonColor: '#dc3545'
        });
    }
}

// ========== FUNGSI OPEN EDIT USER MODAL ==========
function openEditUserModal(id, username, role) {
    try {
        var userIdField = document.getElementById('edit_user_id');
        var usernameField = document.getElementById('edit_username');
        var roleField = document.getElementById('edit_role');
        var modal = document.getElementById('editUserModal');
        
        if (!userIdField || !usernameField || !roleField || !modal) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Elemen modal tidak ditemukan. Refresh halaman.',
                confirmButtonColor: '#dc3545'
            });
            return;
        }
        
        userIdField.value = id;
        usernameField.value = username;
        roleField.value = role;
        
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        
    } catch (e) {
        console.error('Error in openEditUserModal:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + e.message,
            confirmButtonColor: '#dc3545'
        });
    }
}

// ========== FUNGSI OPEN EDIT LOCATION MODAL ==========
function openEditLocationModal(id, id_alat, nama_lokasi, lat, lng, interval) {
    try {
        document.getElementById('edit_location_id').value = id;
        document.getElementById('edit_id_alat').value = id_alat;
        document.getElementById('edit_nama_lokasi').value = nama_lokasi || '';
        document.getElementById('edit_latitude').value = lat;
        document.getElementById('edit_longitude').value = lng;
        document.getElementById('edit_interval_kirim').value = interval;

        var modal = document.getElementById('editLocationModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
        }
    } catch (e) {
        console.error('Error in openEditLocationModal:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan saat membuka modal edit: ' + e.message,
        });
    }
}

// ========== EVENT LISTENER UNTUK TOMBOL ==========
document.addEventListener('DOMContentLoaded', function() {
    // Event listener untuk tombol edit user
    var editButtons = document.querySelectorAll('.btn-edit-user');
    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var id = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            var role = this.getAttribute('data-role');
            openEditUserModal(id, username, role);
        });
    });
    
    // Event listener untuk tombol delete user
    document.querySelectorAll('.btn-delete-user').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var userId = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            deleteUser(userId, username);
        });
    });

    // Event listener untuk tombol delete location
    document.querySelectorAll('.btn-delete-location').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var locId = this.getAttribute('data-id');
            deleteLocation(locId);
        });
    });
    
    // Event listener untuk tombol edit alarm (menggunakan jQuery jika ada)
    if (typeof $ !== 'undefined') {
        $('.btn-edit-alarm').on('click', function() {
            var id = $(this).data('id');
            var nama = $(this).data('nama');
            var nilai = $(this).data('nilai');
            var satuan = $(this).data('satuan');
            var min = $(this).data('min');
            var max = $(this).data('max');
            openEditAlarmModal(id, nama, nilai, satuan, min, max);
        });
    }

    // Set default tab
    const tab1 = document.getElementById('tab1');
    const tab2 = document.getElementById('tab2');
    const tab3 = document.getElementById('tab3');
    if (tab1) tab1.style.display = 'block';
    if (tab2) tab2.style.display = 'none';
    if (tab3) tab3.style.display = 'none';

    // Auto-refresh real-time data tabel lokasi
    function updateLocationTableData() {
        fetch('get_locations.php')
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res && !res.error && Array.isArray(res.data)) {
                    res.data.forEach(function(loc) {
                        var row = document.getElementById('loc-row-' + loc.id);
                        if (row) {
                            var intervalElem = row.querySelector('.loc-interval-col');
                            var updateElem = row.querySelector('.loc-update-col');
                            if (intervalElem) {
                                var intVal = loc.interval_kirim || 15;
                                intervalElem.innerHTML = '<span style="font-weight:bold; color:#1e3c72;">' + intVal + '</span> detik';
                            }
                            if (updateElem && loc.last_update) {
                                updateElem.textContent = loc.last_update;
                            }
                        }
                    });
                }
            })
            .catch(function(err) { console.error('Error fetching locations:', err); });
    }
    setInterval(updateLocationTableData, 4000);
});

// ========== FUNGSI MODAL LAINNYA ==========
function openAddSensorModal() {
    const modal = document.getElementById('addSensorModal');
    if (modal) modal.style.display = 'flex';
}

function openAddLocationModal() {
    var modal = document.getElementById('addLocationModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }
}

function openAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) modal.style.display = 'flex';
}

function openTab(tabName, element) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.style.display = 'block';
        targetTab.classList.add('active');
    }
    if (element) {
        element.classList.add('active');
    }
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
    }
}

// ========== FUNGSI HAPUS ==========
function deleteUser(userId, username) {
    Swal.fire({
        title: 'Hapus Akun?',
        text: 'Apakah Anda yakin ingin menghapus akun "' + username + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_user';
            input.value = '1';
            let inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'user_id';
            inputId.value = userId;
            form.appendChild(input);
            form.appendChild(inputId);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteLocation(locationId) {
    Swal.fire({
        title: 'Hapus Lokasi?',
        text: 'Apakah Anda yakin ingin menghapus lokasi ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_location';
            input.value = '1';
            let inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'location_id';
            inputId.value = locationId;
            form.appendChild(input);
            form.appendChild(inputId);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        event.target.style.visibility = 'hidden';
        event.target.style.opacity = '0';
    }
};
