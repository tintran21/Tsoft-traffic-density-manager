<?php
session_start();
// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bao gồm file kết nối database
include '../database.php'; 
// Bao gồm lớp User (giả sử User.class.php nằm ở thư mục gốc)
include '../User.class.php'; 

// --- 1. Kiểm tra đăng nhập và quyền admin ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}
// --- Kết thúc kiểm tra ---

// Kiểm tra kết nối database
if (!$conn) {
    die("<div style='background: #f8d7da; padding: 20px; margin: 20px; border-radius: 5px;'>
        <h3>❌ Lỗi kết nối database!</h3>
        <p>Không thể kết nối đến database. Vui lòng kiểm tra file database.php</p>
    </div>");
}

// Khởi tạo đối tượng User
$user_manager = new User($conn); 

$message = "";
$message_type = "";
$current_action = "list"; // list | add | edit
$user_to_edit = null;

// --- 2. Xử lý Thêm/Sửa/Xóa (CRUD Operations) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý Thêm Users mới
    if (isset($_POST['add_user'])) {
        $user_manager->username = $_POST['username'];
        $user_manager->full_name = $_POST['full_name'];
        $user_manager->role = $_POST['role'];
        $user_manager->password = $_POST['password'];

        if (empty($user_manager->username) || empty($user_manager->password)) {
            $message = "Tên đăng nhập và Mật khẩu không được để trống!";
            $message_type = "error";
        } elseif ($user_manager->create()) {
            $message = "Thêm User <strong>{$user_manager->username}</strong> thành công!";
            $message_type = "success";
        } else {
            $message = "Lỗi: User <strong>{$user_manager->username}</strong> đã tồn tại hoặc có lỗi DB!";
            $message_type = "error";
        }
    }
    
    // Xử lý Cập nhật Users
    elseif (isset($_POST['edit_user'])) {
        $user_manager->id = $_POST['user_id'];
        $user_manager->full_name = $_POST['full_name'];
        $user_manager->role = $_POST['role'];
        $user_manager->password = $_POST['password']; // Có thể là chuỗi rỗng nếu không đổi

        if ($user_manager->update()) {
            $message = "Cập nhật User ID: <strong>{$user_manager->id}</strong> thành công!";
            $message_type = "success";
        } else {
            $message = "Lỗi: Không thể cập nhật user. Vui lòng kiểm tra lại.";
            $message_type = "error";
        }
    }
    
    // Xử lý Xóa Users
    elseif (isset($_POST['delete_user'])) {
        $user_manager->id = $_POST['user_id'];

        if ($user_manager->id == $_SESSION['user_id']) {
            $message = "Lỗi: Bạn không thể tự xóa tài khoản của mình!";
            $message_type = "error";
        } elseif ($user_manager->delete()) {
            $message = "Xóa User ID: <strong>{$user_manager->id}</strong> thành công!";
            $message_type = "success";
        } else {
            $message = "Lỗi: Không thể xóa user. Vui lòng kiểm tra lại.";
            $message_type = "error";
        }
    }
    
    // --- XỬ LÝ YÊU CẦU QUÊN MẬT KHẨU ---
    elseif (isset($_POST['process_password_request'])) {
        $request_id = intval($_POST['request_id']);
        $new_password = $_POST['new_password'];
        $notes = trim($_POST['notes'] ?? '');
        
        // Lấy thông tin yêu cầu
        $stmt = $conn->prepare("SELECT user_id, username, email FROM password_reset_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $request = $result->fetch_assoc();
            $user_id = $request['user_id'];
            $username = $request['username'];
            
            // Cập nhật mật khẩu cho user
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                // Cập nhật trạng thái yêu cầu
                $admin_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("UPDATE password_reset_requests SET 
                    status = 'processed', 
                    processed_by = ?, 
                    processed_time = NOW(),
                    notes = ?
                    WHERE id = ?");
                $stmt->bind_param("isi", $admin_id, $notes, $request_id);
                $stmt->execute();
                
                $message = "Đã cập nhật mật khẩu mới cho user <strong>$username</strong>!";
                $message_type = "success";
            }
        }
    }
    
    elseif (isset($_POST['cancel_password_request'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        
        if ($stmt->execute()) {
            $message = "Đã hủy yêu cầu.";
            $message_type = "success";
        }
    }
}

// --- 3. Xử lý Chuyển đổi giao diện (Get/Edit) ---
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'add') {
        $current_action = 'add';
    } elseif ($_GET['action'] === 'edit' && isset($_GET['id'])) {
        $user_manager->id = intval($_GET['id']);
        if ($user_manager->readOne()) {
            $user_to_edit = [
                'id' => $user_manager->id,
                'username' => $user_manager->username,
                'full_name' => $user_manager->full_name,
                'role' => $user_manager->role
            ];
            $current_action = 'edit';
        } else {
            $message = "Không tìm thấy User ID: <strong>{$user_manager->id}</strong>.";
            $message_type = "error";
            $current_action = 'list';
        }
    }
}

