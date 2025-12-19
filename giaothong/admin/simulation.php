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

// Xử lý lưu cấu hình simulation
$save_success = false;
$simulation_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_simulation_config'])) {
    $directions = ['north', 'south', 'east', 'west'];
    $all_success = true;
    
    foreach ($directions as $dir) {
        if (isset($_POST[$dir . '_green'])) {
            $success = saveSimulationConfig(
                $conn, 
                $dir, 
                intval($_POST[$dir . '_green']),
                intval($_POST[$dir . '_yellow']), 
                intval($_POST[$dir . '_red']),
                intval($_POST[$dir . '_flow'])
            );
            if (!$success) {
                $all_success = false;
            }
        }
    }
    
    if ($all_success) {
        $save_success = true;
        $simulation_message = "Đã lưu cấu hình thành công!";
    } else {
        $simulation_message = "Có lỗi xảy ra khi lưu cấu hình!";
    }
    
    // Reload config sau khi lưu
    $config = getSimulationConfig($conn);
}

// Xử lý thay đổi chế độ điều khiển
$control_success = false;
$control_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_control_mode'])) {
    $mode = $_POST['control_mode'];
    
    // Lấy auto config từ form nếu có
    $auto_config = null;
    if ($mode === 'auto' && isset($_POST['min_green_time'])) {
        $auto_config = [
            'min_green_time' => intval($_POST['min_green_time']),
            'max_green_time' => intval($_POST['max_green_time']),
            'yellow_time' => intval($_POST['yellow_time']),
            'base_green_time' => intval($_POST['base_green_time']),
            'traffic_ratio_threshold' => floatval($_POST['traffic_ratio_threshold']),
            'extra_green_time' => intval($_POST['extra_green_time'])
        ];
    }
    
    if (updateControlMode($conn, $mode, $auto_config)) {
        $control_success = true;
        $control_message = "Đã chuyển sang chế độ " . ($mode === 'manual' ? 'điều khiển tay' : 'tự động');
    } else {
        $control_message = "Có lỗi khi chuyển chế độ";
    }
}

// Lấy chế độ hiện tại
$control_data = getControlMode($conn);
$current_mode = $control_data['mode'];

// Xử lý auto_config - FIXED LỖI JSON_DECODE
$current_auto_config = [];
if (isset($control_data['auto_config'])) {
    // Kiểm tra nếu auto_config là chuỗi JSON thì decode
    if (is_string($control_data['auto_config']) && !empty($control_data['auto_config'])) {
        $decoded = json_decode($control_data['auto_config'], true);
        if ($decoded !== null) {
            $current_auto_config = $decoded;
        }
    } 
    // Nếu auto_config đã là mảng thì sử dụng trực tiếp
    elseif (is_array($control_data['auto_config'])) {
        $current_auto_config = $control_data['auto_config'];
    }
}

// Thiết lập giá trị mặc định nếu không có
if (empty($current_auto_config)) {
    $current_auto_config = [
        'min_green_time' => 10,
        'max_green_time' => 60,
        'yellow_time' => 5,
        'base_green_time' => 30,
        'traffic_ratio_threshold' => 1.5,
        'extra_green_time' => 10
    ];
}

// Xử lý khởi động/dừng simulation
$simulation_status = 'stopped'; // Mặc định là dừng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulation_action'])) {
    if ($_POST['simulation_action'] === 'start') {
        $simulation_status = 'running';
        $simulation_message = "Đã khởi động mô phỏng thành công!";
    } elseif ($_POST['simulation_action'] === 'stop') {
        $simulation_status = 'stopped';
        $simulation_message = "Đã dừng mô phỏng thành công!";
    }
}

