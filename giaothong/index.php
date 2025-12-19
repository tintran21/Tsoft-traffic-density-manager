<?php
// Bật hiển thị lỗi để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Khởi tạo các biến với giá trị mặc định để tránh lỗi
$config = [
    'north' => ['green_time' => 30, 'yellow_time' => 5, 'traffic_flow' => 10],
    'south' => ['green_time' => 30, 'yellow_time' => 5, 'traffic_flow' => 10],
    'east' => ['green_time' => 20, 'yellow_time' => 5, 'traffic_flow' => 10],
    'west' => ['green_time' => 20, 'yellow_time' => 5, 'traffic_flow' => 10]
];

$control_mode = 'manual';
$auto_config = [
    'base_green_time' => 30,
    'yellow_time' => 5,
    'min_green_time' => 10,
    'max_green_time' => 60,
    'traffic_ratio_threshold' => 1.5,
    'extra_green_time' => 10
];

// KẾT NỐI DATABASE
try {
    // Kiểm tra file database.php có tồn tại không
    if (file_exists('database.php')) {
        include 'database.php';
        
        // Kiểm tra xem kết nối database có thành công không
        if (isset($conn) && $conn) {
            // Lấy cấu hình simulation từ database
            if (function_exists('getSimulationConfig')) {
                $db_config = getSimulationConfig($conn);
                if ($db_config && is_array($db_config)) {
                    $config = $db_config;
                }
            }
            
            // Lấy chế độ điều khiển - SỬA LỖI Ở ĐÂY
            if (function_exists('getControlMode')) {
                $control_mode_data = getControlMode($conn);
                if ($control_mode_data && is_array($control_mode_data)) {
                    $control_mode = $control_mode_data['mode'] ?? 'manual';
                    
                    // XỬ LÝ auto_config AN TOÀN
                    if (isset($control_mode_data['auto_config'])) {
                        $auto_config_value = $control_mode_data['auto_config'];
                        
                        // Nếu auto_config đã là mảng, sử dụng trực tiếp
                        if (is_array($auto_config_value)) {
                            $auto_config = array_merge($auto_config, $auto_config_value);
                        } 
                        // Nếu là chuỗi JSON, decode nó
                        elseif (is_string($auto_config_value) && !empty($auto_config_value)) {
                            $decoded_config = json_decode($auto_config_value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_config)) {
                                $auto_config = array_merge($auto_config, $decoded_config);
                            }
                        }
                    }
                }
            }
            
            // Đóng kết nối DB
            $conn->close();
        } else {
            error_log("Không thể kết nối database");
        }
    } else {
        error_log("File database.php không tồn tại");
    }
} catch (Exception $e) {
    error_log("Lỗi khi xử lý database: " . $e->getMessage());
}

