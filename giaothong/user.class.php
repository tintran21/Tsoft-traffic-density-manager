<?php
class User {
    private $conn;
    public $id;
    public $username;
    public $password;
    public $full_name;
    public $email;
    public $phone;
    public $role;
    public $activation_code;
    public $is_active;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Tạo user mới
    public function create() {
        // Kiểm tra username đã tồn tại chưa
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("s", $this->username);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            return false; // Username đã tồn tại
        }
        
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, password, full_name, email, phone, role, activation_code, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        
        // Mặc định các giá trị nếu không có
        $activation_code = $this->activation_code ?? bin2hex(random_bytes(16));
        $is_active = $this->is_active ?? 0;
        $email = $this->email ?? '';
        $phone = $this->phone ?? '';
        $role = $this->role ?? 'user';
        
        $stmt->bind_param("sssssssi", 
            $this->username, 
            $hashed_password, 
            $this->full_name, 
            $email, 
            $phone, 
            $role, 
            $activation_code, 
            $is_active
        );
        
        if ($stmt->execute()) {
            $this->id = $stmt->insert_id;
            return true;
        }
        return false;
    }
    
    // Đọc thông tin user theo ID
    public function readOne() {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->username = $row['username'];
            $this->full_name = $row['full_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->role = $row['role'];
            $this->activation_code = $row['activation_code'];
            $this->is_active = $row['is_active'];
            return true;
        }
        return false;
    }
    
    // Đọc tất cả users
    public function readAll() {
        $sql = "SELECT id, username, full_name, email, phone, role, is_active, created_at FROM users ORDER BY id DESC";
        $result = $this->conn->query($sql);
        return $result;
    }
    
    // Cập nhật user
    public function update() {
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?";
        
        // Nếu có password mới
        if (!empty($this->password)) {
            $sql .= ", password = ?";
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($this->password)) {
            $stmt->bind_param("sssssi", 
                $this->full_name, 
                $this->email, 
                $this->phone, 
                $this->role, 
                $hashed_password, 
                $this->id
            );
        } else {
            $stmt->bind_param("ssssi", 
                $this->full_name, 
                $this->email, 
                $this->phone, 
                $this->role, 
                $this->id
            );
        }
        
        return $stmt->execute();
    }
    
    // Xóa user
    public function delete() {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->id);
        return $stmt->execute();
    }
    
    // Đăng nhập
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Cập nhật thông tin object
                $this->id = $user['id'];
                $this->username = $user['username'];
                $this->full_name = $user['full_name'];
                $this->email = $user['email'];
                $this->phone = $user['phone'];
                $this->role = $user['role'];
                $this->is_active = $user['is_active'];
                return true;
            }
        }
        return false;
    }
}
?>