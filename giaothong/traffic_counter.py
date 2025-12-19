import os
import time
import requests
import random
from ultralytics import YOLO 
from pathlib import Path

# --- 1. Cấu hình ---

BASE_DIR = Path(__file__).resolve().parent 
IMAGE_BASE_DIR = BASE_DIR / 'imageluuluong'  # Thư mục gốc chứa 4 folder hướng

# Định nghĩa 4 thư mục con cho 4 hướng
DIRECTION_FOLDERS = {
    'bac': IMAGE_BASE_DIR / 'bac',
    'nam': IMAGE_BASE_DIR / 'nam',
    'dong': IMAGE_BASE_DIR / 'dong',
    'tay': IMAGE_BASE_DIR / 'tay'
}

API_URL = 'http://localhost/giaothong/api_luuluong.php' 
TIME_PER_IMAGE_SECONDS = 20 
DELAY_SECONDS = 20 
MULTIPLIER = 60 / TIME_PER_IMAGE_SECONDS

# Tải mô hình YOLO
try:
    model = YOLO(BASE_DIR / 'yolov8n.pt') 
except Exception as e:
    print(f"LỖI KHỞI TẠO YOLO: Không tải được yolov8n.pt. Vui lòng kiểm tra file: {e}")
    model = None 

# --- 2. Hàm Xử lý Ảnh & Đếm Xe ---
def count_vehicles(image_path):
    if not model:
        return random.randint(2, 15) 

    try:
        results = model(str(image_path), conf=0.5, verbose=False)
        vehicle_classes = [2, 3, 5, 7] 
        vehicle_count = 0
        for r in results:
            detected_classes = r.boxes.cls.tolist()
            for cls_id in detected_classes:
                if int(cls_id) in vehicle_classes:
                    vehicle_count += 1
        
        print(f"-> Đếm được {vehicle_count} xe trong ảnh: {os.path.basename(str(image_path))}")
        return vehicle_count
    
    except Exception as e:
        print(f"Lỗi khi xử lý ảnh {image_path}: {e}")
        return 0

# --- 3. Hàm lấy ảnh từ mỗi folder hướng ---
def get_random_image_from_each_direction():
    """
    Lấy 1 ảnh ngẫu nhiên từ mỗi folder hướng (bac, nam, dong, tay)
    """
    direction_images = {}
    
    for direction, folder_path in DIRECTION_FOLDERS.items():
        try:
            # Kiểm tra folder có tồn tại không
            if not folder_path.exists():
                print(f"LỖI: Không tìm thấy thư mục {direction}: {folder_path}")
                direction_images[direction] = None
                continue
                
            # Lấy tất cả file ảnh trong folder
            image_files = [p for p in folder_path.iterdir() 
                          if p.suffix.lower() in ['.jpg', '.jpeg', '.png']]
            
            if not image_files:
                print(f"CẢNH BÁO: Không có ảnh trong thư mục {direction}")
                direction_images[direction] = None
                continue
                
            # Chọn ngẫu nhiên 1 ảnh
            selected_image = random.choice(image_files)
            direction_images[direction] = selected_image
            
            print(f"Đã chọn ảnh cho hướng {direction}: {selected_image.name}")
            
        except Exception as e:
            print(f"Lỗi khi đọc thư mục {direction}: {e}")
            direction_images[direction] = None
    
    return direction_images

# --- 4. Hàm Chính ---
def main_traffic_loop():
    print(f"--- Bắt đầu chương trình đếm và gửi lưu lượng (Chu kỳ: {DELAY_SECONDS}s) ---")
    print(f"*** Lưu lượng được tính theo đơn vị: Xe/Phút (Hệ số nhân = {MULTIPLIER}) ***")
    
    # Kiểm tra cấu trúc thư mục
    print("\n--- Kiểm tra cấu trúc thư mục ---")
    for direction, folder in DIRECTION_FOLDERS.items():
        if folder.exists():
            image_count = len([p for p in folder.iterdir() if p.suffix.lower() in ['.jpg', '.jpeg', '.png']])
            print(f"✓ {direction}: {folder} - {image_count} ảnh")
        else:
            print(f"✗ {direction}: Thư mục không tồn tại!")
    
    while True:
        print("\n" + "="*50)
        print("--- Bắt đầu chu trình đếm xe ---")
        
        # 1. Lấy ảnh ngẫu nhiên từ mỗi folder hướng
        direction_images = get_random_image_from_each_direction()
        
        # Kiểm tra nếu có folder nào không có ảnh
        missing_directions = [d for d, img in direction_images.items() if img is None]
        if missing_directions:
            print(f"LỖI: Không thể lấy ảnh từ các hướng: {missing_directions}")
            print(f"Đang chờ {DELAY_SECONDS} giây...")
            time.sleep(DELAY_SECONDS)
            continue
        
        counted_data = {}
        
        # 2. Xử lý từng hướng
        for direction, image_path in direction_images.items():
            if image_path:
                num_vehicles = count_vehicles(image_path) 
                luu_luong_per_min = int(num_vehicles * MULTIPLIER) 
                counted_data[direction] = luu_luong_per_min
                
                print(f"Hướng {direction}: {image_path.name}")
                print(f"  -> {num_vehicles} xe/20s -> {luu_luong_per_min} xe/phút")
        
        print(f"\nTổng hợp dữ liệu lưu lượng (Xe/Phút): {counted_data}")

        # 3. Gửi API
        try:
            print("Đang gửi dữ liệu đến API...")
            response = requests.post(API_URL, json=counted_data, timeout=10)
            response.raise_for_status()
            response_data = response.json() 
            print(f"✓ Gửi API thành công. Kết quả: {response_data.get('status', 'unknown')}")
        
        except requests.exceptions.RequestException as e:
            print(f"✗ LỖI API: {e}")
        except Exception as e:
            print(f"✗ Lỗi xử lý phản hồi: {e}")

        # 4. Tạm dừng
        print(f"\nĐang tạm dừng {DELAY_SECONDS} giây...")
        time.sleep(DELAY_SECONDS)

if __name__ == "__main__":
    main_traffic_loop()