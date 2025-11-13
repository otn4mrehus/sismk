<?php
// Set zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');
// Pastikan tidak ada output sebelum session_start()
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Setelah berhasil mengirim izin
if (isset($success_izin)) {
    unset($_POST);
}
// ============ HEADER KEAMANAN ============ //
// Lindungi dari XSS (browser akan memblokir serangan XSS jika dideteksi)
//header("X-XSS-Protection: 1; mode=block");

// Content Security Policy (Sesuaikan dengan kebutuhan aplikasi)
//header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://maps.googleapis.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' 'unsafe-eval';");
// ============ END HEADER KEAMANAN ============ //

// Inisialisasi variabel $page dan $action dengan nilai default
$page = isset($_GET['page']) ? $_GET['page'] : 'login';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Koneksi ke MySQL server (tanpa memilih database terlebih dahulu)
$host = "mysql";
$user = "user";
$pass = "resu";
$db = "XITKJ2";

// Buat koneksi pertama ke MySQL server
$conn = new mysqli($host, $user, $pass);

// Cek koneksi ke server MySQL
if ($conn->connect_error) {
    die("Koneksi ke server MySQL gagal: " . $conn->connect_error);
}

// Cek apakah database sudah ada
$result = $conn->query("SHOW DATABASES LIKE '$db'");
if ($result->num_rows == 0) {
    // Buat database jika belum ada
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS $db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        die("Gagal membuat database: " . $conn->error);
    }
    
    // Pilih database yang baru dibuat
    $conn->select_db($db);
    
    // Buat semua tabel
    $tables = [
        "absensi_izin" => "CREATE TABLE IF NOT EXISTS absensi_izin (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nis VARCHAR(20) NOT NULL,
            tanggal DATE NOT NULL,
            jenis ENUM('sakit','ijin') NOT NULL,
            keterangan TEXT DEFAULT NULL,
            lampiran VARCHAR(255) DEFAULT NULL,
            status ENUM('pending','diterima','ditolak') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "kelas" => "CREATE TABLE IF NOT EXISTS kelas (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_kelas VARCHAR(10) NOT NULL,
            wali_kelas VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "pengaturan" => "CREATE TABLE IF NOT EXISTS pengaturan (
            id INT(11) NOT NULL AUTO_INCREMENT,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            radius INT(11) NOT NULL COMMENT 'dalam meter',
            waktu_masuk TIME NOT NULL DEFAULT '07:30:00', 
            waktu_pulang TIME NOT NULL DEFAULT '15:30:00', 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "siswa" => "CREATE TABLE IF NOT EXISTS siswa (
            nis VARCHAR(20) NOT NULL,
            nama VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
    		password_hint VARCHAR(255) DEFAULT NULL, 
            kelas_id INT(11) DEFAULT NULL,
            kelas VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (nis),
            KEY kelas_id (kelas_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "presensi" => "CREATE TABLE IF NOT EXISTS presensi (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nis VARCHAR(20) NOT NULL,
            tanggal DATE NOT NULL,
            jam_masuk TIME DEFAULT NULL,
            jam_pulang TIME DEFAULT NULL,
            foto_masuk VARCHAR(255) DEFAULT NULL,
            foto_pulang VARCHAR(255) DEFAULT NULL,
            status_masuk ENUM('tepat waktu','terlambat','lebaih awal') DEFAULT 'tepat waktu',
            keterangan_terlambat VARCHAR(50) DEFAULT NULL,
            lokasi_masuk VARCHAR(50) DEFAULT NULL,
            status_pulang ENUM('tepat waktu','cepat','belum presensi') DEFAULT 'tepat waktu',
            keterangan_pulang_cepat VARCHAR(50) DEFAULT NULL,
            lokasi_pulang VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY nis (nis),
            CONSTRAINT presensi_ibfk_1 FOREIGN KEY (nis) REFERENCES siswa(nis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "periode_libur" => "CREATE TABLE IF NOT EXISTS periode_libur (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_periode VARCHAR(100) NOT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            keterangan TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "terlambat" => "CREATE TABLE IF NOT EXISTS terlambat (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nis VARCHAR(20) NOT NULL,
            tanggal DATE NOT NULL,
            keterangan TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $tableName => $sql) {
        if (!$conn->query($sql)) {
            die("Gagal membuat tabel $tableName: " . $conn->error);
        }
    }

    // Insert data default  [Pass: 123]
    $password_hash_1 = '$2y$10$TaIHxsQzHlzRuJdQU9k6Mu44ZhFQjpWOj6SIm12vygyaQfhF8jenu';  
    //$password_hash_2 = '$2y$10$TaIHxsQzHlzRuJdQU9k6Mu44ZhFQjpWOj6SIm12vygyaQfhF8jenu';

    // password '123'
    $defaultData = [
        "siswa" => "INSERT INTO siswa (nis, nama, password, kelas_id, kelas) VALUES 
         ('0093819515', 'ADLI M HANAFIAH', '$password_hash_1', NULL, NULL), 
         ('0091558896', 'AGUNG GUNAWAN DRAJAT', '$password_hash_1', NULL, NULL), 
         ('0095000071', 'AKBAR SYARIF MUBAROK', '$password_hash_1', NULL, NULL), 
         ('0062978096', 'ARIANA SUHARDIMAN', '$password_hash_1', NULL, NULL), 
         ('0089507568', 'BAYU IKHFADINILLAH', '$password_hash_1', NULL, NULL), 
         ('0085813973', 'DESI NURKARIN', '$password_hash_1', NULL, NULL), 
         ('0084052847', 'ERLANGGA WIJAYA SAPUTRA', '$password_hash_1', NULL, NULL), 
         ('0096450349', 'FAJAR HIDAYATULLOH', '$password_hash_1', NULL, NULL), 
         ('0097499769', 'FATIMAH', '$password_hash_1', NULL, NULL), 
         ('0095892343', 'FERDIAN REZA PRATAMA', '$password_hash_1', NULL, NULL), 
         ('0073464943', 'FITRIYANI', '$password_hash_1', NULL, NULL), 
         ('0089763058', 'GUSTOFA NAZEL', '$password_hash_1', NULL, NULL), 
         ('0085365719', 'HERLINA', '$password_hash_1', NULL, NULL), 
         ('0091833896', 'HUSNI', '$password_hash_1', NULL, NULL), 
         ('0062068091', 'IKBAL FAULA', '$password_hash_1', NULL, NULL), 
         ('0099532630', 'INTAN NURAINI', '$password_hash_1', NULL, NULL), 
         ('0091816202', 'ISMALIAH', '$password_hash_1', NULL, NULL), 
         ('0086728034', 'LELY AMINAH', '$password_hash_1', NULL, NULL), 
         ('0097342590', 'MAFLUHA', '$password_hash_1', NULL, NULL), 
         ('0081299270', 'MEILANI PUSPITA SARI', '$password_hash_1', NULL, NULL), 
         ('0095399190', 'MUHAMAD FATIH', '$password_hash_1', NULL, NULL), 
         ('0071879521', 'MUHAMMAD FATURROHMAN', '$password_hash_1', NULL, NULL), 
         ('0092206514', 'NITA TANIA', '$password_hash_1', NULL, NULL), 
         ('0095320513', 'RATU BILQIS', '$password_hash_1', NULL, NULL), 
         ('0087923569', 'RIHATUL AMBARIYAH', '$password_hash_1', NULL, NULL), 
         ('0099500677', 'RISKI MUBAROK', '$password_hash_1', NULL, NULL), 
         ('0092396187', 'ROSITA', '$password_hash_1', NULL, NULL), 
         ('0091673306', 'SARNUJI', '$password_hash_1', NULL, NULL), 
         ('0091794503', 'SITI AMANDA', '$password_hash_1', NULL, NULL), 
         ('0089712518', 'SITI AMINAH', '$password_hash_1', NULL, NULL), 
         ('0096212539', 'SITI EKASAVITRI', '$password_hash_1', NULL, NULL), 
         ('3072705691', 'SITI MUSLIHAH', '$password_hash_1', NULL, NULL), 
         ('0089846738', 'SUKESIH', '$password_hash_1', NULL, NULL), 
         ('0063984255', 'TUBAGUS SOLIHIN', '$password_hash_1', NULL, NULL), 
         ('0086097794', 'WILDA', '$password_hash_1', NULL, NULL)",
        
        "pengaturan" => "INSERT INTO pengaturan (latitude, longitude, radius) VALUES 
            ('-6.41050000', '106.84400000', 100)"
    ];

    foreach ($defaultData as $table => $sql) {
        if (!$conn->query($sql)) {
            die("Gagal insert data default ke tabel $table: " . $conn->error);
        }
    }

    echo "Database dan tabel berhasil dibuat serta diisi dengan data awal.";
} else {
    // Jika database sudah ada, hanya pilih database
    $conn->select_db($db);
}
// ============ HANDLER HAPUS MASSAL ============ //
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Hapus massal presensi
	/*
    if ($_POST['action'] == 'delete_selected_presensi' && !empty($_POST['selected_ids'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_ids']));
        $sql = "DELETE FROM presensi WHERE id IN ($ids)";
        if ($conn->query($sql)) {
            $_SESSION['success_admin'] = count($_POST['selected_ids']) . " data presensi berhasil dihapus!";
        } else {
            $_SESSION['error_admin'] = "Error: " . $conn->error;
        }
	  // Tambahkan session write sebelum redirect
	  session_write_close(); // Pastikan session disimpan
        //header("Location: index.php?page=admin#presensi");
	  echo "<script>window.location.href = 'index.php?page=admin#presensi';</script>";
        exit();
    }
	*/

	if ($_POST['action'] == 'delete_selected_presensi' && !empty($_POST['selected_ids'])) {
	    $ids = implode(',', array_map('intval', $_POST['selecteApakah Anda yakin ingin menghapus 1 data terpilih?d_ids']));
	    $sql = "DELETE FROM presensi WHERE id IN ($ids)";
	    if ($conn->query($sql)) {
		  $_SESSION['success_admin'] = count($_POST['selected_ids']) . " data presensi berhasil dihapus!";
	    } else {
		  $_SESSION['error_admin'] = "Error: " . $conn->error;
		  error_log("Gagal menghapus presensi: " . $conn->error);
	    }
	    header("Location: index.php?page=admin#presensi");
	    exit();
	} else if ($_POST['action'] == 'delete_selected_presensi') {
	    $_SESSION['error_admin'] = "Tidak ada data yang dipilih untuk dihapus.";
	    header("Location: index.php?page=admin#presensi");
	    exit();
	}

    // Hapus massal izin
    if ($_POST['action'] == 'delete_selected_izin' && !empty($_POST['selected_ids'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_ids']));
        $sql = "DELETE FROM absensi_izin WHERE id IN ($ids)";
        if ($conn->query($sql)) {
            $_SESSION['success_admin'] = count($_POST['selected_ids']) . " data izin berhasil dihapus!";
        } else {
            $_SESSION['error_admin'] = "Error: " . $conn->error;
        }
	  // Tambahkan session write sebelum redirect
	  session_write_close(); // Pastikan session disimpan
        header("Location: index.php?page=admin#pengajuan");
        exit();
    }

    // Hapus massal terlambat
    if ($_POST['action'] == 'delete_selected_terlambat' && !empty($_POST['selected_ids'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_ids']));
        $sql = "DELETE FROM terlambat WHERE id IN ($ids)";
        if ($conn->query($sql)) {
            $_SESSION['success_admin'] = count($_POST['selected_ids']) . " data keterlambatan berhasil dihapus!";
        } else {
            $_SESSION['error_admin'] = "Error: " . $conn->error;
        }
	  // Tambahkan session write sebelum redirect
	  session_write_close(); // Pastikan session disimpan
        header("Location: index.php?page=admin#terlambat");
        exit();
    }

    // Hapus massal periode libur
    if ($_POST['action'] == 'delete_selected_libur' && !empty($_POST['selected_ids'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_ids']));
        $sql = "DELETE FROM periode_libur WHERE id IN ($ids)";
        if ($conn->query($sql)) {
            $_SESSION['success_admin'] = count($_POST['selected_ids']) . " periode libur berhasil dihapus!";
        } else {
            $_SESSION['error_admin'] = "Error: " . $conn->error;
        }
	  // Tambahkan session write sebelum redirect
	  session_write_close(); // Pastikan session disimpan
        header("Location: index.php?page=admin#libur");
        exit();
    }
}
// ============ END HANDLER HAPUS MASSAL ============ //

// Cek apakah ada data pengaturan
$sql_pengaturan = "SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1";
$result_pengaturan = $conn->query($sql_pengaturan);

if ($result_pengaturan->num_rows > 0) {
    $pengaturan = $result_pengaturan->fetch_assoc();
    $latSekolah = $pengaturan['latitude'];
    $lngSekolah = $pengaturan['longitude'];
    $radiusSekolah = $pengaturan['radius'];
    $jamMasuk = $pengaturan['waktu_masuk'];    // Fixed column name
    $jamPulang = $pengaturan['waktu_pulang'];  // Fixed column name
} else {
    // Default jika tidak ada pengaturan
    $latSekolah = -6.4105;
    $lngSekolah = 106.8440;
    $radiusSekolah = 100;
    $jamMasuk = '07:30:00';
    $jamPulang = '15:30:00';   
    // Insert data default
    $conn->query("INSERT INTO pengaturan (latitude, longitude, radius, waktu_masuk, waktu_pulang) VALUES ($latSekolah, $lngSekolah, $radiusSekolah, '$jamMasuk', '$jamPulang')");
}

// Fungsi untuk Format Waktu (Hari dan Tanggal Indonesia)
function formatTanggalID($tgl) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    
    $date = date_create($tgl);
    $hariIndo = $hari[date_format($date, 'w')];
    $tglIndo = date_format($date, 'j');
    $blnIndo = $bulan[(int)date_format($date, 'n')];
    $thnIndo = date_format($date, 'Y');
    
    return "$hariIndo, $tglIndo $blnIndo $thnIndo";
}

// Fungsi untuk menghitung selisih waktu dalam format yang benar
function hitungSelisihWaktu($waktuAwal, $waktuAkhir) {
    $awal = DateTime::createFromFormat('H:i:s', $waktuAwal);
    $akhir = DateTime::createFromFormat('H:i:s', $waktuAkhir);
    
    if (!$awal || !$akhir) return null;
    
    $selisih = $akhir->diff($awal);
    
    $menitTotal = ($selisih->h * 60) + $selisih->i;
    
    if ($menitTotal >= 60) {
        $jam = floor($menitTotal / 60);
        $menit = $menitTotal % 60;
        return "{$jam} Jam {$menit} Menit";
    }
    
    return "{$menitTotal} Menit";
}

// Fungsi untuk kompres gambar
function compressImage($source, $destination, $quality) {
    if (!file_exists($source)) {
        return false;
    }
    
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, round(9 * $quality / 100));
    } else {
        return false;
    }
    
    return true;
}

// Fungsi untuk menghitung jarak
function hitungJarak($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return ($miles * 1609.344); // Meter
}

// Proses login terpadu untuk admin dan siswa
if (isset($_POST['login'])) {
    $identifier = $_POST['identifier'];
    $password = $_POST['password'];
    
    // Coba login sebagai siswa
    $sql = "SELECT * FROM siswa WHERE nis = '$identifier'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['nis'] = $identifier;
            $_SESSION['nama'] = $row['nama'];
            header('Location: index.php?page=menu');
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        // Jika bukan siswa, coba sebagai admin
        if ($identifier == 'admin' && $password == 'admin123') {
            $_SESSION['admin'] = true;
            header('Location: index.php?page=admin');
            exit();
        } else {
            $error = "NISN/Username atau password salah!";
        }
    }
}

// Proses untuk mengambil data presensi (AJAX endpoint)
if (isset($_GET['action']) && $_GET['action'] == 'get_presensi' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM presensi WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Data tidak ditemukan']);
        exit();
    }
}

// Proses Update Presensi
if (isset($_POST['update_presensi'])) {
    $id = $_POST['id'];
    $tanggal = $_POST['tanggal'];
    $jam_masuk = $_POST['jam_masuk'];
    $jam_pulang = $_POST['jam_pulang'];
    
    // Validasi format waktu
    if ($jam_masuk && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_masuk)) {
        $_SESSION['error_admin'] = "Format jam masuk tidak valid";
        header("Location: index.php?page=admin#presensi");
        exit();
    }
    if ($jam_pulang && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_pulang)) {
        $_SESSION['error_admin'] = "Format jam pulang tidak valid";
        header("Location: index.php?page=admin#presensi");
        exit();
    }
    
    // Hitung status masuk berdasarkan jam baru
    $status_masuk = 'tepat waktu';
    $keterangan_terlambat = null;
    
    if ($jam_masuk) {
        // Bandingkan dengan jam masuk setting
        $selisih = hitungSelisihWaktu($jamMasuk, $jam_masuk);
        
        if ($selisih !== null && $jam_masuk > $jamMasuk) {
            $status_masuk = 'terlambat';
            $keterangan_terlambat = $selisih;
        }
    }
    
    // Hitung status pulang berdasarkan jam baru
    $status_pulang = 'tepat waktu';
    $keterangan_pulang_cepat = null;
    
    if ($jam_pulang) {
        $selisih = hitungSelisihWaktu($jam_pulang, $jamPulang);
        
        if ($selisih !== null && $jam_pulang < $jamPulang) {
            $status_pulang = 'cepat';
            $keterangan_pulang_cepat = $selisih;
        }
    }
    
    $sql = "UPDATE presensi SET 
            tanggal = '$tanggal',
            jam_masuk = " . ($jam_masuk ? "'$jam_masuk'" : "NULL") . ",
            jam_pulang = " . ($jam_pulang ? "'$jam_pulang'" : "NULL") . ",
            status_masuk = '$status_masuk',
            keterangan_terlambat = " . ($keterangan_terlambat ? "'$keterangan_terlambat'" : "NULL") . ",
            status_pulang = '$status_pulang',
            keterangan_pulang_cepat = " . ($keterangan_pulang_cepat ? "'$keterangan_pulang_cepat'" : "NULL") . "
            WHERE id = $id";
    
    if ($conn->query($sql)) {
        $_SESSION['success_admin'] = "Data presensi berhasil diperbarui!";
    } else {
        $_SESSION['error_admin'] = "Error: " . $conn->error;
    }
    header("Location: index.php?page=admin#presensi");
    exit();
}

