<?php
session_start();
include 'database.php';

// Nếu đã đăng nhập thì chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate dữ liệu
    if (empty($full_name)) {
        $errors['full_name'] = "Họ và tên không được để trống!";
    } elseif (strlen($full_name) < 3) {
        $errors['full_name'] = "Họ và tên phải có ít nhất 3 ký tự!";
    }
    
    if (empty($username)) {
        $errors['username'] = "Tên đăng nhập không được để trống!";
    } elseif (strlen($username) < 3) {
        $errors['username'] = "Tên đăng nhập phải có ít nhất 3 ký tự!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới!";
    } else {
        // Kiểm tra username đã tồn tại chưa
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors['username'] = "Tên đăng nhập đã tồn tại!";
        }
    }
    
    if (empty($password)) {
        $errors['password'] = "Mật khẩu không được để trống!";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Mật khẩu phải có ít nhất 6 ký tự!";
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Mật khẩu xác nhận không khớp!";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email không hợp lệ!";
    }
    
    if (!empty($phone) && !preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors['phone'] = "Số điện thoại không hợp lệ!";
    }
    
    // Nếu không có lỗi, tiến hành đăng ký
    if (empty($errors)) {
        // Mã hóa mật khẩu
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Mặc định role là 'user'
        $role = 'user';
        
        // Tạo mã kích hoạt (demo, thực tế sẽ gửi email)
        $activation_code = bin2hex(random_bytes(16));
        $is_active = 1; // 1 = active (demo), thực tế sẽ là 0 và cần kích hoạt qua email
        
        // Insert vào database với đầy đủ các trường
        $sql = "INSERT INTO users (full_name, username, password, email, phone, role, activation_code, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        // Sửa lỗi ở đây: bind_param có 8 tham số string và 1 integer
        $stmt->bind_param("sssssssi", $full_name, $username, $hashed_password, $email, $phone, $role, $activation_code, $is_active);
        
        if ($stmt->execute()) {
            $success = true;
            $user_id = $stmt->insert_id;
            
            // Tự động đăng nhập sau khi đăng ký thành công (chỉ khi is_active = 1)
            if ($is_active == 1) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
            }
            
            // Chờ 2 giây rồi chuyển hướng
            header('refresh:2;url=' . ($is_active == 1 ? 'index.php' : 'login.php'));
        } else {
            $errors['database'] = "Có lỗi xảy ra khi đăng ký. Vui lòng thử lại! " . htmlspecialchars($stmt->error);
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
    <title>Đăng ký - Hệ Thống Mô Phỏng Giao Thông</title>
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

        .login-btn {
            background: transparent;
            color: #3498db;
            border-color: #3498db;
        }

        .login-btn:hover {
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

        /* Register Container */
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
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

        .register-header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .register-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .register-header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .register-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .register-body {
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

        .form-group label .required {
            color: #e74c3c;
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
            border-color: #2ecc71;
            background: white;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
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

        /* Password strength indicator */
        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 5px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .strength-level {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }

        .strength-text {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        /* Success message */
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
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message i {
            font-size: 1.2rem;
        }

        /* Two column layout for some fields */
        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .register-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
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
            margin-top: 20px;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(46, 204, 113, 0.3);
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        /* Terms and conditions */
        .terms {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .terms input {
            margin-right: 8px;
        }

        .terms a {
            color: #3498db;
            text-decoration: none;
        }

        .terms a:hover {
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
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .register-container {
                margin: 20px;
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
                        <li><a href="register.php" class="active"><i class="fas fa-user-plus"></i> Đăng ký</a></li>
                    </ul>
                </nav>
                
                <div class="auth-section">
                    <div class="auth-buttons">
                        <a href="login.php" class="auth-btn login-btn">
                            <i class="fas fa-sign-in-alt"></i> Đăng nhập
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="register-container">
            <div class="register-header">
                <i class="fas fa-user-plus"></i>
                <h2>TẠO TÀI KHOẢN MỚI</h2>
                <p>Tham gia hệ thống mô phỏng giao thông thông minh</p>
            </div>
            
            <div class="register-body">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Đăng ký thành công!</strong>
                            <?php if ($is_active == 1): ?>
                                <p>Bạn đã được tự động đăng nhập. Chuyển hướng đến trang chủ trong giây lát...</p>
                            <?php else: ?>
                                <p>Vui lòng kiểm tra email để kích hoạt tài khoản. Chuyển hướng đến trang đăng nhập...</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors['database'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $errors['database']; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="form-group">
                        <label for="full_name">Họ và tên <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   id="full_name" 
                                   name="full_name" 
                                   placeholder="Nhập họ và tên đầy đủ" 
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                   required>
                        </div>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo $errors['full_name']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Tên đăng nhập <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-at"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Nhập tên đăng nhập" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   required>
                        </div>
                        <small style="color: #7f8c8d; font-size: 0.85rem;">Chỉ chứa chữ cái, số và dấu gạch dưới</small>
                        <?php if (isset($errors['username'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo $errors['username']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Mật khẩu <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-key"></i>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Nhập mật khẩu" 
                                       required>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-level" id="strengthLevel"></div>
                                </div>
                                <div class="strength-text" id="strengthText">Độ mạnh mật khẩu</div>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo $errors['password']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Xác nhận mật khẩu <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-key"></i>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Nhập lại mật khẩu" 
                                       required>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo $errors['confirm_password']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       placeholder="example@email.com" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <?php if (isset($errors['email'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo $errors['email']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Số điện thoại</label>
                            <div class="input-with-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="0123456789" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            <?php if (isset($errors['phone'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo $errors['phone']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="terms">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            Tôi đồng ý với 
                            <a href="#" onclick="alert('Điều khoản sử dụng sẽ được hiển thị ở đây'); return false;">
                                Điều khoản dịch vụ
                            </a> 
                            và 
                            <a href="#" onclick="alert('Chính sách bảo mật sẽ được hiển thị ở đây'); return false;">
                                Chính sách bảo mật
                            </a>
                            <span class="required">*</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="register-btn" id="registerBtn">
                        <i class="fas fa-user-plus"></i>
                        ĐĂNG KÝ TÀI KHOẢN
                    </button>
                </form>
                
                <div class="login-link">
                    Đã có tài khoản?
                    <a href="login.php">Đăng nhập ngay</a>
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
        
        // Kiểm tra độ mạnh của mật khẩu
        function checkPasswordStrength(password) {
            let strength = 0;
            const strengthBar = document.getElementById('strengthLevel');
            const strengthText = document.getElementById('strengthText');
            
            if (!password) {
                strengthBar.style.width = '0%';
                strengthBar.style.backgroundColor = '#eee';
                strengthText.textContent = 'Độ mạnh mật khẩu';
                strengthText.style.color = '#7f8c8d';
                return;
            }
            
            // Kiểm tra độ dài
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            
            // Kiểm tra chữ hoa, chữ thường, số, ký tự đặc biệt
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // Cập nhật thanh hiển thị
            let width = 0;
            let color = '';
            let text = '';
            
            switch(strength) {
                case 0:
                case 1:
                    width = '20%';
                    color = '#e74c3c';
                    text = 'Rất yếu';
                    break;
                case 2:
                    width = '40%';
                    color = '#e67e22';
                    text = 'Yếu';
                    break;
                case 3:
                    width = '60%';
                    color = '#f1c40f';
                    text = 'Trung bình';
                    break;
                case 4:
                    width = '80%';
                    color = '#2ecc71';
                    text = 'Mạnh';
                    break;
                case 5:
                case 6:
                    width = '100%';
                    color = '#27ae60';
                    text = 'Rất mạnh';
                    break;
            }
            
            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = `Độ mạnh: ${text}`;
            strengthText.style.color = color;
        }
        
        // Kiểm tra mật khẩu khớp
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword === '') return;
            
            if (password !== confirmPassword) {
                confirmInput.classList.add('error');
            } else {
                confirmInput.classList.remove('error');
            }
        }
        
        // Xử lý form
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            const registerBtn = document.getElementById('registerBtn');
            
            // Xóa class error trước đó
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => input.classList.remove('error'));
            
            // Xóa thông báo lỗi cũ
            const errorMessages = document.querySelectorAll('.error-message');
            errorMessages.forEach(error => {
                if (error.parentElement.classList.contains('form-group')) {
                    error.remove();
                }
            });
            
            let hasError = false;
            
            if (!fullName || fullName.length < 3) {
                document.getElementById('full_name').classList.add('error');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Họ và tên phải có ít nhất 3 ký tự!</span>';
                document.getElementById('full_name').parentElement.parentElement.appendChild(errorDiv);
                hasError = true;
            }
            
            const usernamePattern = /^[a-zA-Z0-9_]+$/;
            if (!username || username.length < 3 || !usernamePattern.test(username)) {
                document.getElementById('username').classList.add('error');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                if (!username) {
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Tên đăng nhập không được để trống!</span>';
                } else if (username.length < 3) {
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Tên đăng nhập phải có ít nhất 3 ký tự!</span>';
                } else {
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới!</span>';
                }
                document.getElementById('username').parentElement.parentElement.appendChild(errorDiv);
                hasError = true;
            }
            
            if (!password || password.length < 6) {
                document.getElementById('password').classList.add('error');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Mật khẩu phải có ít nhất 6 ký tự!</span>';
                document.getElementById('password').parentElement.parentElement.appendChild(errorDiv);
                hasError = true;
            }
            
            if (password !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('error');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Mật khẩu xác nhận không khớp!</span>';
                document.getElementById('confirm_password').parentElement.parentElement.appendChild(errorDiv);
                hasError = true;
            }
            
            if (!terms) {
                alert('Vui lòng đồng ý với Điều khoản dịch vụ và Chính sách bảo mật!');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                return false;
            }
            
            // Thêm hiệu ứng loading
            registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG XỬ LÝ...';
            registerBtn.disabled = true;
        });
        
        // Gắn sự kiện cho các input
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Hiệu ứng focus cho input
        const inputs = document.querySelectorAll('.input-with-icon input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
        
        // Kiểm tra username tồn tại (demo)
        document.getElementById('username').addEventListener('blur', function() {
            const username = this.value.trim();
            if (username === 'admin') {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Tên đăng nhập đã tồn tại!</span>';
                
                // Xóa thông báo lỗi cũ nếu có
                const existingError = this.parentElement.parentElement.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                this.parentElement.parentElement.appendChild(errorDiv);
                this.classList.add('error');
            }
        });
    </script>
</body>
</html>