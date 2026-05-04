<?php
// Buat folder session khusus di aplikasi Anda
// Atur lokasi session khusus
$sessionPath = __DIR__ . '/sessions';
if (!file_exists($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 1440); // 24 menit

// Set path session baru
//ini_set('session.save_path', $customSessionPath);
// Set timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Mulai session
ob_start();
session_start();
// Pastikan file log.php di-include dengan benar
$log_path = __DIR__ . '/log.php';
if (!file_exists($log_path)) {
    die("Error: File log.php tidak ditemukan di $log_path");
}
require_once $log_path;

// Konfigurasi dasar
$school_name = "SMKN ? Kota Serang";
$school_year = "2025/2026";
//require_once __DIR__ . '/log.php';

// File paths
$students_file = 'data/siswa.csv';
$settings_file = 'data/settings.csv';
//$log_file = 'data/access_log.csv';
$log_file = __DIR__ . '/data/access_log.csv';
$upload_dir = 'upload/';
$photo_prefix = 'foto_';
$skl_prefix = 'skl_';

// Daftar kelas yang tersedia
$classes = [
    'XIITKJ-1',
    'XIITKJ-2',
    'XIITKJ-3',
    'XIIAKL-1',
    'XIIAKL-2',
    'XIIAKL-3',
    'XIITPL-1',
    'XIITSM-1'
];

// Inisialisasi variabel
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$error = '';
$success = '';

// Fungsi untuk memuat settings dari CSV
function loadSettings($file) {
    $settings = [
        'announcement_time' => date('Y-m-d H:i:s', strtotime('+0 days')),
        'admin_username' => 'admin',
        'admin_password' => 'admin123'
    ];
    
    if (file_exists($file)) {
        $handle = fopen($file, 'r');
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 2) {
                $settings[$data[0]] = $data[1];
            }
        }
        fclose($handle);
    }
    return $settings;
}

// Fungsi untuk menyimpan settings ke CSV
function saveSettings($file, $settings) {
    $handle = fopen($file, 'w');
    foreach ($settings as $key => $value) {
        fputcsv($handle, [$key, $value]);
    }
    fclose($handle);
}

// Memuat settings
$settings = loadSettings($settings_file);
$default_announcement_time = $settings['announcement_time'];

// Fungsi untuk memuat data siswa dari CSV dengan validasi
function loadStudents($file) {
    $students = [];
    if (file_exists($file)) {
        $handle = fopen($file, 'r');
        
        // Lewati header jika ada
        $header = fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            // Pastikan data memiliki 8 kolom
            if (count($data) < 8) {
                continue;
            }
            
            $students[$data[1]] = [
                'name' => $data[0] ?? '',
                'nisn' => $data[1] ?? '',
                'birth_place' => $data[2] ?? '',
                'birth_date' => $data[3] ?? '',
                'class' => $data[4] ?? '',
                'status' => $data[5] ?? 'TIDAK LULUS',
                'photo' => $data[6] ?? '',
                'skl' => $data[7] ?? ''
            ];
        }
        fclose($handle);
    }
    return $students;
}

// Fungsi untuk menyimpan data siswa ke CSV dengan header
function saveStudents($file, $students) {
    $handle = fopen($file, 'w');
    
    // Tulis header
    fputcsv($handle, ['name', 'nisn', 'birth_place', 'birth_date', 'class', 'status', 'photo', 'skl']);
    
    foreach ($students as $student) {
        fputcsv($handle, [
            $student['name'],
            $student['nisn'],
            $student['birth_place'],
            $student['birth_date'],
            $student['class'],
            $student['status'],
            $student['photo'],
            $student['skl']
        ]);
    }
    fclose($handle);
}

