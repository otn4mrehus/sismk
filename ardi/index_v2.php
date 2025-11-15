<?php
session_start();
ob_start();

// =============================================
// KONFIGURASI DAN INISIALISASI
// =============================================

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ardi');
define('BASE_URL', 'http://siardi.smknegeri6kotaserang.my.id');

// Path upload berdasarkan jenis
define('UPLOAD_BASE_PATH', __DIR__ . '/uploads/');
define('ARSIP_PATH', UPLOAD_BASE_PATH . 'arsip/');
define('PHOTO_PATH', UPLOAD_BASE_PATH . 'photo/');
define('BACKUP_PATH', __DIR__ . '/backups/');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); // Aktifkan untuk debugging

// Global error handler untuk menangkap error yang tidak tertangaki
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    log_error("PHP Error: $errstr in $errfile on line $errline", [
        'errno' => $errno,
        'file' => $errfile,
        'line' => $errline
    ]);
    
    // Jika request AJAX, kembalikan JSON error
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Terjadi kesalahan sistem']);
        exit;
    }
});

// Buat folder uploads dan backups jika belum ada
$folders = [UPLOAD_BASE_PATH, ARSIP_PATH, PHOTO_PATH, BACKUP_PATH];
foreach ($folders as $folder) {
    if (!is_dir($folder)) mkdir($folder, 0777, true);
}

// Koneksi Database
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// =============================================
// FUNGSI UTILITY
// =============================================

// Fungsi Keamanan
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fungsi format file size
function format_file_size($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Fungsi badge level
function get_level_badge($level) {
    $badges = [
        'admin' => 'danger',
        'kepsek' => 'primary',
        'wakasek' => 'info',
        'kaprog' => 'success',
        'kabeng' => 'warning',
        'guru' => 'secondary',
        'siswa' => 'light',
        'tu' => 'dark'
    ];
    return $badges[$level] ?? 'secondary';
}

// Fungsi untuk membangun URL pagination
function build_pagination_url($new_params = []) {
    $current_params = $_GET;
    
    // Gabungkan parameter baru dengan yang sudah ada
    foreach ($new_params as $key => $value) {
        $current_params[$key] = $value;
    }
    
    // Hapus parameter yang tidak diperlukan
    unset($current_params['action']);
    
    return 'index.php?action=' . $_GET['action'] . '&' . http_build_query($current_params);
}

// FUNGSI UPLOAD YANG DIPERBAIKI - DENGAN STRUKTUR FOLDER BARU
function handle_file_upload($file, $jenis = 'arsip', $user_id = null, $tahun_periode = null, $kategori_nama = null, $jenis_nama = null, $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']) {
    global $pdo;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error dalam upload file'];
    }
    
    // Dapatkan nama user dari database
    $nama_user = 'unknown';
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // Format nama: ubah spasi menjadi underscore dan hapus karakter khusus
            $nama_user = preg_replace('/[^a-zA-Z0-9]/', '_', $user['nama_lengkap']);
            $nama_user = trim($nama_user, '_');
        }
    }
    
    // Tentukan path berdasarkan jenis
    $base_path = UPLOAD_BASE_PATH;
    switch($jenis) {
        case 'photo':
            $base_path = PHOTO_PATH;
            $allowed_types = ['jpg', 'jpeg', 'png']; // Untuk photo hanya image
            break;
        default:
            $base_path = ARSIP_PATH;
    }
    
    // PERBAIKAN: Gunakan tahun dari form, jika tidak ada gunakan tahun saat ini
    $tahun = !empty($tahun_periode) ? $tahun_periode : date('Y');
    $bulan = date('m');
    
    // PERBAIKAN: Struktur folder baru - uploads/arsip/[TAHUN]/[BULAN]/[NAMA_USER]/[KATEGORI]/[JENIS]/
    // Bersihkan nama kategori dan jenis untuk digunakan dalam path
    $kategori_folder = 'umum';
    $jenis_folder = 'lainnya';
    
    if ($kategori_nama) {
        $kategori_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $kategori_nama);
        $kategori_folder = strtolower(trim($kategori_folder, '_'));
    }
    
    if ($jenis_nama) {
        $jenis_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $jenis_nama);
        $jenis_folder = strtolower(trim($jenis_folder, '_'));
    }
    
    // Buat path lengkap berdasarkan jenis
    if ($jenis == 'arsip') {
        $jenis_path = $base_path . $tahun . '/' . $bulan . '/' . $nama_user . '/' . $kategori_folder . '/' . $jenis_folder . '/';
    } else {
        // Untuk photo tetap menggunakan struktur lama
        $jenis_path = $base_path . $tahun . '/' . $bulan . '/' . $nama_user . '/';
    }
    
    if (!is_dir($jenis_path)) {
        mkdir($jenis_path, 0777, true);
    }
    
    // Generate nama file yang unik dan aman
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_name);
    $filename = $tahun . $bulan . '_' . uniqid() . '_' . $safe_filename . '.' . $file_extension;
    $target_path = $jenis_path . $filename;
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowed_types)];
    }
    
    // Set max size berdasarkan jenis
    $max_size = ($jenis == 'photo') ? (2 * 1024 * 1024) : (10 * 1024 * 1024); // 2MB untuk photo, 10MB untuk lainnya
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file terlalu besar (max ' . ($max_size / 1024 / 1024) . 'MB)'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // PERBAIKAN: Simpan path relatif untuk akses web dengan struktur baru
        if ($jenis == 'arsip') {
            $relative_path = 'uploads/arsip/' . $tahun . '/' . $bulan . '/' . $nama_user . '/' . $kategori_folder . '/' . $jenis_folder . '/' . $filename;
        } else {
            $relative_path = 'uploads/photo/' . $tahun . '/' . $bulan . '/' . $nama_user . '/' . $filename;
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $file_extension,
            'path' => $jenis_path,
            'full_path' => $target_path,
            'web_path' => $relative_path, // Path untuk akses web
            'user_folder' => $nama_user,
            'tahun_used' => $tahun,
            'kategori_folder' => $kategori_folder,
            'jenis_folder' => $jenis_folder
        ];
    }
    
    return ['success' => false, 'error' => 'Gagal mengupload file'];
}

// Fungsi untuk mendapatkan path file berdasarkan jenis dan filename
function get_file_path($filename, $jenis = 'arsip') {
    // Cari file di semua subdirectory berdasarkan jenis
    $base_path = UPLOAD_BASE_PATH;
    switch($jenis) {
        case 'photo':
            $base_path = PHOTO_PATH;
            break;
        default:
            $base_path = ARSIP_PATH;
    }
    
    // PERBAIKAN: Cari file di semua subdirectory dengan struktur baru
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $filename) {
            return $file->getPathname();
        }
    }
    
    return null;
}

// Fungsi untuk mendapatkan URL file untuk akses web
function get_file_url($filename, $jenis = 'arsip') {
    // Cari file di semua subdirectory berdasarkan jenis
    $base_path = UPLOAD_BASE_PATH;
    switch($jenis) {
        case 'photo':
            $base_path = PHOTO_PATH;
            break;
        default:
            $base_path = ARSIP_PATH;
    }
    
    // PERBAIKAN: Cari file di semua subdirectory dengan struktur baru
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $filename) {
            // Dapatkan path relatif dari root website
            $absolute_path = $file->getPathname();
            $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $absolute_path);
            
            // Pastikan path menggunakan forward slash
            $relative_path = str_replace('\\', '/', $relative_path);
            
            return BASE_URL . $relative_path;
        }
    }
    
    return null;
}

// Fungsi Autentikasi
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function check_access($required_levels) {
    if (!is_logged_in()) {
        header('Location: index.php?action=login');
        exit;
    }
    
    if (is_array($required_levels)) {
        if (!in_array($_SESSION['level'], $required_levels)) {
            $_SESSION['error'] = "Akses ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
    } else {
        if ($_SESSION['level'] != $required_levels) {
            $_SESSION['error'] = "Akses ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
    }
}

// Fungsi Logging
function log_audit($user_id, $action, $description = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch(PDOException $e) {
        // Silent fail untuk audit trail
    }
}

// =============================================
// INISIALISASI DATABASE
// =============================================

function initialize_database($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            nama_lengkap VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            level ENUM('admin','kepsek','wakasek','kaprog','kabeng','guru','siswa','tu') NOT NULL,
            foto_profil VARCHAR(255),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        
        "CREATE TABLE IF NOT EXISTS kategori (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_kategori VARCHAR(100) NOT NULL,
            deskripsi TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB",
        
        "CREATE TABLE IF NOT EXISTS jenis_dokumen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kategori_id INT,
            nama_jenis VARCHAR(100) NOT NULL,
            deskripsi TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB",
        
        // Di dalam array $tables, perbarui CREATE TABLE untuk dokumen:

        "CREATE TABLE IF NOT EXISTS dokumen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(255) NOT NULL,
            deskripsi TEXT,
            nama_file VARCHAR(255) NULL,  -- Diubah menjadi NULL
            nama_file_asli VARCHAR(255) NULL,  -- Diubah menjadi NULL
            ukuran_file BIGINT,
            tipe_file VARCHAR(100),
            kategori_id INT,
            jenis_id INT,
            user_id INT,
            akses ENUM('public','private','shared') DEFAULT 'private',
            tahun_periode YEAR,
            download_count INT DEFAULT 0,
            jenis_upload ENUM('file','link') DEFAULT 'file',  -- Kolom baru
            file_url VARCHAR(500) NULL,  -- Kolom baru
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL,
            FOREIGN KEY (jenis_id) REFERENCES jenis_dokumen(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        // TABEL BARU UNTUK SHARING DOKUMEN
        "CREATE TABLE IF NOT EXISTS dokumen_sharing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dokumen_id INT NOT NULL,
            user_id INT NOT NULL,
            can_edit BOOLEAN DEFAULT FALSE,
            shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_dokumen_user (dokumen_id, user_id)
        ) ENGINE=InnoDB",
        
        "CREATE TABLE IF NOT EXISTS download_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dokumen_id INT,
            user_id INT,
            downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        "CREATE TABLE IF NOT EXISTS audit_trail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB",

        // TABEL UNTUK PENGATURAN MODUL
        "CREATE TABLE IF NOT EXISTS modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_name VARCHAR(100) NOT NULL UNIQUE,
            module_description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            required_level ENUM('admin','kepsek','wakasek','kaprog','kabeng','guru','siswa','tu') DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS user_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module_id INT NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_module (user_id, module_id)
        ) ENGINE=InnoDB"
    ];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec($table);
        } catch(PDOException $e) {
            // Skip error jika tabel sudah ada
        }
    }
    
    // Insert default data dengan penanganan error yang lebih baik
    try {
        $check_admin = $pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'")->fetchColumn();
        if ($check_admin == 0) {
            $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, email, level) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', $hashed_password, 'Administrator Sistem', 'admin@smkn6.sch.id', 'admin']);
            
            // Insert default kategori
            $default_categories = [
                ['Surat Resmi', 'Surat-surat resmi sekolah'],
                ['Surat Keputusan', 'Surat keputusan kepala sekolah'],
                ['Surat Tugas', 'Surat penugasan guru dan staff'],
                ['Dokumen Akademik', 'Dokumen terkait kegiatan akademik'],
                ['Dokumen Administrasi', 'Dokumen administrasi sekolah']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, deskripsi, created_by) VALUES (?, ?, ?)");
            foreach ($default_categories as $category) {
                try {
                    $stmt->execute([$category[0], $category[1], 1]);
                } catch(PDOException $e) {
                    // Skip jika kategori sudah ada
                    continue;
                }
            }
            
            // Insert default jenis dokumen
            $default_jenis = [
                [2, 'SK Kepala Sekolah', 'Surat Keputusan Kepala Sekolah'],
                [2, 'SK Panitia', 'Surat Keputusan Pembentukan Panitia'],
                [3, 'ST Guru', 'Surat Tugas untuk Guru'],
                [3, 'ST Siswa', 'Surat Tugas untuk Siswa'],
                [4, 'RPP', 'Rencana Pelaksanaan Pembelajaran'],
                [4, 'Modul Ajar', 'Modul pembelajaran'],
                [5, 'Laporan Keuangan', 'Laporan keuangan sekolah'],
                [5, 'Administrasi Guru', 'Dokumen administrasi guru']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO jenis_dokumen (kategori_id, nama_jenis, deskripsi, created_by) VALUES (?, ?, ?, ?)");
            foreach ($default_jenis as $jenis) {
                try {
                    $stmt->execute([$jenis[0], $jenis[1], $jenis[2], 1]);
                } catch(PDOException $e) {
                    // Skip jika jenis sudah ada
                    continue;
                }
            }

            // Insert default modules
            $default_modules = [
                ['dashboard', 'Dashboard Sistem', 'admin'],
                ['arsip', 'Manajemen Arsip Digital', 'guru'],
                ['users', 'Manajemen Pengguna', 'admin'],
                ['kategori', 'Manajemen Kategori & Jenis', 'admin'],
                ['laporan', 'Laporan dan Statistik', 'kepsek'],
                ['backup_restore', 'Backup dan Restore Database', 'admin'],
                ['pengaturan_modul', 'Pengaturan Modul Menu', 'admin']
            ];

            $stmt = $pdo->prepare("INSERT IGNORE INTO modules (module_name, module_description, required_level) VALUES (?, ?, ?)");
            foreach ($default_modules as $module) {
                try {
                    $stmt->execute([$module[0], $module[1], $module[2]]);
                } catch(PDOException $e) {
                    // Skip jika module sudah ada
                    continue;
                }
            }

            // Aktifkan modul untuk semua user yang sudah ada berdasarkan level mereka
            $users = $pdo->query("SELECT id, level FROM users")->fetchAll(PDO::FETCH_ASSOC);
            $modules = $pdo->query("SELECT id, required_level FROM modules")->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("INSERT IGNORE INTO user_modules (user_id, module_id, is_active) VALUES (?, ?, ?)");

            foreach ($users as $user) {
                foreach ($modules as $module) {
                    $is_active = ($user['level'] == 'admin') ? true : ($user['level'] == $module['required_level']);
                    try {
                        $stmt->execute([$user['id'], $module['id'], $is_active]);
                    } catch(PDOException $e) {
                        // Skip jika sudah ada
                        continue;
                    }
                }
            }

            // Aktifkan semua modul untuk admin
            $module_ids = $pdo->query("SELECT id FROM modules")->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_modules (user_id, module_id, is_active) VALUES (1, ?, TRUE)");
            foreach ($module_ids as $module_id) {
                try {
                    $stmt->execute([$module_id]);
                } catch(PDOException $e) {
                    // Skip jika sudah ada
                    continue;
                }
            }
        }
    } catch(PDOException $e) {
        // Log error tetapi jangan hentikan eksekusi
        error_log("Error during database initialization: " . $e->getMessage());
    }
}

// Panggil inisialisasi database
initialize_database($pdo);

// =============================================
// FUNGSI CEK AKSES MODUL
// =============================================

function check_module_access($module_name) {
    global $pdo;
    
    if (!is_logged_in()) {
        return false;
    }
    
    // Admin selalu memiliki akses ke semua modul
    if ($_SESSION['level'] == 'admin' || $_SESSION['level'] == 'tu') {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT um.is_active 
                              FROM user_modules um 
                              JOIN modules m ON um.module_id = m.id 
                              WHERE um.user_id = ? AND m.module_name = ? AND m.is_active = TRUE");
        $stmt->execute([$_SESSION['user_id'], $module_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Hanya mengandalkan pengaturan modul menu, tidak ada default berdasarkan level
        return $result && (bool)$result['is_active'];
        
    } catch(PDOException $e) {
        error_log("Module access check error: " . $e->getMessage());
        return false;
    }
}

// =============================================
// FUNGSI CEK AKSES DOKUMEN
// =============================================

function check_document_access($dokumen_id, $require_edit = false) {
    global $pdo;
    
    if (!is_logged_in()) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $user_level = $_SESSION['level'];
    
    // Admin selalu memiliki akses penuh
    if ($user_level == 'admin' || $user_level == 'tu') {
        return true;
    }
    
    try {
        // Cek apakah user adalah pemilik dokumen
        $stmt = $pdo->prepare("SELECT user_id, akses FROM dokumen WHERE id = ?");
        $stmt->execute([$dokumen_id]);
        $dokumen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dokumen) {
            return false;
        }
        
        // Jika user adalah pemilik dokumen
        if ($dokumen['user_id'] == $user_id) {
            return true;
        }
        
        // Jika dokumen public
        if ($dokumen['akses'] == 'public') {
            return !$require_edit; // Public hanya bisa view, tidak bisa edit
        }
        
        // Jika dokumen shared, cek hak akses di tabel dokumen_sharing
        if ($dokumen['akses'] == 'shared') {
            $stmt = $pdo->prepare("SELECT can_edit FROM dokumen_sharing WHERE dokumen_id = ? AND user_id = ?");
            $stmt->execute([$dokumen_id, $user_id]);
            $sharing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sharing) {
                if ($require_edit) {
                    return (bool)$sharing['can_edit'];
                }
                return true; // Bisa view
            }
        }
        
        return false;
        
    } catch(PDOException $e) {
        error_log("Document access check error: " . $e->getMessage());
        return false;
    }
}

// =============================================
// ROUTING DAN CONTROLLER
// =============================================

$action = $_GET['action'] ?? 'dashboard';