// --- 4. Lấy danh sách Users (cho action 'list') ---
$users_list = $user_manager->readAll();

// --- LẤY DANH SÁCH YÊU CẦU QUÊN MẬT KHẨU ---
$pending_requests = [];
$pending_count = 0;

// ĐẢM BẢO BẢNG password_reset_requests TỒN TẠI
$check_table = $conn->query("SHOW TABLES LIKE 'password_reset_requests'");
if ($check_table->num_rows == 0) {
    // Tạo bảng nếu chưa tồn tại
    $create_table_sql = "CREATE TABLE password_reset_requests (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        full_name VARCHAR(100),
        reset_token VARCHAR(64),
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'processed', 'cancelled') DEFAULT 'pending',
        processed_by INT(11) DEFAULT NULL,
        processed_time TIMESTAMP NULL DEFAULT NULL,
        notes TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($create_table_sql)) {
        $message = "Lỗi tạo bảng password_reset_requests: " . htmlspecialchars($conn->error);
        $message_type = "error";
    }
}

// Lấy danh sách yêu cầu chờ xử lý
$sql = "SELECT pr.*, u.full_name 
       FROM password_reset_requests pr 
       JOIN users u ON pr.user_id = u.id 
       WHERE pr.status = 'pending' 
       ORDER BY pr.id DESC 
       LIMIT 10";
       
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_requests[] = $row;
    }
    $pending_count = $result->num_rows;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Users - Admin</title>
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
        .content-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* CSS riêng của trang Users */
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .user-table th, .user-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .user-table th {
            background-color: #f2f2f2;
            color: #2c3e50;
        }
        .action-buttons button, .action-buttons a { 
            margin-right: 5px; 
        }
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            color: #34495e; 
        }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
            font-size: 1rem; 
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .success-message, .error-message { 
            padding: 12px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            font-weight: bold; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success-message { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .error-message { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }
        .debug-info h4 {
            margin-top: 0;
            color: #856404;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        .btn-success:hover {
            background: #27ae60;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-warning:hover {
            background: #d68910;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .form-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        /* CSS cho phần yêu cầu quên mật khẩu */
        .password-requests-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        .password-requests-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .requests-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .requests-table th, .requests-table td {
            border: 1px solid #ddd;
            padding: 10px;
            font-size: 14px;
        }
        .requests-table th {
            background-color: #f8f9fa;
            color: #495057;
        }
        .request-username {
            font-weight: bold;
            color: #2c3e50;
        }
        .request-time {
            color: #7f8c8d;
            font-size: 12px;
        }
        .no-requests {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            color: #6c757d;
        }
        .request-actions {
            display: flex;
            gap: 5px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 25px;
            border-radius: 10px;
            width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
        }
        .close-modal:hover {
            color: #e74c3c;
        }
        .password-generate-btn {
            margin-top: 5px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .password-generate-btn:hover {
            background: #e9ecef;
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
                <li><a href="nutgiaothong.php"><i class="fas fa-crosshairs"></i> Nút giao thông</a></li> 
                <li><a href="baocaoluuluong.php"><i class="fas fa-chart-bar"></i> Báo cáo lưu lượng</a></li> 
            </div>
        
            <div class="menu-section">
                <div class="menu-section-title">QUẢN LÝ HỆ THỐNG</div>
                <li><a href="users.php" class="active"><i class="fas fa-user-cog"></i> Quản lý Users</a></li>
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
                <h1><i class="fas fa-user-cog"></i> Quản lý Users</h1>
                <small>Thêm, sửa, xóa và quản lý quyền truy cập của người dùng</small>
            </div>
            <div class="header-right">
                <a href="../index.php" class="web-link">
                    <i class="fas fa-globe"></i> Về Trang Web
                </a>
                <?php if ($pending_count > 0): ?>
                <a href="#password-requests" class="web-link" style="background: #e74c3c; color: white; border-color: #e74c3c; margin-right: 10px;">
                    <i class="fas fa-key"></i> Yêu cầu MK 
                    <span style="background: white; color: #e74c3c; border-radius: 50%; padding: 2px 6px; font-size: 12px; margin-left: 5px;">
                        <?php echo $pending_count; ?>
                    </span>
                </a>
                <?php endif; ?>
                <div class="user-info">
                    <span>Xin chào, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <div class="user-avatar">
                        <?php echo isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'A'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="<?php echo $message_type; ?>-message">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> 
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- DEBUG INFO -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-info">
            <h4><i class="fas fa-bug"></i> Thông tin debug:</h4>
            <p><strong>Số yêu cầu pending:</strong> <?php echo $pending_count; ?></p>
            <p><strong>Tổng yêu cầu trong DB:</strong> 
                <?php 
                    $total_req = $conn->query("SELECT COUNT(*) as total FROM password_reset_requests");
                    echo $total_req->fetch_assoc()['total'] ?? 0;
                ?>
            </p>
            <p><strong>Yêu cầu pending:</strong></p>
            <pre><?php print_r($pending_requests); ?></pre>
        </div>
        <?php endif; ?>

        <div class="content-card">
            <?php if ($current_action === 'list'): ?>
                <h2>Danh sách Users</h2>
                <a href="users.php?action=add" class="btn btn-primary" style="margin-bottom: 20px;">
                    <i class="fas fa-plus"></i> Thêm User Mới
                </a>
                
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Họ và Tên</th>
                            <th>Quyền</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_list && $users_list->num_rows > 0): ?>
                            <?php while ($row = $users_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td>
                                        <?php if ($row['role'] === 'admin'): ?>
                                            <span style="color: red; font-weight: bold;">ADMIN</span>
                                        <?php else: ?>
                                            <span style="color: #3498db;">User thường</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="users.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Sửa
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa user này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm" <?php echo $row['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-trash"></i> Xóa
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Chưa có User nào trong hệ thống.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- PHẦN YÊU CẦU QUÊN MẬT KHẨU -->
                <div class="password-requests-section" id="password-requests">
                    <h3>
                        <i class="fas fa-key"></i> Yêu cầu Quên Mật khẩu
                        <?php if ($pending_count > 0): ?>
                            <span class="requests-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if ($pending_count > 0): ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th width="120">Thời gian</th>
                                    <th>Người dùng</th>
                                    <th>Email</th>
                                    <th>Trạng thái</th>
                                    <th width="150">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td class="request-time">
                                            <?php 
                                            // Xử lý tên cột timestamp
                                            $timestamp = '';
                                            if (isset($request['requested_at'])) {
                                                $timestamp = $request['requested_at'];
                                            } elseif (isset($request['request_time'])) {
                                                $timestamp = $request['request_time'];
                                            } else {
                                                $timestamp = 'now';
                                            }
                                            echo date('d/m/Y H:i', strtotime($timestamp)); 
                                            ?>
                                        </td>
                                        <td>
                                            <div class="request-username"><?php echo htmlspecialchars($request['username']); ?></div>
                                            <div style="font-size: 12px; color: #7f8c8d;"><?php echo htmlspecialchars($request['full_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                        <td>
                                            <?php 
                                            $status = $request['status'] ?? 'pending';
                                            if ($status === 'pending') {
                                                echo '<span style="color: #e67e22; font-weight: bold;">Chờ xử lý</span>';
                                            } elseif ($status === 'processed') {
                                                echo '<span style="color: #27ae60; font-weight: bold;">Đã xử lý</span>';
                                            } else {
                                                echo '<span style="color: #95a5a6;">Đã hủy</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="request-actions">
                                                <?php if ($status === 'pending'): ?>
                                                    <button onclick="openPasswordModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['username']); ?>')" 
                                                            class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Xử lý
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="cancel_password_request" class="btn btn-danger btn-sm"
                                                                onclick="return confirm('Hủy yêu cầu này?')">
                                                            <i class="fas fa-times"></i> Hủy
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: #7f8c8d; font-size: 12px;">Đã xử lý</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-requests">
                            <i class="fas fa-check-circle" style="color: #2ecc71; font-size: 48px; margin-bottom: 10px;"></i>
                            <p>Không có yêu cầu quên mật khẩu nào đang chờ xử lý.</p>
                            <p style="font-size: 12px; margin-top: 10px;">
                                <a href="?debug=1" style="color: #3498db;">Click để xem thông tin debug</a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            
            <?php elseif ($current_action === 'add'): ?>
                <h2><i class="fas fa-user-plus"></i> Thêm User Mới</h2>
                <form method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="form-group">
                        <label for="username">Tên đăng nhập (<span style="color: red;">*</span>)</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Họ và Tên</label>
                        <input type="text" id="full_name" name="full_name">
                    </div>
                    <div class="form-group">
                        <label for="role">Quyền (<span style="color: red;">*</span>)</label>
                        <select id="role" name="role" required>
                            <option value="user">User thường</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password">Mật khẩu (<span style="color: red;">*</span>)</label>
                        <input type="password" id="password" name="password" required>
                        <small style="color: #7f8c8d;">Ít nhất 6 ký tự</small>
                    </div>
                    <div class="form-buttons">
                        <a href="users.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Hủy</a>
                        <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Thêm User</button>
                    </div>
                </form>
                
            <?php elseif ($current_action === 'edit' && $user_to_edit): ?>
                <h2><i class="fas fa-user-edit"></i> Chỉnh sửa User: <?php echo htmlspecialchars($user_to_edit['username']); ?></h2>
                <form method="POST">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_to_edit['id']; ?>">
                    
                    <div class="form-group">
                        <label for="username_edit">Tên đăng nhập</label>
                        <input type="text" id="username_edit" name="username_display" value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" disabled>
                        <small>Không thể thay đổi tên đăng nhập.</small>
                    </div>
                    <div class="form-group">
                        <label for="full_name_edit">Họ và Tên</label>
                        <input type="text" id="full_name_edit" name="full_name" value="<?php echo htmlspecialchars($user_to_edit['full_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="role_edit">Quyền</label>
                        <select id="role_edit" name="role" required>
                            <option value="user" <?php echo $user_to_edit['role'] === 'user' ? 'selected' : ''; ?>>User thường</option>
                            <option value="admin" <?php echo $user_to_edit['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password_edit">Mật khẩu mới (Để trống nếu không đổi)</label>
                        <input type="password" id="password_edit" name="password">
                        <small>Nhập mật khẩu mới nếu muốn thay đổi.</small>
                    </div>
                    <div class="form-buttons">
                        <a href="users.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Hủy</a>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Lưu thay đổi</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal xử lý yêu cầu quên mật khẩu -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Xử lý Yêu cầu Quên Mật khẩu</h3>
                <button class="close-modal" onclick="closePasswordModal()">&times;</button>
            </div>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="process_password_request" value="1">
                
                <div class="form-group">
                    <label>Người dùng</label>
                    <input type="text" id="modalUsername" disabled style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới *</label>
                    <input type="text" id="new_password" name="new_password" required>
                    <button type="button" class="password-generate-btn" onclick="generatePassword()">
                        <i class="fas fa-redo"></i> Tạo mật khẩu ngẫu nhiên
                    </button>
                </div>
                
                <div class="form-group">
                    <label for="notes">Ghi chú (tùy chọn)</label>
                    <textarea id="notes" name="notes" placeholder="Ghi chú về việc xử lý yêu cầu này..."></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-warning" onclick="closePasswordModal()">
                        <i class="fas fa-times"></i> Hủy
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Xác nhận và đặt lại mật khẩu
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Hàm mở modal xử lý yêu cầu
        function openPasswordModal(requestId, username) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalUsername').value = username;
            document.getElementById('passwordModal').style.display = 'block';
            generatePassword(); // Tự động tạo mật khẩu mới
        }
        
        // Hàm đóng modal
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        // Hàm tạo mật khẩu ngẫu nhiên
        function generatePassword() {
            const length = 10;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let password = "";
            
            // Đảm bảo có ít nhất một chữ số và một ký tự đặc biệt
            password += charset.charAt(Math.floor(Math.random() * 26)); // Chữ thường
            password += charset.charAt(26 + Math.floor(Math.random() * 26)); // Chữ hoa
            password += charset.charAt(52 + Math.floor(Math.random() * 10)); // Số
            password += charset.charAt(62 + Math.floor(Math.random() * 8)); // Ký tự đặc biệt
            
            // Thêm các ký tự ngẫu nhiên cho đủ độ dài
            for (let i = 4; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            
            // Xáo trộn mật khẩu
            password = password.split('').sort(() => 0.5 - Math.random()).join('');
            
            document.getElementById('new_password').value = password;
        }
        
        // Đóng modal khi click bên ngoài
        window.onclick = function(event) {
            const modal = document.getElementById('passwordModal');
            if (event.target == modal) {
                closePasswordModal();
            }
        }
        
        // Tự động cuộn đến phần yêu cầu nếu có tham số URL
        window.onload = function() {
            if (window.location.hash === '#password-requests') {
                const element = document.getElementById('password-requests');
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth' });
                }
            }
        };
    </script>
</body>
</html>