// Memuat data siswa
$students = loadStudents($students_file);

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?page=admin_login');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_student'])) {
        $nisn = trim($_POST['nisn']);
   	// Catat akses dengan NISN
    	    // Pastikan fungsi logAccess sudah tersedia
	if (function_exists('logAccess')) {
		logAccess(
		$nisn
		//$student_data['name'] ?? '',    
       	//$student_data['class'] ?? ''
		);
	} else {
		error_log("Error: Fungsi logAccess tidak tersedia");
	}
    
        $student_result = checkStudent($nisn, $students, $default_announcement_time);
    } 
    elseif (isset($_POST['admin_login'])) {
        $username = trim($_POST['admin_username']);
        $password = trim($_POST['admin_password']);
        
        if ($username === $settings['admin_username'] && $password === $settings['admin_password']) {
            $_SESSION['admin_logged_in'] = true;
            $page = 'admin_dashboard';
        } else {
            $error = 'Username atau password salah!';
            $page = 'admin_login';
        }
    }
    elseif (isset($_POST['upload_student']) && isset($_SESSION['admin_logged_in'])) {
        // Handle upload data siswa
        $nisn = trim($_POST['student_nisn']);
        
        // Jika siswa sudah ada, gunakan data yang ada sebagai dasar
        $existing_data = isset($students[$nisn]) ? $students[$nisn] : [
            'name' => '',
            'nisn' => $nisn,
            'birth_place' => '',
            'birth_date' => '',
            'class' => '',
            'status' => 'TIDAK LULUS',
            'photo' => '',
            'skl' => ''
        ];
        
        // Update hanya field yang diubah
        $updated_student = [
            'name' => !empty($_POST['student_name']) ? trim($_POST['student_name']) : $existing_data['name'],
            'nisn' => $nisn,
            'birth_place' => !empty($_POST['student_birth_place']) ? trim($_POST['student_birth_place']) : $existing_data['birth_place'],
            'birth_date' => !empty($_POST['student_birth_date']) ? trim($_POST['student_birth_date']) : $existing_data['birth_date'],
            'class' => !empty($_POST['student_class']) ? trim($_POST['student_class']) : $existing_data['class'],
            'status' => isset($_POST['student_status']) ? 'LULUS' : 'TIDAK LULUS',
            'photo' => $existing_data['photo'], // Default ke foto yang ada
            'skl' => $existing_data['skl'] // Default ke SKL yang ada
        ];
        
        // Proses upload foto hanya jika ada file yang diupload
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            // Hapus foto lama jika ada
            if (!empty($existing_data['photo'])) {
                @unlink($upload_dir ."foto/". $existing_data['photo']);
            }
            
            $photo_ext = pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION);
            $photo_filename = $photo_prefix . $nisn . '.' . $photo_ext;
            $photo_path = $upload_dir . "foto/" . $photo_filename;
            
            if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $photo_path)) {
                $updated_student['photo'] = $photo_filename;
            } else {
                $error = 'Gagal mengupload foto siswa';
            }
        }
        
        // Proses upload SKL hanya jika ada file yang diupload
        if (isset($_FILES['skl_file']) && $_FILES['skl_file']['error'] === UPLOAD_ERR_OK) {
            // Hapus SKL lama jika ada
            if (!empty($existing_data['skl'])) {
                @unlink($upload_dir . "skl/" . $existing_data['skl']);
            }
            
            $skl_ext = pathinfo($_FILES['skl_file']['name'], PATHINFO_EXTENSION);
            $skl_filename = $skl_prefix . $nisn . '.' . $skl_ext;
            $skl_path = $upload_dir . "skl/" . $skl_filename;
            
            if (move_uploaded_file($_FILES['skl_file']['tmp_name'], $skl_path)) {
                $updated_student['skl'] = $skl_filename;
            } else {
                $error = 'Gagal mengupload file SKL';
            }
        }
        
        if (empty($error)) {
            // Simpan data siswa yang telah diupdate
            $students[$nisn] = $updated_student;
            
            // Simpan ke CSV
            saveStudents($students_file, $students);
            $success = 'Data siswa berhasil disimpan!';
        }
    }
    elseif (isset($_POST['save_settings']) && isset($_SESSION['admin_logged_in'])) {
        // Handle pengaturan
        if (!empty($_POST['announcement_time'])) {
            $settings['announcement_time'] = $_POST['announcement_time'];
            $default_announcement_time = $settings['announcement_time'];
            $success = 'Pengaturan berhasil disimpan!';
        }
        
        // Handle perubahan password admin
        if (!empty($_POST['admin_password_change'])) {
            $settings['admin_password'] = $_POST['admin_password_change'];
            $success = 'Pengaturan berhasil disimpan!';
        }
        
        // Simpan settings ke file
        saveSettings($settings_file, $settings);
    }
    elseif (isset($_POST['delete_student']) && isset($_SESSION['admin_logged_in'])) {
        // Handle penghapusan siswa
        $nisn_to_delete = $_POST['nisn_to_delete'];
        if (isset($students[$nisn_to_delete])) {
            // Hapus file foto dan SKL
            if (!empty($students[$nisn_to_delete]['photo'])) {
                @unlink($upload_dir ."foto/". $students[$nisn_to_delete]['photo']);
            }
            if (!empty($students[$nisn_to_delete]['skl'])) {
                @unlink($upload_dir ."foto/" . $students[$nisn_to_delete]['skl']);
            }
            
            // Hapus dari array
            unset($students[$nisn_to_delete]);
            
            // Simpan ke CSV
            saveStudents($students_file, $students);
            $success = 'Data siswa berhasil dihapus!';
        }
    }
}

// Redirect ke halaman admin jika sudah login
if (isset($_SESSION['admin_logged_in']) && ($page === 'admin_login' || $page === 'home')) {
    $page = 'admin_dashboard';
}

