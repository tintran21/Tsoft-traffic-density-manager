<?php
// Thiết lập header để nhận JSON và trả về JSON
header('Content-Type: application/json');

// --- KIỂM TRA PHƯƠNG THỨC VÀ DỮ LIỆU ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Chỉ chấp nhận phương thức POST."]);
    exit();
}

// Lấy và giải mã dữ liệu JSON từ Python
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Dữ liệu JSON không hợp lệ."]);
    exit();
}

// Lấy 4 giá trị lưu lượng từ dữ liệu gửi đến (đảm bảo là số nguyên)
$luu_luong_bac = (int) ($data['bac'] ?? 0);
$luu_luong_nam = (int) ($data['nam'] ?? 0);
$luu_luong_dong = (int) ($data['dong'] ?? 0);
$luu_luong_tay = (int) ($data['tay'] ?? 0);

// --- KẾT NỐI DATABASE ---
include 'database.php';

// Kiểm tra kết nối
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Không thể kết nối Database. Vui lòng kiểm tra file database.php."]);
    exit();
}

// --- CẬP NHẬT DATABASE ---
$directions = [
    'north' => $luu_luong_bac,
    'south' => $luu_luong_nam,
    'east' => $luu_luong_dong,
    'west' => $luu_luong_tay
];

$success = true;
$errors = [];

try {
    // 1. Cập nhật simulation_config
    $sql_update = "UPDATE simulation_config SET traffic_flow = ? WHERE direction = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if (!$stmt_update) {
        throw new Exception("Không thể chuẩn bị statement update: " . $conn->error);
    }
    
    foreach ($directions as $direction => $flow) {
        $stmt_update->bind_param("is", $flow, $direction);
        if (!$stmt_update->execute()) {
            $errors[] = "Lỗi cập nhật $direction: " . $stmt_update->error;
        }
        
        // 2. LƯU LỊCH SỬ VÀO traffic_history (QUAN TRỌNG CHO PHÂN TÍCH 5 PHÚT)
        saveTrafficHistory($conn, $direction, $flow);
    }
    
    $stmt_update->close();
    
    // 3. Lưu log tổng hợp
    $total_vehicles = array_sum($directions);
    $traffic_ratio = ($total_vehicles > 0) ? $total_vehicles / 4 : 1.00;
    
    if (function_exists('saveTrafficLog')) {
        saveTrafficLog($conn, $luu_luong_bac, $luu_luong_nam, $luu_luong_dong, $luu_luong_tay, $traffic_ratio);
    }
    
    // PHẢN HỒI THÀNH CÔNG
    if (empty($errors)) {
        echo json_encode([
            "status" => "success", 
            "message" => "Đã cập nhật lưu lượng và lưu lịch sử thành công.",
            "data_received" => $data,
            "historical_saved" => true,
            "analysis_available" => "Dữ liệu đã sẵn sàng cho phân tích 5 phút"
        ]);
    } else {
        echo json_encode([
            "status" => "partial", 
            "message" => "Cập nhật thành công một phần.",
            "errors" => $errors,
            "data_received" => $data
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Lỗi xử lý dữ liệu: " . $e->getMessage()
    ]);
    $success = false;
}

$conn->close();
?>