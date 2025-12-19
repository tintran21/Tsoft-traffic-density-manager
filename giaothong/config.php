<?php
session_start();
include 'database.php';

// Đặt header để trả về JSON cho các yêu cầu AJAX
header('Content-Type: application/json');

// Lấy tất cả cấu hình và chế độ điều khiển
$configs = getSimulationConfig($conn);
$control_mode = getControlMode($conn);
$mode = $control_mode['mode'];
$auto_config = json_decode($control_mode['auto_config'], true);

// Giả lập lưu lượng xe đến hiện tại (Bạn có thể thay bằng dữ liệu cảm biến thực)
// Hiện tại ta dùng giá trị traffic_flow đã lưu trong database
$current_flows = getCurrentTrafficFlows($conn); 

// **********************************************
// LOGIC CHÍNH CỦA MÔ PHỎNG
// **********************************************

// --- 1. Tính toán thời gian đèn Xanh dựa trên Chế độ ---
$green_times = [];
if ($mode === 'auto') {
    // Logic cho chế độ TỰ ĐỘNG (Ví dụ: Tăng/giảm thời gian Xanh theo lưu lượng)
    $total_flow = array_sum($current_flows);
    
    foreach ($current_flows as $direction => $flow) {
        if ($total_flow > 0) {
            // Tỷ lệ lưu lượng (so với tổng)
            $flow_ratio = $flow / $total_flow; 
            
            // Tính toán Green Time mới: base + (tỷ lệ * độ dài khoảng tăng)
            $new_green_time = $auto_config['base_green_time'] + 
                              ($flow_ratio * ($auto_config['max_green_time'] - $auto_config['base_green_time']));
            
            // Đảm bảo không vượt quá Min/Max
            $new_green_time = max($auto_config['min_green_time'], min($auto_config['max_green_time'], $new_green_time));
            
            $green_times[$direction] = round($new_green_time);
        } else {
            // Nếu không có xe, dùng thời gian xanh cơ sở
            $green_times[$direction] = $auto_config['base_green_time'];
        }
    }
    
    // Cập nhật cấu hình đèn mới vào database (để giữ trạng thái)
    foreach ($green_times as $dir => $green_time) {
        // Red time sẽ được tính lại trong logic hiển thị
        saveSimulationConfig($conn, $dir, $green_time, $configs[$dir]['yellow_time'], 0, $configs[$dir]['traffic_flow']);
    }

} else {
    // Logic cho chế độ THỦ CÔNG: Sử dụng thời gian đã cấu hình
    foreach ($configs as $direction => $config) {
        $green_times[$direction] = $config['green_time'];
    }
}


// --- 2. XÁC ĐỊNH TRẠNG THÁI ĐÈN HIỆN TẠI ---
// Đây là logic giả lập chu kỳ đèn. 
// Trong ứng dụng thực tế, bạn sẽ cần một biến trạng thái toàn cục (hoặc một hàng trong DB)
// để lưu pha đèn hiện tại và thời gian còn lại.

// Giả sử chu kỳ đèn luôn là: (N-S Green) -> (N-S Yellow) -> (E-W Green) -> (E-W Yellow)
$cycle_duration = $green_times['north'] + $configs['north']['yellow_time'] + 
                  $green_times['east'] + $configs['east']['yellow_time'];

// Lấy thời gian hiện tại của chu kỳ (giả lập)
$time_in_cycle = time() % $cycle_duration; 

$output = [
    'mode' => $mode,
    'current_time_in_cycle' => $time_in_cycle,
    'total_cycle' => $cycle_duration,
    'lights' => []
];

$phase_time = 0;

// Phase 1: North/South GREEN
if ($time_in_cycle < $phase_time + $green_times['north']) {
    $output['lights']['north'] = ['status' => 'GREEN', 'time_left' => $phase_time + $green_times['north'] - $time_in_cycle];
    $output['lights']['south'] = ['status' => 'GREEN', 'time_left' => $phase_time + $green_times['north'] - $time_in_cycle];
    $output['lights']['east'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle]; // Red cho đến cuối chu kỳ
    $output['lights']['west'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
}

$phase_time += $green_times['north'];

// Phase 2: North/South YELLOW
if ($time_in_cycle >= $phase_time && $time_in_cycle < $phase_time + $configs['north']['yellow_time']) {
    $output['lights']['north'] = ['status' => 'YELLOW', 'time_left' => $phase_time + $configs['north']['yellow_time'] - $time_in_cycle];
    $output['lights']['south'] = ['status' => 'YELLOW', 'time_left' => $phase_time + $configs['north']['yellow_time'] - $time_in_cycle];
    $output['lights']['east'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
    $output['lights']['west'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
}

$phase_time += $configs['north']['yellow_time'];

// Phase 3: East/West GREEN
if ($time_in_cycle >= $phase_time && $time_in_cycle < $phase_time + $green_times['east']) {
    $output['lights']['north'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
    $output['lights']['south'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
    $output['lights']['east'] = ['status' => 'GREEN', 'time_left' => $phase_time + $green_times['east'] - $time_in_cycle];
    $output['lights']['west'] = ['status' => 'GREEN', 'time_left' => $phase_time + $green_times['east'] - $time_in_cycle];
}

$phase_time += $green_times['east'];

// Phase 4: East/West YELLOW
if ($time_in_cycle >= $phase_time && $time_in_cycle < $phase_time + $configs['east']['yellow_time']) {
    $output['lights']['north'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
    $output['lights']['south'] = ['status' => 'RED', 'time_left' => $cycle_duration - $time_in_cycle];
    $output['lights']['east'] = ['status' => 'YELLOW', 'time_left' => $phase_time + $configs['east']['yellow_time'] - $time_in_cycle];
    $output['lights']['west'] = ['status' => 'YELLOW', 'time_left' => $phase_time + $configs['east']['yellow_time'] - $time_in_cycle];
}


// --- 3. Ghi Log Lưu lượng (Giả định ghi log sau mỗi 30s) ---
// Thường việc ghi log sẽ được thực hiện bởi một cron job hoặc một cơ chế riêng biệt
// để không làm chậm request AJAX. Tuy nhiên, ta có thể giả định ở đây.
$traffic_ratio = 1.0; // Tỷ lệ giả định
saveTrafficLog($conn, $current_flows['north'], $current_flows['south'], $current_flows['east'], $current_flows['west'], $traffic_ratio);


// --- 4. Trả về kết quả JSON ---
echo json_encode($output);

?>