// Fungsi untuk memeriksa siswa
function checkStudent($nisn, $students, $announcement_time) {
    try {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $announcement = new DateTime($announcement_time, new DateTimeZone('Asia/Jakarta'));
        
        // Debugging - tampilkan waktu yang dibandingkan
        error_log("Waktu Sekarang: " . $now->format('Y-m-d H:i:s'));
        error_log("Waktu Pengumuman: " . $announcement->format('Y-m-d H:i:s'));
        
        if ($now < $announcement) {
            return [
                'status' => 'warning',
                'message' => 'PENGUMUMAN BELUM DIBUKA',
                'detail' => 'Pengumuman kelulusan akan dibuka pada:<br><strong>' . formatDate($announcement) . '</strong>'
            ];
        }
        
        if (isset($students[$nisn])) {
            $student = $students[$nisn];
            return [
                'status' => $student['status'] === 'LULUS' ? 'success' : 'danger',
                'message' => $student['status'] === 'LULUS' ? 'SELAMAT! ANDA LULUS' : 'ANDA TIDAK LULUS',
                'detail' => $student,
                'show_download' => $student['status'] === 'LULUS'
            ];
        } else {
            return [
                'status' => 'danger',
                'message' => 'DATA TIDAK DITEMUKAN',
                'detail' => 'NISN yang Anda masukkan tidak terdaftar. Silakan coba lagi.'
            ];
        }
    } catch (Exception $e) {
        error_log("Error in checkStudent: " . $e->getMessage());
        return [
            'status' => 'danger',
            'message' => 'TERJADI KESALAHAN SISTEM',
            'detail' => 'Silakan hubungi administrator.'
        ];
    }
}

// Fungsi utilitas untuk format tanggal
function formatDate($date) {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format('l, d F Y H:i');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Kelulusan - <?php echo $school_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding-top: 56px;
            scroll-padding-top: 56px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 4rem 0;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border: none;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .countdown {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .hidden {
            display: none;
        }
        
        #studentResult {
            transition: all 0.5s ease;
        }
        
        footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .hero-section {
            margin-top: 56px;
            border-radius: 0 !important;
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background-color: #343a40;
                padding: 10px;
                margin-top: 8px;
                border-radius: 5px;
                max-height: calc(100vh - 56px);
                overflow-y: auto;
            }
        }

        .nav-tabs .nav-link {
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid var(--secondary-color);
        }
        
        .preview-image {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
        }
        
        .birth-date-container {
            display: flex;
            gap: 10px;
        }
        
        .birth-date-container .form-control {
            flex: 1;
        }

	/* Grafik container */
	.chart-container {
	    position: relative;
	    height: 300px;
	    width: 100%;
	}

	/* Card untuk grafik */
	.chart-card {
	    margin-bottom: 20px;
	    border-radius: 8px;
	    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
	    padding: 15px;
	    background: white;
	}

	/* Responsive chart */
	@media (max-width: 768px) {
	    .chart-container {
		height: 250px;
	    }
	}
    </style>
</head>
<body>
    <!-- Header/Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="?page=home">
                <img src="assets/images/logo.png" alt="<?php echo $school_name; ?>" height="40">
                <span class="ms-2"><?php echo $school_name; ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page === 'home' ? 'active' : ''; ?>" href="?page=home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page === 'check' ? 'active' : ''; ?>" href="?page=check">Cek Kelulusan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page === 'about' ? 'active' : ''; ?>" href="?page=about">Tentang</a>
                    </li>
                    <?php if (isset($_SESSION['admin_logged_in'])): ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-sm btn-outline-light ms-2 active" href="?page=admin_dashboard">Admin</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-sm btn-outline-light" href="?logout=1">Logout</a>
                        </li>
                    <?php else: ?>
				&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <li class="nav-item">
                            <a class="nav-link btn btn-sm btn-outline-light ms-2 <?php echo $page === 'admin_login' ? 'active' : ''; ?>" 
                               href="?page=admin_login">.</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($page === 'home' || $page === 'check'): ?>
        <!-- Hero Section -->
        <section class="hero-section" style="margin-top: <?php echo isset($_SESSION['admin_logged_in']) ? '56px' : '0'; ?>">
            <div class="container text-center">
                <h1 class="display-4 fw-bold mb-4">PENGUMUMAN KELULUSAN</h1>
                <p class="lead mb-5"><?php echo $school_name; ?> <br/>Tahun Ajaran <?php echo $school_year; ?></p>
                
                <div class="countdown-container bg-white p-3 rounded d-inline-block">
                    <p class="mb-2 text-dark">Pengumuman akan dibuka dalam:</p>
                    <div class="countdown" id="countdown">
                        <span id="days">00</span> Hari 
                        <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container my-5">
        <?php if ($page === 'home'): ?>
            <!-- Home Page Content -->
            <div class="row">
                <div class="col-md-8 mx-auto text-center">
                    <h3 class="mb-4">Selamat Datang di Portal Pengumuman Kelulusan</h3>
                    <p class="lead">Portal ini menyediakan informasi mengenai kelulusan siswa <br/><?php echo $school_name; ?> Tahun Ajaran <?php echo $school_year; ?>.</p>
                    
                    <div class="card mt-5">
                        <div class="card-body">
                            <h3 class="card-title">Cara Mengecek Kelulusan</h3>
                            <ol class="text-start">
                                <li>Klik menu "Cek Kelulusan" atau tombol di bawah ini</li>
                                <li>Masukkan NISN Anda pada form yang tersedia</li>
                                <li>Klik tombol "Cek Kelulusan"</li>
                                <li>Hasil akan ditampilkan secara otomatis</li>
                                <li>Jika lulus, Anda dapat mengunduh surat kelulusan</li>
                            </ol>
                            
                            <a href="?page=check" class="btn btn-primary btn-lg mt-3">
                                <i class="fas fa-search me-2"></i>Cek Kelulusan Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
