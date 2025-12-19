<?php
session_start();
include '../database.php'; 

// Kiểm tra đăng nhập và quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ============== THUẬT TOÁN PHÂN TÍCH NÂNG CAO ==============

/**
 * Phân tích thống kê nâng cao
 */
function advancedTrafficAnalysis($conn, $time_range = '1 hour') {
    $analysis = [
        'current' => [],
        'hourly' => [],
        'peak_hours' => [],
        'trends' => [],
        'predictions' => []
    ];
    
    $directions = ['north', 'south', 'east', 'west'];
    
    // 1. Lấy dữ liệu hiện tại
    $current_flows = getCurrentTrafficFlows($conn);
    foreach ($directions as $dir) {
        $analysis['current'][$dir] = $current_flows[$dir] ?? 0;
    }
    
    // 2. Phân tích theo giờ (last 24 hours)
    $hourly_data = getHourlyTrafficData($conn, 24);
    foreach ($directions as $dir) {
        $analysis['hourly'][$dir] = $hourly_data[$dir] ?? [];
    }
    
    // 3. Xác định giờ cao điểm
    $analysis['peak_hours'] = calculatePeakHours($hourly_data);
    
    // 4. Phân tích xu hướng
    $analysis['trends'] = analyzeTrends($hourly_data);
    
    // 5. Dự đoán lưu lượng (sử dụng AI)
    $analysis['predictions'] = predictFutureTraffic($hourly_data);
    
    return $analysis;
}

/**
 * Lấy dữ liệu theo giờ
 */
function getHourlyTrafficData($conn, $hours = 24) {
    $directions = ['north', 'south', 'east', 'west'];
    $hourly_data = [];
    
    foreach ($directions as $dir) {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    AVG({$dir}_count) as avg_flow,
                    MAX({$dir}_count) as max_flow,
                    MIN({$dir}_count) as min_flow
                FROM traffic_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY hour
                ORDER BY hour";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hourly_data[$dir] = [];
        while ($row = $result->fetch_assoc()) {
            $hourly_data[$dir][] = $row;
        }
    }
    
    return $hourly_data;
}

/**
 * Tính giờ cao điểm
 */
function calculatePeakHours($hourly_data) {
    $peak_hours = [];
    $directions = ['north', 'south', 'east', 'west'];
    
    foreach ($directions as $dir) {
        $max_flow = 0;
        $peak_hour = '';
        
        foreach ($hourly_data[$dir] as $hour_data) {
            if ($hour_data['avg_flow'] > $max_flow) {
                $max_flow = $hour_data['avg_flow'];
                $peak_hour = date('H:i', strtotime($hour_data['hour']));
            }
        }
        
        $peak_hours[$dir] = [
            'hour' => $peak_hour,
            'flow' => round($max_flow, 1)
        ];
    }
    
    return $peak_hours;
}

/**
 * Phân tích xu hướng
 */
function analyzeTrends($hourly_data) {
    $trends = [];
    $directions = ['north', 'south', 'east', 'west'];
    
    foreach ($directions as $dir) {
        $data = $hourly_data[$dir];
        if (count($data) < 2) {
            $trends[$dir] = 'stable';
            continue;
        }
        
        $first = $data[0]['avg_flow'];
        $last = end($data)['avg_flow'];
        $change = (($last - $first) / $first) * 100;
        
        if ($change > 10) $trends[$dir] = 'increasing';
        elseif ($change < -10) $trends[$dir] = 'decreasing';
        else $trends[$dir] = 'stable';
    }
    
    return $trends;
}

/**
 * Dự đoán lưu lượng tương lai (AI đơn giản)
 */
