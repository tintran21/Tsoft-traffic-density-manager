<?php
session_start();
include 'database.php';

// Nếu đã đăng nhập thì chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Kiểm tra thông tin đăng nhập
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Kiểm tra mật khẩu
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header('Location: index.php');
            exit();
        } else {
            $error = "Mật khẩu không đúng!";
        }
    } else {
        $error = "Tên đăng nhập không tồn tại!";
    }
}

// Lấy thời gian hiện tại
$current_time = date('H:i:s');
$current_date = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Hệ Thống Mô Phỏng Giao Thông</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        /* Header - Giống với trang chính */
        .header {
            background: #ffffff;
            padding: 0;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1000;
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.3);
        }

        .logo-text h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .logo-text p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .time-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .current-time {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
        }

        .current-date {
            font-size: 1rem;
            color: #7f8c8d;
        }

        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 2px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        .nav-menu li {
            margin: 0;
            padding: 0;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid transparent;
        }

        .nav-menu a i {
            font-size: 1.1rem;
            color: #3498db;
        }

        .nav-menu a:hover {
            background: #e3f2fd;
            color: #2980b9;
            border-color: #3498db;
        }

        .nav-menu a.active {
            background: #3498db;
            color: white;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.3);
        }

        .nav-menu a.active i {
            color: white;
        }

        /* Auth buttons */
        .auth-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .auth-buttons {
            display: flex;
            gap: 10px;
        }

        .auth-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .register-btn {
            background: transparent;
            color: #3498db;
            border-color: #3498db;
        }

        .register-btn:hover {
            background: #3498db;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* Main Content */
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 160px);
            padding: 40px 20px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.3));
        }

        /* Login Container */
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .login-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .login-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .input-with-icon input.error {
            border-color: #e74c3c;
        }

        .error-message {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .error-message i {
            font-size: 0.9rem;
        }

        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i {
            color: #28a745;
            font-size: 1.2rem;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(52, 152, 219, 0.3);
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }

        .forgot-password-link {
            text-align: center;
            margin-top: 15px;
        }

        .forgot-password-link a {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .forgot-password-link a:hover {
            text-decoration: underline;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .register-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 0.9rem;
        }

        .footer p {
            margin: 5px 0;
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .time-info {
                align-items: center;
            }
            
            .nav-menu {
                flex-direction: column;
                width: 100%;
            }
            
            .nav-menu a {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="header-top">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-traffic-light"></i>
                    </div>
                    <div class="logo-text">
                        <h1>Hệ Thống Mô Phỏng Giao Thông</h1>
                        <p>Quản lý và điều khiển đèn giao thông thông minh</p>
                    </div>
                </div>
                
                <div class="time-info">
                    <div class="current-time" id="current-time"><?php echo $current_time; ?></div>
                    <div class="current-date" id="current-date"><?php echo $current_date; ?></div>
                </div>
            </div>
            
            <div class="header-main">
                <nav>
                    <ul class="nav-menu">
                        <li><a href="index.php"><i class="fas fa-home"></i> Trang chủ</a></li>
                        <li><a href="about.php"><i class="fas fa-info-circle"></i> Thông tin</a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> Liên hệ</a></li>
                        <li><a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i> Đăng nhập</a></li>
                    </ul>
                </nav>
                
                <div class="auth-section">
                    <div class="auth-buttons">
                        <a href="register.php" class="auth-btn register-btn">
                            <i class="fas fa-user-plus"></i> Đăng ký
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-lock"></i>
                <h2>ĐĂNG NHẬP HỆ THỐNG</h2>
                <p>Vui lòng nhập thông tin đăng nhập của bạn</p>
            </div>
            
            <div class="login-body">
                <?php if (isset($_GET['reset_success'])): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span>Yêu cầu reset mật khẩu đã được gửi đến admin. Vui lòng kiểm tra email của bạn!</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="username">Tên đăng nhập</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Nhập tên đăng nhập" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <div class="input-with-icon">
                            <i class="fas fa-key"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Nhập mật khẩu" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="forgot-password-link">
                        <a href="forgot_password.php">Quên mật khẩu?</a>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        ĐĂNG NHẬP
                    </button>
                </form>
                
                <div class="register-link">
                    Chưa có tài khoản?
                    <a href="register.php">Đăng ký ngay</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <p>© 2024 Hệ Thống Mô Phỏng Giao Thông. Bản quyền thuộc về nhóm phát triển.</p>
        <p>Phiên bản 2.0 - Hỗ trợ mô phỏng giao thông đa làn đường</p>
    </footer>

    <script>
        // Cập nhật thời gian thực
        function updateRealTimeClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('vi-VN', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('vi-VN');
            
            document.getElementById('current-time').textContent = timeString;
            document.getElementById('current-date').textContent = dateString;
        }
        
        // Cập nhật mỗi giây
        setInterval(updateRealTimeClock, 1000);
        
        // Xử lý form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            // Xóa class error trước đó
            document.getElementById('username').classList.remove('error');
            document.getElementById('password').classList.remove('error');
            
            if (!username) {
                e.preventDefault();
                document.getElementById('username').classList.add('error');
                alert('Vui lòng nhập tên đăng nhập!');
                return false;
            }
            
            if (!password) {
                e.preventDefault();
                document.getElementById('password').classList.add('error');
                alert('Vui lòng nhập mật khẩu!');
                return false;
            }
            
            // Thêm hiệu ứng loading
            const submitBtn = this.querySelector('.login-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG XỬ LÝ...';
            submitBtn.disabled = true;
        });
        
        // Hiệu ứng focus cho input
        const inputs = document.querySelectorAll('.input-with-icon input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
            
            // Xóa class error khi người dùng bắt đầu nhập
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });
    </script>
</body>
</html>