<?php elseif ($page === 'check'): ?>
            <!-- Student Check Section -->
            <section class="mb-5">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body p-5">
                                <h5 class="card-title text-center mb-4">CEK STATUS KELULUSAN</h5>
                                
                                <?php if (!isset($student_result)): ?>
                                <form method="POST" action="?page=check">
                                    <div class="mb-3">
                                        <label for="nisn" class="form-label">Masukkan NISN Anda</label>
                                        <input type="text" class="form-control form-control-lg" id="nisn" name="nisn"
                                               placeholder="Contoh: 1234567890" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="check_student" class="btn btn-primary btn-lg">
                                            <i class="fas fa-search me-2"></i>Cek Kelulusan
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                                
                                <?php if (isset($student_result)): ?>
                                    <div id="studentResult">
                                        <div class="text-center">
                                            <?php 
                                            $detail = $student_result['detail'] ?? [];
                                            if (is_array($detail) && !empty($detail['photo'])): 
                                                $photo_path = __DIR__ . '/' . $upload_dir . "foto/" . $detail['photo'];
                                                if (file_exists($photo_path)): ?>
                                                <img src="<?php echo $upload_dir . "foto/" . $detail['photo']; ?>" 
                                                     alt="Foto Siswa" class="rounded-circle mb-3" width="120">
                                                <?php endif; 
                                            endif; ?>
                                            
                                            <?php if (is_array($detail) && !empty($detail['name'])): ?>
                                                <h3 class="fw-bold"><?php echo $detail['name']; ?></h3>
                                            <?php endif; ?>
                                            
                                            <?php 
                                                if (is_array($detail)):
                                                $birth_place = $detail['birth_place'] ?? '';
                                                $birth_date = $detail['birth_date'] ?? '';
                                                if (!empty($birth_place) || !empty($birth_date)): ?>
                                                <p class="mb-1"><?php echo $birth_place . (!empty($birth_place) && !empty($birth_date) ? ', ' : '') . $birth_date; ?></p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (is_array($detail) && !empty($detail['class'])): ?>
                                                <?php 
                                                    $class = $detail['class'];
                                                    $jurusan = '';
                                                    if (preg_match('/\bTKJ\b/', $class)) {
                                                        $jurusan = 'Teknik Jaringan Komputer dan Telekomunikasi';
                                                    } elseif (preg_match('/\bAK\s/', $class) || preg_match('/\bAK\d/', $class)) {
                                                        $jurusan = 'Akuntansi';
                                                    } elseif (preg_match('/\bTPL\b/', $class)) {
                                                        $jurusan = 'Teknik Pengelasan dan Fabrikasi Logam';
                                                    } elseif (preg_match('/\bTSM\b/', $class)) {
                                                        $jurusan = 'Teknik dan Bisnis Sepeda Motor';
                                                    } else {
                                                        $jurusan = $class;
                                                    }
                                                ?>
                                                <p class="mb-1"><?php echo $jurusan; ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($student_result['status'] === 'success'): ?>
                                                <div class="alert alert-success">
                                                    <h4><i class="fas fa-check-circle me-2"></i>Selamat Anda Lulus</h4>
                                                </div>
                                                
                                                <?php 
                                                if (is_array($detail) && !empty($detail['skl'])): 
                                                $skl_path = __DIR__ . '/' . $upload_dir . "skl/" . $detail['skl'];
                                                if (file_exists($skl_path)): ?>
                                                   <a href="<?php echo $upload_dir . "skl/" . $detail['skl']; ?>" 
                                                       class="btn btn-success mt-2" download>
                                                        <i class="fas fa-download me-2"></i>
                                                        Download Surat Keterangan Kelulusan [SKL]
                                                   </a> 
                                                <?php endif; 
                                                endif; ?>
                                            <?php else: ?>
                                                <div class="alert alert-<?php echo $student_result['status']; ?>">
                                                    <h5><?php echo $student_result['message']; ?></h5>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-4">
                                                <a href="?page=check" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-search me-1"></i>Cek NISN Lain
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
        <?php elseif ($page === 'about'): ?>
            <!-- About Section -->
            <section>
                <div class="row">
                    <div class="col-md-6">
                        <h2>Tentang <?php echo $school_name; ?></h2>
                        <p><?php echo $school_name; ?> merupakan salah satu sekolah menengah kejuruan terbaik di Kota Serang yang mencetak lulusan siap kerja dan berkompeten di bidangnya.</p>
                        
                        <h3 class="mt-4">Visi</h3>
                        <p>Menjadikan Lulusan Yang Kompeten, Religius, Berkarakter, Berbudaya, Profesional Dalam Teknologi Informasi Dan Mampu Berdaya Saing Di Tingkat Global.</p>
                        
                        <h3 class="mt-4">Misi</h3>
				<h4>Dalam rangka mewujudkan visi SMK Negeri 6 Kota Serang</h4>
                        <ul>
					<li>Melaksanakan kegiatan pembelajaran berbasis projek sesuai dengan kejuruannya;</li>
					<li>Mengembangkan budaya sekolah yang unggul dalam IMTAQ dan IPTEK;</li>
					<li>Mengembangkan kegiatan belajar-mengajar berbasis pendidikan karakter dan penguatan profil pelajar Pancasila;</li>
					<li>Menyelenggarakan berbagai kegiatan untuk membentuk lulusan yang berbudaya lingkungan;</li>
					<li>Mengembangkan ICT (Information Communication and Tecnology);</li>
					<li>Menjalin kerjasama dengan lembaga lain dalam merealisasikan program sekolah;</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h3>Kontak</h3>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-map-marker-alt me-2"></i> LINK. PRIYAYI LANGGAR NO. 69, Kel. Mesjid Priyayi, Kec. Kasemen, Kota Serang, Prov. Banten - 42191</li>
                            <li><i class="fas fa-phone me-2"></i> (0254) 2576575</li>
                            <li><i class="fas fa-envelope me-2"></i> info@smkn6kotaserang.sch.id / smkn6kotaserang@gmail.com</li>
                            <li><i class="fas fa-clock me-2"></i> Senin-Jumat: 07:00 - 16:00</li>
                        </ul>
                        
                        <h3 class="mt-4">Program Keahlian</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Teknik Jaringan Komputer dan Telekomunikasi</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Akuntansi Keuangan dan Lembaga</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Teknik dan Bisnis Sepeda Motor</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Teknik Pengelasan dan Fabrikasi Logam </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
        <?php elseif ($page === 'admin_login'): ?>
            <!-- Admin Login Section -->
            <section>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body p-5">
                                <h3 class="card-title text-center mb-4">LOGIN ADMIN</h3>
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="?page=admin_login">
                                    <div class="mb-3">
                                        <label for="admin_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="admin_login" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i>Login
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
        <?php elseif ($page === 'admin_dashboard' && isset($_SESSION['admin_logged_in'])): ?>
            <!-- Admin Dashboard Section -->
            <section>
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title mb-4">Dashboard Admin</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <ul class="nav nav-tabs" id="adminTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#uploadTab">Upload/Edit Data</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#settingsTab">Pengaturan</a>
                            </li>

				<!-- Di bagian nav tabs admin -->
			     <li class="nav-item">
			    	<a class="nav-link" data-bs-toggle="tab" href="#statsTab">Statistik Akses</a>
			     </li>
			</ul>
                        
                        <div class="tab-content mt-3">
                            <!-- Upload/Edit Tab -->
                            <div class="tab-pane fade show active" id="uploadTab">
                                <form id="uploadForm" enctype="multipart/form-data" method="POST">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="studentSearch" class="form-label">Cari Siswa (NISN/Nama)</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="studentSearch" placeholder="Masukkan NISN atau Nama">
                                                <button class="btn btn-outline-secondary" type="button" id="searchButton">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="studentName" class="form-label">Nama Siswa</label>
                                            <input type="text" class="form-control" id="studentName" name="student_name">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="studentNISN" class="form-label">NISN</label>
                                            <input type="text" class="form-control" id="studentNISN" name="student_nisn" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="studentBirthPlace" class="form-label">Tempat Lahir</label>
                                            <input type="text" class="form-control" id="studentBirthPlace" name="student_birth_place">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="studentBirthDate" class="form-label">Tanggal Lahir</label>
                                            <input type="date" class="form-control" id="studentBirthDate" name="student_birth_date">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="studentClass" class="form-label">Kelas</label>
                                        <select class="form-select" id="studentClass" name="student_class">
                                            <option value="">Pilih Kelas</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="studentPhotoFile" class="form-label">Foto Siswa</label>
                                        <input type="file" class="form-control" id="studentPhotoFile" name="student_photo" accept="image/*">
                                        <small class="text-muted">Format: <?php echo $photo_prefix; ?>[nisn].[ext]</small>
                                        <div id="photoPreview" class="preview-image"></div>
                                        <input type="hidden" id="currentPhoto" name="current_photo">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sklFile" class="form-label">File Surat Kelulusan (PDF)</label>
                                        <input type="file" class="form-control" id="sklFile" name="skl_file" accept=".pdf">
                                        <small class="text-muted">Format: <?php echo $skl_prefix; ?>[nisn].pdf</small>
                                        <input type="hidden" id="currentSkl" name="current_skl">
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="studentStatus" name="student_status" checked>
                                        <label class="form-check-label" for="studentStatus">Status Lulus</label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="upload_student" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Simpan Data
                                        </button>
                                        <button type="button" id="clearForm" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-2"></i>Bersihkan Form
                                        </button>
                                    </div>
                                </form>
                                
                                <hr class="my-4">
                                
                                <h5>Daftar Siswa</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>NISN</th>
                                                <th>Status</th>
                                                <th>Foto</th>
                                                <th>SKL</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $nisn => $student): ?>
                                                <tr>
                                                    <td><?php echo $student['nisn']; ?></td>
                                                    <td><?php echo $student['name']; ?></td>
                                                    <td><?php echo $student['class']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $student['status'] === 'LULUS' ? 'success' : 'danger'; ?>">
                                                            <?php echo $student['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($student['photo'])): ?>
                                                            <img src="<?php echo $upload_dir ."foto/". $student['photo']; ?>" alt="Foto" width="50">
                                                        <?php else: ?>
                                                            <span class="text-muted">Tidak ada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($student['skl'])): ?>
                                                            <a href="<?php echo $upload_dir . "skl/" . $student['skl']; ?>" target="_blank">
                                                                <i class="fas fa-file-pdf"></i> Lihat
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Tidak ada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary edit-btn" 
                                                                data-nisn="<?php echo $student['nisn']; ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="nisn_to_delete" value="<?php echo $student['nisn']; ?>">
                                                            <button type="submit" name="delete_student" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                                <i class="fas fa-trash"></i> Hapus
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Settings Tab -->
                            <div class="tab-pane fade" id="settingsTab">
                                <form id="settingsForm" method="POST">
                                    <div class="mb-3">
                                        <label for="announcementTime" class="form-label">Waktu Pengumuman Kelulusan</label>
                                        <input type="datetime-local" class="form-control" id="announcementTime" 
                                               name="announcement_time" value="<?php echo date('Y-m-d\TH:i', strtotime($default_announcement_time)); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="adminUsernameChange" class="form-label">Username Admin</label>
                                        <input type="text" class="form-control" id="adminUsernameChange" name="admin_username_change" 
                                               value="<?php echo htmlspecialchars($settings['admin_username']); ?>" readonly>
                                        <small class="text-muted">Username tidak dapat diubah</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="adminPasswordChange" class="form-label">Password Baru</label>
                                        <input type="password" class="form-control" id="adminPasswordChange" name="admin_password_change">
                                        <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                                    </div>
                                    
                                    <button type="submit" name="save_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Simpan Pengaturan
                                    </button>
                                </form>
                            </div>

