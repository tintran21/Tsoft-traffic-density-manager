<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "traffic_simulation";
$port = 3307;

// Tạo kết nối KHÔNG chọn database trước
$conn = new mysqli($servername, $username, $password, '', $port);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>
        <h3>❌ Lỗi kết nối MySQL</h3>
        <p><strong>Lý do:</strong> " . $conn->connect_error . "</p>
        <p><strong>Giải pháp:</strong></p>
        <ol>
            <li>Mở XAMPP Control Panel</li>
            <li>Start MySQL service (nút Start màu xanh)</li>
            <li>Đảm bảo cổng MySQL là 3307</li>
            <li>Refresh lại trang này</li>
        </ol>
    </div>");
}

// Tạo database nếu chưa tồn tại
$create_db_sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($create_db_sql) === TRUE) {
    // Chọn database sau khi tạo
    $conn->select_db($dbname);
} else {
    die("<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>
        <h3>❌ Lỗi tạo database</h3>
        <p><strong>Lý do:</strong> " . $conn->error . "</p>
    </div>");
}

// Set charset
$conn->set_charset("utf8mb4");

// ==================== TẠO CÁC BẢNG CƠ BẢN ====================
// Bảng users
$create_users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    activation_code VARCHAR(32),
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_users_table)) {
    error_log("Lỗi tạo bảng users: " . $conn->error);
}

// Bảng simulation_config
$create_config_table = "CREATE TABLE IF NOT EXISTS simulation_config (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    direction ENUM('north', 'south', 'east', 'west') NOT NULL,
    green_time INT(11) DEFAULT 30,
    yellow_time INT(11) DEFAULT 3,
    red_time INT(11) DEFAULT 40,
    traffic_flow INT(11) DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_direction (direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_config_table)) {
    error_log("Lỗi tạo bảng simulation_config: " . $conn->error);
}

// Bảng control_mode (đổi tên từ control_settings để nhất quán)
$create_control_table = "CREATE TABLE IF NOT EXISTS control_mode (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    mode ENUM('manual', 'auto') DEFAULT 'manual',
    auto_config JSON DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_control_table)) {
    error_log("Lỗi tạo bảng control_mode: " . $conn->error);
}

// Bảng traffic_logs
$create_traffic_logs_table = "CREATE TABLE IF NOT EXISTS traffic_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    north_count INT(11) DEFAULT 0,
    south_count INT(11) DEFAULT 0,
    east_count INT(11) DEFAULT 0,
    west_count INT(11) DEFAULT 0,
    total_vehicles INT(11) DEFAULT 0,
    traffic_ratio DECIMAL(5,2) DEFAULT 1.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_traffic_logs_table)) {
    error_log("Lỗi tạo bảng traffic_logs: " . $conn->error);
}

// ==================== BẢNG QUAN TRỌNG: TRAFFIC_HISTORY ====================
// Bảng lưu lịch sử lưu lượng theo thời gian (PHẢI CÓ để phân tích 5 phút)
$create_traffic_history_table = "CREATE TABLE IF NOT EXISTS traffic_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    direction VARCHAR(20) NOT NULL,
    traffic_flow INT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_direction_timestamp (direction, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_traffic_history_table)) {
    error_log("Lỗi tạo bảng traffic_history: " . $conn->error);
}

// Bảng password_reset_requests
$create_password_requests_table = "CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    status ENUM('pending', 'processed', 'cancelled') DEFAULT 'pending',
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT(11) DEFAULT NULL,
    processed_time TIMESTAMP NULL DEFAULT NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($create_password_requests_table)) {
    error_log("Lỗi tạo bảng password_reset_requests: " . $conn->error);
}

// ==================== CHÈN DỮ LIỆU MẶC ĐỊNH ====================

