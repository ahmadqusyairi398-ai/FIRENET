<?php
session_start();

// Koneksi ke database
require_once 'koneksi.php';

$error = '';
$showSuccessModal = false;

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Semua field harus diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan Konfirmasi Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        try {
            // Cek apakah username sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM login WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Username sudah terdaftar! Silakan gunakan username lain.";
            } else {
                // Hash password untuk keamanan (INI YANG BENAR)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert data ke database (tanpa role, default user)
                $stmt = $pdo->prepare("INSERT INTO login (username, password, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$username, $hashed_password]);
                
                // Cek apakah insert berhasil
                if ($stmt->rowCount() > 0) {
                    $showSuccessModal = true;
                } else {
                    $error = "Gagal mendaftarkan akun. Silakan coba lagi.";
                }
            }
        } catch(PDOException $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - FIREDETECTOR</title>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    background-image: url('https://i.pinimg.com/736x/ea/7c/ca/ea7cca792d193c0a4599fbcf96f21fa3.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

/* Overlay gelap agar teks lebih terbaca */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 0;
}

/* Background animated bubbles */
.bubble {
    position: absolute;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    pointer-events: none;
    animation: float 20s infinite ease-in-out;
    z-index: 1;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
}

.bubble-1 {
    top: -100px;
    left: -100px;
    width: 400px;
    height: 400px;
    animation-duration: 25s;
}

.bubble-2 {
    bottom: -150px;
    right: -150px;
    width: 500px;
    height: 500px;
    animation-duration: 30s;
}

.bubble-3 {
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    height: 600px;
    opacity: 0.15;
    animation-duration: 35s;
}

/* Register Container */
.register-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 480px;
    overflow: hidden;
    position: relative;
    z-index: 2;
    backdrop-filter: blur(10px);
    animation: slideUp 0.5s ease;
    border: 1px solid rgba(255,255,255,0.2);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Header */
.register-header {
    background: linear-gradient(135deg, rgba(30, 60, 114, 0.9), rgba(42, 82, 152, 0.9));
    padding: 30px;
    text-align: center;
    color: white;
}

.register-header i {
    font-size: 50px;
    margin-bottom: 15px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.register-header h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.register-header p {
    font-size: 14px;
    opacity: 0.9;
}

/* Form */
.register-form {
    padding: 40px;
}

.input-group {
    margin-bottom: 25px;
    position: relative;
}

.input-group i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 18px;
    transition: all 0.3s;
    z-index: 1;
}

.input-group input {
    width: 100%;
    padding: 14px 15px 14px 45px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s;
    background: white;
}

.input-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.input-group input:focus + i {
    color: #667eea;
}

/* Alert Messages */
.alert {
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border-left: 4px solid #dc2626;
}

.alert-success {
    background: #dcfce7;
    color: #16a34a;
    border-left: 4px solid #16a34a;
}

.alert i {
    font-size: 18px;
}

/* Button */
.btn-register {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
}

.btn-register::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-register:hover::before {
    left: 100%;
}

.btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
}

.btn-register:active {
    transform: translateY(0);
}

/* Login Link */
.login-link {
    text-align: center;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.login-link p {
    color: #666;
    font-size: 14px;
}

.login-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s;
}

.login-link a:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Password Strength */
.password-strength {
    margin-top: 8px;
    font-size: 12px;
}

/* Back to Home */
.back-home {
    display: inline-block;
    margin-top: 15px;
    color: #667eea;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s;
}

.back-home:hover {
    color: #764ba2;
}

/* Info Note */
.info-note {
    background: #f0f4ff;
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    border-left: 4px solid #667eea;
}

.info-note i {
    color: #667eea;
    margin-right: 8px;
}

.info-note span {
    color: #667eea;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 576px) {
    .register-container {
        margin: 0 15px;
    }
    
    .register-form {
        padding: 30px 20px;
    }
    
    .register-header {
        padding: 25px;
    }
    
    .register-header h1 {
        font-size: 24px;
    }
}

/* Animasi loading */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid white;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
</head>
<body>

<div class="bubble bubble-1"></div>
<div class="bubble bubble-2"></div>
<div class="bubble bubble-3"></div>