<!-- Di bagian tab content -->
<div class="tab-pane fade" id="statsTab">
    <h4 class="mb-4">Statistik Pengakses</h4>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Ringkasan</h5>
                    <div id="summaryStats"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Pencarian Populer</h5>
                    <div id="popularSearches"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Distribusi Waktu Akses</h5>
                    <canvas id="timeChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Perangkat Pengakses</h5>
                    <canvas id="deviceChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Aktivitas Harian</h5>
                    <canvas id="dailyChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

<!-- ************************************* -->    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Pengunjung Terakhir</h5>
            <div class="table-responsive">
                <table class="table table-striped" id="visitorsTable">
                    <thead>
                        <tr>
			    <th>Waktu</th>
			    <th>IP Address</th>
			    <th>Lokasi</th>
			    <th>Perangkat</th>
                        </tr>
                    </thead>
                    <tbody id="visitorsBody">
                        <!-- Data akan diisi oleh JavaScript -->
 <script> 
// Di bagian script
document.querySelector('a[href="#statsTab"]').addEventListener('click', function() {
    console.log("Tab stats diklik"); // Debug
    
    fetch('get_stats.php')
        .then(response => {
            console.log("Response status:", response.status); // Debug
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log("Data diterima:", data); // Debug
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown server error');
            }

            // Isi tabel Pengunjung Terakhir
            const visitorsBody = document.getElementById('visitorsBody');
            visitorsBody.innerHTML = data.visitors.length > 0 
                ? data.visitors.map(v => `
                    <tr>
			    <td>${v.date} ${v.time || ''}</td>
			    <td>${v.ip || ''}</td>
			    <td>${v.location || 'Unknown'}</td>
			    <td>${v.device || 'Unknown'}</td>
                    </tr>
                `).join('')
                : `<tr><td colspan="5">Tidak ada data pengunjung</td></tr>`;

            // Isi tabel Log Akses Terperinci
            const accessLogBody = document.querySelector('#accessLogTable tbody');
            if (data.raw_logs && data.raw_logs.length > 0) {
                accessLogBody.innerHTML = data.raw_logs.map(log => `
                    <tr>
                        <td>${log[0]}</td>
                        <td>${log[1]}</td>
                        <td>${log[2]}</td>
                        <td>${log[3]}</td>
                        <td>${log[4]}</td>

                   </tr>
                `).join('');
            } else {
                accessLogBody.innerHTML = `<tr><td colspan="5">Tidak ada data log</td></tr>`;
            }
        })
        .catch(error => {
            console.error("Error:", error);
            document.getElementById('visitorsBody').innerHTML = `
                <tr>
                    <td colspan="5" class="text-danger">Error: ${error.message}</td>
                </tr>
            `;
            document.querySelector('#accessLogTable tbody').innerHTML = `
                <tr>
                    <td colspan="5" class="text-danger">Error: ${error.message}</td>
                </tr>
            `;
        });
});

