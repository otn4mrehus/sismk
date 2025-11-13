<?php
// =============================================
// KONFIGURASI DAN KONEKSI DATABASE
// =============================================
session_start();
ob_start();

define('DB_HOST', 'mysql');
define('DB_USER', 'user');
define('DB_PASS', 'resu');
define('DB_NAME', 'XIITKJ-2');

// Cek koneksi database dan buat database/tabel jika belum ada
function initializeDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }
    
    // Cek apakah database sudah ada
    $dbExists = $conn->select_db(DB_NAME);
    
    if (!$dbExists) {
        // Buat database
        $conn->query("CREATE DATABASE ".DB_NAME);
        $conn->select_db(DB_NAME);
        
        // Buat tabel users
        $conn->query("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama VARCHAR(100) NOT NULL,
            role ENUM('admin','bendahara','siswa') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Buat tabel setoran
        $conn->query("CREATE TABLE setoran (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            jumlah INT NOT NULL,
            minggu_ke INT NOT NULL,
            tanggal DATE NOT NULL,
            keterangan VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // Buat tabel penggunaan
        $conn->query("CREATE TABLE penggunaan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            jumlah INT NOT NULL,
            tanggal DATE NOT NULL,
            keterangan VARCHAR(255) NOT NULL,
            user_id INT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // Insert admin default (admin/admin123)
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, nama, role) VALUES 
                      ('admin', '$password', 'Administrator', 'admin')");
        
        // Insert contoh data untuk demo
        $conn->query("INSERT INTO users (username, password, nama, role) VALUES 
                      ('bendahara1', '$password', 'Budi Santoso', 'bendahara'),
                      ('siswa1', '$password', 'Andi Wijaya', 'siswa'),
                      ('siswa2', '$password', 'Siti Rahma', 'siswa')");
        
        $conn->query("INSERT INTO setoran (user_id, jumlah, minggu_ke, tanggal, keterangan) VALUES
                      (3, 10000, 1, '".date('Y-m-d')."', 'Setoran minggu pertama'),
                      (3, 10000, 2, '".date('Y-m-d')."', 'Setoran minggu kedua'),
                      (4, 10000, 1, '".date('Y-m-d')."', 'Setoran minggu pertama')");
        
        $conn->query("INSERT INTO penggunaan (jumlah, tanggal, keterangan, user_id) VALUES
                      (50000, '".date('Y-m-d')."', 'Pembelian alat tulis', 1),
                      (30000, '".date('Y-m-d')."', 'Dana kegiatan kelas', 1)");
    }
    
    $conn->close();
}

// Panggil fungsi inisialisasi
initializeDatabase();

// Koneksi ke database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Koneksi database gagal: " . $db->connect_error);
}

// =============================================
// MODEL
// =============================================
class UserModel {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                return $user;
            }
        }
        return false;
    }
    
    public function getAllSiswa() {
        $result = $this->db->query("SELECT * FROM users WHERE role = 'siswa' ORDER BY nama");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getAllUsers() {
        $result = $this->db->query("SELECT * FROM users ORDER BY role, nama");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function addUser($username, $password, $nama, $role) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $passwordHash, $nama, $role);
        return $stmt->execute();
    }
    
    public function updateUser($id, $username, $nama, $role, $password = null) {
        if ($password) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET username = ?, nama = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $nama, $role, $passwordHash, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE users SET username = ?, nama = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $nama, $role, $id);
        }
        return $stmt->execute();
    }
    
    public function deleteUser($id) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function updateUsers($ids, $data) {
        $success = true;
        foreach ($ids as $id) {
            if (!$this->updateUser($id, $data['username'] ?? null, $data['nama'] ?? null, $data['role'] ?? null, $data['password'] ?? null)) {
                $success = false;
            }
        }
        return $success;
    }
}

class SetoranModel {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function addSetoran($user_id, $jumlah, $minggu_ke, $tanggal, $keterangan) {
        $stmt = $this->db->prepare("INSERT INTO setoran (user_id, jumlah, minggu_ke, tanggal, keterangan) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $user_id, $jumlah, $minggu_ke, $tanggal, $keterangan);
        return $stmt->execute();
    }
    
    public function updateSetoran($id, $user_id, $jumlah, $minggu_ke, $tanggal, $keterangan) {
        $stmt = $this->db->prepare("UPDATE setoran SET user_id = ?, jumlah = ?, minggu_ke = ?, tanggal = ?, keterangan = ? WHERE id = ?");
        $stmt->bind_param("iiissi", $user_id, $jumlah, $minggu_ke, $tanggal, $keterangan, $id);
        return $stmt->execute();
    }
    
