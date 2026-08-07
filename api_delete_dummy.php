 <?php
    session_start();
    require_once 'koneksi.php';
    header('Content-Type: application/json');

    // Proteksi: Hanya admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $device = isset($_GET['device']) ? $_GET['device'] : 'outdoor';
    try {

        if ($device === 'indoor') {
            $stmt = $pdo_indoor->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        } else {
            $stmt = $pdo_outdoor->prepare("DELETE FROM data_sensor WHERE is_dummy = 1");
        }

        $stmt->execute();
        $deletedRows = $stmt->rowCount();

        echo json_encode(['status' => 'success', 'message' => "$deletedRows data dummy berhasil dihapus."]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    ?>

  2. Tambahkan tombol hapus di dashboard_admin.php di bagian <div class="header-right">:
    <div class="header-right">
        <button onclick="hapusDataDummy('outdoor')" class="btn-home-header" style="background: rgba(220, 53, 69, 0.
  15); color: #dc3545;">
            <i class="fas fa-trash-alt"></i> Hapus Dummy
        </button>
        <a href="home.php" class="btn-home-header"><i class="fas fa-home"></i> HOME</a>
        <!-- ... -->

  3. Tambahkan fungsi JS ini di paling bawah dashboard_admin.php (sebelum // == JALANKAN FUNGSI ==):

    // ================= HAPUS DATA DUMMY =================
    function hapusDataDummy(device) {
        if (confirm("Apakah Anda yakin ingin menghapus semua data simulasi (dummy)? Data asli tidak akan terhapus."))
  {
            fetch(`api_delete_dummy.php?device=${device}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        dataChart.labels = [];
                        dataChart.datasets.forEach(ds => ds.data = []);
                        myChart.update();
                    } else {
                        alert("Gagal: " + data.message);
                    }
                })
                .catch(error => alert("Terjadi kesalahan sistem."));
        }
    }