function renderTimeChart(hourlyData) {
    const ctx = document.getElementById('timeChart').getContext('2d');
    const labels = Array.from({length: 24}, (_, i) => `${i}:00`);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Akses per Jam',
                data: hourlyData,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
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
}

function renderDeviceChart(deviceData) {
    const ctx = document.getElementById('deviceChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Desktop', 'Mobile', 'Tablet', 'Lainnya'],
            datasets: [{
                data: [
                    deviceData.desktop,
                    deviceData.mobile,
                    deviceData.tablet,
                    deviceData.other
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)'
                ]
            }]
        }
    });
}
 </script>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
<!-- ************************************* -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Log Akses Terperinci</h5>
            <div class="table-responsive">
                <table class="table table-striped" id="accessLogTable">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>NISN</th>
                            <th>IP Address</th>
                            <th>Perangkat</th>
                            <th>Browser</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (file_exists($log_file)) {
                            $handle = fopen($log_file, 'r');
                            // Lewati header
                            fgetcsv($handle);
                            
                            $count = 0;
                            $max_rows = 10000; // Batasi jumlah baris yang ditampilkan
                            $rows = [];
                            
                            // Baca semua baris
                            while (($data = fgetcsv($handle)) !== false && $count < $max_rows) {
                                //$rows[] = $data;
                                //$count++;
					  //Perbaikkanya adalah ...
					  $row = array_pad($data, 5, '');
					  $rows[] = $row;
					  $count++;
                            }
                            
                            // Tampilkan dari yang terbaru
                            $rows = array_reverse($rows);
                            
                            foreach ($rows as $row) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row[0]) . "</td>";
                                echo "<td>" . htmlspecialchars($row[1]) . "</td>";
                                echo "<td>" . htmlspecialchars($row[2]) . "</td>";
                                echo "<td>" . htmlspecialchars($row[3]) . "</td>";
                                echo "<td>" . htmlspecialchars($row[4]) . "</td>";
                                echo "</tr>";
                            }
                            
                            fclose($handle);
                        } else {
                            echo "<tr><td colspan='5'>Belum ada data log</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <a href="export_log.php" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Export Data Lengkap
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ************************* -->
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> ICT Center - <?php echo $school_name; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

// Di bagian script
document.addEventListener('DOMContentLoaded', function() {
    // Load stats when stats tab is clicked
    document.querySelector('a[href="#statsTab"]').addEventListener('click', function() {
        fetch('get_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update summary stats
                    document.getElementById('summaryStats').innerHTML = `
                        <p>Total Akses: <strong>${data.total_access}</strong></p>
                        <p>Pencarian NISN: <strong>${data.total_searches}</strong></p>
                        <p>Perangkat Mobile: <strong>${data.mobile_devices}</strong></p>
                        <p>Browser Chrome: <strong>${data.chrome_users}</strong></p>
                    `;
                    
                    // Update popular searches
                    let popularHTML = '<ol>';
                    data.popular_searches.forEach(item => {
                        popularHTML += `<li>${item.nisn} (${item.count}x)</li>`;
                    });
                    popularHTML += '</ol>';
                    document.getElementById('popularSearches').innerHTML = popularHTML;
                    
                    // Render charts
                    renderTimeChart(data.hourly_data);
                    renderDeviceChart(data.device_data);
                    renderDailyChart(data.daily_data);
                }
            });
    });
    
    function renderTimeChart(hourlyData) {
        const ctx = document.getElementById('timeChart').getContext('2d');
        const labels = [];
        const dataPoints = [];
        
        // Format data untuk chart
        for (let i = 0; i < 24; i++) {
            labels.push(`${i}:00`);
            dataPoints.push(hourlyData[i] || 0);
        }
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Akses per Jam',
                    data: dataPoints,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah Akses'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Jam dalam Sehari'
                        }
                    }
                }
            }
        });
    }
    
    function renderDeviceChart(deviceData) {
        const ctx = document.getElementById('deviceChart').getContext('2d');
        const labels = ['Desktop', 'Mobile', 'Tablet', 'Lainnya'];
        const backgroundColors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)'
        ];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: [
                        deviceData.desktop || 0,
                        deviceData.mobile || 0,
                        deviceData.tablet || 0,
                        deviceData.other || 0
                    ],
                    backgroundColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
    
    function renderDailyChart(dailyData) {
        const ctx = document.getElementById('dailyChart').getContext('2d');
        const labels = [];
        const dataPoints = [];
        
        // Format data untuk 7 hari terakhir
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const today = new Date();
        
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(today.getDate() - i);
            const dayName = days[date.getDay()];
            const dateStr = `${dayName}, ${date.getDate()}/${date.getMonth()+1}`;
            
            labels.push(dateStr);
            
            // Cari data untuk tanggal ini
            const formattedDate = date.toISOString().split('T')[0];
            dataPoints.push(dailyData[formattedDate] || 0);
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Aktivitas Harian',
                    data: dataPoints,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah Akses'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Tanggal'
                        }
                    }
                }
            }
        });
    }
});




        // JavaScript untuk countdown
        // Handle mobile menu scrolling
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            const navbarHeight = navbar.offsetHeight;
            
            // Update body padding based on navbar height
            document.body.style.paddingTop = navbarHeight + 'px';
            
            // Adjust when menu is toggled on mobile
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            navbarToggler.addEventListener('click', function() {
                if (navbarCollapse.classList.contains('show')) {
                    document.body.style.paddingTop = navbarHeight + 'px';
                } else {
                    const expandedHeight = navbarHeight + navbarCollapse.scrollHeight;
                    document.body.style.paddingTop = expandedHeight + 'px';
                }
            });
            
            // Recalculate on window resize
            window.addEventListener('resize', function() {
                document.body.style.paddingTop = document.querySelector('.navbar').offsetHeight + 'px';
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Set waktu pengumuman (dalam implementasi nyata, ambil dari database)
            const announcementTime = new Date("<?php echo $default_announcement_time; ?>");
            
            // Fungsi untuk menghitung mundur
            function updateCountdown() {
                const now = new Date();
                const diff = announcementTime - now;
                
                if (diff <= 0) {
                    document.getElementById('countdown').innerHTML = 'PENGUMUMAN TELAH DIBUKA!';
                    return;
                }
                
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                document.getElementById('days').textContent = days.toString().padStart(2, '0');
                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            }
            
            // Jalankan countdown setiap detik
            setInterval(updateCountdown, 1000);
            updateCountdown(); // Panggil sekali saat pertama kali load
            
            // Inisialisasi tab Bootstrap
            const tabElms = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabElms.forEach(tabEl => {
                tabEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    const tab = new bootstrap.Tab(this);
                    tab.show();
                });
            });
            
            // Preview gambar sebelum upload
            document.getElementById('studentPhotoFile').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('photoPreview').innerHTML = 
                            `<img src="${e.target.result}" class="img-thumbnail" alt="Preview">`;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Fungsi untuk mengisi form dengan data siswa yang akan diedit
            function fillEditForm(studentData) {
                document.getElementById('studentName').value = studentData.name || '';
                document.getElementById('studentNISN').value = studentData.nisn || '';
                document.getElementById('studentBirthPlace').value = studentData.birth_place || '';
                document.getElementById('studentBirthDate').value = studentData.birth_date || '';
                document.getElementById('studentClass').value = studentData.class || '';
                document.getElementById('studentStatus').checked = studentData.status === 'LULUS';
                document.getElementById('currentPhoto').value = studentData.photo || '';
                document.getElementById('currentSkl').value = studentData.skl || '';
                
                // Tampilkan preview foto jika ada
                if (studentData.photo) {
                    document.getElementById('photoPreview').innerHTML = 
                        `<img src="<?php echo $upload_dir."foto/"; ?>${studentData.photo}" class="img-thumbnail" alt="Preview">`;
                } else {
                    document.getElementById('photoPreview').innerHTML = '';
                }
                
                // Scroll ke form
                document.getElementById('uploadForm').scrollIntoView({ behavior: 'smooth' });
            }
            
            // Handle tombol edit
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const nisn = this.getAttribute('data-nisn');
                    
                    // Cari data siswa
                    fetch(`?page=admin_dashboard&get_student=${nisn}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                fillEditForm(data.student);
                            } else {
                                alert('Gagal memuat data siswa');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Terjadi kesalahan saat memuat data');
                        });
                });
            });
            
            // Handle pencarian siswa
            document.getElementById('searchButton').addEventListener('click', function() {
                const searchTerm = document.getElementById('studentSearch').value.trim();
                
                if (searchTerm) {
                    fetch(`?page=admin_dashboard&search_student=${encodeURIComponent(searchTerm)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.student) {
                                fillEditForm(data.student);
                            } else {
                                alert('Siswa tidak ditemukan');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Terjadi kesalahan saat mencari');
                        });
                }
            });
            
            // Handle tombol bersihkan form
            document.getElementById('clearForm').addEventListener('click', function() {
                document.getElementById('uploadForm').reset();
                document.getElementById('photoPreview').innerHTML = '';
                document.getElementById('currentPhoto').value = '';
                document.getElementById('currentSkl').value = '';
            });
        });
    </script>
</body>
</html>
