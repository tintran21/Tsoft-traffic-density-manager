<?php
session_start();
include 'database.php';

// Lấy thời gian hiện tại
$current_time = date('H:i:s');
$current_date = date('d/m/Y');

// Xử lý gửi liên hệ
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Vui lòng nhập họ tên";
    }
    
    if (empty($email)) {
        $errors[] = "Vui lòng nhập email";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ";
    }
    
    if (empty($subject)) {
        $errors[] = "Vui lòng nhập tiêu đề";
    }
    
    if (empty($message)) {
        $errors[] = "Vui lòng nhập nội dung";
    }
    
    if (empty($errors)) {
        // Lưu vào database
        $sql = "INSERT INTO contact_messages (name, email, subject, message, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $success_message = "Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi trong thời gian sớm nhất.";
                
                // Clear form
                $name = $email = $subject = $message = '';
            } else {
                $error_message = "Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại!";
            }
        } else {
            $error_message = "Lỗi hệ thống! Vui lòng thử lại sau.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Tạo bảng contact_messages nếu chưa có
$create_table_sql = "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($create_table_sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên hệ - Hệ Thống Mô Phỏng Giao Thông</title>
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
            color: #333;
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
            background: #3498db;
            color: white;
        }

        .login-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
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

        /* User info section */
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #2c3e50;
        }

        .user-info span {
            font-weight: 500;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 20px;
            border: 1px solid #eaeaea;
        }

        .admin-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .admin-link:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f8f9fa;
            color: #e74c3c;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid #f0f0f0;
        }

        .logout-link:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
            border-color: #e74c3c;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 50px;
            padding: 30px;
            background: linear-gradient(135deg,  #3498db 0%, #2c3e50 100%);
            border-radius: 20px;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .page-header i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Contact Layout */
        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 50px;
        }

        @media (max-width: 992px) {
            .contact-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Contact Form */
        .contact-form {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .contact-form h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group .required {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(52, 152, 219, 0.3);
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
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

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Contact Info */
        .contact-info {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .contact-info h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .info-content h4 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-content p {
            color: #7f8c8d;
            line-height: 1.6;
        }

        .info-content a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s;
        }

        .info-content a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            justify-content: center;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2c3e50;
            font-size: 1.2rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .social-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .social-link.facebook:hover {
            background: #3b5998;
            color: white;
            border-color: #3b5998;
        }

        .social-link.twitter:hover {
            background: #1da1f2;
            color: white;
            border-color: #1da1f2;
        }

        .social-link.linkedin:hover {
            background: #0077b5;
            color: white;
            border-color: #0077b5;
        }

        .social-link.youtube:hover {
            background: #ff0000;
            color: white;
            border-color: #ff0000;
        }

        /* FAQ Section */
        .faq-section {
            margin-top: 60px;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .faq-section h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
        }

        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .faq-item {
            border: 1px solid #eaeaea;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .faq-item.active {
            border-color: #3498db;
            box-shadow: 0 3px 15px rgba(52, 152, 219, 0.1);
        }

        .faq-question {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            color: #2c3e50;
        }

        .faq-question i {
            transition: transform 0.3s;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 500px;
        }

        .faq-answer p {
            color: #7f8c8d;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 20px;
            text-align: center;
            margin-top: 50px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            text-align: left;
        }

        .footer-section h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #3498db;
        }

        .footer-section p {
            opacity: 0.8;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .footer-section ul {
            list-style-type: none;
            padding-left: 0;
        }

        .footer-section li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-section li:last-child {
            border-bottom: none;
        }

        .footer-section a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section a:hover {
            color: #3498db;
        }

        .footer-bottom {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            opacity: 0.8;
            font-size: 0.9rem;
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
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .contact-form,
            .contact-info {
                padding: 25px;
            }
            
            .info-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
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
                        <li><a href="thongtin.php"><i class="fas fa-info-circle"></i> Thông tin</a></li>
                        <li><a href="lienhe.php" class="active"><i class="fas fa-envelope"></i> Liên hệ</a></li>
                    </ul>
                </nav>
                
                <div class="auth-section">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="admin-link">
                                <i class="fas fa-cog"></i> Quản trị
                            </a>
                        <?php endif; ?>
                        <div class="user-info">
                            <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <a href="logout.php" class="logout-link">
                                <i class="fas fa-sign-out-alt"></i> Đăng xuất
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="login.php" class="auth-btn login-btn">
                                <i class="fas fa-sign-in-alt"></i> Đăng nhập
                            </a>
                            <a href="register.php" class="auth-btn register-btn">
                                <i class="fas fa-user-plus"></i> Đăng ký
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <i class="fas fa-envelope"></i>
            <h1>Liên Hệ Với Chúng Tôi</h1>
            <p>Chúng tôi luôn sẵn sàng lắng nghe và hỗ trợ bạn. Hãy để lại tin nhắn nếu bạn có bất kỳ câu hỏi nào!</p>
        </div>

        <div class="contact-layout">
            <div class="contact-form">
                <h2><i class="fas fa-paper-plane"></i> Gửi Tin Nhắn</h2>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success_message; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error_message; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="contactForm">
                    <div class="form-group">
                        <label for="name">Họ và tên <span class="required">*</span></label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control"
                               placeholder="Nhập họ và tên của bạn"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control"
                               placeholder="Nhập email của bạn"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Tiêu đề <span class="required">*</span></label>
                        <input type="text" 
                               id="subject" 
                               name="subject" 
                               class="form-control"
                               placeholder="Nhập tiêu đề tin nhắn"
                               value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Nội dung <span class="required">*</span></label>
                        <textarea id="message" 
                                  name="message" 
                                  class="form-control"
                                  placeholder="Nhập nội dung tin nhắn của bạn..."
                                  required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        GỬI TIN NHẮN
                    </button>
                </form>
            </div>
            
            <div class="contact-info">
                <h2><i class="fas fa-info-circle"></i> Thông Tin Liên Hệ</h2>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Địa chỉ</h4>
                        <p>
                            54 Nguyễn Lương Bằng, phường Hòa Khánh<br>
                            Quận Liên Chiểu, TP. Đà Nẵng<br>
                            Việt Nam
                        </p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="info-content">
                        <h4>Số điện thoại</h4>
                        <p>
                            <a href="tel:+842812345678">0855894446</a><br>
                            <!-- <a href="tel:+84901234567">0901 234 567</a> (Hotline) -->
                        </p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <h4>Email</h4>
                        <p>
                            <a href="thuanthuan8a3@gmail.com">thuanthuan8a3@gmail.com</a><br>
                            <!-- <a href="mailto:support@traffic-simulation.vn">support@traffic-simulation.vn</a> -->
                        </p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h4>Giờ làm việc</h4>
                        <p>
                            Thứ 2 - Thứ 6: 8:00 - 17:30<br>
                            Thứ 7: 8:00 - 12:00<br>
                            <!-- Chủ nhật: Nghỉ -->
                        </p>
                    </div>
                </div>
                
                <div class="social-links">
                    <a href="https://www.facebook.com/dinhhoangthuan" class="social-link facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link linkedin">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="social-link youtube">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="faq-section">
            <h2><i class="fas fa-question-circle"></i> Câu Hỏi Thường Gặp</h2>
            
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Làm cách nào để đăng ký tài khoản?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Bạn có thể đăng ký tài khoản bằng cách nhấp vào nút "Đăng ký" ở góc trên bên phải trang web. Sau đó điền đầy đủ thông tin theo yêu cầu và nhấn nút "Đăng ký tài khoản".</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Tôi quên mật khẩu thì phải làm sao?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Tại trang đăng nhập, nhấp vào liên kết "Quên mật khẩu". Sau đó nhập email đã đăng ký, hệ thống sẽ gửi yêu cầu reset mật khẩu đến quản trị viên để hỗ trợ bạn.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Làm thế nào để truy cập vào trang quản trị?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Chỉ có tài khoản với quyền "Admin" mới có thể truy cập trang quản trị. Sau khi đăng nhập, nếu bạn có quyền admin, nút "Quản trị" sẽ xuất hiện ở góc trên bên phải.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Hệ thống mô phỏng hoạt động như thế nào?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Hệ thống sử dụng thuật toán điều khiển đèn giao thông thông minh dựa trên lưu lượng xe thực tế. Bạn có thể chọn chế độ điều khiển thủ công hoặc tự động tại trang chủ.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Có phiên bản di động cho ứng dụng này không?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Hiện tại hệ thống chỉ hỗ trợ phiên bản web responsive, có thể truy cập trên các thiết bị di động thông qua trình duyệt web. Chúng tôi đang phát triển ứng dụng di động riêng.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Hệ Thống Mô Phỏng Giao Thông</h3>
                <p>Ứng dụng công nghệ hiện đại để mô phỏng và tối ưu hóa hệ thống giao thông đô thị.</p>
                <p><i class="fas fa-map-marker-alt"></i> Địa chỉ: 123 Đường ABC, Quận XYZ, TP. Hồ Chí Minh</p>
                <p><i class="fas fa-phone"></i> Điện thoại: (028) 1234 5678</p>
            </div>
            
            <div class="footer-section">
                <h3>Liên Kết Nhanh</h3>
                <ul>
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Trang chủ</a></li>
                    <li><a href="thongtin.php"><i class="fas fa-chevron-right"></i> Thông tin hệ thống</a></li>
                    <li><a href="lienhe.php"><i class="fas fa-chevron-right"></i> Liên hệ</a></li>
                    <li><a href="login.php"><i class="fas fa-chevron-right"></i> Đăng nhập</a></li>
                    <li><a href="register.php"><i class="fas fa-chevron-right"></i> Đăng ký</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Thông Tin Bổ Sung</h3>
                <ul>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Chính sách bảo mật</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Điều khoản sử dụng</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Câu hỏi thường gặp</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Hướng dẫn sử dụng</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Tài liệu kỹ thuật</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>© 2024 Hệ Thống Mô Phỏng Giao Thông. Bản quyền thuộc về nhóm phát triển.</p>
            <p>Phiên bản 2.0 - Hỗ trợ mô phỏng giao thông đa làn đường</p>
        </div>
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
        
        // Xử lý form liên hệ
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            const submitBtn = document.getElementById('submitBtn');
            
            let hasError = false;
            
            // Xóa class error trước đó
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => input.classList.remove('error'));
            
            if (!name) {
                document.getElementById('name').classList.add('error');
                hasError = true;
            }
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('email').classList.add('error');
                hasError = true;
            }
            
            if (!subject) {
                document.getElementById('subject').classList.add('error');
                hasError = true;
            }
            
            if (!message) {
                document.getElementById('message').classList.add('error');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin bắt buộc (*)');
                return false;
            }
            
            // Thêm hiệu ứng loading
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG GỬI...';
            submitBtn.disabled = true;
        });
        
        // Xử lý FAQ
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                const item = this.parentElement;
                const isActive = item.classList.contains('active');
                
                // Đóng tất cả các item khác
                document.querySelectorAll('.faq-item').forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle trạng thái hiện tại
                if (!isActive) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });
        
        // Hiệu ứng focus cho input
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>