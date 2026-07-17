<?php
session_start();
require_once 'koneksi.php';

$test_result = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_login') {
        $target_db = $_POST['target_db'] === 'indoor' ? 'indoor' : 'outdoor';
        $test_user = trim($_POST['username']);
        $test_pass = $_POST['password'];
        
        $pdo_conn = ($target_db === 'indoor') ? $pdo_indoor : $pdo_outdoor;
        
        try {
            $table_name = ($target_db === 'indoor') ? 'login' : 'pengguna';
            $stmt = $pdo_conn->prepare("SELECT * FROM $table_name WHERE username = ?");
            $stmt->execute([$test_user]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $test_result = "<div class='alert alert-danger'><strong>Gagal:</strong> Username <code>" . htmlspecialchars($test_user) . "</code> tidak ditemukan di database <strong>" . strtoupper($target_db) . "</strong>.</div>";
            } else {
                $db_pass = $user['password'];
                $is_hashed = (strpos($db_pass, '$2y$') === 0 || strpos($db_pass, '$2a$') === 0);
                
                if (!$is_hashed) {
                    if ($test_pass === $db_pass) {
                        $test_result = "<div class='alert alert-warning'><strong>Gagal:</strong> Password cocok secara teks biasa (Plain Text), tetapi <strong>sistem menggunakan password_verify()</strong>. Di database, password harus di-hash (misalnya dengan <code>password_hash()</code>). Ubah password di database menjadi hash bcrypt!</div>";
                    } else {
                        $test_result = "<div class='alert alert-danger'><strong>Gagal:</strong> Password salah, dan format password di database bukan format hash (Plain Text).</div>";
                    }
                } else {
                    if (password_verify($test_pass, $db_pass)) {
                        $test_result = "<div class='alert alert-success'><strong>Berhasil!</strong> Autentikasi sukses untuk username <code>" . htmlspecialchars($test_user) . "</code> di database <strong>" . strtoupper($target_db) . "</strong>. Role: <code>" . htmlspecialchars($user['role'] ?? 'tidak diatur (default ke user)') . "</code>.</div>";
                    } else {
                        $test_result = "<div class='alert alert-danger'><strong>Gagal:</strong> Password tidak cocok dengan hash yang ada di database.</div>";
                    }
                }
            }
        } catch (PDOException $e) {
            $test_result = "<div class='alert alert-danger'><strong>Error:</strong> " . $e->getMessage() . "</div>";
        }
    } else if ($_POST['action'] === 'create_user') {
        $target_db = $_POST['target_db'] === 'indoor' ? 'indoor' : 'outdoor';
        $new_user = trim($_POST['new_username']);
        $new_pass = $_POST['new_password'];
        $new_role = $_POST['new_role'] === 'admin' ? 'admin' : 'user';
        
        $pdo_conn = ($target_db === 'indoor') ? $pdo_indoor : $pdo_outdoor;
        
        try {
            if (!$pdo_conn) {
                throw new Exception("Koneksi database gagal. Periksa koneksi.php Anda.");
            }
            $table_name = ($target_db === 'indoor') ? 'login' : 'pengguna';
            
            // Cek apakah username sudah ada
            $stmt = $pdo_conn->prepare("SELECT id FROM $table_name WHERE username = ?");
            $stmt->execute([$new_user]);
            $exists = $stmt->fetch();
            
            // Hash password dengan Bcrypt
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            
            // Cek struktur kolom tabel
            $columns = [];
            $colQuery = $pdo_conn->query("SHOW COLUMNS FROM $table_name");
            while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $col['Field'];
            }
            
            if ($exists) {
                // Update password dan role (jika kolom ada)
                if (in_array('role', $columns)) {
                    $stmt = $pdo_conn->prepare("UPDATE $table_name SET password = ?, role = ? WHERE username = ?");
                    $stmt->execute([$hashed, $new_role, $new_user]);
                } else {
                    $stmt = $pdo_conn->prepare("UPDATE $table_name SET password = ? WHERE username = ?");
                    $stmt->execute([$hashed, $new_user]);
                }
                $test_result = "<div class='alert alert-success'><strong>Berhasil:</strong> Akun <code>" . htmlspecialchars($new_user) . "</code> di database <strong>" . strtoupper($target_db) . "</strong> telah diperbarui dengan password ter-hash yang baru!</div>";
            } else {
                // Insert baru
                if (in_array('role', $columns)) {
                    // Cek created_at
                    if (in_array('created_at', $columns)) {
                        $stmt = $pdo_conn->prepare("INSERT INTO $table_name (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$new_user, $hashed, $new_role]);
                    } else {
                        $stmt = $pdo_conn->prepare("INSERT INTO $table_name (username, password, role) VALUES (?, ?, ?)");
                        $stmt->execute([$new_user, $hashed, $new_role]);
                    }
                } else {
                    if (in_array('created_at', $columns)) {
                        $stmt = $pdo_conn->prepare("INSERT INTO $table_name (username, password, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$new_user, $hashed]);
                    } else {
                        $stmt = $pdo_conn->prepare("INSERT INTO $table_name (username, password) VALUES (?, ?)");
                        $stmt->execute([$new_user, $hashed]);
                    }
                }
                $test_result = "<div class='alert alert-success'><strong>Berhasil:</strong> Akun <code>" . htmlspecialchars($new_user) . "</code> di database <strong>" . strtoupper($target_db) . "</strong> berhasil dibuat dengan password ter-hash!</div>";
            }
        } catch (Exception $e) {
            $test_result = "<div class='alert alert-danger'><strong>Error Pembuatan Akun:</strong> " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREDETECTOR - Database & Login Diagnostic Tool</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
            color: #333;
            padding: 30px 15px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        h1 {
            color: #1e3c72;
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h2 {
            font-size: 18px;
            color: #2d3748;
            margin-top: 25px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .db-status-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .db-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 12px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 8px;
        }
        .status-badge.success {
            background: #dcfce7;
            color: #16a34a;
        }
        .status-badge.danger {
            background: #fee2e2;
            color: #dc2626;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 14px;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #edf2f7;
        }
        th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
        }
        .password-format {
            font-family: monospace;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        .password-format.hashed {
            background: #e6fffa;
            color: #047481;
        }
        .password-format.plain {
            background: #fffaf0;
            color: #dd6b20;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background: #3182ce;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: background 0.2s;
        }
        button:hover {
            background: #2b6cb0;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
        }
        .alert-success { background: #dcfce7; color: #16a34a; border-left: 4px solid #16a34a; }
        .alert-danger { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        .alert-warning { background: #fef3c7; color: #d97706; border-left: 4px solid #d97706; }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-tools"></i> Database & Login Diagnostic Tool</h1>
    <p>Gunakan halaman ini secara lokal untuk memeriksa koneksi database Anda dan melihat mengapa kredensial login Anda ditolak.</p>

    <h2><i class="fas fa-database"></i> Status Koneksi Database</h2>
    <div class="db-status-container">
        <!-- DB OUTDOOR -->
        <div class="db-card">
            <strong>Database OUTDOOR (outdoor)</strong>
            <div>
                <?php
                try {
                    $pdo_outdoor->query("SELECT 1");
                    echo '<span class="status-badge success"><i class="fas fa-check-circle"></i> Terhubung</span>';
                } catch (Exception $e) {
                    echo '<span class="status-badge danger"><i class="fas fa-times-circle"></i> Gagal</span>';
                    echo '<div style="font-size:12px; color:red; margin-top:5px;">' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                ?>
            </div>
        </div>

        <!-- DB INDOOR -->
        <div class="db-card">
            <strong>Database INDOOR (firenet)</strong>
            <div>
                <?php
                try {
                    $pdo_indoor->query("SELECT 1");
                    echo '<span class="status-badge success"><i class="fas fa-check-circle"></i> Terhubung</span>';
                } catch (Exception $e) {
                    echo '<span class="status-badge danger"><i class="fas fa-times-circle"></i> Gagal</span>';
                    echo '<div style="font-size:12px; color:red; margin-top:5px;">' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <h2><i class="fas fa-users"></i> Daftar Pengguna di Database INDOOR (firenet)</h2>
    <?php
    try {
        $stmt = $pdo_indoor->query("SELECT id, username, password, role FROM login");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($users) == 0) {
            echo "<p>Tidak ada pengguna yang terdaftar di database Indoor.</p>";
        } else {
            echo "<table>";
            echo "<thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Format Password di DB</th></tr></thead>";
            echo "<tbody>";
            foreach ($users as $u) {
                $p_hash = $u['password'];
                $is_hashed = (strpos($p_hash, '$2y$') === 0 || strpos($p_hash, '$2a$') === 0);
                $format_class = $is_hashed ? 'hashed' : 'plain';
                $format_text = $is_hashed ? 'Hashed (Bcrypt - OK)' : 'Plain Text (SALAH - password_verify akan gagal)';
                
                echo "<tr>";
                echo "<td>" . htmlspecialchars($u['id']) . "</td>";
                echo "<td><strong>" . htmlspecialchars($u['username']) . "</strong></td>";
                echo "<td><code>" . htmlspecialchars($u['role'] ?? 'NULL (default ke user)') . "</code></td>";
                echo "<td><span class='password-format $format_class'>$format_text</span></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Gagal membaca tabel login Indoor: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>

    <h2><i class="fas fa-key"></i> Simulasikan Tes Autentikasi</h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="test_login">
        <div class="form-group">
            <label for="target_db">Pilih Database Target</label>
            <select name="target_db" id="target_db">
                <option value="indoor" <?= isset($_POST['target_db']) && $_POST['target_db'] === 'indoor' ? 'selected' : '' ?>>INDOOR (firenet)</option>
                <option value="outdoor" <?= isset($_POST['target_db']) && $_POST['target_db'] === 'outdoor' ? 'selected' : '' ?>>OUTDOOR (outdoor)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>
        <button type="submit">Uji Autentikasi</button>
    </form>

    <h2><i class="fas fa-user-plus"></i> Tambah / Perbarui Akun Pengguna</h2>
    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Gunakan form ini untuk membuat user baru atau memperbarui password user lama yang belum ter-hash (Bcrypt) di database.</p>
    <form method="POST" action="">
        <input type="hidden" name="action" value="create_user">
        <div class="form-group">
            <label for="new_target_db">Pilih Database Target</label>
            <select name="target_db" id="new_target_db">
                <option value="indoor">INDOOR (firenet)</option>
                <option value="outdoor">OUTDOOR (outdoor)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="new_username">Username Baru / Lama</label>
            <input type="text" name="new_username" id="new_username" required>
        </div>
        <div class="form-group">
            <label for="new_password">Password</label>
            <input type="password" name="new_password" id="new_password" required>
        </div>
        <div class="form-group">
            <label for="new_role">Role</label>
            <select name="new_role" id="new_role">
                <option value="admin">Admin</option>
                <option value="user">User</option>
            </select>
        </div>
        <button type="submit" style="background: #2ec4b6;">Buat / Update Akun</button>
    </form>

    <?= $test_result ?>
</div>

</body>
</html>
