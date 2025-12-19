<?php
session_start();

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'database.php';

// Nếu đã đăng nhập thì chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Vui lòng nhập email của bạn!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } else {
        // KIỂM TRA NGAY: CÓ KẾT NỐI DATABASE KHÔNG?
        if (!$conn) {
            $error = "❌ Không thể kết nối database!";
        } else {
            // Kiểm tra và thêm cột email nếu chưa có
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100)");
            
            // Kiểm tra email có tồn tại không
            $sql = "SELECT id, username, full_name FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                $error = "Lỗi kiểm tra email: " . htmlspecialchars($conn->error);
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $reset_token = bin2hex(random_bytes(16));
                    
                    // TẠO BẢNG NẾU CHƯA CÓ (câu lệnh đơn giản)
                    $create_table = "CREATE TABLE IF NOT EXISTS password_reset_requests (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        username VARCHAR(50) NOT NULL,
                        email VARCHAR(100) NOT NULL,
                        full_name VARCHAR(100),
                        reset_token VARCHAR(32),
                        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        status VARCHAR(20) DEFAULT 'pending'
                    )";
                    
                    if (!$conn->query($create_table)) {
                        $error = "Lỗi tạo bảng: " . htmlspecialchars($conn->error);
                    } else {
                        // INSERT vào bảng
                        $insert_sql = "INSERT INTO password_reset_requests (user_id, username, email, full_name, reset_token) 
                                      VALUES (?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_sql);
                        
                        if (!$insert_stmt) {
                            $error = "Lỗi chuẩn bị INSERT: " . htmlspecialchars($conn->error);
                        } else {
                            $insert_stmt->bind_param("issss", 
                                $user['id'], 
                                $user['username'], 
                                $email, 
                                $user['full_name'], 
                                $reset_token
                            );
                            
                            if ($insert_stmt->execute()) {
                                $success = "✅ Yêu cầu đã được gửi thành công! Quản trị viên sẽ xử lý và liên hệ với bạn.";
                            } else {
                                $error = "❌ Lỗi khi lưu yêu cầu: " . htmlspecialchars($insert_stmt->error);
                            }
                        }
                    }
                } else {
                    $error = "❌ Email không tồn tại trong hệ thống!";
                }
            }
        }
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
    <title>Quên mật khẩu - Hệ Thống Mô Phỏng Giao Thông</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS giữ nguyên như trước, chỉ sửa phần PHP */
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

        /* Header */
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

        /* Main Content */
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 160px);
            padding: 40px 20px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.3));
        }

        /* Forgot Password Container */
        .forgot-container {
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

        .forgot-header {
            background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .forgot-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .forgot-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .forgot-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .forgot-body {
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
            border-color: #f39c12;
            background: white;
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
        }

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message i {
            font-size: 0.9rem;
        }

        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i {
            color: #28a745;
            font-size: 1.2rem;
        }

        .instructions {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #495057;
        }

        .instructions h4 {
            margin-bottom: 10px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .instructions ul {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .instructions li {
            margin-bottom: 5px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
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

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(243, 156, 18, 0.3);
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .back-to-login a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            transition: color 0.3s ease;
        }

        .back-to-login a:hover {
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
                        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Đăng nhập</a></li>
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
        <div class="forgot-container">
            <div class="forgot-header">
                <i class="fas fa-key"></i>
                <h2>QUÊN MẬT KHẨU</h2>
                <p>Khôi phục mật khẩu tài khoản của bạn</p>
            </div>
            
            <div class="forgot-body">
                <div class="instructions">
                    <h4><i class="fas fa-info-circle"></i> Hướng dẫn:</h4>
                    <ul>
                        <li>Nhập email đã đăng ký tài khoản của bạn</li>
                        <li>Yêu cầu reset mật khẩu sẽ được gửi đến Admin</li>
                        <li>Admin sẽ xử lý và gửi mật khẩu mới qua email của bạn</li>
                        <li>Vui lòng kiểm tra hộp thư email (cả spam)</li>
                    </ul>
                </div>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="forgotForm">
                    <div class="form-group">
                        <label for="email">Email đăng ký</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Nhập email đã đăng ký tài khoản"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        GỬI YÊU CẦU RESET
                    </button>
                </form>
                
                <div class="back-to-login">
                    Quay lại trang 
                    <a href="login.php">Đăng nhập</a>
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
    </script>
</body>
</html>