    public function deleteSetoran($id) {
        $stmt = $this->db->prepare("DELETE FROM setoran WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function getSetoranById($id) {
        $stmt = $this->db->prepare("SELECT s.*, u.nama FROM setoran s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function getSetoranByUser($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM setoran WHERE user_id = ? ORDER BY tanggal DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getAllSetoran() {
        $result = $this->db->query("SELECT s.*, u.nama FROM setoran s JOIN users u ON s.user_id = u.id ORDER BY s.tanggal DESC, s.minggu_ke, u.nama");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getTotalSetoran() {
        $result = $this->db->query("SELECT SUM(jumlah) as total FROM setoran");
        $row = $result->fetch_assoc();
        return isset($row['total']) ? $row['total'] : 0;
    }
    
    public function addSetoranMulti($siswa_ids, $jumlah, $minggu_ke, $tanggal, $keterangan) {
        $success = true;
        foreach ($siswa_ids as $siswa_id) {
            if (!$this->addSetoran($siswa_id, $jumlah, $minggu_ke, $tanggal, $keterangan)) {
                $success = false;
            }
        }
        return $success;
    }
    
    public function updateSetorans($ids, $data) {
        $success = true;
        foreach ($ids as $id) {
            $currentData = $this->getSetoranById($id);
            if (!$currentData) continue;
            
            $user_id = $data['user_id'] ?? $currentData['user_id'];
            $jumlah = $data['jumlah'] ?? $currentData['jumlah'];
            $minggu_ke = $data['minggu_ke'] ?? $currentData['minggu_ke'];
            $tanggal = $data['tanggal'] ?? $currentData['tanggal'];
            $keterangan = $data['keterangan'] ?? $currentData['keterangan'];
            
            if (!$this->updateSetoran($id, $user_id, $jumlah, $minggu_ke, $tanggal, $keterangan)) {
                $success = false;
            }
        }
        return $success;
    }
    
    public function getSetoranByMinggu($minggu_ke) {
        $stmt = $this->db->prepare("SELECT s.*, u.nama FROM setoran s JOIN users u ON s.user_id = u.id WHERE s.minggu_ke = ? ORDER BY u.nama");
        $stmt->bind_param("i", $minggu_ke);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

class PenggunaanModel {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function addPenggunaan($jumlah, $tanggal, $keterangan, $user_id) {
        $stmt = $this->db->prepare("INSERT INTO penggunaan (jumlah, tanggal, keterangan, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $jumlah, $tanggal, $keterangan, $user_id);
        return $stmt->execute();
    }
    
    public function updatePenggunaan($id, $jumlah, $tanggal, $keterangan) {
        $stmt = $this->db->prepare("UPDATE penggunaan SET jumlah = ?, tanggal = ?, keterangan = ? WHERE id = ?");
        $stmt->bind_param("issi", $jumlah, $tanggal, $keterangan, $id);
        return $stmt->execute();
    }
    
    public function deletePenggunaan($id) {
        $stmt = $this->db->prepare("DELETE FROM penggunaan WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function getPenggunaanById($id) {
        $stmt = $this->db->prepare("SELECT p.*, u.nama FROM penggunaan p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function getAllPenggunaan() {
        $result = $this->db->query("SELECT p.*, u.nama FROM penggunaan p JOIN users u ON p.user_id = u.id ORDER BY p.tanggal DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getTotalPenggunaan() {
        $result = $this->db->query("SELECT SUM(jumlah) as total FROM penggunaan");
        $row = $result->fetch_assoc();
        return isset($row['total']) ? $row['total'] : 0;
    }
    
    public function updatePenggunaans($ids, $data) {
        $success = true;
        foreach ($ids as $id) {
            $currentData = $this->getPenggunaanById($id);
            if (!$currentData) continue;
            
            $jumlah = $data['jumlah'] ?? $currentData['jumlah'];
            $tanggal = $data['tanggal'] ?? $currentData['tanggal'];
            $keterangan = $data['keterangan'] ?? $currentData['keterangan'];
            
            if (!$this->updatePenggunaan($id, $jumlah, $tanggal, $keterangan)) {
                $success = false;
            }
        }
        return $success;
    }
    
    public function getPenggunaanByBulan($bulan, $tahun) {
        $stmt = $this->db->prepare("SELECT p.*, u.nama FROM penggunaan p JOIN users u ON p.user_id = u.id WHERE MONTH(p.tanggal) = ? AND YEAR(p.tanggal) = ? ORDER BY p.tanggal DESC");
        $stmt->bind_param("ii", $bulan, $tahun);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Inisialisasi model
$userModel = new UserModel($db);
$setoranModel = new SetoranModel($db);
$penggunaanModel = new PenggunaanModel($db);

// =============================================
// CONTROLLER
// =============================================
function handleLogin() {
    global $userModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Login normal dari database
        $user = $userModel->authenticate($username, $password);
        if ($user) {
            $_SESSION['user'] = $user;
            header("Location: index.php");
            exit();
        } else {
            return "Username atau password salah!";
        }
    }
    return null;
}

function handleLogout() {
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit();
    }
}

function handleAddSetoran() {
    global $setoranModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_setoran'])) {
        $siswa_ids = $_POST['siswa_ids'] ?? [];
        $jumlah = intval($_POST['jumlah'] ?? 0);
        $minggu_ke = intval($_POST['minggu_ke'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $keterangan = $_POST['keterangan'] ?? '';
        
        if (empty($siswa_ids)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Pilih setidaknya satu siswa!"];
            header("Location: index.php?page=setoran");
            exit();
        }
        
        if ($jumlah <= 0 || $minggu_ke <= 0) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Jumlah dan minggu ke harus lebih dari 0!"];
            header("Location: index.php?page=setoran");
            exit();
        }
        
        if (empty($tanggal)) {
            $tanggal = date('Y-m-d');
        }
        
        $success = true;
        $count = 0;
        foreach ($siswa_ids as $siswa_id) {
            if ($setoranModel->addSetoran($siswa_id, $jumlah, $minggu_ke, $tanggal, $keterangan)) {
                $count++;
            } else {
                $success = false;
            }
        }
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Berhasil menambahkan $count setoran!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menambahkan beberapa setoran!"];
        }
        header("Location: index.php?page=setoran");
        exit();
    }
    return null;
}

function handleUpdateSetoran() {
    global $setoranModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_setoran'])) {
        $id = intval($_POST['id'] ?? 0);
        $user_id = intval($_POST['siswa_id'] ?? 0);
        $jumlah = intval($_POST['jumlah'] ?? 0);
        $minggu_ke = intval($_POST['minggu_ke'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $keterangan = $_POST['keterangan'] ?? '';
        
        if ($jumlah <= 0 || $minggu_ke <= 0) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Jumlah dan minggu ke harus lebih dari 0!"];
            header("Location: index.php?page=setoran");
            exit();
        }
        
        if (empty($tanggal)) {
            $tanggal = date('Y-m-d');
        }
        
        $success = $setoranModel->updateSetoran($id, $user_id, $jumlah, $minggu_ke, $tanggal, $keterangan);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Setoran berhasil diperbarui!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal memperbarui setoran!"];
        }
        header("Location: index.php?page=setoran");
        exit();
    }
    return null;
}

function handleBulkUpdateSetoran() {
    global $setoranModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_setoran'])) {
        $ids = explode(',', $_POST['ids'] ?? '');
        $user_id = !empty($_POST['siswa_id']) ? intval($_POST['siswa_id']) : null;
        $jumlah = !empty($_POST['jumlah']) ? intval($_POST['jumlah']) : null;
        $minggu_ke = !empty($_POST['minggu_ke']) ? intval($_POST['minggu_ke']) : null;
        $tanggal = $_POST['tanggal'] ?? null;
        $keterangan = $_POST['keterangan'] ?? null;
        
        $data = [
            'user_id' => $user_id,
            'jumlah' => $jumlah,
            'minggu_ke' => $minggu_ke,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan
        ];
        
        // Hapus null values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        if (empty($data)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Tidak ada data yang diubah!"];
            header("Location: index.php?page=setoran");
            exit();
        }
        
        $success = $setoranModel->updateSetorans($ids, $data);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Setoran terpilih berhasil diperbarui!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal memperbarui beberapa setoran!"];
        }
        header("Location: index.php?page=setoran");
        exit();
    }
    return null;
}

function handleDeleteSetoran() {
    global $setoranModel;
    
    if (isset($_GET['delete_setoran'])) {
        $id = intval($_GET['delete_setoran'] ?? 0);
        
        $success = $setoranModel->deleteSetoran($id);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Setoran berhasil dihapus!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menghapus setoran!"];
        }
        header("Location: index.php?page=setoran");
        exit();
    }
    return null;
}

function handleAddPenggunaan() {
    global $penggunaanModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_penggunaan'])) {
        $jumlah = intval($_POST['jumlah'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $keterangan = $_POST['keterangan'] ?? '';
        $user_id = $_SESSION['user']['id'] ?? 0;
        
        if ($jumlah <= 0) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Jumlah harus lebih dari 0!"];
            header("Location: index.php?page=penggunaan");
            exit();
        }
        
        if (empty($keterangan)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Keterangan harus diisi!"];
            header("Location: index.php?page=penggunaan");
            exit();
        }
        
        if (empty($tanggal)) {
            $tanggal = date('Y-m-d');
        }
        
        $success = $penggunaanModel->addPenggunaan($jumlah, $tanggal, $keterangan, $user_id);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Penggunaan berhasil dicatat!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal mencatat penggunaan!"];
        }
        header("Location: index.php?page=penggunaan");
        exit();
    }
    return null;
}

function handleUpdatePenggunaan() {
    global $penggunaanModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_penggunaan'])) {
        $id = intval($_POST['id'] ?? 0);
        $jumlah = intval($_POST['jumlah'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $keterangan = $_POST['keterangan'] ?? '';
        
        if ($jumlah <= 0) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Jumlah harus lebih dari 0!"];
            header("Location: index.php?page=penggunaan");
            exit();
        }
        
        if (empty($keterangan)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Keterangan harus diisi!"];
            header("Location: index.php?page=penggunaan");
            exit();
        }
        
        if (empty($tanggal)) {
            $tanggal = date('Y-m-d');
        }
        
        $success = $penggunaanModel->updatePenggunaan($id, $jumlah, $tanggal, $keterangan);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Penggunaan berhasil diperbarui!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal memperbarui penggunaan!"];
        }
        header("Location: index.php?page=penggunaan");
        exit();
    }
    return null;
}

function handleBulkUpdatePenggunaan() {
    global $penggunaanModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_penggunaan'])) {
        $ids = explode(',', $_POST['ids'] ?? '');
        $jumlah = !empty($_POST['jumlah']) ? intval($_POST['jumlah']) : null;
        $tanggal = $_POST['tanggal'] ?? null;
        $keterangan = $_POST['keterangan'] ?? null;
        
        $data = [
            'jumlah' => $jumlah,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan
        ];
        
        // Hapus null values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        if (empty($data)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Tidak ada data yang diubah!"];
            header("Location: index.php?page=penggunaan");
            exit();
        }
        