// Proses Hapus Presensi
if (isset($_GET['action']) && $_GET['action'] == 'delete_presensi' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM presensi WHERE id = $id";
    
    if ($conn->query($sql)) {
        $_SESSION['success_admin'] = "Data presensi berhasil dihapus!";
    } else {
        $_SESSION['error_admin'] = "Error: " . $conn->error;
    }
    header("Location: index.php?page=admin#presensi");
    exit();
}
// End Proses untuk mengambil data presensi (AJAX endpoint)

// Fungsi Reset Password dengan Hint
if (isset($_POST['reset_password'])) {
    $nis = $_POST['nis'];
    $hint_answer = $_POST['hint_answer'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi password
    if ($new_password !== $confirm_password) {
        $reset_error = "Password baru dan konfirmasi password tidak cocok!";
    } else {
        // Cek apakah NIS ada di database
        $sql = "SELECT * FROM siswa WHERE nis = '$nis'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Verifikasi hint answer
            if (empty($row['password_hint']) || $hint_answer !== $row['password_hint']) {
                $reset_error = "Jawaban hint salah!";
            } else {
                // Hash password baru
                $password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $update_sql = "UPDATE siswa SET password = '$password_hashed' WHERE nis = '$nis'";
                
                if ($conn->query($update_sql)) {
                    $reset_success = "Password berhasil direset! Silakan login dengan password baru Anda.";
                } else {
                    $reset_error = "Error: " . $conn->error;
                }
            }
        } else {
            $reset_error = "NISN tidak ditemukan!";
        }
    }
}

// Proses CRUD Siswa
$nis_edit = isset($_GET['nis']) ? $_GET['nis'] : '';

if ($action == 'edit_siswa' && $nis_edit != '') {
    $sql = "SELECT * FROM siswa WHERE nis = '$nis_edit'";
    $result = $conn->query($sql);
    $siswa_edit = $result->fetch_assoc();
}

if (isset($_POST['save_siswa'])) {
    $nis = $_POST['nis'];
    $nama = $_POST['nama'];
    $password = $_POST['password'];
    
    $password_hint = $_POST['password_hint'];   // Di proses save_siswa

    if (!empty($password)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE siswa SET nama = '$nama', password = '$password_hashed', password_hint = '$password_hint' WHERE nis = '$nis'";
    } else {
        $sql = "UPDATE siswa SET nama = '$nama', password_hint = '$password_hint' WHERE nis = '$nis'";
    }
    
    if ($conn->query($sql) === TRUE) {
        $success_siswa = "Data siswa berhasil diperbarui!";
        // Redirect untuk menghindari form resubmission
        header('Location: index.php?page=admin#siswa');
        exit();
    } else {
        $error_siswa = "Error: " . $conn->error;
    }
}

if (isset($_POST['delete_siswa'])) {
    $nis = $_POST['nis'];
    $sql = "DELETE FROM siswa WHERE nis = '$nis'";
    
    if ($conn->query($sql) === TRUE) {
        $success_siswa = "Siswa berhasil dihapus!";
        // Redirect untuk menghindari form resubmission
        header('Location: index.php?page=admin#siswa');
        exit();
    } else {
        $error_siswa = "Error: " . $conn->error;
    }
}

if (isset($_POST['add_siswa'])) {
    $nis = $_POST['nis'];
    $nama = $_POST['nama'];
    $password = $_POST['password'];
    $password_hint = $_POST['password_hint'];
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO siswa (nis, nama, password, password_hint) VALUES ('$nis', '$nama', '$password_hashed', '$password_hint')";
    if ($conn->query($sql) === TRUE) {
        $success_siswa = "Siswa berhasil ditambahkan!";
        // Redirect untuk menghindari form resubmission
        header('Location: index.php?page=admin#siswa');
        exit();
    } else {
        $error_siswa = "Error: " . $conn->error;
    }
}

// Proses simpan pengaturan
if (isset($_POST['save_pengaturan'])) {
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $radius = $_POST['radius'];
    $jamMasuk = $_POST['jamMasuk'];   // NEW
    $jamPulang =  $_POST['jamPulang']; // NEW
    
    // Update atau insert
    $check = $conn->query("SELECT id FROM pengaturan ORDER BY id ASC LIMIT 1");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $id = $row['id'];
        $sql = "UPDATE pengaturan SET 
                latitude='$latitude', 
                longitude='$longitude', 
                radius='$radius',
                waktu_masuk='$jamMasuk',
                waktu_pulang='$jamPulang'
                WHERE id=$id";
    } else {
        $sql = "INSERT INTO pengaturan (latitude, longitude, radius, waktu_masuk, waktu_pulang) 
                VALUES ('$latitude', '$longitude', '$radius', '$jamMasuk', '$jamPulang')";
    }
    
    if ($conn->query($sql) === TRUE) {
        $success_pengaturan = "Pengaturan berhasil disimpan!";
        header('Location: index.php?page=admin#pengaturan');
        exit();
    } else {
        $error_pengaturan = "Error: " . $conn->error;
    }
}

//////////////////////////  HAPUS MASSAL DENGAN MULTI SELECT /////////////
// Proses hapus periode libur
if ($action == 'delete_periode_libur' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM periode_libur WHERE id = $id";
    if ($conn->query($sql)) {
        $success_admin = "Periode libur berhasil dihapus!";
    } else {
        $error_admin = "Error: " . $conn->error;
    }
    header('Location: index.php?page=admin#libur');
    exit();
}

//////////////////////////  END HAPUS MASSAL DENGAN MULTI SELECT /////////////

// Proses simpan keterangan terlambat
if (isset($_POST['save_keterangan_terlambat'])) {
    $nis = $_SESSION['nis'];
    $tanggal = date('Y-m-d');
    $keterangan = $_POST['keterangan'];
    
    $sql = "INSERT INTO terlambat (nis, tanggal, keterangan) VALUES ('$nis', '$tanggal', '$keterangan')";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['show_terlambat_modal'] = false;
        header('Location: index.php?page=presensi');
        exit();
    } else {
        $error = "Error menyimpan keterangan: " . $conn->error;
    }
}


// Proses pengajuan izin
if (isset($_POST['ajukan_izin'])) {
    $nis = $_SESSION['nis'];
    $tanggal = $_POST['tanggal'];
    $jenis = $_POST['jenis'];
    $keterangan = $_POST['keterangan'];
    $lampiran = '';
    // Cek duplikat izin untuk tanggal yang sama
    $cek_sql = "SELECT id FROM absensi_izin 
    WHERE nis = '$nis' AND tanggal = '$tanggal' 
    LIMIT 1";
    $cek_result = $conn->query($cek_sql);

    if ($cek_result->num_rows > 0) {
    $error_izin = "Anda sudah mengajukan izin/sakit untuk tanggal ini!";
    } else { 
    // Proses lampiran jika ada
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['lampiran'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Tentukan nama file berdasarkan jenis izin
        $namaFile = ($jenis == 'sakit') ? "sakit-$nis-" . date('YmdHis') . "." . $fileExt : "izin-$nis-" . date('YmdHis') . "." . $fileExt;
        
        // Gunakan path absolute dan tentukan direktori berdasarkan jenis
        $baseDir = __DIR__;
        $lampiranDir = ($jenis == 'sakit') ? $baseDir . '/uploads/lampiran/sakit' : $baseDir . '/uploads/lampiran/ijin';
        
        // Buat direktori jika belum ada
        if (!file_exists($lampiranDir)) {
            if (!mkdir($lampiranDir, 0777, true)) {
                $error_izin = "Gagal membuat folder untuk menyimpan lampiran!";
            }
        }
        
        $targetFile = $lampiranDir . '/' . $namaFile;
        
        // Pindahkan file upload
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Kompres gambar jika formatnya JPEG atau PNG
            if (in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
                if (compressImage($targetFile, $targetFile, 60)) {
                    // Simpan path relatif untuk database
                    $lampiran = ($jenis == 'sakit') ? "sakit/$namaFile" : "ijin/$namaFile";
                } else {
                    $error_izin = "Gagal mengkompres lampiran!";
                }
            } else {
                // Jika bukan gambar (misalnya PDF), simpan tanpa kompresi
                $lampiran = ($jenis == 'sakit') ? "sakit/$namaFile" : "ijin/$namaFile";
            }
        } else {
            $error_izin = "Gagal menyimpan lampiran! Pastikan folder '$lampiranDir' memiliki izin tulis.";
        }
    }
    
    $sql = "INSERT INTO absensi_izin (nis, tanggal, jenis, keterangan, lampiran) 
            VALUES ('$nis', '$tanggal', '$jenis', '$keterangan', '$lampiran')";
    
    if ($conn->query($sql) === TRUE) {
        $success_izin = "Pengajuan izin berhasil dikirim!";
    } else {
        $error_izin = "Error: " . $conn->error;
    }
    }
}

