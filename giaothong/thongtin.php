<?php
session_start();
include 'database.php';

// Lấy thời gian hiện tại
$current_time = date('H:i:s');
$current_date = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin - Hệ Thống Mô Phỏng Giao Thông</title>
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
            background: linear-gradient(135deg,#3498db 0%, #2c3e50 100%);
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

        /* Info Sections */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .info-card i {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 20px;
            display: block;
        }

        .info-card h3 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .info-card p {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .info-card ul {
            list-style-type: none;
            padding-left: 0;
        }

        .info-card li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            color: #5d6d7e;
        }

        .info-card li:last-child {
            border-bottom: none;
        }

        .info-card li i {
            color: #2ecc71;
            margin-right: 10px;
            font-size: 1rem;
        }

        /* Feature List */
        .feature-list {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 50px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .feature-list h2 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: background 0.3s;
        }

        .feature-item:hover {
            background: #e3f2fd;
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: #3498db;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .feature-content h4 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .feature-content p {
            color: #7f8c8d;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Technology Stack */
        .tech-stack {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 50px;
            color: white;
        }

        .tech-stack h2 {
            font-size: 2rem;
            margin-bottom: 30px;
            text-align: center;
        }

        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .tech-item {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            transition: transform 0.3s;
        }

        .tech-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .tech-item i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
        }

        .tech-item h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .tech-item p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Team Section */
        .team-section {
            text-align: center;
            margin-bottom: 50px;
        }

        .team-section h2 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 40px;
            font-weight: 700;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .team-member {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .team-member:hover {
            transform: translateY(-10px);
        }

        .member-avatar {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .team-member h4 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .team-member p {
            color: #3498db;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .team-member .bio {
            color: #7f8c8d;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 20px;
            text-align: center;
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
            
            .info-grid {
                grid-template-columns: 1fr;
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
                        <li><a href="thongtin.php" class="active"><i class="fas fa-info-circle"></i> Thông tin</a></li>
                        <li><a href="lienhe.php"><i class="fas fa-envelope"></i> Liên hệ</a></li>
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
            <i class="fas fa-info-circle"></i>
            <h1>Thông Tin Hệ Thống</h1>
            <p>Khám phá các tính năng và công nghệ đằng sau hệ thống mô phỏng giao thông thông minh của chúng tôi</p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <i class="fas fa-bullseye"></i>
                <h3>Mục Tiêu</h3>
                <p>Hệ thống được phát triển với mục tiêu tạo ra môi trường mô phỏng giao thông chân thực, giúp nghiên cứu và tối ưu hóa luồng giao thông đô thị.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Mô phỏng giao thông đa chiều</li>
                    <li><i class="fas fa-check"></i> Điều khiển đèn giao thông thông minh</li>
                    <li><i class="fas fa-check"></i> Phân tích lưu lượng xe</li>
                    <li><i class="fas fa-check"></i> Tối ưu thời gian chờ đợi</li>
                </ul>
            </div>

            <div class="info-card">
                <i class="fas fa-cogs"></i>
                <h3>Tính Năng Nổi Bật</h3>
                <p>Hệ thống tích hợp nhiều tính năng hiện đại để đảm bảo mô phỏng chính xác và hiệu quả.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Điều khiển thủ công & tự động</li>
                    <li><i class="fas fa-check"></i> Thời gian thực tế 24/7</li>
                    <li><i class="fas fa-check"></i> Giao diện trực quan 3D</li>
                    <li><i class="fas fa-check"></i> Báo cáo thống kê chi tiết</li>
                    <li><i class="fas fa-check"></i> Quản lý người dùng đa cấp</li>
                </ul>
            </div>

            <div class="info-card">
                <i class="fas fa-chart-line"></i>
                <h3>Lợi Ích</h3>
                <p>Hệ thống mang lại nhiều lợi ích thiết thực cho công tác quản lý và nghiên cứu giao thông.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Giảm ùn tắc giao thông</li>
                    <li><i class="fas fa-check"></i> Tối ưu hóa thời gian đèn</li>
                    <li><i class="fas fa-check"></i> Tiết kiệm nhiên liệu</li>
                    <li><i class="fas fa-check"></i> Giảm ô nhiễm môi trường</li>
                    <li><i class="fas fa-check"></i> Nâng cao an toàn giao thông</li>
                </ul>
            </div>
        </div>

        <div class="feature-list">
            <h2>Tính Năng Chi Tiết</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Điều Khiển Tự Động</h4>
                        <p>Hệ thống tự động điều chỉnh thời gian đèn dựa trên lưu lượng xe thực tế tại ngã tư.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-hand-paper"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Điều Khiển Thủ Công</h4>
                        <p>Quản trị viên có thể can thiệp thủ công để điều chỉnh hệ thống theo nhu cầu đặc biệt.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Mô Phỏng Xe</h4>
                        <p>Tạo và quản lý luồng xe với các thuộc tính: tốc độ, hướng di chuyển, mật độ.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Phân Tích Dữ Liệu</h4>
                        <p>Thu thập và phân tích dữ liệu giao thông để đưa ra quyết định tối ưu hóa.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Bảo Mật Đa Cấp</h4>
                        <p>Hệ thống phân quyền người dùng với 2 cấp: Admin và User thường.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Lưu Trữ Dữ Liệu</h4>
                        <p>Lưu trữ lịch sử giao thông và cấu hình hệ thống để phân tích và báo cáo.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="tech-stack">
            <h2>Công Nghệ Sử Dụng</h2>
            <div class="tech-grid">
                <div class="tech-item">
                    <i class="fab fa-php"></i>
                    <h4>PHP 7.4+</h4>
                    <p>Backend xử lý logic và kết nối database</p>
                </div>
                <div class="tech-item">
                    <i class="fas fa-database"></i>
                    <h4>MySQL</h4>
                    <p>Hệ quản trị cơ sở dữ liệu quan hệ</p>
                </div>
                <div class="tech-item">
                    <i class="fab fa-js"></i>
                    <h4>JavaScript</h4>
                    <p>Xử lý frontend và mô phỏng thời gian thực</p>
                </div>
                <div class="tech-item">
                    <i class="fab fa-html5"></i>
                    <h4>HTML5</h4>
                    <p>Cấu trúc và semantic markup</p>
                </div>
                <div class="tech-item">
                    <i class="fab fa-css3-alt"></i>
                    <h4>CSS3</h4>
                    <p>Styling và animations nâng cao</p>
                </div>
                <div class="tech-item">
                    <i class="fas fa-server"></i>
                    <h4>XAMPP</h4>
                    <p>Môi trường phát triển web server</p>
                </div>
            </div>
        </div>

        <div class="team-section">
            <h2>Đội Ngũ Phát Triển</h2>
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h4>Đinh Hoàng Thuận</h4>
                    <p>Member 1</p>
                    <p class="bio">Chịu trách nhiệm quản lý dự án và phát triển chiến lược tổng thể.</p>
                </div>
                <div class="team-member">
                    <div class="member-avatar">
                        <i class="fas fa-code"></i>
                    </div>
                    <h4>Trần Đại Tín</h4>
                    <p>Member 2</p>
                    <p class="bio">Phát triển hệ thống backend, xử lý logic và kết nối database.</p>
                </div>
                <div class="team-member">
                    <div class="member-avatar">
                        <i class="fas fa-paint-brush"></i>
                    </div>
                    <h4>Hà Tiến Đạt</h4>
                    <p>Member 3</p>
                    <p class="bio">Thiết kế giao diện người dùng và xử lý mô phỏng thời gian thực.</p>
                </div>
                <div class="team-member">
                    <div class="member-avatar">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Nguyễn Xuân Trường</h4>
                    <p>Member 4</p>
                    <p class="bio">Phân tích dữ liệu giao thông và tối ưu hóa thuật toán.</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Hệ Thống Mô Phỏng Giao Thông</h3>
                <p>Ứng dụng công nghệ hiện đại để mô phỏng và tối ưu hóa hệ thống giao thông đô thị.</p>
                <p><i class="fas fa-map-marker-alt"></i> Địa chỉ: 54 Nguyễn Lương Bằng, phường Hòa Khánh, quận Liên Chiểu, thành phố Đà Nẵng</p>
                <p><i class="fas fa-phone"></i> Điện thoại: 0855894446 (Thuận)</p>
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
        
        // Hiệu ứng scroll mượt cho các liên kết trong trang
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId !== '#') {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>