<?php
session_start();

// Koneksi ke database
require_once 'koneksi.php';

$error = '';

// Proses Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Username dan Password harus diisi!";
    } else {
        try {
            // Query ke database
            $stmt = $pdo->prepare("SELECT * FROM login WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verifikasi password menggunakan password_verify() (BENAR)
            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['login_time'] = time();
                
                // Cek role dari database
                if (isset($user['role'])) {
                    $_SESSION['role'] = $user['role'];
                } else {
                    // Fallback: tentukan role berdasarkan username
                    $_SESSION['role'] = ($username == 'admin') ? 'admin' : 'user';
                }
                
                // Redirect berdasarkan role
                if ($_SESSION['role'] == 'admin') {
                    header("Location: dashboard_admin.php");
                } else {
                    header("Location: dashboard_user.php");
                }
                exit();
            } else {
                $error = "Username atau Password salah!";
            }
        } catch(PDOException $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: dashboard_admin.php");
    } else {
        header("Location: dashboard_user.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - FIREDETECTOR</title>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

/* Login Container */
.login-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 450px;
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
.login-header {
    background: linear-gradient(135deg, rgba(30, 60, 114, 0.9), rgba(42, 82, 152, 0.9));
    padding: 35px 30px;
    text-align: center;
    color: white;
}

.login-header i {
    font-size: 50px;
    margin-bottom: 15px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.login-header h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.login-header p {
    font-size: 14px;
    opacity: 0.9;
}

/* Form */
.login-form {
    padding: 35px 40px 40px 40px;
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
    font-size: 16px;
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

/* Password toggle */
.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #999;
    transition: color 0.3s;
    z-index: 1;
}

.password-toggle:hover {
    color: #667eea;
}

/* Alert Messages */
.alert {
    padding: 12px 15px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
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
    font-size: 16px;
}

/* Button Login */
.btn-login {
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

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
}

.btn-login:active {
    transform: translateY(0);
}

/* Register Link */
.register-link {
    text-align: center;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.register-link p {
    color: #666;
    font-size: 14px;
}

.register-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s;
}

.register-link a:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Back to Home */
.back-home {
    display: inline-block;
    margin-top: 12px;
    color: #667eea;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s;
}

.back-home:hover {
    color: #764ba2;
}

/* Info Akun Demo */
.demo-info {
    background: rgba(102, 126, 234, 0.1);
    border-radius: 12px;
    padding: 12px;
    margin-top: 20px;
    text-align: center;
}

.demo-info h4 {
    color: #1e3c72;
    margin-bottom: 8px;
    font-size: 14px;
}

.demo-info p {
    font-size: 12px;
    color: #666;
    margin: 4px 0;
}

.demo-info small {
    display: block;
    margin-top: 5px;
    font-size: 10px;
    color: #28a745;
}

/* Responsive */
@media (max-width: 576px) {
    .login-container {
        margin: 0 15px;
    }
    
    .login-form {
        padding: 25px 20px 30px 20px;
    }
    
    .login-header {
        padding: 25px;
    }
    
    .login-header h1 {
        font-size: 24px;
    }
}

/* Loading animation */
.loading {
    display: inline-block;
    width: 18px;
    height: 18px;
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

<div class="login-container">
    <div class="login-header">
        <i class="fas fa-sign-in-alt"></i>
        <h1>LOGIN</h1>
        <p>Silakan masuk ke akun Anda</p>
    </div>
    
    <div class="login-form">
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['register_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Registrasi berhasil! Silakan login dengan akun Anda.
        </div>
        <?php 
        unset($_SESSION['register_success']);
        endif; 
        ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" id="username" placeholder="USERNAME" 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                       autocomplete="off" required>
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="PASSWORD" required>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye-slash" id="toggleIcon"></i>
                </span>
            </div>
            
            <button type="submit" class="btn-login" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Masuk</span>
            </button>
        </form>
        
        <div class="register-link">
            <a href="home.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    }
}

// Loading effect on submit
const form = document.getElementById('loginForm');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    if (!username || !password) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian!',
            text: 'Username dan Password harus diisi!',
            confirmButtonColor: '#667eea'
        });
        return;
    }
    
    // Tampilkan loading
    submitBtn.innerHTML = '<div class="loading"></div> Memproses...';
    submitBtn.disabled = true;
});

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

// Enter key submit
document.getElementById('password').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        form.dispatchEvent(new Event('submit'));
    }
});
</script>

</body>
</html>