// Proses ubah status izin (admin)
if (isset($_POST['update_status_izin'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    $sql = "UPDATE absensi_izin SET status = '$status' WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $success_admin = "Status izin berhasil diperbarui!";
    } else {
        $error_admin = "Error: " . $conn->error;
    }
}

// Proses presensi
if (isset($_POST['presensi'])) {
    $nis = $_SESSION['nis'];
    $jenis = $_POST['jenis_presensi'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $tanggal = date('Y-m-d');
    
    // Gunakan pengaturan dari database
    $jarak = hitungJarak($latSekolah, $lngSekolah, $latitude, $longitude);
    
    // Radius dari database
    if ($jarak > $radiusSekolah) {
        $error = "Anda berada di luar area sekolah! (".round($jarak)." Meter dari pusat)";
    } else {
        // Cek apakah sudah ada presensi hari ini
        $cek_sql = "SELECT * FROM presensi WHERE nis = '$nis' AND tanggal = '$tanggal'";
        $cek_result = $conn->query($cek_sql);
        $row_presensi = $cek_result->fetch_assoc();
        
        // Jika presensi masuk
        if ($jenis == 'masuk') {
            // Jika sudah ada presensi masuk hari ini
            if ($row_presensi && $row_presensi['jam_masuk']) {
                $error = "Anda sudah melakukan presensi masuk hari ini!";
            } else {
                // Proses foto
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    $namaFile = "foto-masuk-$nis-" . date('H.i-d.m.Y') . ".jpg";
                    
                    // Gunakan path absolute
                    $baseDir = __DIR__;
                    $fotoDir = $baseDir . '/uploads/foto/masuk';
                    
                    // Buat direktori jika belum ada
                    if (!file_exists($fotoDir)) {
                        if (!mkdir($fotoDir, 0777, true)) {
                            $error = "Gagal membuat folder untuk menyimpan foto!";
                        }
                    }
                    
                    $targetFile = $fotoDir . '/' . $namaFile;
                    
                    // Pindahkan file upload
                    if (move_uploaded_file($foto['tmp_name'], $targetFile)) {
                        // Kompres gambar ke 500KB
                        if (compressImage($targetFile, $targetFile, 60)) {
                            // Simpan data presensi
                            $waktu = date('H:i:s');
                            
                            // Tentukan status kehadiran
                            $status_masuk = 'tepat waktu';
                            $keterangan_terlambat = null;

                            // Perbaikan perhitungan terlambat
                            $selisih = hitungSelisihWaktu($jamMasuk, $waktu);
                            
                            if ($selisih !== null && $waktu > $jamMasuk) {
                                $status_masuk = 'terlambat';
                                $keterangan_terlambat = $selisih;
                                $_SESSION['show_terlambat_modal'] = true;
                            }
                          
                            // Simpan lokasi presensi
                            $lokasi = "$latitude,$longitude";
                            
                            if ($row_presensi) {
                                // Update jika sudah ada (mungkin hanya ada pulang sebelumnya, tapi seharusnya tidak)
                                $update_sql = "UPDATE presensi SET 
                                    jam_masuk = '$waktu', 
                                    foto_masuk = '$namaFile', 
                                    status_masuk = '$status_masuk',
                                    keterangan_terlambat = " . ($keterangan_terlambat ? "'$keterangan_terlambat'" : "NULL") . ",
                                    lokasi_masuk = '$lokasi' 
                                    WHERE nis = '$nis' AND tanggal = '$tanggal'";
                                    
                                if ($conn->query($update_sql) === TRUE) {
                                    $success = "Presensi masuk berhasil dicatat!";
                                } else {
                                    $error = "Error update: " . $conn->error;
                                }
                            } else {
                                // Insert baru
                                $insert_sql = "INSERT INTO presensi (nis, tanggal, jam_masuk, foto_masuk, status_masuk, keterangan_terlambat, lokasi_masuk) 
                                        VALUES ('$nis', '$tanggal', '$waktu', '$namaFile', '$status_masuk', " . ($keterangan_terlambat ? "'$keterangan_terlambat'" : "NULL") . ", '$lokasi')";
                                        
                                if ($conn->query($insert_sql) === TRUE) {
                                    $success = "Presensi masuk berhasil dicatat!";
                                } else {
                                    $error = "Error insert: " . $conn->error;
                                }
                            }
                        } else {
                            $error = "Gagal mengkompres foto!";
                        }
                    } else {
                        $error = "Gagal menyimpan foto! Pastikan folder 'uploads/foto' memiliki izin tulis.";
                    }
                } else {
                    $error = "Foto tidak terupload! Error: " . $_FILES['foto']['error'];
                }
            }
        } else { // presensi pulang
            // Cek apakah sudah ada presensi masuk hari ini
            if (!$row_presensi || !$row_presensi['jam_masuk']) {
                $error = "Anda belum melakukan presensi masuk hari ini!";
            } else if ($row_presensi['jam_pulang']) {
                $error = "Anda sudah melakukan presensi pulang hari ini!";
            } else {
                // Proses foto
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    $namaFile = "foto-pulang-$nis-" . date('H.i-d.m.Y') . ".jpg";
                    
                    // Gunakan path absolute
                    $baseDir = __DIR__;
                    $fotoDir = $baseDir . '/uploads/foto/pulang';
                    
                    // Buat direktori jika belum ada
                    if (!file_exists($fotoDir)) {
                        if (!mkdir($fotoDir, 0777, true)) {
                            $error = "Gagal membuat folder untuk menyimpan foto!";
                        }
                    }
                    
                    $targetFile = $fotoDir . '/' . $namaFile;
                    
                    // Pindahkan file upload
                    if (move_uploaded_file($foto['tmp_name'], $targetFile)) {
                        // Kompres gambar ke 500KB
                        if (compressImage($targetFile, $targetFile, 60)) {
                            // Simpan data presensi
                            $waktu = date('H:i:s');
                            
                            $status_pulang = 'tepat waktu';
                            $keterangan_pulang_cepat = null;
                            
                            // Perbaikan perhitungan pulang cepat
                            $selisih = hitungSelisihWaktu($waktu, $jamPulang);
                            
                            if ($selisih !== null && $waktu < $jamPulang) {
                                $status_pulang = 'cepat';
                                $keterangan_pulang_cepat = $selisih;
                            }
                            
                            // Simpan lokasi presensi
                            $lokasi = "$latitude,$longitude";
                            
                            $update_sql = "UPDATE presensi SET 
                                jam_pulang = '$waktu', 
                                foto_pulang = '$namaFile', 
                                status_pulang = '$status_pulang',
                                keterangan_pulang_cepat = " . ($keterangan_pulang_cepat ? "'$keterangan_pulang_cepat'" : "NULL") . ",
                                lokasi_pulang = '$lokasi' 
                                WHERE nis = '$nis' AND tanggal = '$tanggal'";
                            
                            if ($conn->query($update_sql) === TRUE) {
                                $success = "Presensi pulang berhasil dicatat!";
                            } else {
                                $error = "Error: " . $conn->error;
                            }
                        } else {
                            $error = "Gagal mengkompres foto!";
                        }
                    } else {
                        $error = "Gagal menyimpan foto! Pastikan folder 'uploads/foto' memiliki izin tulis.";
                    }
                } else {
                    $error = "Foto tidak terupload! Error: " . $_FILES['foto']['error'];
                }
            }
        }
    }
}