// Lấy thời gian hiện tại từ server
$current_time = date('H:i:s');
$current_date = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mô phỏng Giao thông</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a2e;
            min-height: 100vh;
            overflow: hidden;
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

        .simulation-container {
            position: relative;
            width: 100vw;
            height: calc(100vh - 160px);
            background: #2c3e50;
            overflow: hidden;
        }

        .road {
            position: absolute;
            background: #555;
        }

        .road-horizontal {
            width: 100vw;
            height: 80px;
            top: 50%;
            transform: translateY(-50%);
            background: #666;
        }

        .road-vertical {
            width: 80px;
            height: 100vh;
            left: 50%;
            transform: translateX(-50%);
            background: #666;
        }

        .lane-marking {
            position: absolute;
        }

        .horizontal-center {
            width: 100vw;
            height: 4px;
            top: 50%;
            transform: translateY(-2px);
            background: repeating-linear-gradient(
                to right,
                white 0px,
                white 10px,
                transparent 10px,
                transparent 20px
            );
        }

        .vertical-center {
            width: 4px;
            height: 100vh;
            left: 50%;
            transform: translateX(-2px);
            background: repeating-linear-gradient(
                to bottom, 
                white 0px,
                white 10px,
                transparent 10px,
                transparent 20px
            );
        }

        .stop-line {
            position: absolute;
            background: white;
            z-index: 100;
        }

        .stop-line-north { 
            top: calc(50% - 48px);
            left: calc(50% - 40px);
            width: 40px; 
            height: 8px; 
        }

        .stop-line-south { 
            top: calc(50% + 40px);
            right: calc(50% - 40px);
            width: 40px;
            height: 8px;
        }

        .stop-line-west {
            left: calc(50% + 40px);
            top: calc(50% - 40px);
            width: 8px;
            height: 40px; 
        }

        .stop-line-east { 
            left: calc(50% - 48px);
            bottom: calc(50% - 40px);
            width: 8px;
            height: 40px;
        }

        .traffic-light {
            position: absolute;
            width: 60px;
            height: 160px;
            background: #2c3e50;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 200;
            border: 3px solid #1a252f;
        }

        .traffic-light::before {
            content: '';
            position: absolute;
            background: #34495e;
        }

        .light-north::before {
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 15px;
            height: 40px;
        }

        .light-south::before {
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 15px;
            height: 40px;
        }

        .light-east::before {
            top: 50%;
            right: -20px;
            transform: translateY(-50%);
            width: 40px;
            height: 15px;
        }

        .light-west::before {
            top: 50%;
            left: -20px; 
            transform: translateY(-50%);
            width: 40px;
            height: 15px;
        }

        .light {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 3px solid #333;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 5px rgba(0,0,0,0.5);
        }

        .light.active.red { background: #ff4444; box-shadow: 0 0 15px #ff4444; }
        .light.active.yellow { background: #ffbb33; box-shadow: 0 0 15px #ffbb33; }
        .light.active.green { background: #00C851; box-shadow: 0 0 15px #00C851; }
        .light:not(.active) { background: #444; }
        
        .light-north {
            top: calc(50% - 250px);
            left: calc(50% - 100px);
        }

        .light-south {
            bottom: calc(50% - 250px);
            right: calc(50% - 100px);
        }

        .light-east {
            top: calc(50% - 100px);
            right: calc(50% - 250px);
            flex-direction: row;
            width: 160px;
            height: 60px;
        }

        .light-west {
            top: calc(50% + 40px); 
            left: calc(50% - 250px); 
            flex-direction: row;
            width: 160px;
            height: 60px;
        }

        .direction-label {
            position: absolute;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            z-index: 50;
        }

        .north-label { top: 20%; left: 50%; transform: translateX(-50%); }
        .south-label { bottom: 20%; left: 50%; transform: translateX(-50%); }
        .east-label { top: 50%; right: 20%; transform: translateY(-50%); }
        .west-label { top: 50%; left: 20%; transform: translateY(-50%); }

        .timer-display {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 300;
            text-align: center;
            min-width: 180px;
        }

        .timer {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
        }

        .current-state {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .real-time {
            font-size: 12px;
            color: #3498db;
            margin-top: 8px;
            font-family: 'Courier New', monospace;
        }

        .control-mode-display {
            position: absolute;
            top: 20px;
            right: 220px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 300;
            text-align: center;
            min-width: 180px;
        }

        .control-mode {
            font-size: 16px;
            font-weight: bold;
            color: #3498db;
        }

        .timing-info {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .timing-info.hidden {
            display: none;
        }

        .simulation-info {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 300;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .vehicle {
            position: absolute;
            width: 30px;
            height: 15px;
            border-radius: 3px;
            z-index: 150;
            transition: all 0.1s linear; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            transform-origin: center center;
            outline: 1px solid rgba(0,0,0,0.2);
        }

        .vehicle::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 12px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 2px;
            top: 1.5px;
            left: 3px;
            z-index: 160;
        }

        .vehicle.north-bound { 
            transform: rotate(90deg); 
        }
        .vehicle.south-bound { 
            transform: rotate(-90deg); 
        }
        .vehicle.east-bound { 
            transform: rotate(180deg); 
        }
        .vehicle.west-bound { 
            transform: rotate(0deg); 
        }

        .vehicle.stopped {
            border: 2px solid #ff4444;
            animation: pulseStopped 1.5s infinite;
        }

        @keyframes pulseStopped {
            0%, 100% { border-color: #ff4444; }
            50% { border-color: rgba(255, 68, 68, 0.5); }
        }

        .traffic-flow-info {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 300;
            font-size: 12px;
        }

        .flow-item {
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .flow-item:last-child {
            margin-bottom: 0;
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
                    <div class="current-time" id="current-time"><?php echo htmlspecialchars($current_time); ?></div>
                    <div class="current-date" id="current-date"><?php echo htmlspecialchars($current_date); ?></div>
                </div>
            </div>
            
            <div class="header-main">
                <nav>
                    <ul class="nav-menu">
                        <li><a href="index.php" class="active"><i class="fas fa-home"></i> Trang chủ</a></li>
                        <li><a href="thongtin.php"><i class="fas fa-info-circle"></i> Thông tin</a></li>
                        <li><a href="lienhe.php"><i class="fas fa-envelope"></i> Liên hệ</a></li>
                    </ul>
                </nav>
                
                <div class="auth-section">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="admin-link">
                                <i class="fas fa-cog"></i> Quản trị
                            </a>
                        <?php endif; ?>
                        <div class="user-info">
                            <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Người dùng'); ?></span>
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

    <div class="simulation-container">
        <div class="road road-horizontal"></div>
        <div class="road road-vertical"></div>

        <div class="lane-marking horizontal-center"></div>
        <div class="lane-marking vertical-center"></div>

        <div class="stop-line stop-line-north"></div>
        <div class="stop-line stop-line-south"></div>
        <div class="stop-line stop-line-east"></div>
        <div class="stop-line stop-line-west"></div>

        <div class="traffic-light light-north">
            <div class="light red" id="north-red"></div>
            <div class="light yellow" id="north-yellow"></div>
            <div class="light green" id="north-green"></div>
        </div>
        <div class="traffic-light light-south">
            <div class="light red" id="south-red"></div>
            <div class="light yellow" id="south-yellow"></div>
            <div class="light green" id="south-green"></div>
        </div>
        <div class="traffic-light light-east">
            <div class="light red" id="east-red"></div>
            <div class="light yellow" id="east-yellow"></div>
            <div class="light green" id="east-green"></div>
        </div>
        <div class="traffic-light light-west">
            <div class="light red" id="west-red"></div>
            <div class="light yellow" id="west-yellow"></div>
            <div class="light green" id="west-green"></div>
        </div>

        <div id="vehicle-container"></div>

        <div class="direction-label north-label">BẮC</div>
        <div class="direction-label south-label">NAM</div>
        <div class="direction-label east-label">ĐÔNG</div>
        <div class="direction-label west-label">TÂY</div>

        <div class="timer-display">
            <div class="timer" id="timer">0</div>
            <div class="current-state" id="current-state">Đang khởi động...</div>
            <div class="real-time" id="real-time"><?php echo htmlspecialchars($current_time); ?></div>
        </div>

        <!-- Control Mode Display -->
        <div class="control-mode-display">
            <div class="control-mode" id="control-mode">Chế độ: ĐANG TẢI...</div>
            <div class="timing-info <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? '' : 'hidden'; ?>" id="timing-info"></div>
        </div>

        <!-- Traffic Flow Info Display -->
        <div class="traffic-flow-info">
            <div class="flow-item"><strong>Lưu lượng xe hiện tại:</strong></div>
            <div class="flow-item" id="north-flow">Bắc: 0 xe</div>
            <div class="flow-item" id="south-flow">Nam: 0 xe</div>
            <div class="flow-item" id="east-flow">Đông: 0 xe</div>
            <div class="flow-item" id="west-flow">Tây: 0 xe</div>
            <div class="flow-item" id="total-vehicles">Tổng: 0 xe</div>
        </div>

        <div class="simulation-info">
            <div class="info-item">
                <i class="fas fa-lightbulb" style="color: #00C851;"></i>
                <span><strong>ĐÈN XANH:</strong> Được đi thẳng</span>
            </div>
            <div class="info-item">
                <i class="fas fa-lightbulb" style="color: #ffbb33;"></i>
                <span><strong>ĐÈN VÀNG:</strong> Chuẩn bị dừng</span>
            </div>
            <div class="info-item">
                <i class="fas fa-lightbulb" style="color: #ff4444;"></i>
                <span><strong>ĐÈN ĐỎ:</strong> Dừng trước vạch</span>
            </div>
        </div>
    </div>

    <script>
        // Keys cho localStorage
        const SIMULATION_STATE_KEY = 'traffic_simulation_state_v3';
        const SIMULATION_VEHICLES_KEY = 'traffic_simulation_vehicles_v3';
        
        // Kiểm tra xem user có phải là admin không
        const isAdmin = <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'true' : 'false'; ?>;
        
        // Configuration from PHP - An toàn với fallback
        const config = <?php 
            echo json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?> || {
            north: {green_time: 30, yellow_time: 5, traffic_flow: 10},
            south: {green_time: 30, yellow_time: 5, traffic_flow: 10},
            east: {green_time: 20, yellow_time: 5, traffic_flow: 10},
            west: {green_time: 20, yellow_time: 5, traffic_flow: 10}
        };

        // Control mode from PHP
        const controlMode = '<?php echo htmlspecialchars($control_mode, ENT_QUOTES, 'UTF-8'); ?>';
        const autoConfig = <?php 
            echo json_encode($auto_config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?> || {
            base_green_time: 30,
            yellow_time: 5,
            min_green_time: 10,
            max_green_time: 60,
            traffic_ratio_threshold: 1.5,
            extra_green_time: 10
        };

        console.log('Loaded config:', config);
        console.log('Control Mode:', controlMode);
        console.log('Auto Config:', autoConfig);
        console.log('Is Admin:', isAdmin);

        // Traffic light states
        const states = {
            EAST_WEST_GREEN: 'east_west_green',
            EAST_WEST_YELLOW: 'east_west_yellow', 
            NORTH_SOUTH_GREEN: 'north_south_green',
            NORTH_SOUTH_YELLOW: 'north_south_yellow'
        };

        // Khởi tạo biến simulation
        let currentState, stateTimer, lastTime, vehicles = [];
        let vehicleSpawnTimers = {
            north: 0,
            south: 0,
            east: 0,
            west: 0
        };
        
        let trafficStats = {
            north: { spawned: 0, current: 0 },
            south: { spawned: 0, current: 0 },
            east: { spawned: 0, current: 0 },
            west: { spawned: 0, current: 0 }
        };

        // Function to save vehicles state to localStorage
        function saveVehiclesState() {
            try {
                const vehicleData = vehicles.map(v => ({
                    id: v.id,
                    direction: v.direction,
                    position: v.position,
                    speed: v.speed,
                    isStopped: v.isStopped,
                    stopTarget: v.stopTarget,
                    hasCrossedStopLine: v.hasCrossedStopLine,
                    color: v.element.style.backgroundColor,
                    zIndex: v.zIndex || 150
                }));
                
                localStorage.setItem(SIMULATION_VEHICLES_KEY, JSON.stringify(vehicleData));
                console.log('💾 Đã lưu trạng thái xe vào localStorage:', vehicleData.length, 'xe');
            } catch (e) {
                console.error('❌ Lỗi khi lưu trạng thái xe:', e);
            }
        }

        // Function to save simulation state to localStorage
        function saveSimulationState() {
            try {
                const stateToSave = {
                    currentState: currentState,
                    stateTimer: stateTimer,
                    lastTimeDelta: Date.now() - lastTime,
                    trafficStats: trafficStats,
                    spawnTimers: vehicleSpawnTimers,
                    savedAt: new Date().toISOString()
                };
                
                localStorage.setItem(SIMULATION_STATE_KEY, JSON.stringify(stateToSave));
                
                // Lưu cả trạng thái xe
                saveVehiclesState();
                
                console.log('💾 Đã lưu trạng thái simulation');
            } catch (e) {
                console.error('❌ Lỗi khi lưu trạng thái simulation:', e);
            }
        }

        // Function to load vehicles state from localStorage
        function loadVehiclesState() {
            try {
                const savedVehicles = localStorage.getItem(SIMULATION_VEHICLES_KEY);
                if (savedVehicles) {
                    const vehicleData = JSON.parse(savedVehicles);
                    
                    // Kiểm tra xem dữ liệu có cũ quá không (quá 5 phút)
                    const savedState = localStorage.getItem(SIMULATION_STATE_KEY);
                    if (savedState) {
                        const state = JSON.parse(savedState);
                        const savedAt = new Date(state.savedAt);
                        const now = new Date();
                        const minutesDiff = (now - savedAt) / (1000 * 60);
                        
                        if (minutesDiff > 5) {
                            console.log('🗑️ Dữ liệu xe đã cũ, bỏ qua khôi phục');
                            return false;
                        }
                    }
                    
                    console.log('🔁 Đang khôi phục trạng thái xe từ localStorage...', vehicleData.length, 'xe');
                    
                    // Khôi phục từng xe
                    vehicleData.forEach(data => {
                        const vehicle = document.createElement('div');
                        
                        vehicle.className = `vehicle ${data.direction}-bound`;
                        vehicle.style.backgroundColor = data.color || getRandomColor();
                        vehicle.id = data.id;
                        
                        vehicle.style.left = `calc(${data.position.x}% - 7.5px)`;
                        vehicle.style.top = `calc(${data.position.y}% - 7.5px)`;
                        vehicle.style.zIndex = data.zIndex || 150;

                        document.getElementById('vehicle-container').appendChild(vehicle);
                        
                        vehicles.push({
                            id: data.id,
                            element: vehicle,
                            direction: data.direction,
                            position: data.position,
                            speed: data.speed || 0.15,
                            isStopped: data.isStopped || false,
                            stopTarget: data.stopTarget,
                            hasCrossedStopLine: data.hasCrossedStopLine || false,
                            zIndex: data.zIndex || 150
                        });
                        
                        // Cập nhật thống kê
                        trafficStats[data.direction].spawned++;
                        trafficStats[data.direction].current++;
                        
                        if (data.isStopped) {
                            vehicle.classList.add('stopped');
                        }
                    });
                    
                    console.log('✅ Đã khôi phục', vehicles.length, 'xe từ localStorage');
                    return true;
                }
            } catch (e) {
                console.error('❌ Lỗi khi khôi phục trạng thái xe:', e);
            }
            return false;
        }

        // Function to load simulation state from localStorage
        function loadSimulationState() {
            try {
                const savedState = localStorage.getItem(SIMULATION_STATE_KEY);
                if (savedState) {
                    const parsed = JSON.parse(savedState);
                    
                    // Kiểm tra xem state có cũ quá không (quá 5 phút)
                    const savedAt = new Date(parsed.savedAt);
                    const now = new Date();
                    const minutesDiff = (now - savedAt) / (1000 * 60);
                    
                    if (minutesDiff > 5) {
                        console.log('🗑️ State đã cũ, khởi tạo mới');
                        return false;
                    }
                    
                    // Khôi phục state
                    currentState = parsed.currentState || states.EAST_WEST_GREEN;
                    stateTimer = parsed.stateTimer || 0;
                    lastTime = Date.now() - (parsed.lastTimeDelta || 0);
                    
                    // Khôi phục thống kê và spawn timers
                    if (parsed.trafficStats) {
                        trafficStats = parsed.trafficStats;
                    }
                    
                    if (parsed.spawnTimers) {
                        vehicleSpawnTimers = parsed.spawnTimers;
                    }
                    
                    console.log('🔁 Đã khôi phục trạng thái simulation từ localStorage');
                    return true;
                }
            } catch (e) {
                console.error('❌ Lỗi khi đọc localStorage:', e);
            }
            
            // Khởi tạo mới
            currentState = states.EAST_WEST_GREEN;
            stateTimer = 0;
            lastTime = Date.now();
            return false;
        }

        // Utility function to get a random bright color
        function getRandomColor() {
            const colors = [
                '#FF6B6B', '#4ECDC4', '#FFD166', '#06D6A0', 
                '#118AB2', '#073B4C', '#EF476F', '#7209B7',
                '#3A86FF', '#FB5607', '#8338EC', '#FF006E'
            ];
            return colors[Math.floor(Math.random() * colors.length)];
        }

        // Hàm lấy vị trí làn và vị trí dừng (tính bằng %)
        function getLanePosition(direction) {
            const container = document.querySelector('.simulation-container');
            if (!container) {
                // Fallback values if container not found
                switch(direction) {
                    case 'north': return { x: 62.5, y: 115, stop_y: 62, spawn_y: 115 };
                    case 'south': return { x: 37.5, y: -5, stop_y: 38, spawn_y: -5 };
                    case 'east': return { x: -5, y: 62.5, stop_x: 38, spawn_x: -5 };
                    case 'west': return { x: 105, y: 37.5, stop_x: 62, spawn_x: 105 };
                }
            }
            
            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;

            const roadDimension = 80;
            const stopLineThickness = 8;
            const vehicleLength = 30;
            const laneCenterOffset = roadDimension / 4;

            const roadDimensionPct = (roadDimension / containerWidth) * 100;
            const stopLineThicknessPct_V = (stopLineThickness / containerHeight) * 100;
            const stopLineThicknessPct_H = (stopLineThickness / containerWidth) * 100;
            const vehicleLengthPct_H = (vehicleLength / containerWidth) * 100;
            const vehicleLengthPct_V = (vehicleLength / containerHeight) * 100;
            const laneCenterOffsetPct_H = (laneCenterOffset / containerWidth) * 100;
            const laneCenterOffsetPct_V = (laneCenterOffset / containerHeight) * 100;
            
            const north_stop_line_y = 50 - (roadDimensionPct / 2);
            const south_stop_line_y = 50 + (roadDimensionPct / 2);
            const west_stop_line_x = 50 - (roadDimensionPct / 2);
            const east_stop_line_x = 50 + (roadDimensionPct / 2);
                    
            switch(direction) {
                case 'north':
                    const north_x = 50 + laneCenterOffsetPct_H;
                    return { 
                        x: north_x,
                        y: 100 + vehicleLengthPct_V,
                        stop_y:  5 + south_stop_line_y - stopLineThicknessPct_V + vehicleLengthPct_V, 
                        spawn_y: 100 + vehicleLengthPct_V,
                    }; 
                case 'south':
                    const south_x = 50 - laneCenterOffsetPct_H;
                    return { 
                        x: south_x, 
                        y: 0 - vehicleLengthPct_V,
                        stop_y: north_stop_line_y + stopLineThicknessPct_V - vehicleLengthPct_V - 5, 
                        spawn_y: 0 - vehicleLengthPct_V,
                    };
                case 'east':
                    const east_y = 50 + laneCenterOffsetPct_V;
                    return { 
                        x: 0 - vehicleLengthPct_H, 
                        y: east_y, 
                        stop_x:  west_stop_line_x + stopLineThicknessPct_H - vehicleLengthPct_H - 2, 
                        spawn_x: 0 - vehicleLengthPct_H,
                    };
                case 'west':
                    const west_y = 50 - laneCenterOffsetPct_V;
                    return { 
                        x: 100 + vehicleLengthPct_H,
                        y: west_y, 
                        stop_x: 2 + east_stop_line_x - stopLineThicknessPct_H + vehicleLengthPct_H, 
                        spawn_x: 100 + vehicleLengthPct_H,
                    };
                default:
                    return { x: 50, y: 50 };
            }
        }

        // Function to create a new vehicle
        function createVehicle(direction) {
            const vehicle = document.createElement('div');
            const color = getRandomColor();
            const lanePos = getLanePosition(direction);

            vehicle.className = `vehicle ${direction}-bound`;
            vehicle.style.backgroundColor = color;
            // Sử dụng timestamp + random để đảm bảo ID duy nhất
            vehicle.id = 'vehicle-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            
            vehicle.style.left = `calc(${lanePos.x}% - 7.5px)`;
            vehicle.style.top = `calc(${lanePos.y}% - 7.5px)`;

            const vehicleContainer = document.getElementById('vehicle-container');
            if (vehicleContainer) {
                vehicleContainer.appendChild(vehicle);
            } else {
                console.error('Không tìm thấy vehicle-container');
                return null;
            }
            
            const newVehicle = {
                id: vehicle.id,
                element: vehicle,
                direction: direction,
                position: { x: lanePos.x, y: lanePos.y },
                speed: 0.15,
                isStopped: false,
                stopTarget: lanePos,
                hasCrossedStopLine: false,
                zIndex: 150 + Math.floor(Math.random() * 10)
            };
            
            trafficStats[direction].spawned++;
            trafficStats[direction].current++;
            
            return newVehicle;
        }

        // Function to check if light is red for a direction
        function isRedLight(direction) {
            if (!currentState) return false;
            
            switch(direction) {
                case 'north':
                case 'south':
                    return currentState === states.EAST_WEST_GREEN || currentState === states.EAST_WEST_YELLOW;
                case 'east':
                case 'west':
                    return currentState === states.NORTH_SOUTH_GREEN || currentState === states.NORTH_SOUTH_YELLOW;
                default:
                    return false;
            }
        }

        // Function to update vehicle positions
        function updateVehicles() {
            const container = document.querySelector('.simulation-container');
            if (!container) return;
            
            const containerHeight = container.clientHeight;
            const containerWidth = container.clientWidth;
            
            const vehicleLength_V = (30 / containerHeight) * 100;
            const vehicleLength_H = (30 / containerWidth) * 100;
            const safeDistance = 3.5;

            // Reset current counts
            trafficStats.north.current = 0;
            trafficStats.south.current = 0;
            trafficStats.east.current = 0;
            trafficStats.west.current = 0;

            // Sắp xếp xe theo thứ tự từ xa đến gần để xử lý đúng
            const sortedVehicles = [...vehicles].sort((a, b) => {
                if (a.direction === 'north' && b.direction === 'north') return b.position.y - a.position.y;
                if (a.direction === 'south' && b.direction === 'south') return a.position.y - b.position.y;
                if (a.direction === 'east' && b.direction === 'east') return a.position.x - b.position.x;
                if (a.direction === 'west' && b.direction === 'west') return b.position.x - a.position.x;
                return 0;
            });

            sortedVehicles.forEach((vehicle, index) => {
                // Count current vehicles
                trafficStats[vehicle.direction].current++;

                const stopCondition = isRedLight(vehicle.direction);
                const ownLength = (vehicle.direction === 'north' || vehicle.direction === 'south') ? vehicleLength_V : vehicleLength_H;
                
                let shouldStop = false;
                let stopPosition = null;

                // Cập nhật trạng thái đã vượt vạch
                if (!vehicle.hasCrossedStopLine) {
                    if (vehicle.direction === 'north' && vehicle.position.y <= vehicle.stopTarget.stop_y) {
                        vehicle.hasCrossedStopLine = true;
                    } else if (vehicle.direction === 'south' && vehicle.position.y >= vehicle.stopTarget.stop_y) {
                        vehicle.hasCrossedStopLine = true;
                    } else if (vehicle.direction === 'east' && vehicle.position.x >= vehicle.stopTarget.stop_x) {
                        vehicle.hasCrossedStopLine = true;
                    } else if (vehicle.direction === 'west' && vehicle.position.x <= vehicle.stopTarget.stop_x) {
                        vehicle.hasCrossedStopLine = true;
                    }
                }
                
                // 1. Kiểm tra vị trí dừng trước vạch (đèn đỏ)
                if (stopCondition && !vehicle.hasCrossedStopLine) { 
                    if (vehicle.direction === 'north' && vehicle.position.y <= vehicle.stopTarget.stop_y + ownLength) {
                        shouldStop = true;
                        stopPosition = vehicle.stopTarget.stop_y + ownLength; 
                    }
                    else if (vehicle.direction === 'south' && vehicle.position.y >= vehicle.stopTarget.stop_y - ownLength) {
                        shouldStop = true;
                        stopPosition = vehicle.stopTarget.stop_y - ownLength;
                    }
                    else if (vehicle.direction === 'east' && vehicle.position.x >= vehicle.stopTarget.stop_x - ownLength) {
                        shouldStop = true;
                        stopPosition = vehicle.stopTarget.stop_x - ownLength;
                    }
                    else if (vehicle.direction === 'west' && vehicle.position.x <= vehicle.stopTarget.stop_x + ownLength) {
                        shouldStop = true;
                        stopPosition = vehicle.stopTarget.stop_x + ownLength;
                    }
                }

                // 2. Kiểm tra khoảng cách với xe phía trước
                const vehiclesInSameDirection = vehicles.filter(v => 
                    v.direction === vehicle.direction && v.id !== vehicle.id
                );
                
                let closestVehicle = null;
                let minDistance = Infinity;
                
                vehiclesInSameDirection.forEach(otherVehicle => {
                    let distance = 0;
                    let isAhead = false;
                    
                    if (vehicle.direction === 'north') {
                        distance = vehicle.position.y - otherVehicle.position.y;
                        isAhead = otherVehicle.position.y < vehicle.position.y;
                    } else if (vehicle.direction === 'south') {
                        distance = otherVehicle.position.y - vehicle.position.y;
                        isAhead = otherVehicle.position.y > vehicle.position.y;
                    } else if (vehicle.direction === 'east') {
                        distance = otherVehicle.position.x - vehicle.position.x;
                        isAhead = otherVehicle.position.x > vehicle.position.x;
                    } else if (vehicle.direction === 'west') {
                        distance = vehicle.position.x - otherVehicle.position.x;
                        isAhead = otherVehicle.position.x < vehicle.position.x;
                    }
                    
                    if (isAhead && distance > 0 && distance < minDistance) {
                        minDistance = distance;
                        closestVehicle = otherVehicle;
                    }
                });
                
                if (closestVehicle && minDistance < ownLength + safeDistance) {
                    shouldStop = true;
                    
                    // Tính vị trí dừng chính xác
                    if (vehicle.direction === 'north') {
                        stopPosition = closestVehicle.position.y + ownLength + safeDistance;
                    } else if (vehicle.direction === 'south') {
                        stopPosition = closestVehicle.position.y - ownLength - safeDistance;
                    } else if (vehicle.direction === 'east') {
                        stopPosition = closestVehicle.position.x - ownLength - safeDistance;
                    } else if (vehicle.direction === 'west') {
                        stopPosition = closestVehicle.position.x + ownLength + safeDistance;
                    }
                }
                
                // Cập nhật trạng thái dừng
                vehicle.isStopped = shouldStop;
                
                if (vehicle.isStopped) {
                    vehicle.element.classList.add('stopped');
                    if (stopPosition !== null) {
                        if (vehicle.direction === 'north') {
                            vehicle.position.y = Math.max(vehicle.position.y, stopPosition);
                        } else if (vehicle.direction === 'south') {
                            vehicle.position.y = Math.min(vehicle.position.y, stopPosition);
                        } else if (vehicle.direction === 'east') {
                            vehicle.position.x = Math.min(vehicle.position.x, stopPosition);
                        } else if (vehicle.direction === 'west') {
                            vehicle.position.x = Math.max(vehicle.position.x, stopPosition);
                        }
                    }
                    
                } else {
                    vehicle.element.classList.remove('stopped');
                    
                    // Di chuyển
                    switch(vehicle.direction) {
                        case 'north':
                            vehicle.position.y -= vehicle.speed;
                            break;
                        case 'south':
                            vehicle.position.y += vehicle.speed;
                            break;
                        case 'east':
                            vehicle.position.x += vehicle.speed;
                            break;
                        case 'west':
                            vehicle.position.x -= vehicle.speed;
                            break;
                    }
                }
                
                // Update CSS position và z-index
                vehicle.element.style.left = `calc(${vehicle.position.x}% - 7.5px)`;
                vehicle.element.style.top = `calc(${vehicle.position.y}% - 7.5px)`;
                vehicle.element.style.zIndex = vehicle.zIndex;
                
                // Remove vehicle if it goes off screen
                if (vehicle.position.y < -15 || vehicle.position.y > 115 || 
                    vehicle.position.x < -15 || vehicle.position.x > 115) {
                    vehicle.element.remove();
                    const idx = vehicles.findIndex(v => v.id === vehicle.id);
                    if (idx > -1) vehicles.splice(idx, 1);
                }
            });
        }

        // Function to spawn vehicles based on traffic flow
        function spawnVehicles(deltaTime) {
            const directions = ['north', 'south', 'east', 'west'];
            
            directions.forEach(direction => {
                const flowRate = parseInt(config[direction]?.traffic_flow) || 5;
                const spawnInterval = 60 / flowRate;
                
                vehicleSpawnTimers[direction] += deltaTime;
                
                if (vehicleSpawnTimers[direction] >= spawnInterval) {
                    const currentVehiclesInDirection = vehicles.filter(v => v.direction === direction).length;
                    
                    if (currentVehiclesInDirection < 15) {
                        const newVehicle = createVehicle(direction);
                        if (newVehicle) {
                            vehicles.push(newVehicle);
                        }
                        vehicleSpawnTimers[direction] = 0;
                    } else {
                        vehicleSpawnTimers[direction] = spawnInterval / 2;
                    }
                }
            });
        }

        // Function to update traffic flow display
        function updateTrafficFlowDisplay() {
            const northFlow = document.getElementById('north-flow');
            const southFlow = document.getElementById('south-flow');
            const eastFlow = document.getElementById('east-flow');
            const westFlow = document.getElementById('west-flow');
            const totalVehicles = document.getElementById('total-vehicles');
            
            if (northFlow) northFlow.textContent = `Bắc: ${trafficStats.north.current} xe`;
            if (southFlow) southFlow.textContent = `Nam: ${trafficStats.south.current} xe`;
            if (eastFlow) eastFlow.textContent = `Đông: ${trafficStats.east.current} xe`;
            if (westFlow) westFlow.textContent = `Tây: ${trafficStats.west.current} xe`;
            if (totalVehicles) totalVehicles.textContent = `Tổng: ${vehicles.length} xe`;
        }

        // Function to update traffic lights
        function updateTrafficLights() {
            if (!currentState) return;
            
            document.querySelectorAll('.light').forEach(light => {
                light.classList.remove('active');
            });

            switch(currentState) {
                case states.EAST_WEST_GREEN:
                    document.getElementById('east-green')?.classList.add('active');
                    document.getElementById('west-green')?.classList.add('active');
                    document.getElementById('north-red')?.classList.add('active');
                    document.getElementById('south-red')?.classList.add('active');
                    break;

                case states.EAST_WEST_YELLOW:
                    document.getElementById('east-yellow')?.classList.add('active');
                    document.getElementById('west-yellow')?.classList.add('active');
                    document.getElementById('north-red')?.classList.add('active');
                    document.getElementById('south-red')?.classList.add('active');
                    break;

                case states.NORTH_SOUTH_GREEN:
                    document.getElementById('north-green')?.classList.add('active');
                    document.getElementById('south-green')?.classList.add('active');
                    document.getElementById('east-red')?.classList.add('active');
                    document.getElementById('west-red')?.classList.add('active');
                    break;

                case states.NORTH_SOUTH_YELLOW:
                    document.getElementById('north-yellow')?.classList.add('active');
                    document.getElementById('south-yellow')?.classList.add('active');
                    document.getElementById('east-red')?.classList.add('active');
                    document.getElementById('west-red')?.classList.add('active');
                    break;
            }

            // Release stopped vehicles when light turns green
            vehicles.forEach(vehicle => {
                if (vehicle.isStopped && !isRedLight(vehicle.direction)) {
                    const nextVehicle = vehicles.find(v => v.direction === vehicle.direction && v.id !== vehicle.id);
                    if (!nextVehicle || !nextVehicle.isStopped) {
                         vehicle.isStopped = false;
                    }
                }
            });
        }

        // Function to calculate automatic traffic light timing
        function calculateAutomaticTiming() {
            const northCount = vehicles.filter(v => v.direction === 'north').length;
            const southCount = vehicles.filter(v => v.direction === 'south').length;
            const eastCount = vehicles.filter(v => v.direction === 'east').length;
            const westCount = vehicles.filter(v => v.direction === 'west').length;
            
            const northSouthTotal = northCount + southCount;
            const eastWestTotal = eastCount + westCount;
            
            let trafficRatio = 1;
            if (northSouthTotal > 0 && eastWestTotal > 0) {
                trafficRatio = Math.max(northSouthTotal, eastWestTotal) / Math.min(northSouthTotal, eastWestTotal);
            }
            
            console.log(`🚦 Auto Traffic: N-S: ${northSouthTotal}, E-W: ${eastWestTotal}, Ratio: ${trafficRatio.toFixed(2)}`);
            
            let northSouthGreenTime = autoConfig.base_green_time || 30;
            let eastWestGreenTime = autoConfig.base_green_time || 30;
            
            if (trafficRatio > (autoConfig.traffic_ratio_threshold || 1.5)) {
                if (northSouthTotal > eastWestTotal) {
                    northSouthGreenTime = Math.min(
                        (autoConfig.base_green_time || 30) + Math.floor((trafficRatio - 1) * (autoConfig.extra_green_time || 10)),
                        autoConfig.max_green_time || 60
                    );
                    eastWestGreenTime = Math.max(
                        (autoConfig.base_green_time || 30) - Math.floor((trafficRatio - 1) * (autoConfig.extra_green_time || 10) / 2),
                        autoConfig.min_green_time || 10
                    );
                } else {
                    eastWestGreenTime = Math.min(
                        (autoConfig.base_green_time || 30) + Math.floor((trafficRatio - 1) * (autoConfig.extra_green_time || 10)),
                        autoConfig.max_green_time || 60
                    );
                    northSouthGreenTime = Math.max(
                        (autoConfig.base_green_time || 30) - Math.floor((trafficRatio - 1) * (autoConfig.extra_green_time || 10) / 2),
                        autoConfig.min_green_time || 10
                    );
                }
            }
            
            return {
                northSouthGreenTime: Math.max(northSouthGreenTime, autoConfig.min_green_time || 10),
                eastWestGreenTime: Math.max(eastWestGreenTime, autoConfig.min_green_time || 10),
                yellowTime: autoConfig.yellow_time || 5,
                trafficRatio: trafficRatio
            };
        }

        // Function to get state duration
        function getStateDuration(state) {
            if (!state) return 10;
            
            if (controlMode === 'manual') {
                switch(state) {
                    case states.EAST_WEST_GREEN:
                        return parseInt(config.east?.green_time) || 20;
                    case states.EAST_WEST_YELLOW:
                        return parseInt(config.east?.yellow_time) || 5;
                    case states.NORTH_SOUTH_GREEN:
                        return parseInt(config.north?.green_time) || 30;
                    case states.NORTH_SOUTH_YELLOW:
                        return parseInt(config.north?.yellow_time) || 5;
                    default:
                        return 10;
                }
            } else {
                const autoTiming = calculateAutomaticTiming();
                
                switch(state) {
                    case states.EAST_WEST_GREEN:
                        return autoTiming.eastWestGreenTime;
                    case states.EAST_WEST_YELLOW:
                        return autoTiming.yellowTime;
                    case states.NORTH_SOUTH_GREEN:
                        return autoTiming.northSouthGreenTime;
                    case states.NORTH_SOUTH_YELLOW:
                        return autoTiming.yellowTime;
                    default:
                        return 10;
                }
            }
        }

        // Function to get state name for display
        function getStateDisplayName(state) {
            if (!state) return 'Đang khởi động...';
            
            switch(state) {
                case states.EAST_WEST_GREEN:
                    return 'ĐÔNG-TÂY: XANH';
                case states.EAST_WEST_YELLOW:
                    return 'ĐÔNG-TÂY: VÀNG';
                case states.NORTH_SOUTH_GREEN:
                    return 'BẮC-NAM: XANH';
                case states.NORTH_SOUTH_YELLOW:
                    return 'BẮC-NAM: VÀNG';
                default:
                    return 'Đang khởi động...';
            }
        }

        // Function to update control mode display
        function updateControlModeDisplay() {
            const modeDisplay = document.getElementById('control-mode');
            const timingInfo = document.getElementById('timing-info');
            
            if (modeDisplay) {
                modeDisplay.textContent = `Chế độ: ${controlMode === 'manual' ? 'ĐIỀU KHIỂN TAY' : 'TỰ ĐỘNG'}`;
            }
            
            // CHỈ hiển thị thời gian đèn cho admin
            if (timingInfo && isAdmin) {
                if (controlMode === 'auto') {
                    const autoTiming = calculateAutomaticTiming();
                    timingInfo.innerHTML = `
                        <div>BẮC-NAM: ${autoTiming.northSouthGreenTime}s 🟢</div>
                        <div>ĐÔNG-TÂY: ${autoTiming.eastWestGreenTime}s 🟢</div>
                        <div>VÀNG: ${autoTiming.yellowTime}s 🟡</div>
                        <div>Tỷ lệ: ${autoTiming.trafficRatio.toFixed(2)}</div>
                    `;
                } else {
                    timingInfo.innerHTML = `
                        <div>BẮC-NAM: ${config.north?.green_time || 30}s 🟢</div>
                        <div>ĐÔNG-TÂY: ${config.east?.green_time || 20}s 🟢</div>
                        <div>VÀNG: ${config.north?.yellow_time || 5}s 🟡</div>
                    `;
                }
                // Đảm bảo timing-info hiển thị
                timingInfo.classList.remove('hidden');
            }
        }

        // Function to update real-time clock
        function updateRealTimeClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('vi-VN', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('vi-VN');
            
            const currentTimeEl = document.getElementById('current-time');
            const currentDateEl = document.getElementById('current-date');
            const realTimeEl = document.getElementById('real-time');
            
            if (currentTimeEl) currentTimeEl.textContent = timeString;
            if (currentDateEl) currentDateEl.textContent = dateString;
            if (realTimeEl) realTimeEl.textContent = timeString;
        }

        // Main simulation loop
        function simulate() {
            const currentTime = Date.now();
            const deltaTime = (currentTime - lastTime) / 1000;
            lastTime = currentTime;
            
            stateTimer += deltaTime;
            
            const stateDuration = getStateDuration(currentState);
            const timeLeft = Math.max(0, Math.ceil(stateDuration - stateTimer));
            
            // Update timer display
            const timerEl = document.getElementById('timer');
            const currentStateEl = document.getElementById('current-state');
            
            if (timerEl) timerEl.textContent = timeLeft;
            if (currentStateEl) currentStateEl.textContent = getStateDisplayName(currentState);
            
            // Spawn and update vehicles
            spawnVehicles(deltaTime);
            updateVehicles();
            updateTrafficFlowDisplay();
            updateControlModeDisplay();
            
            // Lưu trạng thái mỗi frame (hoặc có thể giảm tần suất)
            saveSimulationState();
            
            // State transitions
            if (stateTimer >= stateDuration) {
                stateTimer = 0;
                
                switch(currentState) {
                    case states.EAST_WEST_GREEN:
                        currentState = states.EAST_WEST_YELLOW;
                        break;
                    case states.EAST_WEST_YELLOW:
                        currentState = states.NORTH_SOUTH_GREEN;
                        break;
                    case states.NORTH_SOUTH_GREEN:
                        currentState = states.NORTH_SOUTH_YELLOW;
                        break;
                    case states.NORTH_SOUTH_YELLOW:
                        currentState = states.EAST_WEST_GREEN;
                        break;
                }
                
                updateTrafficLights();
            }
            
            requestAnimationFrame(simulate);
        }

        // Start simulation
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🚦 Khởi động hệ thống mô phỏng giao thông...');
            
            // Khôi phục trạng thái từ localStorage
            loadSimulationState();
            loadVehiclesState();
            
            // Update real-time clock every second
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000);
            
            // Lưu trạng thái khi trang bị đóng/tải lại
            window.addEventListener('beforeunload', saveSimulationState);
            
            // Lưu trạng thái định kỳ mỗi 5 giây (phòng trường hợp beforeunload không hoạt động)
            setInterval(saveSimulationState, 5000);
            
            updateTrafficLights();
            updateControlModeDisplay();
            
            // Nếu không có xe nào được khôi phục, tạo một vài xe ban đầu
            if (vehicles.length === 0) {
                console.log('🚗 Khởi tạo xe ban đầu...');
                setTimeout(() => {
                    for (let i = 0; i < 3; i++) {
                        createVehicle('north');
                        createVehicle('south');
                        createVehicle('east');
                        createVehicle('west');
                    }
                }, 1000);
            }
            
            // Bắt đầu simulation loop
            simulate();
        });
    </script>
</body>
</html>