        $success = $penggunaanModel->updatePenggunaans($ids, $data);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Penggunaan terpilih berhasil diperbarui!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal memperbarui beberapa penggunaan!"];
        }
        header("Location: index.php?page=penggunaan");
        exit();
    }
    return null;
}

function handleDeletePenggunaan() {
    global $penggunaanModel;
    
    if (isset($_GET['delete_penggunaan'])) {
        $id = intval($_GET['delete_penggunaan'] ?? 0);
        
        $success = $penggunaanModel->deletePenggunaan($id);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Penggunaan berhasil dihapus!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menghapus penggunaan!"];
        }
        header("Location: index.php?page=penggunaan");
        exit();
    }
    return null;
}

function handleAddUser() {
    global $userModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $role = $_POST['role'] ?? '';
        
        if (empty($username) || empty($password) || empty($nama) || empty($role)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Semua field harus diisi!"];
            header("Location: index.php?page=users");
            exit();
        }
        
        $success = $userModel->addUser($username, $password, $nama, $role);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "User berhasil ditambahkan!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menambahkan user! Username mungkin sudah digunakan."];
        }
        header("Location: index.php?page=users");
        exit();
    }
    return null;
}

function handleUpdateUser() {
    global $userModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
        $id = intval($_POST['id'] ?? 0);
        $username = $_POST['username'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? null;
        
        if (empty($username) || empty($nama) || empty($role)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Username, nama, dan role harus diisi!"];
            header("Location: index.php?page=users");
            exit();
        }
        
        $success = $userModel->updateUser($id, $username, $nama, $role, $password);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "User berhasil diperbarui!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal memperbarui user!"];
        }
        header("Location: index.php?page=users");
        exit();
    }
    return null;
}

function handleBulkUpdateUser() {
    global $userModel;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_user'])) {
        $ids = explode(',', $_POST['ids'] ?? '');
        $username = $_POST['username'] ?? null;
        $nama = $_POST['nama'] ?? null;
        $role = $_POST['role'] ?? null;
        $password = $_POST['password'] ?? null;
        
        $data = [
            'username' => $username,
            'nama' => $nama,
            'role' => $role,
            'password' => $password
        ];
        
        // Hapus null values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        if (empty($data)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Tidak ada data yang diubah!"];
            header("Location: index.php?page=users");
            exit();
        }
        
        $success = $userModel->updateUsers($ids, $data);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "User terpilih berhasil diperbarui!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal memperbarui beberapa user!"];
        }
        header("Location: index.php?page=users");
        exit();
    }
    return null;
}

function handleDeleteUser() {
    global $userModel;
    
    if (isset($_GET['delete_user'])) {
        $id = intval($_GET['delete_user'] ?? 0);
        
        // Cegah penghapusan diri sendiri
        if ($id == ($_SESSION['user']['id'] ?? 0)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Tidak dapat menghapus akun sendiri!"];
            header("Location: index.php?page=users");
            exit();
        }
        
        $success = $userModel->deleteUser($id);
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "User berhasil dihapus!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menghapus user!"];
        }
        header("Location: index.php?page=users");
        exit();
    }
    return null;
}

function handleBulkDeleteSetoran() {
    global $setoranModel;
    
    if (isset($_GET['bulk_delete_setoran'])) {
        $ids = explode(',', $_GET['bulk_delete_setoran'] ?? '');
        $success = true;
        foreach ($ids as $id) {
            if (!$setoranModel->deleteSetoran(intval($id))) {
                $success = false;
            }
        }
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Setoran terpilih berhasil dihapus!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menghapus beberapa setoran!"];
        }
        header("Location: index.php?page=setoran");
        exit();
    }
    return null;
}

