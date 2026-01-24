<?php
ob_start();
date_default_timezone_set('Asia/Jakarta');
// --- FUNGSI PAGINATION ---
function showPagination($current_page, $total_records, $per_page, $base_url) {
    $total_pages = ceil($total_records / $per_page);
    if ($total_pages <= 1) return '';
    $output = '<div style="margin-top: 15px; text-align: center;">';
    if ($current_page > 1) {
        $output .= '<a href="'.$base_url.'&pg='.($current_page-1).'" class="btn btn-sm btn-secondary">Prev</a> ';
    }
    for ($i=1; $i <= $total_pages; $i++) {
        if ($i == $current_page) $output .= '<span class="btn btn-primary btn-sm" style="cursor:default;">'.$i.'</span> ';
        else $output .= '<a href="'.$base_url.'&pg='.$i.'" class="btn btn-secondary btn-sm">'.$i.'</a> ';
    }
    if ($current_page < $total_pages) {
        $output .= '<a href="'.$base_url.'&pg='.($current_page+1).'" class="btn btn-sm btn-secondary">Next</a> ';
    }
    $output .= '</div>';
    return $output;
}

// --- KONFIGURASI SESI & KEAMANAN ---
$session_name = 'epkl_session';
$secure = false;
$httponly = true;
$session_timeout = 900;
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $session_timeout,
    'path' => $cookieParams["path"],
    'domain' => $cookieParams["domain"],
    'secure' => $secure,
    'httponly' => $httponly,
    'samesite' => 'Lax'
]);
session_name($session_name);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout)) {
        session_unset();
        session_destroy();
        $_SESSION = array();
        if (!isset($_GET['session_expired'])) {
            header("Location: index.php?page=login&session_expired=1");
            exit();
        }
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

// --- KONEKSI DATABASE ---
$host = "_SERVER_";
$user = "_USER_";
$pass = "_PASSWORD_";
$db = "_DATABASE_";
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);
$result = $conn->query("SHOW DATABASES LIKE '$db'");
if ($result->num_rows == 0) {
    $conn->query("CREATE DATABASE IF NOT EXISTS $db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($db);

    // --- STRUKTUR TABEL ---
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','guru','industri') NOT NULL,
            nama_lengkap VARCHAR(100) NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            kelas_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "kelas" => "CREATE TABLE IF NOT EXISTS kelas (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_kelas VARCHAR(50) NOT NULL,
            jurusan VARCHAR(50) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "kelompok_pkl" => "CREATE TABLE IF NOT EXISTS kelompok_pkl (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_kelompok VARCHAR(100) NOT NULL,
            nama_industri VARCHAR(100) NOT NULL,
            alamat_industri TEXT DEFAULT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            radius INT(11) NOT NULL DEFAULT 100,
            id_guru_pembimbing INT(11) NOT NULL,
            id_industri_pembimbing INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (id_guru_pembimbing) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (id_industri_pembimbing) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "presensi" => "CREATE TABLE IF NOT EXISTS presensi (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nis VARCHAR(20) NOT NULL,
            tanggal DATE NOT NULL,
            jam_masuk TIME DEFAULT NULL,
            jam_pulang TIME DEFAULT NULL,
            foto_masuk VARCHAR(255) DEFAULT NULL,
            foto_pulang VARCHAR(255) DEFAULT NULL,
            status_masuk ENUM('tepat waktu','terlambat') DEFAULT 'tepat waktu',
            keterangan_terlambat TEXT DEFAULT NULL,
            lokasi_masuk VARCHAR(50) DEFAULT NULL,
            status_pulang ENUM('tepat waktu','cepat','belum presensi') DEFAULT 'tepat waktu',
            keterangan_pulang_cepat TEXT DEFAULT NULL,
            lokasi_pulang VARCHAR(50) DEFAULT NULL,
            status_approval ENUM('pending','approved','rejected') DEFAULT 'pending',
            catatan_pembimbing TEXT DEFAULT NULL,
            id_pembimbing_approver INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY nis (nis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "siswa" => "CREATE TABLE IF NOT EXISTS siswa (
            nis VARCHAR(20) NOT NULL,
            nama VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            password_hint VARCHAR(255) DEFAULT NULL,
            kelas_id INT(11) DEFAULT NULL,
            kelompok_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (nis),
            KEY kelompok_id (kelompok_id),
            KEY kelas_id (kelas_id),
            FOREIGN KEY (kelompok_id) REFERENCES kelompok_pkl(id) ON DELETE SET NULL,
            FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "absensi_izin" => "CREATE TABLE IF NOT EXISTS absensi_izin (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nis VARCHAR(20) NOT NULL,
            tanggal DATE NOT NULL,
            jenis ENUM('sakit','ijin') NOT NULL,
            keterangan TEXT DEFAULT NULL,
            lampiran VARCHAR(255) DEFAULT NULL,
            status ENUM('pending','diterima','ditolak') DEFAULT 'pending',
            catatan_pembimbing TEXT DEFAULT NULL,
            id_pembimbing_approver INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "pengaturan" => "CREATE TABLE IF NOT EXISTS pengaturan (
            id INT(11) NOT NULL AUTO_INCREMENT,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            radius INT(11) NOT NULL,
            waktu_masuk TIME NOT NULL DEFAULT '07:30:00',
            waktu_pulang TIME NOT NULL DEFAULT '15:30:00',
            logo_sekolah VARCHAR(255) DEFAULT NULL,
            nama_sekolah VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "periode_libur" => "CREATE TABLE IF NOT EXISTS periode_libur (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_periode VARCHAR(100) NOT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            keterangan TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($tables as $tableName => $sql) {
        if (!$conn->query($sql)) die("Gagal tabel $tableName: " . $conn->error);
    }

    // --- SEED DATA AWAL ---
    $pass_hash = '$2y$10$TaIHxsQzHlzRuJdQU9k6Mu44ZhFQjpWOj6SIm12vygyaQfhF8jenu';
    // 1. Seed Kelas
    $conn->query("INSERT INTO kelas (nama_kelas, jurusan) VALUES
        ('XI TKJ 1', 'Teknik Komputer Jaringan'),
        ('XI RPL 1', 'Rekayasa Perangkat Lunak'),
        ('XII TKJ 2', 'Teknik Komputer Jaringan')
        ON DUPLICATE KEY UPDATE nama_kelas=nama_kelas");

    $kelas_id_1 = $conn->query("SELECT id FROM kelas WHERE nama_kelas='XI TKJ 1'")->fetch_assoc()['id'];

    // 2. Seed User
    $conn->query("INSERT INTO users (username, password, role, nama_lengkap, kelas_id) VALUES
        ('admin', '$pass_hash', 'admin', 'Super Admin', NULL)
        ON DUPLICATE KEY UPDATE username=username");

    $conn->query("INSERT INTO users (username, password, role, nama_lengkap, kelas_id) VALUES
        ('guru1', '$pass_hash', 'guru', 'Bapak Guru Pembimbing', $kelas_id_1)
        ON DUPLICATE KEY UPDATE username=username");

    $conn->query("INSERT INTO users (username, password, role, nama_lengkap) VALUES
        ('ind1', '$pass_hash', 'industri', 'Ibu Pembimbing Industri')
        ON DUPLICATE KEY UPDATE username=username");

    $guru_id = $conn->query("SELECT id FROM users WHERE username='guru1'")->fetch_assoc()['id'];
    $ind_id = $conn->query("SELECT id FROM users WHERE username='ind1'")->fetch_assoc()['id'];

    $conn->query("INSERT INTO kelompok_pkl (nama_kelompok, nama_industri, latitude, longitude, radius, id_guru_pembimbing, id_industri_pembimbing) VALUES
        ('Kelompok A', 'PT Teknologi Maju', -6.2088, 106.8456, 200, $guru_id, $ind_id)
        ON DUPLICATE KEY UPDATE nama_kelompok=nama_kelompok");

    $kelompok_id = $conn->query("SELECT id FROM kelompok_pkl LIMIT 1")->fetch_assoc()['id'];

    $conn->query("INSERT INTO siswa (nis, nama, password, kelas_id, kelompok_id) VALUES
        ('123', 'Siswa PKL Demo', '$pass_hash', $kelas_id_1, $kelompok_id)
        ON DUPLICATE KEY UPDATE nama=nama");

    $conn->query("INSERT INTO pengaturan (latitude, longitude, radius, waktu_masuk, waktu_pulang, nama_sekolah) VALUES
        (-6.084264, 106.191407, 100, '08:00:00', '16:00:00', 'SMK Bisa')");

    echo "Setup Database V5.7 Berhasil!";
} else {
    $conn->select_db($db);

    // Alters (Run once if needed)
    $conn->query("ALTER TABLE presensi ADD COLUMN IF NOT EXISTS status_approval ENUM('pending','approved','rejected') DEFAULT 'pending'");
    $conn->query("ALTER TABLE presensi ADD COLUMN IF NOT EXISTS catatan_pembimbing TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE presensi ADD COLUMN IF NOT EXISTS id_pembimbing_approver INT(11) DEFAULT NULL");
    $conn->query("ALTER TABLE absensi_izin ADD COLUMN IF NOT EXISTS catatan_pembimbing TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE absensi_izin ADD COLUMN IF NOT EXISTS id_pembimbing_approver INT(11) DEFAULT NULL");
    $conn->query("ALTER TABLE siswa ADD COLUMN IF NOT EXISTS kelompok_id INT(11) DEFAULT NULL");

    // Check FKs
    $check_fk = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'siswa' AND CONSTRAINT_NAME = 'siswa_ibfk_2'");
    if ($check_fk->num_rows == 0) {
        $conn->query("ALTER TABLE siswa ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL");
    }
    $check_fk_user = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'users_ibfk_1'");
    if ($check_fk_user->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL");
    }
}

// --- HELPER FUNCTION ---
function formatTanggalID($tgl) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $date = date_create($tgl);
    $namaHari  = $hari[(int)date_format($date, 'w')];
    $tanggal   = date_format($date, 'j');
    $namaBulan = $bulan[(int)date_format($date, 'n')];
    $tahun     = date_format($date, 'Y');
    return "$namaHari, $tanggal $namaBulan $tahun";
}

function hitungJarak($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    return ($dist * 60 * 1.1515 * 1609.344);
}

function compressImage($source, $destination, $quality) {
    if (!file_exists($source)) return false;
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, round(9 * $quality / 100));
    } else { return false; }
    return true;
}

// --- AJAX HANDLER ---
if (isset($_GET['action_ajax']) && $_GET['action_ajax'] == 'get_siswa_kelompok' && isset($_GET['kel_id'])) {
    $kel_id = intval($_GET['kel_id']);
    $q_non = $conn->query("SELECT s.*, kp.nama_kelompok as current_group, k.nama_kelas, k.jurusan
                           FROM siswa s LEFT JOIN kelompok_pkl kp ON s.kelompok_id = kp.id
                           LEFT JOIN kelas k ON s.kelas_id = k.id
                           WHERE (s.kelompok_id IS NULL OR s.kelompok_id != $kel_id)
                           ORDER BY s.kelas_id, s.nama");
    $non_members = [];
    while($r = $q_non->fetch_assoc()) $non_members[] = $r;

    $q_mem = $conn->query("SELECT s.*, k.nama_kelas, k.jurusan FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.kelompok_id = $kel_id ORDER BY s.kelas_id, s.nama");
    $members = [];
    while($r = $q_mem->fetch_assoc()) $members[] = $r;

    header('Content-Type: application/json');
    echo json_encode(['non_members' => $non_members, 'members' => $members]);
    exit();
}

if (isset($_POST['action_ajax']) && $_POST['action_ajax'] == 'update_siswa_kelompok') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { echo json_encode(['status' => 'error']); exit(); }
    $target_kel_id = intval($_POST['target_kel_id']);
    $nis_list = isset($_POST['nis']) ? $_POST['nis'] : [];
    $moved = 0;
    if (!empty($nis_list)) {
        foreach ($nis_list as $nis) {
            $nis_clean = $conn->real_escape_string($nis);
            $val = ($target_kel_id == 0) ? "NULL" : $target_kel_id;
            $sql = "UPDATE siswa SET kelompok_id = $val WHERE nis = '$nis_clean'";
            if ($conn->query($sql)) $moved++;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'moved' => $moved]);
    exit();
}

// --- BACKEND LOGIC ---
// 1. CRUD KELAS
if (isset($_POST['action_kelas'])) {
    $nama = $conn->real_escape_string($_POST['nama_kelas']);
    $jurusan = $conn->real_escape_string($_POST['jurusan']);
    if ($_POST['action_kelas'] == 'add') {
        $sql = "INSERT INTO kelas (nama_kelas, jurusan) VALUES ('$nama', '$jurusan')";
        if ($conn->query($sql)) $crud_msg = "Kelas berhasil ditambahkan!"; else $crud_error = $conn->error;
    } elseif ($_POST['action_kelas'] == 'update') {
        $id = $_POST['id_kelas'];
        $sql = "UPDATE kelas SET nama_kelas='$nama', jurusan='$jurusan' WHERE id=$id";
        if ($conn->query($sql)) $crud_msg = "Kelas berhasil diupdate!"; else $crud_error = $conn->error;
    }
}
if (isset($_GET['delete_kelas'])) { $conn->query("DELETE FROM kelas WHERE id=".intval($_GET['delete_kelas'])); $crud_msg = "Kelas dihapus!"; }

// 2. BULK DELETE
if (isset($_POST['action_bulk_delete'])) {
    $table = $_POST['table_name']; $ids = $_POST['selected_ids'];
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') $error = "Akses Ditolak!";
    else {
        if ($table == 'siswa') {
            $ids_clean_nis = implode("','", array_map(function($val) use($conn){ return "'".$conn->real_escape_string($val)."'"; }, $ids));
            $sql = "DELETE FROM siswa WHERE nis IN ($ids_clean_nis)";
            if ($conn->query($sql)) $crud_msg = "Data dihapus!"; else $crud_error = $conn->error;
        } elseif ($table == 'users' || $table == 'kelas') {
            $ids_clean = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM $table WHERE id IN ($ids_clean)";
            if ($conn->query($sql)) $crud_msg = "Data dihapus!"; else $crud_error = $conn->error;
        } elseif ($table == 'kelompok_pkl') {
            $ids_clean = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM kelompok_pkl WHERE id IN ($ids_clean)";
            if ($conn->query($sql)) $crud_msg = "Kelompok dihapus!"; else $crud_error = $conn->error;
        }
    }
}

// 3. CRUD KELOMPOK
if (isset($_POST['action_kelompok'])) {
    if ($_POST['action_kelompok'] == 'add') {
        $nama = $conn->real_escape_string($_POST['nama_kelompok']); $industri = $conn->real_escape_string($_POST['nama_industri']);
        $lat = $conn->real_escape_string($_POST['latitude']); $lng = $conn->real_escape_string($_POST['longitude']);
        $rad = $_POST['radius']; $gid = $_POST['id_guru']; $iid = $_POST['id_industri'];
        $sql = "INSERT INTO kelompok_pkl (nama_kelompok, nama_industri, latitude, longitude, radius, id_guru_pembimbing, id_industri_pembimbing) VALUES ('$nama', '$industri', '$lat', '$lng', $rad, $gid, $iid)";
        if ($conn->query($sql)) $crud_msg = "Kelompok ditambahkan!";
    } elseif ($_POST['action_kelompok'] == 'update') {
        $id = $_POST['id_kel']; $nama = $conn->real_escape_string($_POST['nama_kelompok']); $industri = $conn->real_escape_string($_POST['nama_industri']);
        $lat = $conn->real_escape_string($_POST['latitude']); $lng = $conn->real_escape_string($_POST['longitude']);
        $rad = $_POST['radius']; $gid = $_POST['id_guru']; $iid = $_POST['id_industri'];
        $sql = "UPDATE kelompok_pkl SET nama_kelompok='$nama', nama_industri='$industri', latitude='$lat', longitude='$lng', radius=$rad, id_guru_pembimbing=$gid, id_industri_pembimbing=$iid WHERE id=$id";
        if ($conn->query($sql)) $crud_msg = "Kelompok diupdate!";
    }
}
if (isset($_GET['delete_kelompok'])) { $conn->query("DELETE FROM kelompok_pkl WHERE id=".intval($_GET['delete_kelompok'])); $crud_msg = "Kelompok dihapus!"; }

// 4. CRUD USERS
if (isset($_POST['action_user'])) {
    $username = $conn->real_escape_string($_POST['username']); $nama = $conn->real_escape_string($_POST['nama_lengkap']);
    $role = $_POST['role']; $kelas_id = !empty($_POST['kelas_id']) ? $_POST['kelas_id'] : 'NULL';
    $pass = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
    if ($_POST['action_user'] == 'add') {
        if (empty($pass)) $crud_error = "Password wajib!"; else {
            $sql = "INSERT INTO users (username, password, role, nama_lengkap, kelas_id) VALUES ('$username', '$pass', '$role', '$nama', $kelas_id)";
            if ($conn->query($sql)) $crud_msg = "User ditambahkan!"; else $crud_error = $conn->error;
        }
    } elseif ($_POST['action_user'] == 'update') {
        $id = $_POST['id_user'];
        $sql = "UPDATE users SET username='$username', nama_lengkap='$nama', role='$role', kelas_id=$kelas_id";
        if (!empty($pass)) $sql .= ", password='$pass'"; $sql .= " WHERE id=$id";
        if ($conn->query($sql)) $crud_msg = "User diupdate!"; else $crud_error = $conn->error;
    }
}
if (isset($_GET['delete_user'])) { $conn->query("DELETE FROM users WHERE id=".intval($_GET['delete_user'])); $crud_msg = "User dihapus!"; }

// 5. CRUD SISWA
if (isset($_POST['action_siswa'])) {
    $nis = $conn->real_escape_string($_POST['nis']); $nama = $conn->real_escape_string($_POST['nama']);
    $kelas_id = !empty($_POST['kelas_id']) ? $_POST['kelas_id'] : 'NULL';
    $pass = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
    if ($_POST['action_siswa'] == 'add') {
        if (empty($pass)) $crud_error = "Password wajib!"; else {
            $sql = "INSERT INTO siswa (nis, nama, password, kelas_id) VALUES ('$nis', '$nama', '$pass', $kelas_id)";
            if ($conn->query($sql)) $crud_msg = "Siswa ditambahkan!"; else $crud_error = $conn->error;
        }
    } elseif ($_POST['action_siswa'] == 'update') {
        $old_nis = $_POST['old_nis'];
        $sql = "UPDATE siswa SET nis='$nis', nama='$nama', kelas_id=$kelas_id";
        if (!empty($pass)) $sql .= ", password='$pass'"; $sql .= " WHERE nis='$old_nis'";
        if ($conn->query($sql)) {
            if(isset($_SESSION['nis']) && $_SESSION['nis'] == $old_nis) $_SESSION['nis'] = $nis;
            $crud_msg = "Siswa diupdate!";
        } else $crud_error = $conn->error;
    }
}
if (isset($_GET['delete_siswa'])) { $conn->query("DELETE FROM siswa WHERE nis='".$conn->real_escape_string($_GET['delete_siswa'])."'"); $crud_msg = "Siswa dihapus!"; }

// 6. APPROVAL PEMBIMBING
if (isset($_POST['action_approve']) && isset($_SESSION['role']) && ($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'industri')) {
    $type = $_POST['type']; $id = $_POST['id']; $status = $_POST['status']; $catatan = $conn->real_escape_string($_POST['catatan']); $approver_id = $_SESSION['user_id'];
    if ($type == 'presensi') $sql = "UPDATE presensi SET status_approval='$status', catatan_pembimbing='$catatan', id_pembimbing_approver=$approver_id WHERE id=$id";
    else $sql = "UPDATE absensi_izin SET status='$status', catatan_pembimbing='$catatan', id_pembimbing_approver=$approver_id WHERE id=$id";
    if ($conn->query($sql)) $success_mentor = "Data diproses!"; else $error_mentor = "Gagal: " . $conn->error;
}

// 7. LOGIC PRESENSI SISWA
if (isset($_POST['presensi']) && isset($_SESSION['nis'])) {
    $nis = $_SESSION['nis']; $jenis = $_POST['jenis_presensi'];
    $latitude = $_POST['latitude']; $longitude = $_POST['longitude']; $tanggal = date('Y-m-d');

    $pengaturan = $conn->query("SELECT * FROM pengaturan LIMIT 1")->fetch_assoc();
    $latSekolah = $pengaturan['latitude']; $lngSekolah = $pengaturan['longitude'];
    $radiusSekolah = $pengaturan['radius']; $jamMasuk = $pengaturan['waktu_masuk']; $jamPulang = $pengaturan['waktu_pulang'];

    $user_location = ['lat' => $latSekolah, 'lng' => $lngSekolah, 'radius' => $radiusSekolah, 'location_name' => 'Sekolah'];
    $siswa_data = $conn->query("SELECT s.*, k.nama_kelompok, k.latitude as lat_kelompok, k.longitude as lng_kelompok, k.radius as rad_kelompok
                                FROM siswa s LEFT JOIN kelompok_pkl k ON s.kelompok_id = k.id WHERE s.nis = '$nis'")->fetch_assoc();

    if ($siswa_data && !empty($siswa_data['kelompok_id'])) {
        $user_location['lat'] = $siswa_data['lat_kelompok']; $user_location['lng'] = $siswa_data['lng_kelompok'];
        $user_location['radius'] = $siswa_data['rad_kelompok']; $user_location['location_name'] = "Industri: " . $siswa_data['nama_kelompok'];
    }

    $jarak = hitungJarak($user_location['lat'], $user_location['lng'], $latitude, $longitude);

    if ($jarak > $user_location['radius']) {
        $error = "Di luar area {$user_location['location_name']}! (".round($jarak)." Meter)";
    } else {
        $cek_sql = "SELECT * FROM presensi WHERE nis = '$nis' AND tanggal = '$tanggal'";
        $cek_result = $conn->query($cek_sql);
        $row_presensi = $cek_result->fetch_assoc();

        if ($jenis == 'masuk') {
            if ($row_presensi && $row_presensi['jam_masuk']) { $error = "Sudah presensi masuk!"; }
            else {
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    $namaFile = "foto-masuk-$nis-" . date('H.i-d.m.Y') . ".jpg";
                    $targetDir = __DIR__ . '/uploads/foto/masuk';
                    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
                    if (move_uploaded_file($foto['tmp_name'], $targetDir . '/' . $namaFile)) {
                        compressImage($targetDir . '/' . $namaFile, $targetDir . '/' . $namaFile, 60);
                        $waktu = date('H:i:s');
                        $status_masuk = ($waktu > $jamMasuk) ? 'terlambat' : 'tepat waktu';
                        $keterangan_terlambat = ($status_masuk == 'terlambat') ? "Terlambat dari " . $jamMasuk : NULL;
                        $lokasi = "$latitude,$longitude";
                        $insert_sql = "INSERT INTO presensi (nis, tanggal, jam_masuk, foto_masuk, status_masuk, keterangan_terlambat, lokasi_masuk)
                                       VALUES ('$nis', '$tanggal', '$waktu', '$namaFile', '$status_masuk', " . ($keterangan_terlambat ? "'$keterangan_terlambat'" : "NULL") . ", '$lokasi')";
                        if ($conn->query($insert_sql)) $success = "Presensi Masuk Berhasil!"; else $error = "Gagal: " . $conn->error;
                    } else { $error = "Gagal upload foto"; }
                } else { $error = "Foto wajib diambil!"; }
            }
        } else { // Pulang
            if (!$row_presensi || !$row_presensi['jam_masuk']) { $error = "Belum presensi masuk!"; }
            else if ($row_presensi['jam_pulang']) { $error = "Sudah presensi pulang!"; }
            else {
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    $namaFile = "foto-pulang-$nis-" . date('H.i-d.m.Y') . ".jpg";
                    $targetDir = __DIR__ . '/uploads/foto/pulang';
                    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
                    if (move_uploaded_file($foto['tmp_name'], $targetDir . '/' . $namaFile)) {
                        compressImage($targetDir . '/' . $namaFile, $targetDir . '/' . $namaFile, 60);
                        $waktu = date('H:i:s');
                        $status_pulang = ($waktu < $jamPulang) ? 'cepat' : 'tepat waktu';
                        $keterangan_pulang_cepat = ($status_pulang == 'cepat') ? "Pulang sebelum " . $jamPulang : NULL;
                        $lokasi = "$latitude,$longitude";
                        $update_sql = "UPDATE presensi SET jam_pulang='$waktu', foto_pulang='$namaFile', status_pulang='$status_pulang', keterangan_pulang_cepat=" . ($keterangan_pulang_cepat ? "'$keterangan_pulang_cepat'" : "NULL") . ", lokasi_pulang='$lokasi' WHERE nis='$nis' AND tanggal='$tanggal'";
                        if ($conn->query($update_sql)) $success = "Presensi Pulang Berhasil!"; else $error = "Gagal: " . $conn->error;
                    }
                } else { $error = "Foto wajib diambil!"; }
            }
        }
    }
}

// 8. IZIN SISWA
if (isset($_POST['ajukan_izin']) && isset($_SESSION['nis'])) {
    $nis = $_SESSION['nis']; $tanggal = $_POST['tanggal']; $jenis = $_POST['jenis']; $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $cek = $conn->query("SELECT id FROM absensi_izin WHERE nis='$nis' AND tanggal='$tanggal'");
    if ($cek->num_rows > 0) $error_izin = "Sudah mengajukan izin tanggal ini!";
    else {
        $sql = "INSERT INTO absensi_izin (nis, tanggal, jenis, keterangan) VALUES ('$nis', '$tanggal', '$jenis', '$keterangan')";
        if ($conn->query($sql)) { $_SESSION['success_izin'] = "Pengajuan izin terkirim!"; header("Location: index.php?page=izin"); exit(); }
        else { $error_izin = "Gagal: " . $conn->error; }
    }
}

// 9. LOGIN
if (isset($_POST['login'])) {
    $identifier = $_POST['identifier']; $password = $_POST['password'];
    $sql = "SELECT * FROM siswa WHERE nis = '$identifier'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['nis'] = $identifier; $_SESSION['nama'] = $row['nama']; $_SESSION['role'] = 'siswa';
            header('Location: index.php?page=menu'); exit();
        } else { $error = "Password salah!"; }
    } else {
        $sql = "SELECT * FROM users WHERE username = '$identifier'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id']; $_SESSION['nama'] = $row['nama_lengkap']; $_SESSION['role'] = $row['role']; $_SESSION['kelas_id'] = $row['kelas_id'];
                if ($row['role'] == 'admin') header('Location: index.php?page=admin'); else header('Location: index.php?page=dashboard_pembimbing'); exit();
            } else { $error = "Password salah!"; }
        } else { $error = "Username/NIS atau password salah!"; }
    }
}