// Chèn dữ liệu mặc định cho simulation_config
$check_config = "SELECT COUNT(*) as count FROM simulation_config";
$result = $conn->query($check_config);
if ($result && $result->fetch_assoc()['count'] == 0) {
    $default_config = [
        ['north', 30, 3, 40, 10],
        ['south', 30, 3, 40, 10],
        ['east', 25, 3, 45, 15],
        ['west', 25, 3, 45, 15]
    ];
    
    $stmt = $conn->prepare("INSERT INTO simulation_config (direction, green_time, yellow_time, red_time, traffic_flow) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        foreach ($default_config as $config) {
            $stmt->bind_param("siiii", $config[0], $config[1], $config[2], $config[3], $config[4]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// Chèn dữ liệu mặc định cho control_mode
$check_control = "SELECT COUNT(*) as count FROM control_mode";
$result = $conn->query($check_control);
if ($result && $result->fetch_assoc()['count'] == 0) {
    $default_auto_config = json_encode([
        'min_green_time' => 15,
        'max_green_time' => 60,
        'yellow_time' => 5,
        'base_green_time' => 30,
        'traffic_ratio_threshold' => 1.5,
        'extra_green_time' => 10
    ]);
    
    $insert_control = "INSERT INTO control_mode (mode, auto_config) VALUES ('manual', '$default_auto_config')";
    $conn->query($insert_control);
}

// Thêm user admin mặc định
$check_admin = "SELECT COUNT(*) as count FROM users WHERE username = 'admin'";
$result = $conn->query($check_admin);
if ($result && $result->fetch_assoc()['count'] == 0) {
    $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
    $activation_code = bin2hex(random_bytes(16));
    
    $insert_admin = "INSERT INTO users (username, password, full_name, email, role, activation_code, is_active) 
                     VALUES ('admin', '$hashed_password', 'Quản trị viên', 'admin@traffic.com', 'admin', '$activation_code', 1)";
    
    if (!$conn->query($insert_admin)) {
        error_log("Lỗi tạo admin user: " . $conn->error);
    }
}

// ==================== CÁC HÀM HỖ TRỢ ====================

// Hàm lấy cấu hình simulation
function getSimulationConfig($conn) {
    $config = [];
    $sql = "SELECT direction, green_time, yellow_time, red_time, traffic_flow FROM simulation_config";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $config[$row['direction']] = [
                'green_time' => (int)$row['green_time'],
                'yellow_time' => (int)$row['yellow_time'],
                'red_time' => (int)$row['red_time'],
                'traffic_flow' => (int)$row['traffic_flow']
            ];
        }
    } else {
        // Giá trị mặc định
        $config = [
            'north' => ['green_time' => 30, 'yellow_time' => 3, 'red_time' => 40, 'traffic_flow' => 10],
            'south' => ['green_time' => 30, 'yellow_time' => 3, 'red_time' => 40, 'traffic_flow' => 10],
            'east' => ['green_time' => 25, 'yellow_time' => 3, 'red_time' => 45, 'traffic_flow' => 15],
            'west' => ['green_time' => 25, 'yellow_time' => 3, 'red_time' => 45, 'traffic_flow' => 15]
        ];
    }
    return $config;
}

// Hàm lấy lưu lượng hiện tại
function getCurrentTrafficFlows($conn) {
    $flows = [];
    $sql = "SELECT direction, traffic_flow FROM simulation_config";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $flows[$row['direction']] = (int)$row['traffic_flow'];
        }
    }
    return $flows;
}

// Hàm lưu cấu hình simulation
function saveSimulationConfig($conn, $direction, $green_time, $yellow_time, $red_time, $traffic_flow) {
    $sql = "INSERT INTO simulation_config (direction, green_time, yellow_time, red_time, traffic_flow) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            green_time = VALUES(green_time), 
            yellow_time = VALUES(yellow_time), 
            red_time = VALUES(red_time), 
            traffic_flow = VALUES(traffic_flow)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("siiii", $direction, $green_time, $yellow_time, $red_time, $traffic_flow);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

// Hàm lấy chế độ điều khiển
function getControlMode($conn) {
    $sql = "SELECT mode, auto_config FROM control_mode ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'mode' => $row['mode'],
            'auto_config' => $row['auto_config'] ? json_decode($row['auto_config'], true) : [
                'min_green_time' => 15,
                'max_green_time' => 60,
                'yellow_time' => 5,
                'base_green_time' => 30,
                'traffic_ratio_threshold' => 1.5,
                'extra_green_time' => 10
            ]
        ];
    } else {
        return [
            'mode' => 'manual',
            'auto_config' => [
                'min_green_time' => 15,
                'max_green_time' => 60,
                'yellow_time' => 5,
                'base_green_time' => 30,
                'traffic_ratio_threshold' => 1.5,
                'extra_green_time' => 10
            ]
        ];
    }
}