function handleBulkDeletePenggunaan() {
    global $penggunaanModel;
    
    if (isset($_GET['bulk_delete_penggunaan'])) {
        $ids = explode(',', $_GET['bulk_delete_penggunaan'] ?? '');
        $success = true;
        foreach ($ids as $id) {
            if (!$penggunaanModel->deletePenggunaan(intval($id))) {
                $success = false;
            }
        }
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "Penggunaan terpilih berhasil dihapus!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menghapus beberapa penggunaan!"];
        }
        header("Location: index.php?page=penggunaan");
        exit();
    }
    return null;
}

function handleBulkDeleteUser() {
    global $userModel;
    
    if (isset($_GET['bulk_delete_user'])) {
        $ids = explode(',', $_GET['bulk_delete_user'] ?? '');
        $currentUserId = $_SESSION['user']['id'] ?? 0;
        $success = true;
        
        // Filter out current user from deletion
        $ids = array_filter($ids, function($id) use ($currentUserId) {
            return intval($id) != $currentUserId;
        });
        
        if (empty($ids)) {
            $_SESSION['message'] = ["type" => "danger", "message" => "Tidak dapat menghapus akun sendiri!"];
            header("Location: index.php?page=users");
            exit();
        }
        
        foreach ($ids as $id) {
            if (!$userModel->deleteUser(intval($id))) {
                $success = false;
            }
        }
        
        if ($success) {
            $_SESSION['message'] = ["type" => "success", "message" => "User terpilih berhasil dihapus!"];
        } else {
            $_SESSION['message'] = ["type" => "danger", "message" => "Gagal menghapus beberapa user!"];
        }
        header("Location: index.php?page=users");
        exit();
    }
    return null;
}

// Proses semua aksi
$error = handleLogin();
handleLogout();

// Handle messages from session
$sessionMessage = isset($_SESSION['message']) ? $_SESSION['message'] : null;

// Handle actions based on request method and parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_setoran']) || isset($_POST['update_setoran']) || isset($_POST['bulk_update_setoran'])) {
        handleAddSetoran();
        handleUpdateSetoran();
        handleBulkUpdateSetoran();
    }
    
    if (isset($_POST['tambah_penggunaan']) || isset($_POST['update_penggunaan']) || isset($_POST['bulk_update_penggunaan'])) {
        handleAddPenggunaan();
        handleUpdatePenggunaan();
        handleBulkUpdatePenggunaan();
    }
    
    if (isset($_POST['tambah_user']) || isset($_POST['update_user']) || isset($_POST['bulk_update_user'])) {
        handleAddUser();
        handleUpdateUser();
        handleBulkUpdateUser();
    }
} else {
    // Handle GET requests for deletions
    handleDeleteSetoran();
    handleDeletePenggunaan();
    handleDeleteUser();
    handleBulkDeleteSetoran();
    handleBulkDeletePenggunaan();
    handleBulkDeleteUser();
}

// =============================================
// VIEW
// =============================================
function renderHeader() {
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manajemen Kas Kelas</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            body {
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
                color: #333;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 15px;
            }
            header {
                background-color: #4CAF50;
                color: white;
                padding: 15px 0;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .header-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .logo {
                font-size: 1.5rem;
                font-weight: bold;
            }
            nav ul {
                display: flex;
                list-style: none;
                margin: 0;
                padding: 0;
            }
            nav ul li {
                margin-left: 15px;
            }
            nav ul li a {
                color: white;
                text-decoration: none;
                padding: 5px 10px;
                border-radius: 3px;
                transition: background-color 0.3s;
            }
            nav ul li a:hover {
                background-color: rgba(255,255,255,0.2);
            }
            .card {
                background-color: white;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                padding: 15px;
                margin-bottom: 15px;
            }
            .card-title {
                margin-top: 0;
                color: #4CAF50;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th, td {
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            input, select, textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            button, .btn {
                background-color: #4CAF50;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-size: 14px;
            }
            .btn-edit {
                background-color: #2196F3;
            }
            .btn-delete {
                background-color: #f44336;
            }
            .btn-secondary {
                background-color: #6c757d;
            }
            button:hover, .btn:hover {
                opacity: 0.9;
            }
            .alert {
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 4px;
                border: 1px solid transparent;
            }
            .alert-success {
                background-color: #dff0d8;
                color: #3c763d;
                border-color: #d6e9c6;
            }
            .alert-danger {
                background-color: #f2dede;
                color: #a94442;
                border-color: #ebccd1;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }
            .stat-card {
                background-color: white;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                padding: 15px;
                text-align: center;
            }
            .stat-value {
                font-size: 1.5rem;
                font-weight: bold;
                color: #4CAF50;
            }
            .stat-label {
                color: #777;
            }
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fefefe;
                margin: 10% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 600px;
                border-radius: 5px;
            }
            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .close:hover {
                color: black;
            }
            .action-buttons {
                display: flex;
                gap: 5px;
            }
            .checkbox-cell {
                width: 20px;
                text-align: center;
            }
            .bulk-actions {
                margin-bottom: 15px;
                display: flex;
                gap: 10px;
            }
            .siswa-list {
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #ddd;
                padding: 10px;
                border-radius: 4px;
                text-align: left;
            }
            .siswa-item {
                display: flex;
                align-items: center;
                padding: 5px 0;
                border-bottom: 1px solid #eee;
            }
            .siswa-item:last-child {
                border-bottom: none;
            }
            .siswa-item input[type="checkbox"] {
                margin-right: 10px;
            }
            .filter-form {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }
            @media (max-width: 768px) {
                .header-content {
                    flex-direction: column;
                    text-align: center;
                }
                nav ul {
                    margin-top: 10px;
                    justify-content: center;
                }
                nav ul li {
                    margin: 0 5px;
                }
                .stats {
                    grid-template-columns: 1fr;
                }
                .action-buttons {
                    flex-direction: column;
                }
                .bulk-actions {
                    flex-direction: column;
                }
                .filter-form {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
    HTML;
}

function renderFooter() {
    echo <<<HTML
        </div>
        <script>
            // Fungsi untuk menampilkan modal
            function openModal(modalId) {
                document.getElementById(modalId).style.display = 'block';
            }
            
            // Fungsi untuk menutup modal
            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
            }
            
            // Fungsi untuk mengisi form edit dengan data yang ada
            function fillEditForm(data, formId) {
                for (const key in data) {
                    const input = document.querySelector(`#${formId} [name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            input.checked = data[key];
                        } else {
                            input.value = data[key];
                        }
                    }
                }
            }
            
            // Fungsi untuk menangani edit data
            function handleEdit(type, id) {
                fetch(`index.php?get_${type}=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        fillEditForm(data, `${type}_form`);
                        openModal(`edit_${type}_modal`);
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        alert('Gagal mengambil data untuk diedit');
                    });
            }
            
            // Fungsi untuk menangani bulk action
            function handleBulkAction(type, action) {
                const checkboxes = document.querySelectorAll("." + type + "-checkbox:checked");
                if (checkboxes.length === 0) {
                    alert('Pilih setidaknya satu item!');
                    return;
                }
                
                const ids = Array.from(checkboxes).map(checkbox => checkbox.value);
                
                if (action === 'delete') {
                    if (confirm("Apakah Anda yakin ingin menghapus " + checkboxes.length + " item?")) {
                        window.location.href = "index.php?bulk_delete_" + type + "=" + ids.join(',');
                    }
                } else if (action === 'edit') {
                    document.getElementById('bulk_ids').value = ids.join(',');
                    openModal('bulk_edit_' + type + '_modal');
                }
            }
            
            // Tutup modal ketika klik di luar konten modal
            window.onclick = function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // Fungsi untuk memilih semua siswa
            function toggleSelectAllSiswa() {
                const selectAll = document.getElementById('select_all_siswa');
                const checkboxes = document.querySelectorAll('.siswa-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
            }
            
            // Inisialisasi setelah halaman dimuat
            document.addEventListener('DOMContentLoaded', function() {
                // Tangani penutupan modal dengan tombol close
                document.querySelectorAll('.close').forEach(button => {
                    button.addEventListener('click', function() {
                        const modalId = this.closest('.modal').id;
                        closeModal(modalId);
                    });
                });
                
                // Select all checkbox di tabel
                const selectAllCheckboxes = document.querySelectorAll('[id^="select_all_"]');
                selectAllCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const type = this.id.replace('select_all_', '');
                        const checkboxes = document.querySelectorAll('.' + type + '-checkbox');
                        checkboxes.forEach(cb => {
                            cb.checked = this.checked;
                        });
                    });
                });
            });
        </script>
    </body>
    </html>
    HTML;
}