function predictFutureTraffic($hourly_data) {
    $predictions = [];
    $directions = ['north', 'south', 'east', 'west'];
    
    foreach ($directions as $dir) {
        $data = $hourly_data[$dir];
        if (count($data) < 3) {
            $predictions[$dir] = ['value' => 0, 'confidence' => 0];
            continue;
        }
        
        // Linear regression đơn giản
        $sum_x = 0; $sum_y = 0; $sum_xy = 0; $sum_x2 = 0;
        $n = count($data);
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $data[$i]['avg_flow'];
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Dự đoán cho giờ tiếp theo
        $next_value = $slope * $n + $intercept;
        
        // Độ tin cậy (dựa trên số điểm dữ liệu)
        $confidence = min(95, ($n / 10) * 100);
        
        $predictions[$dir] = [
            'value' => round(max(0, $next_value), 1),
            'confidence' => round($confidence, 1)
        ];
    }
    
    return $predictions;
}

// ============== XỬ LÝ DỮ LIỆU CHÍNH ==============

// Lấy dữ liệu phân tích nâng cao
$analysis = advancedTrafficAnalysis($conn);

// Lấy dữ liệu cho biểu đồ (last 12 hours)
$chart_data = getChartData($conn, 12);

// Tính toán thống kê tổng hợp
$stats = calculateStatistics($analysis);

// ============== HÀM HỖ TRỢ ==============

function getChartData($conn, $hours = 12) {
    $sql = "SELECT 
                DATE_FORMAT(created_at, '%H:%i') as time_label,
                AVG(north_count) as north,
                AVG(south_count) as south,
                AVG(east_count) as east,
                AVG(west_count) as west
            FROM traffic_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
            ORDER BY created_at
            LIMIT 30";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $hours);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [
        'labels' => [],
        'north' => [],
        'south' => [],
        'east' => [],
        'west' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $data['labels'][] = $row['time_label'];
        $data['north'][] = round($row['north'], 1);
        $data['south'][] = round($row['south'], 1);
        $data['east'][] = round($row['east'], 1);
        $data['west'][] = round($row['west'], 1);
    }
    
    return $data;
}

function calculateStatistics($analysis) {
    $stats = [
        'total_current' => array_sum($analysis['current']),
        'avg_hourly' => [],
        'max_flows' => [],
        'min_flows' => []
    ];
    
    $directions = ['north', 'south', 'east', 'west'];
    
    foreach ($directions as $dir) {
        $flows = array_column($analysis['hourly'][$dir], 'avg_flow');
        if (!empty($flows)) {
            $stats['avg_hourly'][$dir] = round(array_sum($flows) / count($flows), 1);
            $stats['max_flows'][$dir] = round(max($flows), 1);
            $stats['min_flows'][$dir] = round(min($flows), 1);
        } else {
            $stats['avg_hourly'][$dir] = 0;
            $stats['max_flows'][$dir] = 0;
            $stats['min_flows'][$dir] = 0;
        }
    }
    
    return $stats;
}

// Chuẩn bị dữ liệu cho JavaScript
$chart_labels = json_encode($chart_data['labels']);
$chart_north = json_encode($chart_data['north']);
$chart_south = json_encode($chart_data['south']);
$chart_east = json_encode($chart_data['east']);
$chart_west = json_encode($chart_data['west']);

// Dữ liệu cho biểu đồ phân bố
$distribution_data = json_encode([
    'labels' => ['Bắc', 'Nam', 'Đông', 'Tây'],
    'values' => [
        $analysis['current']['north'],
        $analysis['current']['south'],
        $analysis['current']['east'],
        $analysis['current']['west']
    ]
]);

// Dữ liệu cho heatmap (giả lập)
$heatmap_data = generateHeatmapData();

