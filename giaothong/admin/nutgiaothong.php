<?php
session_start();
include '../database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$directions_map = [
    'north' => 'BẮC',
    'south' => 'NAM',
    'east' => 'ĐÔNG',
    'west' => 'TÂY',
];

// Đường dẫn ảnh mặc định
$default_images = [
    'north' => '../imageluuluong/bac/ll1.jpg',
    'south' => '../imageluuluong/nam/ll2.jpg',
    'east' => '../imageluuluong/dong/ll3.jpg',
    'west' => '../imageluuluong/tay/ll4.jpg',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nút Giao thông - Traffic Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f6fa; display: flex; }
        .sidebar { width: 280px; background: #2c3e50; color: white; height: 100vh; position: fixed; overflow-y: auto; }
        .sidebar-header { padding: 20px; background: #34495e; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar-menu { list-style: none; padding: 0; }
        .menu-section { margin: 15px 0; }
        .menu-section-title { padding: 10px 20px; color: #95a5a6; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 20px; color: #bdc3c7; text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #34495e; color: white; border-left: 4px solid #3498db; }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; font-size: 1.1rem; }
        .main-content { margin-left: 280px; padding: 20px; width: calc(100% - 280px); }
        .header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.8rem; color: #2c3e50; }
        .header small { color: #7f8c8d; }
        .header-right { display: flex; align-items: center; }
        .user-info { display: flex; align-items: center; margin-left: 20px; }
        .user-avatar { width: 40px; height: 40px; background: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-left: 10px; }
        .web-link { text-decoration: none; color: #3498db; padding: 8px 15px; border: 1px solid #3498db; border-radius: 5px; transition: background 0.3s, color 0.3s; }
        .web-link:hover { background: #3498db; color: white; }
        
        .traffic-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px; 
            margin-top: 20px;
        }
        .direction-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .direction-box img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 5px;
            margin: 15px 0;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>
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
                <li><a href="simulation.php"><i class="fas fa-play-circle"></i> Điều khiển Mô phỏng</a></li>
                <li><a href="traffic-lights.php"><i class="fas fa-traffic-light"></i> Đèn giao thông</a></li>
                <li><a href="nutgiaothong.php" class="active"><i class="fas fa-crosshairs"></i> Nút giao thông</a></li> 
                <li><a href="baocaoluuluong.php"><i class="fas fa-chart-bar"></i> Báo cáo lưu lượng</a></li> 
            </div>

            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ HỆ THỐNG</div>
                <li><a href="users.php"><i class="fas fa-user-cog"></i> Quản lý Users </a></li>
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

    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-crosshairs"></i> Tình hình Nút Giao thông</h1>
                <small>Xem ảnh trực tiếp được cập nhật sau mỗi 20 giây</small>
                <button id="refresh-btn" style="margin-left: 15px; padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fas fa-redo"></i> Làm mới
                </button>
            </div>
            <div class="header-right">
                <a href="../index.php" class="web-link">
                    <i class="fas fa-globe"></i> Về Trang Web
                </a>
                <div class="user-info">
                    <span>Xin chào, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <h2><i class="fas fa-camera"></i> Giám sát Trực tiếp (tự động cập nhật mỗi 20 giây)</h2>
        <div style="margin: 10px 0; color: #666; font-size: 14px;">
            <i class="fas fa-info-circle"></i> Đang sử dụng: 
            <span id="api-status">Đang kiểm tra kết nối...</span>
        </div>
        
        <div class="traffic-grid" id="traffic-display">
            <?php foreach ($directions_map as $direction => $name): ?>
                <div class="direction-box" id="box-<?php echo $direction; ?>">
                    <h3>Hướng <?php echo $name; ?></h3>
                    
                    <img id="img-<?php echo $direction; ?>" 
                         src="<?php echo $default_images[$direction]; ?>" 
                         alt="Ảnh giao thông Hướng <?php echo $name; ?>">
                    
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        <i class="far fa-clock"></i> 
                        Cập nhật: <span id="time-<?php echo $direction; ?>">--:--:--</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div> 
    
    <script>
        // DÙNG ĐƯỜNG DẪN TUYỆT ĐỐI ĐỂ TRÁNH LỖI
        const API_URL = 'http://localhost/giaothong/api_get_images.php';
        const DIRECTIONS = ['north', 'south', 'east', 'west'];
        let isUpdating = false;

        function updateTrafficData() {
            if (isUpdating) return;
            
            isUpdating = true;
            document.getElementById('api-status').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Đang cập nhật...';
            
            console.log('[' + new Date().toLocaleTimeString() + '] Gọi API:', API_URL);
            
            fetch(API_URL)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API Response:', data);
                    
                    if (data.status === 'success') {
                        document.getElementById('api-status').innerHTML = '<i class="fas fa-check-circle" style="color:#2ecc71"></i> Kết nối thành công';
                        
                        DIRECTIONS.forEach(direction => {
                            const imgElement = document.getElementById(`img-${direction}`);
                            const timeElement = document.getElementById(`time-${direction}`);
                            
                            if (imgElement && data.image_paths[direction]) {
                                // Tạo URL ảnh đầy đủ
                                let imagePath = data.image_paths[direction];
                                
                                // Đảm bảo có đường dẫn đúng
                                if (!imagePath.startsWith('http')) {
                                    imagePath = 'http://localhost/giaothong/' + imagePath;
                                }
                                
                                // Thêm timestamp để tránh cache
                                const timestamp = new Date().getTime();
                                const imageUrl = imagePath + (imagePath.includes('?') ? '&' : '?') + 't=' + timestamp;
                                
                                // Cập nhật ảnh
                                imgElement.src = imageUrl;
                                
                                // Cập nhật thời gian
                                if (timeElement) {
                                    const now = new Date();
                                    timeElement.textContent = now.toLocaleTimeString('vi-VN');
                                }
                            }
                        });
                        
                        showNotification('success', 'Đã cập nhật ảnh thành công!');
                        
                    } else {
                        document.getElementById('api-status').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#e74c3c"></i> API lỗi: ' + (data.message || 'Không rõ');
                        showNotification('error', 'Lỗi từ API: ' + (data.message || 'Không rõ'));
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    document.getElementById('api-status').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#e74c3c"></i> Lỗi kết nối: ' + error.message;
                    showNotification('error', 'Không thể kết nối đến API');
                })
                .finally(() => {
                    isUpdating = false;
                });
        }
        
        function showNotification(type, message) {
            // Tạo hoặc cập nhật notification
            let notification = document.getElementById('global-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'global-notification';
                notification.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 5px;
                    color: white;
                    font-weight: bold;
                    z-index: 1000;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                `;
                document.body.appendChild(notification);
            }
            
            if (type === 'success') {
                notification.style.background = '#2ecc71';
                notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            } else {
                notification.style.background = '#e74c3c';
                notification.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
            }
            
            // Tự động ẩn sau 3 giây
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 500);
                }
            }, 3000);
        }
        
        // Khởi động
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Trang đã tải. Vị trí:', window.location.href);
            
            // Kiểm tra API ngay
            updateTrafficData();
            
            // Nút refresh thủ công
            document.getElementById('refresh-btn').addEventListener('click', updateTrafficData);
            
            // Tự động cập nhật mỗi 20 giây
            setInterval(updateTrafficData, 20000);
            
            // Kiểm tra xem ảnh mặc định có tồn tại không
            DIRECTIONS.forEach(direction => {
                const img = document.getElementById(`img-${direction}`);
                if (img) {
                    img.onerror = function() {
                        this.src = 'data:image/svg+xml;base64,' + btoa(`
                            <svg width="400" height="300" xmlns="http://www.w3.org/2000/svg">
                                <rect width="100%" height="100%" fill="#f5f6fa"/>
                                <text x="50%" y="50%" font-family="Arial" font-size="16" text-anchor="middle" fill="#7f8c8d">
                                    Ảnh hướng ${direction.toUpperCase()} không tải được
                                </text>
                            </svg>
                        `);
                    };
                }
            });
        });
    </script>
</body>
</html>