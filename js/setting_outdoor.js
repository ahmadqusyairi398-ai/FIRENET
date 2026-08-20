// ================= JAVASCRIPT SETTING OUTDOOR =================

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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var lModal = document.getElementById('logoutModal');
        var hModal = document.getElementById('homeModal');
        if (lModal && lModal.style.display === 'flex') closeLogoutModal();
        if (hModal && hModal.style.display === 'flex') closeHomeModal();
    }
});

function openEditAlarmModal(id, nama, nilai, satuan, min, max) {
    try {
        var elId = document.getElementById('edit_sensor_id');
        if (elId) elId.value = id;
        var elName = document.getElementById('edit_sensor_name');
        if (elName) elName.value = nama;

        var cleanMin = isNaN(parseFloat(min)) ? min : parseFloat(min);
        var cleanMax = isNaN(parseFloat(max)) ? max : parseFloat(max);
        var cleanVal = isNaN(parseFloat(nilai)) ? nilai : parseFloat(nilai);

        var elMin = document.getElementById('edit_batas_min');
        if (elMin) elMin.value = cleanMin;
        var elMax = document.getElementById('edit_batas_max');
        if (elMax) elMax.value = cleanMax;
        var elVal = document.getElementById('edit_alarm_value');
        if (elVal) elVal.value = cleanVal;
        var elSat = document.getElementById('edit_satuan');
        if (elSat) elSat.value = satuan;

        var warning = document.getElementById('range_warning');
        if (warning) {
            warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Nilai alarm harus antara ' + cleanMin + ' - ' + cleanMax + ' ' + satuan;
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
    }
}

function openEditUserModal(id, username, role) {
    try {
        var elId = document.getElementById('edit_user_id');
        if (elId) elId.value = id;
        var elUser = document.getElementById('edit_username');
        if (elUser) elUser.value = username;
        var elRole = document.getElementById('edit_role');
        if (elRole) elRole.value = role;
        
        var modal = document.getElementById('editUserModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
        }
    } catch (e) {
        console.error('Error in openEditUserModal:', e);
    }
}

function openEditLocationModal(id, id_alat, nama_lokasi, lat, lng) {
    try {
        var elId = document.getElementById('edit_location_id');
        if (elId) elId.value = id;
        var elAlat = document.getElementById('edit_id_alat');
        if (elAlat) elAlat.value = id_alat;
        var elNama = document.getElementById('edit_nama_lokasi');
        if (elNama) elNama.value = nama_lokasi;
        var elLat = document.getElementById('edit_latitude');
        if (elLat) elLat.value = lat;
        var elLng = document.getElementById('edit_longitude');
        if (elLng) elLng.value = lng;
        
        var modal = document.getElementById('editLocationModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
        }
    } catch (e) {
        console.error('Error in openEditLocationModal:', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var editButtons = document.querySelectorAll('.btn-edit-user');
    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            var role = this.getAttribute('data-role');
            openEditUserModal(id, username, role);
        });
    });
    
    document.querySelectorAll('.btn-delete-user').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var userId = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            deleteUser(userId, username);
        });
    });

    document.querySelectorAll('.btn-delete-location').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var locId = this.getAttribute('data-id');
            deleteLocation(locId);
        });
    });
    
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

    var tab1 = document.getElementById('tab1');
    if (tab1) tab1.style.display = 'block';
    var tab2 = document.getElementById('tab2');
    if (tab2) tab2.style.display = 'none';
    var tab3 = document.getElementById('tab3');
    if (tab3) tab3.style.display = 'none';

    // Handle SweetAlert Messages from config
    var cfg = window.FIRENET_CONFIG || {};
    if (cfg.successMessage && typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: cfg.successMessage,
            timer: 2000,
            showConfirmButton: false
        });
    } else if (cfg.errorMessage && typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: cfg.errorMessage
        });
    }
});

function openAddSensorModal() {
    var modal = document.getElementById('addSensorModal');
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
    var modal = document.getElementById('addUserModal');
    if (modal) modal.style.display = 'flex';
}

function openTab(tabName, element) {
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
    var target = document.getElementById(tabName);
    if (target) {
        target.style.display = 'block';
        target.classList.add('active');
    }
    if (element) element.classList.add('active');
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
    }
}

function deleteUser(userId, username) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Akun?',
            text: 'Apakah Anda yakin ingin menghapus akun "' + username + '"?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus!'
        }).then(function(result) {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_user';
                input.value = '1';
                var inputId = document.createElement('input');
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
}

function deleteLocation(locationId) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Lokasi?',
            text: 'Apakah Anda yakin ingin menghapus lokasi ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus!'
        }).then(function(result) {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_location';
                input.value = '1';
                var inputId = document.createElement('input');
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
}

window.onclick = function(event) {
    if (event.target && event.target.classList && event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        event.target.style.visibility = 'hidden';
        event.target.style.opacity = '0';
    }
};