// Lấy thống kê users
$user_stats = [];
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $user_stats['total_users'] = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    $user_stats['total_users'] = 0;
}

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin'");
    $user_stats['total_admins'] = $result ? $result->fetch_assoc()['total'] : 0;
} catch (Exception $e) {
    $user_stats['total_admins'] = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Lưu lượng - Hệ thống Phân tích AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar - Đã sửa để giống code thứ hai */
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

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar-header small {
            font-size: 0.85rem;
            opacity: 0.9;
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

        .sidebar-menu {
            list-style: none;
            padding: 0;
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
            border-left: 4px solid var(--primary-color);
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
            min-height: 100vh;
        }

        /* Header */
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
            font-size: 1.8rem;
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

        /* KPI Cards - Giữ nguyên */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .kpi-card.north::before { background: #3498db; }
        .kpi-card.south::before { background: #2ecc71; }
        .kpi-card.east::before { background: #f39c12; }
        .kpi-card.west::before { background: #e74c3c; }
        .kpi-card.total::before { background: #9b59b6; }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .kpi-title {
            font-size: 0.9rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .kpi-icon.north { background: #3498db; }
        .kpi-icon.south { background: #2ecc71; }
        .kpi-icon.east { background: #f39c12; }
        .kpi-icon.west { background: #e74c3c; }
        .kpi-icon.total { background: #9b59b6; }

        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
        }

        .kpi-card.north .kpi-value { color: #3498db; }
        .kpi-card.south .kpi-value { color: #2ecc71; }
        .kpi-card.east .kpi-value { color: #f39c12; }
        .kpi-card.west .kpi-value { color: #e74c3c; }
        .kpi-card.total .kpi-value { color: #9b59b6; }

        .kpi-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .kpi-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .trend-up { color: #27ae60; }
        .trend-down { color: #e74c3c; }
        .trend-stable { color: #7f8c8d; }

        /* Chart Containers */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .chart-container:hover {
            transform: translateY(-3px);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-controls {
            display: flex;
            gap: 10px;
        }

        .time-range-btn {
            padding: 8px 16px;
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 8px;
            color: var(--primary-color);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .time-range-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .time-range-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Analysis Panels */
        .analysis-panel {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .panel-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }

        .stat-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .stat-subtext {
            font-size: 0.85rem;
            color: #95a5a6;
            margin-top: 5px;
        }

        /* Prediction Panel */
        .prediction-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .prediction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .prediction-item {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .prediction-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.25);
        }

        .prediction-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .prediction-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .confidence-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Heatmap */
        .heatmap-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .heatmap {
            display: grid;
            grid-template-columns: repeat(24, 1fr);
            gap: 2px;
            margin-top: 20px;
        }

        .heatmap-cell {
            aspect-ratio: 1;
            border-radius: 3px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .heatmap-cell:hover {
            transform: scale(1.1);
            z-index: 1;
        }

        .heatmap-legend {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 250px;
            }
            .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
                padding: 20px;
            }
            .chart-grid {
                grid-template-columns: 1fr;
            }
            .kpi-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - Đã sửa để giống code thứ hai -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-traffic-light"></i> TRAFFIC CONTROL</h2>
            <small>Hệ thống Mô phỏng Giao thông</small>
        </div>
        
        <ul class="sidebar-menu">
            <!-- Phần Tổng quan -->
            <div class="menu-section">
                <div class="menu-section-title">TỔNG QUAN</div>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            </div>

            <!-- Phần Quản lý Giao thông -->
            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ GIAO THÔNG</div>
                <li><a href="simulation.php"><i class="fas fa-play-circle"></i> Điều khiển Mô phỏng</a></li>
                <li><a href="traffic-lights.php"><i class="fas fa-traffic-light"></i> Đèn giao thông</a></li>
                <li><a href="baocaoluuluong.php" class="active"><i class="fas fa-chart-bar"></i> Báo cáo lưu lượng</a></li>
                <li><a href="nutgiaothong.php"><i class="fas fa-crosshairs"></i> Nút giao thông</a></li>
            </div>

            <!-- Phần Quản lý Hệ thống -->
            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ HỆ THỐNG</div>
                <li><a href="users.php"><i class="fas fa-user-cog"></i> Quản lý Users <span class="menu-badge"><?php echo $user_stats['total_users']; ?></span></a></li>
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
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-chart-network"></i> Báo cáo Lưu lượng & Phân tích AI</h1>
                <small>Phân tích thời gian thực và dự đoán lưu lượng giao thông</small>
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

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card north">
                <div class="kpi-header">
                    <div class="kpi-title">BẮC</div>
                    <div class="kpi-icon north">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
                <div class="kpi-value"><?php echo $analysis['current']['north']; ?></div>
                <div class="kpi-footer">
                    <div class="kpi-trend">
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span class="trend-up">+12%</span>
                    </div>
                    <div class="kpi-subtext">Cao nhất: <?php echo $stats['max_flows']['north']; ?></div>
                </div>
            </div>

            <div class="kpi-card south">
                <div class="kpi-header">
                    <div class="kpi-title">NAM</div>
                    <div class="kpi-icon south">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
                <div class="kpi-value"><?php echo $analysis['current']['south']; ?></div>
                <div class="kpi-footer">
                    <div class="kpi-trend">
                        <i class="fas fa-arrow-down trend-down"></i>
                        <span class="trend-down">-5%</span>
                    </div>
                    <div class="kpi-subtext">Cao nhất: <?php echo $stats['max_flows']['south']; ?></div>
                </div>
            </div>

            <div class="kpi-card east">
                <div class="kpi-header">
                    <div class="kpi-title">ĐÔNG</div>
                    <div class="kpi-icon east">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <div class="kpi-value"><?php echo $analysis['current']['east']; ?></div>
                <div class="kpi-footer">
                    <div class="kpi-trend">
                        <i class="fas fa-minus trend-stable"></i>
                        <span class="trend-stable">+2%</span>
                    </div>
                    <div class="kpi-subtext">Cao nhất: <?php echo $stats['max_flows']['east']; ?></div>
                </div>
            </div>

            <div class="kpi-card west">
                <div class="kpi-header">
                    <div class="kpi-title">TÂY</div>
                    <div class="kpi-icon west">
                        <i class="fas fa-arrow-left"></i>
                    </div>
                </div>
                <div class="kpi-value"><?php echo $analysis['current']['west']; ?></div>
                <div class="kpi-footer">
                    <div class="kpi-trend">
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span class="trend-up">+8%</span>
                    </div>
                    <div class="kpi-subtext">Cao nhất: <?php echo $stats['max_flows']['west']; ?></div>
                </div>
            </div>

            <div class="kpi-card total">
                <div class="kpi-header">
                    <div class="kpi-title">TỔNG LƯU LƯỢNG</div>
                    <div class="kpi-icon total">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
                <div class="kpi-value"><?php echo $stats['total_current']; ?></div>
                <div class="kpi-footer">
                    <div class="kpi-trend">
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span class="trend-up">+4.2%</span>
                    </div>
                    <div class="kpi-subtext">Tổng số xe/giờ</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="chart-grid">
            <!-- Line Chart: Xu hướng lưu lượng -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-line"></i> Xu hướng Lưu lượng theo Thời gian
                    </div>
                    <div class="chart-controls">
                        <button class="time-range-btn active" onclick="changeTimeRange('1h')">1 Giờ</button>
                        <button class="time-range-btn" onclick="changeTimeRange('6h')">6 Giờ</button>
                        <button class="time-range-btn" onclick="changeTimeRange('24h')">24 Giờ</button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>

            <!-- Bar Chart: Phân bố lưu lượng -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-bar"></i> Phân bố Lưu lượng theo Hướng
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Analysis Panel -->
        <div class="analysis-panel">
            <div class="panel-title">
                <i class="fas fa-chart-pie"></i> Phân tích Chi tiết
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> Giờ cao điểm (Bắc)
                    </div>
                    <div class="stat-value"><?php echo $analysis['peak_hours']['north']['hour'] ?? 'N/A'; ?></div>
                    <div class="stat-subtext"><?php echo ($analysis['peak_hours']['north']['flow'] ?? 0) . ' xe/phút'; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> Giờ cao điểm (Nam)
                    </div>
                    <div class="stat-value"><?php echo $analysis['peak_hours']['south']['hour'] ?? 'N/A'; ?></div>
                    <div class="stat-subtext"><?php echo ($analysis['peak_hours']['south']['flow'] ?? 0) . ' xe/phút'; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> Giờ cao điểm (Đông)
                    </div>
                    <div class="stat-value"><?php echo $analysis['peak_hours']['east']['hour'] ?? 'N/A'; ?></div>
                    <div class="stat-subtext"><?php echo ($analysis['peak_hours']['east']['flow'] ?? 0) . ' xe/phút'; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> Giờ cao điểm (Tây)
                    </div>
                    <div class="stat-value"><?php echo $analysis['peak_hours']['west']['hour'] ?? 'N/A'; ?></div>
                    <div class="stat-subtext"><?php echo ($analysis['peak_hours']['west']['flow'] ?? 0) . ' xe/phút'; ?></div>
                </div>
            </div>
        </div>

        <!-- Prediction Panel -->
        <div class="prediction-panel">
            <div class="panel-title" style="color: white;">
                <i class="fas fa-crystal-ball"></i> Dự đoán Lưu lượng (1 giờ tới)
            </div>
            <div class="prediction-grid">
                <?php foreach ($analysis['predictions'] as $dir => $prediction): ?>
                    <?php 
                    $dir_names = [
                        'north' => 'BẮC',
                        'south' => 'NAM', 
                        'east' => 'ĐÔNG',
                        'west' => 'TÂY'
                    ];
                    $icons = [
                        'north' => 'fa-arrow-up',
                        'south' => 'fa-arrow-down',
                        'east' => 'fa-arrow-right',
                        'west' => 'fa-arrow-left'
                    ];
                    ?>
                    <div class="prediction-item">
                        <div class="prediction-value"><?php echo $prediction['value']; ?></div>
                        <div class="prediction-label">
                            <i class="fas <?php echo $icons[$dir]; ?>"></i> <?php echo $dir_names[$dir]; ?>
                        </div>
                        <div class="confidence-badge">
                            <?php echo $prediction['confidence']; ?>% Độ tin cậy
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Heatmap -->
        <div class="heatmap-container">
            <div class="panel-title">
                <i class="fas fa-fire"></i> Heatmap Lưu lượng theo Giờ
            </div>
            <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 0.95rem;">
                Hiển thị mật độ lưu lượng trong 24 giờ qua (càng đậm = càng nhiều xe)
            </p>
            <div class="heatmap" id="heatmap"></div>
            <div class="heatmap-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #e0f3f8;"></div>
                    <span>Rất thấp (0-5 xe)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #abd9e9;"></div>
                    <span>Thấp (6-10 xe)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #74add1;"></div>
                    <span>Trung bình (11-15 xe)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #4575b4;"></div>
                    <span>Cao (16-20 xe)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #313695;"></div>
                    <span>Rất cao (>20 xe)</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============== CHART CONFIGURATIONS ==============
        
        // Line Chart: Xu hướng lưu lượng
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        const lineChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?php echo $chart_labels; ?>,
                datasets: [
                    {
                        label: 'Bắc',
                        data: <?php echo $chart_north; ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Nam',
                        data: <?php echo $chart_south; ?>,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Đông',
                        data: <?php echo $chart_east; ?>,
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Tây',
                        data: <?php echo $chart_west; ?>,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        padding: 12
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Số lượng xe'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                },
                animations: {
                    tension: {
                        duration: 1000,
                        easing: 'linear'
                    }
                }
            }
        });

        // Bar Chart: Phân bố lưu lượng
        const barCtx = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Bắc', 'Nam', 'Đông', 'Tây'],
                datasets: [{
                    label: 'Lưu lượng hiện tại',
                    data: [
                        <?php echo $analysis['current']['north']; ?>,
                        <?php echo $analysis['current']['south']; ?>,
                        <?php echo $analysis['current']['east']; ?>,
                        <?php echo $analysis['current']['west']; ?>
                    ],
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(243, 156, 18, 0.8)',
                        'rgba(231, 76, 60, 0.8)'
                    ],
                    borderColor: [
                        '#3498db',
                        '#2ecc71',
                        '#f39c12',
                        '#e74c3c'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        padding: 12
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Số lượng xe'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // ============== HEATMAP GENERATION ==============
        function generateHeatmap() {
            const heatmap = document.getElementById('heatmap');
            heatmap.innerHTML = '';
            
            // Tạo dữ liệu giả lập cho 24 giờ
            const hours = 24;
            for (let i = 0; i < hours; i++) {
                // Tạo giá trị ngẫu nhiên từ 0-25
                const value = Math.floor(Math.random() * 26);
                
                // Xác định màu dựa trên giá trị
                let color;
                if (value <= 5) color = '#e0f3f8';
                else if (value <= 10) color = '#abd9e9';
                else if (value <= 15) color = '#74add1';
                else if (value <= 20) color = '#4575b4';
                else color = '#313695';
                
                const cell = document.createElement('div');
                cell.className = 'heatmap-cell';
                cell.style.backgroundColor = color;
                cell.title = `${i.toString().padStart(2, '0')}:00 - ${value} xe`;
                
                heatmap.appendChild(cell);
            }
        }

        // ============== TIME RANGE CONTROLS ==============
        function changeTimeRange(range) {
            // Cập nhật nút active
            document.querySelectorAll('.time-range-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Thực tế: Gọi API để lấy dữ liệu mới
            // Ở đây chỉ mô phỏng
            console.log('Đổi khoảng thời gian:', range);
            
            // Hiệu ứng loading
            lineChart.data.datasets.forEach(dataset => {
                dataset.data = dataset.data.map(() => Math.random() * 20 + 5);
            });
            lineChart.update();
        }

        // ============== REAL-TIME UPDATES ==============
        function updateRealTimeData() {
            // Mô phỏng cập nhật dữ liệu thời gian thực
            const newData = {
                north: Math.floor(Math.random() * 30 + 10),
                south: Math.floor(Math.random() * 30 + 10),
                east: Math.floor(Math.random() * 30 + 10),
                west: Math.floor(Math.random() * 30 + 10)
            };
            
            // Cập nhật KPI cards
            document.querySelectorAll('.kpi-card').forEach((card, index) => {
                const valueElement = card.querySelector('.kpi-value');
                const values = Object.values(newData);
                const total = values.reduce((a, b) => a + b, 0);
                
                if (index < 4) {
                    valueElement.textContent = values[index];
                } else {
                    valueElement.textContent = total;
                }
            });
            
            // Cập nhật biểu đồ thanh
            barChart.data.datasets[0].data = Object.values(newData);
            barChart.update();
            
            // Thêm dữ liệu mới vào biểu đồ đường
            const now = new Date();
            const timeLabel = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
            
            lineChart.data.labels.push(timeLabel);
            lineChart.data.labels.shift();
            
            lineChart.data.datasets[0].data.push(newData.north);
            lineChart.data.datasets[0].data.shift();
            
            lineChart.data.datasets[1].data.push(newData.south);
            lineChart.data.datasets[1].data.shift();
            
            lineChart.data.datasets[2].data.push(newData.east);
            lineChart.data.datasets[2].data.shift();
            
            lineChart.data.datasets[3].data.push(newData.west);
            lineChart.data.datasets[3].data.shift();
            
            lineChart.update();
        }

        // ============== INITIALIZATION ==============
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚦 Báo cáo Lưu lượng với Phân tích AI đã tải');
            
            // Tạo heatmap
            generateHeatmap();
            
            // Cập nhật dữ liệu thời gian thực mỗi 10 giây
            setInterval(updateRealTimeData, 10000);
            
            // Tự động refresh trang mỗi 5 phút
            setTimeout(() => {
                location.reload();
            }, 5 * 60 * 1000);
        });

        // ============== EXPORT FUNCTIONS ==============
        function exportToPDF() {
            alert('Đang xuất báo cáo ra PDF...');
            // Thực tế: Gọi API xuất PDF
        }

        function exportToExcel() {
            alert('Đang xuất dữ liệu ra Excel...');
            // Thực tế: Gọi API xuất Excel
        }

        function printReport() {
            window.print();
        }

        // ============== KEYBOARD SHORTCUTS ==============
        document.addEventListener('keydown', function(e) {
            // Ctrl + P để in
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReport();
            }
            // Ctrl + E để xuất Excel
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportToExcel();
            }
            // Ctrl + R để refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
        });
    </script>
</body>
</html>
<?php 
// Hàm tạo dữ liệu heatmap giả lập
function generateHeatmapData() {
    $heatmap = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $heatmap[$hour] = rand(0, 25);
    }
    return json_encode($heatmap);
}
?>