if ($page == 'logout') { session_unset(); session_destroy(); header('Location: index.php?page=login'); exit(); }
$page = isset($_GET['page']) ? $_GET['page'] : 'login';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistem Presensi PKL V5.7</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
:root { --primary: #007bff; --secondary: #6c757d; --success: #28a745; --danger: #dc3545; --warning: #ffc107; --light: #f8f9fa; --dark: #343a40; --bg-app: #f4f6f9; }
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
body { background-color: var(--bg-app); color: #333; padding-bottom: 0; }
.container { max-width: 100%; margin: 0 auto; padding: 15px; padding-bottom: 90px; }
.card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; }
.header { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 15px; border-radius: 0 0 15px 15px; margin-bottom: 20px; text-align: center; position: sticky; top: 0; z-index: 100; }
/* BUTTONS */
.btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-block; font-size: 14px; }
.btn-sm { padding: 5px 8px; font-size: 12px; border-radius: 4px; } /* Icon size buttons */
.btn:disabled { background: #ccc; cursor: not-allowed; opacity: 0.7; }
.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-warning { background: var(--warning); color: #212529; }
.btn-secondary { background: var(--secondary); color: white; }
.btn:hover { opacity: 0.9; }
.form-group { margin-bottom: 12px; }
.form-control { width: 100%; padding: 8px; border:1px solid #ddd; border-radius: 5px; font-size: 14px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
th, td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
th { background: #f8f9fa; font-weight: 600; white-space: nowrap; }
.bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: white; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 8px 0; z-index: 9999; border-top: 1px solid #eee; height: 60px; }
.nav-item { text-align: center; color: #999; text-decoration: none; font-size: 11px; display: flex; flex-direction: column; align-items: center; flex:1; }
.nav-item i { font-size: 18px; margin-bottom: 2px; }
.nav-item.active { color: var(--primary); }
/* MODIFIKASI UNTUK MENU HAMBURGER */
.tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 15px; overflow-x: auto; }
.tab { padding: 8px 15px; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 600; color: #666; white-space: nowrap; font-size: 13px; }
.tab.active { border-bottom-color: var(--primary); color: var(--primary); }
/* Dropdown untuk Master */
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; background-color: #f9f9f9; min-width: 160px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1; border-radius: 5px; }
.dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; }
.dropdown-content a:hover { background-color: #f1f1f1; }
.dropdown:hover .dropdown-content { display: block; }
.tab:hover { background-color: #f1f1f1; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
.modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 10px; position: relative; }
.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
/* KAMERA PRESENSI */
.camera-wrapper { position: relative; width: 100%; max-width: 400px; margin: 0 auto; background: #000; border-radius: 12px; overflow: hidden; aspect-ratio: 4/3; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
#video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
.capture-btn-wrapper { position: absolute; bottom: 20px; left: 0; right: 0; display: flex; justify-content: center; align-items: center; z-index: 10; }
.capture-btn { width: 70px; height: 70px; border-radius: 50%; background: rgba(255,255,255,0.9); border: 4px solid rgba(255,255,255,0.5); display: flex; justify-content: center; align-items: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
.capture-btn:active { transform: scale(0.9); background: #fff; }
.capture-btn i { font-size: 28px; color: #333; }
.capture-btn.captured { background: #28a745; border-color: #fff; }
.capture-btn.captured i { color: white; }
/* ALOKASI TAB */
.alokasi-container { display: flex; flex-direction: column; gap: 15px; }
.alokasi-controls { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #ddd; }
.siswa-dual-list-container { display: flex; gap: 15px; margin-top: 10px; align-items: flex-start; }
.siswa-col { flex: 1; display: flex; flex-direction: column; }
.siswa-box { border: 1px solid #ddd; border-radius: 5px; background: #fff; max-height: 400px; overflow-y: auto; box-shadow: inset 0 0 5px rgba(0,0,0,0.05); }
.siswa-header { background: #e9ecef; padding: 10px; font-weight: bold; border-bottom: 1px solid #ddd; font-size: 13px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 2; }
.siswa-header .count-badge { background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
.siswa-list { list-style: none; margin: 0; padding: 0; }
.siswa-list li { padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; cursor: pointer; transition: background 0.2s; font-size: 13px; }
.siswa-list li:hover { background-color: #f1f3f5; }
.siswa-list li .info { flex: 1; margin-left: 10px; }
.siswa-list li .info b { display: block; font-size: 13px; color: #333; }
.siswa-list li .info small { display: block; color: #666; font-size: 11px; }
.siswa-list li input[type="checkbox"] { width: 16px; height: 16px; }
.siswa-actions { display: flex; flex-direction: column; gap: 10px; justify-content: center; padding-top: 20px; min-width: 50px; }
.siswa-search { padding: 10px; border-bottom: 1px solid #eee; background: #fff; position: sticky; top: 0; z-index: 1; }
.siswa-search input { width: 100%; padding: 6px 10px; font-size: 12px; border: 1px solid #ccc; border-radius: 15px; }
@media(max-width: 768px) {
.siswa-dual-list-container { flex-direction: column; }
.siswa-actions { flex-direction: row; justify-content: center; padding-top: 0; padding-bottom: 10px; }
.siswa-box { max-height: 250px; }
}
</style>
</head>
<body>
<div class="header">
<h3><i class="fas fa-user-graduate"></i> Sistem Presensi PKL V5.7</h3>
<?php if(isset($_SESSION['nama'])): ?>
<small>Halo, <b><?= substr($_SESSION['nama'], 0, 15) ?>...</b></small>
<br><a href="?page=logout" style="color: #ddd; text-decoration: none; font-size: 11px;"><i class="fas fa-sign-out-alt"></i> Keluar</a>
<?php endif; ?>
</div>
<div class="container">
<div id="toast-container"></div>
<!-- HALAMAN LOGIN -->
<?php if ($page == 'login'): ?>
<div class="card" style="max-width: 350px; margin: 50px auto;">
<h3 style="text-align: center; margin-bottom: 20px;">Login</h3>
<?php if(isset($error)): ?><div class="toast" style="position:static; width:100%; margin-bottom:10px; border-left-color:var(--danger);"><?= $error ?></div><?php endif; ?>
<form method="POST">
<div class="form-group"><input type="text" name="identifier" class="form-control" required placeholder="Username / NIS"></div>
<div class="form-group"><input type="password" name="password" class="form-control" required placeholder="Password"></div>
<button type="submit" name="login" class="btn btn-primary" style="width: 100%;">Masuk</button>
</form>
<div style="margin-top: 15px; font-size: 11px; text-align: center; color: #666;"><p>Pass default: <b>123</b></p></div>
</div>
<?php endif; ?>

<!-- DASHBOARD SISWA -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'siswa'): ?>
<?php
$pengaturan = $conn->query("SELECT * FROM pengaturan LIMIT 1")->fetch_assoc();
$latSekolah = $pengaturan['latitude']; $lngSekolah = $pengaturan['longitude'];
$radiusSekolah = $pengaturan['radius']; $jamMasuk = $pengaturan['waktu_masuk']; $jamPulang = $pengaturan['waktu_pulang'];
$user_location = ['lat' => $latSekolah, 'lng' => $lngSekolah, 'radius' => $radiusSekolah, 'location_name' => 'Sekolah'];
$siswa_data = $conn->query("SELECT s.*, k.nama_kelompok, k.latitude as lat_kelompok, k.longitude as lng_kelompok, k.radius as rad_kelompok
                            FROM siswa s LEFT JOIN kelompok_pkl k ON s.kelompok_id = k.id WHERE s.nis = '{$_SESSION['nis']}'")->fetch_assoc();
if ($siswa_data && !empty($siswa_data['kelompok_id'])) {
    $user_location['lat'] = $siswa_data['lat_kelompok']; $user_location['lng'] = $siswa_data['lng_kelompok'];
    $user_location['radius'] = $siswa_data['rad_kelompok']; $user_location['location_name'] = "Industri: " . $siswa_data['nama_kelompok'];
}
$today = date('Y-m-d');
$check_presensi = $conn->query("SELECT * FROM presensi WHERE nis='{$_SESSION['nis']}' AND tanggal='$today'")->fetch_assoc();
$btn_masuk_disabled = ""; $btn_pulang_disabled = "disabled"; $status_text = "Silakan Presensi Masuk";
if ($check_presensi) {
    if ($check_presensi['jam_masuk'] && empty($check_presensi['jam_pulang'])) { $btn_masuk_disabled = "disabled"; $btn_pulang_disabled = ""; $status_text = "Silakan Presensi Pulang."; }
    elseif ($check_presensi['jam_pulang']) { $btn_masuk_disabled = "disabled"; $btn_pulang_disabled = "disabled"; $status_text = "Presensi hari ini selesai."; }
}
?>
<?php if ($page == 'menu'): ?>
<div class="card"><h3>Selamat Datang, <?= $_SESSION['nama'] ?></h3><p>Silakan gunakan menu di bawah.</p></div>
<?php endif; ?>
<?php if ($page == 'presensi'): ?>
<div class="card">
<h4>Presensi Harian</h4>
<?php if(isset($error)): ?><div class="toast" style="position:static; border-left-color:var(--danger); margin-bottom:10px;"><?= $error ?></div><?php endif; ?>
<?php if(isset($success)): ?><div class="toast" style="position:static; border-left-color:var(--success); margin-bottom:10px;"><?= $success ?></div><?php endif; ?>
<p style="text-align: center; margin-bottom: 15px; font-weight:bold; color:var(--primary);"><i class="fas fa-map-marker-alt"></i> <?= $user_location['location_name'] ?></p>
<p style="text-align: center; margin-bottom: 20px; font-size:14px; color:#666;">Status: <?= $status_text ?></p>
<form method="POST" enctype="multipart/form-data" id="presensiForm">
<input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude">
<div class="camera-wrapper"><video id="video" autoplay playsinline></video><div class="capture-btn-wrapper"><button type="button" id="btnCapture" class="capture-btn" title="Ambil Foto"><i class="fas fa-camera"></i></button></div></div>
<input type="file" name="foto" id="fotoInput" accept="image/*" required style="display: none;">
<div style="margin-top: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
<button type="submit" name="presensi" value="masuk" class="btn btn-success" onclick="document.getElementById('jenis_presensi').value='masuk'" <?= $btn_masuk_disabled ?>><i class="fas fa-sign-in-alt"></i> Masuk</button>
<button type="submit" name="presensi" value="pulang" class="btn btn-danger" onclick="document.getElementById('jenis_presensi').value='pulang'" <?= $btn_pulang_disabled ?>><i class="fas fa-sign-out-alt"></i> Pulang</button>
<input type="hidden" name="jenis_presensi" id="jenis_presensi">
</div>
<div id="locStatus" style="margin-top: 15px; text-align: center; font-size: 13px; color: #666;"><i class="fas fa-spinner fa-spin"></i> Cek Lokasi...</div>
</form>
<script>
const video = document.getElementById('video'); const btnCapture = document.getElementById('btnCapture'); const fotoInput = document.getElementById('fotoInput'); const canvas = document.createElement('canvas'); const context = canvas.getContext('2d');
navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } }).then(stream => { video.srcObject = stream; }).catch(err => alert("Kamera error: " + err));
btnCapture.onclick = function() {
    canvas.width = video.videoWidth; canvas.height = video.videoHeight; context.drawImage(video,0,0, canvas.width, canvas.height);
    canvas.toBlob(function(blob) {
        const file = new File([blob], "presensi.jpg", { type: "image/jpeg" }); const dataTransfer = new DataTransfer(); dataTransfer.items.add(file); fotoInput.files = dataTransfer.files;
        btnCapture.classList.add('captured'); const icon = btnCapture.querySelector('i'); icon.className = 'fas fa-check'; setTimeout(() => { btnCapture.classList.remove('captured'); icon.className = 'fas fa-camera'; }, 2000);
    }, 'image/jpeg', 0.8);
};
if(navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude; const lng = pos.coords.longitude; document.getElementById('latitude').value = lat; document.getElementById('longitude').value = lng;
        const targetLat = <?= $user_location['lat'] ?>; const targetLng = <?= $user_location['lng'] ?>; const radius = <?= $user_location['radius'] ?>;
        function getDist(lat1, lon1, lat2, lon2) { const R = 6371e3; const φ1 = lat1 * Math.PI/180, φ2 = lat2 * Math.PI/180; const Δφ = (lat2-lat1) * Math.PI/180, Δλ = (lon2-lon1) * Math.PI/180; const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) + Math.cos(φ1)*Math.cos(φ2)*Math.sin(Δλ/2)*Math.sin(Δλ/2); const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); return R * c; }
        const dist = getDist(lat, lng, targetLat, targetLng); const locDiv = document.getElementById('locStatus');
        if(dist <= radius) { locDiv.innerHTML = `<span style="color:green"><i class="fas fa-check-circle"></i> Dalam Radius (${Math.round(dist)}m)</span>`; } else { locDiv.innerHTML = `<span style="color:red"><i class="fas fa-times-circle"></i> Di Luar Radius (${Math.round(dist)}m)</span>`; document.querySelectorAll('button[name="presensi"]').forEach(b=>b.disabled=true); }
    }, err => document.getElementById('locStatus').innerText = "Gagal ambil lokasi.");
}
</script>
</div>
<?php endif; ?>
<?php if ($page == 'izin'): ?><div class="card"><h4>Pengajuan Izin</h4><form method="POST"><div class="form-group"><input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>"></div><div class="form-group"><select name="jenis" class="form-control"><option value="sakit">Sakit</option><option value="ijin">Izin</option></select></div><div class="form-group"><textarea name="keterangan" class="form-control" rows="3" required></textarea></div><button type="submit" name="ajukan_izin" class="btn btn-warning">Kirim</button></form></div><?php endif; ?>
<?php if ($page == 'riwayat_siswa'): ?>
<div class="card"><h4>Riwayat Presensi</h4><div class="tabs"><div class="tab active" onclick="openTab(event, 'hist-pres')">Presensi</div><div class="tab" onclick="openTab(event, 'hist-izin')">Izin/Sakit</div></div>
<div id="hist-pres" class="tab-content active"><table><thead><tr><th>Tgl</th><th>Masuk</th><th>Pulang</th><th>Approval</th></tr></thead><tbody><?php $q = $conn->query("SELECT * FROM presensi WHERE nis='{$_SESSION['nis']}' ORDER BY tanggal DESC LIMIT 20"); while($r = $q->fetch_assoc()): ?><tr><td><?= formatTanggalID($r['tanggal']) ?></td><td><?= $r['jam_masuk'] ?></td><td><?= $r['jam_pulang'] ?></td><td><?= ucfirst($r['status_approval']) ?></td></tr><?php endwhile; ?></tbody></table></div>
<div id="hist-izin" class="tab-content"><table><thead><tr><th>Tgl</th><th>Jenis</th><th>Ket</th><th>Status</th></tr></thead><tbody><?php $q = $conn->query("SELECT * FROM absensi_izin WHERE nis='{$_SESSION['nis']}' ORDER BY tanggal DESC"); while($r = $q->fetch_assoc()): ?><tr><td><?= formatTanggalID($r['tanggal']) ?></td><td><?= ucfirst($r['jenis']) ?></td><td><?= $r['keterangan'] ?></td><td><?= ucfirst($r['status']) ?></td></tr><?php endwhile; ?></tbody></table></div>
</div>
<?php endif; ?>
<?php if ($page != 'login'): ?>
<div class="bottom-nav">
<a href="?page=menu" class="nav-item <?= $page=='menu'?'active':'' ?>"><i class="fas fa-home"></i> Home</a>
<a href="?page=presensi" class="nav-item <?= $page=='presensi'?'active':'' ?>"><i class="fas fa-camera"></i> Presensi</a>
<a href="?page=izin" class="nav-item <?= $page=='izin'?'active':'' ?>"><i class="fas fa-envelope"></i> Izin</a>
<a href="?page=riwayat_siswa" class="nav-item <?= $page=='riwayat_siswa'?'active':'' ?>"><i class="fas fa-list"></i> Riwayat</a>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- DASHBOARD PEMBIMBING -->
<?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'industri')): ?>
<?php $uid = $_SESSION['user_id']; $kelompok = $conn->query("SELECT kp.* FROM kelompok_pkl kp WHERE kp.id_guru_pembimbing = $uid OR kp.id_industri_pembimbing = $uid")->fetch_assoc(); ?>
<?php if ($page == 'dashboard_pembimbing'): ?>
<div class="card">
<h3>Dashboard Pembimbing</h3>
<?php if($kelompok): ?><p>Kelompok: <b><?= $kelompok['nama_kelompok'] ?></b></p>
<div class="tabs">
<div class="tab active" onclick="openTab(event, 'mentor-dashboard')">Approval</div>
<div class="tab" onclick="openTab(event, 'mentor-riwayat')">Riwayat</div>
<div class="tab" onclick="openTab(event, 'mentor-rekap')">Rekap</div>
</div>
<!-- TAB 1: APPROVAL -->
<div id="mentor-dashboard" class="tab-content active">
<h5>Approval Presensi (Hari Ini)</h5><?php $today = date('Y-m-d');
$sql_presensi = "SELECT p.*, s.nama as nama_siswa FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal = '$today' AND p.status_approval = 'pending'";
$res_presensi = $conn->query($sql_presensi);
if($res_presensi->num_rows > 0): ?>
<table><thead><tr><th>Siswa</th><th>Jam</th><th>Foto</th><th>Aksi</th></tr></thead><tbody>
<?php while($row = $res_presensi->fetch_assoc()): ?>
<tr><td><?= $row['nama_siswa'] ?></td><td><?= $row['jam_masuk'] ?></td><td><img src="uploads/foto/masuk/<?= $row['foto_masuk'] ?>" width="40" height="40" style="object-fit:cover;border-radius:4px;"></td><td><button class="btn btn-sm btn-success" onclick="approveModal('presensi', <?= $row['id'] ?>, 'approved')"><i class="fas fa-check"></i></button><button class="btn btn-sm btn-danger" onclick="approveModal('presensi', <?= $row['id'] ?>, 'rejected')"><i class="fas fa-times"></i></button></td></tr><?php endwhile; ?>
</tbody></table>
<?php else: ?><p>Tidak ada presensi pending.</p><?php endif; ?>
<h5 style="margin-top:20px;">Approval Izin</h5><?php $sql_izin = "SELECT ai.*, s.nama as nama_siswa FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND ai.status = 'pending'"; $res_izin = $conn->query($sql_izin); if($res_izin->num_rows > 0): ?><table><thead><tr><th>Siswa</th><th>Jenis</th><th>Ket</th><th>Aksi</th></tr></thead><tbody><?php while($row = $res_izin->fetch_assoc()): ?><tr><td><?= $row['nama_siswa'] ?></td><td><?= $row['jenis'] ?></td><td><?= $row['keterangan'] ?></td><td><button class="btn btn-sm btn-success" onclick="approveModal('izin', <?= $row['id'] ?>, 'diterima')"><i class="fas fa-check"></i></button><button class="btn btn-sm btn-danger" onclick="approveModal('izin', <?= $row['id'] ?>, 'ditolak')"><i class="fas fa-times"></i></button></td></tr><?php endwhile; ?></tbody></table><?php else: ?><p>Tidak ada izin pending.</p><?php endif; ?>
</div>
<!-- TAB 2: RIWAYAT -->
<div id="mentor-riwayat" class="tab-content">
<div class="card" style="background:#f8f9fa; border:none; margin-bottom:15px;">
<form method="GET">
<input type="hidden" name="page" value="dashboard_pembimbing">
<h5>Filter Riwayat</h5>
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
<div><label>Tgl Mulai</label><input type="date" name="start_hist" class="form-control" value="<?= isset($_GET['start_hist'])?$_GET['start_hist']:date('Y-m-01') ?>"></div>
<div><label>Tgl Akhir</label><input type="date" name="end_hist" class="form-control" value="<?= isset($_GET['end_hist'])?$_GET['end_hist']:date('Y-m-d') ?>"></div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:10px;">Filter</button>
</form>
</div>
<?php if(isset($_GET['start_hist'])): $start = $_GET['start_hist']; $end = $_GET['end_hist']; ?>
<h6>Riwayat Presensi</h6>
<div style="overflow-x:auto;">
<table><thead><tr><th>Tgl</th><th>Siswa</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead><tbody>
<?php $sql_hist = "SELECT p.*, s.nama FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal BETWEEN '$start' AND '$end' ORDER BY p.tanggal DESC, s.nama"; $res_hist = $conn->query($sql_hist); while($rh = $res_hist->fetch_assoc()): ?>
<tr><td><?= formatTanggalID($rh['tanggal']) ?></td><td><?= $rh['nama'] ?></td><td><?= $rh['jam_masuk'] ?></td><td><?= $rh['jam_pulang'] ?></td><td><?= ucfirst($rh['status_approval']) ?></td></tr>
<?php endwhile; ?>
</tbody></table>
</div>
<h6 style="margin-top:20px;">Riwayat Izin</h6>
<div style="overflow-x:auto;">
<table><thead><tr><th>Tgl</th><th>Siswa</th><th>Jenis</th><th>Ket</th><th>Status</th></tr></thead><tbody>
<?php $sql_iz_hist = "SELECT ai.*, s.nama FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND ai.tanggal BETWEEN '$start' AND '$end' ORDER BY ai.tanggal DESC, s.nama"; $res_iz_hist = $conn->query($sql_iz_hist); while($rih = $res_iz_hist->fetch_assoc()): ?>
<tr><td><?= formatTanggalID($rih['tanggal']) ?></td><td><?= $rih['nama'] ?></td><td><?= ucfirst($rih['jenis']) ?></td><td><?= $rih['keterangan'] ?></td><td><?= ucfirst($rih['status']) ?></td></tr>
<?php endwhile; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>
<!-- TAB 3: REKAP -->
<div id="mentor-rekap" class="tab-content">
<div class="card" style="background:#f8f9fa; border:none; margin-bottom:15px;">
<form method="GET">
<input type="hidden" name="page" value="dashboard_pembimbing">
<h5>Filter Rekapitulasi</h5>
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
<div><label>Tgl Mulai</label><input type="date" name="start_rep" class="form-control" value="<?= isset($_GET['start_rep'])?$_GET['start_rep']:date('Y-m-01') ?>"></div>
<div><label>Tgl Akhir</label><input type="date" name="end_rep" class="form-control" value="<?= isset($_GET['end_rep'])?$_GET['end_rep']:date('Y-m-d') ?>"></div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:10px;">Tampilkan</button>
</form>
</div>
<?php if(isset($_GET['start_rep'])): $start_r = $_GET['start_rep']; $end_r = $_GET['end_rep']; ?>
<div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-bottom:15px;">
<?php
$total_siswa = $conn->query("SELECT COUNT(*) as c FROM siswa WHERE kelompok_id={$kelompok['id']}")->fetch_assoc()['c'];
$total_hadir = $conn->query("SELECT COUNT(DISTINCT nis) as c FROM presensi WHERE nis IN (SELECT nis FROM siswa WHERE kelompok_id={$kelompok['id']}) AND tanggal BETWEEN '$start_r' AND '$end_r'")->fetch_assoc()['c'];
$total_izin = $conn->query("SELECT COUNT(*) as c FROM absensi_izin WHERE nis IN (SELECT nis FROM siswa WHERE kelompok_id={$kelompok['id']}) AND tanggal BETWEEN '$start_r' AND '$end_r' AND status='diterima'")->fetch_assoc()['c'];
?>
<div style="background:#d1ecf1; color:#0c5460; padding:10px; border-radius:5px; text-align:center;"><b>Total Siswa</b><br><?= $total_siswa ?></div>
<div style="background:#d4edda; color:#155724; padding:10px; border-radius:5px; text-align:center;"><b>Hadir (Unik)</b><br><?= $total_hadir ?></div>
<div style="background:#fff3cd; color:#856404; padding:10px; border-radius:5px; text-align:center;"><b>Izin/Sakit</b><br><?= $total_izin ?></div>
</div>
<h6>Rekap Harian</h6>
<div style="overflow-x:auto;">
<table><thead><tr><th>Tanggal</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Tidak Hadir</th></tr></thead><tbody>
<?php
$sql_rep = "SELECT p.tanggal,
                   SUM(CASE WHEN p.status_masuk='tepat waktu' THEN 1 ELSE 0 END) as hadir,
                   SUM(CASE WHEN p.status_masuk='terlambat' THEN 1 ELSE 0 END) as telat
                   FROM presensi p JOIN siswa s ON p.nis = s.nis
                   WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal BETWEEN '$start_r' AND '$end_r'
                   GROUP BY p.tanggal ORDER BY p.tanggal DESC";
$res_rep = $conn->query($sql_rep);
while($rr = $res_rep->fetch_assoc()):
    $tgl = $rr['tanggal'];
    $jml_izin = $conn->query("SELECT COUNT(*) FROM absensi_izin ai JOIN siswa s ON ai.nis=s.nis WHERE ai.tanggal='$tgl' AND s.kelompok_id={$kelompok['id']} AND ai.status='diterima'")->fetch_assoc()['COUNT(*)'];
    $jml_absen = $total_siswa - ($rr['hadir'] + $rr['telat'] + $jml_izin);
?>
<tr>
<td><?= formatTanggalID($tgl) ?></td>
<td style="text-align:center; color:green;"><b><?= $rr['hadir'] ?></b></td>
<td style="text-align:center; color:orange;"><b><?= $rr['telat'] ?></b></td>
<td style="text-align:center; color:blue;"><b><?= $jml_izin ?></b></td>
<td style="text-align:center; color:red;"><b><?= $jml_absen ?></b></td>
</tr>
<?php endwhile; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>
<?php else: ?><p>Anda tidak memiliki kelompok bimbingan.</p><?php endif; ?>
</div>
<?php endif; ?>
<!-- Bottom Nav PEMBIMBING (DIPERBAIKI) -->
<?php if ($page != 'login'): ?>
<div class="bottom-nav">
<a href="?page=dashboard_pembimbing" class="nav-item <?= $page=='dashboard_pembimbing'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
<!-- LOGIKA BARU: Tampilkan Wali Kelas HANYA jika Guru DAN punya kelas_id -->
<?php if($_SESSION['role'] == 'guru' && !empty($_SESSION['kelas_id'])): ?>
<a href="?page=dashboard_walikelas" class="nav-item <?= $page=='dashboard_walikelas'?'active':'' ?>"><i class="fas fa-user-graduate"></i> Wali Kelas</a>
<?php endif; ?>
<a href="?page=logout" class="nav-item"><i class="fas fa-sign-out-alt"></i> Keluar</a>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- DASHBOARD WALI KELAS -->
<?php if (isset($_SESSION['role']) && $page == 'dashboard_walikelas'): ?>
<div class="card"><h4>Rekap Wali Kelas (Read Only)</h4><p>Anda melihat data kelas ID: <b><?= $_SESSION['kelas_id'] ?></b></p><table><thead><tr><th>NIS</th><th>Nama</th><th>Kelompok</th><th>Hadir Bulan Ini</th><th>Izin Bulan Ini</th></tr></thead><tbody><?php $cid = $_SESSION['kelas_id']; $month = date('m'); $year = date('Y'); $sql = "SELECT s.*, kp.nama_kelompok, (SELECT COUNT(*) FROM presensi p WHERE p.nis=s.nis AND MONTH(p.tanggal)=$month AND YEAR(p.tanggal)=$year) as jml_hadir, (SELECT COUNT(*) FROM absensi_izin a WHERE a.nis=s.nis AND MONTH(a.tanggal)=$month AND YEAR(a.tanggal)=$year AND a.status='diterima') as jml_izin FROM siswa s LEFT JOIN kelompok_pkl kp ON s.kelompok_id = kp.id WHERE s.kelas_id = $cid ORDER BY s.nama"; $res = $conn->query($sql); while($r = $res->fetch_assoc()): ?><tr><td><?= $r['nis'] ?></td><td><?= $r['nama'] ?></td><td><?= $r['nama_kelompok'] ?: '-' ?></td><td><?= $r['jml_hadir'] ?></td><td><?= $r['jml_izin'] ?></td></tr><?php endwhile; ?></tbody></table><br><a href="javascript:history.back()" class="btn btn-secondary">Kembali</a></div>
<?php endif; ?>

<!-- DASHBOARD ADMIN -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
<?php if ($page == 'admin'): ?>
<div class="card">
<h3>Panel Admin</h3>
<div class="tabs">
<div class="tab active" onclick="openTab(event, 'adm-master')">Master</div>
<div class="tab" onclick="openTab(event, 'adm-alokasi')">Alokasi PKL</div>
<div class="tab" onclick="openTab(event, 'adm-kelompok')">Kelompok</div>
<div class="tab" onclick="openTab(event, 'adm-rekap')">Rekap Global</div>
</div>

<!-- MASTER DROPDOWN -->
<div id="adm-master" class="tab-content active">
<div class="dropdown">
    <div class="tab active">Master <i class="fas fa-caret-down"></i></div>
    <div class="dropdown-content">
        <a href="#" onclick="openTab(event, 'adm-kelas')">Kelas</a>
        <a href="#" onclick="openTab(event, 'adm-siswa')">Siswa</a>
        <a href="#" onclick="openTab(event, 'adm-user')">User</a>
    </div>
</div>
</div>

<!-- 1. ALOKASI PKL (TAB NORMAL) -->
<div id="adm-alokasi" class="tab-content">
<div class="alokasi-controls">
<h5><i class="fas fa-users-cog"></i> Pengaturan Alokasi Siswa ke Kelompok PKL</h5>
<div class="form-group" style="margin-top:10px;">
<label>Pilih Kelompok / Industri Tujuan:</label>
<select id="selectKelompokAlokasi" class="form-control" onchange="loadAllocation()">
<option value="">-- Pilih Kelompok --</option>
<?php $qk = $conn->query("SELECT * FROM kelompok_pkl ORDER BY nama_kelompok ASC"); while($rk = $qk->fetch_assoc()): ?>
<option value="<?= $rk['id'] ?>"><?= $rk['nama_kelompok'] ?> - <?= $rk['nama_industri'] ?></option>
<?php endwhile; ?>
</select>
</div>
</div>
<div id="allocationArea" style="display:none;">
<div class="siswa-dual-list-container">
<div class="siswa-col">
<div class="siswa-header"><span>Belum Di Alokasi</span><span id="count-non-members" class="count-badge">0</span></div>
<div class="siswa-search"><input type="text" id="search-non-members" placeholder="Cari..." onkeyup="filterList('list-non-members', this.value)"></div>
<div class="siswa-box"><ul id="list-non-members" class="siswa-list"></ul></div>
<div style="margin-top:5px;"><label><input type="checkbox" onchange="toggleAllCheck('list-non-members', this)"> Pilih Semua</label></div>
</div>
<div class="siswa-actions">
<button type="button" class="btn btn-primary" onclick="moveStudents('list-non-members', 'list-members', 'add')"><i class="fas fa-chevron-right"></i></button>
<button type="button" class="btn btn-danger" onclick="moveStudents('list-members', 'list-non-members', 'remove')"><i class="fas fa-chevron-left"></i></button>
</div>
<div class="siswa-col">
<div class="siswa-header"><span>Siswa Anggota</span><span id="count-members" class="count-badge">0</span></div>
<div class="siswa-search"><input type="text" id="search-members" placeholder="Cari..." onkeyup="filterList('list-members', this.value)"></div>
<div class="siswa-box"><ul id="list-members" class="siswa-list"></ul></div>
<div style="margin-top:5px;"><label><input type="checkbox" onchange="toggleAllCheck('list-members', this)"> Pilih Semua</label></div>
</div>
</div>
</div>
</div>

<!-- 2. KELOLA KELOMPOK (TAB NORMAL) -->
<div id="adm-kelompok" class="tab-content">
<button class="btn btn-primary" onclick="openModal('modalKelompok')" style="margin-bottom:10px; width:auto;">+ Tambah</button>
<div style="overflow-x:auto;">
<table>
<thead><tr><th><input type="checkbox" id="checkAllKelompok"></th><th>Nama</th><th>Industri</th><th>Lokasi</th><th>Pembimbing</th><th>Aksi</th></tr></thead>
<tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT kp.*, u1.nama_lengkap as guru, u2.nama_lengkap as industri FROM kelompok_pkl kp LEFT JOIN users u1 ON kp.id_guru_pembimbing=u1.id LEFT JOIN users u2 ON kp.id_industri_pembimbing=u2.id LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM kelompok_pkl")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr>
<td><input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>"></td>
<td><?= $r['nama_kelompok'] ?></td><td><?= $r['nama_industri'] ?></td><td><?= $r['latitude'] ?>, <?= $r['longitude'] ?></td>
<td>G: <?= $r['guru'] ?><br>I: <?= $r['industri'] ?></td>
<td>
<button class="btn btn-sm btn-warning" onclick='openEditKelompok(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
<a href="?page=admin&delete_kelompok=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<form method="POST" style="margin-top: 10px;"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="kelompok_pkl"><button type="submit" class="btn btn-danger">Hapus Terpilih</button></form>
</div>
<?= showPagination($pg, $total, $limit, "?page=admin#adm-kelompok") ?>
</div>

<!-- 3. KELOLA USERS (DI MASTER) -->
<div id="adm-user" class="tab-content">
<button class="btn btn-primary" onclick="openModal('modalUser')" style="margin-bottom:10px; width:auto;">+ Tambah</button>
<table>
<thead><tr><th><input type="checkbox" id="checkAllUser"></th><th>Username</th><th>Nama</th><th>Role</th><th>Wali Kelas</th><th>Aksi</th></tr></thead>
<tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT u.*, k.nama_kelas as nm_kelas, k.jurusan as nm_jurusan FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id WHERE u.role IN ('guru','industri') LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('guru','industri')")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr>
<td><input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>"></td>
<td><?= $r['username'] ?></td><td><?= $r['nama_lengkap'] ?></td><td><?= $r['role'] ?></td>
<td><?= $r['nm_kelas'] ? $r['nm_kelas'].' ('.$r['nm_jurusan'].')' : '-' ?></td>
<td>
<button class="btn btn-sm btn-warning" onclick='openEditUser(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
<a href="?page=admin&delete_user=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<form method="POST" style="margin-top: 10px;"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="users"><button type="submit" class="btn btn-danger">Hapus Terpilih</button></form>
<?= showPagination($pg, $total, $limit, "?page=admin#adm-user") ?>
</div>

<!-- 4. KELOLA SISWA (DI MASTER) -->
<div id="adm-siswa" class="tab-content">
<button class="btn btn-primary" onclick="openModal('modalSiswa')" style="margin-bottom:10px; width:auto;">+ Tambah</button>
<table>
<thead><tr><th><input type="checkbox" id="checkAllSiswa"></th><th>NIS</th><th>Nama</th><th>Kelas - Jurusan</th><th>Kelompok</th><th>Aksi</th></tr></thead>
<tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT s.*, k.nama_kelas, k.jurusan, kp.nama_kelompok FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id LEFT JOIN kelompok_pkl kp ON s.kelompok_id = kp.id LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM siswa")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr>
<td><input type="checkbox" name="selected_ids[]" value="<?= $r['nis'] ?>"></td>
<td><?= $r['nis'] ?></td><td><?= $r['nama'] ?></td>
<td><?= $r['nama_kelas'] ? $r['nama_kelas'].'<br><small>'.$r['jurusan'].'</small>' : '-' ?></td>
<td><?= $r['nama_kelompok'] ?: '-' ?></td>
<td>
<button class="btn btn-sm btn-warning" onclick='openEditSiswa(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
<a href="?page=admin&delete_siswa=<?= $r['nis'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<form method="POST" style="margin-top: 10px;"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="siswa"><button type="submit" class="btn btn-danger">Hapus Terpilih</button></form>
<?= showPagination($pg, $total, $limit, "?page=admin#adm-siswa") ?>
</div>

<!-- 0. MASTER KELAS (DI MASTER) -->
<div id="adm-kelas" class="tab-content">
<button class="btn btn-primary" onclick="openModal('modalKelas')" style="margin-bottom:10px; width:auto;">+ Tambah Kelas</button>
<div style="overflow-x:auto;">
<table>
<thead><tr><th><input type="checkbox" id="checkAllKelas"></th><th>Nama Kelas</th><th>Jurusan</th><th>Aksi</th></tr></thead>
<tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT * FROM kelas LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM kelas")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr>
<td><input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>"></td>
<td><b><?= $r['nama_kelas'] ?></b></td>
<td><?= $r['jurusan'] ?></td>
<td>
<button class="btn btn-sm btn-warning" onclick='openEditKelas(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
<a href="?page=admin&delete_kelas=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<form method="POST" style="margin-top: 10px;"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="kelas"><button type="submit" class="btn btn-danger">Hapus Terpilih</button></form>
</div>
<?= showPagination($pg, $total, $limit, "?page=admin#adm-kelas") ?>
</div>

<!-- 5. REKAP GLOBAL -->
<div id="adm-rekap" class="tab-content">
<form method="GET" class="card" style="background: #f8f9fa; border:none;">
<input type="hidden" name="page" value="admin">
<h5>Filter Rekapitulasi</h5>
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
<div><label>Tanggal Mulai</label><input type="date" name="start_date" class="form-control" value="<?= isset($_GET['start_date'])?$_GET['start_date']:date('Y-m-01') ?>"></div>
<div><label>Tanggal Akhir</label><input type="date" name="end_date" class="form-control" value="<?= isset($_GET['end_date'])?$_GET['end_date']:date('Y-m-d') ?>"></div>
<div><label>Filter Kelas</label>
<select name="filter_kelas" class="form-control">
<option value="">Semua Kelas</option>
<?php $qfk = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC"); while($rfk = $qfk->fetch_assoc()): ?>
<option value="<?= $rfk['id'] ?>" <?= isset($_GET['filter_kelas']) && $_GET['filter_kelas']==$rfk['id']?'selected':'' ?>><?= $rfk['nama_kelas'] ?></option>
<?php endwhile; ?>
</select>
</div>
<div><label>Filter Industri</label><input type="number" name="filter_industri" class="form-control" placeholder="ID Kelompok" value="<?= isset($_GET['filter_industri'])?$_GET['filter_industri']:'' ?>"></div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:10px;">Tampilkan Data</button>
<a href="?page=admin" class="btn btn-secondary" style="margin-top:10px;">Reset</a>
</form>
<?php if(isset($_GET['start_date'])): ?>
<div style="overflow-x:auto;">
<table>
<thead><tr><th>Tanggal</th><th>Industri</th><th>Hadir Tepat</th><th>Terlambat</th><th>Tidak Hadir</th><th>Sakit/Ijin</th></tr></thead>
<tbody>
<?php $start = $_GET['start_date']; $end = $_GET['end_date'];
$fkelas = isset($_GET['filter_kelas']) ? "AND s.kelas_id = " . intval($_GET['filter_kelas']) : "";
$finddustry = isset($_GET['filter_industri']) ? "AND s.kelompok_id = " . intval($_GET['filter_industri']) : "";
$sql = "SELECT p.tanggal, kp.nama_kelompok, SUM(CASE WHEN p.status_masuk='tepat waktu' THEN 1 ELSE 0 END) as hadir_tepat, SUM(CASE WHEN p.status_masuk='terlambat' THEN 1 ELSE 0 END) as terlambat, SUM(CASE WHEN p.jam_masuk IS NULL THEN 1 ELSE 0 END) as tidak_hadir FROM presensi p JOIN siswa s ON p.nis = s.nis LEFT JOIN kelompok_pkl kp ON s.kelompok_id = kp.id WHERE p.tanggal BETWEEN '$start' AND '$end' $fkelas $finddustry GROUP BY p.tanggal, kp.nama_kelompok ORDER BY p.tanggal DESC";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()):
    $tgl = $row['tanggal'];
    $sqlizin = "SELECT COUNT(*) as jml FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE ai.tanggal = '$tgl' AND ai.status='diterima' $fkelas $finddustry";
    $jmlizin = $conn->query($sqlizin)->fetch_assoc()['jml'];
?>
<tr>
<td><?= formatTanggalID($row['tanggal']) ?></td><td><?= $row['nama_kelompok'] ?: 'Sekolah/Tidak Ada' ?></td>
<td style="text-align:center; color:green;"><b><?= $row['hadir_tepat'] ?></b></td>
<td style="text-align:center; color:orange;"><b><?= $row['terlambat'] ?></b></td>
<td style="text-align:center; color:red;"><b><?= $row['tidak_hadir'] ?></b></td>
<td style="text-align:center; color:blue;"><b><?= $jmlizin ?></b></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<!-- Bottom Nav ADMIN -->
<?php if ($page != 'login'): ?>
<div class="bottom-nav">
<a href="?page=admin" class="nav-item <?= $page=='admin'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Admin</a>
<a href="?page=admin#adm-rekap" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], '#adm-rekap') !== false ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Rekap</a>
<a href="?page=logout" class="nav-item"><i class="fas fa-power-off"></i> Keluar</a>
</div>
<?php endif; ?>
<?php endif; ?>
</div>

<!-- MODALS -->
<div id="modalApprove" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal('modalApprove')">&times;</span>
<h4>Konfirmasi Approval</h4>
<form method="POST">
<input type="hidden" name="action_approve" value="1">
<input type="hidden" name="type" id="appType">
<input type="hidden" name="id" id="appId">
<input type="hidden" name="status" id="appStatus">
<div class="form-group"><textarea id="appNote" name="catatan" class="form-control" rows="3" placeholder="Catatan (Opsional)..."></textarea></div>
<button type="submit" class="btn btn-primary" style="width:100%;">Proses</button>
</form>
</div>
</div>
<div id="modalKelas" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal('modalKelas')">&times;</span>
<h4 id="modalKelasTitle">Tambah Kelas</h4>
<form method="POST">
<input type="hidden" name="action_kelas" value="add"><input type="hidden" name="id_kelas" id="kelasId">
<div class="form-group"><input type="text" name="nama_kelas" id="kelasNama" class="form-control" placeholder="Contoh: XI TKJ 1" required></div>
<div class="form-group"><input type="text" name="jurusan" id="kelasJurusan" class="form-control" placeholder="Contoh: Teknik Komputer Jaringan" required></div>
<button type="submit" class="btn btn-primary" style="width:100%;">Simpan Kelas</button>
</form>
</div>
</div>
<div id="modalKelompok" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal('modalKelompok')">&times;</span>
<h4 id="modalKelompokTitle">Tambah Kelompok</h4>
<form method="POST">
<input type="hidden" name="action_kelompok" value="add"><input type="hidden" name="id_kel" id="kelId">
<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
<div class="form-group"><input type="text" name="nama_kelompok" id="kelNama" class="form-control" placeholder="Nama Kelompok" required></div>
<div class="form-group"><input type="text" name="nama_industri" id="kelIndustri" class="form-control" placeholder="Nama Industri" required></div>
<div class="form-group"><input type="text" name="latitude" id="kelLat" class="form-control" placeholder="Latitude" required></div>
<div class="form-group"><input type="text" name="longitude" id="kelLng" class="form-control" placeholder="Longitude" required></div>
<div class="form-group"><input type="number" name="radius" id="kelRad" class="form-control" placeholder="Radius (m)" value="100"></div>
<div class="form-group"><select name="id_guru" id="kelGuru" class="form-control" required><option value="">Pilih Guru</option><?php $g = $conn->query("SELECT * FROM users WHERE role='guru'"); while($rg=$g->fetch_assoc()) echo "<option value='{$rg['id']}'>{$rg['nama_lengkap']}</option>"; ?></select></div>
<div class="form-group"><select name="id_industri" id="kelInd" class="form-control" required><option value="">Pilih Pembimbing Industri</option><?php $i = $conn->query("SELECT * FROM users WHERE role='industri'"); while($ri=$i->fetch_assoc()) echo "<option value='{$ri['id']}'>{$ri['nama_lengkap']}</option>"; ?></select></div>
</div>
<button type="submit" class="btn btn-primary" style="width:100%;">Simpan Data Kelompok</button>
</form>
</div>
</div>
<div id="modalUser" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal('modalUser')">&times;</span>
<h4 id="modalUserTitle">Tambah User</h4>
<form method="POST">
<input type="hidden" name="action_user" value="add"><input type="hidden" name="id_user" id="userId">
<div class="form-group"><input type="text" name="username" id="userUsername" class="form-control" placeholder="Username" required></div>
<div class="form-group"><input type="text" name="nama_lengkap" id="userNama" class="form-control" placeholder="Nama Lengkap" required></div>
<div class="form-group"><select name="role" id="userRole" class="form-control" required><option value="guru">Guru</option><option value="industri">Industri</option></select></div>
<div class="form-group"><select name="kelas_id" id="userKelasId" class="form-control"><option value="">Pilih Kelas (Wali Kelas)</option><?php $qk = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC"); while($rk=$qk->fetch_assoc()) echo "<option value='{$rk['id']}'>{$rk['nama_kelas']} ({$rk['jurusan']})</option>"; ?></select></div>
<div class="form-group"><input type="password" name="password" id="userPass" class="form-control" placeholder="Password (Isi untuk baru/ganti)"></div>
<button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button>
</form>
</div>
</div>
<div id="modalSiswa" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal('modalSiswa')">&times;</span>
<h4 id="modalSiswaTitle">Tambah Siswa</h4>
<form method="POST">
<input type="hidden" name="action_siswa" value="add"><input type="hidden" name="old_nis" id="siswaOldNis">
<div class="form-group"><input type="text" name="nis" id="siswaNis" class="form-control" placeholder="NIS" required></div>
<div class="form-group"><input type="text" name="nama" id="siswaNama" class="form-control" placeholder="Nama Lengkap" required></div>
<div class="form-group"><select name="kelas_id" id="siswaKelasId" class="form-control"><option value="">Pilih Kelas</option><?php $qk = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC"); while($rk=$qk->fetch_assoc()) echo "<option value='{$rk['id']}'>{$rk['nama_kelas']} - {$rk['jurusan']}</option>"; ?></select></div>
<div class="form-group"><small style="color:#666;">Alokasi Kelompok PKL diatur melalui menu <b>Alokasi PKL</b>.</small></div>
<div class="form-group"><input type="password" name="password" id="siswaPass" class="form-control" placeholder="Password (Isi untuk baru/ganti)"></div>
<button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button>
</form>
</div>
</div>
<script>
function openTab(evt, tabName) {
    var i, x = document.getElementsByClassName("tab-content");
    for (i = 0; i < x.length; i++) x[i].style.display = "none";
    var tabs = document.getElementsByClassName("tab");
    for (i = 0; i < tabs.length; i++) tabs[i].className = tabs[i].className.replace(" active", "");
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

function openTabById(tabId) {
    var i, x = document.getElementsByClassName("tab-content");
    for (i = 0; i < x.length; i++) x[i].style.display = "none";
    var content = document.getElementById(tabId);
    if(content) content.style.display = "block";
    var btns = document.getElementsByClassName("tab");
    for (var i = 0; i < btns.length; i++) {
        if(btns[i].getAttribute("onclick").includes("'"+tabId+"'")) {
            btns[i].className += " active";
        }
    }
}

function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openEditKelas(data) {
    document.getElementById('modalKelasTitle').innerText = "Edit Kelas";
    document.querySelector('#modalKelas input[name="action_kelas"]').value = "update";
    document.getElementById('kelasId').value = data.id;
    document.getElementById('kelasNama').value = data.nama_kelas;
    document.getElementById('kelasJurusan').value = data.jurusan;
    openModal('modalKelas');
}
function approveModal(type, id, status) {
    document.getElementById('appType').value = type; document.getElementById('appId').value = id;
    document.getElementById('appStatus').value = status; openModal('modalApprove');
}
function openEditKelompok(data) {
    document.getElementById('modalKelompokTitle').innerText = "Edit Kelompok";
    document.querySelector('#modalKelompok input[name="action_kelompok"]').value = "update";
    document.getElementById('kelId').value = data.id;
    document.getElementById('kelNama').value = data.nama_kelompok;
    document.getElementById('kelIndustri').value = data.nama_industri;
    document.getElementById('kelLat').value = data.latitude;
    document.getElementById('kelLng').value = data.longitude;
    document.getElementById('kelRad').value = data.radius;
    document.getElementById('kelGuru').value = data.id_guru_pembimbing;
    document.getElementById('kelInd').value = data.id_industri_pembimbing;
    openModal('modalKelompok');
}
function openEditUser(data) {
    document.getElementById('modalUserTitle').innerText = "Edit User";
    document.querySelector('#modalUser input[name="action_user"]').value = "update";
    document.getElementById('userId').value = data.id;
    document.getElementById('userUsername').value = data.username;
    document.getElementById('userNama').value = data.nama_lengkap;
    document.getElementById('userRole').value = data.role;
    document.getElementById('userKelasId').value = data.kelas_id;
    document.getElementById('userPass').value = "";
    openModal('modalUser');
}
function openEditSiswa(data) {
    document.getElementById('modalSiswaTitle').innerText = "Edit Siswa";
    document.querySelector('#modalSiswa input[name="action_siswa"]').value = "update";
    document.getElementById('siswaOldNis').value = data.nis;
    document.getElementById('siswaNis').value = data.nis;
    document.getElementById('siswaNama').value = data.nama;
    document.getElementById('siswaKelasId').value = data.kelas_id;
    document.getElementById('siswaPass').value = "";
    openModal('modalSiswa');
}
function loadAllocation() {
    const kelId = document.getElementById('selectKelompokAlokasi').value;
    const area = document.getElementById('allocationArea');
    if (!kelId) { area.style.display = 'none'; return; }
    area.style.display = 'block';
    document.getElementById('list-non-members').innerHTML = '<li style="text-align:center; padding:20px;">Loading...</li>';
    document.getElementById('list-members').innerHTML = '';
    fetch('?action_ajax=get_siswa_kelompok&kel_id=' + kelId)
        .then(response => response.json())
        .then(data => { renderList('list-non-members', data.non_members); renderList('list-members', data.members); })
        .catch(err => { document.getElementById('list-non-members').innerHTML = '<li style="text-align:center; color:red;">Gagal memuat data</li>'; });
}
function renderList(containerId, students) {
    const list = document.getElementById(containerId); list.innerHTML = '';
    if (students.length === 0) {
        list.innerHTML = '<li style="color:#999; padding:15px; text-align:center;">Tidak ada data</li>';
        document.getElementById(containerId.replace('list-', 'count-')).innerText = '0'; return;
    }
    students.forEach(siswa => {
        const li = document.createElement('li');
        li.dataset.nama = siswa.nama.toLowerCase();
        li.dataset.kelas = siswa.nama_kelas ? siswa.nama_kelas.toLowerCase() : '';
        li.innerHTML = `
            <input type="checkbox" value="${siswa.nis}" class="check-only">
            <div class="info" onclick="toggleCheckbox(this)">
                <b>${siswa.nama}</b>
                <small>NIS: ${siswa.nis}</small>
                <small>Kelas: ${siswa.nama_kelas || '-'} (${siswa.jurusan || '-'})</small>
                ${siswa.current_group ? `<span style="display:block; font-size:10px; color:#dc3545;">Awal: ${siswa.current_group}</span>` : ''}
            </div>`; list.appendChild(li);
    });
    document.getElementById(containerId.replace('list-', 'count-')).innerText = students.length;
}
function toggleCheckbox(div) {
    const cb = div.parentElement.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
}
function filterList(containerId, query) {
    query = query.toLowerCase();
    const list = document.getElementById(containerId);
    const items = list.getElementsByTagName('li');
    Array.from(items).forEach(li => {
        const nama = li.dataset.nama; const kelas = li.dataset.kelas;
        if (nama.includes(query) || kelas.includes(query)) { li.style.display = 'flex'; } else { li.style.display = 'none'; }
    });
}
function toggleAllCheck(containerId, checkbox) {
    const list = document.getElementById(containerId);
    const inputs = list.querySelectorAll('input[type="checkbox"]');
    inputs.forEach(input => { if (input.parentElement.parentElement.style.display !== 'none') input.checked = checkbox.checked; });
}
function moveStudents(sourceId, targetId, action) {
    const sourceList = document.getElementById(sourceId);
    const checkboxes = sourceList.querySelectorAll('input[type="checkbox"]:checked');
    if (checkboxes.length === 0) { alert("Pilih minimal 1 siswa."); return; }
    const nisArr = Array.from(checkboxes).map(cb => cb.value);
    const kelSelect = document.getElementById('selectKelompokAlokasi');
    const targetKelId = kelSelect.value;
    if(!targetKelId) return;
    const finalKelId = (action === 'remove') ? 0 : targetKelId;
    const formData = new FormData();
    formData.append('action_ajax', 'update_siswa_kelompok');
    formData.append('target_kel_id', finalKelId);
    nisArr.forEach(nis => formData.append('nis[]', nis));
    const confirmMsg = (action === 'add') ? `Tambah ${nisArr.length} siswa?` : `Keluarkan ${nisArr.length} siswa?`;
    if(!confirm(confirmMsg)) return;
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => { if(data.status === 'success') loadAllocation(); else alert('Gagal memindahkan siswa.'); })
        .catch(error => console.error('Error:', error));
}
document.addEventListener('DOMContentLoaded', function() {
    const checks = {
        'checkAllKelas': document.querySelectorAll('#adm-kelas input[name="selected_ids[]"]'),
        'checkAllKelompok': document.querySelectorAll('#adm-kelompok input[name="selected_ids[]"]'),
        'checkAllUser': document.querySelectorAll('#adm-user input[name="selected_ids[]"]'),
        'checkAllSiswa': document.querySelectorAll('#adm-siswa input[name="selected_ids[]"]')
    };
    for (const [allId, checkboxes] of Object.entries(checks)) {
        const allCb = document.getElementById(allId);
        if (allCb) { allCb.addEventListener('change', function() { checkboxes.forEach(cb => cb.checked = this.checked); }); }
    }
    var hash = window.location.hash;
    if(hash) openTabById(hash.substring(1));
});
window.addEventListener('hashchange', function() {
    var hash = window.location.hash;
    if(hash) openTabById(hash.substring(1));
});
<?php if(isset($crud_msg)) echo "alert('$crud_msg');"; ?>
<?php if(isset($crud_error)) echo "alert('$crud_error');"; ?>
<?php if(isset($success_mentor)) echo "alert('$success_mentor');"; ?>
<?php if(isset($success)) echo "alert('$success');"; ?>
<?php if(isset($_SESSION['success_izin'])): ?>
alert(<?= json_encode($_SESSION['success_izin']) ?>);
<?php unset($_SESSION['success_izin']); ?>
<?php endif; ?>
</script>
</body>
</html>