// Handle actions
switch($action) {
    case 'login':
        handle_login();
        break;
    case 'logout':
        handle_logout();
        break;
    case 'dashboard':
        show_dashboard();
        break;
    case 'arsip':
        if (!check_module_access('arsip')) {
            $_SESSION['error'] = "Akses modul arsip ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        show_arsip();
        break;

    case 'upload':
        if (check_module_access('arsip')) {
            handle_upload();
        } else {
            $_SESSION['error'] = "Akses modul arsip ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'edit_file':
        if (check_module_access('arsip')) {
            handle_edit_file();
        } else {
            $_SESSION['error'] = "Akses modul arsip ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'download':
        handle_download();
        break;
    case 'preview':
        show_preview();
        break;
    case 'delete_file':
        if (check_module_access('arsip')) {
            handle_delete_file();
        } else {
            $_SESSION['error'] = "Akses modul arsip ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'users':
        if (check_module_access('users')) {
            manage_users();
        } else {
            $_SESSION['error'] = "Akses modul users ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'edit_user':
        if (check_module_access('users')) {
            handle_edit_user();
        } else {
            $_SESSION['error'] = "Akses modul users ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'delete_user':
        if (check_module_access('users')) {
            handle_delete_user();
        } else {
            $_SESSION['error'] = "Akses modul users ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'kategori':
        if (check_module_access('kategori')) {
            manage_kategori();
        } else {
            $_SESSION['error'] = "Akses modul kategori ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'edit_kategori':
        if (check_module_access('kategori')) {
            handle_edit_kategori();
        } else {
            $_SESSION['error'] = "Akses modul kategori ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'delete_kategori':
        if (check_module_access('kategori')) {
            handle_delete_kategori();
        } else {
            $_SESSION['error'] = "Akses modul kategori ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'edit_jenis':
        if (check_module_access('kategori')) {
            handle_edit_jenis();
        } else {
            $_SESSION['error'] = "Akses modul kategori ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'delete_jenis':
        if (check_module_access('kategori')) {
            handle_delete_jenis();
        } else {
            $_SESSION['error'] = "Akses modul kategori ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'profile':
        show_profile();
        break;
    case 'update_profile':
        handle_update_profile();
        break;
    case 'change_password':
        handle_change_password();
        break;
    case 'laporan':
        if (check_module_access('laporan')) {
            show_laporan();
        } else {
            $_SESSION['error'] = "Akses modul laporan ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'export_excel':
        if (check_module_access('laporan')) {
            export_excel();
        } else {
            $_SESSION['error'] = "Akses modul laporan ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'export_pdf':
        if (check_module_access('laporan')) {
            export_pdf();
        } else {
            $_SESSION['error'] = "Akses modul laporan ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'backup_restore':
        if (check_module_access('backup_restore')) {
            show_backup_restore();
        } else {
            $_SESSION['error'] = "Akses modul backup & restore ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'backup_database':
        if (check_module_access('backup_restore')) {
            handle_backup_database();
        } else {
            $_SESSION['error'] = "Akses modul backup & restore ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'restore_database':
        if (check_module_access('backup_restore')) {
            handle_restore_database();
        } else {
            $_SESSION['error'] = "Akses modul backup & restore ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'pengaturan_modul':
        if (check_module_access('pengaturan_modul')) {
            manage_pengaturan_modul();
        } else {
            $_SESSION['error'] = "Akses modul pengaturan ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'update_module_status':
        if (check_module_access('pengaturan_modul')) {
            handle_update_module_status();
        } else {
            $_SESSION['error'] = "Akses modul pengaturan ditolak!";
            header('Location: index.php?action=dashboard');
            exit;
        }
        break;
    case 'get_file_data':
        get_file_data();
        break;
    case 'get_user_data':
        get_user_data();
        break;
    case 'get_kategori_data':
        get_kategori_data();
        break;
    case 'get_jenis_data':
        get_jenis_data();
        break;
    case 'get_shared_users':
        get_shared_users();
        break;
    case 'get_kategori_stats':
        get_kategori_stats();
        break;
    case 'get_user_level_stats':
        get_user_level_stats();
        break;
    case 'get_download_monthly_stats':
        get_download_monthly_stats();
        break;
    default:
        show_dashboard();
}

// =============================================
// HANDLER FUNCTIONS - AUTHENTICATION
// =============================================

function handle_login() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=login');
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['level'] = $user['level'];
            $_SESSION['foto_profil'] = $user['foto_profil'];
            $_SESSION['email'] = $user['email'];
            
            log_audit($user['id'], 'LOGIN', 'User berhasil login');
            
            $_SESSION['success'] = "Login berhasil! Selamat datang " . $user['nama_lengkap'];
            header('Location: index.php?action=dashboard');
            exit;
        } else {
            log_audit(null, 'LOGIN_FAILED', "Percobaan login gagal untuk username: $username");
            $_SESSION['error'] = "Username atau password salah!";
            header('Location: index.php?action=login');
            exit;
        }
    }
    
    show_login_page();
}

function handle_logout() {
    if (isset($_SESSION['user_id'])) {
        log_audit($_SESSION['user_id'], 'LOGOUT', 'User berhasil logout');
    }
    
    session_destroy();
    header('Location: index.php?action=login');
    exit;
}

// =============================================
// HANDLER FUNCTIONS - DASHBOARD
// =============================================

function handle_update_module_status() {
    global $pdo;
    
    // Pastikan hanya mengembalikan JSON
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        // Pastikan user sudah login dan memiliki akses
        if (!is_logged_in() || $_SESSION['level'] != 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
            exit;
        }
        
        $user_id = (int)$_POST['user_id'] ?? 0;
        $module_id = (int)$_POST['module_id'] ?? 0;
        $is_active = (int)$_POST['is_active'] ?? 0;
        
        // Validasi input
        if ($user_id <= 0 || $module_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Data tidak valid']);
            exit;
        }
        
        // Check if user exists and is not admin
        try {
            $stmt = $pdo->prepare("SELECT level FROM users WHERE id = ? AND is_active = TRUE");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User tidak ditemukan']);
                exit;
            }
            
            if ($user['level'] == 'admin') {
                echo json_encode(['success' => false, 'error' => 'Tidak dapat mengubah akses modul untuk admin']);
                exit;
            }
            
            // Check if module exists
            $stmt = $pdo->prepare("SELECT id FROM modules WHERE id = ? AND is_active = TRUE");
            $stmt->execute([$module_id]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$module) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Modul tidak ditemukan']);
                exit;
            }
            
            // Update atau insert user module access
            $stmt = $pdo->prepare("
                INSERT INTO user_modules (user_id, module_id, is_active) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE is_active = ?
            ");
            $stmt->execute([$user_id, $module_id, $is_active, $is_active]);
            
            log_audit($_SESSION['user_id'], 'UPDATE_MODULE_ACCESS', 
                     "Update akses modul: User $user_id, Module $module_id, Status: " . ($is_active ? 'Aktif' : 'Nonaktif'));
            
            echo json_encode(['success' => true, 'message' => 'Akses modul berhasil diperbarui']);
            
        } catch(PDOException $e) {
            error_log("Module access update error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Terjadi kesalahan database: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metode request tidak valid']);
    }
    exit;
}

// Fungsi untuk handle JSON response dengan better error handling
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    
    // Pastikan tidak ada output sebelumnya
    if (ob_get_length()) {
        ob_clean();
    }
    
    echo json_encode($data);
    exit;
}

// Fungsi untuk log error
function log_error($message, $context = []) {
    error_log("ERROR: " . $message . " Context: " . json_encode($context));
}

function show_dashboard() {
    global $pdo;
    
    if (!is_logged_in()) {
        header('Location: index.php?action=login');
        exit;
    }
    
    // Statistik berdasarkan level user
    $user_id = $_SESSION['user_id'];
    $user_level = $_SESSION['level'];
    
    if ($user_level == 'admin') {
        $total_arsip = $pdo->query("SELECT COUNT(*) FROM dokumen")->fetchColumn();
        $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = TRUE")->fetchColumn();
        $total_downloads = $pdo->query("SELECT COUNT(*) FROM download_log")->fetchColumn();
    } else {
        // Hitung dokumen yang bisa diakses user (milik sendiri, public, atau shared)
        $total_arsip = $pdo->query("
            SELECT COUNT(DISTINCT d.id) 
            FROM dokumen d 
            LEFT JOIN dokumen_sharing ds ON d.id = ds.dokumen_id 
            WHERE d.user_id = $user_id 
            OR d.akses = 'public' 
            OR (d.akses = 'shared' AND ds.user_id = $user_id)
        ")->fetchColumn();
        
        $total_users = 0;
        $total_downloads = $pdo->query("SELECT COUNT(*) FROM download_log WHERE user_id = $user_id")->fetchColumn();
    }
    
    // Grafik download per hari (7 hari terakhir)
    $download_stats = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        if ($user_level == 'admin') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM download_log WHERE DATE(downloaded_at) = ?");
            $stmt->execute([$date]);
            $count = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM download_log WHERE DATE(downloaded_at) = ? AND user_id = ?");
            $stmt->execute([$date, $user_id]);
            $count = $stmt->fetchColumn();
        }
        $download_stats[] = [
            'date' => $date,
            'count' => $count
        ];
    }
    
    // Dokumen terbaru yang bisa diakses user
    if ($user_level == 'admin') {
        $recent_docs = $pdo->query("SELECT d.*, u.nama_lengkap, k.nama_kategori, j.nama_jenis 
                                   FROM dokumen d 
                                   LEFT JOIN users u ON d.user_id = u.id 
                                   LEFT JOIN kategori k ON d.kategori_id = k.id 
                                   LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id 
                                   ORDER BY d.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT DISTINCT d.*, u.nama_lengkap, k.nama_kategori, j.nama_jenis 
            FROM dokumen d 
            LEFT JOIN users u ON d.user_id = u.id 
            LEFT JOIN kategori k ON d.kategori_id = k.id 
            LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id 
            LEFT JOIN dokumen_sharing ds ON d.id = ds.dokumen_id 
            WHERE d.user_id = ? OR d.akses = 'public' OR (d.akses = 'shared' AND ds.user_id = ?)
            ORDER BY d.created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id, $user_id]);
        $recent_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    show_header('Dashboard');
    ?>
    <div class="dashboard">
        <div class="welcome-section">
            <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h2>
            <p>Sistem Manajemen Arsip Digital SMKN 6 Kota Serang</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Arsip</h3>
                    <div class="stat-number"><?= $total_arsip ?></div>
                </div>
            </div>
            
            <?php if ($_SESSION['level'] == 'admin'): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Pengguna</h3>
                    <div class="stat-number"><?= $total_users ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Download</h3>
                    <div class="stat-number"><?= $total_downloads ?></div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="chart-section">
                <div class="section-header">
                    <h3>Statistik Download (7 Hari Terakhir)</h3>
                </div>
                <div class="chart-container">
                    <canvas id="downloadChart"></canvas>
                </div>
            </div>
            
            <div class="recent-docs">
                <div class="section-header">
                    <h3>Dokumen Terbaru</h3>
                    <a href="index.php?action=arsip" class="btn btn-outline">Lihat Semua</a>
                </div>
                <div class="documents-list">
                    <?php if (empty($recent_docs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>Belum ada dokumen</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_docs as $doc): ?>
                            <div class="doc-item" style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #ddd; padding: 10px; margin-bottom: 8px; border-radius: 8px; background: #fafafa;">
                                <div class="doc-icon" style="margin-right: 12px; font-size: 28px; color: #666;">
                                    <?php 
                                    $icon = 'fas fa-file';
                                    if ($doc['tipe_file'] == 'pdf') $icon = 'fas fa-file-pdf text-danger';
                                    elseif (in_array($doc['tipe_file'], ['jpg', 'jpeg', 'png'])) $icon = 'fas fa-file-image text-warning';
                                    elseif (in_array($doc['tipe_file'], ['doc', 'docx'])) $icon = 'fas fa-file-word text-primary';
                                    ?>
                                    <i class="<?= $icon ?>"></i>
                                </div>

                                <div class="doc-info" style="flex: 1;">
                                    <h4 style="margin: 0; font-size: 16px;"><?= htmlspecialchars($doc['judul']) ?></h4>
                                    <p class="doc-date" style="margin: 0; font-size: 12px; color: #888;">
                                        <span>Kategori: <?= htmlspecialchars($doc['nama_kategori']) ?></span>
                                        <?php if ($doc['nama_jenis']): ?>
                                        | <span>Jenis: <?= htmlspecialchars($doc['nama_jenis']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="doc-meta" style="margin: 4px 0; font-size: 13px; color: #555;">
                                        <span>Oleh: <?= htmlspecialchars($doc['nama_lengkap']) ?></span>
                                        
                                    </p>
                                    <p class="doc-date" style="margin: 0; font-size: 12px; color: #888;">
                                        <?= date('d M Y H:i', strtotime($doc['created_at'])) ?>
                                    </p>
                                </div>

                                <div class="doc-actions" style="display: flex; gap: 6px;">
                                    <button class="btn btn-sm btn-info" onclick="previewFile(<?= $doc['id'] ?>)" title="Lihat Dokumen">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="downloadFile(<?= $doc['id'] ?>)" title="Unduh Dokumen">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Chart.js untuk grafik download
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('downloadChart').getContext('2d');
        const downloadChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?= implode(',', array_map(function($stat) { return "'" . date('d M', strtotime($stat['date'])) . "'"; }, $download_stats)) ?>],
                datasets: [{
                    label: 'Jumlah Download',
                    data: [<?= implode(',', array_column($download_stats, 'count')) ?>],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
    
    function previewFile(fileId) {
        window.open('index.php?action=preview&id=' + fileId, '_blank');
    }
    
    function downloadFile(fileId) {
        window.location.href = 'index.php?action=download&id=' + fileId;
    }
    </script>
    <?php
    show_footer();
}

// =============================================
// HANDLER FUNCTIONS - MANAJEMEN ARSIP (DENGAN SHARING DAN PAGINATION)
// =============================================

function show_arsip() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru','tu']);
    
    $user_id = $_SESSION['user_id'];
    $user_level = $_SESSION['level'];
    
    // Handle filter dan pagination
    $filter_kategori = $_GET['kategori'] ?? '';
    $filter_jenis = $_GET['jenis'] ?? '';
    $filter_tahun = $_GET['tahun'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = $_GET['per_page'] ?? '10';
    
    // Validate page
    if ($page < 1) $page = 1;
    
    // Calculate offset based on per_page
    if ($per_page === 'all') {
        $limit = '';
        $offset = '';
    } else {
        $per_page_int = (int)$per_page;
        $offset = ($page - 1) * $per_page_int;
        $limit = "LIMIT $per_page_int OFFSET $offset";
    }
    
    // Get categories and types for filter
    $kategories = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);
    $jenis_dokumen = $pdo->query("SELECT * FROM jenis_dokumen ORDER BY nama_jenis")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users untuk sharing (kecuali user sendiri)
    $users_for_sharing = $pdo->query("SELECT id, username, nama_lengkap, level FROM users WHERE id != $user_id AND is_active = TRUE ORDER BY nama_lengkap")->fetchAll(PDO::FETCH_ASSOC);
    
    show_header('Manajemen Arsip');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Manajemen Arsip Digital</h2>
            <p>Kelola dokumen dan arsip digital sekolah</p>
            <hr style="border:none; height:0; margin:10px 0;">
            <button class="btn btn-primary" onclick="showUploadModal()">
            <i class="fas fa-upload"></i> Upload Dokumen
        </button>
        </div>

    </div>
    
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <input type="hidden" name="action" value="arsip">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= htmlspecialchars($per_page) ?>">
            
            <div class="filter-group">
                <label>Pencarian:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul dokumen...">
            </div>
            
            <div class="filter-group">
                <label>Kategori:</label>
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach($kategories as $kategori): ?>
                    <option value="<?= $kategori['id'] ?>" <?= $filter_kategori == $kategori['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Jenis:</label>
                <select name="jenis">
                    <option value="">Semua Jenis</option>
                    <?php foreach($jenis_dokumen as $jenis): ?>
                    <option value="<?= $jenis['id'] ?>" <?= $filter_jenis == $jenis['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($jenis['nama_jenis']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Tahun:</label>
                <select name="tahun">
                    <option value="">Semua Tahun</option>
                    <?php for($year = date('Y'); $year >= 2020; $year--): ?>
                    <option value="<?= $year ?>" <?= $filter_tahun == $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-outline">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="index.php?action=arsip" class="btn btn-outline">
                <i class="fas fa-refresh"></i> Reset
            </a>
        </form>
    </div>
    
    <div class="arsip-container">
        <div class="table-responsive">
            <table id="arsipTable" class="data-table">
                <thead>
                    <tr>
                        <th>Judul Dokumen</th>
                        <th>Kategori</th>
                        <th>Jenis</th>
                        <th>Uploader</th>
                        <th>Akses</th>
                        <th>Tanggal</th>
                        <th>Ukuran</th>
                        <th>Download</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Build query based on filters and user access
                    if ($user_level == 'admin') {
                        $query = "SELECT d.*, u.nama_lengkap, k.nama_kategori, j.nama_jenis 
                                 FROM dokumen d 
                                 LEFT JOIN users u ON d.user_id = u.id 
                                 LEFT JOIN kategori k ON d.kategori_id = k.id 
                                 LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id 
                                 WHERE 1=1";
                        $count_query = "SELECT COUNT(*) 
                                       FROM dokumen d 
                                       LEFT JOIN users u ON d.user_id = u.id 
                                       LEFT JOIN kategori k ON d.kategori_id = k.id 
                                       LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id 
                                       WHERE 1=1";
                        $params = [];
                    } else {
                        $query = "SELECT DISTINCT d.*, u.nama_lengkap, k.nama_kategori, j.nama_jenis 
                                 FROM dokumen d 
                                 LEFT JOIN users u ON d.user_id = u.id 
                                 LEFT JOIN kategori k ON d.kategori_id = k.id 
                                 LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id 
                                 LEFT JOIN dokumen_sharing ds ON d.id = ds.dokumen_id 
                                 WHERE (d.user_id = ? OR d.akses = 'public' OR (d.akses = 'shared' AND ds.user_id = ?))";
                        $count_query = "SELECT COUNT(DISTINCT d.id) 
                                       FROM dokumen d 
                                       LEFT JOIN dokumen_sharing ds ON d.id = ds.dokumen_id 
                                       WHERE (d.user_id = ? OR d.akses = 'public' OR (d.akses = 'shared' AND ds.user_id = ?))";
                        $params = [$user_id, $user_id];
                    }
                    
                    if (!empty($search)) {
                        $query .= " AND (d.judul LIKE ? OR d.deskripsi LIKE ?)";
                        $count_query .= " AND (d.judul LIKE ? OR d.deskripsi LIKE ?)";
                        $params[] = "%$search%";
                        $params[] = "%$search%";
                    }
                    
                    if (!empty($filter_kategori)) {
                        $query .= " AND d.kategori_id = ?";
                        $count_query .= " AND d.kategori_id = ?";
                        $params[] = $filter_kategori;
                    }
                    
                    if (!empty($filter_jenis)) {
                        $query .= " AND d.jenis_id = ?";
                        $count_query .= " AND d.jenis_id = ?";
                        $params[] = $filter_jenis;
                    }
                    
                    if (!empty($filter_tahun)) {
                        $query .= " AND d.tahun_periode = ?";
                        $count_query .= " AND d.tahun_periode = ?";
                        $params[] = $filter_tahun;
                    }
                    
                    $query .= " ORDER BY d.created_at DESC";
                    
                    // Get total count
                    $stmt_count = $pdo->prepare($count_query);
                    $stmt_count->execute($params);
                    $total_records = $stmt_count->fetchColumn();
                    
                    // Add pagination to main query
                    if ($limit) {
                        $query .= " $limit";
                    }
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $arsip = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate pagination info
                    $total_pages = $per_page === 'all' ? 1 : ceil($total_records / $per_page_int);
                    $start_record = $per_page === 'all' ? 1 : (($page - 1) * $per_page_int) + 1;
                    $end_record = $per_page === 'all' ? $total_records : min($page * $per_page_int, $total_records);
                    
                    if (empty($arsip)): ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>Belum ada dokumen</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        foreach($arsip as $doc):
                            $can_edit = check_document_access($doc['id'], true);
                    ?>
                    <tr style="font-size: 0.8em;">
                        <td>
                            <div class="doc-title">
                                <?php if ($doc['jenis_upload'] == 'link'): ?>
                                    <i class="fas fa-external-link-alt text-primary" title="Link External"></i>
                                <?php else: ?>
                                    <i class="fas fa-file-<?= $doc['tipe_file'] == 'pdf' ? 'pdf' : 'alt' ?> text-danger"></i>
                                <?php endif; ?>
                                
                                <?= htmlspecialchars($doc['judul']) ?>
                                
                                <?php if ($doc['akses'] == 'private'): ?>
                                    <i class="fas fa-lock text-warning" title="Private"></i>
                                <?php elseif ($doc['akses'] == 'shared'): ?>
                                    <i class="fas fa-share-alt text-info" title="Shared"></i>
                                <?php endif; ?>
                            </div>
                            <?php if ($doc['deskripsi']): ?>
                            <small class="text-muted"><?= htmlspecialchars($doc['deskripsi']) ?></small>
                            <?php endif; ?>
                            
                            <?php if ($doc['jenis_upload'] == 'link' && $doc['file_url']): ?>
                            <br><small class="text-info"><i class="fas fa-link"></i> <?= htmlspecialchars($doc['file_url']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($doc['nama_kategori']) ?></td>
                        <td><?= htmlspecialchars($doc['nama_jenis'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($doc['nama_lengkap']) ?></td>
                        <td>
                            <?php if ($doc['akses'] == 'public'): ?>
                                <span class="badge badge-success">Public</span>
                            <?php elseif ($doc['akses'] == 'shared'): ?>
                                <span class="badge badge-info">Shared</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Private</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($doc['created_at'])) ?></td>
                        <td><?= format_file_size($doc['ukuran_file']) ?></td>
                        <td><?= $doc['download_count'] ?>x</td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-info" onclick="previewFile(<?= $doc['id'] ?>)" title="Preview">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="downloadFile(<?= $doc['id'] ?>)" title="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                                <?php if ($can_edit): ?>
                                <button class="btn btn-sm btn-warning" onclick="editFile(<?= $doc['id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($doc['user_id'] == $user_id || $user_level == 'admin'): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteFile(<?= $doc['id'] ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; 
                    endif; ?>
                </tbody>
            </table>
            
            <!-- PAGINATION SECTION -->
            <?php if ($total_records > 0): ?>
            <div class="pagination-section">
                
                <div class="pagination-controls">
                    <!-- Previous Button -->
                    <?php if ($page > 1 && $per_page !== 'all'): ?>
                    <a href="<?= build_pagination_url(['page' => $page - 1]) ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php if ($per_page !== 'all'): ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="<?= build_pagination_url(['page' => $i]) ?>" 
                                   class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">
                                    <?= $i ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span class="btn btn-sm btn-outline disabled">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <?php if ($page < $total_pages && $per_page !== 'all'): ?>
                    <a href="<?= build_pagination_url(['page' => $page + 1]) ?>" class="btn btn-outline btn-sm">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Items Per Page Dropdown -->
                    <div class="pagination-dropdown">
                        <form method="GET" class="per-page-form">
                            <input type="hidden" name="action" value="arsip">
                            <input type="hidden" name="page" value="1">
                            <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                            <?php if (!empty($filter_kategori)): ?><input type="hidden" name="kategori" value="<?= htmlspecialchars($filter_kategori) ?>"><?php endif; ?>
                            <?php if (!empty($filter_jenis)): ?><input type="hidden" name="jenis" value="<?= htmlspecialchars($filter_jenis) ?>"><?php endif; ?>
                            <?php if (!empty($filter_tahun)): ?><input type="hidden" name="tahun" value="<?= htmlspecialchars($filter_tahun) ?>"><?php endif; ?>
                            
                            <select name="per_page" onchange="this.form.submit()" class="per-page-select">
                                <option value="10" <?= $per_page == '10' ? 'selected' : '' ?>>10 per halaman</option>
                                <option value="25" <?= $per_page == '25' ? 'selected' : '' ?>>25 per halaman</option>
                                <option value="50" <?= $per_page == '50' ? 'selected' : '' ?>>50 per halaman</option>
                                <option value="all" <?= $per_page == 'all' ? 'selected' : '' ?>>Tampilkan Semua</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="pagination-info">
                    Menampilkan <?= $start_record ?> - <?= $end_record ?> dari <?= $total_records ?> data
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Upload -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upload Dokumen Baru</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data" method="POST" action="index.php?action=upload">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group">
                        <label>Judul Dokumen *</label>
                        <input type="text" name="judul" required placeholder="Masukkan judul dokumen">
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" placeholder="Deskripsi singkat dokumen" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori *</label>
                            <select name="kategori_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach($kategories as $kategori): ?>
                                <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Dokumen</label>
                            <select name="jenis_id">
                                <option value="">Pilih Jenis</option>
                                <?php foreach($jenis_dokumen as $jenis): ?>
                                <option value="<?= $jenis['id'] ?>"><?= htmlspecialchars($jenis['nama_jenis']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tahun Periode</label>
                            <select name="tahun_periode">
                                <option value="">Pilih Tahun</option>
                                <?php for($year = date('Y'); $year >= 2020; $year--): ?>
                                <option value="<?= $year ?>"><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Akses Dokumen</label>
                            <select name="akses" id="aksesSelect" onchange="toggleSharingSection()">
                                <option value="private">Private</option>
                                <option value="public">Public</option>
                                <option value="shared">Shared (User Tertentu)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Section untuk sharing dokumen -->
                    <div id="sharingSection" style="display: none;">
                        <div class="form-group">
                            <label>Pilih User yang Dapat Mengakses:</label>
                            <div class="sharing-users-container">
                                <?php foreach($users_for_sharing as $user): ?>
                                <div class="sharing-user-item">
                                    <label>
                                        <input type="checkbox" name="shared_users[]" value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['nama_lengkap']) ?> 
                                        <small>(<?= htmlspecialchars($user['username']) ?>)</small>
                                        <span class="badge badge-<?= get_level_badge($user['level']) ?>">
                                            <?= ucfirst($user['level']) ?>
                                        </span>
                                    </label>
                                    <div class="sharing-permission">
                                        <label>
                                            <input type="checkbox" name="can_edit[<?= $user['id'] ?>]">
                                            Dapat Edit
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Jenis Upload *</label>
                        <div style="display: flex; gap: 20px; margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="jenis_upload" value="file" checked onchange="toggleUploadType()">
                                <span>Upload File ke Server</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="jenis_upload" value="link" onchange="toggleUploadType()">
                                <span>Link Google Drive/Cloud</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section untuk upload file -->
                    <div id="fileUploadSection">
                        <div class="form-group">
                            <label>File Dokumen *</label>
                            <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="form-help">Format yang didukung: PDF, DOC, DOCX, JPG, JPEG, PNG (Max 10MB)</small>
                        </div>
                    </div>
                    <!-- Section untuk link Google Drive -->
                    <div id="linkUploadSection" style="display: none;">
                        <div class="form-group">
                            <label>Link Google Drive/Cloud *</label>
                            <input type="url" name="file_url" placeholder="https://drive.google.com/file/d/... atau https://docs.google.com/..." 
                                pattern="https://.*" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <small class="form-help">
                                Masukkan link Google Drive, Google Docs, atau cloud storage lainnya yang dapat diakses publik
                            </small>
                        </div>
                        
                        <div class="alert alert-info" style="margin-top: 10px; padding: 10px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Tips:</strong> Pastikan link yang dibagikan memiliki akses "Anyone with the link can view"
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('uploadModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Arsip -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Dokumen</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" action="index.php?action=edit_file">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="file_id" id="edit_file_id">
                    
                    <div class="form-group">
                        <label>Judul Dokumen *</label>
                        <input type="text" name="judul" id="edit_judul" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori *</label>
                            <select name="kategori_id" id="edit_kategori_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach($kategories as $kategori): ?>
                                <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Dokumen</label>
                            <select name="jenis_id" id="edit_jenis_id">
                                <option value="">Pilih Jenis</option>
                                <?php foreach($jenis_dokumen as $jenis): ?>
                                <option value="<?= $jenis['id'] ?>"><?= htmlspecialchars($jenis['nama_jenis']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tahun Periode</label>
                            <select name="tahun_periode" id="edit_tahun_periode">
                                <option value="">Pilih Tahun</option>
                                <?php for($year = date('Y'); $year >= 2020; $year--): ?>
                                <option value="<?= $year ?>"><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Akses Dokumen</label>
                            <select name="akses" id="edit_akses" onchange="toggleEditSharingSection()">
                                <option value="private">Private</option>
                                <option value="public">Public</option>
                                <option value="shared">Shared (User Tertentu)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Section untuk editing sharing dokumen -->
                    <div id="editSharingSection" style="display: none;">
                        <div class="form-group">
                            <label>Pilih User yang Dapat Mengakses:</label>
                            <div class="sharing-users-container" id="editSharingUsers">
                                <!-- akan diisi via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>

    // Tambahkan di script section setelah form upload

    function toggleUploadType() {
        const jenisUpload = document.querySelector('input[name="jenis_upload"]:checked').value;
        const fileSection = document.getElementById('fileUploadSection');
        const linkSection = document.getElementById('linkUploadSection');
        
        if (jenisUpload === 'file') {
            fileSection.style.display = 'block';
            linkSection.style.display = 'none';
            // Buat field file required
            document.querySelector('input[name="file"]').required = true;
            document.querySelector('input[name="file_url"]').required = false;
        } else {
            fileSection.style.display = 'none';
            linkSection.style.display = 'block';
            // Buat field link required
            document.querySelector('input[name="file"]').required = false;
            document.querySelector('input[name="file_url"]').required = true;
        }
    }

    function showUploadModal() {
        document.getElementById('uploadModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function toggleSharingSection() {
        const accessSelect = document.getElementById('aksesSelect');
        const sharingSection = document.getElementById('sharingSection');
        
        if (accessSelect.value === 'shared') {
            sharingSection.style.display = 'block';
        } else {
            sharingSection.style.display = 'none';
        }
    }
    
    function toggleEditSharingSection() {
        const accessSelect = document.getElementById('edit_akses');
        const sharingSection = document.getElementById('editSharingSection');
        
        if (accessSelect.value === 'shared') {
            sharingSection.style.display = 'block';
            // Load shared users untuk dokumen ini
            loadSharedUsers();
        } else {
            sharingSection.style.display = 'none';
        }
    }
    
    function loadSharedUsers() {
        const fileId = document.getElementById('edit_file_id').value;
        const container = document.getElementById('editSharingUsers');
        
        fetch(`index.php?action=get_shared_users&file_id=${fileId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    container.innerHTML = data.html;
                } else {
                    container.innerHTML = '<p>Error loading shared users</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<p>Error loading shared users</p>';
            });
    }
    
    function editFile(fileId) {
        // Fetch data via AJAX
        fetch(`index.php?action=get_file_data&id=${fileId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Isi form dengan data
                    document.getElementById('edit_file_id').value = data.id;
                    document.getElementById('edit_judul').value = data.judul;
                    document.getElementById('edit_deskripsi').value = data.deskripsi;
                    document.getElementById('edit_kategori_id').value = data.kategori_id;
                    document.getElementById('edit_jenis_id').value = data.jenis_id;
                    document.getElementById('edit_tahun_periode').value = data.tahun_periode;
                    document.getElementById('edit_akses').value = data.akses;
                    
                    // Toggle sharing section jika perlu
                    if (data.akses === 'shared') {
                        document.getElementById('editSharingSection').style.display = 'block';
                        loadSharedUsers();
                    } else {
                        document.getElementById('editSharingSection').style.display = 'none';
                    }
                    
                    // Buka modal
                    document.getElementById('editModal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Gagal mengambil data dokumen');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengambil data dokumen');
            });
    }
    
    function deleteFile(fileId) {
        if (confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) {
            window.location.href = 'index.php?action=delete_file&id=' + fileId + '&csrf_token=<?= generate_csrf_token() ?>';
        }
    }
    
    function previewFile(fileId) {
        window.open('index.php?action=preview&id=' + fileId, '_blank');
    }
    
    function downloadFile(fileId) {
        window.location.href = 'index.php?action=download&id=' + fileId;
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            if (event.target == modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
    
    // Close modal with close button
    document.querySelectorAll('.close').forEach(function(closeBtn) {
        closeBtn.onclick = function() {
            this.closest('.modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
    });

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    </script>
    
    <style>
    .sharing-users-container {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #e1e1e1;
        border-radius: 5px;
        padding: 10px;
        background: #f9f9f9;
    }
    
    .sharing-user-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    .sharing-user-item:last-child {
        border-bottom: none;
    }
    
    .sharing-user-item label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        cursor: pointer;
    }
    
    .sharing-permission {
        font-size: 12px;
    }
    
    .sharing-permission label {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Pagination Styles */
    .pagination-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .pagination-info {
        color: #6c757d;
        font-size: 14px;
        font-weight: 500;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pagination-dropdown {
        margin-left: 15px;
    }

    .per-page-form {
        margin: 0;
    }

    .per-page-select {
        padding: 6px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background: white;
        font-size: 14px;
        cursor: pointer;
        transition: border-color 0.3s;
    }

    .per-page-select:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .pagination-section {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .pagination-controls {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .pagination-dropdown {
            margin-left: 0;
            margin-top: 10px;
        }
    }

    /* Button styles for pagination */
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
        min-width: 40px;
    }

    .btn-outline.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    </style>
    <?php
    // Dalam modal upload, tambahkan setelah form-group terakhir sebelum form-actions
    echo '
    <hr style="border:none; height:0; margin:15px 0;">

    <div class="upload-structure-info">
        <h4><i class="fas fa-folder-tree"></i> Struktur Penyimpanan File</h4>
        <p>File akan disimpan dalam struktur folder:</p>
        <div class="structure-path">
            <h6 style="font-size:0.9em; color:#FF0000;"> uploads/arsip/[TAHUN]/[BULAN]/[NAMA_USER]/[KATEGORI]/[JENIS]/[FILE]</h6>
        </div>
        <small>Contoh: <span  style="font-size:0.8em; color:FFCC00;">uploads/arsip/2024/12/john_doe/Surat_Resmi/SK_Kepala_Sekolah/202412_abc123.pdf</span></small>
    </div>';
    show_footer();
}

// PERBAIKAN: Fungsi handle_upload dengan struktur folder baru
function handle_upload() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru', 'tu']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=arsip');
            exit;
        }
        
        $judul = sanitize($_POST['judul']);
        $deskripsi = sanitize($_POST['deskripsi']);
        $kategori_id = $_POST['kategori_id'] ?? null;
        $jenis_id = $_POST['jenis_id'] ?? null;
        
        // PERBAIKAN: Handle tahun_periode yang kosong
        $tahun_periode = $_POST['tahun_periode'] ?? null;
        if ($tahun_periode === '') {
            $tahun_periode = null;
        } elseif ($tahun_periode !== null) {
            $tahun_periode = (int)$tahun_periode;
        }
        
        $akses = $_POST['akses'] ?? 'private';
        $jenis_upload = $_POST['jenis_upload'] ?? 'file';
        $file_url = sanitize($_POST['file_url'] ?? '');
        $shared_users = $_POST['shared_users'] ?? [];
        $can_edit = $_POST['can_edit'] ?? [];
        $user_id = $_SESSION['user_id'];
        
        // VALIDASI DAN PERBAIKAN: Pastikan nilai akses valid
        $allowed_access = ['public', 'private', 'shared'];
        if (!in_array($akses, $allowed_access)) {
            $akses = 'private';
        }
        
        // Jika akses adalah shared tapi tidak ada user yang dipilih, ubah ke private
        if ($akses == 'shared' && empty($shared_users)) {
            $akses = 'private';
        }
        
        // PERBAIKAN: Ambil nama kategori dan jenis untuk struktur folder
        $kategori_nama = 'Umum';
        $jenis_nama = 'Lainnya';
        
        if ($kategori_id) {
            $stmt = $pdo->prepare("SELECT nama_kategori FROM kategori WHERE id = ?");
            $stmt->execute([$kategori_id]);
            $kategori = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($kategori) {
                $kategori_nama = $kategori['nama_kategori'];
            }
        }
        
        if ($jenis_id) {
            $stmt = $pdo->prepare("SELECT nama_jenis FROM jenis_dokumen WHERE id = ?");
            $stmt->execute([$jenis_id]);
            $jenis = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($jenis) {
                $jenis_nama = $jenis['nama_jenis'];
            }
        }
        
        try {
            $pdo->beginTransaction();
            
            // Di dalam fungsi handle_upload(), cari bagian upload file dan perbarui:
            if ($jenis_upload == 'file') {
                // UPLOAD FILE KE SERVER
                if (empty($_FILES['file']['name'])) {
                    $_SESSION['error'] = "Harap pilih file untuk diupload";
                    header('Location: index.php?action=arsip');
                    exit;
                }
                
                // PERBAIKAN: Kirim tahun_periode, kategori_nama, dan jenis_nama ke fungsi handle_file_upload
                $upload_result = handle_file_upload($_FILES['file'], 'arsip', $user_id, $tahun_periode, $kategori_nama, $jenis_nama);
                
                if ($upload_result['success']) {
                    $nama_file = $upload_result['filename'];
                    $nama_file_asli = $upload_result['original_name'];
                    $ukuran_file = $upload_result['size'];
                    $tipe_file = $upload_result['type'];
                    $file_url_db = NULL; // Untuk upload file, file_url NULL
                    error_log("File uploaded to directory with year: " . $upload_result['tahun_used'] . " for tahun_periode: " . $tahun_periode);
                } else {
                    $_SESSION['error'] = $upload_result['error'];
                    header('Location: index.php?action=arsip');
                    exit;
                }
            } else {
                // UPLOAD LINK GOOGLE DRIVE
                if (empty($file_url)) {
                    $_SESSION['error'] = "Link Google Drive harus diisi";
                    header('Location: index.php?action=arsip');
                    exit;
                }
                
                // Validasi URL
                if (!filter_var($file_url, FILTER_VALIDATE_URL)) {
                    $_SESSION['error'] = "Format link tidak valid";
                    header('Location: index.php?action=arsip');
                    exit;
                }
                
                // Untuk upload link, set nilai file ke NULL
                $nama_file = NULL;
                $nama_file_asli = 'Link External';
                $ukuran_file = 0;
                $tipe_file = 'link';
                $file_url_db = $file_url;
            }
            
            // Insert dokumen
            $stmt = $pdo->prepare("INSERT INTO dokumen (judul, deskripsi, nama_file, nama_file_asli, ukuran_file, tipe_file, kategori_id, jenis_id, user_id, akses, tahun_periode, jenis_upload, file_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $judul,
                $deskripsi,
                $nama_file,           // Bisa NULL untuk link
                $nama_file_asli,      // Bisa NULL untuk link  
                $ukuran_file,
                $tipe_file,
                $kategori_id,
                $jenis_id,
                $user_id,
                $akses,
                $tahun_periode,       // Bisa NULL
                $jenis_upload,
                $file_url_db
            ]);
            
            $dokumen_id = $pdo->lastInsertId();
            
            // Jika akses adalah shared, simpan data sharing
            if ($akses == 'shared' && !empty($shared_users)) {
                $stmt = $pdo->prepare("INSERT INTO dokumen_sharing (dokumen_id, user_id, can_edit) VALUES (?, ?, ?)");
                
                foreach ($shared_users as $shared_user_id) {
                    $edit_permission = isset($can_edit[$shared_user_id]) ? 1 : 0;
                    $stmt->execute([$dokumen_id, $shared_user_id, $edit_permission]);
                }
            }
            
            $pdo->commit();
            
            log_audit($user_id, 'UPLOAD', "Upload dokumen: $judul (Jenis: $jenis_upload)");
            $_SESSION['success'] = "Dokumen berhasil diupload!";
        } catch(PDOException $e) {
            $pdo->rollBack();
            // Delete uploaded file if database insert fails (hanya untuk upload file)
            if ($jenis_upload == 'file' && isset($upload_result) && file_exists($upload_result['full_path'])) {
                unlink($upload_result['full_path']);
            }
            $_SESSION['error'] = "Gagal menyimpan data dokumen: " . $e->getMessage();
        }
        
        header('Location: index.php?action=arsip');
        exit;
    }
}

function handle_edit_file() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru','tu']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=arsip');
            exit;
        }
        
        $file_id = $_POST['file_id'];
        $judul = sanitize($_POST['judul']);
        $deskripsi = sanitize($_POST['deskripsi']);
        $kategori_id = $_POST['kategori_id'] ?? null;
        $jenis_id = $_POST['jenis_id'] ?? null;
        
        // PERBAIKAN: Handle tahun_periode yang kosong
        $tahun_periode = $_POST['tahun_periode'] ?? null;
        if ($tahun_periode === '') {
            $tahun_periode = null;
        } elseif ($tahun_periode !== null) {
            $tahun_periode = (int)$tahun_periode;
        }
        
        $akses = $_POST['akses'] ?? 'private';
        $shared_users = $_POST['shared_users'] ?? [];
        $can_edit = $_POST['can_edit'] ?? [];
        $user_id = $_SESSION['user_id'];
        
        // VALIDASI DAN PERBAIKAN: Pastikan nilai akses valid
        $allowed_access = ['public', 'private', 'shared'];
        if (!in_array($akses, $allowed_access)) {
            $akses = 'private'; // Default jika tidak valid
        }
        
        // Jika akses adalah shared tapi tidak ada user yang dipilih, ubah ke private
        if ($akses == 'shared' && empty($shared_users)) {
            $akses = 'private';
        }
        
        // Check if user has permission to edit this file
        if (!check_document_access($file_id, true)) {
            $_SESSION['error'] = "Anda tidak memiliki izin untuk mengedit dokumen ini!";
            header('Location: index.php?action=arsip');
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Update dokumen
            $stmt = $pdo->prepare("UPDATE dokumen SET judul = ?, deskripsi = ?, kategori_id = ?, jenis_id = ?, tahun_periode = ?, akses = ? WHERE id = ?");
            $stmt->execute([$judul, $deskripsi, $kategori_id, $jenis_id, $tahun_periode, $akses, $file_id]);
            
            // Hapus semua sharing yang ada
            $pdo->prepare("DELETE FROM dokumen_sharing WHERE dokumen_id = ?")->execute([$file_id]);
            
            // Jika akses adalah shared, simpan data sharing baru
            if ($akses == 'shared' && !empty($shared_users)) {
                $stmt = $pdo->prepare("INSERT INTO dokumen_sharing (dokumen_id, user_id, can_edit) VALUES (?, ?, ?)");
                
                foreach ($shared_users as $shared_user_id) {
                    $edit_permission = isset($can_edit[$shared_user_id]) ? 1 : 0;
                    $stmt->execute([$file_id, $shared_user_id, $edit_permission]);
                }
            }
            
            $pdo->commit();
            
            log_audit($user_id, 'EDIT_FILE', "Edit dokumen: $judul (ID: $file_id)");
            $_SESSION['success'] = "Dokumen berhasil diperbarui!";
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Gagal memperbarui dokumen: " . $e->getMessage();
        }
        
        header('Location: index.php?action=arsip');
        exit;
    }
}

function get_file_data() {
    global $pdo;

    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $file_id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        exit;
    }

    // Check access
    if (!check_document_access($file_id, false)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    echo json_encode(['success' => true] + $file);
    exit;
}

function get_shared_users() {
    global $pdo;
    
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $file_id = $_GET['file_id'] ?? 0;
    $user_id = $_SESSION['user_id'];
    
    // Check if user has permission to view this file's sharing
    if (!check_document_access($file_id, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    
    // Get users untuk sharing (kecuali user sendiri)
    $users_for_sharing = $pdo->query("SELECT id, username, nama_lengkap, level FROM users WHERE id != $user_id AND is_active = TRUE ORDER BY nama_lengkap")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current shared users
    $shared_users = [];
    $stmt = $pdo->prepare("SELECT user_id, can_edit FROM dokumen_sharing WHERE dokumen_id = ?");
    $stmt->execute([$file_id]);
    $current_sharing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($current_sharing as $share) {
        $shared_users[$share['user_id']] = $share['can_edit'];
    }
    
    // Generate HTML
    $html = '';
    foreach ($users_for_sharing as $user) {
        $is_shared = isset($shared_users[$user['id']]);
        $can_edit = $is_shared ? $shared_users[$user['id']] : false;
        
        $html .= '<div class="sharing-user-item">';
        $html .= '<label>';
        $html .= '<input type="checkbox" name="shared_users[]" value="' . $user['id'] . '" ' . ($is_shared ? 'checked' : '') . '>';
        $html .= htmlspecialchars($user['nama_lengkap']) . ' ';
        $html .= '<small>(' . htmlspecialchars($user['username']) . ')</small>';
        $html .= '<span class="badge badge-' . get_level_badge($user['level']) . '">';
        $html .= ucfirst($user['level']);
        $html .= '</span>';
        $html .= '</label>';
        $html .= '<div class="sharing-permission">';
        $html .= '<label>';
        $html .= '<input type="checkbox" name="can_edit[' . $user['id'] . ']" ' . ($can_edit ? 'checked' : '') . '>';
        $html .= 'Dapat Edit';
        $html .= '</label>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

function handle_download() {
    global $pdo;
    
    if (!is_logged_in()) {
        header('Location: index.php?action=login');
        exit;
    }
    
    $file_id = $_GET['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        $_SESSION['error'] = "Dokumen tidak ditemukan!";
        header('Location: index.php?action=arsip');
        exit;
    }
    
    if ($file['jenis_upload'] == 'link' && $file['file_url']) {
        // Untuk dokumen link, redirect ke URL external
        log_audit($_SESSION['user_id'], 'DOWNLOAD_LINK', "Download link: " . $file['judul']);
        
        // Update download count
        $pdo->prepare("UPDATE dokumen SET download_count = download_count + 1 WHERE id = ?")->execute([$file_id]);
        
        // Log download
        $pdo->prepare("INSERT INTO download_log (dokumen_id, user_id) VALUES (?, ?)")->execute([$file_id, $_SESSION['user_id']]);
        
        // Redirect ke link external
        header('Location: ' . $file['file_url']);
        exit;
    }

    // Check access untuk dokumen
    if (!check_document_access($file_id, false)) {
        $_SESSION['error'] = "Anda tidak memiliki akses ke dokumen ini!";
        header('Location: index.php?action=arsip');
        exit;
    }
    
    // Dapatkan path file yang benar
    $file_path = get_file_path($file['nama_file'], 'arsip');
    
    if (!$file_path || !file_exists($file_path)) {
        $_SESSION['error'] = "File tidak ditemukan di server!";
        header('Location: index.php?action=arsip');
        exit;
    }
    
    // Update download count
    $pdo->prepare("UPDATE dokumen SET download_count = download_count + 1 WHERE id = ?")->execute([$file_id]);
    
    // Log download
    $pdo->prepare("INSERT INTO download_log (dokumen_id, user_id) VALUES (?, ?)")->execute([$file_id, $_SESSION['user_id']]);
    
    log_audit($_SESSION['user_id'], 'DOWNLOAD', "Download arsip: " . $file['judul']);
    
    // Send file for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['nama_file_asli'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

function show_preview() {
    global $pdo;
    
    if (!is_logged_in()) {
        die("Anda harus login untuk melihat preview");
    }
    
    $file_id = $_GET['id'] ?? 0;
    
    // Default dokumen biasa
    $stmt = $pdo->prepare("SELECT d.*, u.nama_lengkap, k.nama_kategori, j.nama_jenis 
                          FROM dokumen d 
                          LEFT JOIN users u ON d.user_id = u.id 
                          LEFT JOIN kategori k ON d.kategori_id = k.id 
                          LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id 
                          WHERE d.id = ?");
    $stmt->execute([$file_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        die("Dokumen tidak ditemukan!");
    }
    
    // Check access
    if (!check_document_access($file_id, false)) {
        die("Anda tidak memiliki akses ke dokumen ini!");
    }
    
    $title = $data['judul'];
    $file_path = $data['nama_file'] ? get_file_path($data['nama_file'], 'arsip') : null;
    
    if ($file_path && !file_exists($file_path)) {
        die("File tidak ditemukan di server!");
    }
    
    // PERBAIKAN: Dapatkan URL file untuk akses web
    $file_url = get_file_url($data['nama_file'], 'arsip');
    
    // Show preview
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Preview: <?= htmlspecialchars($title) ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f5f5f5; }
            .preview-container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .preview-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .preview-title h2 { margin: 0; color: #333; }
            .file-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 10px; }
            .file-info p { margin: 5px 0;  }
            .preview-content { text-align: center; }
            .preview-actions { margin-top: 20px; text-align: center; }
            .btn { padding: 10px 20px; margin: 0 5px; text-decoration: none; border-radius: 5px; display: inline-block; }
            .btn-primary { background: #3498db; color: white; }
            .btn-success { background: #27ae60; color: white; }
        </style>
    </head>
    <body>
        <div class="preview-container">
            <div class="preview-header">
                <div class="preview-title">
                    <h2><?= htmlspecialchars($title) ?></h2>
                    <p>Diupload oleh: <?= htmlspecialchars($data['nama_lengkap']) ?> | Tanggal: <?= date('d M Y H:i', strtotime($data['created_at'])) ?></p>
                </div>
            </div>            
            <div class="file-info">
                <p><strong>Deskripsi:</strong> <?= $data['deskripsi'] ? htmlspecialchars($data['deskripsi']) : '-' ?></p>
                <p><strong>Ukuran File:</strong> <?= format_file_size($data['ukuran_file']) ?></p>
                <p><strong>Tipe File:</strong> <?= strtoupper($data['tipe_file']) ?></p>
                <p><strong>Akses:</strong> 
                    <?php if ($data['akses'] == 'public'): ?>
                        <span class="badge badge-success">Public</span>
                    <?php elseif ($data['akses'] == 'shared'): ?>
                        <span class="badge badge-info">Shared</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Private</span>
                    <?php endif; ?>
                </p>
                <p><strong>Total Download:</strong> <?= $data['download_count'] ?> kali</p>
            </div>
            
            <div class="preview-content">
                <?php if ($data['jenis_upload'] == 'link' && $data['file_url']): ?>
                    <!-- Preview untuk link external -->
                    <div class="alert alert-info">
                        <h4><i class="fas fa-external-link-alt"></i> Dokumen External</h4>
                        <p>Dokumen ini tersimpan di cloud storage external. Klik tombol di bawah untuk membuka dokumen.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-cloud" style="font-size: 64px; color: #3498db; margin-bottom: 20px;"></i>
                        <h3>Dokumen Cloud Storage</h3>
                        <p style="margin-bottom: 30px; color: #666;">
                            <?= htmlspecialchars($data['file_url']) ?>
                        </p>
                        <a href="<?= htmlspecialchars($data['file_url']) ?>" 
                        target="_blank" 
                        class="btn btn-primary btn-lg"
                        style="padding: 12px 30px; font-size: 16px;">
                            <i class="fas fa-external-link-alt"></i> Buka di Tab Baru
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Preview untuk file upload biasa -->
                    <?php if ($file_path): ?>
                        <!-- ... kode preview existing untuk file ... -->
                    <?php else: ?>
                        <!-- ... kode untuk file tidak ditemukan ... -->
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="preview-actions">
                <?php if ($file_path): ?>
                <a href="index.php?action=download&id=<?= $file_id ?>" class="btn btn-success" download>
                    <i class="fas fa-download"></i> Download File
                </a>
                <?php endif; ?>
                <a href="javascript:window.close()" class="btn btn-primary">Tutup</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function handle_delete_file() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru','tu']);
    
    $file_id = $_GET['id'] ?? 0;
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Token keamanan tidak valid";
        header('Location: index.php?action=arsip');
        exit;
    }
    
    // Get file info
    $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        $_SESSION['error'] = "Dokumen tidak ditemukan!";
        header('Location: index.php?action=arsip');
        exit;
    }
    
    // Check if user has permission to delete
    if ($_SESSION['level'] != 'admin' && $file['user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "Anda tidak memiliki izin untuk menghapus dokumen ini!";
        header('Location: index.php?action=arsip');
        exit;
    }
    
    try {
        // Delete file from filesystem - menggunakan fungsi get_file_path
        $file_path = get_file_path($file['nama_file'], 'arsip');
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database (dokumen_sharing akan terhapus otomatis karena CASCADE)
        $pdo->prepare("DELETE FROM dokumen WHERE id = ?")->execute([$file_id]);
        
        log_audit($_SESSION['user_id'], 'DELETE', "Hapus dokumen: " . $file['judul']);
        
        $_SESSION['success'] = "Dokumen berhasil dihapus!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus dokumen: " . $e->getMessage();
    }
    
    header('Location: index.php?action=arsip');
    exit;
}

// =============================================
// FUNGSI LAINNYA (users, kategori, profile, dll) DENGAN PAGINATION
// =============================================

function manage_users() {
    global $pdo;
    check_access(['admin']);
    
    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = $_GET['per_page'] ?? '10';
    
    // Validate page
    if ($page < 1) $page = 1;
    
    // Calculate offset based on per_page
    if ($per_page === 'all') {
        $limit = '';
        $offset = '';
    } else {
        $per_page_int = (int)$per_page;
        $offset = ($page - 1) * $per_page_int;
        $limit = "LIMIT $per_page_int OFFSET $offset";
    }
    
    show_header('Manajemen User');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Manajemen User</h2>
            <p>Kelola pengguna sistem arsip digital</p>
        </div>
        <button class="btn btn-primary" onclick="showAddUserModal()">
            <i class="fas fa-user-plus"></i> Tambah User
        </button>
    </div>
    
    <div class="table-responsive">
        <table id="usersTable" class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Tanggal Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get total count
                $total_records = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                
                // Get users with pagination
                $query = "SELECT * FROM users ORDER BY created_at DESC";
                if ($limit) {
                    $query .= " $limit";
                }
                
                $users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate pagination info
                $total_pages = $per_page === 'all' ? 1 : ceil($total_records / $per_page_int);
                $start_record = $per_page === 'all' ? 1 : (($page - 1) * $per_page_int) + 1;
                $end_record = $per_page === 'all' ? $total_records : min($page * $per_page_int, $total_records);
                
                if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>Belum ada user</p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach($users as $user):
                ?>
                <tr style="font-size:0.8em;">
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="badge badge-<?= get_level_badge($user['level']) ?>"><?= ucfirst($user['level']) ?></span></td>
                    <td>
                        <span class="badge <?= $user['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-warning" onclick="editUser(<?= $user['id'] ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>)" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; 
                endif; ?>
            </tbody>
        </table>
        
        <!-- PAGINATION SECTION -->
        <?php if ($total_records > 0): ?>
        <div class="pagination-section">
           
            <div class="pagination-controls">
                <!-- Previous Button -->
                <?php if ($page > 1 && $per_page !== 'all'): ?>
                <a href="<?= build_pagination_url(['page' => $page - 1]) ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <?php if ($per_page !== 'all'): ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <a href="<?= build_pagination_url(['page' => $i]) ?>" 
                               class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <span class="btn btn-sm btn-outline disabled">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                <?php endif; ?>
                
                <!-- Next Button -->
                <?php if ($page < $total_pages && $per_page !== 'all'): ?>
                <a href="<?= build_pagination_url(['page' => $page + 1]) ?>" class="btn btn-outline btn-sm">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
                
                <!-- Items Per Page Dropdown -->
                <div class="pagination-dropdown">
                    <form method="GET" class="per-page-form">
                        <input type="hidden" name="action" value="users">
                        <input type="hidden" name="page" value="1">
                        
                        <select name="per_page" onchange="this.form.submit()" class="per-page-select">
                            <option value="10" <?= $per_page == '10' ? 'selected' : '' ?>>10 per halaman</option>
                            <option value="25" <?= $per_page == '25' ? 'selected' : '' ?>>25 per halaman</option>
                            <option value="50" <?= $per_page == '50' ? 'selected' : '' ?>>50 per halaman</option>
                            <option value="all" <?= $per_page == 'all' ? 'selected' : '' ?>>Tampilkan Semua</option>
                        </select>
                    </form>
                </div>
            </div>
            <div class="pagination-info">
                Menampilkan <?= $start_record ?> - <?= $end_record ?> dari <?= $total_records ?> user
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Add/Edit User -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="userModalTitle">Tambah User Baru</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="userForm" method="POST" action="index.php?action=edit_user">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="user_id" id="user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" id="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" id="level" required>
                                <option value="">Pilih Level</option>
                                <option value="admin">Admin</option>
                                <option value="kepsek">Kepala Sekolah</option>
                                <option value="wakasek">Wakil Kepala Sekolah</option>
                                <option value="kaprog">Kepala Program</option>
                                <option value="kabeng">Kepala Bengkel</option>
                                <option value="guru">Guru</option>
                                <option value="siswa">Siswa</option>
                                <option value="tu">Tata Usaha</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" id="nama_lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <?php echo !isset($_GET['edit']) ? '*' : ''; ?></label>
                            <input type="password" name="password" id="password" <?php echo !isset($_GET['edit']) ? 'required' : ''; ?>>
                            <?php if(isset($_GET['edit'])): ?>
                            <small class="form-help">Kosongkan jika tidak ingin mengubah password</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password <?php echo !isset($_GET['edit']) ? '*' : ''; ?></label>
                            <input type="password" name="confirm_password" id="confirm_password" <?php echo !isset($_GET['edit']) ? 'required' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" id="is_active">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showAddUserModal() {
        document.getElementById('userModalTitle').textContent = 'Tambah User Baru';
        document.getElementById('userForm').reset();
        document.getElementById('user_id').value = '';
        document.getElementById('password').required = true;
        document.getElementById('confirm_password').required = true;
        document.getElementById('userModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function editUser(userId) {
        fetch(`index.php?action=get_user_data&id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('userModalTitle').textContent = 'Edit User';
                    document.getElementById('user_id').value = data.id;
                    document.getElementById('username').value = data.username;
                    document.getElementById('nama_lengkap').value = data.nama_lengkap;
                    document.getElementById('email').value = data.email;
                    document.getElementById('level').value = data.level;
                    document.getElementById('is_active').value = data.is_active;
                    document.getElementById('password').required = false;
                    document.getElementById('confirm_password').required = false;
                    document.getElementById('userModal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Gagal mengambil data user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengambil data user');
            });
    }
    
    function deleteUser(userId) {
        if (confirm('Apakah Anda yakin ingin menghapus user ini?')) {
            window.location.href = 'index.php?action=delete_user&id=' + userId + '&csrf_token=<?= generate_csrf_token() ?>';
        }
    }
    </script>
    <?php
    show_footer();
}

function handle_edit_user() {
    global $pdo;
    check_access(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=users');
            exit;
        }
        
        $user_id = $_POST['user_id'] ?? null;
        $username = sanitize($_POST['username']);
        $nama_lengkap = sanitize($_POST['nama_lengkap']);
        $email = sanitize($_POST['email']);
        $level = $_POST['level'];
        $is_active = $_POST['is_active'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Check if username already exists (for new user or when changing username)
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
        }
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username sudah digunakan!";
            header('Location: index.php?action=users');
            exit;
        }
        
        if ($password) {
            if ($password !== $confirm_password) {
                $_SESSION['error'] = "Konfirmasi password tidak sesuai!";
                header('Location: index.php?action=users');
                exit;
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        }
        
        try {
            if ($user_id) {
                // Update existing user
                if ($password) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, nama_lengkap = ?, email = ?, level = ?, is_active = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $nama_lengkap, $email, $level, $is_active, $hashed_password, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, nama_lengkap = ?, email = ?, level = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$username, $nama_lengkap, $email, $level, $is_active, $user_id]);
                }
                $action = 'EDIT_USER';
            } else {
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, email, level, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $nama_lengkap, $email, $level, $is_active]);
                $action = 'ADD_USER';
            }
            
            log_audit($_SESSION['user_id'], $action, "User: $username");
            $_SESSION['success'] = "User berhasil " . ($user_id ? "diperbarui" : "ditambahkan") . "!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal menyimpan user: " . $e->getMessage();
        }
        
        header('Location: index.php?action=users');
        exit;
    }
}

function manage_kategori() {
    global $pdo;
    check_access(['admin']);
    
    show_header('Manajemen Kategori & Jenis');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Manajemen Kategori & Jenis Dokumen</h2>
            <p>Kelola kategori dan jenis dokumen untuk pengelompokan arsip</p>
        </div>
    </div>
    
    <div class="kategori-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Kategori Section -->
        <div class="section-card">
            <div class="section-header">
                <h3>Kategori Dokumen</h3>
                <button class="btn btn-primary btn-sm" onclick="showAddKategoriModal()">
                    <i class="fas fa-plus"></i> Tambah
                </button>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $kategories = $pdo->query("SELECT k.*, u.nama_lengkap FROM kategori k LEFT JOIN users u ON k.created_by = u.id ORDER BY k.nama_kategori")->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($kategories)): ?>
                            <tr>
                                <td colspan="3" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-tags"></i>
                                        <p>Belum ada kategori</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else:
                            foreach($kategories as $kategori):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($kategori['nama_kategori']) ?></td>
                            <td><?= htmlspecialchars($kategori['deskripsi']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-warning" onclick="editKategori(<?= $kategori['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteKategori(<?= $kategori['id'] ?>)" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Jenis Dokumen Section -->
        <div class="section-card">
            <div class="section-header">
                <h3>Jenis Dokumen</h3>
                <button class="btn btn-primary btn-sm" onclick="showAddJenisModal()">
                    <i class="fas fa-plus"></i> Tambah
                </button>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama Jenis</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $jenis_dokumen = $pdo->query("SELECT j.*, k.nama_kategori FROM jenis_dokumen j LEFT JOIN kategori k ON j.kategori_id = k.id ORDER BY j.nama_jenis")->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($jenis_dokumen)): ?>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-list"></i>
                                        <p>Belum ada jenis dokumen</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else:
                            foreach($jenis_dokumen as $jenis):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($jenis['nama_jenis']) ?></td>
                            <td><?= htmlspecialchars($jenis['nama_kategori']) ?></td>
                            <td><?= htmlspecialchars($jenis['deskripsi']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-warning" onclick="editJenis(<?= $jenis['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteJenis(<?= $jenis['id'] ?>)" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Kategori -->
    <div id="kategoriModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="kategoriModalTitle">Tambah Kategori</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="kategoriForm" method="POST" action="index.php?action=edit_kategori">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="kategori_id" id="kategori_id">
                    
                    <div class="form-group">
                        <label>Nama Kategori *</label>
                        <input type="text" name="nama_kategori" id="nama_kategori" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" id="kategori_deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('kategoriModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Jenis Dokumen -->
    <div id="jenisModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="jenisModalTitle">Tambah Jenis Dokumen</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="jenisForm" method="POST" action="index.php?action=edit_jenis">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="jenis_id" id="jenis_id">
                    
                    <div class="form-group">
                        <label>Kategori *</label>
                        <select name="kategori_id" id="jenis_kategori_id" required>
                            <option value="">Pilih Kategori</option>
                            <?php
                            $kategories = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);
                            foreach($kategories as $kategori): ?>
                            <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Jenis *</label>
                        <input type="text" name="nama_jenis" id="nama_jenis" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" id="jenis_deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('jenisModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showAddKategoriModal() {
        document.getElementById('kategoriModalTitle').textContent = 'Tambah Kategori';
        document.getElementById('kategoriForm').reset();
        document.getElementById('kategori_id').value = '';
        document.getElementById('kategoriModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function editKategori(kategoriId) {
        fetch(`index.php?action=get_kategori_data&id=${kategoriId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('kategoriModalTitle').textContent = 'Edit Kategori';
                    document.getElementById('kategori_id').value = data.id;
                    document.getElementById('nama_kategori').value = data.nama_kategori;
                    document.getElementById('kategori_deskripsi').value = data.deskripsi;
                    document.getElementById('kategoriModal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Gagal mengambil data kategori');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengambil data kategori');
            });
    }
    
    function deleteKategori(kategoriId) {
        if (confirm('Apakah Anda yakin ingin menghapus kategori ini?')) {
            window.location.href = 'index.php?action=delete_kategori&id=' + kategoriId + '&csrf_token=<?= generate_csrf_token() ?>';
        }
    }
    
    function showAddJenisModal() {
        document.getElementById('jenisModalTitle').textContent = 'Tambah Jenis Dokumen';
        document.getElementById('jenisForm').reset();
        document.getElementById('jenis_id').value = '';
        document.getElementById('jenisModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function editJenis(jenisId) {
        fetch(`index.php?action=get_jenis_data&id=${jenisId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('jenisModalTitle').textContent = 'Edit Jenis Dokumen';
                    document.getElementById('jenis_id').value = data.id;
                    document.getElementById('jenis_kategori_id').value = data.kategori_id;
                    document.getElementById('nama_jenis').value = data.nama_jenis;
                    document.getElementById('jenis_deskripsi').value = data.deskripsi;
                    document.getElementById('jenisModal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Gagal mengambil data jenis dokumen');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengambil data jenis dokumen');
            });
    }
    
    function deleteJenis(jenisId) {
        if (confirm('Apakah Anda yakin ingin menghapus jenis dokumen ini?')) {
            window.location.href = 'index.php?action=delete_jenis&id=' + jenisId + '&csrf_token=<?= generate_csrf_token() ?>';
        }
    }
    </script>
    <?php
    show_footer();
}

// PERBAIKAN: Fungsi handle_update_profile dengan direktori nama user
function handle_update_profile() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru', 'siswa', 'tu']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=profile');
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        $nama_lengkap = sanitize($_POST['nama_lengkap']);
        $email = sanitize($_POST['email']);
        
        try {
            // Handle photo upload
            if (!empty($_FILES['foto_profil']['name'])) {
                // PERBAIKAN: Upload file dengan jenis 'photo' dan user_id, tanpa tahun_periode, kategori, dan jenis
                $upload_result = handle_file_upload($_FILES['foto_profil'], 'photo', $user_id);
                
                if ($upload_result['success']) {
                    // Delete old photo if exists
                    $stmt = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $old_photo = $stmt->fetchColumn();
                    
                    if ($old_photo) {
                        $old_photo_path = get_file_path($old_photo, 'photo');
                        if ($old_photo_path && file_exists($old_photo_path)) {
                            unlink($old_photo_path);
                        }
                    }
                    
                    // Update user with new photo
                    $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, email = ?, foto_profil = ? WHERE id = ?");
                    $stmt->execute([$nama_lengkap, $email, $upload_result['filename'], $user_id]);
                    $_SESSION['foto_profil'] = $upload_result['filename'];
                } else {
                    $_SESSION['error'] = $upload_result['error'];
                    header('Location: index.php?action=profile');
                    exit;
                }
            } else {
                // Update without photo
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
                $stmt->execute([$nama_lengkap, $email, $user_id]);
            }
            
            $_SESSION['nama_lengkap'] = $nama_lengkap;
            $_SESSION['email'] = $email;
            
            log_audit($user_id, 'UPDATE_PROFILE', "Update profil user");
            $_SESSION['success'] = "Profil berhasil diperbarui!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal memperbarui profil: " . $e->getMessage();
        }
        
        header('Location: index.php?action=profile');
        exit;
    }
}

function show_profile() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru', 'siswa', 'tu']);
    
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User tidak ditemukan!";
        header('Location: index.php?action=dashboard');
        exit;
    }
    
    show_header('Profile');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Profil Pengguna</h2>
            <p>Kelola informasi profil dan akun Anda</p>
        </div>
    </div>

    <div class="profile-container">
        <div class="profile-card">
            <!-- Profile Header Section -->
            <div class="profile-header">
                <div class="profile-avatar-section">
                    <div class="profile-avatar">
                        <?php if ($user['foto_profil']): ?>
                            <!-- PERBAIKAN: Gunakan get_file_url untuk akses web -->
                            <img src="<?= htmlspecialchars(get_file_url($user['foto_profil'], 'photo')) ?>" alt="Foto Profil" class="avatar-image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-basic-info">
                        <h2><?= htmlspecialchars($user['nama_lengkap']) ?></h2>
                        <div class="profile-meta">
                            <span class="badge badge-<?= get_level_badge($user['level']) ?> profile-level">
                                <i class="fas fa-user-tag"></i> <?= ucfirst($user['level']) ?>
                            </span>
                            <span class="username">@<?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <?php if ($user['email']): ?>
                        <p class="profile-email">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= date('d M Y', strtotime($user['created_at'])) ?></div>
                        <div class="stat-label">Bergabung</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <span class="badge <?= $user['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>
                        <div class="stat-label">Status</div>
                    </div>
                </div>
            </div>

            <!-- Profile Content Section -->
            <div class="profile-content">
                <!-- Information Section -->
                <div class="profile-section">
                    <div class="section-header">
                        <h3><i class="fas fa-info-circle"></i> Informasi Profil</h3>
                        <button class="btn btn-primary btn-sm" onclick="showEditProfileModal()">
                            <i class="fas fa-edit"></i> Edit Profil
                        </button>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-content">
                                <label>Username</label>
                                <p><?= htmlspecialchars($user['username']) ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <label>Nama Lengkap</label>
                                <p><?= htmlspecialchars($user['nama_lengkap']) ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <label>Email</label>
                                <p><?= htmlspecialchars($user['email'] ?: 'Tidak ada email') ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="info-content">
                                <label>Level Akses</label>
                                <p><span class="badge badge-<?= get_level_badge($user['level']) ?>"><?= ucfirst($user['level']) ?></span></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="info-content">
                                <label>Bergabung Pada</label>
                                <p><?= date('d F Y H:i', strtotime($user['created_at'])) ?></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="info-content">
                                <label>Terakhir Diupdate</label>
                                <p><?= date('d F Y H:i', strtotime($user['updated_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Section -->
                <div class="profile-section">
                    <div class="section-header">
                        <h3><i class="fas fa-shield-alt"></i> Keamanan & Akses</h3>
                        <button class="btn btn-warning btn-sm" onclick="showChangePasswordModal()">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </div>
                    <div class="security-grid">
                        <div class="security-item">
                            <div class="security-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="security-content">
                                <h4>Keamanan Akun</h4>
                                <p>Pastikan password Anda kuat dan aman</p>
                                <div class="security-status">
                                    <span class="status-dot success"></span>
                                    <span>Status: Aman</span>
                                </div>
                            </div>
                        </div>
                        <div class="security-item">
                            <div class="security-icon">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <div class="security-content">
                                <h4>Aktivitas Terkini</h4>
                                <p>Login terakhir: <?= date('d M Y H:i') ?></p>
                                <p>IP Address: <?= $_SERVER['REMOTE_ADDR'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Profile -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Profil</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editProfileForm" method="POST" action="index.php?action=update_profile" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group">
                        <label>Foto Profil</label>
                        <div class="file-upload-container">
                            <div class="file-upload-preview">
                                <?php if ($user['foto_profil']): ?>
                                <div class="current-photo">
                                    <p><strong>Foto Saat Ini:</strong></p>
                                    <!-- PERBAIKAN: Gunakan get_file_url untuk akses web -->
                                    <img src="<?= htmlspecialchars(get_file_url($user['foto_profil'], 'photo')) ?>" alt="Foto Profil" class="current-photo-img">
                                </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="foto_profil" accept="image/*" class="file-input">
                            <small class="form-help">Format: JPG, JPEG, PNG (Maksimal 2MB)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-control" placeholder="Masukkan alamat email">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editProfileModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Change Password -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Ubah Password</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" method="POST" action="index.php?action=change_password">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group">
                        <label>Password Saat Ini <span class="required">*</span></label>
                        <input type="password" name="current_password" required class="form-control" placeholder="Masukkan password saat ini">
                    </div>
                    
                    <div class="form-group">
                        <label>Password Baru <span class="required">*</span></label>
                        <input type="password" name="new_password" required class="form-control" placeholder="Masukkan password baru">
                        <small class="form-help">Minimal 6 karakter, disarankan kombinasi huruf dan angka</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Konfirmasi Password Baru <span class="required">*</span></label>
                        <input type="password" name="confirm_password" required class="form-control" placeholder="Konfirmasi password baru">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('changePasswordModal')">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showEditProfileModal() {
        document.getElementById('editProfileModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function showChangePasswordModal() {
        document.getElementById('changePasswordModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    // Preview image sebelum upload
    document.querySelector('input[name="foto_profil"]')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewContainer = document.querySelector('.file-upload-preview');
                previewContainer.innerHTML = `
                    <div class="photo-preview">
                        <p><strong>Preview Foto Baru:</strong></p>
                        <img src="${e.target.result}" alt="Preview Foto" class="preview-photo">
                    </div>
                `;
            }
            reader.readAsDataURL(file);
        }
    });
    </script>

    <style>

    /* Tambahkan di bagian CSS */
    .upload-structure-info {
        background: #e8f4fd;
        border: 1px solid #b6d7f2;
        border-radius: 5px;
        padding: 12px;
        margin: 10px 0;
        font-size: 12px;

    }

    .upload-structure-info h4 {
        margin: 0 0 8px 0;
        color: #2c5aa0;
        font-size: 14px;
    }

    .upload-structure-info .structure-path {
        font-family: monospace;
        background: #f8f9fa;
        padding: 8px;
        border-radius: 3px;
        border: 1px solid #dee2e6;
        margin: 5px 0;
        color: #495057;
        
    }

    .profile-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .profile-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }

    /* Profile Header */
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .profile-avatar-section {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .profile-avatar {
        position: relative;
    }

    .avatar-image, .avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.3);
        object-fit: cover;
    }

    .avatar-placeholder {
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
    }

    .profile-basic-info h2 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
    }

    .profile-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 8px;
    }

    .profile-level {
        font-size: 12px;
        padding: 6px 12px;
    }

    .username {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
    }

    .profile-email {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
    }

    .profile-stats {
        display: flex;
        gap: 30px;
        text-align: center;
    }

    .stat-item .stat-number {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .stat-item .stat-label {
        font-size: 12px;
        opacity: 0.8;
    }

    /* Profile Content */
    .profile-content {
        padding: 40px;
    }

    .profile-section {
        margin-bottom: 40px;
    }

    .profile-section:last-child {
        margin-bottom: 0;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .section-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Info Grid */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .info-item {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .info-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .info-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
        flex-shrink: 0;
    }

    .info-content {
        flex: 1;
    }

    .info-content label {
        display: block;
        font-weight: 600;
        color: #555;
        margin-bottom: 5px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-content p {
        margin: 0;
        color: #2c3e50;
        font-size: 14px;
        font-weight: 500;
    }

    /* Security Grid */
    .security-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .security-item {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 25px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .security-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }

    .security-content h4 {
        margin: 0 0 8px 0;
        color: #2c3e50;
        font-size: 16px;
    }

    .security-content p {
        margin: 0 0 10px 0;
        color: #666;
        font-size: 14px;
        line-height: 1.4;
    }

    .security-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #27ae60;
        font-weight: 500;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-dot.success {
        background: #27ae60;
    }

    /* Modal Styles */
    .file-upload-container {
        margin-bottom: 10px;
    }

    .file-upload-preview {
        margin-bottom: 15px;
    }

    .current-photo-img, .preview-photo {
        max-width: 150px;
        max-height: 150px;
        border-radius: 8px;
        border: 2px solid #e1e1e1;
    }

    .photo-preview {
        margin-top: 10px;
    }

    .required {
        color: #e74c3c;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e1e1e1;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
            gap: 25px;
            padding: 30px 20px;
        }

        .profile-avatar-section {
            flex-direction: column;
            text-align: center;
        }

        .profile-stats {
            justify-content: center;
        }

        .section-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .info-grid,
        .security-grid {
            grid-template-columns: 1fr;
        }

        .profile-content {
            padding: 25px 20px;
        }

        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
    }

    @media (max-width: 480px) {
        .profile-meta {
            flex-direction: column;
            gap: 8px;
        }

        .profile-stats {
            flex-direction: column;
            gap: 15px;
        }

        .info-item,
        .security-item {
            flex-direction: column;
            text-align: center;
        }

        .info-icon,
        .security-icon {
            align-self: center;
        }
    }
    </style>
    <?php
    show_footer();
}

// =============================================
// FUNGSI TAMPILAN (HEADER, FOOTER, LOGIN)
// =============================================

function show_login_page() {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Sistem Arsip Digital SMKN 6 Kota Serang</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 24px;
            }
            
            .login-header p {
                color: #666;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #333;
                font-weight: 500;
            }
            
            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #e1e1e1;
                border-radius: 8px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .btn-login {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .btn-login:hover {
                transform: translateY(-2px);
            }
            
            .alert {
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
            }
            
            .alert-error {
                background: #fee;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            
            .alert-success {
                background: #e8f5e8;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1><i class="fas fa-archive"></i> Sistem Arsip Digital</h1>
                <p>SMKN 6 Kota Serang</p>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <form method="POST" action="index.php?action=login">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" required placeholder="Masukkan username">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required placeholder="Masukkan password">
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
                <p>&copy; 2024 SMKN 6 Kota Serang. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function show_header($title = 'Dashboard') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> - Sistem Arsip Digital</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f8f9fa;
                color: #333;
            }
            
            .app-container {
                display: flex;
                min-height: 100vh;
            }
            
            /* Sidebar Styles */
            .sidebar {
                width: 250px;
                background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
                color: white;
                position: fixed;
                height: 100vh;
                overflow-y: auto;
                transition: transform 0.3s;
                z-index: 1000;
            }
            
            .sidebar-header {
                padding: 20px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
                text-align: center;
            }
            
            .sidebar-header h2 {
                font-size: 18px;
                margin-bottom: 5px;
            }
            
            .sidebar-header p {
                font-size: 12px;
                opacity: 0.8;
            }
            
            .sidebar-menu {
                padding: 20px 0;
            }
            
            .menu-item {
                padding: 12px 20px;
                display: flex;
                align-items: center;
                color: rgba(255,255,255,0.8);
                text-decoration: none;
                transition: all 0.3s;
                border-left: 3px solid transparent;
            }
            
            .menu-item:hover, .menu-item.active {
                background: rgba(255,255,255,0.1);
                color: white;
                border-left-color: #3498db;
            }
            
            .menu-item i {
                width: 20px;
                margin-right: 10px;
            }
            
            /* Main Content Styles */
            .main-content {
                flex: 1;
                margin-left: 250px;
                transition: margin-left 0.3s;
            }
            
            .top-header {
                background: white;
                padding: 15px 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .user-info {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #3498db;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
            }
            
            .user-avatar img {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                object-fit: cover;
            }
            
            .content {
                padding: 30px;
            }
            
            /* Alert Styles */
            .alert {
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .alert-error {
                background: #fee;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            
            .alert-success {
                background: #e8f5e8;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            
            .alert-info {
                background: #e3f2fd;
                border: 1px solid #b3e0ff;
                color: #0c5460;
            }
            
            /* Button Styles */
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s;
            }
            
            .btn-primary {
                background: #3498db;
                color: white;
            }
            
            .btn-primary:hover {
                background: #2980b9;
            }
            
            .btn-success {
                background: #27ae60;
                color: white;
            }
            
            .btn-success:hover {
                background: #219a52;
            }
            
            .btn-danger {
                background: #e74c3c;
                color: white;
            }
            
            .btn-danger:hover {
                background: #c0392b;
            }
            
            .btn-warning {
                background: #f39c12;
                color: white;
            }
            
            .btn-warning:hover {
                background: #d68910;
            }
            
            .btn-info {
                background: #17a2b8;
                color: white;
            }
            
            .btn-info:hover {
                background: #138496;
            }
            
            .btn-outline {
                background: transparent;
                border: 2px solid #3498db;
                color: #3498db;
            }
            
            .btn-outline:hover {
                background: #3498db;
                color: white;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .form-help {
                display: block;
                margin-top: 5px;
                font-size: 12px;
                color: #6c757d;
            }

            .file-input {
                padding: 8px;
                border: 2px dashed #dee2e6;
                border-radius: 6px;
                width: 100%;
            }

            /* Table Styles */
            .table-responsive {
                overflow-x: auto;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #e1e1e1;
            }
            
            .data-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #555;
            }
            
            .data-table tr:hover {
                background: #f8f9fa;
            }
            
            /* Badge Styles */
            .badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
            }
            
            .badge-primary { background: #3498db; color: white; }
            .badge-success { background: #27ae60; color: white; }
            .badge-danger { background: #e74c3c; color: white; }
            .badge-warning { background: #f39c12; color: white; }
            .badge-info { background: #17a2b8; color: white; }
            .badge-secondary { background: #95a5a6; color: white; }
            .badge-light { background: #ecf0f1; color: #2c3e50; }
            .badge-dark { background: #34495e; color: white; }
            
            /* Form Styles */
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: #555;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 10px 12px;
                border: 2px solid #e1e1e1;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            
            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: #3498db;
            }
            
            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .form-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 20px;
            }
            
            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1001;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
            }
            
            .modal-content {
                background: white;
                margin: 5% auto;
                padding: 0;
                border-radius: 8px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .modal-header {
                padding: 20px;
                border-bottom: 1px solid #e1e1e1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                margin: 0;
                color: #333;
            }
            
            .close {
                font-size: 24px;
                cursor: pointer;
                color: #999;
            }
            
            .close:hover {
                color: #333;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            /* Content Header */
            .content-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
            }
            
            .header-title h2 {
                color: #2c3e50;
                margin-bottom: 5px;
            }
            
            .header-title p {
                color: #7f8c8d;
            }
            
            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                color: white;
            }
            
            .stat-icon.primary { background: #3498db; }
            .stat-icon.success { background: #27ae60; }
            .stat-icon.danger { background: #e74c3c; }
            .stat-icon.warning { background: #f39c12; }
            
            .stat-info h3 {
                font-size: 14px;
                color: #7f8c8d;
                margin-bottom: 5px;
            }
            
            .stat-number {
                font-size: 28px;
                font-weight: bold;
                color: #2c3e50;
            }
            
            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #7f8c8d;
            }
            
            .empty-state i {
                font-size: 48px;
                margin-bottom: 15px;
                opacity: 0.5;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                }
                
                .main-content {
                    margin-left: 0;
                }
                
                .sidebar.active {
                    transform: translateX(0);
                }
                
                .content-header {
                    flex-direction: column;
                    gap: 15px;
                    align-items: flex-start;
                }
                
                .form-row {
                    grid-template-columns: 1fr;
                }
                
                .stats-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            /* Button Group */
            .btn-group {
                display: flex;
                gap: 5px;
            }
            
            /* Text Utilities */
            .text-center { text-align: center; }
            .text-muted { color: #7f8c8d; }
            
            /* Filter Section */
            .filter-section {
                background: white;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .filter-form {
                display: flex;
                gap: 15px;
                align-items: end;
                flex-wrap: wrap;
            }
            
            .filter-group {
                flex: 1;
                min-width: 150px;
            }
            
            .filter-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: #555;
            }
            
            /* Mobile Menu Toggle */
            .mobile-menu-toggle {
                display: none;
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #2c3e50;
            }
            
            @media (max-width: 768px) {
                .mobile-menu-toggle {
                    display: block;
                }
                
                .top-header {
                    padding: 15px 20px;
                }
                
                .content {
                    padding: 20px;
                }
            }
            /* Module Settings Styles */
            .module-settings-container {
                display: flex;
                flex-direction: column;
                gap: 30px;
            }

            .modules-section {
                background: white;
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            }

            .modules-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .module-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                transition: all 0.3s ease;
            }

            .module-card:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            }

            .module-header {
                display: flex;
                justify-content: between;
                align-items: start;
                margin-bottom: 15px;
            }

            .module-header h4 {
                flex: 1;
                margin: 0;
                color: #2c3e50;
                font-size: 16px;
            }

            .module-badge {
                font-size: 10px !important;
                padding: 4px 8px !important;
            }

            .module-info {
                border-top: 1px solid #e9ecef;
                padding-top: 15px;
            }

            .module-info p {
                margin: 5px 0;
                font-size: 13px;
            }

            .user-access-section {
                background: white;
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            }

            .access-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            .access-table th,
            .access-table td {
                padding: 12px 15px;
                border-bottom: 1px solid #e9ecef;
                text-align: left;
            }

            .access-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #495057;
            }

            .access-table td {
                vertical-align: middle;
            }

            .module-switch-cell {
                text-align: center;
                width: 100px;
            }

            /* Switch Styles */
            .switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 24px;
            }

            .switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 24px;
            }

            .slider:before {
                position: absolute;
                content: "";
                height: 16px;
                width: 16px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            input:checked + .slider {
                background-color: #28a745;
            }

            input:checked + .slider:before {
                transform: translateX(26px);
            }

            input:disabled + .slider {
                background-color: #6c757d;
                cursor: not-allowed;
            }

            /* Notification Styles */
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                z-index: 10000;
                animation: slideInRight 0.3s ease-out;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }

            .notification-success {
                background-color: #28a745;
            }

            .notification-error {
                background-color: #dc3545;
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .modules-grid {
                    grid-template-columns: 1fr;
                }
                
                .access-table {
                    font-size: 12px;
                }
                
                .access-table th,
                .access-table td {
                    padding: 8px 10px;
                }
            }
            /* Tambahkan di bagian CSS */
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                z-index: 10000;
                animation: slideInRight 0.3s ease-out;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                min-width: 300px;
            }

            .notification-success {
                background-color: #28a745;
            }

            .notification-error {
                background-color: #dc3545;
            }

            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .notification-close {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                margin-left: auto;
                opacity: 0.8;
            }

            .notification-close:hover {
                opacity: 1;
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            /* Tambahkan di bagian CSS */
            .upload-type-selector {
                display: flex;
                gap: 20px;
                margin-bottom: 15px;
            }

            .upload-type-option {
                flex: 1;
                text-align: center;
                padding: 15px;
                border: 2px solid #e1e1e1;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s;
            }

            .upload-type-option:hover {
                border-color: #3498db;
                background: #f8f9fa;
            }

            .upload-type-option.selected {
                border-color: #3498db;
                background: #e3f2fd;
            }

            .upload-type-option input[type="radio"] {
                margin-right: 8px;
            }

            /* Pagination Styles */
            .pagination-section {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }

            .pagination-info {
                color: #6c757d;
                font-size: 14px;
                font-weight: 500;
            }

            .pagination-controls {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .pagination-dropdown {
                margin-left: 15px;
            }

            .per-page-form {
                margin: 0;
            }

            .per-page-select {
                padding: 6px 12px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                background: white;
                font-size: 14px;
                cursor: pointer;
                transition: border-color 0.3s;
            }

            .per-page-select:focus {
                outline: none;
                border-color: #3498db;
                box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .pagination-section {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }
                
                .pagination-controls {
                    flex-wrap: wrap;
                    justify-content: center;
                }
                
                .pagination-dropdown {
                    margin-left: 0;
                    margin-top: 10px;
                }
            }

            /* Button styles for pagination */
            .btn-sm {
                padding: 6px 12px;
                font-size: 12px;
                min-width: 40px;
            }

            .btn-outline.disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        </style>
    </head>
    <body>
        <div class="app-container">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h2><i class="fas fa-archive"></i> Sistem Arsip</h2>
                    <p>SMKN 6 Kota Serang</p>
                </div>
                
                <div class="sidebar-menu">
                    <!-- Profil dan Logout selalu tampil -->
                    <a href="index.php?action=profile" 
                    class="menu-item <?= ($_GET['action'] ?? '') == 'profile' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i> Profil
                    </a>
                    <?php 
                    // Selalu tampilkan Dashboard jika sudah login
                    if (is_logged_in()): ?>
                        <a href="index.php?action=dashboard" 
                        class="menu-item <?= ($_GET['action'] ?? '') == 'dashboard' ? 'active' : '' ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    <?php endif;

                    // Manajemen Arsip
                    if (check_module_access('arsip')): ?>
                        <a href="index.php?action=arsip" 
                        class="menu-item <?= ($_GET['action'] ?? '') == 'arsip' ? 'active' : '' ?>">
                            <i class="fas fa-folder"></i> Manajemen Arsip
                        </a>
                    <?php endif;

                    // Laporan
                    if (check_module_access('laporan')): ?>
                        <a href="index.php?action=laporan" 
                        class="menu-item <?= ($_GET['action'] ?? '') == 'laporan' ? 'active' : '' ?>">
                            <i class="fas fa-chart-bar"></i> Laporan
                        </a>
                    <?php endif; ?>

                    <?php 
                    // Menu Master untuk Admin
                    if ($_SESSION['level'] == 'admin'):
                        $active_master = in_array(($_GET['action'] ?? ''), ['users','kategori','backup_restore','pengaturan_modul']);
                    ?>
                        <div class="menu-group <?= $active_master ? 'open' : '' ?>">
                            <div class="menu-item menu-toggle" onclick="toggleSubmenu(this)">
                                <i class="fas fa-layer-group"></i> Master
                                <i class="fas fa-chevron-down submenu-icon <?= $active_master ? 'rotate' : '' ?>"></i>
                            </div>
                            <div class="submenu <?= $active_master ? 'show' : '' ?>">
                                <?php if (check_module_access('users')): ?>
                                <a href="index.php?action=users" 
                                class="submenu-item <?= ($_GET['action'] ?? '') == 'users' ? 'active' : '' ?>">
                                    <i class="fas fa-users"></i> Manajemen User
                                </a>
                                <?php endif; ?>

                                <?php if (check_module_access('kategori')): ?>
                                <a href="index.php?action=kategori" 
                                class="submenu-item <?= ($_GET['action'] ?? '') == 'kategori' ? 'active' : '' ?>">
                                    <i class="fas fa-tags"></i> Kategori & Jenis
                                </a>
                                <?php endif; ?>

                                <?php if (check_module_access('backup_restore')): ?>
                                <a href="index.php?action=backup_restore" 
                                class="submenu-item <?= ($_GET['action'] ?? '') == 'backup_restore' ? 'active' : '' ?>">
                                    <i class="fas fa-database"></i> Backup & Restore
                                </a>
                                <?php endif; ?>

                                <?php if (check_module_access('pengaturan_modul')): ?>
                                <a href="index.php?action=pengaturan_modul" 
                                class="submenu-item <?= ($_GET['action'] ?? '') == 'pengaturan_modul' ? 'active' : '' ?>">
                                    <i class="fas fa-cog"></i> Pengaturan Modul
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Profil dan Logout selalu tampil -->
                    <a href="index.php?action=logout" class="menu-item" onclick="return confirm('Yakin ingin logout?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>

                <!-- Script toggle submenu -->
                <script>
                function toggleSubmenu(el) {
                    const group = el.parentElement;
                    group.classList.toggle('open');
                    const submenu = el.nextElementSibling;
                    submenu.classList.toggle('show');
                    el.querySelector('.submenu-icon').classList.toggle('rotate');
                }
                </script>

                <!-- Style Sidebar -->
                <style>
                .sidebar-menu {
                    width: 100%;
                    background:  #2c3e50; /* #1e293b warna biru gelap   */
                    color: #f1f5f9;
                    display: flex;
                    flex-direction: column;
                    padding: 10px;
                    min-height: 100vh;
                    font-family: 'Segoe UI', sans-serif;
                }

                .menu-item {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px 12px;
                    border-radius: 6px;
                    color: #f1f5f9;
                    text-decoration: none;
                    transition: background 0.3s;
                }

                .menu-item:hover {
                    background: #334155;
                }

                .menu-item.active {
                    background: #3b82f6; /* biru terang */
                    color: #fff;
                }

                .menu-toggle {
                    cursor: pointer;
                    justify-content: space-between;
                }

                .menu-group {
                    margin-top: 5px;
                }

                .submenu {
                    display: none;
                    flex-direction: column;
                    margin-left: 20px;
                    margin-top: 4px;
                }

                .submenu.show {
                    display: flex;
                }

                .submenu-item {
                    padding: 8px 10px;
                    color: #cbd5e1;
                    border-radius: 5px;
                    text-decoration: none;
                    transition: background 0.3s;
                }

                .submenu-item:hover {
                    background: #475569;
                    color: #fff;
                }

                .submenu-item.active {
                    background: #2563eb;
                    color: #fff;
                }

                .submenu-icon {
                    margin-left: auto;
                    transition: transform 0.3s;
                }

                .submenu-icon.rotate {
                    transform: rotate(180deg);
                }

                .menu-group.open > .menu-toggle {
                    background: #334155;
                    border-radius: 6px;
                }
                </style>

            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="top-header">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php if ($_SESSION['foto_profil']): ?>
                                <!-- PERBAIKAN: Gunakan get_file_url untuk akses web -->
                                <img src="<?= htmlspecialchars(get_file_url($_SESSION['foto_profil'], 'photo')) ?>" alt="Foto Profil">
                            <?php else: ?>
                                <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight: 500;"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                <span class="badge badge-<?= get_level_badge($_SESSION['level']) ?>">
                                    <?= ucfirst($_SESSION['level']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="content">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['info'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <?= $_SESSION['info'] ?>
                        </div>
                        <?php unset($_SESSION['info']); ?>
                    <?php endif; ?>
    <?php
}

function show_footer() {
    ?>
                </div>
            </div>
        </div>
        
        <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // Close modal with close button
        document.querySelectorAll('.close').forEach(function(closeBtn) {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = 'none';
                document.body.style.overflow = 'auto';
            };
        });
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// =============================================
// FUNGSI LAINNYA YANG TIDAK BERUBAH
// =============================================

// =============================================
// FUNGSI YANG MISSING - LAPORAN
// =============================================

function show_laporan() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek','tu',]);
    
    show_header('Laporan dan Statistik');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Laporan dan Statistik</h2>
            <p>Analisis data dan statistik sistem arsip digital</p>
        </div>
        <div class="header-actions">
            <a href="index.php?action=export_excel" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="index.php?action=export_pdf" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>
    
    <div class="laporan-container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Dokumen</h3>
                    <div class="stat-number"><?= $pdo->query("SELECT COUNT(*) FROM dokumen")->fetchColumn() ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Download</h3>
                    <div class="stat-number"><?= $pdo->query("SELECT COUNT(*) FROM download_log")->fetchColumn() ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total User</h3>
                    <div class="stat-number"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = TRUE")->fetchColumn() ?></div>
                </div>
            </div>
        </div>
        
        <div class="charts-section">
            <div class="chart-row">
                <div class="chart-card">
                    <h3>Dokumen per Kategori</h3>
                    <div class="chart-container">
                        <canvas id="kategoriChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <h3>User per Level</h3>
                    <div class="chart-container">
                        <canvas id="userLevelChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="chart-row">
                <div class="chart-card">
                    <h3>Download per Bulan (Tahun <?= date('Y') ?>)</h3>
                    <div class="chart-container">
                        <canvas id="downloadMonthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="recent-activity">
            <div class="section-header">
                <h3>Aktivitas Terbaru</h3>
            </div>
            <div class="activity-list">
                <?php
                $activities = $pdo->query("
                    SELECT a.*, u.nama_lengkap, u.level 
                    FROM audit_trail a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.created_at DESC 
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($activities)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>Belum ada aktivitas</p>
                    </div>
                <?php else:
                    foreach($activities as $activity):
                ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-<?= 
                            strpos($activity['action'], 'LOGIN') !== false ? 'sign-in-alt' : 
                            (strpos($activity['action'], 'LOGOUT') !== false ? 'sign-out-alt' : 
                            (strpos($activity['action'], 'UPLOAD') !== false ? 'upload' : 
                            (strpos($activity['action'], 'DOWNLOAD') !== false ? 'download' : 
                            (strpos($activity['action'], 'DELETE') !== false ? 'trash' : 
                            (strpos($activity['action'], 'EDIT') !== false ? 'edit' : 'info'))))) 
                        ?>"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-text"><?= htmlspecialchars($activity['description'] ?: $activity['action']) ?></p>
                        <p class="activity-meta">
                            <span class="user"><?= htmlspecialchars($activity['nama_lengkap'] ?: 'System') ?></span>
                            <span class="time"><?= date('d M Y H:i', strtotime($activity['created_at'])) ?></span>
                        </p>
                    </div>
                </div>
                <?php endforeach; 
                endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dokumen per Kategori
        const kategoriCtx = document.getElementById('kategoriChart').getContext('2d');
        fetch('index.php?action=get_kategori_stats')
            .then(response => response.json())
            .then(data => {
                new Chart(kategoriCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.data,
                            backgroundColor: [
                                '#3498db', '#e74c3c', '#2ecc71', '#f39c12', 
                                '#9b59b6', '#1abc9c', '#34495e', '#d35400'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            });
        
        // User per Level
        const userLevelCtx = document.getElementById('userLevelChart').getContext('2d');
        fetch('index.php?action=get_user_level_stats')
            .then(response => response.json())
            .then(data => {
                new Chart(userLevelCtx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Jumlah User',
                            data: data.data,
                            backgroundColor: '#27ae60'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        
        // Download per Bulan
        const downloadMonthlyCtx = document.getElementById('downloadMonthlyChart').getContext('2d');
        fetch('index.php?action=get_download_monthly_stats')
            .then(response => response.json())
            .then(data => {
                new Chart(downloadMonthlyCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Jumlah Download',
                            data: data.data,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
    });
    </script>
    <?php
    show_footer();
}

// =============================================
// FUNGSI YANG MISSING - BACKUP & RESTORE
// =============================================

function show_backup_restore() {
    global $pdo;
    check_access(['admin','tu']);
    
    // Get list of backup files
    $backup_files = [];
    if (is_dir(BACKUP_PATH)) {
        $files = scandir(BACKUP_PATH);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                $file_path = BACKUP_PATH . $file;
                $backup_files[] = [
                    'name' => $file,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path)
                ];
            }
        }
        // Sort by modification time (newest first)
        usort($backup_files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    
    show_header('Backup dan Restore Database');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Backup dan Restore Database</h2>
            <p>Kelola backup dan restore database sistem</p>
        </div>
    </div>
    
    <div class="backup-restore-container">
        <div class="action-section">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="action-content">
                    <h3>Backup Database</h3>
                    <p>Buat backup seluruh database sistem</p>
                    <form method="POST" action="index.php?action=backup_database" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup database?')">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download"></i> Backup Sekarang
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="action-content">
                    <h3>Restore Database</h3>
                    <p>Restore database dari file backup</p>
                    <form method="POST" action="index.php?action=restore_database" enctype="multipart/form-data" onsubmit="return confirm('PERINGATAN: Restore akan mengganti seluruh data yang ada. Apakah Anda yakin?')">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="form-group">
                            <label>Pilih File Backup (.sql)</label>
                            <input type="file" name="backup_file" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-upload"></i> Restore Database
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="backup-list-section">
            <div class="section-header">
                <h3>File Backup Tersedia</h3>
            </div>
            
            <?php if (empty($backup_files)): ?>
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <p>Belum ada file backup</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Tanggal Backup</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($backup_files as $file): ?>
                            <tr>
                                <td><?= htmlspecialchars($file['name']) ?></td>
                                <td><?= format_file_size($file['size']) ?></td>
                                <td><?= date('d M Y H:i', $file['modified']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= BASE_URL ?>/backups/<?= htmlspecialchars($file['name']) ?>" 
                                           class="btn btn-sm btn-success" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <form method="POST" action="index.php?action=restore_database" style="display: inline;" 
                                              onsubmit="return confirm('PERINGATAN: Restore akan mengganti seluruh data yang ada. Apakah Anda yakin?')">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="existing_file" value="<?= htmlspecialchars($file['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="fas fa-upload"></i> Restore
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="info-section">
            <div class="alert alert-info">
                <h4><i class="fas fa-info-circle"></i> Informasi Backup & Restore</h4>
                <ul>
                    <li>Backup akan menyimpan seluruh struktur dan data database</li>
                    <li>File backup disimpan dalam format SQL di folder <code>backups/</code></li>
                    <li>Restore akan mengganti seluruh data yang ada dengan data dari backup</li>
                    <li>Pastikan untuk membuat backup sebelum melakukan restore</li>
                    <li>Hanya file dengan ekstensi .sql yang dapat digunakan untuk restore</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
    show_footer();
}

// =============================================
// FUNGSI YANG MISSING - PENGATURAN MODUL
// =============================================

function manage_pengaturan_modul() {
    global $pdo;
    check_access(['admin','tu']);
    
    // Get all modules
    $modules = $pdo->query("SELECT * FROM modules ORDER BY module_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all users with their module access
    $users = $pdo->query("SELECT id, username, nama_lengkap, level FROM users WHERE is_active = TRUE ORDER BY nama_lengkap")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user module access
    $user_modules = [];
    $stmt = $pdo->prepare("SELECT user_id, module_id, is_active FROM user_modules");
    $stmt->execute();
    $user_module_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($user_module_data as $um) {
        $user_modules[$um['user_id']][$um['module_id']] = $um['is_active'];
    }
    
    show_header('Pengaturan Modul Menu');
    ?>
    <div class="content-header">
        <div class="header-title">
            <h2>Pengaturan Modul Menu</h2>
            <p>Kelola akses modul menu untuk setiap pengguna - Sistem hanya menggunakan pengaturan ini</p>
        </div>
    </div>
    
    <div class="module-settings-container">
        <!-- Information Banner -->
        <div class="alert alert-info">
            <h4><i class="fas fa-info-circle"></i> Sistem Pengaturan Baru</h4>
            <p><strong>Perhatian:</strong> Sistem sekarang hanya menggunakan pengaturan modul menu. Tidak ada akses default berdasarkan level user.</p>
            <p>Setiap user (kecuali admin) harus diatur akses modulnya secara manual melalui tabel di bawah.</p>
        </div>
        
        <!-- Modules List Section -->
        <div class="modules-section">
            <div class="section-header">
                <h3><i class="fas fa-cubes"></i> Daftar Modul Sistem</h3>
                <p>Semua modul yang tersedia dalam sistem</p>
            </div>
            <div class="modules-grid">
                <?php foreach($modules as $module): ?>
                <div class="module-card">
                    <div class="module-header">
                        <h4><?= htmlspecialchars($module['module_description']) ?></h4>
                        <span class="badge badge-<?= get_level_badge($module['required_level']) ?> module-badge">
                            Level: <?= ucfirst($module['required_level']) ?>
                        </span>
                    </div>
                    <div class="module-info">
                        <p><strong><i class="fas fa-code"></i> Kode Modul:</strong> <?= htmlspecialchars($module['module_name']) ?></p>
                        <p><strong><i class="fas fa-info-circle"></i> Status Sistem:</strong> 
                            <span class="badge <?= $module['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $module['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </p>
                        <p><strong><i class="fas fa-align-left"></i> Level Default (Referensi):</strong> <?= ucfirst($module['required_level']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- User Access Section -->
        <div class="user-access-section">
            <div class="section-header">
                <h3><i class="fas fa-user-shield"></i> Pengaturan Akses Modul per User</h3>
                <p>Atur hak akses modul untuk setiap pengguna - Centang untuk mengaktifkan</p>
            </div>
            
            <div class="table-responsive">
                <table class="access-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Level</th>
                            <?php foreach($modules as $module): ?>
                            <th class="module-switch-cell" title="<?= htmlspecialchars($module['module_description']) ?>">
                                <div class="module-header-small">
                                    <div><?= htmlspecialchars($module['module_description']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($module['module_name']) ?></small>
                                </div>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): 
                            $is_admin = $user['level'] == 'admin';
                        ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong><br>
                                    <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= get_level_badge($user['level']) ?>">
                                    <i class="fas fa-user-tag"></i> <?= ucfirst($user['level']) ?>
                                </span>
                                <?php if ($is_admin): ?>
                                <br><small class="text-success">Full Access</small>
                                <?php endif; ?>
                            </td>
                            <?php foreach($modules as $module): 
                                $is_active = isset($user_modules[$user['id']][$module['id']]) ? 
                                    $user_modules[$user['id']][$module['id']] : false;
                            ?>
                            <td class="module-switch-cell">
                                <?php if (!$is_admin): ?>
                                <label class="switch">
                                    <input type="checkbox" 
                                           class="module-toggle"
                                           data-user-id="<?= $user['id'] ?>" 
                                           data-module-id="<?= $module['id'] ?>"
                                           data-module-name="<?= htmlspecialchars($module['module_description']) ?>"
                                           data-user-name="<?= htmlspecialchars($user['nama_lengkap']) ?>"
                                           <?= $is_active ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                                <div class="module-status">
                                    <?php if ($is_active): ?>
                                        <small class="text-success">Aktif</small>
                                    <?php else: ?>
                                        <small class="text-danger">Nonaktif</small>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-crown"></i> Admin
                                </span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-section">
            <div class="section-header">
                <h3><i class="fas fa-bolt"></i> Aksi Cepat</h3>
            </div>
            <div class="action-buttons">
                <button type="button" class="btn btn-outline" onclick="activateAllForLevel('tu')">
                    <i class="fas fa-users"></i> Aktifkan Semua untuk Tata Usaha
                </button>
                <button type="button" class="btn btn-outline" onclick="activateAllForLevel('guru')">
                    <i class="fas fa-chalkboard-teacher"></i> Aktifkan Semua untuk Guru
                </button>
                <button type="button" class="btn btn-outline" onclick="deactivateAllNonAdmin()">
                    <i class="fas fa-ban"></i> Nonaktifkan Semua (Non-Admin)
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Initialize module toggles
    document.addEventListener('DOMContentLoaded', function() {
        const toggles = document.querySelectorAll('.module-toggle');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const userId = this.dataset.userId;
                const moduleId = this.dataset.moduleId;
                const moduleName = this.dataset.moduleName;
                const userName = this.dataset.userName;
                const isActive = this.checked ? 1 : 0;
                
                updateModuleAccess(userId, moduleId, isActive, moduleName, userName, this);
            });
        });
    });
    
    function updateModuleAccess(userId, moduleId, isActive, moduleName, userName, checkbox) {
    const originalState = checkbox.checked;
    
    // Show loading
    const statusElement = checkbox.closest('.module-switch-cell').querySelector('.module-status');
    statusElement.innerHTML = '<small class="text-warning">Loading...</small>';
    
    // Buat form data
    const formData = new URLSearchParams();
    formData.append('user_id', userId);
    formData.append('module_id', moduleId);
    formData.append('is_active', isActive ? '1' : '0');
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');
    
    fetch('index.php?action=update_module_status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
    })
    .then(response => {
        // Cek jika response adalah JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Response bukan JSON');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            statusElement.innerHTML = isActive ? 
                '<small class="text-success">Aktif</small>' : 
                '<small class="text-danger">Nonaktif</small>';
            showNotification(
                data.message || `Akses "${moduleName}" untuk ${userName} berhasil ${isActive ? 'diaktifkan' : 'dinonaktifkan'}`,
                'success'
            );
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Kembalikan ke state semula
        checkbox.checked = !originalState;
        statusElement.innerHTML = originalState ? 
            '<small class="text-success">Aktif</small>' : 
            '<small class="text-danger">Nonaktif</small>';
        
        let errorMessage = error.message;
        if (error.message.includes('JSON')) {
            errorMessage = 'Terjadi kesalahan server. Silakan refresh halaman dan coba lagi.';
        }
        
        showNotification(`Gagal memperbarui akses: ${errorMessage}`, 'error');
    });
}
    
    // Quick action functions
    function activateAllForLevel(level) {
        if (confirm(`Aktifkan semua modul untuk semua user dengan level ${level.toUpperCase()}?`)) {
            // This would require additional backend implementation
            showNotification('Fitur aksi cepat membutuhkan implementasi backend tambahan', 'info');
        }
    }
    
    function deactivateAllNonAdmin() {
        if (confirm('Nonaktifkan semua modul untuk semua user non-admin?')) {
            // This would require additional backend implementation
            showNotification('Fitur aksi cepat membutuhkan implementasi backend tambahan', 'info');
        }
    }
    
    function showNotification(message, type) {
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 
                                  type === 'error' ? 'exclamation-triangle' : 
                                  'info-circle'}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    </script>
    
    <style>
    .module-header-small {
        font-size: 12px;
        line-height: 1.3;
    }
    .module-header-small small {
        font-size: 10px;
    }
    .module-status {
        margin-top: 5px;
        text-align: center;
    }
    .user-info {
        line-height: 1.4;
    }
    .module-switch-cell {
        text-align: center;
        min-width: 120px;
        padding: 10px 5px;
    }
    .access-table {
        font-size: 12px;
    }
    .access-table th {
        font-size: 11px;
        text-align: center;
        vertical-align: bottom;
    }
    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .quick-actions-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    </style>
    <?php
    show_footer();
}

// =============================================
// FUNGSI TAMBAHAN UNTUK ROUTING STATISTIK
// =============================================

// Tambahkan routing untuk statistik di blok switch-case utama
// Tambahkan case berikut di dalam blok switch($action):

/*
case 'get_kategori_stats':
    get_kategori_stats();
    break;
case 'get_user_level_stats':
    get_user_level_stats();
    break;
case 'get_download_monthly_stats':
    get_download_monthly_stats();
    break;
*/


function handle_change_password() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek', 'kaprog', 'kabeng', 'guru', 'siswa', 'tu']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=profile');
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validasi password
        if (strlen($new_password) < 6) {
            $_SESSION['error'] = "Password baru minimal 6 karakter";
            header('Location: index.php?action=profile');
            exit;
        }
        
        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Konfirmasi password tidak sesuai";
            header('Location: index.php?action=profile');
            exit;
        }
        
        try {
            // Verifikasi password saat ini
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $_SESSION['error'] = "Password saat ini salah";
                header('Location: index.php?action=profile');
                exit;
            }
            
            // Update password baru
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            log_audit($user_id, 'CHANGE_PASSWORD', "User mengubah password");
            $_SESSION['success'] = "Password berhasil diubah!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal mengubah password: " . $e->getMessage();
        }
        
        header('Location: index.php?action=profile');
        exit;
    }
}

function get_user_data() {
    global $pdo;

    if (!is_logged_in() || $_SESSION['level'] != 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $user_id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT id, username, nama_lengkap, email, level, is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    echo json_encode(['success' => true] + $user);
    exit;
}

function get_kategori_data() {
    global $pdo;

    if (!is_logged_in() || $_SESSION['level'] != 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $kategori_id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM kategori WHERE id = ?");
    $stmt->execute([$kategori_id]);
    $kategori = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kategori) {
        echo json_encode(['success' => false, 'error' => 'Kategori not found']);
        exit;
    }

    echo json_encode(['success' => true] + $kategori);
    exit;
}

function get_jenis_data() {
    global $pdo;

    if (!is_logged_in() || $_SESSION['level'] != 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $jenis_id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM jenis_dokumen WHERE id = ?");
    $stmt->execute([$jenis_id]);
    $jenis = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jenis) {
        echo json_encode(['success' => false, 'error' => 'Jenis not found']);
        exit;
    }

    echo json_encode(['success' => true] + $jenis);
    exit;
}

function handle_delete_user() {
    global $pdo;
    check_access(['admin']);
    
    $user_id = $_GET['id'] ?? 0;
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Token keamanan tidak valid";
        header('Location: index.php?action=users');
        exit;
    }
    
    // Prevent self-deletion
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Tidak dapat menghapus akun sendiri!";
        header('Location: index.php?action=users');
        exit;
    }
    
    try {
        // Check if user has documents
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $doc_count = $stmt->fetchColumn();
        
        if ($doc_count > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus user yang memiliki dokumen!";
            header('Location: index.php?action=users');
            exit;
        }
        
        // Delete user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        
        log_audit($_SESSION['user_id'], 'DELETE_USER', "Hapus user ID: $user_id");
        $_SESSION['success'] = "User berhasil dihapus!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus user: " . $e->getMessage();
    }
    
    header('Location: index.php?action=users');
    exit;
}

function handle_delete_kategori() {
    global $pdo;
    check_access(['admin']);
    
    $kategori_id = $_GET['id'] ?? 0;
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Token keamanan tidak valid";
        header('Location: index.php?action=kategori');
        exit;
    }
    
    try {
        // Check if kategori has documents
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen WHERE kategori_id = ?");
        $stmt->execute([$kategori_id]);
        $doc_count = $stmt->fetchColumn();
        
        if ($doc_count > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus kategori yang memiliki dokumen!";
            header('Location: index.php?action=kategori');
            exit;
        }
        
        // Delete kategori
        $pdo->prepare("DELETE FROM kategori WHERE id = ?")->execute([$kategori_id]);
        
        log_audit($_SESSION['user_id'], 'DELETE_KATEGORI', "Hapus kategori ID: $kategori_id");
        $_SESSION['success'] = "Kategori berhasil dihapus!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus kategori: " . $e->getMessage();
    }
    
    header('Location: index.php?action=kategori');
    exit;
}

function handle_delete_jenis() {
    global $pdo;
    check_access(['admin']);
    
    $jenis_id = $_GET['id'] ?? 0;
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Token keamanan tidak valid";
        header('Location: index.php?action=kategori');
        exit;
    }
    
    try {
        // Check if jenis has documents
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen WHERE jenis_id = ?");
        $stmt->execute([$jenis_id]);
        $doc_count = $stmt->fetchColumn();
        
        if ($doc_count > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus jenis dokumen yang memiliki dokumen!";
            header('Location: index.php?action=kategori');
            exit;
        }
        
        // Delete jenis
        $pdo->prepare("DELETE FROM jenis_dokumen WHERE id = ?")->execute([$jenis_id]);
        
        log_audit($_SESSION['user_id'], 'DELETE_JENIS', "Hapus jenis dokumen ID: $jenis_id");
        $_SESSION['success'] = "Jenis dokumen berhasil dihapus!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus jenis dokumen: " . $e->getMessage();
    }
    
    header('Location: index.php?action=kategori');
    exit;
}

function handle_edit_kategori() {
    global $pdo;
    check_access(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=kategori');
            exit;
        }
        
        $kategori_id = $_POST['kategori_id'] ?? null;
        $nama_kategori = sanitize($_POST['nama_kategori']);
        $deskripsi = sanitize($_POST['deskripsi']);
        $user_id = $_SESSION['user_id'];
        
        try {
            if ($kategori_id) {
                // Update existing kategori
                $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id = ?");
                $stmt->execute([$nama_kategori, $deskripsi, $kategori_id]);
                $action = 'EDIT_KATEGORI';
            } else {
                // Insert new kategori
                $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, deskripsi, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$nama_kategori, $deskripsi, $user_id]);
                $action = 'ADD_KATEGORI';
            }
            
            log_audit($user_id, $action, "Kategori: $nama_kategori");
            $_SESSION['success'] = "Kategori berhasil " . ($kategori_id ? "diperbarui" : "ditambahkan") . "!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal menyimpan kategori: " . $e->getMessage();
        }
        
        header('Location: index.php?action=kategori');
        exit;
    }
}

function handle_edit_jenis() {
    global $pdo;
    check_access(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=kategori');
            exit;
        }
        
        $jenis_id = $_POST['jenis_id'] ?? null;
        $kategori_id = $_POST['kategori_id'];
        $nama_jenis = sanitize($_POST['nama_jenis']);
        $deskripsi = sanitize($_POST['deskripsi']);
        $user_id = $_SESSION['user_id'];
        
        try {
            if ($jenis_id) {
                // Update existing jenis
                $stmt = $pdo->prepare("UPDATE jenis_dokumen SET kategori_id = ?, nama_jenis = ?, deskripsi = ? WHERE id = ?");
                $stmt->execute([$kategori_id, $nama_jenis, $deskripsi, $jenis_id]);
                $action = 'EDIT_JENIS';
            } else {
                // Insert new jenis
                $stmt = $pdo->prepare("INSERT INTO jenis_dokumen (kategori_id, nama_jenis, deskripsi, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$kategori_id, $nama_jenis, $deskripsi, $user_id]);
                $action = 'ADD_JENIS';
            }
            
            log_audit($user_id, $action, "Jenis: $nama_jenis");
            $_SESSION['success'] = "Jenis dokumen berhasil " . ($jenis_id ? "diperbarui" : "ditambahkan") . "!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal menyimpan jenis dokumen: " . $e->getMessage();
        }
        
        header('Location: index.php?action=kategori');
        exit;
    }
}

function export_excel() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek','tu']);
    
    // Set headers for Excel file
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_arsip_' . date('Y-m-d') . '.xls"');
    
    // Get data for export
    $query = "
        SELECT d.judul, d.deskripsi, k.nama_kategori, j.nama_jenis, u.nama_lengkap, 
               d.akses, d.tahun_periode, d.download_count, d.created_at
        FROM dokumen d
        LEFT JOIN kategori k ON d.kategori_id = k.id
        LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY d.created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create Excel content
    echo "<table border='1'>";
    echo "<tr>
            <th>No</th>
            <th>Judul</th>
            <th>Deskripsi</th>
            <th>Kategori</th>
            <th>Jenis</th>
            <th>Uploader</th>
            <th>Akses</th>
            <th>Tahun</th>
            <th>Download</th>
            <th>Tanggal Upload</th>
          </tr>";
    
    $no = 1;
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['judul']) . "</td>";
        echo "<td>" . htmlspecialchars($row['deskripsi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kategori']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_jenis']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_lengkap']) . "</td>";
        echo "<td>" . htmlspecialchars($row['akses']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tahun_periode']) . "</td>";
        echo "<td>" . $row['download_count'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

function export_pdf() {
    global $pdo;
    check_access(['admin', 'kepsek', 'wakasek','tu']);
    
    // Simple PDF generation using HTML to PDF (would need TCPDF or similar library in production)
    // For now, we'll create a simple HTML page that can be printed as PDF
    
    $query = "
        SELECT d.judul, d.deskripsi, k.nama_kategori, j.nama_jenis, u.nama_lengkap, 
               d.akses, d.tahun_periode, d.download_count, d.created_at
        FROM dokumen d
        LEFT JOIN kategori k ON d.kategori_id = k.id
        LEFT JOIN jenis_dokumen j ON d.jenis_id = j.id
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY d.created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create PDF content
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Arsip Digital - <?= date('d-m-Y') ?></title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .header { text-align: center; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Laporan Arsip Digital</h2>
            <p>SMKN 6 Kota Serang</p>
            <p>Periode: <?= date('d F Y') ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Judul</th>
                    <th>Kategori</th>
                    <th>Jenis</th>
                    <th>Uploader</th>
                    <th>Akses</th>
                    <th>Tahun</th>
                    <th>Download</th>
                    <th>Tanggal Upload</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($data as $row): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['judul']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                    <td><?= htmlspecialchars($row['nama_jenis']) ?></td>
                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($row['akses']) ?></td>
                    <td><?= htmlspecialchars($row['tahun_periode']) ?></td>
                    <td><?= $row['download_count'] ?></td>
                    <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

function handle_backup_database() {
    global $pdo;
    check_access(['admin','tu']);
    
    try {
        // Get database configuration
        $db_host = DB_HOST;
        $db_name = DB_NAME;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        
        // Create backup filename
        $backup_file = BACKUP_PATH . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // MySQL dump command
        $command = "mysqldump --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > {$backup_file}";
        
        // Execute command
        system($command, $output);
        
        if ($output === 0 && file_exists($backup_file)) {
            log_audit($_SESSION['user_id'], 'BACKUP_DATABASE', "Backup database berhasil");
            $_SESSION['success'] = "Backup database berhasil dibuat!";
        } else {
            throw new Exception("Gagal menjalankan perintah backup");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal membuat backup: " . $e->getMessage();
    }
    
    header('Location: index.php?action=backup_restore');
    exit;
}

function handle_restore_database() {
    global $pdo;
    check_access(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Token keamanan tidak valid";
            header('Location: index.php?action=backup_restore');
            exit;
        }
        
        try {
            // Get database configuration
            $db_host = DB_HOST;
            $db_name = DB_NAME;
            $db_user = DB_USER;
            $db_pass = DB_PASS;
            
            // Determine backup file source
            if (!empty($_FILES['backup_file']['name'])) {
                // From uploaded file
                $backup_file = $_FILES['backup_file']['tmp_name'];
            } elseif (!empty($_POST['existing_file'])) {
                // From existing backup file
                $backup_file = BACKUP_PATH . $_POST['existing_file'];
            } else {
                throw new Exception("Tidak ada file backup yang dipilih");
            }
            
            if (!file_exists($backup_file)) {
                throw new Exception("File backup tidak ditemukan");
            }
            
            // MySQL restore command
            $command = "mysql --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} < {$backup_file}";
            
            // Execute command
            system($command, $output);
            
            if ($output === 0) {
                log_audit($_SESSION['user_id'], 'RESTORE_DATABASE', "Restore database berhasil");
                $_SESSION['success'] = "Restore database berhasil!";
            } else {
                throw new Exception("Gagal menjalankan perintah restore");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal melakukan restore: " . $e->getMessage();
        }
    }
    
    header('Location: index.php?action=backup_restore');
    exit;
}

// =============================================
// FUNGSI STATISTIK UNTUK LAPORAN
// =============================================

function get_kategori_stats() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT k.nama_kategori, COUNT(d.id) as jumlah 
        FROM kategori k 
        LEFT JOIN dokumen d ON k.id = d.kategori_id 
        GROUP BY k.id, k.nama_kategori 
        ORDER BY jumlah DESC
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($stats as $stat) {
        $labels[] = $stat['nama_kategori'];
        $data[] = (int)$stat['jumlah'];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

function get_user_level_stats() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT level, COUNT(*) as jumlah 
        FROM users 
        WHERE is_active = TRUE 
        GROUP BY level 
        ORDER BY jumlah DESC
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    
    foreach ($stats as $stat) {
        $labels[] = ucfirst($stat['level']);
        $data[] = (int)$stat['jumlah'];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

function get_download_monthly_stats() {
    global $pdo;
    
    $current_year = date('Y');
    $stats = [];
    
    for ($month = 1; $month <= 12; $month++) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as jumlah 
            FROM download_log 
            WHERE YEAR(downloaded_at) = ? AND MONTH(downloaded_at) = ?
        ");
        $stmt->execute([$current_year, $month]);
        $count = $stmt->fetchColumn();
        
        $stats[] = [
            'month' => $month,
            'count' => (int)$count
        ];
    }
    
    $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $data = array_column($stats, 'count');
    
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

// =============================================
// FUNGSI TAMBAHAN UNTUK MODUL PENGATURAN
// =============================================

function update_user_module_access($user_id, $module_id, $is_active) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_modules (user_id, module_id, is_active) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE is_active = ?
        ");
        $stmt->execute([$user_id, $module_id, $is_active, $is_active]);
        return true;
    } catch(PDOException $e) {
        error_log("Module access update error: " . $e->getMessage());
        return false;
    }
}

function get_user_active_modules($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.module_name 
        FROM user_modules um 
        JOIN modules m ON um.module_id = m.id 
        WHERE um.user_id = ? AND um.is_active = TRUE AND m.is_active = TRUE
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// =============================================
// FUNGSI VALIDASI DAN UTILITAS TAMBAHAN
// =============================================

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password_strength($password) {
    // Minimal 6 karakter, mengandung huruf dan angka
    if (strlen($password) < 6) {
        return false;
    }
    
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
}

function generate_random_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

function format_date_id($date) {
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

function get_file_icon($file_type) {
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'default' => 'fas fa-file'
    ];
    
    return $icons[strtolower($file_type)] ?? $icons['default'];
}

// =============================================
// FUNGSI CLEANUP DAN MAINTENANCE
// =============================================

function cleanup_old_files($days_old = 30) {
    $base_path = UPLOAD_BASE_PATH;
    $cutoff_time = time() - ($days_old * 24 * 60 * 60);
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    $deleted_files = 0;
    $deleted_dirs = 0;
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getMTime() < $cutoff_time) {
            if (unlink($file->getPathname())) {
                $deleted_files++;
            }
        }
    }
    
    // Remove empty directories
    foreach ($iterator as $dir) {
        if ($dir->isDir() && count(scandir($dir->getPathname())) == 2) {
            if (rmdir($dir->getPathname())) {
                $deleted_dirs++;
            }
        }
    }
    
    return ['files' => $deleted_files, 'directories' => $deleted_dirs];
}

function optimize_database() {
    global $pdo;
    
    $tables = ['users', 'kategori', 'jenis_dokumen', 'dokumen', 'dokumen_sharing', 'download_log', 'audit_trail', 'modules', 'user_modules'];
    $optimized = 0;
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE $table");
            $optimized++;
        } catch(PDOException $e) {
            error_log("Failed to optimize table $table: " . $e->getMessage());
        }
    }
    
    return $optimized;
}

// =============================================
// FUNGSI NOTIFIKASI DAN ALERT
// =============================================

function add_system_notification($title, $message, $type = 'info', $user_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
            VALUES (?, ?, ?, ?, FALSE, NOW())
        ");
        $stmt->execute([$user_id, $title, $message, $type]);
        return true;
    } catch(PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
        return false;
    }
}

function get_unread_notifications($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function mark_notifications_read($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return true;
    } catch(PDOException $e) {
        error_log("Failed to mark notifications as read: " . $e->getMessage());
        return false;
    }
}

// =============================================
// INISIALISASI TABEL TAMBAHAN JIKA DIPERLUKAN
// =============================================

function initialize_additional_tables($pdo) {
    $additional_tables = [
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info','success','warning','error') DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level ENUM('info','warning','error','critical') DEFAULT 'info',
            message TEXT NOT NULL,
            context TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    ];
    
    foreach ($additional_tables as $table) {
        try {
            $pdo->exec($table);
        } catch(PDOException $e) {
            // Skip error jika tabel sudah ada
        }
    }
}

// Panggil inisialisasi tabel tambahan
initialize_additional_tables($pdo);

?>
