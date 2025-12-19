<?php
header('Content-Type: application/json');

// --- 1. THIẾT LẬP ĐƯỜNG DẪN ---
$base_path = dirname(__FILE__); // D:\XAMPP\htdocs\giaothong\
$image_base_dir = $base_path . DIRECTORY_SEPARATOR . 'imageluuluong';

// Định nghĩa 4 thư mục hướng
$direction_folders = [
    'north' => $image_base_dir . DIRECTORY_SEPARATOR . 'bac',
    'south' => $image_base_dir . DIRECTORY_SEPARATOR . 'nam', 
    'east' => $image_base_dir . DIRECTORY_SEPARATOR . 'dong',
    'west' => $image_base_dir . DIRECTORY_SEPARATOR . 'tay'
];

// --- 2. LẤY ẢNH NGẪU NHIÊN TỪ MỖI FOLDER ---
$result_data = [];
$errors = [];

foreach ($direction_folders as $direction_key => $folder_path) {
    // Kiểm tra folder có tồn tại không
    if (!is_dir($folder_path)) {
        $errors[] = "Không tìm thấy thư mục: " . basename($folder_path);
        $result_data[$direction_key] = null;
        continue;
    }
    
    // Lấy danh sách file ảnh
    $all_files = scandir($folder_path);
    $image_files = [];
    
    $valid_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    foreach ($all_files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if (in_array(strtolower($extension), $valid_extensions)) {
            $image_files[] = $file;
        }
    }
    
    if (empty($image_files)) {
        $errors[] = "Không có ảnh trong thư mục: " . basename($folder_path);
        $result_data[$direction_key] = null;
        continue;
    }
    
    // Chọn ngẫu nhiên 1 ảnh
    $random_index = array_rand($image_files);
    $selected_image = $image_files[$random_index];
    
    // Tạo đường dẫn URL tương đối
    // Định dạng: imageluuluong/[bac|nam|dong|tay]/ten_anh.jpg
    $folder_name = basename($folder_path); // bac, nam, dong, tay
    $result_data[$direction_key] = 'imageluuluong/' . $folder_name . '/' . $selected_image;
}

// --- 3. TRẢ VỀ KẾT QUẢ ---
if (!empty($errors)) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Có lỗi xảy ra khi lấy ảnh",
        "errors" => $errors,
        "image_paths" => $result_data
    ]);
    exit();
}

// --- 4. LẤY DỮ LIỆU LƯU LƯỢNG XE (TÙY CHỌN) ---
$traffic_flows = [];
$database_file = $base_path . DIRECTORY_SEPARATOR . 'database.php';

if (file_exists($database_file)) {
    include $database_file;
    
    if (isset($conn) && function_exists('getCurrentTrafficFlows')) {
        $traffic_flows = getCurrentTrafficFlows($conn);
        $conn->close();
    }
}

// --- 5. TRẢ VỀ KẾT QUẢ ---
echo json_encode([
    "status" => "success", 
    "image_paths" => $result_data,
    "traffic_flows" => $traffic_flows,
    "debug_info" => [
        "base_path" => $base_path,
        "total_folders" => count($direction_folders)
    ]
]);
?>