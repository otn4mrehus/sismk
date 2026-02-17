<?php
// ==================== KONFIGURASI DATABASE MYSQL 5.7 ====================
session_start();
header('Content-Type: text/html; charset=utf-8');

$db_host = '_HOST_';
$db_user = '_USER';
$db_pass = '_PASSWORD_';
$db_name = '_DB_';

$conn = new mysqli($db_host, $db_user, $db_pass);
if ($conn->connect_error) die("Koneksi database gagal: " . $conn->connect_error);
$conn->query("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4");
$conn->select_db($db_name);

// ==================== FUNGSI BANTU UPLOAD FOTO ====================
function uploadPhoto($file, $type, $userId) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowed)) return null;
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) return null;
    
    $tahun = date('Y');
    $bulan = date('m');
    $timestamp = date('Ymd_His');
    $namaFile = $userId . '_' . $type . '_' . $timestamp . '.' . $ext;
    
    $direktori = "uploads/foto/" . $type . "/" . $tahun . "/" . $bulan . "/";
    if (!is_dir($direktori)) {
        mkdir($direktori, 0777, true);
    }
    
    $path = $direktori . $namaFile;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $path;
    }
    return null;
}

// ==================== INISIALISASI TABEL & DATA DUMMY ====================
function initDatabase($conn) {
    // Users
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            role ENUM('admin','operator','guru') NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Categories
    $conn->query("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            code_prefix VARCHAR(10) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Brands
    $conn->query("
        CREATE TABLE IF NOT EXISTS brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            code_prefix VARCHAR(10) NOT NULL DEFAULT '',
            UNIQUE KEY unique_category_brand (category_id, name),
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Types
    $conn->query("
        CREATE TABLE IF NOT EXISTS types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            code_prefix VARCHAR(10) NOT NULL DEFAULT '',
            UNIQUE KEY unique_brand_type (brand_id, name),
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Rooms
    $conn->query("
        CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            description TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Items
    $conn->query("
        CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            brand_id INT NOT NULL,
            type_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            year INT,
            item_code VARCHAR(50) UNIQUE NOT NULL,
            qr_code VARCHAR(50) UNIQUE NOT NULL,
            status ENUM('available','borrowed','damaged','lost') DEFAULT 'available',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
            FOREIGN KEY (type_id) REFERENCES types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Transactions
    $conn->query("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT NOT NULL,
            room_id INT NOT NULL,
            transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('borrowed','returned','overdue') DEFAULT 'borrowed',
            type ENUM('borrow') DEFAULT 'borrow',
            borrow_signature TEXT,
            return_signature TEXT,
            borrow_photo VARCHAR(255) NULL,
            return_photo VARCHAR(255) NULL,
            borrow_approved_at DATETIME,
            return_approved_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (admin_id) REFERENCES users(id),
            FOREIGN KEY (room_id) REFERENCES rooms(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Cek dan tambahkan kolom notes jika belum ada
    $result = $conn->query("SHOW COLUMNS FROM transactions LIKE 'notes'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE transactions ADD COLUMN notes TEXT NULL AFTER return_approved_at");
    }

    // Cek dan tambahkan kolom borrow_code jika belum ada
    $result = $conn->query("SHOW COLUMNS FROM transactions LIKE 'borrow_code'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE transactions ADD COLUMN borrow_code VARCHAR(50) NULL AFTER notes");
    }

    // Transaction Items
    $conn->query("
        CREATE TABLE IF NOT EXISTS transaction_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NOT NULL,
            item_id INT NOT NULL,
            borrow_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            return_date DATETIME NULL,
            status ENUM('borrowed','returned') DEFAULT 'borrowed',
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tambahkan kolom return_signature dan return_photo ke transaction_items jika belum ada
    $result = $conn->query("SHOW COLUMNS FROM transaction_items LIKE 'return_signature'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE transaction_items ADD COLUMN return_signature TEXT NULL AFTER return_date");
    }
    $result = $conn->query("SHOW COLUMNS FROM transaction_items LIKE 'return_photo'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE transaction_items ADD COLUMN return_photo VARCHAR(255) NULL AFTER return_signature");
    }

    // ==================== DATA DUMMY BARU ====================
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM users");
    $row = $res->fetch_assoc();
    if ($row['cnt'] == 0) {
        // Insert Users (password semua '123')
        $conn->query("
            INSERT INTO users (name, role, email, password) VALUES
            ('admin', 'admin', 'admin@sekolah.sch.id', '123'),
            ('suhermanto', 'guru', 'suhermanto@sekolah.sch.id', '123'),
            ('syarif', 'guru', 'syarif@sekolah.sch.id', '123'),
            ('ruslan', 'guru', 'ruslan@sekolah.sch.id', '123'),
            ('sahidi', 'guru', 'sahidi@sekolah.sch.id', '123'),
            ('ube', 'guru', 'ube@sekolah.sch.id', '123'),
            ('empud', 'guru', 'empud@sekolah.sch.id', '123'),
            ('ahmadi', 'guru', 'ahmadi@sekolah.sch.id', '123'),
            ('kusnadi', 'guru', 'kusnadi@sekolah.sch.id', '123'),
            ('dedi', 'guru', 'dedi@sekolah.sch.id', '123')
        ");

        // Insert Categories
        $conn->query("
            INSERT INTO categories (name, description, code_prefix) VALUES
            ('laptop', 'Laptop dan aksesoris', 'LPT'),
            ('proyektor', 'Proyektor LCD', 'PRJ'),
            ('sound system', 'Peralatan audio', 'SND'),
            ('elektronik', 'Peralatan elektronik lainnya', 'ELK')
        ");

        // Insert Brands (dengan code_prefix)
        $conn->query("
            INSERT INTO brands (category_id, name, code_prefix) VALUES
            (2, 'Epson', 'EPS'),   -- proyektor
            (2, 'BenQ', 'BNQ'),    -- proyektor
            (1, 'HP', 'HP'),        -- laptop
            (1, 'Asus', 'ASS'),     -- laptop
            (1, 'Charger', 'CHG'),  -- laptop (charger)
            (4, 'Kabel Roll', 'KBL'), -- elektronik
            (3, 'Yamaha', 'YAM'),   -- sound system
            (3, 'Philips', 'PHI')   -- sound system
        ");

        // Insert Types (dengan nama dan code_prefix sesuai contoh)
        $conn->query("
            INSERT INTO types (brand_id, name, code_prefix) VALUES
            -- Epson
            (1, 'EPS-01', 'EPS01'),
            (1, 'EPS-02', 'EPS02'),
            (1, 'EPS-03', 'EPS03'),
            -- BenQ
            (2, 'BNQ-01', 'BNQ01'),
            (2, 'BNQ-02', 'BNQ02'),
            -- HP
            (3, 'HP-01', 'HP01'),
            (3, 'HP-02', 'HP02'),
            (3, 'HP-03', 'HP03'),
            -- Asus
            (4, 'ASS-01', 'ASS01'),
            (4, 'ASS-02', 'ASS02'),
            -- Charger
            (5, 'CHG-01', 'CHG01'),
            (5, 'CHG-02', 'CHG02'),
            -- Kabel Roll
            (6, 'KBL5M-01', 'KBL5M'),
            (6, 'KBL10M-01', 'KBL10M'),
            (6, 'KBL5M-02', 'KBL5M'),
            -- Yamaha
            (7, 'YAM-01', 'YAM01'),
            (7, 'YAM-02', 'YAM02'),
            -- Philips
            (8, 'PHI-01', 'PHI01'),
            (8, 'PHI-02', 'PHI02')
        ");

        // Insert Rooms (sama seperti sebelumnya)
        $conn->query("
            INSERT INTO rooms (name, description) VALUES
            ('Lab Komputer', 'Lab komputer'),
            ('Ruang Kelas A', 'Kelas A'),
            ('Aula', 'Aula'),
            ('Ruang Guru', 'Ruang guru'),
            ('Perpustakaan', 'Perpustakaan')
        ");

        // Insert Items
        // Kita buat beberapa item dengan status available
        $conn->query("
            INSERT INTO items (category_id, brand_id, type_id, name, year, item_code, qr_code, status) VALUES
            (2, 1, 1, 'Proyektor Epson EPS-01', 2023, 'PRJ-EPS-001', 'QR-PRJ-001', 'available'),
            (2, 1, 2, 'Proyektor Epson EPS-02', 2023, 'PRJ-EPS-002', 'QR-PRJ-002', 'available'),
            (2, 2, 4, 'Proyektor BenQ BNQ-01', 2023, 'PRJ-BNQ-001', 'QR-PRJ-003', 'available'),
            (1, 3, 7, 'Laptop HP HP-01', 2022, 'LPT-HP-001', 'QR-LPT-001', 'available'),
            (1, 3, 8, 'Laptop HP HP-02', 2022, 'LPT-HP-002', 'QR-LPT-002', 'borrowed'),
            (1, 4, 9, 'Laptop Asus ASS-01', 2023, 'LPT-ASS-001', 'QR-LPT-003', 'available'),
            (1, 5, 11, 'Charger CHG-01', 2023, 'LPT-CHG-001', 'QR-LPT-004', 'available'),
            (4, 6, 13, 'Kabel Roll 5 meter', 2024, 'ELK-KBL-001', 'QR-ELK-001', 'available'),
            (4, 6, 14, 'Kabel Roll 10 meter', 2024, 'ELK-KBL-002', 'QR-ELK-002', 'available'),
            (3, 7, 16, 'Sound System Yamaha YAM-01', 2023, 'SND-YAM-001', 'QR-SND-001', 'available'),
            (3, 8, 18, 'Sound System Philips PHI-01', 2023, 'SND-PHI-001', 'QR-SND-002', 'available'),
            (2, 1, 3, 'Proyektor Epson EPS-03', 2024, 'PRJ-EPS-003', 'QR-PRJ-004', 'available'),
            (1, 4, 10, 'Laptop Asus ASS-02', 2024, 'LPT-ASS-002', 'QR-LPT-005', 'available'),
            (1, 5, 12, 'Charger CHG-02', 2024, 'LPT-CHG-002', 'QR-LPT-006', 'available')
        ");

        // Insert Transactions (dengan borrow_code dummy)
        // Kita asumsikan id item yang diinsert di atas: 1-15
        $conn->query("
            INSERT INTO transactions (user_id, admin_id, room_id, status, type, borrow_signature, borrow_approved_at, return_signature, return_approved_at, borrow_code, transaction_date) VALUES
            (2, 1, 1, 'returned', 'borrow', 'dummy', NOW(), 'dummy', DATE_SUB(NOW(), INTERVAL 3 DAY), 'P_1_2-150226', DATE_SUB(NOW(), INTERVAL 3 DAY)),
            (3, 1, 2, 'borrowed', 'borrow', 'dummy', NOW(), NULL, NULL, 'P_2_3-150226', DATE_SUB(NOW(), INTERVAL 2 DAY)),
            (4, 1, 3, 'returned', 'borrow', 'dummy', NOW(), 'dummy', DATE_SUB(NOW(), INTERVAL 1 DAY), 'P_1_4-140226', DATE_SUB(NOW(), INTERVAL 1 DAY)),
            (5, 1, 4, 'borrowed', 'borrow', 'dummy', NOW(), NULL, NULL, 'P_3_5-160226', DATE_SUB(NOW(), INTERVAL 5 DAY)),
            (6, 1, 5, 'returned', 'borrow', 'dummy', NOW(), 'dummy', DATE_SUB(NOW(), INTERVAL 7 DAY), 'P_1_6-130226', DATE_SUB(NOW(), INTERVAL 7 DAY))
        ");

        // Insert Transaction Items
        $conn->query("
            INSERT INTO transaction_items (transaction_id, item_id, status, return_date) VALUES
            (1, 1, 'returned', DATE_SUB(NOW(), INTERVAL 3 DAY)),
            (1, 2, 'returned', DATE_SUB(NOW(), INTERVAL 3 DAY)),
            (2, 5, 'borrowed', NULL),
            (3, 10, 'returned', DATE_SUB(NOW(), INTERVAL 1 DAY)),
            (4, 13, 'borrowed', NULL),
            (4, 14, 'borrowed', NULL),
            (5, 8, 'returned', DATE_SUB(NOW(), INTERVAL 7 DAY)),
            (5, 9, 'returned', DATE_SUB(NOW(), INTERVAL 7 DAY))
        ");
    }
}
initDatabase($conn);

// ==================== PROSES LOGIN / LOGOUT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ? AND password = ?");
    $stmt->bind_param('ss', $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $login_error = 'Email atau password salah!';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ==================== REST API HANDLER ====================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function getInput() {
    if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
        return json_decode(file_get_contents('php://input'), true);
    } else {
        return $_POST;
    }
}

if ($action && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');

    // ----- GET CURRENT USER -----
    if ($action == 'getCurrentUser' && $method == 'GET') {
        echo json_encode([
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ]);
        exit;
    }

    $currentRole = $_SESSION['user_role'];
    $isAdmin = ($currentRole === 'admin');
    $isOperator = ($currentRole === 'operator');
    $isGuru = ($currentRole === 'guru');

    // ========== USER MANAGEMENT ==========
    if ($action == 'getUsers' && $method == 'GET') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $result = $conn->query("SELECT id, name, email, role FROM users ORDER BY id DESC");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    if ($action == 'addUser' && $method == 'POST') {
        // Izinkan admin dan operator (operator hanya boleh menambah guru)
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        if ($isOperator && $input['role'] != 'guru') {
            http_response_code(403); echo json_encode(['error' => 'Operator hanya dapat menambah guru']); exit;
        }
        if (!in_array($input['role'], ['admin','operator','guru'])) {
            http_response_code(400); echo json_encode(['error' => 'Role tidak valid']); exit;
        }
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $input['name'], $input['email'], $input['password'], $input['role']);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        exit;
    }
    if ($action == 'updateUser' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        if (!in_array($input['role'], ['admin','operator','guru'])) {
            http_response_code(400); echo json_encode(['error' => 'Role tidak valid']); exit;
        }
        if (empty($input['password'])) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param('sssi', $input['name'], $input['email'], $input['role'], $input['id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $input['name'], $input['email'], $input['password'], $input['role'], $input['id']);
        }
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action == 'deleteUser' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        if ($input['id'] == $_SESSION['user_id']) {
            http_response_code(400); echo json_encode(['error' => 'Tidak dapat menghapus akun sendiri']); exit;
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // ========== CATEGORIES ==========
    if ($action == 'getCategories' && $method == 'GET') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $result = $conn->query("SELECT * FROM categories ORDER BY name");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    if ($action == 'addCategory' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $input['name']), 0, 3));
        $stmt = $conn->prepare("INSERT INTO categories (name, description, code_prefix) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $input['name'], $input['description'], $prefix);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        exit;
    }
    if ($action == 'updateCategory' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssi', $input['name'], $input['description'], $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action == 'deleteCategory' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param('i', $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // ========== BRANDS ==========
    if ($action == 'getBrandsByCategory' && $method == 'GET') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $catId = $_GET['category_id'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM brands WHERE category_id = ? ORDER BY name");
        $stmt->bind_param('i', $catId);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    if ($action == 'getAllBrands' && $method == 'GET') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $result = $conn->query("SELECT * FROM brands ORDER BY name");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    if ($action == 'addBrand' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $input['name']), 0, 3));
        $stmt = $conn->prepare("INSERT INTO brands (category_id, name, code_prefix) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $input['category_id'], $input['name'], $prefix);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        exit;
    }
    if ($action == 'updateBrand' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("UPDATE brands SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $input['name'], $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action == 'deleteBrand' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->bind_param('i', $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // ========== TYPES ==========
    if ($action == 'getTypesByBrand' && $method == 'GET') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $brandId = $_GET['brand_id'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM types WHERE brand_id = ? ORDER BY name");
        $stmt->bind_param('i', $brandId);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    if ($action == 'addType' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $input['name']), 0, 3));
        $stmt = $conn->prepare("INSERT INTO types (brand_id, name, code_prefix) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $input['brand_id'], $input['name'], $prefix);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        exit;
    }
    if ($action == 'updateType' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("UPDATE types SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $input['name'], $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action == 'deleteType' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("DELETE FROM types WHERE id = ?");
        $stmt->bind_param('i', $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // ========== ROOMS ==========
    if ($action == 'getRooms' && $method == 'GET') {
        $result = $conn->query("SELECT * FROM rooms ORDER BY name");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    if ($action == 'addRoom' && $method == 'POST') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("INSERT INTO rooms (name, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $input['name'], $input['description']);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        exit;
    }

    // ========== ITEMS ==========
    if ($action == 'getItems' && $method == 'GET') {
        $filter_status = $_GET['status'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $brand_id = isset($_GET['brand_id']) ? intval($_GET['brand_id']) : 0;
        $sort = $_GET['sort'] ?? 'asc';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT items.*, 
                       categories.name as category_name, categories.code_prefix as cat_prefix,
                       brands.name as brand_name, brands.code_prefix as brand_prefix,
                       types.name as type_name, types.code_prefix as type_prefix
                FROM items 
                LEFT JOIN categories ON items.category_id = categories.id
                LEFT JOIN brands ON items.brand_id = brands.id
                LEFT JOIN types ON items.type_id = types.id
                WHERE 1=1";

        $countSql = "SELECT COUNT(*) as total FROM items WHERE 1=1";
        $params = [];
        $types = '';

        if ($filter_status != 'all') {
            $sql .= " AND items.status = ?";
            $countSql .= " AND items.status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }
        if ($category_id > 0) {
            $sql .= " AND items.category_id = ?";
            $countSql .= " AND items.category_id = ?";
            $params[] = $category_id;
            $types .= 'i';
        }
        if ($brand_id > 0) {
            $sql .= " AND items.brand_id = ?";
            $countSql .= " AND items.brand_id = ?";
            $params[] = $brand_id;
            $types .= 'i';
        }
        if (!empty($search)) {
            $sql .= " AND (items.name LIKE ? OR items.item_code LIKE ? OR brands.name LIKE ? OR categories.name LIKE ?)";
            $countSql .= " AND (items.name LIKE ? OR items.item_code LIKE ? OR brands.name LIKE ? OR categories.name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
            $types .= 'ssss';
        }

        $sql .= " ORDER BY items.name " . ($sort === 'asc' ? 'ASC' : 'DESC');

        $stmtCount = $conn->prepare($countSql);
        if (!empty($params)) $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result()->fetch_assoc();
        $totalItems = $totalResult['total'];

        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }

        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'items' => $items,
            'total' => $totalItems,
            'page' => $page,
            'limit' => $limit
        ]);
        exit;
    }

    if ($action == 'addItem' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $catId = $input['category_id'];
        $brandId = $input['brand_id'];
        $typeId = $input['type_id'];
        $name = $input['name'];
        $year = $input['year'];
        
        $cat = $conn->query("SELECT code_prefix FROM categories WHERE id = $catId")->fetch_assoc();
        $brand = $conn->query("SELECT code_prefix FROM brands WHERE id = $brandId")->fetch_assoc();
        $catPrefix = $cat['code_prefix'] ?? 'GEN';
        $brandPrefix = $brand['code_prefix'] ?? 'GEN';
        
        $stmt = $conn->prepare("SELECT COUNT(*) + 1 AS next FROM items WHERE category_id = ? AND brand_id = ?");
        $stmt->bind_param('ii', $catId, $brandId);
        $stmt->execute();
        $next = $stmt->get_result()->fetch_assoc()['next'];
        $itemCode = strtoupper($catPrefix . '-' . $brandPrefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT));
        
        $qr = 'QR-' . strtoupper(uniqid());
        
        $stmt = $conn->prepare("INSERT INTO items (category_id, brand_id, type_id, name, year, item_code, qr_code, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'available')");
        $stmt->bind_param('iiisiss', $catId, $brandId, $typeId, $name, $year, $itemCode, $qr);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'item_code' => $itemCode, 'qr_code' => $qr]);
        exit;
    }

    if ($action == 'updateItem' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("UPDATE items SET category_id = ?, brand_id = ?, type_id = ?, name = ?, year = ?, status = ? WHERE id = ?");
        $stmt->bind_param('iiisisi', $input['category_id'], $input['brand_id'], $input['type_id'], $input['name'], $input['year'], $input['status'], $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action == 'deleteItem' && $method == 'POST') {
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->bind_param('i', $input['id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // ----- SCAN ITEM -----
    if ($action == 'scanItem' && $method == 'GET') {
        $qr = $_GET['qr'] ?? '';
        $stmt = $conn->prepare("SELECT items.*, categories.name as category_name, brands.name as brand_name, types.name as type_name
                                FROM items 
                                LEFT JOIN categories ON items.category_id = categories.id 
                                LEFT JOIN brands ON items.brand_id = brands.id
                                LEFT JOIN types ON items.type_id = types.id
                                WHERE items.qr_code = ?");
        $stmt->bind_param('s', $qr);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        if ($item) {
            $stmt2 = $conn->prepare("SELECT transaction_items.*, users.name as borrower 
                                     FROM transaction_items 
                                     JOIN transactions ON transaction_items.transaction_id = transactions.id
                                     JOIN users ON transactions.user_id = users.id
                                     WHERE transaction_items.item_id = ? AND transaction_items.status = 'borrowed'");
            $stmt2->bind_param('i', $item['id']);
            $stmt2->execute();
            $item['current_loan'] = $stmt2->get_result()->fetch_assoc();
        }
        echo json_encode($item);
        exit;
    }

    // ----- GET GURU -----
    if ($action == 'getGuru' && $method == 'GET') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $result = $conn->query("SELECT id, name FROM users WHERE role = 'guru'");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    // ----- CHECKOUT (MODIFIED: generate borrow_code) -----
    if ($action == 'checkout' && $method == 'POST') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        
        $user_id = $_POST['user_id'];
        $room_id = $_POST['room_id'];
        $borrow_signature = $_POST['borrow_signature'];
        $items = json_decode($_POST['items'], true);
        $notes = $_POST['notes'] ?? null;
        
        $borrow_photo = null;
        if (isset($_FILES['borrow_photo']) && $_FILES['borrow_photo']['error'] == UPLOAD_ERR_OK) {
            $borrow_photo = uploadPhoto($_FILES['borrow_photo'], 'pinjam', $user_id);
        }

        // Generate borrow_code: P_urutan_ID-USER_DDMMYY
        // Hitung jumlah transaksi hari ini
        $today = date('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transactions WHERE DATE(transaction_date) = ?");
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
        $urutan = $cnt + 1;
        $ddmmyy = date('dmy');
        $borrow_code = "P_{$urutan}_{$user_id}-{$ddmmyy}";

        $conn->begin_transaction();
        try {
            $admin_id = $_SESSION['user_id'];
            // Insert dengan borrow_code
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, admin_id, room_id, status, type, borrow_signature, borrow_photo, borrow_approved_at, notes, borrow_code) 
                                    VALUES (?, ?, ?, 'borrowed', 'borrow', ?, ?, NOW(), ?, ?)");
            $stmt->bind_param('iiissss', $user_id, $admin_id, $room_id, $borrow_signature, $borrow_photo, $notes, $borrow_code);
            $stmt->execute();
            $transId = $stmt->insert_id;

            $insertItem = $conn->prepare("INSERT INTO transaction_items (transaction_id, item_id, status) VALUES (?, ?, 'borrowed')");
            $updateItem = $conn->prepare("UPDATE items SET status = 'borrowed' WHERE id = ?");

            foreach ($items as $itemId) {
                $insertItem->bind_param('ii', $transId, $itemId);
                $insertItem->execute();
                $updateItem->bind_param('i', $itemId);
                $updateItem->execute();
            }
            $conn->commit();
            echo json_encode(['success' => true, 'transaction_id' => $transId, 'borrow_code' => $borrow_code]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ----- RETURN ITEM -----
    if ($action == 'returnItem' && $method == 'POST') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        
        $item_id = $_POST['item_id'];
        $return_signature = $_POST['return_signature'];
        
        $return_photo = null;
        if (isset($_FILES['return_photo']) && $_FILES['return_photo']['error'] == UPLOAD_ERR_OK) {
            $stmt = $conn->prepare("SELECT user_id FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE ti.item_id = ? AND ti.status = 'borrowed' LIMIT 1");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $userData = $stmt->get_result()->fetch_assoc();
            if ($userData) {
                $return_photo = uploadPhoto($_FILES['return_photo'], 'kembali', $userData['user_id']);
            }
        }

        $conn->begin_transaction();
        try {
            // Cari transaction_items yang sedang dipinjam untuk item ini
            $stmt = $conn->prepare("SELECT ti.id, ti.transaction_id 
                                    FROM transaction_items ti 
                                    WHERE ti.item_id = ? AND ti.status = 'borrowed' LIMIT 1");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $ti = $stmt->get_result()->fetch_assoc();
            if (!$ti) throw new Exception('Item tidak sedang dipinjam');

            // Update transaction_items
            $upd = $conn->prepare("UPDATE transaction_items 
                                   SET status = 'returned', return_date = NOW(), return_signature = ?, return_photo = ? 
                                   WHERE id = ?");
            $upd->bind_param('ssi', $return_signature, $return_photo, $ti['id']);
            $upd->execute();

            $upd2 = $conn->prepare("UPDATE items SET status = 'available' WHERE id = ?");
            $upd2->bind_param('i', $item_id);
            $upd2->execute();

            // Periksa apakah semua item dalam transaksi sudah returned
            $stmt2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM transaction_items WHERE transaction_id = ? AND status = 'borrowed'");
            $stmt2->bind_param('i', $ti['transaction_id']);
            $stmt2->execute();
            $cnt = $stmt2->get_result()->fetch_assoc()['cnt'];
            if ($cnt == 0) {
                $upd4 = $conn->prepare("UPDATE transactions SET status = 'returned' WHERE id = ?");
                $upd4->bind_param('i', $ti['transaction_id']);
                $upd4->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ----- DASHBOARD STATS -----
    if ($action == 'dashboard' && $method == 'GET') {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['user_role'];
        $stats = [];

        $res = $conn->query("SELECT COUNT(*) AS cnt FROM items");
        $stats['total_items'] = $res->fetch_assoc()['cnt'];
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM items WHERE status = 'available'");
        $stats['available_items'] = $res->fetch_assoc()['cnt'];
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM items WHERE status = 'borrowed'");
        $stats['borrowed_items'] = $res->fetch_assoc()['cnt'];

        if ($role == 'admin' || $role == 'operator') {
            $res = $conn->query("SELECT COUNT(*) AS cnt FROM transactions WHERE DATE(transaction_date) = CURDATE()");
            $stats['today_transactions'] = $res->fetch_assoc()['cnt'];
            $res = $conn->query("SELECT COUNT(*) AS cnt FROM transaction_items WHERE status = 'borrowed'");
            $stats['active_loans'] = $res->fetch_assoc()['cnt'];
        } else {
            $stmt = $conn->prepare("SELECT items.*, transaction_items.borrow_date, rooms.name as room_name, brands.name as brand_name, types.name as type_name
                                    FROM transaction_items 
                                    JOIN transactions ON transaction_items.transaction_id = transactions.id
                                    JOIN items ON transaction_items.item_id = items.id
                                    JOIN rooms ON transactions.room_id = rooms.id
                                    LEFT JOIN brands ON items.brand_id = brands.id
                                    LEFT JOIN types ON items.type_id = types.id
                                    WHERE transactions.user_id = ? AND transaction_items.status = 'borrowed'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stats['my_borrowed_items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $stmt2 = $conn->prepare("SELECT items.name, brands.name as brand_name, types.name as type_name, transaction_items.borrow_date, transaction_items.return_date, rooms.name as room_name
                                     FROM transaction_items 
                                     JOIN transactions ON transaction_items.transaction_id = transactions.id
                                     JOIN items ON transaction_items.item_id = items.id
                                     JOIN rooms ON transactions.room_id = rooms.id
                                     LEFT JOIN brands ON items.brand_id = brands.id
                                     LEFT JOIN types ON items.type_id = types.id
                                     WHERE transactions.user_id = ? AND transaction_items.status = 'returned'
                                     ORDER BY transaction_items.return_date DESC LIMIT 10");
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $stats['history'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        echo json_encode($stats);
        exit;
    }

    // ========== GET TRANSACTIONS (include borrow_code) ==========
    if ($action == 'getTransactions' && $method == 'GET') {
        $filter_status = $_GET['status'] ?? 'all';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'operator') {
            $sql = "
                SELECT t.id, t.transaction_date, t.status, t.borrow_code,
                       u.name as borrower, a.name as admin,
                       r.name as room_name,
                       COUNT(ti.id) as total_items,
                       SUM(CASE WHEN ti.status = 'returned' THEN 1 ELSE 0 END) as returned_items,
                       t.borrow_signature, t.return_signature,
                       t.borrow_photo, t.return_photo,
                       t.borrow_approved_at, t.return_approved_at,
                       t.notes
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                JOIN users a ON t.admin_id = a.id
                JOIN rooms r ON t.room_id = r.id
                LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
                WHERE 1=1
            ";
            $params = [];
            $types = '';

            if ($filter_status != 'all') {
                $sql .= " AND t.status = ?";
                $params[] = $filter_status;
                $types .= 's';
            }
            if (!empty($date_from)) {
                $sql .= " AND DATE(t.transaction_date) >= ?";
                $params[] = $date_from;
                $types .= 's';
            }
            if (!empty($date_to)) {
                $sql .= " AND DATE(t.transaction_date) <= ?";
                $params[] = $date_to;
                $types .= 's';
            }
            if ($user_id_filter > 0) {
                $sql .= " AND t.user_id = ?";
                $params[] = $user_id_filter;
                $types .= 'i';
            }

            $sql .= " GROUP BY t.id ORDER BY t.transaction_date DESC LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            // untuk guru
            $sql = "
                SELECT t.id, t.transaction_date, t.status, t.borrow_code,
                       r.name as room_name,
                       COUNT(ti.id) as total_items,
                       SUM(CASE WHEN ti.status = 'returned' THEN 1 ELSE 0 END) as returned_items,
                       t.borrow_signature, t.return_signature,
                       t.borrow_photo, t.return_photo,
                       t.borrow_approved_at, t.return_approved_at,
                       t.notes
                FROM transactions t
                LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
                JOIN rooms r ON t.room_id = r.id
                WHERE t.user_id = ?
            ";
            $params = [$_SESSION['user_id']];
            $types = 'i';

            if ($filter_status != 'all') {
                $sql .= " AND t.status = ?";
                $params[] = $filter_status;
                $types .= 's';
            }
            if (!empty($date_from)) {
                $sql .= " AND DATE(t.transaction_date) >= ?";
                $params[] = $date_from;
                $types .= 's';
            }
            if (!empty($date_to)) {
                $sql .= " AND DATE(t.transaction_date) <= ?";
                $params[] = $date_to;
                $types .= 's';
            }

            $sql .= " GROUP BY t.id ORDER BY t.transaction_date DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        }
        exit;
    }

    // ========== GET FULL TRANSACTIONS (with items, pagination, filters) ==========
    if ($action == 'getFullTransactions' && $method == 'GET') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        // Query untuk mengambil transaksi dengan item-itemnya
        $sql = "
            SELECT 
                t.id, t.transaction_date, t.status, t.borrow_code, t.user_id, t.admin_id, t.room_id,
                u.name as borrower,
                a.name as admin,
                r.name as room_name,
                ti.id as item_id,
                ti.item_id as item_id_ref,
                ti.status as item_status,
                ti.borrow_date,
                ti.return_date,
                i.item_code,
                i.name as item_name,
                ti.return_signature,
                ti.return_photo
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            JOIN users a ON t.admin_id = a.id
            JOIN rooms r ON t.room_id = r.id
            LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
            LEFT JOIN items i ON ti.item_id = i.id
            WHERE 1=1
        ";
        $countSql = "SELECT COUNT(DISTINCT t.id) as total FROM transactions t WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($date_from)) {
            $sql .= " AND DATE(t.transaction_date) >= ?";
            $countSql .= " AND DATE(t.transaction_date) >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        if (!empty($date_to)) {
            $sql .= " AND DATE(t.transaction_date) <= ?";
            $countSql .= " AND DATE(t.transaction_date) <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        if ($room_id > 0) {
            $sql .= " AND t.room_id = ?";
            $countSql .= " AND t.room_id = ?";
            $params[] = $room_id;
            $types .= 'i';
        }
        if ($user_id > 0) {
            $sql .= " AND t.user_id = ?";
            $countSql .= " AND t.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }

        // Hitung total untuk pagination
        $stmtCount = $conn->prepare($countSql);
        if (!empty($params)) $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result()->fetch_assoc();
        $totalTransactions = $totalResult['total'];

        // Tambah order by dan limit
        $sql .= " ORDER BY t.transaction_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Kelompokkan per transaksi
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transId = $row['id'];
            if (!isset($transactions[$transId])) {
                $transactions[$transId] = [
                    'id' => $transId,
                    'transaction_date' => $row['transaction_date'],
                    'status' => $row['status'],
                    'borrow_code' => $row['borrow_code'],
                    'borrower' => $row['borrower'],
                    'admin' => $row['admin'],
                    'room_name' => $row['room_name'],
                    'items' => []
                ];
            }
            if ($row['item_id']) {
                $transactions[$transId]['items'][] = [
                    'item_id' => $row['item_id_ref'],
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'status' => $row['item_status'],
                    'borrow_date' => $row['borrow_date'],
                    'return_date' => $row['return_date'],
                    'return_signature' => $row['return_signature'],
                    'return_photo' => $row['return_photo']
                ];
            }
        }

        echo json_encode([
            'transactions' => array_values($transactions),
            'total' => $totalTransactions,
            'page' => $page,
            'limit' => $limit
        ]);
        exit;
    }

    // ========== GET TRANSACTION DETAIL ==========
    if ($action == 'getTransactionDetail' && $method == 'GET') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $transId = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("SELECT t.*, u.name as borrower_name, r.name as room_name FROM transactions t JOIN users u ON t.user_id = u.id JOIN rooms r ON t.room_id = r.id WHERE t.id = ?");
        $stmt->bind_param('i', $transId);
        $stmt->execute();
        $trans = $stmt->get_result()->fetch_assoc();
        if (!$trans) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
        
        // Ambil item beserta return_signature dan return_photo dari transaction_items
        $stmt2 = $conn->prepare("SELECT ti.*, i.name as item_name, i.item_code, i.qr_code, i.status as item_status 
                                 FROM transaction_items ti 
                                 JOIN items i ON ti.item_id = i.id 
                                 WHERE ti.transaction_id = ?");
        $stmt2->bind_param('i', $transId);
        $stmt2->execute();
        $items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $trans['items'] = $items;
        echo json_encode($trans);
        exit;
    }

    // ========== UPDATE TRANSACTION (FULL) ==========
    if ($action == 'updateTransaction' && $method == 'POST') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $trans_id = $input['id'];
        $new_user_id = $input['user_id'];
        $new_room_id = $input['room_id'];
        $new_transaction_date = $input['transaction_date'];
        $new_items = $input['items']; // array of item_id
        $new_notes = $input['notes'] ?? null;

        $conn->begin_transaction();
        try {
            // Ambil item lama
            $stmt = $conn->prepare("SELECT item_id, status FROM transaction_items WHERE transaction_id = ?");
            $stmt->bind_param('i', $trans_id);
            $stmt->execute();
            $old_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $old_item_ids = array_column($old_items, 'item_id');

            // Tentukan item yang dihapus (ada di old tapi tidak di new)
            $items_to_remove = array_diff($old_item_ids, $new_items);
            // Tentukan item yang ditambah (ada di new tapi tidak di old)
            $items_to_add = array_diff($new_items, $old_item_ids);

            // Kembalikan status item yang dihapus menjadi available (jika sedang borrowed di transaksi ini)
            foreach ($items_to_remove as $item_id) {
                // Cek apakah item sedang borrowed di transaksi ini
                $upd = $conn->prepare("UPDATE items SET status = 'available' WHERE id = ? AND status = 'borrowed'");
                $upd->bind_param('i', $item_id);
                $upd->execute();
                // Hapus dari transaction_items
                $del = $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = ? AND item_id = ?");
                $del->bind_param('ii', $trans_id, $item_id);
                $del->execute();
            }

            // Tambah item baru
            foreach ($items_to_add as $item_id) {
                // Cek apakah item tersedia
                $check = $conn->prepare("SELECT status FROM items WHERE id = ?");
                $check->bind_param('i', $item_id);
                $check->execute();
                $item_status = $check->get_result()->fetch_assoc()['status'];
                if ($item_status != 'available') {
                    throw new Exception("Item ID $item_id tidak tersedia");
                }
                // Update status item menjadi borrowed
                $upd = $conn->prepare("UPDATE items SET status = 'borrowed' WHERE id = ?");
                $upd->bind_param('i', $item_id);
                $upd->execute();
                // Insert ke transaction_items dengan status borrowed
                $ins = $conn->prepare("INSERT INTO transaction_items (transaction_id, item_id, status, borrow_date) VALUES (?, ?, 'borrowed', NOW())");
                $ins->bind_param('ii', $trans_id, $item_id);
                $ins->execute();
            }

            // Update data transaksi (user_id, room_id, transaction_date, notes)
            $upd_trans = $conn->prepare("UPDATE transactions SET user_id = ?, room_id = ?, transaction_date = ?, notes = ? WHERE id = ?");
            $upd_trans->bind_param('iissi', $new_user_id, $new_room_id, $new_transaction_date, $new_notes, $trans_id);
            $upd_trans->execute();

            // Hitung ulang status transaksi: jika semua item returned, ubah status transaksi jadi returned, else borrowed
            $stmt2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM transaction_items WHERE transaction_id = ? AND status = 'borrowed'");
            $stmt2->bind_param('i', $trans_id);
            $stmt2->execute();
            $cnt_borrowed = $stmt2->get_result()->fetch_assoc()['cnt'];
            $new_status = ($cnt_borrowed == 0) ? 'returned' : 'borrowed';
            $upd_status = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
            $upd_status->bind_param('si', $new_status, $trans_id);
            $upd_status->execute();

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ========== DELETE TRANSACTION ==========
    if ($action == 'deleteTransaction' && $method == 'POST') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $input = getInput();
        $trans_id = $input['id'];
        
        $conn->begin_transaction();
        try {
            // Update items yang terkait menjadi available jika masih borrowed
            $upd = $conn->prepare("UPDATE items SET status = 'available' WHERE id IN (SELECT item_id FROM transaction_items WHERE transaction_id = ? AND status = 'borrowed')");
            $upd->bind_param('i', $trans_id);
            $upd->execute();
            
            // Hapus transaction_items (ON DELETE CASCADE, jadi tidak perlu manual)
            // Hapus transaksi
            $del = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $del->bind_param('i', $trans_id);
            $del->execute();
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ----- GET REPORT -----
    if ($action == 'getReport' && $method == 'GET') {
        if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        
        $sql = "
            SELECT 
                ti.id,
                u.name as peminjam,
                DATE(t.transaction_date) as tanggal,
                i.item_code,
                i.name as item_name,
                t.borrow_signature,
                ti.return_signature,
                t.borrow_photo,
                ti.return_photo,
                ti.status as item_status,
                ti.return_date
            FROM transaction_items ti
            JOIN transactions t ON ti.transaction_id = t.id
            JOIN users u ON t.user_id = u.id
            JOIN items i ON ti.item_id = i.id
            WHERE MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?
            ORDER BY t.transaction_date ASC, ti.id ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $paraf_pinjam = (!empty($row['borrow_signature']) && $row['borrow_signature'] != 'dummy') || !empty($row['borrow_photo']) ? '✓' : '-';
            $paraf_kembali = ($row['item_status'] == 'returned' && (!empty($row['return_signature']) && $row['return_signature'] != 'dummy') || !empty($row['return_photo'])) ? '✓' : '-';
            
            $data[] = [
                'no' => $no++,
                'peminjam' => $row['peminjam'],
                'tanggal' => $row['tanggal'],
                'kode' => $row['item_code'],
                'item' => $row['item_name'],
                'paraf_pinjam' => $paraf_pinjam,
                'paraf_kembali' => $paraf_kembali,
                'keterangan' => $row['item_status'] == 'returned' ? 'Dikembalikan' : 'Dipinjam'
            ];
        }
        echo json_encode(['data' => $data, 'month' => $month, 'year' => $year]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
    exit;
}

// ==================== TAMPILAN LOGIN ATAU SPA ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title>Sistem Inventaris & Peminjaman QR - Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow-x: hidden; }
        [v-cloak] { display: none; }
        .modal { backdrop-filter: blur(4px); }
        .signature-canvas { border: 1px solid #ccc; background: #fff; width: 100%; height: 200px; }
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background-color: #2563eb; color: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex; justify-content: space-around;
            padding: 0.5rem 0; z-index: 1000;
            border-top: 1px solid #1d4ed8; overflow-x: auto;
        }
        .bottom-nav a {
            display: flex; flex-direction: column; align-items: center;
            color: #ffffff; font-size: 0.75rem; transition: all 0.2s;
            text-decoration: none; flex: 0 0 auto; min-width: 4rem;
            text-align: center; padding: 0.25rem 0.5rem;
        }
        .bottom-nav a.active { color: #bfdbfe; font-weight: 600; }
        .bottom-nav i { font-size: 1.25rem; margin-bottom: 0.1rem; }
        .bottom-nav a:hover { background-color: #1d4ed8; }
        /* Padding bottom untuk konten utama - ditingkatkan agar tidak tertutup bottom nav */
        .content-with-bottom-nav { padding-bottom: 7rem; }
        .flex-1.container { position: relative; z-index: 1; min-height: calc(100vh - 120px); }
        .modal { z-index: 2000 !important; }
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; background: white; padding: 20px; }
            .no-print { display: none; }
        }
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .item-link { color: #2563eb; cursor: pointer; text-decoration: underline; }
        .item-link:hover { color: #1d4ed8; }
    </style>
</head>
<body class="bg-gray-100">
    <?php if (!isset($_SESSION['user_id'])): ?>
    <!-- HALAMAN LOGIN -->
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <div class="text-center mb-6">
                <i class="fas fa-qrcode text-5xl text-blue-600"></i>
                <h1 class="text-2xl font-bold mt-2">Sistem Peminjaman</h1>
                <p class="text-gray-600">Silakan login</p>
            </div>
            <?php if (isset($login_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $login_error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 transition">Login</button>
            </form>
            <div class="mt-4 text-sm text-gray-600">
                <p><strong>Demo Akun:</strong></p>
                <p>Admin: admin@sekolah.sch.id / 123</p>
                <p>Guru: suhermanto@sekolah.sch.id / 123</p>
                <p>Guru: syarif@sekolah.sch.id / 123</p>
                <p>Guru: ruslan@sekolah.sch.id / 123</p>
                <p>Guru: sahidi@sekolah.sch.id / 123</p>
                <p>Guru: ube@sekolah.sch.id / 123</p>
                <p>Guru: empud@sekolah.sch.id / 123</p>
                <p>Guru: ahmadi@sekolah.sch.id / 123</p>
                <p>Guru: kusnadi@sekolah.sch.id / 123</p>
                <p>Guru: dedi@sekolah.sch.id / 123</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- APLIKASI SPA -->
    <div id="app" class="min-h-screen flex flex-col" v-cloak>
        <!-- Navbar Atas -->
        <nav class="bg-blue-600 text-white shadow-lg">
            <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                <h1 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-qrcode"></i> <span>QR-Pinjam Sekolah</span></h1>
                <div class="flex items-center gap-4">
                    <!-- Dropdown Master (hanya untuk admin) -->
                    <div v-if="isAdmin" class="relative">
                        <button @click="showMasterMenu = !showMasterMenu" class="flex items-center gap-1 bg-blue-700 hover:bg-blue-800 px-3 py-1.5 rounded text-sm">
                            <i class="fas fa-cog"></i> Master <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div v-if="showMasterMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 text-gray-800">
                            <a href="#" @click.prevent="setActiveMenu('categories'); showMasterMenu = false" class="block px-4 py-2 text-sm hover:bg-gray-100">Kategori</a>
                            <a href="#" @click.prevent="setActiveMenu('users'); showMasterMenu = false" class="block px-4 py-2 text-sm hover:bg-gray-100">User</a>
                        </div>
                    </div>

                    <!-- Profil User -->
                    <span class="text-sm hidden sm:inline"><i class="fas fa-user"></i> {{ user?.name }} ({{ user?.role }})</span>
                    <span class="text-sm sm:hidden"><i class="fas fa-user"></i> {{ user?.name }}</span>

                    <a href="?logout" class="bg-red-500 hover:bg-red-600 px-3 py-1.5 rounded text-sm"><i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">Logout</span></a>
                </div>
            </div>
        </nav>

        <!-- Konten Utama -->
        <main class="flex-1 container mx-auto p-4 content-with-bottom-nav">
            <component :is="currentView"></component>
        </main>

        <!-- Bottom Navigation -->
        <div class="bottom-nav">
            <a v-for="menu in filteredMenus" :key="menu.id" href="#" @click.prevent="setActiveMenu(menu.id)" :class="{ 'active': activeMenu == menu.id }">
                <i :class="menu.icon"></i><span>{{ menu.name }}</span>
            </a>
        </div>

        <!-- Modal QR Scanner (z-60 agar di atas modal lainnya) -->
        <div v-if="showScanner" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-60 p-4" @click.self="stopScannerAndClose">
            <div class="bg-white rounded-lg max-w-lg w-full p-5">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-lg">Scan QR Code Peralatan</h3>
                    <button @click="stopScannerAndClose" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                </div>
                <div id="qr-reader" style="width:100%;"></div>
                <p class="text-sm text-gray-500 mt-2 text-center">Tempatkan QR code dalam area kamera</p>
            </div>
        </div>

        <!-- Modal QR Code Display -->
        <div v-if="showQRModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showQRModal = false">
            <div class="bg-white rounded-lg p-6 max-w-sm w-full text-center">
                <h3 class="font-bold text-lg mb-3">QR Code Item</h3>
                <div class="flex justify-center mb-3"><canvas id="qrCanvas" width="200" height="200"></canvas></div>
                <p class="text-sm">{{ selectedItem?.name }}</p>
                <p class="text-xs text-gray-500">{{ selectedItem?.qr_code }}</p>
                <button @click="showQRModal = false" class="mt-4 px-4 py-2 bg-gray-200 rounded">Tutup</button>
            </div>
        </div>

        <!-- Modal Tanda Tangan -->
        <div v-if="showSignatureModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="closeSignatureModal">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6">
                <h3 class="font-bold text-lg mb-3">{{ signatureTitle }}</h3>
                <canvas ref="signatureCanvas" class="signature-canvas" width="600" height="200"></canvas>
                <div class="flex justify-between mt-4">
                    <button @click="clearSignature" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"><i class="fas fa-eraser"></i> Hapus</button>
                    <div class="space-x-2">
                        <button @click="saveSignature" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"><i class="fas fa-check"></i> Simpan</button>
                        <button @click="closeSignatureModal" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Batal</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kategori -->
        <div v-if="showEditCategoryModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showEditCategoryModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Edit Kategori</h3>
                <div class="space-y-3">
                    <input v-model="editCategoryData.name" placeholder="Nama Kategori" class="border rounded px-3 py-2 w-full">
                    <input v-model="editCategoryData.description" placeholder="Deskripsi" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showEditCategoryModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="updateCategory" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Merk -->
        <div v-if="showEditBrandModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showEditBrandModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Edit Merk</h3>
                <div class="space-y-3">
                    <input v-model="editBrandData.name" placeholder="Nama Merk" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showEditBrandModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="updateBrand" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Tipe -->
        <div v-if="showEditTypeModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showEditTypeModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Edit Tipe</h3>
                <div class="space-y-3">
                    <input v-model="editTypeData.name" placeholder="Nama Tipe" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showEditTypeModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="updateType" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Item -->
        <div v-if="showEditItemModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showEditItemModal = false">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6">
                <h3 class="font-bold text-lg mb-4">Edit Item</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="flex gap-2 items-center">
                        <select v-model="editItemData.category_id" @change="onEditCategoryChange" class="border rounded px-3 py-2 flex-1">
                            <option value="">Pilih Kategori</option>
                            <option v-for="cat in categories" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <button @click="openAddCategoryModal('edit')" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 text-sm" title="Tambah Kategori Baru"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="flex gap-2 items-center">
                        <select v-model="editItemData.brand_id" @change="onEditBrandChange" class="border rounded px-3 py-2 flex-1">
                            <option value="">Pilih Merk</option>
                            <option v-for="brand in brands" :value="brand.id">{{ brand.name }}</option>
                        </select>
                        <button @click="openAddBrandModal('edit', editItemData.category_id)" :disabled="!editItemData.category_id" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 disabled:bg-gray-400 text-sm" title="Tambah Merk Baru"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="flex gap-2 items-center">
                        <select v-model="editItemData.type_id" class="border rounded px-3 py-2 flex-1">
                            <option value="">Pilih Tipe</option>
                            <option v-for="type in types" :value="type.id">{{ type.name }}</option>
                        </select>
                        <button @click="openAddTypeModal('edit', editItemData.brand_id)" :disabled="!editItemData.brand_id" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 disabled:bg-gray-400 text-sm" title="Tambah Tipe Baru"><i class="fas fa-plus"></i></button>
                    </div>
                    <input v-model="editItemData.name" placeholder="Nama Peralatan (Deskripsi)" class="border rounded px-3 py-2 col-span-2">
                    <select v-model="editItemData.year" class="border rounded px-3 py-2">
                        <option value="">Pilih Tahun</option>
                        <option value="2021">2021</option><option value="2022">2022</option><option value="2023">2023</option><option value="2025">2025</option><option value="2026">2026</option>
                    </select>
                    <select v-model="editItemData.status" class="border rounded px-3 py-2">
                        <option value="available">Tersedia</option><option value="borrowed">Dipinjam</option><option value="damaged">Rusak</option><option value="lost">Hilang</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="showEditItemModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                    <button @click="updateItem" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Kategori Cepat -->
        <div v-if="showQuickCategoryModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showQuickCategoryModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Tambah Kategori Baru</h3>
                <div class="space-y-3">
                    <input v-model="quickCategory.name" placeholder="Nama Kategori" class="border rounded px-3 py-2 w-full">
                    <input v-model="quickCategory.description" placeholder="Deskripsi" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showQuickCategoryModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveQuickCategory" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Merk Cepat -->
        <div v-if="showQuickBrandModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showQuickBrandModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Tambah Merk Baru untuk {{ selectedCategoryName }}</h3>
                <div class="space-y-3">
                    <input v-model="quickBrand.name" placeholder="Nama Merk" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showQuickBrandModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveQuickBrand" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Tipe Cepat -->
        <div v-if="showQuickTypeModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showQuickTypeModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Tambah Tipe Baru untuk {{ selectedBrandName }}</h3>
                <div class="space-y-3">
                    <input v-model="quickType.name" placeholder="Nama Tipe" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showQuickTypeModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveQuickType" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Ruang Cepat -->
        <div v-if="showQuickRoomModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showQuickRoomModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Tambah Ruang Baru</h3>
                <div class="space-y-3">
                    <input v-model="quickRoom.name" placeholder="Nama Ruang" class="border rounded px-3 py-2 w-full">
                    <input v-model="quickRoom.description" placeholder="Deskripsi (opsional)" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showQuickRoomModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveQuickRoom" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Guru Cepat -->
        <div v-if="showAddGuruModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showAddGuruModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">Tambah Guru Baru</h3>
                <div class="space-y-3">
                    <input v-model="newGuru.name" placeholder="Nama Lengkap" class="border rounded px-3 py-2 w-full">
                    <input v-model="newGuru.email" type="email" placeholder="Email" class="border rounded px-3 py-2 w-full">
                    <input v-model="newGuru.password" type="password" placeholder="Password" class="border rounded px-3 py-2 w-full">
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showAddGuruModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveGuru" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah/Edit User -->
        <div v-if="showUserModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showUserModal = false">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="font-bold text-lg mb-4">{{ userModalTitle }}</h3>
                <div class="space-y-3">
                    <input v-model="userForm.name" placeholder="Nama Lengkap" class="border rounded px-3 py-2 w-full">
                    <input v-model="userForm.email" type="email" placeholder="Email" class="border rounded px-3 py-2 w-full">
                    <input v-model="userForm.password" type="password" placeholder="Password (kosongkan jika tidak diubah)" class="border rounded px-3 py-2 w-full">
                    <select v-model="userForm.role" class="border rounded px-3 py-2 w-full">
                        <option value="admin">Admin</option><option value="operator">Operator</option><option value="guru">Guru</option>
                    </select>
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showUserModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveUser" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tampil Foto -->
        <div v-if="showPhotoModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showPhotoModal = false">
            <div class="bg-white rounded-lg p-6 max-w-md w-full text-center">
                <h3 class="font-bold text-lg mb-3">{{ photoTitle }}</h3>
                <img :src="photoData" class="border max-w-full h-auto" />
                <button @click="showPhotoModal = false" class="mt-4 px-4 py-2 bg-gray-200 rounded">Tutup</button>
            </div>
        </div>

        <!-- Modal Edit Transaksi Detail -->
        <div v-if="showEditTransactionDetailModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showEditTransactionDetailModal = false">
            <div class="bg-white rounded-lg max-w-3xl w-full p-6">
                <h3 class="font-bold text-lg mb-4">Edit Transaksi #{{ editTransactionData.id }}</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Peminjam</label>
                            <select v-model="editTransactionData.user_id" class="border rounded px-3 py-2 w-full">
                                <option v-for="g in guruList" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ruang</label>
                            <select v-model="editTransactionData.room_id" class="border rounded px-3 py-2 w-full">
                                <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Transaksi</label>
                            <input type="datetime-local" v-model="editTransactionData.transaction_date" class="border rounded px-3 py-2 w-full">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Daftar Item</label>
                        <div class="space-y-2 max-h-60 overflow-y-auto border p-2 rounded">
                            <div v-for="item in editTransactionData.items" :key="item.item_id" class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                <div>
                                    <span class="font-medium">{{ item.item_name }}</span> <span class="text-xs text-gray-500">{{ item.item_code }}</span>
                                </div>
                                <button @click="removeItemFromTransaction(item.item_id)" class="text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button>
                            </div>
                            <div v-if="editTransactionData.items.length === 0" class="text-gray-500 italic">Tidak ada item</div>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <button @click="scanAndAddToTransaction" class="bg-blue-600 text-white px-3 py-1 rounded text-sm"><i class="fas fa-camera"></i> Scan QR</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea v-model="editTransactionData.notes" rows="2" class="border rounded px-3 py-2 w-full" placeholder="Catatan (opsional)"></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="showEditTransactionDetailModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                        <button @click="saveTransactionDetail" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus Transaksi -->
        <div v-if="showDeleteTransactionModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showDeleteTransactionModal = false">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="font-bold text-lg mb-4">Hapus Transaksi</h3>
                <p class="mb-4">Apakah Anda yakin ingin menghapus transaksi ini? Semua item akan dikembalikan ke status tersedia.</p>
                <div class="flex justify-end gap-2">
                    <button @click="showDeleteTransactionModal = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
                    <button @click="confirmDeleteTransaction" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Ya, Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp, ref, computed, onMounted, markRaw, watch } = Vue;

        const App = {
            setup() {
                // ---------- STATE ----------
                const user = ref(null);
                const activeMenu = ref('dashboard');
                const showScanner = ref(false);
                const scannerInstance = ref(null);
                const cart = ref([]);
                const guruList = ref([]);
                const rooms = ref([]);
                const showQRModal = ref(false);
                const selectedItem = ref(null);
                const showSignatureModal = ref(false);
                const signatureTitle = ref('');
                const signatureResolve = ref(null);
                const signatureCanvas = ref(null);
                let signaturePadInstance = null;
                const showPhotoModal = ref(false);
                const photoData = ref('');
                const photoTitle = ref('');
                // Flag untuk mengetahui apakah scanner dibuka dari modal Edit Transaksi
                const isScanningFromEdit = ref(false);
                // State untuk menu master dropdown
                const showMasterMenu = ref(false);
                // State untuk modal tambah guru
                const showAddGuruModal = ref(false);
                const newGuru = ref({ name: '', email: '', password: '' });

                // Edit Modals
                const showEditCategoryModal = ref(false);
                const editCategoryData = ref({ id: null, name: '', description: '' });
                const showEditBrandModal = ref(false);
                const editBrandData = ref({ id: null, name: '' });
                const showEditTypeModal = ref(false);
                const editTypeData = ref({ id: null, name: '' });
                const showEditItemModal = ref(false);
                const editItemData = ref({ id: null, category_id: '', brand_id: '', type_id: '', name: '', year: '', status: '' });
                
                // Quick Add Modals
                const showQuickCategoryModal = ref(false);
                const quickCategory = ref({ name: '', description: '' });
                const quickCategoryContext = ref('add');
                const showQuickBrandModal = ref(false);
                const quickBrand = ref({ name: '' });
                const quickBrandContext = ref({ mode: 'add', category_id: null });
                const showQuickTypeModal = ref(false);
                const quickType = ref({ name: '' });
                const quickTypeContext = ref({ mode: 'add', brand_id: null });
                // Quick Add Room
                const showQuickRoomModal = ref(false);
                const quickRoom = ref({ name: '', description: '' });

                // Data lists
                const categories = ref([]);
                const brands = ref([]);
                const types = ref([]);
                const selectedCategoryName = ref('');
                const selectedBrandName = ref('');

                // User Management
                const showUserModal = ref(false);
                const userModalTitle = ref('Tambah User');
                const userForm = ref({ id: null, name: '', email: '', password: '', role: 'guru' });
                const users = ref([]);
                const loadingUsers = ref(false);

                // File upload
                const borrowPhotoFile = ref(null);
                const returnPhotoFile = ref(null);

                // State untuk form tambah item
                const newItem = ref({ category_id: '', brand_id: '', type_id: '', name: '', year: '' });

                // State untuk modal edit transaksi detail
                const showEditTransactionDetailModal = ref(false);
                const editTransactionData = ref({
                    id: null,
                    user_id: '',
                    room_id: '',
                    transaction_date: '',
                    items: [],
                    notes: ''
                });
                const showDeleteTransactionModal = ref(false);
                const selectedTransactionId = ref(null);

                // Menu definitions (Kategori dan User dihapus dari bottom nav)
                const menus = {
                    admin: [
                        { id: 'dashboard', name: 'Dashboard', icon: 'fas fa-home' },
                        { id: 'items', name: 'Inventaris', icon: 'fas fa-boxes' },
                        { id: 'borrow', name: 'Pinjam', icon: 'fas fa-camera' },
                        { id: 'return', name: 'Kembali', icon: 'fas fa-undo-alt' },
                        { id: 'transactions', name: 'Riwayat', icon: 'fas fa-history' },
                        { id: 'report', name: 'Laporan', icon: 'fas fa-print' }
                    ],
                    operator: [
                        { id: 'dashboard', name: 'Dashboard', icon: 'fas fa-home' },
                        { id: 'items', name: 'Inventaris', icon: 'fas fa-boxes' },
                        { id: 'borrow', name: 'Pinjam', icon: 'fas fa-camera' },
                        { id: 'return', name: 'Kembali', icon: 'fas fa-undo-alt' },
                        { id: 'transactions', name: 'Riwayat', icon: 'fas fa-history' },
                        { id: 'report', name: 'Laporan', icon: 'fas fa-print' }
                    ],
                    guru: [
                        { id: 'dashboard', name: 'Dashboard', icon: 'fas fa-home' },
                        { id: 'items', name: 'Peralatan', icon: 'fas fa-list' },
                        { id: 'transactions', name: 'Peminjamanku', icon: 'fas fa-book-open' }
                    ]
                };

                const filteredMenus = computed(() => menus[user.value?.role] || menus.guru);
                const isAdmin = computed(() => user.value?.role === 'admin');
                const isOperator = computed(() => user.value?.role === 'operator');

                // Current view
                const currentView = computed(() => {
                    switch(activeMenu.value) {
                        case 'dashboard': return markRaw(Dashboard);
                        case 'items': return markRaw(ItemList);
                        case 'categories': return markRaw(CategoryBrand);
                        case 'users': return markRaw(UserManagement);
                        case 'borrow': return markRaw(BorrowProcess);
                        case 'return': return markRaw(ReturnProcess);
                        case 'transactions': return markRaw(TransactionHistory);
                        case 'report': return markRaw(Report);
                        default: return markRaw(Dashboard);
                    }
                });

                // ---------- API METHODS ----------
                async function fetchCurrentUser() {
                    const res = await fetch('?action=getCurrentUser');
                    user.value = await res.json();
                }

                async function loadGuruList() {
                    if (!isAdmin.value && !isOperator.value) return;
                    const res = await fetch('?action=getGuru');
                    guruList.value = await res.json();
                }

                async function loadRooms() {
                    const res = await fetch('?action=getRooms');
                    rooms.value = await res.json();
                }

                async function loadCategories() {
                    if (!isAdmin.value) return;
                    const res = await fetch('?action=getCategories');
                    categories.value = await res.json();
                }

                async function loadBrandsByCategory(catId) {
                    if (!isAdmin.value) return;
                    if (!catId) { brands.value = []; return; }
                    const res = await fetch(`?action=getBrandsByCategory&category_id=${catId}`);
                    brands.value = await res.json();
                }

                async function loadTypesByBrand(brandId) {
                    if (!isAdmin.value) return;
                    if (!brandId) { types.value = []; return; }
                    const res = await fetch(`?action=getTypesByBrand&brand_id=${brandId}`);
                    types.value = await res.json();
                }

                async function loadAllBrands() {
                    if (!isAdmin.value) return;
                    const res = await fetch('?action=getAllBrands');
                    brands.value = await res.json();
                }

                function setActiveMenu(menuId) {
                    activeMenu.value = menuId;
                }

                // ---------- USER MANAGEMENT ----------
                async function fetchUsers() {
                    if (!isAdmin.value) return;
                    loadingUsers.value = true;
                    const res = await fetch('?action=getUsers');
                    users.value = await res.json();
                    loadingUsers.value = false;
                }

                function openAddUser() {
                    userModalTitle.value = 'Tambah User';
                    userForm.value = { id: null, name: '', email: '', password: '', role: 'guru' };
                    showUserModal.value = true;
                }

                function openEditUser(u) {
                    userModalTitle.value = 'Edit User';
                    userForm.value = { id: u.id, name: u.name, email: u.email, password: '', role: u.role };
                    showUserModal.value = true;
                }

                async function saveUser() {
                    if (!userForm.value.name || !userForm.value.email) { alert('Nama dan email harus diisi'); return; }
                    if (!userForm.value.id && !userForm.value.password) { alert('Password harus diisi untuk user baru'); return; }
                    const url = userForm.value.id ? '?action=updateUser' : '?action=addUser';
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(userForm.value)
                    });
                    const result = await res.json();
                    if (result.success) { showUserModal.value = false; fetchUsers(); } else alert('Gagal menyimpan user');
                }

                async function deleteUser(id) {
                    if (!confirm('Yakin hapus user ini?')) return;
                    if (id == user.value?.id) { alert('Tidak dapat menghapus akun sendiri'); return; }
                    const res = await fetch('?action=deleteUser', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    if ((await res.json()).success) fetchUsers(); else alert('Gagal menghapus user');
                }

                // ---------- FUNGSI TAMBAH GURU CEPAT ----------
                function openAddGuruModal() {
                    newGuru.value = { name: '', email: '', password: '' };
                    showAddGuruModal.value = true;
                }

                async function saveGuru() {
                    if (!newGuru.value.name || !newGuru.value.email || !newGuru.value.password) {
                        alert('Nama, email, dan password harus diisi');
                        return;
                    }
                    const payload = {
                        name: newGuru.value.name,
                        email: newGuru.value.email,
                        password: newGuru.value.password,
                        role: 'guru'
                    };
                    const res = await fetch('?action=addUser', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert('Guru berhasil ditambahkan');
                        showAddGuruModal.value = false;
                        await loadGuruList(); // refresh daftar guru
                    } else {
                        alert('Gagal: ' + (result.error || 'Unknown error'));
                    }
                }

                // ---------- QR SCANNER ----------
                function startScanner(callback) {
                    showScanner.value = true;
                    setTimeout(() => {
                        const html5QrcodeScanner = new Html5Qrcode("qr-reader");
                        scannerInstance.value = html5QrcodeScanner;
                        html5QrcodeScanner.start(
                            { facingMode: "environment" },
                            { fps: 10, qrbox: { width: 250, height: 250 } },
                            (decodedText) => {
                                // Hentikan scanner
                                html5QrcodeScanner.stop().then(() => {
                                    scannerInstance.value = null;
                                    showScanner.value = false;
                                    // Panggil callback dengan hasil scan
                                    callback(decodedText);
                                    // Jika scanner dibuka dari edit transaksi, setelah scan berhasil, buka kembali modal edit
                                    if (isScanningFromEdit.value) {
                                        showEditTransactionDetailModal.value = true;
                                        isScanningFromEdit.value = false;
                                    }
                                }).catch(e => console.log(e));
                            },
                            (error) => {}
                        );
                    }, 500);
                }

                function stopScannerAndClose() {
                    if (scannerInstance.value) {
                        scannerInstance.value.stop().then(() => {
                            scannerInstance.value = null;
                            showScanner.value = false;
                            // Jika scanner dibuka dari edit transaksi, buka kembali modal edit
                            if (isScanningFromEdit.value) {
                                showEditTransactionDetailModal.value = true;
                                isScanningFromEdit.value = false;
                            }
                        }).catch(e => console.log(e));
                    } else {
                        showScanner.value = false;
                        if (isScanningFromEdit.value) {
                            showEditTransactionDetailModal.value = true;
                            isScanningFromEdit.value = false;
                        }
                    }
                }

                // ---------- QR DISPLAY ----------
                function showQR(item) {
                    selectedItem.value = item;
                    showQRModal.value = true;
                    setTimeout(() => {
                        const canvas = document.getElementById('qrCanvas');
                        if (canvas) QRCode.toCanvas(canvas, item.qr_code, { width: 200 }, (err) => { if (err) console.error(err); });
                    }, 100);
                }

                // ---------- SIGNATURE ----------
                function openSignatureModal(title) {
                    return new Promise((resolve) => {
                        signatureTitle.value = title;
                        showSignatureModal.value = true;
                        signatureResolve.value = resolve;
                        setTimeout(() => {
                            const canvas = signatureCanvas.value;
                            if (canvas) {
                                canvas.width = canvas.clientWidth;
                                canvas.height = canvas.clientHeight;
                                signaturePadInstance = new SignaturePad(canvas);
                                signaturePadInstance.clear();
                            }
                        }, 100);
                    });
                }

                function closeSignatureModal() {
                    showSignatureModal.value = false;
                    if (signatureResolve.value) signatureResolve.value(null);
                }

                function clearSignature() { if (signaturePadInstance) signaturePadInstance.clear(); }

                function saveSignature() {
                    if (signaturePadInstance && !signaturePadInstance.isEmpty()) {
                        const dataURL = signaturePadInstance.toDataURL('image/png');
                        showSignatureModal.value = false;
                        signatureResolve.value(dataURL);
                    } else alert('Silakan tanda tangan terlebih dahulu.');
                }

                // ---------- PHOTO VIEW ----------
                function viewPhoto(photoPath, title) {
                    photoData.value = photoPath;
                    photoTitle.value = title;
                    showPhotoModal.value = true;
                }

                // ---------- CART ----------
                function addToCart(item) { if (!cart.value.find(i => i.id === item.id)) cart.value.push({ ...item }); }
                function removeFromCart(itemId) { cart.value = cart.value.filter(i => i.id !== itemId); }
                function clearCart() { cart.value = []; }

                // ---------- EDIT FUNCTIONS ----------
                function openEditCategory(category) {
                    editCategoryData.value = { id: category.id, name: category.name, description: category.description };
                    showEditCategoryModal.value = true;
                }
                async function updateCategory() {
                    await fetch('?action=updateCategory', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(editCategoryData.value)
                    });
                    showEditCategoryModal.value = false;
                    if (refreshCategories) refreshCategories();
                }

                function openEditBrand(brand) {
                    editBrandData.value = { id: brand.id, name: brand.name };
                    showEditBrandModal.value = true;
                }
                async function updateBrand() {
                    await fetch('?action=updateBrand', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(editBrandData.value)
                    });
                    showEditBrandModal.value = false;
                    if (refreshBrands) refreshBrands();
                }

                function openEditType(type) {
                    editTypeData.value = { id: type.id, name: type.name };
                    showEditTypeModal.value = true;
                }
                async function updateType() {
                    await fetch('?action=updateType', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(editTypeData.value)
                    });
                    showEditTypeModal.value = false;
                    if (refreshTypes) refreshTypes();
                }

                function openEditItem(item) {
                    editItemData.value = { 
                        id: item.id, 
                        category_id: item.category_id, 
                        brand_id: item.brand_id, 
                        type_id: item.type_id, 
                        name: item.name, 
                        year: item.year, 
                        status: item.status 
                    };
                    loadCategories();
                    if (item.category_id) loadBrandsByCategory(item.category_id);
                    if (item.brand_id) loadTypesByBrand(item.brand_id);
                    showEditItemModal.value = true;
                }
                async function onEditCategoryChange() {
                    editItemData.value.brand_id = '';
                    editItemData.value.type_id = '';
                    await loadBrandsByCategory(editItemData.value.category_id);
                }
                async function onEditBrandChange() {
                    editItemData.value.type_id = '';
                    await loadTypesByBrand(editItemData.value.brand_id);
                }
                async function updateItem() {
                    await fetch('?action=updateItem', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(editItemData.value)
                    });
                    showEditItemModal.value = false;
                    if (refreshItems) refreshItems();
                }

                // ---------- QUICK ADD ----------
                function openAddCategoryModal(context = 'add') {
                    quickCategory.value = { name: '', description: '' };
                    quickCategoryContext.value = context;
                    showQuickCategoryModal.value = true;
                }
                async function saveQuickCategory() {
                    if (!quickCategory.value.name) return alert('Nama kategori harus diisi');
                    const res = await fetch('?action=addCategory', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(quickCategory.value)
                    });
                    const result = await res.json();
                    if (result.success) {
                        await loadCategories();
                        showQuickCategoryModal.value = false;
                        if (quickCategoryContext.value === 'add' && newItem.value) {
                            newItem.value.category_id = result.id;
                        } else if (quickCategoryContext.value === 'edit') {
                            editItemData.value.category_id = result.id;
                            onEditCategoryChange();
                        }
                    }
                }

                function openAddBrandModal(context = 'add', categoryId = null) {
                    if (!categoryId) {
                        if (context === 'add' && newItem.value?.category_id) categoryId = newItem.value.category_id;
                        else if (context === 'edit' && editItemData.value.category_id) categoryId = editItemData.value.category_id;
                        else { alert('Pilih kategori terlebih dahulu'); return; }
                    }
                    quickBrand.value = { name: '' };
                    quickBrandContext.value = { mode: context, category_id: categoryId };
                    const cat = categories.value.find(c => c.id == categoryId);
                    selectedCategoryName.value = cat ? cat.name : '';
                    showQuickBrandModal.value = true;
                }
                async function saveQuickBrand() {
                    if (!quickBrand.value.name) return alert('Nama merk harus diisi');
                    const payload = { category_id: quickBrandContext.value.category_id, name: quickBrand.value.name };
                    const res = await fetch('?action=addBrand', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const result = await res.json();
                    if (result.success) {
                        await loadBrandsByCategory(quickBrandContext.value.category_id);
                        showQuickBrandModal.value = false;
                        if (quickBrandContext.value.mode === 'add' && newItem.value) {
                            newItem.value.brand_id = result.id;
                        } else if (quickBrandContext.value.mode === 'edit') {
                            editItemData.value.brand_id = result.id;
                            onEditBrandChange();
                        }
                    }
                }

                function openAddTypeModal(context = 'add', brandId = null) {
                    if (!brandId) {
                        if (context === 'add' && newItem.value?.brand_id) brandId = newItem.value.brand_id;
                        else if (context === 'edit' && editItemData.value.brand_id) brandId = editItemData.value.brand_id;
                        else { alert('Pilih merk terlebih dahulu'); return; }
                    }
                    quickType.value = { name: '' };
                    quickTypeContext.value = { mode: context, brand_id: brandId };
                    const brand = brands.value.find(b => b.id == brandId);
                    selectedBrandName.value = brand ? brand.name : '';
                    showQuickTypeModal.value = true;
                }
                async function saveQuickType() {
                    if (!quickType.value.name) return alert('Nama tipe harus diisi');
                    const payload = { brand_id: quickTypeContext.value.brand_id, name: quickType.value.name };
                    const res = await fetch('?action=addType', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const result = await res.json();
                    if (result.success) {
                        await loadTypesByBrand(quickTypeContext.value.brand_id);
                        showQuickTypeModal.value = false;
                        if (quickTypeContext.value.mode === 'add' && newItem.value) newItem.value.type_id = result.id;
                        else if (quickTypeContext.value.mode === 'edit') editItemData.value.type_id = result.id;
                    }
                }

                // Quick Add Room
                function openAddRoomModal() {
                    quickRoom.value = { name: '', description: '' };
                    showQuickRoomModal.value = true;
                }

                async function saveQuickRoom() {
                    if (!quickRoom.value.name) return alert('Nama ruang harus diisi');
                    const res = await fetch('?action=addRoom', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(quickRoom.value)
                    });
                    const result = await res.json();
                    if (result.success) {
                        await loadRooms();
                        showQuickRoomModal.value = false;
                    } else {
                        alert('Gagal menambah ruang: ' + (result.error || 'Unknown error'));
                    }
                }

                // ---------- REFRESH CALLBACKS ----------
                let refreshCategories = null;
                let refreshBrands = null;
                let refreshTypes = null;
                let refreshItems = null;
                let refreshTransactions = null;
                function registerRefreshCategories(fn) { refreshCategories = fn; }
                function registerRefreshBrands(fn) { refreshBrands = fn; }
                function registerRefreshTypes(fn) { refreshTypes = fn; }
                function registerRefreshItems(fn) { refreshItems = fn; }
                function registerRefreshTransactions(fn) { refreshTransactions = fn; }

                // ---------- FUNGSI UNTUK EDIT TRANSAKSI DETAIL ----------
                async function openEditTransactionDetail(transId) {
                    const res = await fetch(`?action=getTransactionDetail&id=${transId}`);
                    const data = await res.json();
                    // Format tanggal untuk input datetime-local
                    if (data.transaction_date) {
                        const d = new Date(data.transaction_date);
                        const year = d.getFullYear();
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        const hour = String(d.getHours()).padStart(2, '0');
                        const minute = String(d.getMinutes()).padStart(2, '0');
                        data.transaction_date = `${year}-${month}-${day}T${hour}:${minute}`;
                    }
                    editTransactionData.value = data;
                    showEditTransactionDetailModal.value = true;
                }

                function removeItemFromTransaction(itemId) {
                    editTransactionData.value.items = editTransactionData.value.items.filter(item => item.item_id != itemId);
                }

                // Fungsi untuk scan dari modal edit transaksi
                async function scanAndAddToTransaction() {
                    // Tutup modal edit terlebih dahulu
                    showEditTransactionDetailModal.value = false;
                    // Set flag bahwa scanner dibuka dari edit
                    isScanningFromEdit.value = true;
                    
                    // Mulai scanner dengan callback
                    startScanner(async (qrCode) => {
                        const res = await fetch(`?action=scanItem&qr=${encodeURIComponent(qrCode)}`);
                        const item = await res.json();
                        if (item) {
                            // Cek apakah item sudah ada
                            if (editTransactionData.value.items.find(i => i.item_id == item.id)) {
                                alert('Item sudah ada dalam transaksi');
                                return;
                            }
                            // Cek status item: harus available atau sedang dipinjam di transaksi ini?
                            if (item.status !== 'available' && item.status !== 'borrowed') {
                                alert(`Item tidak dapat ditambahkan (status: ${item.status})`);
                                return;
                            }
                            // Jika item sedang dipinjam di transaksi lain, tidak boleh
                            if (item.status === 'borrowed' && item.current_loan && item.current_loan.transaction_id != editTransactionData.value.id) {
                                alert('Item sedang dipinjam di transaksi lain');
                                return;
                            }
                            // Tambahkan ke daftar
                            editTransactionData.value.items.push({
                                item_id: item.id,
                                item_name: item.name,
                                item_code: item.item_code,
                                status: 'borrowed'
                            });
                        } else {
                            alert('QR Code tidak dikenal');
                        }
                        // Catatan: modal edit akan dibuka kembali di dalam startScanner (karena flag isScanningFromEdit)
                    });
                }

                async function saveTransactionDetail() {
                    if (!editTransactionData.value.user_id || !editTransactionData.value.room_id || !editTransactionData.value.transaction_date) {
                        alert('Harap isi semua field');
                        return;
                    }
                    const payload = {
                        id: editTransactionData.value.id,
                        user_id: editTransactionData.value.user_id,
                        room_id: editTransactionData.value.room_id,
                        transaction_date: editTransactionData.value.transaction_date,
                        items: editTransactionData.value.items.map(item => item.item_id),
                        notes: editTransactionData.value.notes
                    };
                    const res = await fetch('?action=updateTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert('Transaksi berhasil diperbarui');
                        showEditTransactionDetailModal.value = false;
                        if (refreshTransactions) refreshTransactions();
                    } else {
                        alert('Gagal: ' + result.error);
                    }
                }

                // ---------- FUNGSI UNTUK DELETE TRANSAKSI ----------
                function openDeleteTransaction(transId) {
                    selectedTransactionId.value = transId;
                    showDeleteTransactionModal.value = true;
                }
                async function confirmDeleteTransaction() {
                    const res = await fetch('?action=deleteTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: selectedTransactionId.value })
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert('Transaksi berhasil dihapus');
                        showDeleteTransactionModal.value = false;
                        if (refreshTransactions) refreshTransactions();
                    } else {
                        alert('Gagal: ' + result.error);
                    }
                }

                // ---------- ON MOUNTED ----------
                onMounted(async () => {
                    await fetchCurrentUser();
                    if (isAdmin.value || isOperator.value) { await loadGuruList(); await loadRooms(); }
                    if (isAdmin.value) { await loadCategories(); await fetchUsers(); }
                });

                // ---------- PROVIDE ----------
                Vue.provide('app', {
                    user, isAdmin, isOperator, cart, guruList, rooms,
                    addToCart, removeFromCart, clearCart,
                    startScanner, stopScannerAndClose, openSignatureModal, viewPhoto, loadRooms, showQR,
                    openEditCategory, openEditBrand, openEditType, openEditItem,
                    registerRefreshCategories, registerRefreshBrands, registerRefreshTypes, registerRefreshItems, registerRefreshTransactions,
                    categories, brands, types, loadBrandsByCategory, loadTypesByBrand,
                    openAddCategoryModal, openAddBrandModal, openAddTypeModal,
                    openAddRoomModal, openAddGuruModal, // tambahkan
                    selectedCategoryName, selectedBrandName,
                    newItem,
                    users, loadingUsers, fetchUsers, openAddUser, openEditUser, deleteUser,
                    borrowPhotoFile, returnPhotoFile,
                    // Provide fungsi transaksi
                    openEditTransactionDetail, openDeleteTransaction
                });

                return {
                    user, activeMenu, filteredMenus, currentView, showScanner, showQRModal, selectedItem,
                    showSignatureModal, signatureTitle, signatureCanvas, setActiveMenu, stopScannerAndClose,
                    clearSignature, saveSignature, closeSignatureModal,
                    showEditCategoryModal, editCategoryData, showEditBrandModal, editBrandData,
                    showEditTypeModal, editTypeData, showEditItemModal, editItemData,
                    categories, brands, types,
                    updateCategory, updateBrand, updateType, updateItem,
                    onEditCategoryChange, onEditBrandChange,
                    showQuickCategoryModal, quickCategory, showQuickBrandModal, quickBrand,
                    showQuickTypeModal, quickType, selectedCategoryName, selectedBrandName,
                    saveQuickCategory, saveQuickBrand, saveQuickType,
                    showQuickRoomModal, quickRoom, saveQuickRoom, openAddRoomModal,
                    showAddGuruModal, newGuru, saveGuru, openAddGuruModal,
                    openAddCategoryModal, openAddBrandModal, openAddTypeModal,
                    showUserModal, userModalTitle, userForm, saveUser,
                    showPhotoModal, photoData, photoTitle,
                    // Expose guruList dan rooms agar tersedia di template modal
                    guruList, rooms,
                    // Expose modal transaksi
                    showEditTransactionDetailModal, editTransactionData,
                    showDeleteTransactionModal,
                    removeItemFromTransaction, scanAndAddToTransaction, saveTransactionDetail,
                    confirmDeleteTransaction,
                    // Expose untuk master menu
                    showMasterMenu, isAdmin
                };
            }
        };

        // =============== DASHBOARD ===============
        const Dashboard = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5"><i :class="roleIcon" class="text-blue-600 mr-2"></i> {{ isAdmin ? 'Dashboard Admin' : (isOperator ? 'Dashboard Operator' : 'Dashboard Guru') }}</h2>
                    <div v-if="loading" class="text-center py-10"><i class="fas fa-spinner fa-spin text-3xl"></i></div>
                    <div v-else>
                        <!-- Statistik 4 kolom di semua layar -->
                        <div v-if="isAdmin || isOperator" class="grid grid-cols-4 gap-2 mb-8">
                            <div class="bg-blue-50 p-2 md:p-4 rounded-lg border border-blue-200 text-center">
                                <div class="text-blue-700 text-xs md:text-sm">Total Item</div>
                                <div class="text-lg md:text-3xl font-bold">{{ stats.total_items || 0 }}</div>
                            </div>
                            <div class="bg-green-50 p-2 md:p-4 rounded-lg border border-green-200 text-center">
                                <div class="text-green-700 text-xs md:text-sm">Tersedia</div>
                                <div class="text-lg md:text-3xl font-bold">{{ stats.available_items || 0 }}</div>
                            </div>
                            <div class="bg-yellow-50 p-2 md:p-4 rounded-lg border border-yellow-200 text-center">
                                <div class="text-yellow-700 text-xs md:text-sm">Dipinjam</div>
                                <div class="text-lg md:text-3xl font-bold">{{ stats.borrowed_items || 0 }}</div>
                            </div>
                            <div class="bg-purple-50 p-2 md:p-4 rounded-lg border border-purple-200 text-center">
                                <div class="text-purple-700 text-xs md:text-sm">Transaksi Hari Ini</div>
                                <div class="text-lg md:text-3xl font-bold">{{ stats.today_transactions || 0 }}</div>
                            </div>
                        </div>
                        
                        <div v-if="!isAdmin && !isOperator">
                            <h3 class="font-semibold text-lg mb-3">Peralatan Sedang Dipinjam</h3>
                            <div v-if="stats.my_borrowed_items?.length" class="grid gap-3 md:grid-cols-2">
                                <div v-for="item in stats.my_borrowed_items" class="border p-3 rounded-lg shadow-sm">
                                    <div class="font-medium">{{ item.name }}</div>
                                    <div class="text-sm text-gray-600">{{ item.brand_name }} {{ item.type_name }}</div>
                                    <div class="text-xs text-gray-500">Ruang: {{ item.room_name }}</div>
                                    <div class="text-xs text-gray-500">Dipinjam: {{ formatDate(item.borrow_date) }}</div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 italic">Tidak ada peminjaman aktif.</p>
                            
                            <h3 class="font-semibold text-lg mt-6 mb-3">Riwayat Peminjaman</h3>
                            <div v-if="stats.history?.length" class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead><tr class="bg-gray-100"><th>Item</th><th>Ruang</th><th>Tgl Pinjam</th><th>Tgl Kembali</th></tr></thead>
                                    <tbody>
                                        <tr v-for="h in stats.history" class="border-b">
                                            <td>{{ h.name }} ({{ h.brand_name }} {{ h.type_name }})</td>
                                            <td>{{ h.room_name }}</td>
                                            <td>{{ formatDate(h.borrow_date) }}</td>
                                            <td>{{ formatDate(h.return_date) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-gray-500 italic">Belum ada riwayat.</p>
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const isAdmin = Vue.computed(() => app.isAdmin.value);
                const isOperator = Vue.computed(() => app.isOperator.value);
                const stats = Vue.ref({});
                const loading = Vue.ref(true);

                async function fetchDashboard() {
                    loading.value = true;
                    const res = await fetch('?action=dashboard');
                    stats.value = await res.json();
                    loading.value = false;
                }

                function formatDate(d) {
                    if (!d) return '-';
                    return new Date(d).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'});
                }

                Vue.onMounted(fetchDashboard);
                return { isAdmin, isOperator, stats, loading, formatDate, roleIcon: Vue.computed(() => isAdmin.value ? 'fas fa-user-cog' : (isOperator.value ? 'fas fa-user-tie' : 'fas fa-chalkboard-teacher')) };
            }
        };

        // =============== KATEGORI, MERK, TIPE ===============
        const CategoryBrand = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">Manajemen Kategori, Merk & Tipe</h2>
                    <div class="border-b border-gray-200 mb-4">
                        <nav class="flex -mb-px">
                            <button @click="activeTab = 'categories'" :class="activeTab === 'categories' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="py-2 px-4 text-center border-b-2 font-medium text-sm">Kategori</button>
                            <button @click="activeTab = 'brands'" :class="activeTab === 'brands' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="py-2 px-4 text-center border-b-2 font-medium text-sm">Merk</button>
                            <button @click="activeTab = 'types'" :class="activeTab === 'types' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="py-2 px-4 text-center border-b-2 font-medium text-sm">Tipe</button>
                        </nav>
                    </div>

                    <!-- Kategori -->
                    <div v-if="activeTab === 'categories'">
                        <div class="mb-4 flex gap-2">
                            <input v-model="newCategory.name" placeholder="Nama Kategori" class="border rounded px-3 py-2 flex-1">
                            <input v-model="newCategory.description" placeholder="Deskripsi" class="border rounded px-3 py-2 flex-1">
                            <button @click="addCategory" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus"></i> Tambah</button>
                        </div>
                        <div v-if="loadingCategories" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead><tr><th>ID</th><th>Nama</th><th>Prefix</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
                                <tbody>
                                    <tr v-for="cat in categories" :key="cat.id" class="border-b">
                                        <td class="py-2">{{ cat.id }}</td>
                                        <td class="font-medium">{{ cat.name }}</td>
                                        <td>{{ cat.code_prefix }}</td>
                                        <td>{{ cat.description }}</td>
                                        <td>
                                            <button @click="editCategory(cat)" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></button>
                                            <button @click="deleteCategory(cat.id)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Merk -->
                    <div v-if="activeTab === 'brands'">
                        <div class="mb-4 flex gap-2">
                            <select v-model="selectedCategoryForBrand" class="border rounded px-3 py-2">
                                <option value="">Pilih Kategori</option>
                                <option v-for="cat in categories" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <input v-model="newBrand.name" placeholder="Nama Merk" class="border rounded px-3 py-2 flex-1">
                            <button @click="addBrand" :disabled="!selectedCategoryForBrand || !newBrand.name" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:bg-gray-400"><i class="fas fa-plus"></i> Tambah</button>
                        </div>
                        <div v-if="selectedCategoryForBrand">
                            <h3 class="font-semibold mb-2">Daftar Merk untuk Kategori: {{ getCategoryName(selectedCategoryForBrand) }}</h3>
                            <div v-if="loadingBrands" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <div v-for="brand in brands" :key="brand.id" class="border rounded p-2 flex justify-between items-center">
                                    <span>{{ brand.name }} ({{ brand.code_prefix }})</span>
                                    <div><button @click="editBrand(brand)" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></button><button @click="deleteBrand(brand.id)" class="text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tipe -->
                    <div v-if="activeTab === 'types'">
                        <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-2">
                            <select v-model="selectedCategoryForType" @change="onCategoryForTypeChange" class="border rounded px-3 py-2">
                                <option value="">Pilih Kategori</option>
                                <option v-for="cat in categories" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <select v-model="selectedBrandForType" class="border rounded px-3 py-2">
                                <option value="">Pilih Merk</option>
                                <option v-for="brand in brandsForType" :value="brand.id">{{ brand.name }}</option>
                            </select>
                            <div class="flex gap-2">
                                <input v-model="newType.name" placeholder="Nama Tipe" class="border rounded px-3 py-2 flex-1">
                                <button @click="addType" :disabled="!selectedBrandForType || !newType.name" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:bg-gray-400"><i class="fas fa-plus"></i> Tambah</button>
                            </div>
                        </div>
                        <div v-if="selectedBrandForType">
                            <h3 class="font-semibold mb-2">Daftar Tipe untuk Merk: {{ getBrandName(selectedBrandForType) }}</h3>
                            <div v-if="loadingTypes" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <div v-for="type in types" :key="type.id" class="border rounded p-2 flex justify-between items-center">
                                    <span>{{ type.name }} ({{ type.code_prefix }})</span>
                                    <div><button @click="editType(type)" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></button><button @click="deleteType(type.id)" class="text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const openEditCategory = app.openEditCategory;
                const openEditBrand = app.openEditBrand;
                const openEditType = app.openEditType;
                const registerRefreshCategories = app.registerRefreshCategories;
                const registerRefreshBrands = app.registerRefreshBrands;
                const registerRefreshTypes = app.registerRefreshTypes;

                const activeTab = ref('categories');
                const categories = ref([]);
                const brands = ref([]);
                const types = ref([]);
                const loadingCategories = ref(true);
                const loadingBrands = ref(false);
                const loadingTypes = ref(false);
                const newCategory = ref({ name: '', description: '' });
                const newBrand = ref({ name: '' });
                const newType = ref({ name: '' });
                const selectedCategoryForBrand = ref(null);
                const selectedCategoryForType = ref(null);
                const selectedBrandForType = ref(null);
                const brandsForType = ref([]);

                async function fetchCategories() {
                    loadingCategories.value = true;
                    const res = await fetch('?action=getCategories');
                    categories.value = await res.json();
                    loadingCategories.value = false;
                }
                async function addCategory() {
                    if (!newCategory.value.name) return alert('Nama kategori harus diisi');
                    await fetch('?action=addCategory', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(newCategory.value)
                    });
                    newCategory.value = { name: '', description: '' };
                    fetchCategories();
                }
                async function deleteCategory(id) {
                    if (!confirm('Yakin hapus kategori? Semua merk dan item terkait akan ikut terhapus.')) return;
                    await fetch('?action=deleteCategory', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    fetchCategories();
                }
                function editCategory(cat) { openEditCategory(cat); }

                async function fetchBrands(catId) {
                    if (!catId) return;
                    loadingBrands.value = true;
                    const res = await fetch(`?action=getBrandsByCategory&category_id=${catId}`);
                    brands.value = await res.json();
                    loadingBrands.value = false;
                }
                async function addBrand() {
                    if (!selectedCategoryForBrand.value) return alert('Pilih kategori terlebih dahulu');
                    if (!newBrand.value.name) return alert('Nama merk harus diisi');
                    await fetch('?action=addBrand', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ category_id: selectedCategoryForBrand.value, name: newBrand.value.name })
                    });
                    newBrand.value.name = '';
                    fetchBrands(selectedCategoryForBrand.value);
                }
                async function deleteBrand(id) {
                    if (!confirm('Yakin hapus merk?')) return;
                    await fetch('?action=deleteBrand', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    fetchBrands(selectedCategoryForBrand.value);
                }
                function editBrand(brand) { openEditBrand(brand); }
                function getCategoryName(id) { const cat = categories.value.find(c => c.id == id); return cat ? cat.name : ''; }

                async function onCategoryForTypeChange() {
                    selectedBrandForType.value = null;
                    if (selectedCategoryForType.value) {
                        const res = await fetch(`?action=getBrandsByCategory&category_id=${selectedCategoryForType.value}`);
                        brandsForType.value = await res.json();
                    } else brandsForType.value = [];
                }
                async function fetchTypes(brandId) {
                    if (!brandId) return;
                    loadingTypes.value = true;
                    const res = await fetch(`?action=getTypesByBrand&brand_id=${brandId}`);
                    types.value = await res.json();
                    loadingTypes.value = false;
                }
                async function addType() {
                    if (!selectedBrandForType.value) return alert('Pilih merk terlebih dahulu');
                    if (!newType.value.name) return alert('Nama tipe harus diisi');
                    await fetch('?action=addType', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ brand_id: selectedBrandForType.value, name: newType.value.name })
                    });
                    newType.value.name = '';
                    fetchTypes(selectedBrandForType.value);
                }
                async function deleteType(id) {
                    if (!confirm('Yakin hapus tipe?')) return;
                    await fetch('?action=deleteType', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    fetchTypes(selectedBrandForType.value);
                }
                function editType(type) { openEditType(type); }
                function getBrandName(id) { const brand = brandsForType.value.find(b => b.id == id) || brands.value.find(b => b.id == id); return brand ? brand.name : ''; }

                Vue.watch(selectedCategoryForBrand, fetchBrands);
                Vue.watch(selectedBrandForType, fetchTypes);
                Vue.onMounted(() => {
                    fetchCategories();
                    registerRefreshCategories(fetchCategories);
                    registerRefreshBrands(() => { if (selectedCategoryForBrand.value) fetchBrands(selectedCategoryForBrand.value); });
                    registerRefreshTypes(() => { if (selectedBrandForType.value) fetchTypes(selectedBrandForType.value); });
                });
                return {
                    activeTab, categories, brands, types, loadingCategories, loadingBrands, loadingTypes,
                    newCategory, newBrand, newType, selectedCategoryForBrand, selectedCategoryForType,
                    selectedBrandForType, brandsForType, addCategory, deleteCategory, editCategory,
                    addBrand, deleteBrand, editBrand, getCategoryName, onCategoryForTypeChange,
                    addType, deleteType, editType, getBrandName
                };
            }
        };

        // =============== ITEM LIST ===============
        const ItemList = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">Inventaris Peralatan</h2>
                    
                    <!-- Baris Filter, Pencarian, Sort, dan Tambah -->
                    <div class="flex flex-wrap gap-2 mb-4 items-center">
                        <!-- Filter Status -->
                        <select v-model="filterStatus" @change="applyFilters" class="border rounded px-3 py-2 text-sm">
                            <option value="all">Semua Status</option>
                            <option value="available">Tersedia</option>
                            <option value="borrowed">Dipinjam</option>
                        </select>

                        <!-- Filter Kategori -->
                        <select v-model="selectedCategory" @change="onCategoryChange" class="border rounded px-3 py-2 text-sm">
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>

                        <!-- Filter Merk (bergantung pada kategori) -->
                        <select v-model="selectedBrand" @change="applyFilters" class="border rounded px-3 py-2 text-sm" :disabled="!selectedCategory && brandsFilter.length === 0">
                            <option value="">Semua Merk</option>
                            <option v-for="brand in brandsFilter" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                        </select>

                        <!-- Sort A-Z / Z-A -->
                        <select v-model="sortOrder" @change="applyFilters" class="border rounded px-3 py-2 text-sm">
                            <option value="asc">A-Z</option>
                            <option value="desc">Z-A</option>
                        </select>

                        <!-- Pencarian dengan debounce -->
                        <div class="relative flex-1 min-w-[200px]">
                            <input type="text" v-model="searchQuery" @input="debouncedSearch" placeholder="Cari nama, kode, kategori, merk..." class="border rounded px-3 py-2 w-full pr-8 text-sm">
                            <i v-if="searchLoading" class="fas fa-spinner fa-spin absolute right-2 top-3 text-gray-400"></i>
                            <i v-else class="fas fa-search absolute right-2 top-3 text-gray-400"></i>
                        </div>

                        <!-- Tombol Tambah Item (untuk admin) -->
                        <button v-if="isAdmin" @click="toggleAddForm" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm whitespace-nowrap">
                            <i class="fas" :class="showAddForm ? 'fa-minus' : 'fa-plus'"></i> {{ showAddForm ? 'Tutup Form' : 'Tambah Item' }}
                        </button>
                    </div>
                    
                    <!-- Form tambah item (hide/unhide) -->
                    <div v-if="showAddForm" class="border p-4 rounded-lg mb-6 bg-gray-50">
                        <h3 class="font-semibold mb-3">Tambah Item Baru</h3>
                        <div class="space-y-4">
                            <!-- Baris Kategori -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <select v-model="newItem.category_id" class="border rounded px-3 py-2 w-full">
                                        <option value="">Pilih Kategori</option>
                                        <option v-for="cat in categories" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                    <button @click="openAddCategoryModal('add')" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 text-sm whitespace-nowrap" title="Tambah Kategori Baru"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                            
                            <!-- Baris Merk (muncul jika kategori dipilih) -->
                            <div v-if="newItem.category_id">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Merk <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <select v-model="newItem.brand_id" class="border rounded px-3 py-2 w-full" :disabled="!brands.length">
                                        <option value="">Pilih Merk</option>
                                        <option v-for="brand in brands" :value="brand.id">{{ brand.name }}</option>
                                    </select>
                                    <button @click="openAddBrandModal('add', newItem.category_id)" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 text-sm whitespace-nowrap" title="Tambah Merk Baru"><i class="fas fa-plus"></i></button>
                                </div>
                                <p v-if="brands.length === 0" class="text-sm text-yellow-600 mt-1">Tidak ada merk untuk kategori ini. Silakan tambah merk terlebih dahulu.</p>
                            </div>
                            
                            <!-- Baris Tipe (muncul jika merk dipilih) -->
                            <div v-if="newItem.brand_id">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <select v-model="newItem.type_id" class="border rounded px-3 py-2 w-full" :disabled="!types.length">
                                        <option value="">Pilih Tipe</option>
                                        <option v-for="type in types" :value="type.id">{{ type.name }}</option>
                                    </select>
                                    <button @click="openAddTypeModal('add', newItem.brand_id)" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 text-sm whitespace-nowrap" title="Tambah Tipe Baru"><i class="fas fa-plus"></i></button>
                                </div>
                                <p v-if="types.length === 0" class="text-sm text-yellow-600 mt-1">Tidak ada tipe untuk merk ini. Silakan tambah tipe terlebih dahulu.</p>
                            </div>
                            
                            <!-- Kode Peralatan (otomatis) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Peralatan</label>
                                <input :value="generatedItemCode" readonly class="border rounded px-3 py-2 w-full bg-gray-50" placeholder="Otomatis">
                            </div>
                            
                            <!-- Nama Item -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Peralatan <span class="text-red-500">*</span></label>
                                <input v-model="newItem.name" placeholder="Contoh: Proyektor Epson EB-X41" class="border rounded px-3 py-2 w-full">
                            </div>
                            
                            <!-- Tahun -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tahun <span class="text-red-500">*</span></label>
                                <select v-model="newItem.year" class="border rounded px-3 py-2 w-full">
                                    <option value="">Pilih Tahun</option>
                                    <option value="2021">2021</option><option value="2022">2022</option><option value="2023">2023</option><option value="2025">2025</option><option value="2026">2026</option>
                                </select>
                            </div>
                            
                            <!-- Tombol Aksi -->
                            <div class="flex gap-2 pt-2">
                                <button @click="saveItem" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Simpan</button>
                                <button @click="closeAddForm" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Batal</button>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar item (loading skeleton) -->
                    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="n in 6" :key="n" class="border rounded-lg p-4 shadow-sm skeleton h-48"></div>
                    </div>
                    <div v-else-if="items.length === 0" class="text-center py-10 text-gray-500">Tidak ada item ditemukan.</div>
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="item in items" :key="item.id" class="border rounded-lg p-4 shadow-sm hover:shadow-md transition break-words">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-base break-words">{{ item.name }}</h3>
                                    <p class="text-sm text-gray-600 break-words">{{ item.brand_name }} {{ item.type_name }} ({{ item.year }})</p>
                                    <p class="text-xs text-gray-500 break-words">Kategori: {{ item.category_name }}</p>
                                    <p class="text-xs text-gray-500 break-words">Kode: {{ item.item_code }}</p>
                                    <span :class="statusClass(item.status)" class="inline-block px-2 py-1 text-xs rounded mt-2 break-words">{{ item.status === 'available' ? 'Tersedia' : item.status === 'borrowed' ? 'Dipinjam' : item.status }}</span>
                                </div>
                                <button @click="showQR(item)" class="text-blue-600 hover:text-blue-800 ml-2 flex-shrink-0" title="Lihat QR"><i class="fas fa-qrcode text-xl"></i></button>
                            </div>
                            <div class="mt-3 text-xs text-gray-400 break-words">QR: {{ item.qr_code }}</div>
                            <div v-if="isAdmin" class="mt-3 flex gap-2">
                                <button @click="editItem(item)" class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></button>
                                <button @click="deleteItem(item.id)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="!loading && totalItems > 0" class="mt-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Menampilkan {{ items.length }} dari {{ totalItems }} item
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm">Item per halaman:</label>
                            <select v-model="limit" @change="changePage(1)" class="border rounded px-2 py-1 text-sm">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="0">Semua</option>
                            </select>
                        </div>
                        <div class="flex gap-1">
                            <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50 text-sm">&laquo;</button>
                            <span class="px-3 py-1 border rounded bg-blue-50 text-sm">Halaman {{ currentPage }} dari {{ totalPages }}</span>
                            <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages || totalPages === 0" class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50 text-sm">&raquo;</button>
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const isAdmin = Vue.computed(() => app.isAdmin.value);
                const showQR = app.showQR;
                const openEditItem = app.openEditItem;
                const registerRefreshItems = app.registerRefreshItems;
                const categories = app.categories;
                const brands = app.brands;
                const types = app.types;
                const loadBrandsByCategory = app.loadBrandsByCategory;
                const loadTypesByBrand = app.loadTypesByBrand;
                const openAddCategoryModal = app.openAddCategoryModal;
                const openAddBrandModal = app.openAddBrandModal;
                const openAddTypeModal = app.openAddTypeModal;
                const newItem = app.newItem;
                
                const items = ref([]);
                const totalItems = ref(0);
                const currentPage = ref(1);
                const limit = ref(10);
                const filterStatus = ref('all');
                const selectedCategory = ref('');
                const selectedBrand = ref('');
                const sortOrder = ref('asc');
                const searchQuery = ref('');
                const searchLoading = ref(false);
                const loading = ref(true);
                const brandsFilter = ref([]);

                watch(selectedCategory, async (newVal) => {
                    selectedBrand.value = '';
                    if (newVal) {
                        const res = await fetch(`?action=getBrandsByCategory&category_id=${newVal}`);
                        brandsFilter.value = await res.json();
                    } else {
                        brandsFilter.value = [];
                    }
                    applyFilters();
                });

                let searchTimeout;
                function debouncedSearch() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        applyFilters();
                    }, 500);
                }

                async function fetchItems() {
                    loading.value = true;
                    let url = `?action=getItems&status=${filterStatus.value}&sort=${sortOrder.value}&page=${currentPage.value}`;
                    if (limit.value > 0) url += `&limit=${limit.value}`;
                    else url += `&limit=1000`;
                    if (selectedCategory.value) url += `&category_id=${selectedCategory.value}`;
                    if (selectedBrand.value) url += `&brand_id=${selectedBrand.value}`;
                    if (searchQuery.value) url += `&search=${encodeURIComponent(searchQuery.value)}`;
                    
                    const res = await fetch(url);
                    const data = await res.json();
                    items.value = data.items;
                    totalItems.value = data.total;
                    loading.value = false;
                }

                function applyFilters() {
                    currentPage.value = 1;
                    fetchItems();
                }

                function changePage(page) {
                    if (page < 1) return;
                    if (limit.value > 0 && page > totalPages.value) return;
                    currentPage.value = page;
                    fetchItems();
                }

                const totalPages = computed(() => {
                    if (limit.value === 0) return 1;
                    return Math.ceil(totalItems.value / limit.value);
                });

                watch([limit, sortOrder], () => {
                    currentPage.value = 1;
                    fetchItems();
                });

                watch(filterStatus, applyFilters);

                const generatedItemCode = Vue.computed(() => {
                    if (!newItem.value.category_id || !newItem.value.brand_id) return '';
                    const cat = categories.value.find(c => c.id == newItem.value.category_id);
                    const brand = brands.value.find(b => b.id == newItem.value.brand_id);
                    if (!cat || !brand) return '';
                    return cat.code_prefix + '-' + brand.code_prefix + '-XXX';
                });

                async function saveItem() {
                    if (!newItem.value.name || !newItem.value.category_id || !newItem.value.brand_id || !newItem.value.type_id || !newItem.value.year) {
                        return alert('Nama, kategori, merk, tipe, dan tahun harus diisi');
                    }
                    const res = await fetch('?action=addItem', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(newItem.value)
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert(`Item berhasil ditambahkan. Kode: ${result.item_code}`);
                        closeAddForm();
                        fetchItems();
                    }
                }

                async function deleteItem(id) {
                    if (!confirm('Yakin hapus item?')) return;
                    await fetch('?action=deleteItem', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    fetchItems();
                }

                function statusClass(status) {
                    return { 'available': 'bg-green-100 text-green-800', 'borrowed': 'bg-yellow-100 text-yellow-800', 'damaged': 'bg-red-100 text-red-800', 'lost': 'bg-gray-100 text-gray-800' }[status] || 'bg-gray-100';
                }

                function editItem(item) { openEditItem(item); }

                const showAddForm = ref(false);
                function toggleAddForm() {
                    if (showAddForm.value) {
                        closeAddForm();
                    } else {
                        openAddForm();
                    }
                }
                function openAddForm() {
                    showAddForm.value = true;
                    newItem.value = { category_id: '', brand_id: '', type_id: '', name: '', year: '' };
                    brands.value = [];
                    types.value = [];
                }
                function closeAddForm() {
                    showAddForm.value = false;
                    newItem.value = { category_id: '', brand_id: '', type_id: '', name: '', year: '' };
                    brands.value = [];
                    types.value = [];
                }

                Vue.watch(() => newItem.value.category_id, async (newVal) => {
                    if (newVal) {
                        newItem.value.brand_id = '';
                        newItem.value.type_id = '';
                        await loadBrandsByCategory(newVal);
                    } else {
                        brands.value = [];
                        types.value = [];
                    }
                });

                Vue.watch(() => newItem.value.brand_id, async (newVal) => {
                    if (newVal) {
                        newItem.value.type_id = '';
                        await loadTypesByBrand(newVal);
                    } else {
                        types.value = [];
                    }
                });

                onMounted(() => {
                    fetchItems();
                    registerRefreshItems(fetchItems);
                });

                return { 
                    isAdmin, items, categories, brands, types, loading, filterStatus, 
                    showAddForm, newItem, generatedItemCode,
                    fetchItems, saveItem, deleteItem, statusClass, showQR, editItem,
                    openAddCategoryModal, openAddBrandModal, openAddTypeModal,
                    toggleAddForm, openAddForm, closeAddForm,
                    selectedCategory, selectedBrand, sortOrder, searchQuery, searchLoading,
                    brandsFilter, limit, currentPage, totalItems, totalPages,
                    applyFilters, changePage, debouncedSearch, onCategoryChange: () => {},
                };
            }
        };

        // =============== PEMINJAMAN ===============
        const BorrowProcess = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">Peminjaman Peralatan</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h3 class="font-semibold mb-2">1. Scan QR Item</h3>
                                <button @click="startScan" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-camera"></i> Scan QR</button>
                                <p v-if="lastScanned" class="mt-2 text-sm">Terakhir scan: {{ lastScanned.name }}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold mb-2 flex justify-between"><span>2. Keranjang Peminjaman</span><span class="bg-blue-600 text-white rounded-full px-2 py-0.5 text-xs">{{ cart.length }} item</span></h3>
                                <div v-if="cart.length === 0" class="text-gray-500 italic py-3">Keranjang kosong</div>
                                <div v-else>
                                    <div v-for="item in cart" :key="item.id" class="flex justify-between items-center border-b py-2">
                                        <div><div class="font-medium">{{ item.name }}</div><div class="text-xs">{{ item.brand_name }} - {{ item.item_code }}</div></div>
                                        <button @click="removeFromCart(item.id)" class="text-red-500"><i class="fas fa-times"></i></button>
                                    </div>
                                    <button @click="clearCart" class="mt-2 text-sm text-red-600">Kosongkan</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold mb-2">3. Pilih Peminjam (Guru)</h3>
                                <div class="flex gap-2 items-center">
                                    <select v-model="selectedTeacher" class="border rounded px-3 py-2 flex-1">
                                        <option v-for="g in guruList" :key="g.id" :value="g.id">{{ g.name }}</option>
                                    </select>
                                    <button @click="openAddGuruModal" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 text-sm whitespace-nowrap" title="Tambah Guru Baru"><i class="fas fa-plus"></i></button>
                                </div>
                                <h3 class="font-semibold mb-2 mt-4">4. Pilih Ruang Penggunaan</h3>
                                <div class="flex gap-2 items-center">
                                    <select v-model="selectedRoom" class="border rounded px-3 py-2 flex-1">
                                        <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                    <button @click="openAddRoomModal" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 text-sm whitespace-nowrap" title="Tambah Ruang Baru"><i class="fas fa-plus"></i></button>
                                </div>
                                <h3 class="font-semibold mb-2 mt-4">5. Upload Foto (Opsional)</h3>
                                <input type="file" @change="onBorrowPhotoChange" accept="image/*" class="border rounded px-3 py-2 w-full mb-4">
                                <h3 class="font-semibold mb-2">6. Catatan</h3>
                                <textarea v-model="notes" rows="2" class="border rounded px-3 py-2 w-full mb-4" placeholder="Catatan (opsional)"></textarea>
                                <div class="mt-4">
                                    <button @click="processCheckout" :disabled="cart.length===0 || !selectedTeacher || !selectedRoom" class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 disabled:bg-gray-400"><i class="fas fa-check-circle"></i> Proses Peminjaman (dengan Tanda Tangan)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const cart = Vue.computed(() => app.cart.value);
                const guruList = Vue.computed(() => app.guruList.value);
                const rooms = Vue.computed(() => app.rooms.value);
                const addToCart = app.addToCart;
                const removeFromCart = app.removeFromCart;
                const clearCart = app.clearCart;
                const startScanner = app.startScanner;
                const openSignatureModal = app.openSignatureModal;
                const openAddRoomModal = app.openAddRoomModal;
                const openAddGuruModal = app.openAddGuruModal;
                
                const selectedTeacher = Vue.ref(null);
                const selectedRoom = Vue.ref(null);
                const lastScanned = Vue.ref(null);
                const borrowPhotoFile = Vue.ref(null);
                const notes = Vue.ref('');

                function onBorrowPhotoChange(e) { borrowPhotoFile.value = e.target.files[0]; }

                async function startScan() {
                    startScanner(async (qrCode) => {
                        const res = await fetch(`?action=scanItem&qr=${encodeURIComponent(qrCode)}`);
                        const item = await res.json();
                        if (item) {
                            if (item.status !== 'available') { alert(`Item sedang ${item.status}`); return; }
                            lastScanned.value = item;
                            addToCart(item);
                        } else alert('QR Code tidak dikenal');
                    });
                }

                async function processCheckout() {
                    if (cart.value.length === 0) return alert('Keranjang kosong');
                    if (!selectedTeacher.value) return alert('Pilih guru peminjam');
                    if (!selectedRoom.value) return alert('Pilih ruang penggunaan');

                    const borrowSignature = await openSignatureModal('Tanda Tangan Peminjam (Guru)');
                    if (!borrowSignature) return alert('Tanda tangan peminjam wajib diisi');
                    
                    const adminSignature = await openSignatureModal('Tanda Tangan Petugas');
                    if (!adminSignature) return alert('Tanda tangan petugas wajib diisi');

                    const formData = new FormData();
                    formData.append('user_id', selectedTeacher.value);
                    formData.append('room_id', selectedRoom.value);
                    formData.append('borrow_signature', borrowSignature);
                    formData.append('items', JSON.stringify(cart.value.map(i => i.id)));
                    formData.append('notes', notes.value);
                    if (borrowPhotoFile.value) formData.append('borrow_photo', borrowPhotoFile.value);

                    const res = await fetch('?action=checkout', { method: 'POST', body: formData });
                    const result = await res.json();
                    if (result.success) {
                        alert(`Transaksi berhasil! ID: ${result.transaction_id} - Kode Pinjam: ${result.borrow_code}`);
                        clearCart();
                        borrowPhotoFile.value = null;
                        notes.value = '';
                        if (guruList.value.length) selectedTeacher.value = guruList.value[0].id;
                        if (rooms.value.length) selectedRoom.value = rooms.value[0].id;
                    } else alert('Error: ' + result.error);
                }

                Vue.onMounted(() => {
                    if (guruList.value.length) selectedTeacher.value = guruList.value[0].id;
                    if (rooms.value.length) selectedRoom.value = rooms.value[0].id;
                });

                return { cart, guruList, rooms, selectedTeacher, selectedRoom, lastScanned, notes, startScan, removeFromCart, clearCart, processCheckout, onBorrowPhotoChange, openAddRoomModal, openAddGuruModal };
            }
        };

        // =============== PENGEMBALIAN ===============
        const ReturnProcess = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">Pengembalian Peralatan</h2>
                    <div class="bg-gray-50 p-6 rounded-lg max-w-md mx-auto text-center">
                        <i class="fas fa-undo-alt text-5xl text-blue-600 mb-4"></i>
                        <p class="mb-4">Scan QR code peralatan yang akan dikembalikan</p>
                        <button @click="startScanReturn" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700"><i class="fas fa-camera"></i> Scan QR</button>
                        <div v-if="message" class="mt-4 p-3 rounded" :class="messageClass">{{ message }}</div>
                        <div v-if="showUpload" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Foto Pengembalian (Opsional)</label>
                            <input type="file" @change="onReturnPhotoChange" accept="image/*" class="border rounded px-3 py-2 w-full">
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const startScanner = app.startScanner;
                const openSignatureModal = app.openSignatureModal;
                const message = Vue.ref('');
                const messageClass = Vue.ref('');
                const showUpload = Vue.ref(false);
                const returnPhotoFile = Vue.ref(null);
                const currentItem = Vue.ref(null);

                function onReturnPhotoChange(e) { returnPhotoFile.value = e.target.files[0]; }

                async function startScanReturn() {
                    startScanner(async (qrCode) => {
                        const res = await fetch(`?action=scanItem&qr=${encodeURIComponent(qrCode)}`);
                        const item = await res.json();
                        if (item) {
                            if (item.status !== 'borrowed') {
                                message.value = `Item ini tidak sedang dipinjam (status: ${item.status})`;
                                messageClass.value = 'bg-yellow-100 text-yellow-800';
                                showUpload.value = false;
                                return;
                            }
                            currentItem.value = item;
                            showUpload.value = true;
                            
                            const returnSignature = await openSignatureModal('Tanda Tangan Pengembalian (Petugas)');
                            if (!returnSignature) return alert('Tanda tangan pengembalian wajib diisi');
                            
                            const formData = new FormData();
                            formData.append('item_id', item.id);
                            formData.append('return_signature', returnSignature);
                            if (returnPhotoFile.value) formData.append('return_photo', returnPhotoFile.value);

                            const ret = await fetch('?action=returnItem', { method: 'POST', body: formData });
                            const result = await ret.json();
                            if (result.success) {
                                message.value = `Item ${item.name} berhasil dikembalikan.`;
                                messageClass.value = 'bg-green-100 text-green-800';
                                returnPhotoFile.value = null;
                                showUpload.value = false;
                            } else {
                                message.value = 'Error: ' + result.error;
                                messageClass.value = 'bg-red-100 text-red-800';
                            }
                        } else {
                            message.value = 'QR Code tidak ditemukan';
                            messageClass.value = 'bg-red-100 text-red-800';
                        }
                    });
                }

                return { startScanReturn, message, messageClass, showUpload, onReturnPhotoChange };
            }
        };

        // =============== RIWAYAT TRANSAKSI ===============
        const TransactionHistory = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">{{ isAdmin || isOperator ? 'Semua Transaksi' : 'Riwayat Peminjamanku' }}</h2>
                    
                    <!-- Filter Section -->
                    <div v-if="isAdmin || isOperator" class="bg-gray-50 p-4 rounded-lg mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Status</label><select v-model="filterStatus" class="border rounded px-3 py-2 w-full"><option value="all">Semua</option><option value="borrowed">Dipinjam</option><option value="returned">Dikembalikan</option></select></div>
                        <!-- Filter by Peminjam -->
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Peminjam</label><select v-model="filterUser" class="border rounded px-3 py-2 w-full"><option value="">Semua Guru</option><option v-for="g in guruList" :key="g.id" :value="g.id">{{ g.name }}</option></select></div>
                        <div class="grid grid-cols-2 gap-3 md:hidden">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label><input type="date" v-model="dateFrom" class="border rounded px-3 py-2 w-full"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label><input type="date" v-model="dateTo" class="border rounded px-3 py-2 w-full"></div>
                        </div>
                        <div class="hidden md:block"><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label><input type="date" v-model="dateFrom" class="border rounded px-3 py-2 w-full"></div>
                        <div class="hidden md:block"><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label><input type="date" v-model="dateTo" class="border rounded px-3 py-2 w-full"></div>
                        <div class="flex items-end gap-2 md:col-span-1">
                            <button @click="applyFilter" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex-1"><i class="fas fa-filter"></i> Filter</button>
                            <button @click="resetFilter" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 flex-1"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </div>

                    <div v-if="loading" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr><th>Kode Pinjam</th><th>ID</th><th>Tanggal</th><th>Status</th><th v-if="isAdmin || isOperator">Peminjam</th><th v-if="isAdmin || isOperator">Petugas</th><th>Ruang</th><th>Jml Item</th><th>Dikembalikan</th><th>Catatan</th><th>Bukti</th><th v-if="isAdmin || isOperator">Aksi</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in transactions" :key="t.id" class="border-b">
                                    <td>{{ t.borrow_code || '-' }}</td>
                                    <td>{{ t.id }}</td>
                                    <td>{{ formatDate(t.transaction_date) }}</td>
                                    <td><span :class="t.status === 'returned' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 py-1 text-xs rounded">{{ t.status === 'returned' ? 'Dikembalikan' : 'Dipinjam' }}</span></td>
                                    <td v-if="isAdmin || isOperator">{{ t.borrower || '-' }}</td>
                                    <td v-if="isAdmin || isOperator">{{ t.admin || '-' }}</td>
                                    <td>{{ t.room_name || '-' }}</td>
                                    <td>{{ t.total_items || 0 }}</td>
                                    <td>{{ t.returned_items || 0 }}</td>
                                    <td>{{ t.notes || '-' }}</td>
                                    <td>
                                        <button v-if="t.borrow_signature && t.borrow_signature !== 'dummy'" @click="viewSignature(t.borrow_signature, 'Tanda Tangan Peminjaman')" class="text-blue-600 hover:text-blue-800 mr-2" title="Tanda Tangan Peminjaman"><i class="fas fa-signature"></i></button>
                                        <button v-if="t.return_signature && t.return_signature !== 'dummy'" @click="viewSignature(t.return_signature, 'Tanda Tangan Pengembalian')" class="text-green-600 hover:text-green-800 mr-2" title="Tanda Tangan Pengembalian"><i class="fas fa-signature"></i></button>
                                        <button v-if="t.borrow_photo" @click="viewPhoto(t.borrow_photo, 'Foto Peminjaman')" class="text-purple-600 hover:text-purple-800 mr-2" title="Foto Peminjaman"><i class="fas fa-camera"></i></button>
                                        <button v-if="t.return_photo" @click="viewPhoto(t.return_photo, 'Foto Pengembalian')" class="text-orange-600 hover:text-orange-800" title="Foto Pengembalian"><i class="fas fa-camera-retro"></i></button>
                                    </td>
                                    <td v-if="isAdmin || isOperator">
                                        <!-- Tombol Edit (Detail) dan Delete -->
                                        <button @click="openEditTransactionDetail(t.id)" class="text-blue-600 hover:text-blue-800 mr-2" title="Edit Transaksi"><i class="fas fa-edit"></i></button>
                                        <button @click="openDeleteTransaction(t.id)" class="text-red-600 hover:text-red-800" title="Hapus Transaksi"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-if="showSignatureViewModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showSignatureViewModal = false">
                        <div class="bg-white rounded-lg p-6 max-w-md w-full text-center">
                            <h3 class="font-bold text-lg mb-3">{{ signatureViewTitle }}</h3>
                            <img :src="signatureViewData" class="border max-w-full h-auto" />
                            <button @click="showSignatureViewModal = false" class="mt-4 px-4 py-2 bg-gray-200 rounded">Tutup</button>
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const isAdmin = Vue.computed(() => app.isAdmin.value);
                const isOperator = Vue.computed(() => app.isOperator.value);
                const registerRefreshTransactions = app.registerRefreshTransactions;
                const viewPhoto = app.viewPhoto;
                const guruList = Vue.computed(() => app.guruList.value);
                const openEditTransactionDetail = app.openEditTransactionDetail;
                const openDeleteTransaction = app.openDeleteTransaction;
                
                const transactions = Vue.ref([]);
                const loading = Vue.ref(true);
                const showSignatureViewModal = Vue.ref(false);
                const signatureViewData = Vue.ref('');
                const signatureViewTitle = Vue.ref('');
                const filterStatus = Vue.ref('all');
                const dateFrom = Vue.ref('');
                const dateTo = Vue.ref('');
                const filterUser = Vue.ref('');

                async function fetchTransactions() {
                    loading.value = true;
                    let url = `?action=getTransactions&status=${filterStatus.value}`;
                    if (dateFrom.value) url += `&date_from=${dateFrom.value}`;
                    if (dateTo.value) url += `&date_to=${dateTo.value}`;
                    if (filterUser.value) url += `&user_id=${filterUser.value}`;
                    const res = await fetch(url);
                    transactions.value = await res.json();
                    loading.value = false;
                }

                function formatDate(d) { return new Date(d).toLocaleString('id-ID'); }
                function viewSignature(signature, title) { signatureViewData.value = signature; signatureViewTitle.value = title; showSignatureViewModal.value = true; }
                function applyFilter() { fetchTransactions(); }
                function resetFilter() { filterStatus.value = 'all'; dateFrom.value = ''; dateTo.value = ''; filterUser.value = ''; fetchTransactions(); }

                Vue.onMounted(() => { fetchTransactions(); registerRefreshTransactions(fetchTransactions); });

                return { isAdmin, isOperator, transactions, loading, formatDate, viewSignature, viewPhoto, showSignatureViewModal, signatureViewData, signatureViewTitle, filterStatus, dateFrom, dateTo, filterUser, guruList, applyFilter, resetFilter, openEditTransactionDetail, openDeleteTransaction };
            }
        };

        // =============== LAPORAN PER KODE PEMINJAMAN (baru dengan filter dan pagination) ===============
        const ReportByCode = {
            template: `
                <div class="pb-8"> <!-- Tambahkan padding bottom agar tidak tertutup bottom navbar -->
                    <!-- Filter Section -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
                            <input type="date" v-model="dateFrom" class="border rounded px-3 py-2 w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
                            <input type="date" v-model="dateTo" class="border rounded px-3 py-2 w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ruang</label>
                            <select v-model="roomId" class="border rounded px-3 py-2 w-full">
                                <option value="">Semua Ruang</option>
                                <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Peminjam</label>
                            <select v-model="userId" class="border rounded px-3 py-2 w-full">
                                <option value="">Semua Guru</option>
                                <option v-for="g in guruList" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button @click="applyFilter" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex-1"><i class="fas fa-search"></i> Filter</button>
                            <button @click="resetFilter" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 flex-1"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm">Item per halaman:</label>
                            <select v-model="limit" @change="changePage(1)" class="border rounded px-2 py-1 text-sm">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        <div class="flex gap-1 items-center">
                            <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50 text-sm">&laquo;</button>
                            <span class="px-3 py-1 text-sm">Halaman {{ currentPage }} dari {{ totalPages }}</span>
                            <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages || totalPages === 0" class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50 text-sm">&raquo;</button>
                        </div>
                    </div>

                    <div v-if="loading" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                    <div v-else-if="transactions.length === 0" class="text-center py-5 text-gray-500">Tidak ada data transaksi.</div>
                    <div v-else class="space-y-6">
                        <div v-for="t in transactions" :key="t.id" class="border rounded-lg p-4 bg-white shadow-sm">
                            <div class="flex flex-wrap gap-4 items-center mb-3 pb-2 border-b">
                                <div><span class="font-semibold">Kode Pinjam:</span> {{ t.borrow_code || '-' }}</div>
                                <div><span class="font-semibold">Peminjam:</span> {{ t.borrower || '-' }}</div>
                                <div><span class="font-semibold">Tanggal:</span> {{ formatDate(t.transaction_date) }}</div>
                                <div><span class="font-semibold">Ruang:</span> {{ t.room_name || '-' }}</div>
                                <div><span class="font-semibold">Status:</span> <span :class="t.status === 'returned' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 py-1 text-xs rounded">{{ t.status === 'returned' ? 'Dikembalikan' : 'Dipinjam' }}</span></div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="px-2 py-1 text-left">No</th>
                                            <th class="px-2 py-1 text-left">Kode Item</th>
                                            <th class="px-2 py-1 text-left">Nama Item</th>
                                            <th class="px-2 py-1 text-left">Status</th>
                                            <th class="px-2 py-1 text-left">Tgl Kembali</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, idx) in t.items" :key="item.item_id" class="border-b">
                                            <td class="px-2 py-1">{{ idx+1 }}</td>
                                            <td class="px-2 py-1">
                                                <a href="#" @click.prevent="openEditTransactionDetail(t.id)" class="item-link">{{ item.item_code }}</a>
                                            </td>
                                            <td class="px-2 py-1">{{ item.item_name }}</td>
                                            <td class="px-2 py-1"><span :class="item.status === 'returned' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 py-1 text-xs rounded">{{ item.status === 'returned' ? 'Dikembalikan' : 'Dipinjam' }}</span></td>
                                            <td class="px-2 py-1">{{ item.return_date ? formatDate(item.return_date) : '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination bottom -->
                    <div v-if="!loading && totalPages > 1" class="mt-6 flex justify-center gap-1 pb-4">
                        <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50">&laquo;</button>
                        <span class="px-3 py-1 border rounded bg-blue-50">Halaman {{ currentPage }} dari {{ totalPages }}</span>
                        <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50">&raquo;</button>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const guruList = Vue.computed(() => app.guruList.value);
                const rooms = Vue.computed(() => app.rooms.value);
                const openEditTransactionDetail = app.openEditTransactionDetail;

                const transactions = ref([]);
                const loading = ref(true);
                const total = ref(0);
                const currentPage = ref(1);
                const limit = ref(10);
                const dateFrom = ref('');
                const dateTo = ref('');
                const roomId = ref('');
                const userId = ref('');

                const totalPages = computed(() => Math.ceil(total.value / limit.value));

                async function fetchTransactions() {
                    loading.value = true;
                    let url = `?action=getFullTransactions&page=${currentPage.value}&limit=${limit.value}`;
                    if (dateFrom.value) url += `&date_from=${dateFrom.value}`;
                    if (dateTo.value) url += `&date_to=${dateTo.value}`;
                    if (roomId.value) url += `&room_id=${roomId.value}`;
                    if (userId.value) url += `&user_id=${userId.value}`;
                    
                    const res = await fetch(url);
                    const data = await res.json();
                    transactions.value = data.transactions || [];
                    total.value = data.total || 0;
                    loading.value = false;
                }

                function applyFilter() {
                    currentPage.value = 1;
                    fetchTransactions();
                }

                function resetFilter() {
                    dateFrom.value = '';
                    dateTo.value = '';
                    roomId.value = '';
                    userId.value = '';
                    currentPage.value = 1;
                    fetchTransactions();
                }

                function changePage(page) {
                    if (page < 1 || (page > totalPages.value && totalPages.value > 0)) return;
                    currentPage.value = page;
                    fetchTransactions();
                }

                function formatDate(d) {
                    if (!d) return '-';
                    return new Date(d).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' });
                }

                onMounted(() => {
                    fetchTransactions();
                });

                return {
                    transactions, loading, total, currentPage, limit, dateFrom, dateTo, roomId, userId,
                    guruList, rooms, totalPages, applyFilter, resetFilter, changePage, formatDate,
                    openEditTransactionDetail
                };
            }
        };

        // =============== LAPORAN (dengan tab) ===============
        const Report = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">Laporan Peminjaman Peralatan</h2>
                    
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="flex -mb-px">
                            <button @click="activeTab = 'monthly'" :class="activeTab === 'monthly' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="py-2 px-4 text-center border-b-2 font-medium text-sm">
                                DAFTAR PEMINJAMAN PERALATAN
                            </button>
                            <button @click="activeTab = 'bycode'" :class="activeTab === 'bycode' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="py-2 px-4 text-center border-b-2 font-medium text-sm">
                                Laporan Per Kode Peminjaman
                            </button>
                        </nav>
                    </div>

                    <!-- Konten Tab -->
                    <div v-if="activeTab === 'monthly'">
                        <!-- Konten laporan bulanan (sama seperti sebelumnya) -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-6 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label><select v-model="month" class="border rounded px-3 py-2 w-full"><option value="01">Januari</option><option value="02">Februari</option><option value="03">Maret</option><option value="04">April</option><option value="05">Mei</option><option value="06">Juni</option><option value="07">Juli</option><option value="08">Agustus</option><option value="09">September</option><option value="10">Oktober</option><option value="11">November</option><option value="12">Desember</option></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label><select v-model="year" class="border rounded px-3 py-2 w-full"><option v-for="y in years" :value="y">{{ y }}</option></select></div>
                            <div class="flex items-end gap-2"><button @click="fetchReport" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search"></i> Tampilkan</button><button @click="printReport" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print"></i> Cetak</button></div>
                        </div>

                        <div v-if="loading" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                        <div v-else-if="data.length === 0" class="text-center py-5 text-gray-500">Tidak ada data untuk bulan ini.</div>
                        <div v-else id="print-area" class="bg-white p-6 rounded-lg shadow">
                            <div class="text-center mb-6">
                                <h1 class="text-2xl font-bold">DAFTAR PEMINJAMAN PERALATAN</h1>
                                <p class="text-lg">SMKN 6 KOTA SERANG</p>
                                <p class="text-lg">TAHUN {{ year }}</p>
                                <p class="text-lg font-semibold">Bulan: {{ monthName }}</p>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100"><th class="border px-2 py-1">NO</th><th class="border px-2 py-1">NAMA PEMINJAM</th><th class="border px-2 py-1">TANGGAL</th><th class="border px-2 py-1" colspan="2">PERALATAN</th><th class="border px-2 py-1" colspan="2">PARAF</th><th class="border px-2 py-1">KETERANGAN</th></tr>
                                        <tr class="bg-gray-50"><th class="border"></th><th class="border"></th><th class="border"></th><th class="border px-2 py-1">KODE</th><th class="border px-2 py-1">ITEM</th><th class="border px-2 py-1">PINJAM</th><th class="border px-2 py-1">KEMBALI</th><th class="border"></th></tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in data" :key="row.no" class="hover:bg-gray-50">
                                            <td class="border px-2 py-1 text-center">{{ row.no }}</td>
                                            <td class="border px-2 py-1">{{ row.peminjam }}</td>
                                            <td class="border px-2 py-1">{{ row.tanggal }}</td>
                                            <td class="border px-2 py-1">{{ row.kode }}</td>
                                            <td class="border px-2 py-1">{{ row.item }}</td>
                                            <td class="border px-2 py-1 text-center">{{ row.paraf_pinjam }}</td>
                                            <td class="border px-2 py-1 text-center">{{ row.paraf_kembali }}</td>
                                            <td class="border px-2 py-1">{{ row.keterangan }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-8 text-right">
                                <p>Serang, {{ currentDate }}</p>
                                <p class="mt-8">Mengetahui/Menyetujui</p>
                                <p class="mt-12">( _____________________ )</p>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <ReportByCode />
                    </div>
                </div>
            `,
            setup() {
                const month = ref((new Date().getMonth() + 1).toString().padStart(2, '0'));
                const year = ref(new Date().getFullYear().toString());
                const years = ref([]);
                const data = ref([]);
                const loading = ref(false);
                const activeTab = ref('monthly'); // tab aktif

                for (let y = 2020; y <= 2030; y++) years.value.push(y.toString());
                const monthName = computed(() => { const names = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; return names[parseInt(month.value)-1]; });
                const currentDate = computed(() => { const d = new Date(); return d.toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }); });
                
                async function fetchReport() { 
                    loading.value = true; 
                    const res = await fetch(`?action=getReport&month=${month.value}&year=${year.value}`); 
                    const result = await res.json(); 
                    data.value = result.data; 
                    loading.value = false; 
                }
                function printReport() { window.print(); }

                onMounted(() => { fetchReport(); });

                return { month, year, years, monthName, currentDate, data, loading, fetchReport, printReport, activeTab };
            }
        };

        // =============== USER MANAGEMENT ===============
        const UserManagement = {
            template: `
                <div>
                    <h2 class="text-2xl font-bold mb-5">Manajemen User</h2>
                    <div class="mb-4"><button @click="openAddUser" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-plus"></i> Tambah User</button></div>
                    <div v-if="loadingUsers" class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <tr v-for="u in users" :key="u.id" class="border-b">
                                    <td>{{ u.id }}</td><td>{{ u.name }}</td><td>{{ u.email }}</td><td>{{ u.role }}</td>
                                    <td><button @click="openEditUser(u)" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></button><button @click="deleteUser(u.id)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `,
            setup() {
                const app = Vue.inject('app');
                const users = app.users;
                const loadingUsers = app.loadingUsers;
                const openAddUser = app.openAddUser;
                const openEditUser = app.openEditUser;
                const deleteUser = app.deleteUser;
                return { users, loadingUsers, openAddUser, openEditUser, deleteUser };
            }
        };

        // =============== REGISTER COMPONENTS ===============
        const app = createApp(App);
        app.component('Dashboard', Dashboard);
        app.component('CategoryBrand', CategoryBrand);
        app.component('ItemList', ItemList);
        app.component('BorrowProcess', BorrowProcess);
        app.component('ReturnProcess', ReturnProcess);
        app.component('TransactionHistory', TransactionHistory);
        app.component('Report', Report);
        app.component('ReportByCode', ReportByCode);
        app.component('UserManagement', UserManagement);
        app.mount('#app');
    </script>
    <?php endif; ?>
</body>
</html>