// Hàm cập nhật chế độ điều khiển
function updateControlMode($conn, $mode, $auto_config = null) {
    if ($auto_config === null) {
        $auto_config = json_encode([
            'min_green_time' => 15,
            'max_green_time' => 60,
            'yellow_time' => 5,
            'base_green_time' => 30,
            'traffic_ratio_threshold' => 1.5,
            'extra_green_time' => 10
        ]);
    } else {
        $auto_config = json_encode($auto_config);
    }
    
    $sql = "INSERT INTO control_mode (mode, auto_config) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $mode, $auto_config);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

// Hàm lưu log lưu lượng
function saveTrafficLog($conn, $north_count, $south_count, $east_count, $west_count, $traffic_ratio = 1.00) {
    $total_vehicles = $north_count + $south_count + $east_count + $west_count;
    
    $sql = "INSERT INTO traffic_logs (north_count, south_count, east_count, west_count, total_vehicles, traffic_ratio) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iiiiid", $north_count, $south_count, $east_count, $west_count, $total_vehicles, $traffic_ratio);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

// ==================== HÀM MỚI: PHÂN TÍCH 5 PHÚT GẦN NHẤT ====================

// Hàm lưu dữ liệu vào traffic_history
function saveTrafficHistory($conn, $direction, $traffic_flow) {
    $sql = "INSERT INTO traffic_history (direction, traffic_flow) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $direction, $traffic_flow);
        $result = $stmt->execute();
        $stmt->close();
        
        // Dọn dẹp: chỉ giữ 100 bản ghi mới nhất cho mỗi hướng
        $cleanup = "DELETE FROM traffic_history 
                    WHERE id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM traffic_history 
                            WHERE direction = ? 
                            ORDER BY timestamp DESC 
                            LIMIT 100
                        ) AS temp
                    ) AND direction = ?";
        
        $stmt = $conn->prepare($cleanup);
        $stmt->bind_param("ss", $direction, $direction);
        $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    return false;
}

// Hàm lấy dữ liệu lịch sử 5 phút gần nhất
function getTrafficHistoryLast5Minutes($conn, $direction) {
    $sql = "SELECT traffic_flow, timestamp 
            FROM traffic_history 
            WHERE direction = ? 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY timestamp DESC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $direction);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'flow' => (int)$row['traffic_flow'],
                'time' => $row['timestamp']
            ];
        }
        
        $stmt->close();
        return $data;
    }
    return [];
}

// Hàm tính trung bình lưu lượng 5 phút gần nhất
function getAverageTrafficLast5Minutes($conn, $direction) {
    $data = getTrafficHistoryLast5Minutes($conn, $direction);
    
    if (empty($data)) {
        return 10.0; // Giá trị mặc định nếu không có dữ liệu
    }
    
    $total = 0;
    $count = count($data);
    
    foreach ($data as $record) {
        $total += $record['flow'];
    }
    
    return $count > 0 ? round($total / $count, 2) : 10.0;
}

// Hàm lấy thống kê lưu lượng
function getTrafficStats($conn, $hours = 24) {
    $sql = "SELECT 
            AVG(north_count) as avg_north,
            AVG(south_count) as avg_south, 
            AVG(east_count) as avg_east,
            AVG(west_count) as avg_west,
            AVG(total_vehicles) as avg_total,
            AVG(traffic_ratio) as avg_ratio,
            MAX(total_vehicles) as peak_traffic,
            COUNT(*) as record_count
            FROM traffic_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        return $stats;
    }
    return null;
}

// Hàm kiểm tra kết nối
function checkDatabaseConnection($conn) {
    if ($conn->connect_error) {
        return false;
    }
    return true;
}

// Hàm lấy thông tin database
function getDatabaseInfo($conn) {
    $info = [
        'host' => $conn->host_info,
        'version' => $conn->server_version,
        'database' => $conn->query("SELECT DATABASE()")->fetch_array()[0],
        'tables' => []
    ];
    
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            $info['tables'][] = $row[0];
        }
    }
    
    return $info;
}

// Debug function
function debugTableStructure($conn, $tableName) {
    $result = $conn->query("DESCRIBE $tableName");
    $structure = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $structure[] = $row;
        }
    }
    return $structure;
}

// Ghi log để debug
error_log("Database initialized successfully - " . date('Y-m-d H:i:s'));

// Tự động tạo vài dữ liệu mẫu cho traffic_history nếu bảng trống
$check_history = "SELECT COUNT(*) as count FROM traffic_history";
$result = $conn->query($check_history);
if ($result && $result->fetch_assoc()['count'] == 0) {
    // Tạo dữ liệu mẫu cho 5 phút trước
    $directions = ['north', 'south', 'east', 'west'];
    $base_flows = ['north' => 10, 'south' => 12, 'east' => 15, 'west' => 8];
    
    foreach ($directions as $dir) {
        for ($i = 0; $i < 15; $i++) { // 15 bản ghi ~ 5 phút (mỗi 20 giây 1 lần)
            $flow = $base_flows[$dir] + rand(-3, 3); // Thêm biến thiên ngẫu nhiên
            $flow = max(1, $flow); // Đảm bảo không âm
            
            // Tạo timestamp cách đây i*20 giây
            $timestamp = date('Y-m-d H:i:s', strtotime("-$i*20 seconds"));
            
            $sql = "INSERT INTO traffic_history (direction, traffic_flow, timestamp) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sis", $dir, $flow, $timestamp);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    error_log("Đã tạo dữ liệu mẫu cho traffic_history");
}
?>