<div class="register-container">
    <div class="register-header">
        <i class="fas fa-user-plus"></i>
        <h1>REGISTER</h1>
        <p>Buat akun baru untuk memulai</p>
    </div>
    
    <div class="register-form">
        
        <!-- Informasi Note -->
        <div class="info-note">
            <i class="fas fa-info-circle"></i>
            Mendaftar sebagai: <span><i class="fas fa-user"></i> USER</span>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="USERNAME" 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                       required autocomplete="off">
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="PASSWORD (min. 6 karakter)" required>
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" id="confirm_password" 
                       placeholder="KETIK ULANG PASSWORD" required>
            </div>
            
            <button type="submit" class="btn-register" id="submitBtn">
                <i class="fas fa-user-plus"></i>
                <span>Daftar Sekarang</span>
            </button>
        </form>
        
        <div class="login-link">
            <p>Sudah punya akun? <a href="login.php">Login Now</a></p>
            <a href="home.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>

<script>
// Password match validation
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');
const form = document.getElementById('registerForm');

// Real-time password match check
confirmPassword.addEventListener('input', function() {
    if (password.value !== this.value) {
        this.setCustomValidity('Password tidak cocok!');
        this.style.borderColor = '#dc2626';
        
        let errorMsg = document.getElementById('passwordMatchError');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.id = 'passwordMatchError';
            errorMsg.className = 'password-strength';
            errorMsg.style.color = '#dc2626';
            errorMsg.style.fontSize = '12px';
            errorMsg.style.marginTop = '5px';
            this.parentNode.appendChild(errorMsg);
        }
        errorMsg.innerHTML = '<i class="fas fa-times-circle"></i> Password tidak cocok!';
    } else {
        this.setCustomValidity('');
        this.style.borderColor = '#e0e0e0';
        
        const errorMsg = document.getElementById('passwordMatchError');
        if (errorMsg) {
            errorMsg.remove();
        }
    }
});

// Password strength indicator
password.addEventListener('input', function() {
    const strength = checkPasswordStrength(this.value);
    updateStrengthIndicator(strength);
});

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    if (password.length === 0) return 0;
    if (strength <= 2) return 1;
    if (strength <= 4) return 2;
    return 3;
}

function updateStrengthIndicator(strength) {
    let indicator = document.getElementById('strengthIndicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'strengthIndicator';
        indicator.className = 'password-strength';
        password.parentNode.appendChild(indicator);
    }
    
    if (strength === 0) {
        indicator.innerHTML = '';
        return;
    }
    
    const strengths = {
        1: { text: 'Lemah', color: '#dc2626', width: '33%' },
        2: { text: 'Sedang', color: '#f59e0b', width: '66%' },
        3: { text: 'Kuat', color: '#10b981', width: '100%' }
    };
    
    const s = strengths[strength];
    indicator.innerHTML = `
        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
            <span>🔒 Kekuatan Password: <strong style="color: ${s.color}">${s.text}</strong></span>
        </div>
        <div style="height: 4px; background: #e0e0e0; border-radius: 2px; overflow: hidden;">
            <div style="height: 100%; width: ${s.width}; background: ${s.color}; transition: all 0.3s;"></div>
        </div>
    `;
}

// Submit form dengan validasi
form.addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    
    // Validasi password match
    if (password.value !== confirmPassword.value) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Password dan Konfirmasi Password tidak cocok!',
            confirmButtonColor: '#dc2626'
        });
        return;
    }
    
    // Validasi panjang password
    if (password.value.length < 6) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian!',
            text: 'Password minimal 6 karakter!',
            confirmButtonColor: '#667eea'
        });
        return;
    }
    
    // Validasi form kosong
    const username = document.querySelector('input[name="username"]').value;
    
    if (!username || !password.value) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian!',
            text: 'Semua field harus diisi!',
            confirmButtonColor: '#667eea'
        });
        return;
    }
    
    // Tampilkan loading
    btn.innerHTML = '<div class="loading"></div> Memproses...';
    btn.disabled = true;
});

// Tampilkan SweetAlert jika registrasi berhasil
<?php if ($showSuccessModal): ?>
Swal.fire({
    icon: 'success',
    title: 'Registrasi Berhasil!',
    html: 'Selamat datang, <strong><?= htmlspecialchars($_POST['username']) ?></strong>!<br>Anda terdaftar sebagai <strong><i class="fas fa-user"></i> USER</strong>.<br><br>Silakan login dengan akun Anda.',
    confirmButtonColor: '#16a34a',
    confirmButtonText: 'OK, Lanjutkan Login'
}).then(() => {
    window.location.href = 'login.php';
});
<?php endif; ?>

// Animasi input focus
document.querySelectorAll('.input-group input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateX(5px)';
        this.parentElement.style.transition = 'all 0.3s';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateX(0)';
    });
});
</script>

</body>
</html>