function renderLoginForm($error = null) {
    $errorDisplay = $error ? '<div class="alert alert-danger">'.$error.'</div>' : '';
    echo <<<HTML
    <div class="container">
        <div class="card" style="max-width: 400px; margin: 50px auto;">
            <h2 class="card-title">Login</h2>
            {$errorDisplay}
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login">Login</button>
            </form>
            <div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 4px;">
                <small><strong>Demo Account:</strong><br>
                Admin: admin / admin123<br>
                Bendahara: bendahara1 / admin123<br>
                Siswa: siswa1 / admin123</small>
            </div>
        </div>
    </div>
    HTML;
}

function renderDashboard($user, $setoranModel, $penggunaanModel) {
    $totalSetoran = $setoranModel->getTotalSetoran();
    $totalPenggunaan = $penggunaanModel->getTotalPenggunaan();
    $saldo = $totalSetoran - $totalPenggunaan;
    
    $userRole = isset($user['role']) ? $user['role'] : '';
    $userName = isset($user['nama']) ? $user['nama'] : '';
    
    // Generate navigation menu based on user role
    $navMenu = '<li><a href="index.php">Dashboard</a></li>';
    if ($userRole === 'admin' || $userRole === 'bendahara') {
        $navMenu .= '<li><a href="index.php?page=setoran">Setoran</a></li>';
        $navMenu .= '<li><a href="index.php?page=penggunaan">Penggunaan</a></li>';
    }
    if ($userRole === 'admin') {
        $navMenu .= '<li><a href="index.php?page=users">Manajemen User</a></li>';
    }
    $navMenu .= '<li><a href="index.php?logout=1">Logout</a></li>';
    
    // Format mata uang Indonesia
    $formattedTotalSetoran = 'Rp ' . number_format($totalSetoran, 0, ',', '.') . ',-';
    $formattedTotalPenggunaan = 'Rp ' . number_format($totalPenggunaan, 0, ',', '.') . ',-';
    $formattedSaldo = 'Rp ' . number_format($saldo, 0, ',', '.') . ',-';
    
    echo <<<HTML
    <header>
        <div class="container header-content">
            <div class="logo">Manajemen Kas Kelas</div>
            <nav>
                <ul>
                    {$navMenu}
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h1>Selamat datang, {$userName}!</h1>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value">{$formattedTotalSetoran}</div>
                <div class="stat-label">Total Setoran</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$formattedTotalPenggunaan}</div>
                <div class="stat-label">Total Penggunaan</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$formattedSaldo}</div>
                <div class="stat-label">Saldo Kas</div>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title">Riwayat Setoran Anda</h2>
            <table>
                <thead>
                    <tr>
                        <th>Minggu Ke</th>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
    HTML;
    
    $userId = isset($user['id']) ? $user['id'] : 0;
    $setoranSaya = $setoranModel->getSetoranByUser($userId);
    if (empty($setoranSaya)) {
        echo '<tr><td colspan="4" style="text-align: center;">Belum ada data setoran</td></tr>';
    } else {
        foreach ($setoranSaya as $setoran) {
            $formattedJumlah = 'Rp ' . number_format($setoran['jumlah'], 0, ',', '.') . ',-';
            echo "<tr>
                    <td>{$setoran['minggu_ke']}</td>
                    <td>{$setoran['tanggal']}</td>
                    <td>{$formattedJumlah}</td>
                    <td>{$setoran['keterangan']}</td>
                  </tr>";
        }
    }
    
    echo <<<HTML
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2 class="card-title">Riwayat Penggunaan Kas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
    HTML;
    
    $allPenggunaan = $penggunaanModel->getAllPenggunaan();
    if (empty($allPenggunaan)) {
        echo '<tr><td colspan="4" style="text-align: center;">Belum ada data penggunaan</td></tr>';
    } else {
        foreach ($allPenggunaan as $penggunaan) {
            $formattedJumlah = 'Rp ' . number_format($penggunaan['jumlah'], 0, ',', '.') . ',-';
            echo "<tr>
                    <td>{$penggunaan['tanggal']}</td>
                    <td>{$formattedJumlah}</td>
                    <td>{$penggunaan['keterangan']}</td>
                    <td>{$penggunaan['nama']}</td>
                  </tr>";
        }
    }
    
    echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
    HTML;
}