// Lấy cấu hình hiện tại
$config = getSimulationConfig($conn);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điều khiển Mô phỏng - Quản lý hệ thống</title>
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

        /* Simulation Controls */
        .simulation-controls {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .control-section {
            margin-bottom: 2rem;
        }

        .control-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .traffic-controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .direction-control {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        .direction-control h4 {
            text-align: center;
            margin-bottom: 1rem;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .control-item {
            margin-bottom: 1rem;
        }

        .control-item label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }

        .control-item input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .light-preview {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 1rem;
        }

        .traffic-light {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #333;
        }

        .red { background: #ff4444; }
        .yellow { background: #ffbb33; }
        .green { background: #00C851; }
        .off { background: #666; }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #d35400;
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .simulation-control-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }

        /* Control Mode Styles */
        .control-mode-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
        }

        .mode-selection {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .mode-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mode-option input[type="radio"] {
            margin: 0;
        }

        .mode-option label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }

        .auto-config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        /* Status Indicator */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .status-running {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-stopped {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <div class="menu-section">
                <div class="menu-section-title">TỔNG QUAN</div>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ GIAO THÔNG</div>
                <li><a href="simulation.php" class="active"><i class="fas fa-play-circle"></i> Điều khiển Mô phỏng</a></li>
                <li><a href="traffic-lights.php"><i class="fas fa-traffic-light"></i> Đèn giao thông</a></li>
                <li><a href="nutgiaothong.php"><i class="fas fa-crosshairs"></i> Nút giao thông</a></li>
                <li><a href="baocaoluuluong.php"><i class="fas fa-chart-bar"></i> Báo cáo lưu lượng</a></li>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ HỆ THỐNG</div>
                <li><a href="users.php"><i class="fas fa-user-cog"></i> Quản lý Users</a></li>
                <li><a href="logs.php"><i class="fas fa-clipboard-list"></i> Nhật ký hệ thống</a></li>
                <li><a href="backup.php"><i class="fas fa-database"></i> Sao lưu dữ liệu</a></li>
            </div>

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
                <h1><i class="fas fa-play-circle"></i> Điều khiển Mô phỏng Giao thông</h1>
                <small>Thiết lập và điều khiển hệ thống mô phỏng</small>
            </div>
            <div class="header-right">
                <a href="../index.php" class="web-link">
                    <i class="fas fa-globe"></i> Xem Mô phỏng
                </a>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo $_SESSION['role']; ?>)</span>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Simulation Controls -->
        <div class="simulation-controls">
            <h2><i class="fas fa-traffic-light"></i> Điều khiển Mô phỏng</h2>
            
            <!-- Status Indicator -->
            <div class="status-indicator <?php echo $simulation_status === 'running' ? 'status-running' : 'status-stopped'; ?>">
                <i class="fas <?php echo $simulation_status === 'running' ? 'fa-play-circle' : 'fa-stop-circle'; ?>"></i>
                Trạng thái mô phỏng: <?php echo $simulation_status === 'running' ? 'ĐANG CHẠY' : 'ĐÃ DỪNG'; ?>
            </div>
            
            <?php if ($save_success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $simulation_message; ?>
                </div>
            <?php elseif (!empty($simulation_message) && !$save_success): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $simulation_message; ?>
                </div>
            <?php endif; ?>

            <!-- Control Mode Selection -->
            <div class="control-section">
                <h3><i class="fas fa-cog"></i> Chế độ điều khiển</h3>
                
                <?php if (isset($control_message)): ?>
                    <div class="<?php echo $control_success ? 'success-message' : 'error-message'; ?>">
                        <i class="fas <?php echo $control_success ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> 
                        <?php echo $control_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="control-mode-form">
                    <input type="hidden" name="change_control_mode" value="1">
                    
                    <div class="mode-selection">
                        <div class="mode-option">
                            <input type="radio" id="manual_mode" name="control_mode" value="manual" 
                                <?php echo $current_mode === 'manual' ? 'checked' : ''; ?>>
                            <label for="manual_mode">
                                <i class="fas fa-hand-paper"></i> Điều khiển tay
                            </label>
                        </div>
                        <div class="mode-option">
                            <input type="radio" id="auto_mode" name="control_mode" value="auto"
                                <?php echo $current_mode === 'auto' ? 'checked' : ''; ?>>
                            <label for="auto_mode">
                                <i class="fas fa-robot"></i> Tự động
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Áp dụng
                        </button>
                    </div>

                    <!-- Auto Mode Configuration -->
                    <div id="auto_config_section" style="<?php echo $current_mode === 'auto' ? '' : 'display: none;'; ?>">
                        <h4><i class="fas fa-sliders-h"></i> Cấu hình chế độ tự động</h4>
                        <div class="auto-config-grid">
                            <div class="control-item">
                                <label>Thời gian xanh tối thiểu (giây):</label>
                                <input type="number" name="min_green_time" value="<?php echo $current_auto_config['min_green_time']; ?>" min="5" max="30" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian xanh tối đa (giây):</label>
                                <input type="number" name="max_green_time" value="<?php echo $current_auto_config['max_green_time']; ?>" min="30" max="120" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian vàng (giây):</label>
                                <input type="number" name="yellow_time" value="<?php echo $current_auto_config['yellow_time']; ?>" min="3" max="10" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian xanh cơ bản (giây):</label>
                                <input type="number" name="base_green_time" value="<?php echo $current_auto_config['base_green_time']; ?>" min="10" max="60" required>
                            </div>
                            <div class="control-item">
                                <label>Ngưỡng tỷ lệ giao thông:</label>
                                <input type="number" step="0.1" name="traffic_ratio_threshold" value="<?php echo $current_auto_config['traffic_ratio_threshold']; ?>" min="1.0" max="3.0" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian xanh thêm (giây):</label>
                                <input type="number" name="extra_green_time" value="<?php echo $current_auto_config['extra_green_time']; ?>" min="5" max="30" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Simulation Control Buttons -->
            <div class="simulation-control-buttons">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="simulation_action" value="start">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-play"></i> Khởi động Mô phỏng
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="simulation_action" value="stop">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-stop"></i> Dừng Mô phỏng
                    </button>
                </form>
                <a href="../index.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-eye"></i> Xem Mô phỏng
                </a>
                <a href="javascript:location.reload()" class="btn btn-warning">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
            </div>

            <!-- Traffic Light Configuration -->
            <form method="POST">
                <input type="hidden" name="save_simulation_config" value="1">
                
                <div class="control-section">
                    <h3><i class="fas fa-sliders-h"></i> Cài đặt Đèn giao thông</h3>
                    <p style="color: #666; margin-bottom: 1rem;">
                        <i class="fas fa-info-circle"></i> Thay đổi cấu hình sẽ được lưu vào database và áp dụng ngay trên trang chủ.
                    </p>
                    <div class="traffic-controls-grid">
                        <!-- North Direction -->
                        <div class="direction-control">
                            <h4><i class="fas fa-arrow-up"></i> Hướng BẮC</h4>
                            <div class="control-item">
                                <label>Thời gian đèn xanh (giây):</label>
                                <input type="number" name="north_green" min="5" max="60" 
                                       value="<?php echo isset($config['north']['green_time']) ? $config['north']['green_time'] : 30; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn vàng (giây):</label>
                                <input type="number" name="north_yellow" min="2" max="10" 
                                       value="<?php echo isset($config['north']['yellow_time']) ? $config['north']['yellow_time'] : 3; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn đỏ (giây):</label>
                                <input type="number" name="north_red" min="5" max="60" 
                                       value="<?php echo isset($config['north']['red_time']) ? $config['north']['red_time'] : 40; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Lưu lượng xe (xe/phút):</label>
                                <input type="number" name="north_flow" min="1" max="30" 
                                       value="<?php echo isset($config['north']['traffic_flow']) ? $config['north']['traffic_flow'] : 10; ?>" required>
                            </div>
                            <div class="light-preview">
                                <div class="traffic-light red"></div>
                                <div class="traffic-light off"></div>
                                <div class="traffic-light off"></div>
                            </div>
                        </div>

                        <!-- South Direction -->
                        <div class="direction-control">
                            <h4><i class="fas fa-arrow-down"></i> Hướng NAM</h4>
                            <div class="control-item">
                                <label>Thời gian đèn xanh (giây):</label>
                                <input type="number" name="south_green" min="5" max="60" 
                                       value="<?php echo isset($config['south']['green_time']) ? $config['south']['green_time'] : 30; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn vàng (giây):</label>
                                <input type="number" name="south_yellow" min="2" max="10" 
                                       value="<?php echo isset($config['south']['yellow_time']) ? $config['south']['yellow_time'] : 3; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn đỏ (giây):</label>
                                <input type="number" name="south_red" min="5" max="60" 
                                       value="<?php echo isset($config['south']['red_time']) ? $config['south']['red_time'] : 40; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Lưu lượng xe (xe/phút):</label>
                                <input type="number" name="south_flow" min="1" max="30" 
                                       value="<?php echo isset($config['south']['traffic_flow']) ? $config['south']['traffic_flow'] : 10; ?>" required>
                            </div>
                            <div class="light-preview">
                                <div class="traffic-light red"></div>
                                <div class="traffic-light off"></div>
                                <div class="traffic-light off"></div>
                            </div>
                        </div>

                        <!-- East Direction -->
                        <div class="direction-control">
                            <h4><i class="fas fa-arrow-right"></i> Hướng ĐÔNG</h4>
                            <div class="control-item">
                                <label>Thời gian đèn xanh (giây):</label>
                                <input type="number" name="east_green" min="5" max="60" 
                                       value="<?php echo isset($config['east']['green_time']) ? $config['east']['green_time'] : 25; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn vàng (giây):</label>
                                <input type="number" name="east_yellow" min="2" max="10" 
                                       value="<?php echo isset($config['east']['yellow_time']) ? $config['east']['yellow_time'] : 3; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn đỏ (giây):</label>
                                <input type="number" name="east_red" min="5" max="60" 
                                       value="<?php echo isset($config['east']['red_time']) ? $config['east']['red_time'] : 45; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Lưu lượng xe (xe/phút):</label>
                                <input type="number" name="east_flow" min="1" max="30" 
                                       value="<?php echo isset($config['east']['traffic_flow']) ? $config['east']['traffic_flow'] : 15; ?>" required>
                            </div>
                            <div class="light-preview">
                                <div class="traffic-light green"></div>
                                <div class="traffic-light off"></div>
                                <div class="traffic-light off"></div>
                            </div>
                        </div>

                        <!-- West Direction -->
                        <div class="direction-control">
                            <h4><i class="fas fa-arrow-left"></i> Hướng TÂY</h4>
                            <div class="control-item">
                                <label>Thời gian đèn xanh (giây):</label>
                                <input type="number" name="west_green" min="5" max="60" 
                                       value="<?php echo isset($config['west']['green_time']) ? $config['west']['green_time'] : 25; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn vàng (giây):</label>
                                <input type="number" name="west_yellow" min="2" max="10" 
                                       value="<?php echo isset($config['west']['yellow_time']) ? $config['west']['yellow_time'] : 3; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Thời gian đèn đỏ (giây):</label>
                                <input type="number" name="west_red" min="5" max="60" 
                                       value="<?php echo isset($config['west']['red_time']) ? $config['west']['red_time'] : 45; ?>" required>
                            </div>
                            <div class="control-item">
                                <label>Lưu lượng xe (xe/phút):</label>
                                <input type="number" name="west_flow" min="1" max="30" 
                                       value="<?php echo isset($config['west']['traffic_flow']) ? $config['west']['traffic_flow'] : 15; ?>" required>
                            </div>
                            <div class="light-preview">
                                <div class="traffic-light green"></div>
                                <div class="traffic-light off"></div>
                                <div class="traffic-light off"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Lưu Cấu hình
                    </button>
                    <button type="reset" class="btn btn-warning">
                        <i class="fas fa-undo"></i> Đặt lại
                    </button>
                    <a href="../index.php" class="btn btn-primary" target="_blank">
                        <i class="fas fa-eye"></i> Xem thay đổi
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle auto config section
        const manualRadio = document.getElementById('manual_mode');
        const autoRadio = document.getElementById('auto_mode');
        const autoConfigSection = document.getElementById('auto_config_section');
        
        function toggleAutoConfig() {
            if (autoRadio.checked) {
                autoConfigSection.style.display = 'block';
            } else {
                autoConfigSection.style.display = 'none';
            }
        }
        
        manualRadio.addEventListener('change', toggleAutoConfig);
        autoRadio.addEventListener('change', toggleAutoConfig);
        
        // Initialize
        toggleAutoConfig();

        // Auto-hide success message after 5 seconds
        <?php if ($save_success): ?>
        setTimeout(function() {
            const successMsg = document.querySelector('.success-message');
            if (successMsg) {
                successMsg.style.display = 'none';
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>