// Tangani logout
if ($page == 'logout') {
    session_destroy();
    header('Location: index.php?page=login');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Siswa </title>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ffffff">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 100%;
            padding: 15px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
        }
        
        .header {
            background: linear-gradient(135deg, #3498db, #1a5276);
            color: white;
            padding: 15px 0;
            text-align: center;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
	.nav-tabs {
	    display: flex;
	    justify-content: space-between;
	    border-bottom: 1px solid #ddd;
	    margin-bottom: 15px;
	    overflow-x: auto;
	    -webkit-overflow-scrolling: touch;
	    flex-wrap: wrap;
	}

	.tab-group {
	    display: flex;
	    flex-wrap: wrap;
	    gap: 5px;
	}

	.tab-group-left {
	    justify-content: flex-start;
	    flex: 1;
	}

	.tab-group-right {
	    justify-content: flex-end;
	}

        
        .nav-tabs a {
            padding: 10px 15px;
            text-decoration: none;
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        .nav-tabs a.active {
            border-bottom: 3px solid #3498db;
            color: #3498db;
        }
        
        .nav-tabs a:hover {
            color: #3498db;
        }
        
        .tabs-container {
            margin-bottom: 15px;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        input, button, select, textarea {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 0.95rem;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        button {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        button:hover {
            background: linear-gradient(135deg, #2980b9, #1a5276);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            margin: 15px auto;
            border-radius: 10px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 4/3;
        }
        
        #video {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }
        
        .camera-controls {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
        }
        
        .btn-capture {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid #3498db;
            cursor: pointer;
        }
        
        .presensi-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            background: #e8f4fc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            gap: 8px;
        }
        
        .info-item {
            text-align: center;
            flex: 1 1 calc(25% - 8px);
            min-width: 100px;
            padding: 8px;
            background: #FFFCFB;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2980b9;
            margin-bottom: 4px;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        tr:hover {
            background-color: #f5f7fa;
        }
        
        .foto-presensi {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .foto-presensi:hover {
            transform: scale(1.05);
        }
        
        .status-tepat {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-telambat {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .status-cepat {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-pending {
            color: #3498db;
            font-weight: 600;
        }
        
        .status-diterima {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-ditolak {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            color: #7f8c8d;
            font-size: 0.8rem;
            margin-top: 15px;
        }
        
        .presensi-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0;
        }
        
        @media (min-width: 480px) {
            .presensi-options {
                flex-direction: row;
            }
        }
        
        .presensi-option {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            background: #f5f7fa;
            border: 2px solid #ddd;
            transition: all 0.3s;
            flex: 1;
        }
        
        .presensi-option.active {
            border-color: #3498db;
            background: #e8f4fc;
        }
        
        .presensi-option.masuk.active {
            border-color: #27ae60;
            background: #e8f6f0;
        }
        
        .presensi-option.pulang.active {
            border-color: #e74c3c;
            background: #fceae8;
        }
        
        .file-input-container {
            margin: 15px 0;
            text-align: center;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 16px;
            background: #3498db;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        
        .file-input-label:hover {
            background: #2980b9;
        }
        
        #foto-input {
            display: none;
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
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            color: #aaa;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-title {
            margin-bottom: 15px;
            text-align: center;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .delete-confirm {
            text-align: center;
            padding: 15px;
        }
        
        .delete-confirm p {
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            flex-direction: column;
        }
        
        @media (min-width: 480px) {
            .btn-group {
                flex-direction: row;
            }
        }
        
        .btn-group button {
            flex: 1;
        }
        
        /* Responsive table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .header p {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 0.8rem;
            }
            
            .foto-presensi {
                width: 45px;
                height: 45px;
            }
            
            .info-item {
                flex: 1 1 calc(50% - 8px);
            }
            
            .info-value {
                font-size: 1rem;
            }
            
            .presensi-option {
                padding: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .info-item {
                flex: 1 1 100%;
            }
            
            .nav-tabs a {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .file-input-label {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .modal-content {
                padding: 15px;
            }
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #b8c2cc;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #3498db;
        }
        
        /* Action buttons in table */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-buttons button {
            padding: 8px 10px;
            font-size: 0.8rem;
        }
        
        .lokasi-link {
            display: inline-block;
            padding: 5px 10px;
            background: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .lokasi-link:hover {
            background: #2980b9;
        }
        
        /* Ikon tombol kecil */
        .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        
        .btn-icon-edit {
            background: #3498db;
            color: white;
            border: none;
        }
        
        .btn-icon-delete {
            background: #e74c3c;
            color: white;
            border: none;
            margin-left: 5px;
        }
        
        /* Form edit sederhana */
        .edit-form {
            padding: 20px;
        }
        
        .edit-form .form-group {
            margin-bottom: 15px;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .menu-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }
        
        .menu-option {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .menu-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        
        .menu-icon {
            font-size: 2rem;
            margin-right: 20px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
        }
        
        .menu-kehadiran .menu-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .menu-ketidakhadiran .menu-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .menu-text {
            flex: 1;
        }
        
        .menu-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .menu-description {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        /* Modal Gambar */
        #imageModal {
            display: none;
            position: fixed;
            z-index: 2000;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }
        
        #imageModal .modal-content {
            max-width: 95%;
            max-height: 80vh;
            display: block;
            margin: auto;
            margin-top: 5vh;
        }
        
        #imageModal .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            text-shadow: 0 0 5px rgba(0,0,0,0.5);
            z-index: 100;
        }
        
        #imageModal .close-modal:hover,
        #imageModal .close-modal:focus {
            color: #bbb;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-user-check"></i> PRESENSI SISWA TKJ</h1>
        <p>Bukti Unjuk Kinerja Dengan Sistem Manajemen Kelas</p>
    </div>
    
    
    <div class="container">
        <?php if ($page == 'login'): ?>
            <!-- Halaman Login Terpadu -->
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;">Login Presensi</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error">
                        <?php echo $error; ?>
                        <?php if (strpos($error, 'Password salah') !== false): ?>
                            <div style="margin-top: 8px;">
                                <a href="index.php?page=reset_password&nis=<?php echo isset($_POST['identifier']) ? $_POST['identifier'] : ''; ?>" 
                                style="color: #3498db; font-size: 0.9rem;">
                                <i class="fas fa-question-circle"></i> Lupa Password? Gunakan Password Hint
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="identifier"><i class="fas fa-user"></i> Username (NISN)</label>
                        <input type="text" id="identifier" name="identifier" required placeholder="Masukkan Username atau NISN ">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" required placeholder="Masukkan password">
                    </div>
                    
                    <button type="submit" name="login"><i class="fas fa-sign-in-alt"></i> Mulai Presensi</button>
                </form>
            </div>
        <?php endif; ?>
        

<?php if ($page == 'reset_password'): ?>
    <!-- Halaman Reset Password -->
    <div class="card">
        <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;"><i class="fas fa-key"></i> Reset Password</h2>
        
        <?php if (isset($reset_error)): ?>
            <div class="error"><?php echo $reset_error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($reset_success)): ?>
            <div class="success"><?php echo $reset_success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nis"><i class="fas fa-id-card"></i> NISN</label>
                <input type="text" id="nis" name="nis" required placeholder="Masukkan NISN Anda">
            </div>
            
            <div class="form-group">
                <label for="hint_answer"><?php 
                    // Tampilkan pertanyaan hint default
                    echo isset($_POST['nis']) ? "Masukkan jawaban hint: " : "Pertanyaan hint akan ditampilkan setelah memasukkan NISN";
                ?></label>
                <input type="text" id="hint_answer" name="hint_answer" required placeholder="Masukkan jawaban hint">
                <small>Contoh: Tokoh favorit, nama hewan peliharaan, dll.</small>
            </div>
            
            <div class="form-group">
                <label for="new_password"><i class="fas fa-lock"></i> Password Baru</label>
                <input type="password" id="new_password" name="new_password" required placeholder="Masukkan password baru">
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Konfirmasi Password Baru</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Konfirmasi password baru">
            </div>
            
            <button type="submit" name="reset_password" class="btn-warning">
                <i class="fas fa-sync-alt"></i> Reset Password
            </button>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="index.php?page=login" style="color: #3498db;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Login
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>	

        <?php if ($page == 'menu' && isset($_SESSION['nis'])): ?>
           <div style="text-align: right; margin-bottom: 25px;">
           	   <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;">
                 <i class="fas fa-sign-out-alt"></i> Keluar
               </a>
           </div>
            <!-- Menu Utama Siswa -->
            <div class="presensi-info">
                <div class="info-item">
                    <div class="info-value"><?php echo date('H:i'); ?></div>
                    <div class="info-label"><i class="fas fa-clock"></i> Waktu Sekarang</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nama']; ?></div>
                    <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nis']; ?></div>
                    <div class="info-label"><i class="fas fa-id-card"></i> NISN</div>
                </div>
            </div>
            
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 20px; font-size: 1.3rem;">Menu Utama</h2>

                <div class="menu-options">
                    <a href="index.php?page=presensi" class="menu-option menu-kehadiran">
                        <div class="menu-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="menu-text">
                            <div class="menu-title">Kehadiran (Presensi)</div>
                            <div class="menu-description">Presensi masuk dan pulang dengan verifikasi lokasi dan foto</div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <a href="index.php?page=izin" class="menu-option menu-ketidakhadiran">
                        <div class="menu-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="menu-text">
                            <div class="menu-title">Ketidakhadiran (Ijin/Sakit)</div>
                            <div class="menu-description">Ajukan ijin tidak masuk karena sakit atau keperluan lainnya</div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                </div>
                

            </div>
        <?php endif; ?>
        
        <?php if ($page == 'presensi' && isset($_SESSION['nis'])): ?>
        <!-- Halaman Presensi -->
        <?php
        $today = date('Y-m-d');
        $sql_check_libur = "SELECT * FROM periode_libur 
                            WHERE '$today' BETWEEN tanggal_mulai AND tanggal_selesai";
        $result_libur = $conn->query($sql_check_libur);

        $is_libur = false;
        $error_libur = '';
        if ($result_libur->num_rows > 0) {
            $is_libur = true;
            $libur = $result_libur->fetch_assoc();
            $error_libur = "Presensi tidak dapat dilakukan karena periode libur: <b>" . $libur['nama_periode'] . 
                        "</b> (" . formatTanggalID($libur['tanggal_mulai']) . " s/d " . formatTanggalID($libur['tanggal_selesai']) . ")";
        }
        // Cek hari Sabtu (6) atau Minggu (0)
        $hari_ini = date('w'); // 0 (Minggu) sampai 6 (Sabtu)
        $is_weekend = ($hari_ini == 0 || $hari_ini == 6);
        ?>
                    <?php
                    // Ambil rekap absen bulan ini untuk siswa ini
                    $nis_siswa = $_SESSION['nis'];
                    $bulan_ini = date('m');
                    $tahun_ini = date('Y');

                    $sql_rekap = "SELECT 
                        COUNT(*) AS total_hadir,
                        SUM(CASE WHEN status_masuk = 'tepat waktu' THEN 1 ELSE 0 END) AS tepat_waktu,
                        SUM(CASE WHEN status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                        SUM(CASE WHEN jam_pulang IS NOT NULL THEN 1 ELSE 0 END) AS pulang
                        FROM presensi 
                        WHERE nis = '$nis_siswa' 
                        AND MONTH(tanggal) = '$bulan_ini' 
                        AND YEAR(tanggal) = '$tahun_ini'";

                    $result_rekap = $conn->query($sql_rekap);
                    $rekap = $result_rekap->fetch_assoc();

                    $total_hadir = $rekap['total_hadir'] ?? 0;
                    $tepat_waktu = $rekap['tepat_waktu'] ?? 0;
                    $terlambat = $rekap['terlambat'] ?? 0;
                    $pulang = $rekap['pulang'] ?? 0;
                    ?>
                        <div style="text-align: right; margin-top: 15px;">
                            <a href="index.php?page=menu" style="color: #3498db; text-decoration: none; font-size: 0.9rem; margin-right: 15px;">
                                <i class="fas fa-arrow-left"></i> Kembali ke Menu
                            </a>
                            <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;">
                                <i class="fas fa-sign-out-alt"></i> Keluar
                            </a>
                        </div>
                    <div class="presensi-info">
                        <div class="info-item">
                            <div class="info-value"><?php echo date('H:i'); ?></div>
                            <div class="info-label"><i class="fas fa-clock"></i> Waktu Sekarang</div>
                        </div>
                        <div class="info-item">
                            <div class="info-value"><?php echo $_SESSION['nama']; ?></div>
                            <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                        </div>
                        <div class="info-item">
                            <div class="info-value"><?php echo $_SESSION['nis']; ?></div>
                            <div class="info-label"><i class="fas fa-id-card"></i> NISN</div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3 style="text-align: center; margin-bottom: 15px; font-size: 1.1rem;"><i class="fas fa-chart-bar"></i> Rekap Presensi Bulan Ini</h3>
                        <div class="presensi-info">
                            <div class="info-item">
                                <div class="info-value"><?php echo $total_hadir; ?></div>
                                <div class="info-label">Total Hadir</div>
                            </div>
                            <div class="info-item">
                                <div class="info-value"><?php echo $tepat_waktu; ?></div>
                                <div class="info-label">Tepat Waktu</div>
                            </div>
                            <div class="info-item">
                                <div class="info-value"><?php echo $terlambat; ?></div>
                                <div class="info-label">Terlambat</div>
                            </div>
                            <div class="info-item">
                                <div class="info-value"><?php echo $pulang; ?></div>
                                <div class="info-label">Pulang</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="error">
                            <strong>Error:</strong> <?php echo $error; ?>
                            <?php if (isset($foto) && is_array($foto)): ?>
                                <div style="margin-top: 8px; font-size: 11px;">
                                    <div>Nama File: <?php echo $foto['name']; ?></div>
                                    <div>Ukuran: <?php echo round($foto['size']/1024, 2); ?> KB</div>
                                    <div>Tipe: <?php echo $foto['type']; ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;"><i class="fas fa-camera"></i> Presensi Siswa</h2>
                        <?php if ($is_libur || $is_weekend): ?>
        <div class="error" style="text-align: center; padding: 20px;">
            <i class="fas fa-calendar-times fa-2x"></i>
            <?php if ($is_weekend): ?>
                <h3 style="margin: 10px 0;">Presensi tidak dapat dilakukan karena hari <?php echo $hari_ini == 0 ? 'Minggu' : 'Sabtu'; ?></h3>
            <?php else: ?>
                <h3 style="margin: 10px 0;"><?php echo $error_libur; ?></h3>
            <?php endif; ?>
            <p>Silakan kembali ke menu utama.</p>
            </div>
        <?php else: ?>                
                <form method="POST" enctype="multipart/form-data" id="presensi-form">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <input type="hidden" name="jenis_presensi" id="jenis_presensi" value="masuk">
                    
                    <!-- Opsi Presensi -->
                    <div class="presensi-options">
                        <div class="presensi-option masuk active" data-jenis="masuk">
                            <i class="fas fa-sign-in-alt fa-lg"></i>
                            <div>Presensi Masuk</div>
                        </div>
                        <div class="presensi-option pulang" data-jenis="pulang">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                            <div>Presensi Pulang</div>
                        </div>
                    </div>
                    
                    <!-- Preview Kamera -->
                    <div class="camera-container">
                        <video id="video" autoplay playsinline></video>
                        <div class="camera-controls">
                            <button type="button" id="btn-capture" class="btn-capture">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    <canvas id="canvas" style="display: none;"></canvas>
                    
                    <!-- Input File untuk Foto -->
                    <div class="file-input-container">
                        <label for="foto-input" class="file-input-label">
                            <i class="fas fa-camera"></i> Ambil Foto Wajah
                        </label>
                        <input type="file" name="foto" id="foto-input" accept="image/*" capture="user" required>
                    </div>
                    
                    <!-- Info Lokasi -->
                    <div id="lokasi-info" style="margin-top: 12px; padding: 10px; background: #f8f9fa; border-radius: 8px; text-align: center; font-size: 0.9rem;">
                        <i class="fas fa-sync fa-spin"></i> Mengambil lokasi...
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="presensi" id="btn-submit" disabled class="btn-success" style="width: 100%; margin-top: 10px;">
                        <i class="fas fa-paper-plane"></i> Kirim Presensi
                    </button>
                </form>
 <?php endif; ?>               

            </div>
            
            <!-- Modal Keterangan Terlambat -->
            <?php if (isset($_SESSION['show_terlambat_modal']) && $_SESSION['show_terlambat_modal']): ?>
                <div id="terlambatModal" class="modal" style="display: block;">
                    <div class="modal-content">
                        <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Keterangan Terlambat</h3>
                        <p style="margin-bottom: 15px; text-align: center;">
                            Anda terlambat melakukan presensi. Silakan berikan keterangan alasan keterlambatan Anda.
                        </p>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="keterangan">Alasan Keterlambatan</label>
                                <textarea id="keterangan" name="keterangan" rows="4" required placeholder="Mohon jelaskan alasan keterlambatan Anda..."></textarea>
                            </div>
                            
                            <button type="submit" name="save_keterangan_terlambat" class="btn-warning">
                                <i class="fas fa-paper-plane"></i> Kirim Keterangan
                            </button>
                        </form>
                    </div>
                </div>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('terlambatModal').style.display = 'block';
                    });
                </script>
            <?php endif; ?>
            
            <script>
                // Inisialisasi variabel
                const video = document.getElementById('video');
                const canvas = document.getElementById('canvas');
                const ctx = canvas.getContext('2d');
                const btnCapture = document.getElementById('btn-capture');
                const fotoInput = document.getElementById('foto-input');
                const lokasiInfo = document.getElementById('lokasi-info');
                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');
                const btnSubmit = document.getElementById('btn-submit');
                const jenisPresensiInput = document.getElementById('jenis_presensi');
                const presensiOptions = document.querySelectorAll('.presensi-option');
                
                // Set ukuran canvas sesuai video
                function setCanvasSize() {
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                    }
                }
                
                // Mengakses kamera
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    navigator.mediaDevices.getUserMedia({ 
                        video: { facingMode: 'user' } // Gunakan kamera depan
                    })
                    .then(function(stream) {
                        video.srcObject = stream;
                        video.addEventListener('loadedmetadata', function() {
                            setCanvasSize();
                        });
                    })
                    .catch(function(error) {
                        lokasiInfo.innerHTML = "Tidak dapat mengakses kamera: " + error.name;
                        console.error("Camera error: ", error);
                    });
                } else {
                    lokasiInfo.innerHTML = "Browser Anda tidak mendukung akses kamera";
                }
                
                // Geolocation
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(showPosition, showError, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    });
                } else {
                    lokasiInfo.innerHTML = "Geolocation tidak didukung oleh browser ini.";
                }
                
                function showPosition(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    latitudeInput.value = lat;
                    longitudeInput.value = lng;
                    
                    // Koordinat sekolah diambil dari PHP
                    const latSekolah = <?php echo $latSekolah; ?>;
                    const lngSekolah = <?php echo $lngSekolah; ?>;
                    const radiusSekolah = <?php echo $radiusSekolah; ?>;
                    
                    // Hitung jarak dalam meter
                    const jarak = hitungJarak(latSekolah, lngSekolah, lat, lng);
                    
                    if (jarak <= radiusSekolah) {
                        lokasiInfo.innerHTML = `<i class="fas fa-check-circle"></i> <b>ANDA</b> berada <b>DI DALAM AREA SEKOLAH</b> <br/>(${jarak.toFixed(0)} meter dari pusat radius presensi).`;
                        btnSubmit.disabled = false;
                    } else {
                        lokasiInfo.innerHTML = `<i class="fas fa-exclamation-triangle"></i> <b>ANDA</b> berada <b>DI LUAR AREA SEKOLAH</b> <br/>(${jarak.toFixed(0)} meter dari pusat). Hanya bisa melakukan presensi dalam radius ${radiusSekolah} meter.`;
                        btnSubmit.disabled = true;
                    }
                }
                
                function showError(error) {
                    let message = '';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message = "Izin lokasi ditolak. Aktifkan izin lokasi untuk presensi.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = "Informasi lokasi tidak tersedia.";
                            break;
                        case error.TIMEOUT:
                            message = "Permintaan lokasi timeout.";
                            break;
                        case error.UNKNOWN_ERROR:
                            message = "Terjadi kesalahan tidak diketahui.";
                            break;
                    }
                    lokasiInfo.innerHTML = `<i class='fas fa-exclamation-circle'></i> ${message}`;
                    btnSubmit.disabled = true;
                }
                
                // Fungsi untuk menghitung jarak
                function hitungJarak(lat1, lon1, lat2, lon2) {
                    const R = 6371e3; // Radius bumi dalam meter
                    const φ1 = lat1 * Math.PI/180;
                    const φ2 = lat2 * Math.PI/180;
                    const Δφ = (lat2-lat1) * Math.PI/180;
                    const Δλ = (lon2-lon1) * Math.PI/180;
                    
                    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                              Math.cos(φ1) * Math.cos(φ2) *
                              Math.sin(Δλ/2) * Math.sin(Δλ/2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    
                    const distance = R * c;
                    return distance;
                }
                
                // Tombol ambil foto
                btnCapture.addEventListener('click', function() {
                    // Ambil gambar dari video
                    setCanvasSize();
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    // Konversi ke blob dan set ke input file
                    canvas.toBlob(function(blob) {
                        const file = new File([blob], "presensi.jpg", {type: 'image/jpeg'});
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fotoInput.files = dataTransfer.files;
                        
                        // Tampilkan notifikasi
                        alert('Foto telah diambil! Silakan kirim presensi.');
                    }, 'image/jpeg', 0.9);
                });
                
                // Pilihan jenis presensi
                presensiOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        // Hapus active class dari semua opsi
                        presensiOptions.forEach(opt => opt.classList.remove('active'));
                        
                        // Tambahkan active class ke opsi yang dipilih
                        this.classList.add('active');
                        
                        // Set nilai jenis presensi
                        jenisPresensiInput.value = this.dataset.jenis;
                    });
                });
                
                // Form submission handler
                document.getElementById('presensi-form').addEventListener('submit', function(e) {
                    if (!fotoInput.files.length) {
                        e.preventDefault();
                        alert('Silakan ambil foto terlebih dahulu!');
                    } else if (btnSubmit.disabled) {
                        e.preventDefault();
                        alert('Lokasi Anda di luar area sekolah atau tidak valid!');
                    } else {
                        // Tampilkan loading indicator
                        const overlay = document.createElement('div');
                        overlay.className = 'loading-overlay';
                        overlay.innerHTML = '<div class="loading-spinner"></div>';
                        document.body.appendChild(overlay);
                        overlay.style.display = 'flex';
                    }
                });
            </script>
        <?php endif; ?>
        
        <?php if ($page == 'izin' && isset($_SESSION['nis'])): ?>
            <!-- Halaman Pengajuan Izin -->
                <div style="text-align: right; margin-top: 25px;">
                    <a href="index.php?page=menu" style="color: #3498db; text-decoration: none; font-size: 0.9rem; margin-right: 15px;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Menu
                    </a>
                    <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </a>
                </div>
            <div class="presensi-info">
                <div class="info-item">
                    <div class="info-value"><?php echo date('d/m/Y'); ?></div>
                    <div class="info-label"><i class="fas fa-calendar"></i> Tanggal</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nama']; ?></div>
                    <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nis']; ?></div>
                    <div class="info-label"><i class="fas fa-id-card"></i> NISN</div>
                </div>
            </div>
            
            <?php if (isset($error_izin)): ?>
                <div class="error"><?php echo $error_izin; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success_izin)): ?>
                <div class="success"><?php echo $success_izin; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;"><i class="fas fa-user-times"></i> Pengajuan Izin</h2>
                
                <form method="POST" enctype="multipart/form-data" id="form-izin" onsubmit="disableSubmitButton()">
                    <div class="form-group">
                        <label for="tanggal"><i class="fas fa-calendar"></i> Tanggal Izin</label>
                        <input type="date" id="tanggal" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="jenis"><i class="fas fa-info-circle"></i> Jenis Izin</label>
                        <select id="jenis" name="jenis" required>
                            <option value="">-- Pilih Jenis Izin --</option>
                            <option value="sakit">Sakit</option>
                            <option value="ijin">Ijin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="keterangan"><i class="fas fa-comment"></i> Keterangan</label>
                        <textarea id="keterangan" name="keterangan" rows="4" required placeholder="Berikan keterangan alasan ketidakhadiran Anda..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="lampiran"><i class="fas fa-paperclip"></i> Lampiran (Opsional)</label>
                        <input type="file" id="lampiran" name="lampiran" accept="image/*,application/pdf">
                        <small style="display: block; margin-top: 5px; color: #7f8c8d;">Format: JPG, PNG, PDF (maks. 2MB)</small>
                    </div>
                    
                    <button type="submit" name="ajukan_izin" id="btn-ajukan" class="btn-warning">
                        <i class="fas fa-paper-plane"></i> Ajukan Izin
                    </button>
            <!-- Loading Indicator -->
            <div id="loading-izin" style="display: none; text-align: center; margin-top: 15px;">
                <i class="fas fa-spinner fa-spin"></i> Mengirim data...
            </div>
                </form>
                
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px; font-size: 1.1rem;"><i class="fas fa-history"></i> Riwayat Pengajuan Izin</h3>
                    
                    <?php
                    $nis_siswa = $_SESSION['nis'];
                    $sql = "SELECT * FROM absensi_izin WHERE nis = '$nis_siswa' ORDER BY tanggal DESC, jenis ASC";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Keterangan</th>
                                        <th>Lampiran</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td><?php echo ucfirst($row['jenis']); ?></td>
                                            <td><?php echo substr($row['keterangan'], 0, 50) . (strlen($row['keterangan']) > 50 ? '...' : ''); ?></td>
                                            <td>
                                                <?php if ($row['lampiran']): 
                                                    $ext = pathinfo($row['lampiran'], PATHINFO_EXTENSION);
                                                    $ext = strtolower($ext);
                                                ?>
                                                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                        <img src="uploads/lampiran/<?php echo $row['lampiran']; ?>" 
                                                             class="foto-presensi" 
                                                             onclick="showImageModal('uploads/lampiran/<?php echo $row['lampiran']; ?>')">
                                                    <?php elseif ($ext === 'pdf'): ?>
                                                        <a href="uploads/lampiran/<?php echo $row['lampiran']; ?>" target="_blank">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="uploads/lampiran/<?php echo $row['lampiran']; ?>" target="_blank">Lihat Lampiran</a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <span class="status-pending">Menunggu</span>
                                                <?php elseif ($row['status'] == 'diterima'): ?>
                                                    <span class="status-diterima">Diterima</span>
                                                <?php else: ?>
                                                    <span class="status-ditolak">Ditolak</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; padding: 15px; color: #7f8c8d;">Belum ada riwayat pengajuan izin</p>
                    <?php endif; ?>
                </div>
                

            </div>
        <?php endif; ?>
        
        <?php if ($page == 'admin' && isset($_SESSION['admin'])): ?>
            <!-- Admin Dashboard -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h2 style="font-size: 1.3rem;"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h2>
			<div style="text-align: right; margin-bottom: 25px;">
                <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;"><i class="fas fa-sign-out-alt"></i> Keluar</a>
			</div>
            </div>
            
            <?php if (isset($success_siswa)): ?>
                <div class="success"><?php echo $success_siswa; ?></div>
            <?php endif; ?>
            <?php if (isset($error_siswa)): ?>
                <div class="error"><?php echo $error_siswa; ?></div>
            <?php endif; ?>
            <?php if (isset($success_pengaturan)): ?>
                <div class="success"><?php echo $success_pengaturan; ?></div>
            <?php endif; ?>
            <?php if (isset($error_pengaturan)): ?>
                <div class="error"><?php echo $error_pengaturan; ?></div>
            <?php endif; ?>
            <?php if (isset($success_admin)): ?>
                <div class="success"><?php echo $success_admin; ?></div>
            <?php endif; ?>
            <?php if (isset($error_admin)): ?>
                <div class="error"><?php echo $error_admin; ?></div>
            <?php endif; ?>
            
            <!--QUERY EDIT TAMBAHAN DATA PRESENSI SISWA -->
		<?php
		    // Total Siswa
		    $sql_total_siswa = "SELECT COUNT(*) as total FROM siswa";
		    $result_total_siswa = $conn->query($sql_total_siswa);
		    $row_total = $result_total_siswa->fetch_assoc();
		    $total_siswa = $row_total['total'];

		    // Hadir Hari Ini
		    $today = date('Y-m-d');
		    $sql_hadir = "SELECT COUNT(DISTINCT nis) as total FROM presensi WHERE tanggal = '$today' AND jam_masuk IS NOT NULL";
		    $result_hadir = $conn->query($sql_hadir);
		    $row_hadir = $result_hadir->fetch_assoc();
		    $hadir_hari_ini = $row_hadir['total'];

		    // Terlambat Hari Ini
		    $sql_terlambat = "SELECT COUNT(DISTINCT nis) as total FROM presensi WHERE tanggal = '$today' AND status_masuk = 'terlambat'";
		    $result_terlambat = $conn->query($sql_terlambat);
		    $row_terlambat = $result_terlambat->fetch_assoc();
		    $terlambat_hari_ini = $row_terlambat['total'];

		    // Pulang Cepat Hari Ini
		    $sql_cepat = "SELECT COUNT(DISTINCT nis) as total FROM presensi WHERE tanggal = '$today' AND status_pulang = 'cepat'";
		    $result_cepat = $conn->query($sql_cepat);
		    $row_cepat = $result_cepat->fetch_assoc();
		    $cepat_hari_ini = $row_cepat['total'];

		?>
  
          <!--QUERY EDIT TAMBAHAN DATA PENGAJUAN IJIN -->
		<?php
		    // Total Ijin Siswa
		    $sql_total_ijin_siswa = "SELECT COUNT(*) as total FROM absensi_izin";
		    $result_total_ijin_siswa = $conn->query($sql_total_ijin_siswa);
		    $row_total_ijin = $result_total_ijin_siswa->fetch_assoc();
		    $total_ijin_siswa = $row_total_ijin['total'];

		    // Ijin Hari Ini
		    $sql_ijin = "SELECT COUNT(DISTINCT nis) as total FROM absensi_izin WHERE tanggal = '$today' AND jenis = 'ijin'";
		    $result_ijin = $conn->query($sql_ijin);
		    $row_ijin = $result_ijin->fetch_assoc();
		    $ijin_hari_ini = $row_ijin['total'];

		    // Sakit Hari Ini
		    $sql_sakit = "SELECT COUNT(DISTINCT nis) as total FROM absensi_izin WHERE tanggal = '$today' AND jenis = 'sakit'";
		    $result_sakit = $conn->query($sql_sakit);
		    $row_sakit = $result_sakit->fetch_assoc();
		    $sakit_hari_ini = $row_sakit['total'];
		?>
            <!--END QUERY EDIT TAMBAHAN-->

		<div class="tabs-container">

			<div class="nav-tabs">
				<!-- Kelompok Tab Kiri -->
				<div class="tab-group tab-group-left">
				    <a href="#presensi" class="active">Data Presensi</a>
        			    <a href="#pengajuan">Pengajuan Izin</a> <!-- Tab baru -->
				    <a href="#terlambat">Data Terlambat</a>
				    <a href="#rekap">Rekap Presensi</a>
				</div>
				
				<!-- Kelompok Tab Kanan -->
				<div class="tab-group tab-group-right">
				    <a href="#siswa">Data Siswa</a>
				    <a href="#pengaturan">Pengaturan</a>
                    <a href="#libur">Periode Libur</a>
				</div>
		 	</div>

		<div id="presensi" class="tab-content active">
		    <div class="card">
			  <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-list"></i> Data Presensi Siswa (<?= formatTanggalID($today) ?>)</h3>
			  			<!--EDIT RUBAH-->
				  <div class="presensi-info">
					    <div class="info-item">
						  <div class="info-value"><?php echo $total_siswa; ?></div>
						  <div class="info-label">Total Siswa Kelas</div>
					    </div>
					    <div class="info-item">
						  <div class="info-value"><?php echo $hadir_hari_ini; ?></div>
						  <div class="info-label">Hadir Hari Ini (Presensi)</div>
					    </div>
					    <div class="info-item">
						  <div class="info-value"><?php echo $terlambat_hari_ini; ?></div>
						  <div class="info-label">Terlambat Hari Ini</div>
					    </div>
					    <div class="info-item">
						  <div class="info-value"><?php echo $cepat_hari_ini; ?></div>
						  <div class="info-label">Pulang Cepat Hari Ini</div>
					    </div>
					</div>
				  </div>
		<!-- Form Filter Tanggal -->
        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="admin">
            <div class="form-group" style="display: flex; gap: 5px; align-items: center; margin-bottom: 10px;">
                <input type="date" id="start_date_presensi" name="start_date_presensi" 
                    value="<?php echo isset($_GET['start_date_presensi']) ? $_GET['start_date_presensi'] : date('Y-m-d'); ?>" >
                <label for="start_date_presensi" style="min-width: 150px;"> Tanggal Awal</label>
                <label for="end_date_presensi" style="min-width: 150px;" align="right">  Tanggal Akhir </label>
                <input type="date" id="end_date_presensi" name="end_date_presensi" 
                    value="<?php echo isset($_GET['end_date_presensi']) ? $_GET['end_date_presensi'] : date('Y-m-d'); ?>" >
            </div>
		<!-- Link sebagai tombol reset -->
		<div style="display: flex; gap: 10px;">
		    <!-- Tombol Filter -->
		    <button type="submit" style=" background-color: #28a745; color: white;  padding: 12px 20px;  border: none;  border-radius: 5px;   cursor: pointer;  font-size: 16px; ">  Filter   </button>

		    <!-- Tombol Reset -->
		    <a href="index.php?page=admin#presensi" style=" background-color: #28a745; color: white; padding: 12px 20px; border: none;	  border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block;"> .::Reset::.  </a>
		</div>


        </form>

			  <?php 
			  /* SEMUA HARI PRESENSI		
			 // $sql = "SELECT p.*, s.nama FROM presensi p JOIN siswa s ON p.nis = s.nis ORDER BY p.tanggal DESC, p.jam_masuk DESC";
			 // $result = $conn->query($sql);
			  
			$today = date('Y-m-d'); // Tambahkan ini
			$sql = "SELECT p.*, s.nama FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE p.tanggal = '$today' ORDER BY p.tanggal DESC, p.jam_masuk DESC"; // Ubah query dengan tambahan WHERE
			$result = $conn->query($sql);	
			  
			  if ($result->num_rows > 0): 
			*/

			// Ambil parameter filter
			  $start_date = isset($_GET['start_date_presensi']) ? $_GET['start_date_presensi'] : date('Y-m-d');
			  $end_date = isset($_GET['end_date_presensi']) ? $_GET['end_date_presensi'] : date('Y-m-d');
			  
			  // Query data presensi dengan filter tanggal
			  $sql = "SELECT p.*, s.nama 
				    FROM presensi p 
				    JOIN siswa s ON p.nis = s.nis 
				    WHERE p.tanggal BETWEEN '$start_date' AND '$end_date'
				    ORDER BY p.tanggal DESC, p.jam_masuk DESC";
			  $result = $conn->query($sql);
			  
			  if ($result->num_rows > 0):
			
			?>

            <!--<form method="POST" action="index.php?page=admin#presensi">-->
		<form id="deletePresensiForm" method="POST" action="index.php?page=admin#presensi">
                <input type="hidden" name="action" value="delete_selected_presensi">
                <button type="submit" class="btn-danger" style="margin-bottom: 10px;">
                    <i class="fas fa-trash"></i> Hapus Data Terpilih
                </button>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th><input type="checkbox" id="selectAllPresensi"></th>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>NISN</th>
                                <th>Nama</th>
                                <th>Jam Masuk</th>
                                <th>Foto Masuk</th>
                                <th>Status Masuk</th>
                                <th>Lokasi Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Foto Pulang</th>
                                <th>Status Pulang</th>
                                <th>Lokasi Pulang</th>
                                <th>Aksi</th> <!-- Kolom Baru -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no=1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><input type="checkbox" class="presensi-check" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                                    <td><?php echo $no++; ?></td>
				                    <td><?php echo formatTanggalID($row['tanggal']); ?></td>
				                    <td><?php echo $row['nis']; ?></td>
				                    <td><?php echo $row['nama']; ?></td>
				                    <td><?php echo $row['jam_masuk']; ?></td>
				                    <td>
				                        <?php if ($row['foto_masuk']): ?>
				                            <img src="uploads/foto/masuk/<?php echo $row['foto_masuk']; ?>" 
                                         class="foto-presensi" 
                                         onclick="showImageModal('uploads/foto/masuk/<?php echo $row['foto_masuk']; ?>')">
				                        <?php else: ?>
				                            -
				                        <?php endif; ?>
				                    </td>
 							        <td>
				                        <?php if ($row['jam_masuk']): ?>
				                            <span class="status-<?php 
				                                echo ($row['status_masuk'] == 'tepat waktu') ? 'tepat' : 'telambat'; 
				                            ?>">
				                                <?php echo $row['status_masuk']; ?>
				                            </span><br><?= $row['keterangan_terlambat'] ? $row['keterangan_terlambat'] : '-' ?>
				                        <?php else: ?>
				                            -
				                        <?php endif; ?>
				                        
				                    </td>
				                    <td>
				                        <?php if (!empty($row['lokasi_masuk'])): ?>
				                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $row['lokasi_masuk'] ?>" 
				                               target="_blank" class="lokasi-link">
				                                <i class="fas fa-map-marker-alt"></i> Masuk
				                            </a>
				                        <?php endif; ?>
				                    </td>
				                    <td><?php echo $row['jam_pulang'] ? $row['jam_pulang'] : '-'; ?></td>
				                    <td>
				                        <?php if ($row['foto_pulang']): ?>
				                            <img src="uploads/foto/pulang/<?php echo $row['foto_pulang']; ?>" 
                                         class="foto-presensi" 
                                         onclick="showImageModal('uploads/foto/pulang/<?php echo $row['foto_pulang']; ?>')">
				                        <?php else: ?>
				                            -
				                        <?php endif; ?>
				                    </td>
				                    <td>
				                        <?php if ($row['jam_pulang']): ?>
				                            <span class="status-<?php 
				                                echo ($row['status_pulang'] == 'tepat waktu') ? 'tepat' : 'cepat'; 
				                            ?>">
				                                <?php echo $row['status_pulang']; ?>
				                            </span><br>
									<?= $row['keterangan_pulang_cepat'] ? $row['keterangan_pulang_cepat'] : '-' ?>
				                        <?php else: ?>
				                            -
				                        <?php endif; ?>
				                    </td>
				                    <td>
				                        <?php if (!empty($row['lokasi_pulang'])): ?>
				                   
				                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $row['lokasi_pulang'] ?>" 
				                               target="_blank" class="lokasi-link">
				                                <i class="fas fa-map-marker-alt"></i> Pulang
				                            </a>
				                        <?php endif; ?>
				                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Tombol Edit -->
                                            <button class="btn-icon btn-icon-edit" onclick="openEditPresensiModal(<?php echo $row['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Tombol Hapus -->
                                            <button class="btn-icon btn-icon-delete" onclick="if(confirm('Hapus data presensi ini?')) { 
    window.location.href='index.php?page=admin&action=delete_presensi&id=<?php echo $row['id']; ?>#presensi'}" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
				                </tr>
				            <?php endwhile; ?>
				        </tbody>
				    </table>
				</div>
                <script>
                    document.getElementById('selectAllPresensi').addEventListener('click', function() {
                        const checkboxes = document.querySelectorAll('.presensi-check');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });
                </script>
			  <?php else: ?>
				<p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data presensi</p>
			  <?php endif; ?>
		    </div>
		</div>

                
                <div id="siswa" class="tab-content">
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-users"></i> Data Siswa</h3>
                            <a href="javascript:void(0)" class="btn-success" onclick="openModal('add', event)" style="padding: 10px 15px; font-size: 0.9rem; display: inline-block; text-align: center; text-decoration: none; color: white; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-plus"></i> Tambah Siswa
                            </a>
                        </div>
                        
                        <?php 
                        $sql = "SELECT * FROM siswa";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr> 
							  <th>No</th>
                                            <th>NISN</th>
                                            <th>Nama</th>
                                            <th>Password</th>
                                            <th>Password Hint</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php  
                                        $no = 1; // Inisialisasi no
                                        while ($row = $result->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td>••••••••</td>
                                                <td><?php echo $row['password_hint']; ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-icon btn-icon-edit" onclick="openModal('edit', event, '<?php echo $row['nis']; ?>')" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn-icon btn-icon-delete" onclick="openModal('delete', event, '<?php echo $row['nis']; ?>', '<?php echo $row['nama']; ?>')" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data siswa</p>
                        <?php endif; ?>
                    </div>

                </div>
                
                <div id="terlambat" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-clock"></i> Data Keterlambatan</h3>
                        
                        <?php 
                        $sql = "SELECT p.tanggal, p.nis, s.nama, p.keterangan_terlambat, t.keterangan 
                                FROM presensi p 
                                JOIN siswa s ON p.nis = s.nis 
                                JOIN terlambat t ON p.nis = t.nis
                                WHERE p.keterangan_terlambat IS NOT NULL 
                                ORDER BY p.tanggal DESC";
                        $result = $conn->query($sql);
                        ?>

                        <?php if ($result->num_rows > 0): ?>
                        <form method="POST" action="index.php?page=admin#terlambat">
                            <input type="hidden" name="action" value="delete_selected_terlambat">
                            <button type="submit" class="btn-danger" style="margin-bottom: 10px;">
                                <i class="fas fa-trash"></i> Hapus Data Terpilih
                            </button>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAllTerlambat"></th>
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>NISN</th>
                                            <th>Nama</th>
                                            <th>Waktu Terlambat</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no=1; while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><input type="checkbox" class="terlambat-check" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo formatTanggalID($row['tanggal']); ?></td>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo $row['keterangan_terlambat']; ?></td>
                                                <td><?php echo $row['keterangan']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        <!-- TAMBAHKAN SCRIPT INI DI AKHIR TAB -->
                            <script>
                                document.getElementById('selectAllTerlambat').addEventListener('click', function() {
                                    const checkboxes = document.querySelectorAll('.terlambat-check');
                                    checkboxes.forEach(checkbox => {
                                        checkbox.checked = this.checked;
                                    });
                                });
                            </script>
                        <?php else: ?>
                        
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data keterlambatan</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="pengajuan" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-file-alt"></i> Pengajuan Izin Siswa</h3>
                 <!--EDIT RUBAH-->
				  <div class="presensi-info">
					    <div class="info-item">
						  <div class="info-value"><?php echo $total_ijin_siswa; ?></div>
						  <div class="info-label">Total Ijin Siswa Kelas</div>
					    </div>
					    <div class="info-item">
						  <div class="info-value"><?php echo $ijin_hari_ini; ?></div>
						  <div class="info-label">Ijin Hari Ini</div>
					    </div>
					    <div class="info-item">
						  <div class="info-value"><?php echo $sakit_hari_ini ?></div>
						  <div class="info-label">Sakit Hari Ini</div>
					    </div>
					</div>
				  </div>

                        <?php 
                        $sql = "SELECT a.*, s.nama FROM absensi_izin a JOIN siswa s ON a.nis = s.nis ORDER BY a.tanggal ASC";
                        $result = $conn->query($sql);
                        if ($result->num_rows > 0): ?>
                        <!-- TAMBAHKAN FORM INI DI BAWAH HEADER TAB -->
                        <form method="POST" action="index.php?page=admin#pengajuan">
                            <input type="hidden" name="action" value="delete_selected_izin">
                            <button type="submit" class="btn-danger" style="margin-bottom: 10px;">
                                <i class="fas fa-trash"></i> Hapus Data Terpilih
                            </button>
                        <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr><th><input type="checkbox" id="selectAllIzin"></th>
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>NISN</th>
                                            <th>Nama</th>
                                            <th>Jenis</th>
                                            <th>Keterangan</th>
                                            <th>Lampiran</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no=1; while ($row = $result->fetch_assoc()): ?>
                                            <tr><td><input type="checkbox" class="izin-check" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo formatTanggalID($row['tanggal']); ?></td>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo ucfirst($row['jenis']); ?></td>
                                                <td><?php echo substr($row['keterangan'], 0, 50) . (strlen($row['keterangan']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <?php if ($row['lampiran']): 
                                                        $ext = pathinfo($row['lampiran'], PATHINFO_EXTENSION);
                                                        $ext = strtolower($ext);
                                                    ?>
                                                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                            <img src="uploads/lampiran/<?php echo $row['lampiran']; ?>" 
                                                                 class="foto-presensi" 
                                                                 onclick="showImageModal('uploads/lampiran/<?php echo $row['lampiran']; ?>')">
                                                        <?php elseif ($ext === 'pdf'): ?>
                                                            <a href="uploads/lampiran/<?php echo $row['lampiran']; ?>" target="_blank" style="display: inline-block; padding: 5px 10px; background: #e74c3c; color: white; border-radius: 4px; text-decoration: none;">
                                                                <i class="fas fa-file-pdf"></i> Buka PDF
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="uploads/lampiran/<?php echo $row['lampiran']; ?>" target="_blank">Lihat Lampiran</a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['status'] == 'pending'): ?>
                                                        <span class="status-pending">Menunggu</span>
                                                    <?php elseif ($row['status'] == 'diterima'): ?>
                                                        <span class="status-diterima">Diterima</span>
                                                    <?php else: ?>
                                                        <span class="status-ditolak">Ditolak</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: flex; gap: 5px;">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <select name="status" style="padding: 5px; border-radius: 5px; font-size: 0.8rem;">
                                                            <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="diterima" <?php echo $row['status'] == 'diterima' ? 'selected' : ''; ?>>Diterima</option>
                                                            <option value="ditolak" <?php echo $row['status'] == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                                                        </select>
                                                        <button type="submit" name="update_status_izin" class="btn-icon btn-icon-edit" title="Update">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        <script>
                            document.getElementById('selectAllIzin').addEventListener('click', function() {
                                const checkboxes = document.querySelectorAll('.izin-check');
                                checkboxes.forEach(checkbox => {
                                    checkbox.checked = this.checked;
                                });
                            });
                         </script>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada pengajuan izin</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="pengaturan" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-cog"></i> Pengaturan Geolokasi</h3>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="latitude">Latitude Sekolah</label>
                                <input type="text" id="latitude" name="latitude" value="<?php echo $latSekolah; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude">Longitude Sekolah</label>
                                <input type="text" id="longitude" name="longitude" value="<?php echo $lngSekolah; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="radius">Radius (meter)</label>
                                <input type="number" id="radius" name="radius" value="<?php echo $radiusSekolah; ?>" required min="10">
                            </div>
                            
                             <!-- NEW TIME SETTINGS -->
                            <h4 style="margin: 25px 0 10px; color: #2c3e50;"><i class="fas fa-clock"></i> Waktu Presensi</h4>
                            <div class="form-group">
                                <label for="jam_masuk">Waktu Masuk</label>
                                <input type="time" id="jam_masuk" name="jamMasuk" value="<?php echo $jamMasuk; ?>" required>
                                <small>Waktu maksimal untuk presensi masuk</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="jam_pulang">Waktu Pulang</label>
                                <input type="time" id="jam_pulang" name="jamPulang" value="<?php echo $jamPulang; ?>" required>
                                <small>Waktu minimal untuk presensi pulang</small>
                            </div>
                            <!-- END NEW TIME SETTINGS -->
                            <button type="submit" name="save_pengaturan" class="btn-success">Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>

                <div id="libur" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-calendar-times"></i> Periode Non-Presensi (Libur)</h3>
                        <?php
                            $edit_mode = false;
                            $edit_data = null;

                            if (isset($_GET['action']) && $_GET['action'] == 'edit_periode_libur' && isset($_GET['id'])) {
                                $id_edit = $_GET['id'];
                                $sql_edit = "SELECT * FROM periode_libur WHERE id = $id_edit";
                                $result_edit = $conn->query($sql_edit);
                                if ($result_edit->num_rows > 0) {
                                    $edit_mode = true;
                                    $edit_data = $result_edit->fetch_assoc();
                                }
                            }
                            ?>                       
                            <form method="POST" action="index.php?page=admin#libur">
                                <?php if ($edit_mode): ?>
                                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="nama_periode">Nama Periode</label>
                                    <input type="text" id="nama_periode" name="nama_periode" 
                                        value="<?= $edit_mode ? $edit_data['nama_periode'] : '' ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tanggal_mulai">Tanggal Mulai</label>
                                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" 
                                        value="<?= $edit_mode ? $edit_data['tanggal_mulai'] : '' ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tanggal_selesai">Tanggal Selesai</label>
                                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" 
                                        value="<?= $edit_mode ? $edit_data['tanggal_selesai'] : '' ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="keterangan_libur">Keterangan</label>
                                    <textarea id="keterangan_libur" name="keterangan" rows="3"><?= $edit_mode ? $edit_data['keterangan'] : '' ?></textarea>
                                </div>
                                
                                <?php if ($edit_mode): ?>
                                    <button type="submit" name="update_periode_libur" class="btn-success">Update Periode</button>
                                <?php else: ?>
                                    <button type="submit" name="add_periode_libur" class="btn-success">Tambah Periode</button>
                                <?php endif; ?>

                            </form>
                        
                        <?php
                        // Proses update periode libur
                        if (isset($_POST['update_periode_libur'])) {
                            $id = $_POST['id'];
                            $nama_periode = $_POST['nama_periode'];
                            $tanggal_mulai = $_POST['tanggal_mulai'];
                            $tanggal_selesai = $_POST['tanggal_selesai'];
                            $keterangan = $_POST['keterangan'];

                            $sql = "UPDATE periode_libur SET 
                                    nama_periode = '$nama_periode',
                                    tanggal_mulai = '$tanggal_mulai',
                                    tanggal_selesai = '$tanggal_selesai',
                                    keterangan = '$keterangan'
                                    WHERE id = $id";

                            if ($conn->query($sql)) {
                                $_SESSION['success_admin'] = "Periode libur berhasil diperbarui!";
                            } else {
                                $_SESSION['error_admin'] = "Error: " . $conn->error;
                            }
                            header('Location: index.php?page=admin#libur');
                            exit();
                            
                        }


                        // Proses tambah periode libur
                        if (isset($_POST['add_periode_libur'])) {
                            $nama_periode = $_POST['nama_periode'];
                            $tanggal_mulai = $_POST['tanggal_mulai'];
                            $tanggal_selesai = $_POST['tanggal_selesai'];
                            $keterangan = $_POST['keterangan'];
                            
                            $sql = "INSERT INTO periode_libur (nama_periode, tanggal_mulai, tanggal_selesai, keterangan) 
                                    VALUES ('$nama_periode', '$tanggal_mulai', '$tanggal_selesai', '$keterangan')";
                            if ($conn->query($sql)) {
                                $success_admin = "Periode libur berhasil ditambahkan!";
                            } else {
                                $error_admin = "Error: " . $conn->error;
                            }
                        }
                        ?>
                        
                        <div style="margin-top: 30px;">
                            <h4 style="margin-bottom: 12px;">Daftar Periode Libur</h4>
                            <?php
                            $sql = "SELECT * FROM periode_libur ORDER BY tanggal_mulai DESC";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0): ?>
                            <form method="POST" action="index.php?page=admin#libur">
                                <input type="hidden" name="action" value="delete_selected_libur">
                                <button type="submit" class="btn-danger" style="margin-bottom: 10px;">
                                    <i class="fas fa-trash"></i> Hapus Data Terpilih
                                </button>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAllLibur"></th>
                                                <th>No</th>
                                                <th>Nama Periode</th>
                                                <th>Tanggal Mulai</th>
                                                <th>Tanggal Selesai</th>
                                                <th>Keterangan</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no=1; while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><input type="checkbox" class="libur-check" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= $row['nama_periode'] ?></td>
                                                    <td><?= formatTanggalID($row['tanggal_mulai']) ?></td>
                                                    <td><?= formatTanggalID($row['tanggal_selesai']) ?></td>
                                                    <td><?= $row['keterangan'] ?></td>
                                                    <td>
                                                        <a href="index.php?page=admin&action=edit_periode_libur&id=<?= $row['id'] ?>#libur" 
                                                        class="btn-icon btn-icon-edit" title="Edit" style="text-decoration: none; display: inline-block; width: 30px; height: 30px; background: #3498db; color: white; border-radius: 50%; text-align: center; line-height: 30px;">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn-icon btn-icon-delete" 
                                                                onclick="if(confirm('Hapus periode ini?')){ location.href='index.php?page=admin&action=delete_periode_libur&id=<?= $row['id'] ?>#libur'; }"
                                                                title="Hapus" style="border: none; width: 30px; height: 30px; background: #e74c3c; color: white; border-radius: 50%; text-align: center; line-height: 30px; cursor: pointer;">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                            <!-- TAMBAHKAN SCRIPT INI DI AKHIR TAB -->
                            <script>
                                document.getElementById('selectAllLibur').addEventListener('click', function() {
                                    const checkboxes = document.querySelectorAll('.libur-check');
                                    checkboxes.forEach(checkbox => {
                                        checkbox.checked = this.checked;
                                    });
                                });
                            </script>
                            <?php else: ?>
                                <p style="text-align: center; padding: 15px; color: #7f8c8d;">Belum ada periode libur</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div id="rekap" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-chart-bar"></i> Rekap Presensi Siswa</h3>
                        
                        <!-- Form Filter Tanggal -->
                        <form method="GET" style="margin-bottom: 20px;">
                            <input type="hidden" name="page" value="admin">
                            <div class="form-group">
                                <label for="start_date">Tanggal Awal</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); ?>">
				
                            </div>
                            <div class="form-group">
                                <label for="end_date">Tanggal Akhir</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" class="btn-success">Tampilkan</button>
                        </form>
                        
                        <?php
                        // Ambil rentang tanggal dari GET
                        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
                        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
                        
                        // Query rekap per siswa
                        $sql_per_siswa = "SELECT 
                            s.nis,
                            s.nama,
                            COUNT(p.id) AS hadir,
                            SUM(CASE WHEN p.status_masuk = 'tepat waktu' THEN 1 ELSE 0 END) AS tepat_waktu,
                            SUM(CASE WHEN p.status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                            (SELECT COUNT(*) FROM absensi_izin a 
                             WHERE a.nis = s.nis 
                             AND a.tanggal BETWEEN '$start_date' AND '$end_date'
                             AND a.status = 'diterima'
                             AND a.jenis = 'sakit') AS sakit,
                            (SELECT COUNT(*) FROM absensi_izin a 
                             WHERE a.nis = s.nis 
                             AND a.tanggal BETWEEN '$start_date' AND '$end_date'
                             AND a.status = 'diterima'
                             AND a.jenis = 'ijin') AS ijin,
                            DATEDIFF('$end_date', '$start_date') + 1 AS total_hari
                          FROM siswa s
                          LEFT JOIN presensi p ON s.nis = p.nis AND p.tanggal BETWEEN '$start_date' AND '$end_date'
                          GROUP BY s.nis, s.nama
                          ORDER BY s.nama";
                        
                        $result_per_siswa = $conn->query($sql_per_siswa);
                        ?>
                        
                        <h4 style="margin: 15px 0 10px;">Rekap Per Siswa</h4>
                        <?php if ($result_per_siswa->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>NISN</th>
                                            <th>Nama</th>
                                            <th>Hadir</th>
                                            <th>Tepat Waktu</th>
                                            <th>Terlambat</th>
                                            <th>Sakit</th>
                                            <th>Ijin</th>
                                            <th>Total Hari</th>
                                            <th>Kehadiran (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $no=1;
                                            while ($row = $result_per_siswa->fetch_assoc()): 
                                            $hadir = $row['hadir'];
                                            $sakit = $row['sakit'];
                                            $ijin = $row['ijin'];
                                            $total_hari = $row['total_hari'];
                                            $persen = $total_hari > 0 ? round(($hadir / $total_hari) * 100, 2) : 0;
                                        ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= $row['nis'] ?></td>
                                                <td><?= $row['nama'] ?></td>
                                                <td><?= $hadir ?></td>
                                                <td><?= $row['tepat_waktu'] ?></td>
                                                <td><?= $row['terlambat'] ?></td>
                                                <td><?= $sakit ?></td>
                                                <td><?= $ijin ?></td>
                                                <td><?= $total_hari ?></td>
                                                <td><?= $persen ?>%</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data siswa</p>
                        <?php endif; ?>
                        
                        <h4 style="margin: 25px 0 10px;">Rekap Harian Kelas</h4>
                        <?php
                        // Query rekap harian kelas
                        $sql_harian = "SELECT 
                            tanggal,
                            COUNT(DISTINCT nis) AS hadir,
                            SUM(CASE WHEN status_masuk = 'tepat waktu' THEN 1 ELSE 0 END) AS tepat_waktu,
                            SUM(CASE WHEN status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                            (SELECT COUNT(DISTINCT a.nis) FROM absensi_izin a 
                             WHERE a.tanggal = p.tanggal
                             AND a.status = 'diterima'
                             AND a.jenis = 'sakit') AS sakit,
                            (SELECT COUNT(DISTINCT a.nis) FROM absensi_izin a 
                             WHERE a.tanggal = p.tanggal
                             AND a.status = 'diterima'
                             AND a.jenis = 'ijin') AS ijin
                          FROM presensi p
                          WHERE tanggal BETWEEN '$start_date' AND '$end_date'
                          GROUP BY tanggal
                          ORDER BY tanggal DESC";
                        
                        $result_harian = $conn->query($sql_harian);
                        ?>
                        
                        <?php if ($result_harian->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Hadir</th>
                                            <th>Tidak Hadir</th> 
                                            <th>Tepat Waktu</th>
                                            <th>Terlambat</th>
                                            <th>Sakit</th>
                                            <th>Ijin</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Dapatkan jumlah total siswa
                                        $total_siswa = $conn->query("SELECT COUNT(*) as total FROM siswa")->fetch_assoc()['total'];
                                        while ($row = $result_harian->fetch_assoc()): 
                                                // Hitung siswa tidak hadir
                                                 $tidak_hadir = $total_siswa - ($row['hadir'] + $row['sakit'] + $row['ijin']);
                                        
                                        ?>
                                            <tr>
                                                <td><?= formatTanggalID($row['tanggal']) ?></td>
                                                <td><?= $row['hadir'] ?></td>
                                                <td><?= $tidak_hadir ?></td>
                                                <td><?= $row['tepat_waktu'] ?></td>
                                                <td><?= $row['terlambat'] ?></td>
                                                <td><?= $row['sakit'] ?></td>
                                                <td><?= $row['ijin'] ?></td>

                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data harian</p>
                        <?php endif; ?>
                        
                        <h4 style="margin: 25px 0 10px;">Rangking Kehadiran</h4>
                        <?php
                        // Gunakan query per siswa yang sudah dihitung persentasenya, lalu urutkan
                        $result_per_siswa->data_seek(0); // reset pointer
                        $ranking = [];
                        while ($row = $result_per_siswa->fetch_assoc()) {
                            $hadir = $row['hadir'];
                            $total_hari = $row['total_hari'];
                            $persen = $total_hari > 0 ? round(($hadir / $total_hari) * 100, 2) : 0;
                            $ranking[] = [
                                'nis' => $row['nis'],
                                'nama' => $row['nama'],
                                'persen' => $persen
                            ];
                        }
                        
                        // Urutkan berdasarkan persentase tertinggi
                        usort($ranking, function($a, $b) {
                            return $b['persen'] - $a['persen'];
                        });
                        ?>
                        
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Peringkat</th>
                                        <th>NISN</th>
                                        <th>Nama</th>
                                        <th>Kehadiran (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ranking as $index => $siswa): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= $siswa['nis'] ?></td>
                                            <td><?= $siswa['nama'] ?></td>
                                            <td><?= $siswa['persen'] ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal untuk CRUD Siswa -->
            <div id="crudModal" class="modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeModal()">&times;</span>
                    <div id="modalContent"></div>
                </div>
            </div>
            
            <!-- Modal Edit Presensi -->
            <div id="editPresensiModal" class="modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeEditPresensiModal()">&times;</span>
                    <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Data Presensi</h3>
                    <form method="POST" action="index.php?page=admin#presensi" id="editPresensiForm">
                        <input type="hidden" name="id" id="edit_presensi_id">
                        
                        <div class="form-group">
                            <label for="edit_tanggal">Tanggal</label>
                            <input type="date" id="edit_tanggal" name="tanggal" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_jam_masuk">Jam Masuk</label>
                            <input type="time" id="edit_jam_masuk" name="jam_masuk" step="1" onchange="calculateStatus()">
                            <input type="hidden" id="edit_status_masuk" name="status_masuk">
                            <input type="hidden" id="edit_keterangan_terlambat" name="keterangan_terlambat">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_jam_pulang">Jam Pulang</label>
                            <input type="time" id="edit_jam_pulang" name="jam_pulang" step="1" onchange="calculateStatus()">
                            <input type="hidden" id="edit_status_pulang" name="status_pulang">
                            <input type="hidden" id="edit_keterangan_pulang_cepat" name="keterangan_pulang_cepat">
                        </div>
                        
                        <div class="form-group">
                            <label>Status Masuk</label>
                            <div id="status_masuk_display" style="padding: 8px; background: #f0f0f0; border-radius: 4px;">
                                - Status akan dihitung otomatis -
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Status Pulang</label>
                            <div id="status_pulang_display" style="padding: 8px; background: #f0f0f0; border-radius: 4px;">
                                - Status akan dihitung otomatis -
                            </div>
                        </div>
                        
                        <button type="submit" name="update_presensi" class="btn-success">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
            <script>
// Fungsi untuk menghitung status berdasarkan jam
function calculateStatus() {
    // Ambil nilai jam masuk dan pulang
    const jamMasuk = document.getElementById('edit_jam_masuk').value;
    const jamPulang = document.getElementById('edit_jam_pulang').value;
    
    // Ambil pengaturan jam dari PHP
    const jamMasukStandar = "<?php echo $jamMasuk; ?>";
    const jamPulangStandar = "<?php echo $jamPulang; ?>";
    
    // Hitung status masuk
    let statusMasuk = 'tepat waktu';
    let keteranganMasuk = null;
    
    if (jamMasuk) {
        // Perbaikan: Hitung selisih waktu dengan fungsi PHP yang sudah diperbaiki
        const waktuMasuk = jamMasuk;
        const waktuStandarMasuk = jamMasukStandar;
        
        if (waktuMasuk > waktuStandarMasuk) {
            statusMasuk = 'terlambat';
            
            // Gunakan format yang benar untuk perhitungan
            const waktu1 = new Date(`2000-01-01T${waktuStandarMasup}`);
            const waktu2 = new Date(`2000-01-01T${waktuMasuk}`);
            const selisihDetik = (waktu2 - waktu1) / 1000;
            const selisihMenit = Math.round(selisihDetik / 60);
            
            if (selisihMenit >= 60) {
                const jam = Math.floor(selisihMenit / 60);
                const menit = selisihMenit % 60;
                keteranganMasuk = `${jam} Jam ${menit} Menit`;
            } else {
                keteranganMasuk = `${selisihMenit} Menit`;
            }
        }
    }
    
    // Hitung status pulang
    let statusPulang = 'tepat waktu';
    let keteranganPulang = null;
    
    if (jamPulang) {
        const waktuPulang = jamPulang;
        const waktuStandarPulang = jamPulangStandar;
        
        if (waktuPulang < waktuStandarPulang) {
            statusPulang = 'cepat';
            
            const waktu1 = new Date(`2000-01-01T${waktuPulang}`);
            const waktu2 = new Date(`2000-01-01T${waktuStandarPulang}`);
            const selisihDetik = (waktu2 - waktu1) / 1000;
            const selisihMenit = Math.round(selisihDetik / 60);
            
            if (selisihMenit >= 60) {
                const jam = Math.floor(selisihMenit / 60);
                const menit = selisihMenit % 60;
                keteranganPulang = `${jam} Jam ${menit} Menit`;
            } else {
                keteranganPulang = `${selisihMenit} Menit`;
            }
        }
    }
    
    // Set nilai hidden fields
    document.getElementById('edit_status_masuk').value = statusMasuk;
    document.getElementById('edit_keterangan_terlambat').value = keteranganMasuk || '';
    document.getElementById('edit_status_pulang').value = statusPulang;
    document.getElementById('edit_keterangan_pulang_cepat').value = keteranganPulang || '';
    
    // Update tampilan
    document.getElementById('status_masuk_display').innerHTML = 
        `<b>${statusMasuk.toUpperCase()}</b>` + 
        (keteranganMasuk ? `<br>${keteranganMasuk}` : '');
    
    document.getElementById('status_pulang_display').innerHTML = 
        `<b>${statusPulang.toUpperCase()}</b>` + 
        (keteranganPulang ? `<br>${keteranganPulang}` : '');
}

// Panggil fungsi saat modal dibuka
function openEditPresensiModal(id) {
    fetch('index.php?page=admin&action=get_presensi&id=' + id)
        .then(response => response.json())
        .then(data => {
            // Isi form
            document.getElementById('edit_presensi_id').value = data.id;
            document.getElementById('edit_tanggal').value = data.tanggal;
            document.getElementById('edit_jam_masuk').value = data.jam_masuk;
            document.getElementById('edit_jam_pulang').value = data.jam_pulang;
            
            // Hitung status awal
            calculateStatus();
            
            // Tampilkan modal
            document.getElementById('editPresensiModal').style.display = 'block';
        });
}
</script>
            <script>
                // Tab switching
                const tabs = document.querySelectorAll('.nav-tabs a');
                const tabContents = document.querySelectorAll('.tab-content');
                
                tabs.forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        e.preventDefault();
                        const target = tab.getAttribute('href').substring(1);
                        
                        // Remove active class from all tabs and contents
                        tabs.forEach(t => t.classList.remove('active'));
                        tabContents.forEach(tc => tc.classList.remove('active'));
                        
                        // Add active class to current tab and content
                        tab.classList.add('active');
                        document.getElementById(target).classList.add('active');
                        
                        // Update URL hash
                        window.location.hash = target;
                    });
                });
                
                // Check hash on page load
                if (window.location.hash) {
                    const targetTab = document.querySelector(`.nav-tabs a[href="${window.location.hash}"]`);
                    if (targetTab) {
                        tabs.forEach(t => t.classList.remove('active'));
                        tabContents.forEach(tc => tc.classList.remove('active'));
                        
                        targetTab.classList.add('active');
                        document.querySelector(targetTab.getAttribute('href')).classList.add('active');
                    }
                }
                
                // Modal functions
                function openModal(action, event, nis = '', nama = '') {
                    // Hentikan event jika ada
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    const modal = document.getElementById('crudModal');
                    const modalContent = document.getElementById('modalContent');
                    
                    if (action === 'add') {
                        modalContent.innerHTML = `
                            <h3 class="modal-title"><i class="fas fa-user-plus"></i> Tambah Siswa Baru</h3>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="add_nis">NISN</label>
                                    <input type="text" id="add_nis" name="nis" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="add_nama">Nama</label>
                                    <input type="text" id="add_nama" name="nama" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="add_password">Password</label>
                                    <input type="password" id="add_password" name="password" required>
                                </div>
                                                  
                                <div class="form-group">
                                    <label for="add_password_hint">Password Hint</label>
                                    <input type="text" id="add_password_hint" name="password_hint" required placeholder="Contoh: Tokoh favorit">
                                    <small>Pertanyaan/petunjuk untuk reset password</small>
                                </div>

                                
                                <button type="submit" name="add_siswa" class="btn-success">Simpan</button>
                            </form>
                        `;
                    } else if (action === 'edit') {
                        // AJAX untuk ambil data siswa
                        fetch(`index.php?page=admin&action=edit_siswa&nis=${nis}`)
                            .then(response => response.text())
                            .then(data => {
                                modalContent.innerHTML = `
                                    <div class="edit-form">
                                        <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Siswa</h3>
                                        ${data}
                                    </div>
                                `;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                modalContent.innerHTML = '<div class="error">Terjadi kesalahan saat mengambil data siswa</div>';
                            });
                    } else if (action === 'delete') {
                        modalContent.innerHTML = `
                            <div class="delete-confirm">
                                <h3 class="modal-title"><i class="fas fa-trash"></i> Hapus Siswa</h3>
                                <p>Apakah Anda yakin ingin menghapus siswa: <strong>${nama} (${nis})</strong>?</p>
                                <form method="POST">
                                    <input type="hidden" name="nis" value="${nis}">
                                    <div class="btn-group">
                                        <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                                        <button type="submit" name="delete_siswa" class="btn-danger">Hapus</button>
                                    </div>
                                </form>
                            </div>
                        `;
                    }
                    
                    modal.style.display = 'block';
                }
                
                function closeModal() {
                    document.getElementById('crudModal').style.display = 'none';
                }
                
                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('crudModal');
                    if (event.target == modal) {
                        closeModal();
                    }
                };

                // Fungsi untuk membuka modal edit presensi
                function openEditPresensiModal(id) {
                    fetch('index.php?page=admin&action=get_presensi&id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                alert(data.error);
                            } else {
                                // Isi form
                                document.getElementById('edit_presensi_id').value = data.id;
                                document.getElementById('edit_tanggal').value = data.tanggal;
                                document.getElementById('edit_jam_masuk').value = formatTimeForInput(data.jam_masuk);
                                document.getElementById('edit_jam_pulang').value = formatTimeForInput(data.jam_pulang);
                                // Tampilkan modal
                                document.getElementById('editPresensiModal').style.display = 'block';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Terjadi kesalahan saat mengambil data presensi');
                        });
                }

                // Fungsi untuk menutup modal edit presensi
                function closeEditPresensiModal() {
                    document.getElementById('editPresensiModal').style.display = 'none';
                }
                function formatTimeForInput(timeStr) {
                    if (!timeStr) return '';
                    const parts = timeStr.split(':');
                    if (parts.length < 2) return '';
                    
                    // Pastikan semua bagian memiliki 2 digit
                    return parts.map(part => part.padStart(2, '0')).join(':');
                    }


                    // Konfirmasi penghapusan massal untuk semua form
				// Perbaikan konfirmasi hapus massal
			/*	document.querySelector('form[action="index.php?page=admin#presensi"]').addEventListener('submit', function(e) {
				    const checked = this.querySelectorAll('input[type="checkbox"]:checked').length;
				    
				    if (checked === 0) {
					  alert('Pilih setidaknya satu data presensi untuk dihapus!');
					  e.preventDefault();
					  return;
				    }
				    
				    if (!confirm(`Anda yakin ingin menghapus ${checked} data presensi?`)) {
					  e.preventDefault();
				    } else {
					  // Tampilkan loading
					  document.getElementById('loading-overlay').style.display = 'flex';
				    }
				});
				*/

                    function disableSubmitButton() {
                        const btn = document.getElementById('btn-ajukan');
                        const loading = document.getElementById('loading-izin');
                        
                        // Nonaktifkan tombol dan tampilkan loading
                        btn.disabled = true;
                        loading.style.display = 'block';
                        
                        // Ganti teks tombol
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
                    }

                    // Handler untuk mencegah form resubmit saat refresh halaman
                    if (window.history.replaceState) {
                        window.history.replaceState(null, null, window.location.href);
                    }

                    // Modal untuk gambar popup
                    function showImageModal(src) {
                        const modal = document.getElementById('imageModal');
                        const modalImg = document.getElementById('modalImage');
                        modal.style.display = 'block';
                        modalImg.src = src;
                        
                        // Tambahkan efek zoom-in
                        modalImg.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            modalImg.style.transform = 'scale(1)';
                            modalImg.style.transition = 'transform 0.3s ease-out';
                        }, 10);
                    }

                    function closeImageModal() {
                        document.getElementById('imageModal').style.display = 'none';
                    }

                    // Tutup modal jika klik di luar gambar
                    window.addEventListener('click', function(event) {
                        const modal = document.getElementById('imageModal');
                        if (event.target === modal) {
                            closeImageModal();
                        }
                    });

			document.getElementById('deletePresensiForm').addEventListener('submit', function(e) {
			    const checked = this.querySelectorAll('input[type="checkbox"]:checked').length;
			    if (checked === 0) {
				  alert('Apakah anda yakin dengan data ini?');
				  e.preventDefault();
				  return;
			    }
			    if (!confirm(`Apakah Anda yakin ingin menghapus ${checked} data terpilih?`)) {
				  e.preventDefault();
			    }
			});
            </script>
        <?php endif; ?>
        
        <?php if ($page == 'admin' && $action == 'edit_siswa' && $nis_edit != ''): ?>
            <!-- Form Edit Siswa -->
            <div class="edit-form">
                <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Siswa</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="edit_nis">NISN</label>
                        <input type="text" id="edit_nis" name="nis" value="<?php echo $siswa_edit['nis']; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_nama">Nama</label>
                        <input type="text" id="edit_nama" name="nama" value="<?php echo $siswa_edit['nama']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">Password (Kosongkan jika tidak ingin mengubah)</label>
                        <input type="password" id="edit_password" name="password">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password_hint">Password Hint</label>
                        <input type="text" id="edit_password_hint" name="password_hint" value="<?php echo $siswa_edit['password_hint'] ?? ''; ?>" required>
                        <h6 style="color:grey;"><i>Di form edit siswa</h6>
                        <small>Pertanyaan/petunjuk untuk reset password</small>
                    </div>


                    <button type="submit" name="save_siswa" class="btn-success">Simpan Perubahan</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>© <?php echo date('Y'); ?> Sistem Presensi Kelas XITKJ-2  <br/> <h6>Man To Development & Maintenance</h6><h5>masihgurutkj@gmail.com</h5></p>
    </div>
    
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Modal untuk gambar popup -->
    <div id="imageModal" class="modal" style="display: none; z-index: 2000;">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
</body>
</html>
