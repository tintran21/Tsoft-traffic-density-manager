<?php
session_start();
include '../database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Lấy thống kê
$stats = [];

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    $stats['total_users'] = 0;
}

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin'");
    $stats['total_admins'] = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    $stats['total_admins'] = 0;
}

// Thống kê cho hệ thống giao thông
$stats['total_simulations'] = 0;
$stats['active_simulations'] = 0;
$stats['traffic_incidents'] = 0;
$stats['system_uptime'] = "99.9%";

// Lấy thống kê từ bảng simulation nếu có
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM simulation_logs");
    if ($result) {
        $stats['total_simulations'] = $result->fetch_assoc()['total'];
    }
} catch (Exception $e) {
    $stats['total_simulations'] = 0;
}

// Lấy số lượng users thông thường
$stats['regular_users'] = $stats['total_users'] - $stats['total_admins'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Quản lý hệ thống</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            background: #34495e;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .menu-section {
            margin: 15px 0;
        }

        .menu-section-title {
            padding: 10px 20px;
            color: #95a5a6;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-menu li {
            border-bottom: 1px solid #34495e;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .menu-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            width: calc(100% - 280px);
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .header-left small {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Nút về trang Web */
        .web-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .web-link:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #34495e;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Colors for different stats */
        .stat-card.users { border-left: 4px solid #e74c3c; }
        .stat-card.admins { border-left: 4px solid #3498db; }
        .stat-card.simulations { border-left: 4px solid #9b59b6; }
        .stat-card.active-sim { border-left: 4px solid #27ae60; }
        .stat-card.incidents { border-left: 4px solid #f39c12; }
        .stat-card.uptime { border-left: 4px solid #1abc9c; }
        .stat-card.performance { border-left: 4px solid #e67e22; }
        .stat-card.analytics { border-left: 4px solid #8e44ad; }

        .welcome-message {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .setup-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .quick-action {
            display: block;
            padding: 20px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-left: 4px solid #3498db;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #3498db;
        }

        /* System Status */
        .system-status {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-online {
            background: #27ae60;
        }

        .status-offline {
            background: #e74c3c;
        }

        .status-warning {
            background: #f39c12;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .traffic-controls-grid {
                grid-template-columns: 1fr;
            }
            
            .form-buttons, .simulation-control-buttons {
                flex-direction: column;
            }
            
            .mode-selection {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .auto-config-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-traffic-light"></i> TRAFFIC CONTROL</h2>
            <small>Hệ thống Mô phỏng Giao thông</small>
        </div>
        
        <ul class="sidebar-menu">
            <!-- Phần Tổng quan -->
            <div class="menu-section">
                <div class="menu-section-title">TỔNG QUAN</div>
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            </div>

            <!-- Phần Quản lý Giao thông -->
            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ GIAO THÔNG</div>
                <li><a href="simulation.php"><i class="fas fa-play-circle"></i> Điều khiển Mô phỏng</a></li>
                <li><a href="traffic-lights.php"><i class="fas fa-traffic-light"></i> Đèn giao thông</a></li>
                <li><a href="nutgiaothong.php"><i class="fas fa-crosshairs"></i> Nút giao thông</a></li>
                <li><a href="baocaoluuluong.php"><i class="fas fa-chart-bar"></i> Báo cáo lưu lượng</a></li>
            </div>

            <!-- Phần Quản lý Hệ thống -->
            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ HỆ THỐNG</div>
                <li><a href="users.php"><i class="fas fa-user-cog"></i> Quản lý Users <span class="menu-badge"><?php echo $stats['total_users']; ?></span></a></li>
                <li><a href="logs.php"><i class="fas fa-clipboard-list"></i> Nhật ký hệ thống</a></li>
                <li><a href="backup.php"><i class="fas fa-database"></i> Sao lưu dữ liệu</a></li>
            </div>

            <!-- Phần Cài đặt -->
            <div class="menu-section">
                <div class="menu-section-title">CÀI ĐẶT</div>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Cài đặt hệ thống</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
            </div>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Quản Trị</h1>
                <small>Quản lý hệ thống Mô phỏng Giao thông</small>
            </div>
            <div class="header-right">
                <!-- NÚT VỀ TRANG WEB -->
                <a href="../index.php" class="web-link">
                    <i class="fas fa-globe"></i> Về Trang Web
                </a>
                <div class="user-info">
                    <span>Xin chào, <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo $_SESSION['role']; ?>)</span>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thông báo setup -->
        <div class="setup-notice">
            <i class="fas fa-info-circle"></i> 
            <strong>Hệ thống đang trong giai đoạn thiết lập.</strong> 
            Bạn có thể bắt đầu bằng cách điều khiển mô phỏng giao thông và quản lý users.
        </div>

        <!-- Welcome Message -->
        <div class="welcome-message">
            <h3><i class="fas fa-rocket"></i> Chào mừng đến trang quản trị!</h3>
            <p>Đây là trang dashboard quản trị hệ thống Mô phỏng Giao thông. Bạn có thể quản lý toàn bộ hệ thống từ menu bên trái.</p>
            
            <div class="quick-actions">
                <a href="users.php" class="quick-action">
                    <i class="fas fa-users"></i>
                    <div>Quản lý Users</div>
                    <small><?php echo $stats['total_users']; ?> users</small>
                </a>
                <a href="simulation.php" class="quick-action">
                    <i class="fas fa-play-circle"></i>
                    <div>Điều khiển Mô phỏng</div>
                    <small>Khởi động hệ thống</small>
                </a>
                <a href="traffic-lights.php" class="quick-action">
                    <i class="fas fa-traffic-light"></i>
                    <div>Đèn giao thông</div>
                    <small>Cấu hình đèn</small>
                </a>
                <a href="reports.php" class="quick-action">
                    <i class="fas fa-chart-bar"></i>
                    <div>Báo cáo</div>
                    <small>Phân tích lưu lượng</small>
                </a>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card users">
                <i class="fas fa-users"></i>
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Tổng số Users</div>
            </div>
            <div class="stat-card admins">
                <i class="fas fa-user-shield"></i>
                <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Quản trị viên</div>
            </div>
            <div class="stat-card simulations">
                <i class="fas fa-play-circle"></i>
                <div class="stat-number"><?php echo $stats['total_simulations']; ?></div>
                <div class="stat-label">Lần mô phỏng</div>
            </div>
            <div class="stat-card active-sim">
                <i class="fas fa-running"></i>
                <div class="stat-number"><?php echo $stats['active_simulations']; ?></div>
                <div class="stat-label">Mô phỏng đang chạy</div>
            </div>
            <div class="stat-card incidents">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="stat-number"><?php echo $stats['traffic_incidents']; ?></div>
                <div class="stat-label">Sự cố giao thông</div>
            </div>
            <div class="stat-card uptime">
                <i class="fas fa-server"></i>
                <div class="stat-number"><?php echo $stats['system_uptime']; ?></div>
                <div class="stat-label">Thời gian hoạt động</div>
            </div>
        </div>

        <!-- System Status -->
        <div class="system-status">
            <h3><i class="fas fa-heartbeat"></i> Trạng thái hệ thống</h3>
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-indicator status-online"></div>
                    <div>
                        <strong>Mô phỏng chính</strong>
                        <div>Đang hoạt động</div>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-indicator status-online"></div>
                    <div>
                        <strong>Cơ sở dữ liệu</strong>
                        <div>Kết nối ổn định</div>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-indicator status-warning"></div>
                    <div>
                        <strong>API Giao thông</strong>
                        <div>Đang bảo trì</div>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-indicator status-online"></div>
                    <div>
                        <strong>Web Server</strong>
                        <div>Hoạt động tốt</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="welcome-message">
            <h3><i class="fas fa-history"></i> Hoạt động gần đây</h3>
            <p>Hệ thống đang được thiết lập. Các hoạt động sẽ được hiển thị tại đây.</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-top: 15px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <i class="fas fa-info-circle" style="color: #3498db;"></i>
                    <span>Chưa có hoạt động nào gần đây</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-lightbulb" style="color: #f39c12;"></i>
                    <span>Bắt đầu bằng cách điều chỉnh cấu hình mô phỏng giao thông</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('Dashboard loaded successfully');
        
        // Thêm hiệu ứng cho các stat card
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        });

        // Xử lý responsive sidebar trên mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>