function renderSetoranPage($userModel, $setoranModel, $message = null) {
    $allSetoran = $setoranModel->getAllSetoran();
    $siswaList = $userModel->getAllSiswa();
    
    $messageDisplay = $message ? '<div class="alert alert-'.$message['type'].'">'.$message['message'].'</div>' : '';
    
    $currentUser = $_SESSION['user'];
    $isAdmin = ($currentUser['role'] === 'admin');
    $isBendahara = ($currentUser['role'] === 'bendahara');
    
    // Tentukan tombol yang ditampilkan berdasarkan peran - ADMIN dan BENDHARA bisa akses
    $tambahButton = ($isAdmin || $isBendahara) ? 
        '<button type="submit" name="tambah_setoran">Simpan Setoran</button>' : '';
    
    $bulkActions = ($isAdmin || $isBendahara) ? 
        '<div class="bulk-actions">
            <button type="button" class="btn btn-edit" onclick="handleBulkAction(\'setoran\', \'edit\')">
                <i class="fas fa-edit"></i> Edit Terpilih
            </button>
            <button type="button" class="btn btn-delete" onclick="handleBulkAction(\'setoran\', \'delete\')">
                <i class="fas fa-trash"></i> Hapus Terpilih
            </button>
        </div>' : '';
    
    echo <<<HTML
    <header>
        <div class="container header-content">
            <div class="logo">Manajemen Kas Kelas</div>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="index.php?page=setoran">Setoran</a></li>
                    <li><a href="index.php?page=penggunaan">Penggunaan</a></li>
    HTML;
    
    if ($isAdmin) {
        echo '<li><a href="index.php?page=users">Manajemen User</a></li>';
    }
    
    echo <<<HTML
                    <li><a href="index.php?logout=1">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h1>Manajemen Setoran Kas</h1>
        
        {$messageDisplay}
        
        <div class="card">
            <h2 class="card-title">Tambah Setoran</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Siswa</label>
                    <div class="siswa-list">
                        <div class="siswa-item">
                            <input type="checkbox" id="select_all_siswa" onchange="toggleSelectAllSiswa()">
                            <label for="select_all_siswa">Pilih Semua</label>
                        </div>
    HTML;
    
    foreach ($siswaList as $siswa) {
        echo '<div class="siswa-item">
                <input type="checkbox" name="siswa_ids[]" class="siswa-checkbox" value="'.$siswa['id'].'" id="siswa_'.$siswa['id'].'">
                <label for="siswa_'.$siswa['id'].'">'.$siswa['nama'].'</label>
              </div>';
    }
    
    echo <<<HTML
                    </div>
                </div>
                <div class="form-group">
                    <label for="minggu_ke">Minggu Ke</label>
                    <input type="number" id="minggu_ke" name="minggu_ke" min="1" required>
                </div>
                <div class="form-group">
                    <label for="jumlah">Jumlah (Rp)</label>
                    <input type="number" id="jumlah" name="jumlah" min="1000" required>
                </div>
                <div class="form-group">
                    <label for="tanggal">Tanggal Setoran</label>
                    <input type="date" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" placeholder="Contoh: Setoran minggu pertama">
                </div>
                {$tambahButton}
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">Daftar Setoran Siswa</h2>
            {$bulkActions}
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell"><input type="checkbox" id="select_all_setoran"></th>
                        <th>Nama Siswa</th>
                        <th>Minggu Ke</th>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
    HTML;
    
    if ($isAdmin || $isBendahara) {
        echo '<th>Aksi</th>';
    }
    
    echo <<<HTML
                    </tr>
                </thead>
                <tbody>
    HTML;
    
    if (empty($allSetoran)) {
        $colspan = ($isAdmin || $isBendahara) ? 7 : 6;
        echo '<tr><td colspan="'.$colspan.'" style="text-align: center;">Belum ada data setoran</td></tr>';
    } else {
        foreach ($allSetoran as $setoran) {
            $formattedJumlah = 'Rp ' . number_format($setoran['jumlah'], 0, ',', '.') . ',-';
            
            // Tombol aksi berdasarkan peran
            $actionButtons = ($isAdmin || $isBendahara) ? 
                '<td class="action-buttons">
                    <button class="btn btn-edit" onclick="handleEdit(\'setoran\', '.$setoran['id'].')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <a href="index.php?delete_setoran='.$setoran['id'].'" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus setoran ini?\')">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </td>' : '';
            
            echo "<tr>
                    <td class='checkbox-cell'><input type='checkbox' class='setoran-checkbox' value='{$setoran['id']}'></td>
                    <td>{$setoran['nama']}</td>
                    <td>{$setoran['minggu_ke']}</td>
                    <td>{$setoran['tanggal']}</td>
                    <td>{$formattedJumlah}</td>
                    <td>{$setoran['keterangan']}</td>
                    {$actionButtons}
                  </tr>";
        }
    }
    
    echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Edit Setoran -->
    <div id="edit_setoran_modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('edit_setoran_modal')">&times;</span>
            <h2>Edit Setoran</h2>
            <form id="setoran_form" method="POST">
                <input type="hidden" name="id">
                <div class="form-group">
                    <label for="siswa_id">Siswa</label>
                    <select id="siswa_id" name="siswa_id" required>
                        <option value="">Pilih Siswa</option>
    HTML;
    
    foreach ($siswaList as $siswa) {
        echo "<option value='{$siswa['id']}'>{$siswa['nama']}</option>";
    }
    
    echo <<<HTML
                    </select>
                </div>
                <div class="form-group">
                    <label for="minggu_ke">Minggu Ke</label>
                    <input type="number" id="minggu_ke" name="minggu_ke" min="1" required>
                </div>
                <div class="form-group">
                    <label for="jumlah">Jumlah (Rp)</label>
                    <input type="number" id="jumlah" name="jumlah" min="1000" required>
                </div>
                <div class="form-group">
                    <label for="tanggal">Tanggal Setoran</label>
                    <input type="date" id="tanggal" name="tanggal" required>
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan">
                </div>
                <button type="submit" name="update_setoran" class="btn">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit_setoran_modal')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Bulk Edit Setoran -->
    <div id="bulk_edit_setoran_modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('bulk_edit_setoran_modal')">&times;</span>
            <h2>Bulk Edit Setoran</h2>
            <form id="bulk_setoran_form" method="POST">
                <input type="hidden" name="ids" id="bulk_ids">
                <div class="form-group">
                    <label for="bulk_siswa_id">Siswa</label>
                    <select id="bulk_siswa_id" name="siswa_id">
                        <option value="">-- Tidak Diubah --</option>
    HTML;
    
    foreach ($siswaList as $siswa) {
        echo "<option value='{$siswa['id']}'>{$siswa['nama']}</option>";
    }
    
    echo <<<HTML
                    </select>
                </div>
                <div class="form-group">
                    <label for="bulk_minggu_ke">Minggu Ke (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="number" id="bulk_minggu_ke" name="minggu_ke" min="1">
                </div>
                <div class="form-group">
                    <label for="bulk_jumlah">Jumlah (Rp) (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="number" id="bulk_jumlah" name="jumlah" min="1000">
                </div>
                <div class="form-group">
                    <label for="bulk_tanggal">Tanggal (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="date" id="bulk_tanggal" name="tanggal">
                </div>
                <div class="form-group">
                    <label for="bulk_keterangan">Keterangan (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="text" id="bulk_keterangan" name="keterangan">
                </div>
                <button type="submit" name="bulk_update_setoran" class="btn">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulk_edit_setoran_modal')">Batal</button>
            </form>
        </div>
    </div>
    HTML;
}

function renderPenggunaanPage($penggunaanModel, $message = null) {
    $allPenggunaan = $penggunaanModel->getAllPenggunaan();
    
    $messageDisplay = $message ? '<div class="alert alert-'.$message['type'].'">'.$message['message'].'</div>' : '';
    
    $currentUser = $_SESSION['user'];
    $isAdmin = ($currentUser['role'] === 'admin');
    $isBendahara = ($currentUser['role'] === 'bendahara');
    
    // Tentukan tombol yang ditampilkan berdasarkan peran - ADMIN dan BENDHARA bisa akses
    $tambahButton = ($isAdmin || $isBendahara) ? 
        '<button type="submit" name="tambah_penggunaan">Simpan Penggunaan</button>' : '';
    
    $bulkActions = ($isAdmin || $isBendahara) ? 
        '<div class="bulk-actions">
            <button type="button" class="btn btn-edit" onclick="handleBulkAction(\'penggunaan\', \'edit\')">
                <i class="fas fa-edit"></i> Edit Terpilih
            </button>
            <button type="button" class="btn btn-delete" onclick="handleBulkAction(\'penggunaan\', \'delete\')">
                <i class="fas fa-trash"></i> Hapus Terpilih
            </button>
        </div>' : '';
    
    echo <<<HTML
    <header>
        <div class="container header-content">
            <div class="logo">Manajemen Kas Kelas</div>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="index.php?page=setoran">Setoran</a></li>
                    <li><a href="index.php?page=penggunaan">Penggunaan</a></li>
    HTML;
    
    if ($isAdmin) {
        echo '<li><a href="index.php?page=users">Manajemen User</a></li>';
    }
    
    echo <<<HTML
                    <li><a href="index.php?logout=1">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h1>Manajemen Penggunaan Kas</h1>
        
        {$messageDisplay}
        
        <div class="card">
            <h2 class="card-title">Tambah Penggunaan Kas</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="jumlah">Jumlah (Rp)</label>
                    <input type="number" id="jumlah" name="jumlah" min="1000" required>
                </div>
                <div class="form-group">
                    <label for="tanggal">Tanggal Penggunaan</label>
                    <input type="date" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" rows="3" required placeholder="Contoh: Pembelian alat tulis kelas"></textarea>
                </div>
                {$tambahButton}
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">Daftar Penggunaan Kas</h2>
            {$bulkActions}
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell"><input type="checkbox" id="select_all_penggunaan"></th>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                        <th>Dicatat Oleh</th>
    HTML;
    
    if ($isAdmin || $isBendahara) {
        echo '<th>Aksi</th>';
    }
    
    echo <<<HTML
                    </tr>
                </thead>
                <tbody>
    HTML;
    
    if (empty($allPenggunaan)) {
        $colspan = ($isAdmin || $isBendahara) ? 6 : 5;
        echo '<tr><td colspan="'.$colspan.'" style="text-align: center;">Belum ada data penggunaan</td></tr>';
    } else {
        foreach ($allPenggunaan as $penggunaan) {
            $formattedJumlah = 'Rp ' . number_format($penggunaan['jumlah'], 0, ',', '.') . ',-';
            
            // Tombol aksi berdasarkan peran
            $actionButtons = ($isAdmin || $isBendahara) ? 
                '<td class="action-buttons">
                    <button class="btn btn-edit" onclick="handleEdit(\'penggunaan\', '.$penggunaan['id'].')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <a href="index.php?delete_penggunaan='.$penggunaan['id'].'" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus penggunaan ini?\')">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </td>' : '';
            
            echo "<tr>
                    <td class='checkbox-cell'><input type='checkbox' class='penggunaan-checkbox' value='{$penggunaan['id']}'></td>
                    <td>{$penggunaan['tanggal']}</td>
                    <td>{$formattedJumlah}</td>
                    <td>{$penggunaan['keterangan']}</td>
                    <td>{$penggunaan['nama']}</td>
                    {$actionButtons}
                  </tr>";
        }
    }
    
    echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Edit Penggunaan -->
    <div id="edit_penggunaan_modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('edit_penggunaan_modal')">&times;</span>
            <h2>Edit Penggunaan</h2>
            <form id="penggunaan_form" method="POST">
                <input type="hidden" name="id">
                <div class="form-group">
                    <label for="jumlah">Jumlah (Rp)</label>
                    <input type="number" id="jumlah" name="jumlah" min="1000" required>
                </div>
                <div class="form-group">
                    <label for="tanggal">Tanggal Penggunaan</label>
                    <input type="date" id="tanggal" name="tanggal" required>
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" rows="3" required></textarea>
                </div>
                <button type="submit" name="update_penggunaan" class="btn">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit_penggunaan_modal')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Bulk Edit Penggunaan -->
    <div id="bulk_edit_penggunaan_modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('bulk_edit_penggunaan_modal')">&times;</span>
            <h2>Bulk Edit Penggunaan</h2>
            <form id="bulk_penggunaan_form" method="POST">
                <input type="hidden" name="ids" id="bulk_ids">
                <div class="form-group">
                    <label for="bulk_jumlah">Jumlah (Rp) (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="number" id="bulk_jumlah" name="jumlah" min="1000">
                </div>
                <div class="form-group">
                    <label for="bulk_tanggal">Tanggal (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="date" id="bulk_tanggal" name="tanggal">
                </div>
                <div class="form-group">
                    <label for="bulk_keterangan">Keterangan (biarkan kosong jika tidak ingin mengubah)</label>
                    <textarea id="bulk_keterangan" name="keterangan" rows="3"></textarea>
                </div>
                <button type="submit" name="bulk_update_penggunaan" class="btn">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulk_edit_penggunaan_modal')">Batal</button>
            </form>
        </div>
    </div>
    HTML;
}

function renderUsersPage($userModel, $message = null) {
    $allUsers = $userModel->getAllUsers();
    
    $messageDisplay = $message ? '<div class="alert alert-'.$message['type'].'">'.$message['message'].'</div>' : '';
    
    $currentUser = $_SESSION['user'];
    $isAdmin = ($currentUser['role'] === 'admin');
    
    // Tentukan tombol yang ditampilkan berdasarkan peran - hanya ADMIN bisa akses
    $tambahButton = $isAdmin ? 
        '<button type="submit" name="tambah_user">Simpan User</button>' : '';
    
    $bulkActions = $isAdmin ? 
        '<div class="bulk-actions">
            <button type="button" class="btn btn-edit" onclick="handleBulkAction(\'user\', \'edit\')">
                <i class="fas fa-edit"></i> Edit Terpilih
            </button>
            <button type="button" class="btn btn-delete" onclick="handleBulkAction(\'user\', \'delete\')">
                <i class="fas fa-trash"></i> Hapus Terpilih
            </button>
        </div>' : '';
    
    echo <<<HTML
    <header>
        <div class="container header-content">
            <div class="logo">Manajemen Kas Kelas</div>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="index.php?page=setoran">Setoran</a></li>
                    <li><a href="index.php?page=penggunaan">Penggunaan</a></li>
                    <li><a href="index.php?page=users">Manajemen User</a></li>
                    <li><a href="index.php?logout=1">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h1>Manajemen User</h1>
        
        {$messageDisplay}
        
        <div class="card">
            <h2 class="card-title">Tambah User Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="bendahara">Bendahara</option>
                        <option value="siswa">Siswa</option>
                    </select>
                </div>
                {$tambahButton}
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">Daftar User</h2>
            {$bulkActions}
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell"><input type="checkbox" id="select_all_users"></th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Tanggal Dibuat</th>
    HTML;
    
    if ($isAdmin) {
        echo '<th>Aksi</th>';
    }
    
    echo <<<HTML
                    </tr>
                </thead>
                <tbody>
    HTML;
    
    if (empty($allUsers)) {
        $colspan = $isAdmin ? 6 : 5;
        echo '<tr><td colspan="'.$colspan.'" style="text-align: center;">Belum ada data user</td></tr>';
    } else {
        foreach ($allUsers as $user) {
            // Tombol aksi berdasarkan peran
            $actionButtons = $isAdmin ? 
                '<td class="action-buttons">
                    <button class="btn btn-edit" onclick="handleEdit(\'user\', '.$user['id'].')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <a href="index.php?delete_user='.$user['id'].'" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus user ini?\')">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </td>' : '';
            
            echo "<tr>
                    <td class='checkbox-cell'><input type='checkbox' class='user-checkbox' value='{$user['id']}'></td>
                    <td>{$user['username']}</td>
                    <td>{$user['nama']}</td>
                    <td>{$user['role']}</td>
                    <td>{$user['created_at']}</td>
                    {$actionButtons}
                  </tr>";
        }
    }
    
    echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Edit User -->
    <div id="edit_user_modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('edit_user_modal')">&times;</span>
            <h2>Edit User</h2>
            <form id="user_form" method="POST">
                <input type="hidden" name="id">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="password" id="password" name="password">
                </div>
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="bendahara">Bendahara</option>
                        <option value="siswa">Siswa</option>
                    </select>
                </div>
                <button type="submit" name="update_user" class="btn">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit_user_modal')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Bulk Edit User -->
    <div id="bulk_edit_user_modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('bulk_edit_user_modal')">&times;</span>
            <h2>Bulk Edit User</h2>
            <form id="bulk_user_form" method="POST">
                <input type="hidden" name="ids" id="bulk_ids">
                <div class="form-group">
                    <label for="bulk_username">Username (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="text" id="bulk_username" name="username">
                </div>
                <div class="form-group">
                    <label for="bulk_password">Password (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="password" id="bulk_password" name="password">
                </div>
                <div class="form-group">
                    <label for="bulk_nama">Nama Lengkap (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="text" id="bulk_nama" name="nama">
                </div>
                <div class="form-group">
                    <label for="bulk_role">Role (biarkan kosong jika tidak ingin mengubah)</label>
                    <select id="bulk_role" name="role">
                        <option value="">-- Tidak Diubah --</option>
                        <option value="admin">Admin</option>
                        <option value="bendahara">Bendahara</option>
                        <option value="siswa">Siswa</option>
                    </select>
                </div>
                <button type="submit" name="bulk_update_user" class="btn">Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulk_edit_user_modal')">Batal</button>
            </form>
        </div>
    </div>
    HTML;
}

// =============================================
// ROUTING
// =============================================
renderHeader();

if (!isset($_SESSION['user'])) {
    // Tampilkan halaman login
    renderLoginForm($error);
} else {
    $user = $_SESSION['user'];
    $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
    
    // Ambil pesan dari session jika ada
    $sessionMessage = isset($_SESSION['message']) ? $_SESSION['message'] : null;
    unset($_SESSION['message']);
    
    // Handle data fetching for modals
    if (isset($_GET['get_setoran'])) {
        $id = (int)$_GET['get_setoran'];
        $data = $setoranModel->getSetoranById($id);
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Data tidak ditemukan']);
            exit();
        }
    }
    
    if (isset($_GET['get_penggunaan'])) {
        $id = (int)$_GET['get_penggunaan'];
        $data = $penggunaanModel->getPenggunaanById($id);
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Data tidak ditemukan']);
            exit();
        }
    }
    
    if (isset($_GET['get_user'])) {
        $id = (int)$_GET['get_user'];
        $data = $userModel->getUserById($id);
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Data tidak ditemukan']);
            exit();
        }
    }
    
    switch ($page) {
        case 'setoran':
            // Admin dan bendahara bisa akses setoran
            if (isset($user['role']) && ($user['role'] === 'admin' || $user['role'] === 'bendahara')) {
                renderSetoranPage($userModel, $setoranModel, $sessionMessage);
            } else {
                header("Location: index.php");
                exit();
            }
            break;
            
        case 'penggunaan':
            // Admin dan bendahara bisa akses penggunaan
            if (isset($user['role']) && ($user['role'] === 'admin' || $user['role'] === 'bendahara')) {
                renderPenggunaanPage($penggunaanModel, $sessionMessage);
            } else {
                header("Location: index.php");
                exit();
            }
            break;
            
        case 'users':
            // Hanya admin yang bisa akses manajemen user
            if (isset($user['role']) && $user['role'] === 'admin') {
                renderUsersPage($userModel, $sessionMessage);
            } else {
                header("Location: index.php");
                exit();
            }
            break;
            
        default:
            renderDashboard($user, $setoranModel, $penggunaanModel);
            break;
    }
}

renderFooter();

// Tutup koneksi database
$db->close();
?>
