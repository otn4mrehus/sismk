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
$host = "_HOST_";
$user = "_USER_";
$pass = "_USER-PASS_";
$db = "_DB_";
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
            industri_id INT(11) DEFAULT NULL,
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
                    id_periode INT(11) DEFAULT NULL,
                    id_industri INT(11) DEFAULT NULL,
                    id_guru_pembimbing INT(11) DEFAULT NULL,
                    id_industri_pembimbing INT(11) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
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
                                        nama_libur VARCHAR(150) NOT NULL,
                                        tanggal_mulai DATE NOT NULL,
                                        tanggal_selesai DATE NOT NULL,
                                        industri_id INT(11) DEFAULT NULL,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        PRIMARY KEY (id)
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                                            "industri" => "CREATE TABLE IF NOT EXISTS industri (
                                            id INT(11) NOT NULL AUTO_INCREMENT,
                                            nama_industri VARCHAR(150) NOT NULL,
                                            alamat TEXT DEFAULT NULL,
                                            no_telp VARCHAR(50) DEFAULT NULL,
                                            email VARCHAR(100) DEFAULT NULL,
                                            nama_pembimbing VARCHAR(100) DEFAULT NULL,
                                            latitude DECIMAL(10,8) DEFAULT NULL,
                                            longitude DECIMAL(11,8) DEFAULT NULL,
                                            radius INT(11) DEFAULT 100,
                                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            PRIMARY KEY (id)
                                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                                            "periode_pkl" => "CREATE TABLE IF NOT EXISTS periode_pkl (
                                                id INT(11) NOT NULL AUTO_INCREMENT,
                                                nama_periode VARCHAR(100) NOT NULL,
                                                tingkat_kelas VARCHAR(20) DEFAULT NULL,
                                                tahun_ajaran VARCHAR(20) DEFAULT NULL,
                                                tanggal_mulai DATE NOT NULL,
                                                tanggal_selesai DATE NOT NULL,
                                                is_aktif TINYINT(1) DEFAULT 0,
                                                keterangan TEXT DEFAULT NULL,
                                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                PRIMARY KEY (id)
                                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                                                "industri_libur" => "CREATE TABLE IF NOT EXISTS industri_libur (
                                                id INT(11) NOT NULL AUTO_INCREMENT,
                                                industri_id INT(11) DEFAULT NULL,
                                                nama_libur VARCHAR(100) NOT NULL,
                                                hari_aktif SET('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') DEFAULT NULL,
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

    // Insert Industri seed
    $conn->query("INSERT INTO industri (nama_industri, alamat, latitude, longitude, radius) VALUES
    ('PT Teknologi Maju', 'Jakarta', -6.2088, 106.8456, 200)
    ON DUPLICATE KEY UPDATE nama_industri=nama_industri");

    $guru_id = $conn->query("SELECT id FROM users WHERE username='guru1'")->fetch_assoc()['id'];
    $ind_id = $conn->query("SELECT id FROM users WHERE username='ind1'")->fetch_assoc()['id'];
    $ind_row_id = $conn->query("SELECT id FROM industri WHERE nama_industri='PT Teknologi Maju'")->fetch_assoc()['id'];

    $conn->query("INSERT INTO kelompok_pkl (nama_kelompok, id_industri, id_guru_pembimbing, id_industri_pembimbing) VALUES
    ('Kelompok A', $ind_row_id, $guru_id, $ind_id)
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

    // Check and add columns if not exists (MySQL compatible)
    $cols = $conn->query("SHOW COLUMNS FROM presensi LIKE 'status_approval'");
    if ($cols->num_rows == 0) {
        $conn->query("ALTER TABLE presensi ADD COLUMN status_approval ENUM('pending','approved','rejected') DEFAULT 'pending'");
        $conn->query("ALTER TABLE presensi ADD COLUMN catatan_pembimbing TEXT DEFAULT NULL");
        $conn->query("ALTER TABLE presensi ADD COLUMN id_pembimbing_approver INT(11) DEFAULT NULL");
    }
    $cols_izin = $conn->query("SHOW COLUMNS FROM absensi_izin LIKE 'catatan_pembimbing'");
    if ($cols_izin->num_rows == 0) {
        $conn->query("ALTER TABLE absensi_izin ADD COLUMN catatan_pembimbing TEXT DEFAULT NULL");
        $conn->query("ALTER TABLE absensi_izin ADD COLUMN id_pembimbing_approver INT(11) DEFAULT NULL");
    }
    $cols_siswa = $conn->query("SHOW COLUMNS FROM siswa LIKE 'kelompok_id'");
    if ($cols_siswa->num_rows == 0) {
        $conn->query("ALTER TABLE siswa ADD COLUMN kelompok_id INT(11) DEFAULT NULL");
    }
    $cols_periode = $conn->query("SHOW COLUMNS FROM periode_pkl LIKE 'tingkat_kelas'");
    if ($cols_periode->num_rows == 0) {
        $conn->query("ALTER TABLE periode_pkl ADD COLUMN tingkat_kelas VARCHAR(20) DEFAULT NULL");
        $conn->query("ALTER TABLE periode_pkl ADD COLUMN tahun_ajaran VARCHAR(20) DEFAULT NULL");
        $conn->query("ALTER TABLE periode_pkl ADD COLUMN keterangan TEXT DEFAULT NULL");
    }

    // Check FKs
    $check_fk = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'siswa' AND CONSTRAINT_NAME = 'siswa_ibfk_2'");
    if ($check_fk->num_rows == 0) {
        $conn->query("ALTER TABLE siswa ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL");
    }
    $check_fk_user = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'users_ibfk_1'");
    if ($check_fk_user->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL");
    }
    $check_fk_user_ind = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'users' AND REFERENCED_TABLE_NAME = 'industri'");
    if ($check_fk_user_ind->num_rows == 0 && $conn->query("SELECT id FROM industri LIMIT 1")->num_rows > 0) {
        $conn->query("ALTER TABLE users ADD FOREIGN KEY (industri_id) REFERENCES industri(id) ON DELETE SET NULL");
    }
    
    // Add FK to kelompok_pkl if not exists
    $check_fk_kel = $conn->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'kelompok_pkl' AND REFERENCED_TABLE_NAME = 'periode_pkl'");
    if ($check_fk_kel->num_rows == 0 && $conn->query("SELECT id FROM periode_pkl LIMIT 1")->num_rows > 0) {
        $conn->query("ALTER TABLE kelompok_pkl ADD FOREIGN KEY (id_periode) REFERENCES periode_pkl(id) ON DELETE SET NULL");
        $conn->query("ALTER TABLE kelompok_pkl ADD FOREIGN KEY (id_industri) REFERENCES industri(id) ON DELETE SET NULL");
        $conn->query("ALTER TABLE kelompok_pkl ADD FOREIGN KEY (id_guru_pembimbing) REFERENCES users(id) ON DELETE SET NULL");
        $conn->query("ALTER TABLE kelompok_pkl ADD FOREIGN KEY (id_industri_pembimbing) REFERENCES users(id) ON DELETE SET NULL");
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

// --- Fungsi redirect admin dengan pesan flash ---
function adminRedirect($tab, $subtab = '', $message = '', $isError = false) {
    $url = "index.php?page=admin&tab=" . urlencode($tab);
    if ($subtab) $url .= "&subtab=" . urlencode($subtab);
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $isError ? 'error' : 'success';
    }
    $_SESSION['reset_modal'] = true;
    header("Location: $url");
    exit();
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
// Fungsi untuk mendapatkan tab dan subtab dari POST atau GET
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['tab']) ? $_POST['tab'] : 'master');
$current_subtab = isset($_GET['subtab']) ? $_GET['subtab'] : (isset($_POST['subtab']) ? $_POST['subtab'] : 'kelas');

// 1. CRUD KELAS
if (isset($_POST['action_kelas'])) {
    $nama = $conn->real_escape_string($_POST['nama_kelas']);
    $jurusan = $conn->real_escape_string($_POST['jurusan']);
    $tab = $_POST['tab'];
    $subtab = $_POST['subtab'];
    if ($_POST['action_kelas'] == 'add') {
        $sql = "INSERT INTO kelas (nama_kelas, jurusan) VALUES ('$nama', '$jurusan')";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Kelas berhasil ditambahkan!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    } elseif ($_POST['action_kelas'] == 'update') {
        $id = intval($_POST['id_kelas']);
        $sql = "UPDATE kelas SET nama_kelas='$nama', jurusan='$jurusan' WHERE id=$id";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Kelas berhasil diupdate!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['delete_kelas'])) {
    $id = intval($_GET['delete_kelas']);
    $conn->query("DELETE FROM kelas WHERE id=$id");
    adminRedirect('master', 'kelas', "Kelas dihapus!");
}

// 2. BULK DELETE
if (isset($_POST['action_bulk_delete'])) {
    $table = $_POST['table_name'];
    $ids = $_POST['selected_ids'];
    $tab = isset($_POST['tab']) ? $_POST['tab'] : 'master';
    $subtab = isset($_POST['subtab']) ? $_POST['subtab'] : 'kelas';
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') adminRedirect($tab, $subtab, "Akses Ditolak!", true);
    else {
        if (empty($ids)) adminRedirect($tab, $subtab, "Pilih data yang akan dihapus!", true);
        elseif ($table == 'siswa') {
            $ids_clean_nis = implode("','", array_map(function($val) use($conn){ return "'".$conn->real_escape_string($val)."'"; }, $ids));
            $sql = "DELETE FROM siswa WHERE nis IN ($ids_clean_nis)";
            if ($conn->query($sql)) adminRedirect($tab, $subtab, "Data dihapus!");
            else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
        } elseif ($table == 'users' || $table == 'kelas') {
            $ids_clean = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM $table WHERE id IN ($ids_clean)";
            if ($conn->query($sql)) adminRedirect($tab, $subtab, "Data dihapus!");
            else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
        } elseif ($table == 'kelompok_pkl') {
            $ids_clean = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM kelompok_pkl WHERE id IN ($ids_clean)";
            if ($conn->query($sql)) adminRedirect($tab, $subtab, "Kelompok dihapus!");
            else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
        } elseif ($table == 'industri') {
            $ids_clean = implode(',', array_map('intval', $ids));
            $sql = "DELETE FROM industri WHERE id IN ($ids_clean)";
            if ($conn->query($sql)) adminRedirect($tab, $subtab, "Industri dihapus!");
            else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
        }
    }
}

// 3. CRUD KELOMPOK (Alokasi Pembimbing)
if (isset($_POST['action_kelompok'])) {
    $tab = $_POST['tab'];
    $subtab = isset($_POST['subtab']) ? $_POST['subtab'] : '';
    if ($_POST['action_kelompok'] == 'add') {
        $nama = $conn->real_escape_string($_POST['nama_kelompok']);
        $id_periode = !empty($_POST['id_periode']) ? intval($_POST['id_periode']) : 'NULL';
        $id_industri = !empty($_POST['id_industri']) ? intval($_POST['id_industri']) : 'NULL';
        $gid = !empty($_POST['id_guru']) ? intval($_POST['id_guru']) : 'NULL';
        $iid = !empty($_POST['id_industri_pembimbing']) ? intval($_POST['id_industri_pembimbing']) : 'NULL';
        $sql = "INSERT INTO kelompok_pkl (nama_kelompok, id_periode, id_industri, id_guru_pembimbing, id_industri_pembimbing) VALUES ('$nama', $id_periode, $id_industri, $gid, $iid)";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Alokasi Pembimbing ditambahkan!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    } elseif ($_POST['action_kelompok'] == 'update') {
        $id = intval($_POST['id_kel']); 
        $nama = $conn->real_escape_string($_POST['nama_kelompok']);
        $id_periode = !empty($_POST['id_periode']) ? intval($_POST['id_periode']) : 'NULL';
        $id_industri = !empty($_POST['id_industri']) ? intval($_POST['id_industri']) : 'NULL';
        $gid = !empty($_POST['id_guru']) ? intval($_POST['id_guru']) : 'NULL';
        $iid = !empty($_POST['id_industri_pembimbing']) ? intval($_POST['id_industri_pembimbing']) : 'NULL';
        $sql = "UPDATE kelompok_pkl SET nama_kelompok='$nama', id_periode=$id_periode, id_industri=$id_industri, id_guru_pembimbing=$gid, id_industri_pembimbing=$iid WHERE id=$id";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Alokasi Pembimbing diupdate!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['delete_kelompok'])) {
    $id = intval($_GET['delete_kelompok']);
    $conn->query("DELETE FROM kelompok_pkl WHERE id=$id");
    adminRedirect('pembimbing', '', "Alokasi Pembimbing dihapus!");
}

// 4. CRUD USERS
if (isset($_POST['action_user'])) {
    $username = $conn->real_escape_string($_POST['username']); $nama = $conn->real_escape_string($_POST['nama_lengkap']);
    $role = $_POST['role']; 
    $kelas_id = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : 'NULL';
    $industri_id = !empty($_POST['industri_id']) ? intval($_POST['industri_id']) : 'NULL';
    $pass = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
    $tab = $_POST['tab'];
    $subtab = $_POST['subtab'];
    if ($_POST['action_user'] == 'add') {
        if (empty($pass)) adminRedirect($tab, $subtab, "Password wajib diisi!", true);
        else {
            $sql = "INSERT INTO users (username, password, role, nama_lengkap, kelas_id, industri_id) VALUES ('$username', '$pass', '$role', '$nama', $kelas_id, $industri_id)";
            if ($conn->query($sql)) adminRedirect($tab, $subtab, "User ditambahkan!");
            else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
        }
    } elseif ($_POST['action_user'] == 'update') {
        $id = intval($_POST['id_user']);
        $sql = "UPDATE users SET username='$username', nama_lengkap='$nama', role='$role', kelas_id=$kelas_id, industri_id=$industri_id";
        if (!empty($pass)) $sql .= ", password='$pass'"; $sql .= " WHERE id=$id";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "User diupdate!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['delete_user'])) {
    $id = intval($_GET['delete_user']);
    $conn->query("DELETE FROM users WHERE id=$id");
    adminRedirect('master', 'user', "User dihapus!");
}

// 5a. CRUD INDUSTRI
if (isset($_POST['action_industri'])) {
    $nama = $conn->real_escape_string($_POST['nama_industri']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $no_telp = $conn->real_escape_string($_POST['no_telp']);
    $email = $conn->real_escape_string($_POST['email']);
    $pembimbing = $conn->real_escape_string($_POST['nama_pembimbing']);
    $lat = $conn->real_escape_string($_POST['latitude']);
    $lng = $conn->real_escape_string($_POST['longitude']);
    $rad = intval($_POST['radius']);
    $tab = $_POST['tab'];
    $subtab = isset($_POST['subtab']) ? $_POST['subtab'] : '';
    if ($_POST['action_industri'] == 'add') {
        $sql = "INSERT INTO industri (nama_industri, alamat, no_telp, email, nama_pembimbing, latitude, longitude, radius) VALUES ('$nama', '$alamat', '$no_telp', '$email', '$pembimbing', '$lat', '$lng', $rad)";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Industri ditambahkan!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    } elseif ($_POST['action_industri'] == 'update') {
        $id = intval($_POST['id_industri']);
        $sql = "UPDATE industri SET nama_industri='$nama', alamat='$alamat', no_telp='$no_telp', email='$email', nama_pembimbing='$pembimbing', latitude='$lat', longitude='$lng', radius=$rad WHERE id=$id";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Industri diupdate!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['delete_industri'])) {
    $id = intval($_GET['delete_industri']);
    $conn->query("DELETE FROM industri WHERE id=$id");
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'industri';
    adminRedirect($tab, '', "Industri dihapus!");
}

// 5b. CRUD PERIODE PKL
if (isset($_POST['action_periode_pkl'])) {
    $nama = $conn->real_escape_string($_POST['nama_periode']);
    $tingkat = $conn->real_escape_string($_POST['tingkat_kelas']);
    $tahun = $conn->real_escape_string($_POST['tahun_ajaran']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $mulai = $_POST['tanggal_mulai'];
    $selesai = $_POST['tanggal_selesai'];
    $aktif = isset($_POST['is_aktif']) ? 1 : 0;
    $tab = $_POST['tab'];
    $subtab = isset($_POST['subtab']) ? $_POST['subtab'] : '';
    if ($_POST['action_periode_pkl'] == 'add') {
        if ($aktif) $conn->query("UPDATE periode_pkl SET is_aktif=0");
        $sql = "INSERT INTO periode_pkl (nama_periode, tingkat_kelas, tahun_ajaran, tanggal_mulai, tanggal_selesai, is_aktif, keterangan) VALUES ('$nama', '$tingkat', '$tahun', '$mulai', '$selesai', $aktif, '$keterangan')";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Periode PKL ditambahkan!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    } elseif ($_POST['action_periode_pkl'] == 'update') {
        $id = intval($_POST['id_periode_pkl']);
        if ($aktif) $conn->query("UPDATE periode_pkl SET is_aktif=0");
        $sql = "UPDATE periode_pkl SET nama_periode='$nama', tingkat_kelas='$tingkat', tahun_ajaran='$tahun', tanggal_mulai='$mulai', tanggal_selesai='$selesai', is_aktif=$aktif, keterangan='$keterangan' WHERE id=$id";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Periode PKL diupdate!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['delete_periode_pkl'])) {
    $id = intval($_GET['delete_periode_pkl']);
    $conn->query("DELETE FROM periode_pkl WHERE id=$id");
    adminRedirect('periode', '', "Periode PKL dihapus!");
}

// 5c. CRUD INDUSTRI LIBUR
if (isset($_POST['action_industri_libur'])) {
    $ind_id = intval($_POST['industri_id']);
    $nama = $conn->real_escape_string($_POST['nama_libur']);
    $hari = isset($_POST['hari_aktif']) && is_array($_POST['hari_aktif']) ? implode(',', $_POST['hari_aktif']) : '';
    $tab = $_POST['tab'];
    $subtab = isset($_POST['subtab']) ? $_POST['subtab'] : '';
    if (empty($hari)) {
        adminRedirect($tab, $subtab, "Pilih minimal 1 hari aktif!", true);
    } elseif ($_POST['action_industri_libur'] == 'add') {
        $sql = "INSERT INTO industri_libur (industri_id, nama_libur, hari_aktif) VALUES ($ind_id, '$nama', '$hari')";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Jadwal libur ditambahkan!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    } elseif ($_POST['action_industri_libur'] == 'update') {
        $id = intval($_POST['id_industri_libur']);
        $sql = "UPDATE industri_libur SET industri_id=$ind_id, nama_libur='$nama', hari_aktif='$hari' WHERE id=$id";
        if ($conn->query($sql)) adminRedirect($tab, $subtab, "Jadwal libur diupdate!");
        else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}

// 5d. CRUD PERIODE LIBUR
if (isset($_POST['action_periode_libur'])) {
    $nama = $conn->real_escape_string($_POST['nama_libur']);
    $mulai = $_POST['tanggal_mulai'];
    $selesai = $_POST['tanggal_selesai'];
    $ind_id = !empty($_POST['industri_id']) ? intval($_POST['industri_id']) : 'NULL';
    if ($_POST['action_periode_libur'] == 'add') {
        $sql = "INSERT INTO periode_libur (nama_libur, tanggal_mulai, tanggal_selesai, industri_id) VALUES ('$nama', '$mulai', '$selesai', $ind_id)";
        if ($conn->query($sql)) adminRedirect('libur', '', "Periode libur ditambahkan!");
        else adminRedirect('libur', '', "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete_periode_libur') {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM periode_libur WHERE id=$id");
    if (isset($_GET['page']) && $_GET['page'] == 'dashboard_pembimbing') {
        header('Location: ?page=dashboard_pembimbing'); exit();
    } else {
        adminRedirect('libur', 'periode', "Periode libur dihapus!");
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete_industri_libur') {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM industri_libur WHERE id=$id");
    if (isset($_GET['page']) && $_GET['page'] == 'dashboard_pembimbing') {
        header('Location: ?page=dashboard_pembimbing'); exit();
    } else {
        adminRedirect('libur', 'jadwal', "Jadwal kerja dihapus!");
    }
}

// 5. CRUD SISWA
if (isset($_POST['action_siswa'])) {
    $nis = $conn->real_escape_string($_POST['nis']); $nama = $conn->real_escape_string($_POST['nama']);
    $kelas_id = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : 'NULL';
    $pass = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
    $tab = $_POST['tab'];
    $subtab = $_POST['subtab'];
    if ($_POST['action_siswa'] == 'add') {
        if (empty($pass)) adminRedirect($tab, $subtab, "Password wajib diisi!", true);
        else {
            $sql = "INSERT INTO siswa (nis, nama, password, kelas_id) VALUES ('$nis', '$nama', '$pass', $kelas_id)";
            if ($conn->query($sql)) adminRedirect($tab, $subtab, "Siswa ditambahkan!");
            else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
        }
    } elseif ($_POST['action_siswa'] == 'update') {
        $old_nis = $_POST['old_nis'];
        $sql = "UPDATE siswa SET nis='$nis', nama='$nama', kelas_id=$kelas_id";
        if (!empty($pass)) $sql .= ", password='$pass'"; $sql .= " WHERE nis='$old_nis'";
        if ($conn->query($sql)) {
            if(isset($_SESSION['nis']) && $_SESSION['nis'] == $old_nis) $_SESSION['nis'] = $nis;
            adminRedirect($tab, $subtab, "Siswa diupdate!");
        } else adminRedirect($tab, $subtab, "Gagal: " . $conn->error, true);
    }
}
if (isset($_GET['delete_siswa'])) {
    $nis = $conn->real_escape_string($_GET['delete_siswa']);
    $conn->query("DELETE FROM siswa WHERE nis='$nis'");
    adminRedirect('master', 'siswa', "Siswa dihapus!");
}

// 6. APPROVAL PEMBIMBING (tidak berubah)
if (isset($_POST['action_approve']) && isset($_SESSION['role']) && ($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'industri')) {
    $type = $_POST['type']; $id = intval($_POST['id']); $status = $_POST['status']; $catatan = $conn->real_escape_string($_POST['catatan']); $approver_id = intval($_SESSION['user_id']);
    if ($type == 'presensi') $sql = "UPDATE presensi SET status_approval='$status', catatan_pembimbing='$catatan', id_pembimbing_approver=$approver_id WHERE id=$id";
    else $sql = "UPDATE absensi_izin SET status='$status', catatan_pembimbing='$catatan', id_pembimbing_approver=$approver_id WHERE id=$id";
    if ($conn->query($sql)) $success_mentor = "Data diproses!"; else $error_mentor = "Gagal: " . $conn->error;
}

// 7. LOGIC PRESENSI SISWA (tidak berubah)
if (isset($_POST['presensi']) && isset($_SESSION['nis'])) {
    $nis = $_SESSION['nis']; $jenis = $_POST['jenis_presensi'];
    $latitude = $_POST['latitude']; $longitude = $_POST['longitude']; $tanggal = date('Y-m-d');

    $pengaturan = $conn->query("SELECT * FROM pengaturan LIMIT 1")->fetch_assoc();
    $latSekolah = $pengaturan['latitude']; $lngSekolah = $pengaturan['longitude'];
    $radiusSekolah = $pengaturan['radius']; $jamMasuk = $pengaturan['waktu_masuk']; $jamPulang = $pengaturan['waktu_pulang'];

    $user_location = ['lat' => $latSekolah, 'lng' => $lngSekolah, 'radius' => $radiusSekolah, 'location_name' => 'Sekolah'];
    $siswa_data = $conn->query("SELECT s.*, k.id_industri, i.nama_industri, i.latitude as lat_industri, i.longitude as lng_industri, i.radius as rad_industri
    FROM siswa s LEFT JOIN kelompok_pkl k ON s.kelompok_id = k.id LEFT JOIN industri i ON k.id_industri = i.id WHERE s.nis = '$nis'")->fetch_assoc();

    if ($siswa_data && !empty($siswa_data['id_industri'])) {
        $user_location['lat'] = $siswa_data['lat_industri']; $user_location['lng'] = $siswa_data['lng_industri'];
        $user_location['radius'] = $siswa_data['rad_industri']; $user_location['location_name'] = "Industri: " . $siswa_data['nama_industri'];
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
                if ($row['role'] == 'industri') { $_SESSION['industri_id'] = $row['industri_id']; $_SESSION['nama_industri'] = $conn->query("SELECT nama_industri FROM industri WHERE id = {$row['industri_id']}")->fetch_assoc()['nama_industri'] ?? ''; }
                if ($row['role'] == 'admin') header('Location: index.php?page=admin'); else header('Location: index.php?page=dashboard_pembimbing'); exit();
            } else { $error = "Password salah!"; }
        } else { $error = "Username/NIS atau password salah!"; }
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// LOGIKA LOGOUT
if ($page == 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php?page=login');
    exit();
}

// CEK LOGIN - JIKA SUDAH LOGIN, TIDAK BISA AKSES HALAMAN LOGIN
if (isset($_SESSION['role'])) {
    if ($page == 'login') {
        if ($_SESSION['role'] == 'siswa') {
            header('Location: index.php?page=menu');
        } elseif ($_SESSION['role'] == 'admin') {
            header('Location: index.php?page=admin');
        } elseif ($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'industri') {
            header('Location: index.php?page=dashboard_pembimbing');
        }
        exit();
    }

    // PROTEKSI HALAMAN BERDASARKAN ROLE
    $allowed_pages_siswa = ['menu', 'presensi', 'izin', 'riwayat_siswa', 'logout'];
    $allowed_pages_guru = ['dashboard_pembimbing', 'dashboard_walikelas', 'logout'];
    $allowed_pages_admin = ['admin', 'logout'];

    if ($_SESSION['role'] == 'siswa' && !in_array($page, $allowed_pages_siswa)) {
        header('Location: index.php?page=menu'); exit();
    }
    if (($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'industri') && !in_array($page, $allowed_pages_guru)) {
        header('Location: index.php?page=dashboard_pembimbing'); exit();
    }
    if ($_SESSION['role'] == 'admin' && !in_array($page, $allowed_pages_admin)) {
        header('Location: index.php?page=admin'); exit();
    }
}

// JIKA BELUM LOGIN DAN BUKAN HALAMAN LOGIN, REDIRECT KE LOGIN
if (!isset($_SESSION['role']) && $page != 'login') {
    header('Location: index.php?page=login'); exit();
}
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
/* ========== STYLE KOMPREHENSIF ========== */
:root { --primary: #2563eb; --primary-dark: #1d4ed8; --secondary: #64748b; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --light: #f1f5f9; --dark: #1e293b; --bg-app: #f8fafc; --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
body { background-color: var(--bg-app); color: var(--dark); padding-bottom: 80px; }
.container { max-width: 1200px; margin: 0 auto; padding: 15px; }
.card { background: white; border-radius: 12px; box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px; transition: box-shadow 0.2s; }
.card:hover { box-shadow: var(--shadow-lg); }

/* HEADER - Desktop Only */
.header-desktop { display: none; }
@media (min-width: 769px) {
    body { padding-top: 70px; padding-bottom: 20px; }
    .header-desktop { display: flex; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 0 20px; align-items: center; justify-content: space-between; z-index: 1000; box-shadow: var(--shadow-lg); }
    .header-desktop .logo { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 700; }
    .header-desktop .logo i { font-size: 24px; }
    .header-desktop .nav-links { display: flex; gap: 5px; }
    .header-desktop .nav-item { color: rgba(255,255,255,0.9); text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
    .header-desktop .nav-item:hover, .header-desktop .nav-item.active { background: rgba(255,255,255,0.15); color: white; }
    .header-desktop .user-info { display: flex; align-items: center; gap: 15px; }
    .header-desktop .user-info span { font-size: 14px; }
    .header-desktop .logout-btn { color: #fca5a5; text-decoration: none; font-size: 14px; padding: 6px 12px; border: 1px solid rgba(252, 165, 165, 0.3); border-radius: 6px; transition: all 0.2s; }
    .header-desktop .logout-btn:hover { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; }
}

/* Mobile Header */
.header-mobile { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 12px 15px; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; }
.header-mobile .header-left { display: flex; align-items: center; gap: 10px; }
.header-mobile h3 { font-size: 16px; margin: 0; display: flex; align-items: center; gap: 8px; }
.header-mobile small { font-size: 12px; color: rgba(255,255,255,0.8); }
.header-mobile .logout-btn { color: white; font-size: 18px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; transition: 0.2s; }
.header-mobile .logout-btn:hover { background: rgba(255,255,255,0.2); }
@media (min-width: 769px) { .header-mobile { display: none; } }

/* BUTTONS */
.btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-block; font-size: 14px; }
.btn-sm { padding: 5px 8px; font-size: 12px; border-radius: 4px; }
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

/* Bottom Navigation - Mobile Fixed */
.bottom-nav {
    position: fixed; bottom: 0; left: 0; width: 100%; background: white; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 8px 0; z-index: 9999; border-top: 1px solid #e2e8f0; height: 60px;
}
.bottom-nav .nav-item { text-decoration: none; color: var(--secondary); font-size: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; padding: 5px; transition: all 0.2s; border-radius: 8px; }
.bottom-nav .nav-item i { font-size: 22px; margin-bottom: 0; transition: transform 0.2s; }
.bottom-nav .nav-item:hover i { transform: scale(1.1); }
.bottom-nav .nav-item.active { color: var(--primary); background: rgba(37, 99, 235, 0.08); }
.bottom-nav .nav-item.active i { font-weight: 900; }
.bottom-nav .nav-item span { display: none; }
@media (min-width: 769px) { .bottom-nav { display: none; } }

/* TABS */
.main-tabs { display: flex; gap: 4px; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; overflow-x: auto; }
.main-tabs .tab { flex: 1; min-width: 80px; padding: 10px 12px; text-align: center; cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b; border-radius: 8px 8px 0 0; transition: all 0.2s; white-space: nowrap; text-decoration: none; display: inline-block; }
.main-tabs .tab.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: rgba(37, 99, 235, 0.05); }
.main-tabs .tab:hover:not(.active) { background: #f1f5f9; }

.sub-tabs-container { display: flex; gap: 5px; margin-bottom: 15px; background: #f1f5f9; border-radius: 10px; padding: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
.sub-tab { flex: 1; min-width: max-content; padding: 10px 16px; text-align: center; cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b; border-radius: 8px; transition: all 0.2s; user-select: none; text-decoration: none; display: inline-block; white-space: nowrap; }
@media (max-width: 768px) {
    .sub-tabs-container { display: flex; gap: 5px; margin-bottom: 15px; background: #f1f5f9; border-radius: 10px; padding: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; justify-content: flex-start; }
    .sub-tab { flex: 0 0 auto; padding: 10px 16px; font-size: 13px; }
}
.sub-tab.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.sub-tab:hover:not(.active) { background: #e2e8f0; }

.admin-sub-tabs { display: flex; gap: 2px; margin-bottom: 15px; background: #f1f5f9; border-radius: 8px; padding: 4px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; justify-content: space-between; }
.admin-sub-tabs .tab { flex: 1; min-width: 0; padding: 8px 8px; text-align: center; cursor: pointer; font-weight: 600; font-size: 11px; color: #64748b; border-radius: 6px; transition: all 0.2s; white-space: nowrap; text-decoration: none; }
.admin-sub-tabs .tab.active { background: white; color: var(--primary); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.admin-sub-tabs .tab:hover:not(.active) { background: #e2e8f0; }
@media (min-width: 769px) {
    .admin-sub-tabs { display: none; }
}

/* Admin Menu Wrapper (Desktop) */
.admin-menu-wrapper {
    position: sticky;
    top: 60px;
    background: white;
    padding: 15px 0 10px 0;
    z-index: 99;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.admin-menu-wrapper .main-tabs {
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}
.admin-menu-wrapper .sub-tabs-container {
    margin-bottom: 0;
    margin-top: 10px;
}

/* Bottom Navigation Khusus Admin (Mobile) */
.bottom-nav-admin {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: white;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
    padding: 8px 0;
    z-index: 9999;
    border-top: 1px solid #e2e8f0;
    justify-content: space-around;
    gap: 0;
}
.bottom-nav-admin a {
    flex: 1;
    text-decoration: none;
    color: var(--secondary);
    font-size: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 6px 8px;
    border-radius: 8px;
    transition: all 0.2s;
}
.bottom-nav-admin a i { font-size: 20px; }
.bottom-nav-admin a.active {
    background: var(--primary);
    color: white;
}
@media (max-width: 768px) {
    .admin-menu-wrapper { display: none; }
    .bottom-nav-admin { display: flex; }
    body { padding-bottom: 70px; }
}

/* Approval Grid */
.approval-grid { display: flex; flex-direction: column; gap: 20px; }
.approval-col { background: white; border-radius: 10px; padding: 15px; border: 1px solid #e2e8f0; }
.approval-col h5 { margin-bottom: 12px; color: #334155; font-size: 14px; font-weight: 600; }
.approval-col .empty-msg { color: #94a3b8; font-size: 13px; text-align: center; padding: 20px; }
@media (min-width: 769px) {
    .approval-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
}

/* Filter */
.filter-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; background: #f8fafc; padding: 10px 12px; border-radius: 10px; margin-bottom: 15px; }
.filter-bar > div { flex: 1; min-width: 30%; }
.filter-bar label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; font-weight: 500; }
.filter-bar .form-control { width: 100%; padding: 8px 10px; font-size: 13px; }
.filter-bar .btn-group { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-bar .btn { padding: 8px 14px; font-size: 12px; white-space: nowrap; }
@media (max-width: 576px) {
    .filter-bar { flex-direction: row; align-items: stretch; }
    .filter-bar > div { min-width: 28%; flex: 1; }
    .filter-bar .btn-group { flex: 0 0 auto; }
}
.filter-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; background: #f8fafc; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
.filter-form > div { flex: 1; min-width: 150px; }
.filter-form label { display: block; font-size: 12px; color: #64748b; margin-bottom: 5px; font-weight: 500; }
.filter-form .form-control { width: 100%; padding: 10px; font-size: 13px; }
.filter-form .btn-group { display: flex; gap: 8px; align-items: center; }
.filter-form .btn { padding: 10px 18px; }
@media (max-width: 768px) {
    .filter-bar > div { min-width: 100%; }
    .filter-form > div { min-width: 100%; }
    .filter-bar.filter-rekap { flex-direction: row; flex-wrap: nowrap; }
    .filter-bar.filter-rekap > div { min-width: auto; flex: 1; }
    .filter-bar.filter-rekap .btn-group { flex: 0 0 auto; }
}

/* Stats */
.stats-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
.stats-row > div { flex: 1; min-width: 80px; padding: 12px 8px; border-radius: 10px; text-align: center; }
.stats-row .stat-siswa { background: #dbeafe; color: #1d4ed8; }
.stats-row .stat-hadir { background: #d1fae5; color: #059669; }
.stats-row .stat-izin { background: #fef3c7; color: #d97706; }
.stats-row > div b { display: block; font-size: 20px; }
.stats-row > div span { font-size: 11px; }
.stats-desktop { display: none; margin-bottom: 15px; }
.stats-desktop .stat-box { display: inline-block; padding: 10px 20px; border-radius: 8px; margin-right: 10px; text-align: center; }
.stats-desktop .stat-box b { display: block; font-size: 24px; }
.stats-desktop .stat-box span { font-size: 12px; }
@media (min-width: 769px) {
    .stats-row { display: none; }
    .stats-desktop { display: block; }
}

/* Table Responsive */
.data-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -15px; padding: 0 15px; }
.data-table-wrapper table { min-width: 500px; }
@media (min-width: 769px) {
    .data-table-wrapper table { min-width: auto; }
}

/* Modal */
.modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
.modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 10px; position: relative; }
.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
.close:hover { color: black; }

/* Flash message */
.flash-message { position: fixed; top: 70px; right: 20px; background: white; padding: 12px 20px; border-radius: 8px; box-shadow: var(--shadow-lg); z-index: 10001; border-left: 4px solid var(--success); max-width: 350px; animation: slideIn 0.3s ease; }
.flash-message.error { border-left-color: var(--danger); }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* Camera */
.camera-wrapper { position: relative; width: 100%; max-width: 400px; margin: 0 auto; background: #000; border-radius: 12px; overflow: hidden; aspect-ratio: 4/3; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
#video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
.capture-btn-wrapper { position: absolute; bottom: 20px; left: 0; right: 0; display: flex; justify-content: center; align-items: center; z-index: 10; }
.capture-btn { width: 70px; height: 70px; border-radius: 50%; background: rgba(255,255,255,0.9); border: 4px solid rgba(255,255,255,0.5); display: flex; justify-content: center; align-items: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
.capture-btn:active { transform: scale(0.9); background: #fff; }
.capture-btn i { font-size: 28px; color: #333; }
.capture-btn.captured { background: #28a745; border-color: #fff; }
.capture-btn.captured i { color: white; }

/* Alokasi */
.alokasi-controls { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #ddd; }
.siswa-dual-list-container { display: flex; gap: 15px; margin-top: 10px; align-items: stretch; }
.siswa-col { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.siswa-box { border: 1px solid #ddd; border-radius: 5px; background: #fff; max-height: 400px; overflow-y: auto; flex: 1; }
.siswa-header { background: #e9ecef; padding: 10px; font-weight: bold; border-bottom: 1px solid #ddd; font-size: 13px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 2; flex-shrink: 0; }
.siswa-header .count-badge { background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
.siswa-list { list-style: none; margin: 0; padding: 0; }
.siswa-list li { padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; cursor: pointer; transition: background 0.2s; font-size: 13px; }
.siswa-list li:hover { background-color: #f1f3f5; }
.siswa-list li .info { flex: 1; margin-left: 10px; min-width: 0; }
.siswa-list li .info b { display: block; font-size: 13px; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.siswa-list li .info small { display: block; color: #666; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.siswa-list li input[type="checkbox"] { width: 16px; height: 16px; flex-shrink: 0; }
.siswa-actions { display: flex; flex-direction: column; gap: 10px; justify-content: center; padding-top: 20px; min-width: 50px; }
.siswa-search { padding: 10px; border-bottom: 1px solid #eee; background: #fff; position: sticky; top: 0; z-index: 1; flex-shrink: 0; }
.siswa-search input { width: 100%; padding: 6px 10px; font-size: 12px; border: 1px solid #ccc; border-radius: 15px; }
@media(max-width: 768px) {
    .siswa-dual-list-container { flex-direction: column; gap: 10px; }
    .siswa-actions { flex-direction: row; justify-content: center; padding: 10px 0; min-width: auto; }
    .siswa-box { max-height: 200px; min-height: 150px; }
    .alokasi-controls select { font-size: 14px; }
}
</style>
</head>
<body>
<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'login';
function isActiveDesktop($page) { global $current_page; return $current_page == $page ? 'active' : ''; }
?>
<!-- DESKTOP HEADER -->
<?php if(isset($_SESSION['role']) && $current_page != 'login'): ?>
<div class="header-desktop">
<div class="logo"><i class="fas fa-user-graduate"></i> <span>Sistem Presensi PKL</span></div>
<div class="nav-links">
<?php if($_SESSION['role'] == 'siswa'): ?>
<a href="?page=menu" class="nav-item <?= isActiveDesktop('menu') ?>"><i class="fas fa-home"></i> Home</a>
<a href="?page=presensi" class="nav-item <?= isActiveDesktop('presensi') ?>"><i class="fas fa-camera"></i> Presensi</a>
<a href="?page=izin" class="nav-item <?= isActiveDesktop('izin') ?>"><i class="fas fa-envelope"></i> Izin</a>
<a href="?page=riwayat_siswa" class="nav-item <?= isActiveDesktop('riwayat_siswa') ?>"><i class="fas fa-history"></i> Riwayat</a>
<?php elseif($_SESSION['role'] == 'guru' || $_SESSION['role'] == 'industri'): ?>
<a href="?page=dashboard_pembimbing" class="nav-item <?= isActiveDesktop('dashboard_pembimbing') ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
<?php if($_SESSION['role'] == 'guru' && !empty($_SESSION['kelas_id'])): ?>
<a href="?page=dashboard_walikelas" class="nav-item <?= isActiveDesktop('dashboard_walikelas') ?>"><i class="fas fa-user-graduate"></i> Wali Kelas</a>
<?php endif; ?>
<?php elseif($_SESSION['role'] == 'admin'): ?>
<a href="?page=admin" class="nav-item <?= isActiveDesktop('admin') ?>"><i class="fas fa-cogs"></i> Admin Panel</a>
<a href="?page=admin&tab=rekap" class="nav-item <?= strpos($_SERVER['REQUEST_URI'], 'tab=rekap') !== false ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Rekap Global</a>
<?php endif; ?>
</div>
<div class="user-info">
<span><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['nama']) ?></span>
<a href="?page=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Keluar</a>
</div>
</div>
<?php endif; ?>

<!-- MOBILE HEADER -->
<?php if(isset($_SESSION['role']) && $current_page != 'login'): ?>
<div class="header-mobile">
<div class="header-left">
<h3><i class="fas fa-user-graduate"></i> ePKL</h3>
<small>Halo, <b><?= substr($_SESSION['nama'], 0, 10) ?>...</b></small>
</div>
<a href="?page=logout" class="logout-btn" title="Keluar"><i class="fas fa-sign-out-alt"></i></a>
</div>
<?php endif; ?>

<div class="container">
<!-- Tampilkan flash message jika ada -->
<?php if(isset($_SESSION['flash_message'])): ?>
<div class="flash-message <?= isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'success' ?>">
<i class="fas <?= $_SESSION['flash_type'] == 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
<?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<script>setTimeout(function(){ document.querySelector('.flash-message')?.remove(); }, 3000);</script>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<!-- HALAMAN LOGIN -->
<?php if ($page == 'login'): ?>
<div class="card" style="max-width: 350px; margin: 50px auto;">
<h3 style="text-align: center; margin-bottom: 20px;">Login</h3>
<?php if(isset($error)): ?><div class="flash-message error" style="position:static; margin-bottom:10px;"><?= $error ?></div><?php endif; ?>
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
$siswa_data = $conn->query("SELECT s.*, k.nama_kelompok, i.latitude as lat_industri, i.longitude as lng_industri, i.radius as rad_industri
FROM siswa s LEFT JOIN kelompok_pkl k ON s.kelompok_id = k.id LEFT JOIN industri i ON k.id_industri = i.id WHERE s.nis = '{$_SESSION['nis']}'")->fetch_assoc();
if ($siswa_data && !empty($siswa_data['kelompok_id']) && !empty($siswa_data['lat_industri'])) {
    $user_location['lat'] = $siswa_data['lat_industri']; $user_location['lng'] = $siswa_data['lng_industri'];
    $user_location['radius'] = $siswa_data['rad_industri']; $user_location['location_name'] = "Industri: " . $siswa_data['nama_kelompok'];
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
<?php if(isset($error)): ?><div class="flash-message error" style="position:static; margin-bottom:10px;"><?= $error ?></div><?php endif; ?>
<?php if(isset($success)): ?><div class="flash-message success" style="position:static; margin-bottom:10px;"><?= $success ?></div><?php endif; ?>
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
<?php if ($page == 'izin'): ?>
<div class="card"><h4>Pengajuan Izin</h4><form method="POST"><div class="form-group"><input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>"></div><div class="form-group"><select name="jenis" class="form-control"><option value="sakit">Sakit</option><option value="ijin">Izin</option></select></div><div class="form-group"><textarea name="keterangan" class="form-control" rows="3" required></textarea></div><button type="submit" name="ajukan_izin" class="btn btn-warning">Kirim</button></form></div>
<?php endif; ?>
<?php if ($page == 'riwayat_siswa'): ?>
<?php $riwayatSubTab = isset($_GET['riwayat_tab']) ? $_GET['riwayat_tab'] : 'presensi'; ?>
<div class="card" style="margin-top: 20px;">
<h4>Riwayat Presensi</h4>
<div class="main-tabs" style="display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap;">
<a href="?page=riwayat_siswa&riwayat_tab=presensi" class="tab <?= $riwayatSubTab == 'presensi' ? 'active' : '' ?>">Presensi</a>
<a href="?page=riwayat_siswa&riwayat_tab=izin" class="tab <?= $riwayatSubTab == 'izin' ? 'active' : '' ?>">Izin/Sakit</a>
</div>

<?php if ($riwayatSubTab == 'presensi'): ?>
<table><thead><tr><th>Tgl</th><th>Masuk</th><th>Pulang</th><th>Approval</th></tr></thead><tbody><?php $q = $conn->query("SELECT * FROM presensi WHERE nis='{$_SESSION['nis']}' ORDER BY tanggal DESC LIMIT 20"); while($r = $q->fetch_assoc()): ?><tr><td><?= formatTanggalID($r['tanggal']) ?></td><td><?= $r['jam_masuk'] ?></td><td><?= $r['jam_pulang'] ?></td><td><?= ucfirst($r['status_approval']) ?></td></tr><?php endwhile; ?></tbody></table>
<?php elseif ($riwayatSubTab == 'izin'): ?>
<table><thead><tr><th>Tgl</th><th>Jenis</th><th>Ket</th><th>Status</th></tr></thead><tbody><?php $q = $conn->query("SELECT * FROM absensi_izin WHERE nis='{$_SESSION['nis']}' ORDER BY tanggal DESC"); while($r = $q->fetch_assoc()): ?><tr><td><?= formatTanggalID($r['tanggal']) ?></td><td><?= ucfirst($r['jenis']) ?></td><td><?= $r['keterangan'] ?></td><td><?= ucfirst($r['status']) ?></td></tr><?php endwhile; ?></tbody></table>
<?php endif; ?>
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
<?php $uid = $_SESSION['user_id']; ?>
<?php $pembimbingSubTab = isset($_GET['subtab']) ? $_GET['subtab'] : 'approval'; ?>
<?php if ($_SESSION['role'] == 'industri' && isset($_SESSION['industri_id'])): ?>
<?php $ind_id = $_SESSION['industri_id']; ?>
<div class="card" style="margin-top: 20px;">
<h3>Dashboard Pembimbing Industri</h3>
<p>Industri: <b><?= $_SESSION['nama_industri'] ?></b></p>
<div class="main-tabs" style="display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap;">
<a href="?page=dashboard_pembimbing&subtab=periode" class="tab <?= $pembimbingSubTab == 'periode' ? 'active' : '' ?>">Periode PKL</a>
<a href="?page=dashboard_pembimbing&subtab=approval" class="tab <?= $pembimbingSubTab == 'approval' ? 'active' : '' ?>">Approval</a>
<a href="?page=dashboard_pembimbing&subtab=jadwal" class="tab <?= $pembimbingSubTab == 'jadwal' ? 'active' : '' ?>">Jadwal Kerja</a>
<a href="?page=dashboard_pembimbing&subtab=libur" class="tab <?= $pembimbingSubTab == 'libur' ? 'active' : '' ?>">Periode Libur</a>
<a href="?page=dashboard_pembimbing&subtab=riwayat" class="tab <?= $pembimbingSubTab == 'riwayat' ? 'active' : '' ?>">Riwayat</a>
<a href="?page=dashboard_pembimbing&subtab=rekap" class="tab <?= $pembimbingSubTab == 'rekap' ? 'active' : '' ?>">Rekap</a>
</div>

<?php if ($pembimbingSubTab == 'periode'): ?>
<h5>Periode PKL Aktif</h5>
<?php $periode_aktif = $conn->query("SELECT * FROM periode_pkl WHERE is_aktif = 1 LIMIT 1")->fetch_assoc(); ?>
<?php if($periode_aktif): ?>
<table><thead><tr><th>Nama Periode</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th></tr></thead><tbody><tr><td><?= $periode_aktif['nama_periode'] ?></td><td><?= formatTanggalID($periode_aktif['tanggal_mulai']) ?></td><td><?= formatTanggalID($periode_aktif['tanggal_selesai']) ?></td></tr></tbody></table>
<?php else: ?><p class="empty-msg">Belum ada periode PKL aktif.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'approval'): ?>
<?php $kelompok = $conn->query("SELECT kp.* FROM kelompok_pkl kp WHERE kp.id_industri_pembimbing = $uid")->fetch_assoc(); ?>
<?php if($kelompok): ?>
<div class="approval-grid">
<div class="approval-col"><h5>Approval Presensi (Hari Ini)</h5>
<?php $today = date('Y-m-d'); $sql_presensi = "SELECT p.*, s.nama as nama_siswa FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal = '$today' AND p.status_approval = 'pending'"; $res_presensi = $conn->query($sql_presensi); if($res_presensi->num_rows > 0): ?>
<table><thead><tr><th>Siswa</th><th>Jam</th><th>Foto</th><th>Aksi</th></tr></thead><tbody><?php while($row = $res_presensi->fetch_assoc()): ?><tr><td><?= $row['nama_siswa'] ?></td><td><?= $row['jam_masuk'] ?></td><td><img src="uploads/foto/masuk/<?= $row['foto_masuk'] ?>" width="40" height="40" style="object-fit:cover;border-radius:4px;"></td><td><button class="btn btn-sm btn-success" onclick="approveModal('presensi', <?= $row['id'] ?>, 'approved')"><i class="fas fa-check"></i></button><button class="btn btn-sm btn-danger" onclick="approveModal('presensi', <?= $row['id'] ?>, 'rejected')"><i class="fas fa-times"></i></button></td></tr><?php endwhile; ?></tbody></table><?php else: ?><p class="empty-msg">Tidak ada presensi pending.</p><?php endif; ?>
</div>
<div class="approval-col"><h5>Approval Izin</h5>
<?php $sql_izin = "SELECT ai.*, s.nama as nama_siswa FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND ai.status = 'pending'"; $res_izin = $conn->query($sql_izin); if($res_izin->num_rows > 0): ?>
<table><thead><tr><th>Siswa</th><th>Jenis</th><th>Ket</th><th>Aksi</th></tr></thead><tbody><?php while($row = $res_izin->fetch_assoc()): ?><tr><td><?= $row['nama_siswa'] ?></td><td><?= $row['jenis'] ?></td><td><?= $row['keterangan'] ?></td><td><button class="btn btn-sm btn-success" onclick="approveModal('izin', <?= $row['id'] ?>, 'diterima')"><i class="fas fa-check"></i></button><button class="btn btn-sm btn-danger" onclick="approveModal('izin', <?= $row['id'] ?>, 'ditolak')"><i class="fas fa-times"></i></button></td></tr><?php endwhile; ?></tbody></table><?php else: ?><p class="empty-msg">Tidak ada izin pending.</p><?php endif; ?>
</div>
</div>
<?php else: ?><p class="empty-msg">Anda tidak memiliki kelompok bimbingan.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'jadwal'): ?>
<h5>Jadwal Kerja Mingguan</h5>
<button class="btn btn-sm btn-primary" onclick="openModal('modalIndustriLibur')" style="margin-bottom:10px;">+ Tambah Jadwal Kerja</button>
<?php $jadwal_kerja = $conn->query("SELECT * FROM industri_libur WHERE industri_id = $ind_id"); if($jadwal_kerja->num_rows > 0): ?>
<table><thead><tr><th>Nama Jadwal</th><th>Hari Aktif</th><th>Aksi</th></tr></thead><tbody><?php while($jk = $jadwal_kerja->fetch_assoc()): ?><tr><td><?= $jk['nama_libur'] ?></td><td><?= $jk['hari_aktif'] ?></td><td><button class="btn btn-sm btn-danger" onclick="if(confirm('Hapus jadwal ini?')){window.location='?page=dashboard_pembimbing&subtab=jadwal&action=delete_industri_libur&id=<?= $jk['id'] ?>'}"><i class="fas fa-trash"></i></button></td></tr><?php endwhile; ?></tbody></table>
<?php else: ?><p class="empty-msg">Belum ada jadwal kerja. Klik tombol di atas untuk menambah.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'libur'): ?>
<h5>Periode Libur (Tanggal Spesifik)</h5>
<button class="btn btn-sm btn-primary" onclick="openModal('modalPeriodeLibur')" style="margin-bottom:10px;">+ Tambah Periode Libur</button>
<?php $periode_libur = $conn->query("SELECT pl.*, i.nama_industri FROM periode_libur pl LEFT JOIN industri i ON pl.industri_id = i.id WHERE pl.industri_id = $ind_id OR pl.industri_id IS NULL ORDER BY pl.tanggal_mulai DESC"); if($periode_libur->num_rows > 0): ?>
<table><thead><tr><th>Nama Libur</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th><th>Industri</th><th>Aksi</th></tr></thead><tbody><?php while($pl = $periode_libur->fetch_assoc()): ?><tr><td><?= $pl['nama_libur'] ?></td><td><?= formatTanggalID($pl['tanggal_mulai']) ?></td><td><?= formatTanggalID($pl['tanggal_selesai']) ?></td><td><?= $pl['nama_industri'] ?: 'Semua' ?></td><td><button class="btn btn-sm btn-danger" onclick="if(confirm('Hapus periode libur ini?')){window.location='?page=dashboard_pembimbing&subtab=libur&action=delete_periode_libur&id=<?= $pl['id'] ?>'}"><i class="fas fa-trash"></i></button></td></tr><?php endwhile; ?></tbody></table>
<?php else: ?><p class="empty-msg">Belum ada periode libur.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'riwayat'): ?>
<h5>Filter Riwayat</h5>
<form method="GET" class="filter-bar">
<input type="hidden" name="page" value="dashboard_pembimbing"><input type="hidden" name="subtab" value="riwayat">
<div><label>Tanggal Mulai</label><input type="date" name="start_hist" class="form-control" value="<?= isset($_GET['start_hist'])?$_GET['start_hist']:date('Y-m-01') ?>"></div>
<div><label>Tanggal Akhir</label><input type="date" name="end_hist" class="form-control" value="<?= isset($_GET['end_hist'])?$_GET['end_hist']:date('Y-m-d') ?>"></div>
<div class="btn-group"><button type="submit" class="btn btn-primary">Filter</button></div>
</form>
<?php if(isset($_GET['start_hist'])): $start = $_GET['start_hist']; $end = $_GET['end_hist']; ?>
<?php $kelompok = $conn->query("SELECT kp.* FROM kelompok_pkl kp WHERE kp.id_industri_pembimbing = $uid")->fetch_assoc(); ?>
<?php if($kelompok): ?>
<h6>Riwayat Presensi</h6><div style="overflow-x:auto;"><table><thead><tr><th>Tgl</th><th>Siswa</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead><tbody><?php $sql_hist = "SELECT p.*, s.nama FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal BETWEEN '$start' AND '$end' ORDER BY p.tanggal DESC, s.nama"; $res_hist = $conn->query($sql_hist); while($rh = $res_hist->fetch_assoc()): ?><tr><td><?= formatTanggalID($rh['tanggal']) ?></td><td><?= $rh['nama'] ?></td><td><?= $rh['jam_masuk'] ?></td><td><?= $rh['jam_pulang'] ?></td><td><?= ucfirst($rh['status_approval']) ?></td></tr><?php endwhile; ?></tbody></table></div>
<h6 style="margin-top:20px;">Riwayat Izin</h6><div style="overflow-x:auto;"><table><thead><tr><th>Tgl</th><th>Siswa</th><th>Jenis</th><th>Ket</th><th>Status</th></tr></thead><tbody><?php $sql_iz_hist = "SELECT ai.*, s.nama FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND ai.tanggal BETWEEN '$start' AND '$end' ORDER BY ai.tanggal DESC, s.nama"; $res_iz_hist = $conn->query($sql_iz_hist); while($rih = $res_iz_hist->fetch_assoc()): ?><tr><td><?= formatTanggalID($rih['tanggal']) ?></td><td><?= $rih['nama'] ?></td><td><?= ucfirst($rih['jenis']) ?></td><td><?= $rih['keterangan'] ?></td><td><?= ucfirst($rih['status']) ?></td></tr><?php endwhile; ?></tbody></table></div>
<?php endif; ?><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'rekap'): ?>
<h5>Filter Rekapitulasi</h5>
<form method="GET" class="filter-bar">
<input type="hidden" name="page" value="dashboard_pembimbing"><input type="hidden" name="subtab" value="rekap">
<div><label>Tanggal Mulai</label><input type="date" name="start_rep" class="form-control" value="<?= isset($_GET['start_rep'])?$_GET['start_rep']:date('Y-m-01') ?>"></div>
<div><label>Tanggal Akhir</label><input type="date" name="end_rep" class="form-control" value="<?= isset($_GET['end_rep'])?$_GET['end_rep']:date('Y-m-d') ?>"></div>
<div class="btn-group"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
</form>
<?php if(isset($_GET['start_rep'])): $start_r = $_GET['start_rep']; $end_r = $_GET['end_rep']; $kelompok = $conn->query("SELECT kp.* FROM kelompok_pkl kp WHERE kp.id_industri_pembimbing = $uid")->fetch_assoc(); ?>
<?php if($kelompok): ?>
<?php $total_siswa = $conn->query("SELECT COUNT(*) as c FROM siswa WHERE kelompok_id={$kelompok['id']}")->fetch_assoc()['c']; $total_hadir = $conn->query("SELECT COUNT(DISTINCT nis) as c FROM presensi WHERE nis IN (SELECT nis FROM siswa WHERE kelompok_id={$kelompok['id']}) AND tanggal BETWEEN '$start_r' AND '$end_r'")->fetch_assoc()['c']; $total_izin = $conn->query("SELECT COUNT(*) as c FROM absensi_izin WHERE nis IN (SELECT nis FROM siswa WHERE kelompok_id={$kelompok['id']}) AND tanggal BETWEEN '$start_r' AND '$end_r' AND status='diterima'")->fetch_assoc()['c']; ?>
<div class="stats-row"><div class="stat-siswa"><b><?= $total_siswa ?></b><span>Total Siswa</span></div><div class="stat-hadir"><b><?= $total_hadir ?></b><span>Hadir (Unik)</span></div><div class="stat-izin"><b><?= $total_izin ?></b><span>Izin/Sakit</span></div></div>
<div class="stats-desktop"><div class="stat-box stat-siswa"><b><?= $total_siswa ?></b><span>Total Siswa</span></div><div class="stat-box stat-hadir"><b><?= $total_hadir ?></b><span>Hadir (Unik)</span></div><div class="stat-box stat-izin"><b><?= $total_izin ?></b><span>Izin/Sakit</span></div></div>
<h6>Rekap Harian</h6><div style="overflow-x:auto;"><table><thead><tr><th>Tanggal</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Tidak Hadir</th></tr></thead><tbody><?php $sql_rep = "SELECT p.tanggal, SUM(CASE WHEN p.status_masuk='tepat waktu' THEN 1 ELSE 0 END) as hadir, SUM(CASE WHEN p.status_masuk='terlambat' THEN 1 ELSE 0 END) as telat FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal BETWEEN '$start_r' AND '$end_r' GROUP BY p.tanggal ORDER BY p.tanggal DESC"; $res_rep = $conn->query($sql_rep); while($rr = $res_rep->fetch_assoc()): $tgl = $rr['tanggal']; $jml_izin = $conn->query("SELECT COUNT(*) FROM absensi_izin ai JOIN siswa s ON ai.nis=s.nis WHERE ai.tanggal='$tgl' AND s.kelompok_id={$kelompok['id']} AND ai.status='diterima'")->fetch_assoc()['COUNT(*)']; $jml_absen = $total_siswa - ($rr['hadir'] + $rr['telat'] + $jml_izin); ?><tr><td><?= formatTanggalID($tgl) ?></td><td style="text-align:center; color:green;"><b><?= $rr['hadir'] ?></b></td><td style="text-align:center; color:orange;"><b><?= $rr['telat'] ?></b></td><td style="text-align:center; color:blue;"><b><?= $jml_izin ?></b></td><td style="text-align:center; color:red;"><b><?= $jml_absen ?></b></td></tr><?php endwhile; ?></tbody></table></div>
<?php endif; ?><?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php else: ?>
<?php $kelompok = $conn->query("SELECT kp.* FROM kelompok_pkl kp WHERE kp.id_guru_pembimbing = $uid OR kp.id_industri_pembimbing = $uid")->fetch_assoc(); ?>
<?php if ($page == 'dashboard_pembimbing'): ?>
<div class="card" style="margin-top: 20px;">
<h3>Dashboard Pembimbing</h3>
<?php if($kelompok): ?><p>Kelompok: <b><?= $kelompok['nama_kelompok'] ?></b></p>
<div class="main-tabs" style="display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap;">
<a href="?page=dashboard_pembimbing&subtab=approval" class="tab <?= $pembimbingSubTab == 'approval' ? 'active' : '' ?>">Approval</a>
<a href="?page=dashboard_pembimbing&subtab=periode" class="tab <?= $pembimbingSubTab == 'periode' ? 'active' : '' ?>">Periode PKL</a>
<a href="?page=dashboard_pembimbing&subtab=jadwal" class="tab <?= $pembimbingSubTab == 'jadwal' ? 'active' : '' ?>">Jadwal Kerja</a>
<a href="?page=dashboard_pembimbing&subtab=libur" class="tab <?= $pembimbingSubTab == 'libur' ? 'active' : '' ?>">Periode Libur</a>
<a href="?page=dashboard_pembimbing&subtab=riwayat" class="tab <?= $pembimbingSubTab == 'riwayat' ? 'active' : '' ?>">Riwayat</a>
<a href="?page=dashboard_pembimbing&subtab=rekap" class="tab <?= $pembimbingSubTab == 'rekap' ? 'active' : '' ?>">Rekap</a>
</div>

<?php if ($pembimbingSubTab == 'approval' || empty($pembimbingSubTab)): ?>
<div class="approval-grid">
<div class="approval-col"><h5>Approval Presensi (Hari Ini)</h5>
<?php $today = date('Y-m-d'); $sql_presensi = "SELECT p.*, s.nama as nama_siswa FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal = '$today' AND p.status_approval = 'pending'"; $res_presensi = $conn->query($sql_presensi); if($res_presensi->num_rows > 0): ?>
<table><thead><tr><th>Siswa</th><th>Jam</th><th>Foto</th><th>Aksi</th></tr></thead><tbody><?php while($row = $res_presensi->fetch_assoc()): ?><tr><td><?= $row['nama_siswa'] ?></td><td><?= $row['jam_masuk'] ?></td><td><img src="uploads/foto/masuk/<?= $row['foto_masuk'] ?>" width="40" height="40" style="object-fit:cover;border-radius:4px;"></td><td><button class="btn btn-sm btn-success" onclick="approveModal('presensi', <?= $row['id'] ?>, 'approved')"><i class="fas fa-check"></i></button><button class="btn btn-sm btn-danger" onclick="approveModal('presensi', <?= $row['id'] ?>, 'rejected')"><i class="fas fa-times"></i></button></td></tr><?php endwhile; ?></tbody></table><?php else: ?><p class="empty-msg">Tidak ada presensi pending.</p><?php endif; ?>
</div>
<div class="approval-col"><h5>Approval Izin</h5>
<?php $sql_izin = "SELECT ai.*, s.nama as nama_siswa FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND ai.status = 'pending'"; $res_izin = $conn->query($sql_izin); if($res_izin->num_rows > 0): ?>
<table><thead><tr><th>Siswa</th><th>Jenis</th><th>Ket</th><th>Aksi</th></tr></thead><tbody><?php while($row = $res_izin->fetch_assoc()): ?><tr><td><?= $row['nama_siswa'] ?></td><td><?= $row['jenis'] ?></td><td><?= $row['keterangan'] ?></td><td><button class="btn btn-sm btn-success" onclick="approveModal('izin', <?= $row['id'] ?>, 'diterima')"><i class="fas fa-check"></i></button><button class="btn btn-sm btn-danger" onclick="approveModal('izin', <?= $row['id'] ?>, 'ditolak')"><i class="fas fa-times"></i></button></td></tr><?php endwhile; ?></tbody></table><?php else: ?><p class="empty-msg">Tidak ada izin pending.</p><?php endif; ?>
</div>
</div>

<?php elseif ($pembimbingSubTab == 'periode'): ?>
<h5>Periode PKL Aktif</h5>
<?php $periode_aktif = $conn->query("SELECT * FROM periode_pkl WHERE is_aktif = 1 LIMIT 1")->fetch_assoc(); ?>
<?php if($periode_aktif): ?>
<table><thead><tr><th>Nama Periode</th><th>Tingkat Kelas</th><th>Tahun Ajaran</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th></tr></thead><tbody><tr><td><?= $periode_aktif['nama_periode'] ?></td><td><?= $periode_aktif['tingkat_kelas'] ?></td><td><?= $periode_aktif['tahun_ajaran'] ?></td><td><?= formatTanggalID($periode_aktif['tanggal_mulai']) ?></td><td><?= formatTanggalID($periode_aktif['tanggal_selesai']) ?></td></tr></tbody></table>
<?php else: ?><p class="empty-msg">Belum ada periode PKL aktif.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'jadwal'): ?>
<h5>Jadwal Kerja Mingguan (Read Only)</h5>
<?php $jadwal_all = $conn->query("SELECT il.*, i.nama_industri FROM industri_libur il LEFT JOIN industri i ON il.industri_id = i.id ORDER BY i.nama_industri"); if($jadwal_all->num_rows > 0): ?>
<table><thead><tr><th>Industri</th><th>Nama Jadwal</th><th>Hari Aktif</th></tr></thead><tbody><?php while($ja = $jadwal_all->fetch_assoc()): ?><tr><td><?= $ja['nama_industri'] ?: '-' ?></td><td><?= $ja['nama_libur'] ?></td><td><?= $ja['hari_aktif'] ?></td></tr><?php endwhile; ?></tbody></table>
<?php else: ?><p class="empty-msg">Belum ada jadwal kerja.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'libur'): ?>
<h5>Periode Libur (Tanggal Spesifik)</h5>
<?php $libur_all = $conn->query("SELECT pl.*, i.nama_industri FROM periode_libur pl LEFT JOIN industri i ON pl.industri_id = i.id ORDER BY pl.tanggal_mulai DESC"); if($libur_all->num_rows > 0): ?>
<table><thead><tr><th>Nama Libur</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th><th>Industri</th></tr></thead><tbody><?php while($la = $libur_all->fetch_assoc()): ?><tr><td><?= $la['nama_libur'] ?></td><td><?= formatTanggalID($la['tanggal_mulai']) ?></td><td><?= formatTanggalID($la['tanggal_selesai']) ?></td><td><?= $la['nama_industri'] ?: 'Semua' ?></td></tr><?php endwhile; ?></tbody></table>
<?php else: ?><p class="empty-msg">Belum ada periode libur.</p><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'riwayat'): ?>
<h5>Filter Riwayat</h5>
<form method="GET" class="filter-bar">
<input type="hidden" name="page" value="dashboard_pembimbing"><input type="hidden" name="subtab" value="riwayat">
<div><label>Tanggal Mulai</label><input type="date" name="start_hist" class="form-control" value="<?= isset($_GET['start_hist'])?$_GET['start_hist']:date('Y-m-01') ?>"></div>
<div><label>Tanggal Akhir</label><input type="date" name="end_hist" class="form-control" value="<?= isset($_GET['end_hist'])?$_GET['end_hist']:date('Y-m-d') ?>"></div>
<div class="btn-group"><button type="submit" class="btn btn-primary">Filter</button></div>
</form>
<?php if(isset($_GET['start_hist'])): $start = $_GET['start_hist']; $end = $_GET['end_hist']; ?>
<h6>Riwayat Presensi</h6><div style="overflow-x:auto;"><table><thead><tr><th>Tgl</th><th>Siswa</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead><tbody><?php $sql_hist = "SELECT p.*, s.nama FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal BETWEEN '$start' AND '$end' ORDER BY p.tanggal DESC, s.nama"; $res_hist = $conn->query($sql_hist); while($rh = $res_hist->fetch_assoc()): ?><tr><td><?= formatTanggalID($rh['tanggal']) ?></td><td><?= $rh['nama'] ?></td><td><?= $rh['jam_masuk'] ?></td><td><?= $rh['jam_pulang'] ?></td><td><?= ucfirst($rh['status_approval']) ?></td></tr><?php endwhile; ?></tbody></table></div>
<h6 style="margin-top:20px;">Riwayat Izin</h6><div style="overflow-x:auto;"><table><thead><tr><th>Tgl</th><th>Siswa</th><th>Jenis</th><th>Ket</th><th>Status</th></tr></thead><tbody><?php $sql_iz_hist = "SELECT ai.*, s.nama FROM absensi_izin ai JOIN siswa s ON ai.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND ai.tanggal BETWEEN '$start' AND '$end' ORDER BY ai.tanggal DESC, s.nama"; $res_iz_hist = $conn->query($sql_iz_hist); while($rih = $res_iz_hist->fetch_assoc()): ?><tr><td><?= formatTanggalID($rih['tanggal']) ?></td><td><?= $rih['nama'] ?></td><td><?= ucfirst($rih['jenis']) ?></td><td><?= $rih['keterangan'] ?></td><td><?= ucfirst($rih['status']) ?></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>

<?php elseif ($pembimbingSubTab == 'rekap'): ?>
<h5>Filter Rekapitulasi</h5>
<form method="GET" class="filter-bar">
<input type="hidden" name="page" value="dashboard_pembimbing"><input type="hidden" name="subtab" value="rekap">
<div><label>Tanggal Mulai</label><input type="date" name="start_rep" class="form-control" value="<?= isset($_GET['start_rep'])?$_GET['start_rep']:date('Y-m-01') ?>"></div>
<div><label>Tanggal Akhir</label><input type="date" name="end_rep" class="form-control" value="<?= isset($_GET['end_rep'])?$_GET['end_rep']:date('Y-m-d') ?>"></div>
<div class="btn-group"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
</form>
<?php if(isset($_GET['start_rep'])): $start_r = $_GET['start_rep']; $end_r = $_GET['end_rep']; $total_siswa = $conn->query("SELECT COUNT(*) as c FROM siswa WHERE kelompok_id={$kelompok['id']}")->fetch_assoc()['c']; $total_hadir = $conn->query("SELECT COUNT(DISTINCT nis) as c FROM presensi WHERE nis IN (SELECT nis FROM siswa WHERE kelompok_id={$kelompok['id']}) AND tanggal BETWEEN '$start_r' AND '$end_r'")->fetch_assoc()['c']; $total_izin = $conn->query("SELECT COUNT(*) as c FROM absensi_izin WHERE nis IN (SELECT nis FROM siswa WHERE kelompok_id={$kelompok['id']}) AND tanggal BETWEEN '$start_r' AND '$end_r' AND status='diterima'")->fetch_assoc()['c']; ?>
<div class="stats-row"><div class="stat-siswa"><b><?= $total_siswa ?></b><span>Total Siswa</span></div><div class="stat-hadir"><b><?= $total_hadir ?></b><span>Hadir (Unik)</span></div><div class="stat-izin"><b><?= $total_izin ?></b><span>Izin/Sakit</span></div></div>
<div class="stats-desktop"><div class="stat-box stat-siswa"><b><?= $total_siswa ?></b><span>Total Siswa</span></div><div class="stat-box stat-hadir"><b><?= $total_hadir ?></b><span>Hadir (Unik)</span></div><div class="stat-box stat-izin"><b><?= $total_izin ?></b><span>Izin/Sakit</span></div></div>
<h6>Rekap Harian</h6><div style="overflow-x:auto;"><table><thead><tr><th>Tanggal</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Tidak Hadir</th></tr></thead><tbody><?php $sql_rep = "SELECT p.tanggal, SUM(CASE WHEN p.status_masuk='tepat waktu' THEN 1 ELSE 0 END) as hadir, SUM(CASE WHEN p.status_masuk='terlambat' THEN 1 ELSE 0 END) as telat FROM presensi p JOIN siswa s ON p.nis = s.nis WHERE s.kelompok_id = {$kelompok['id']} AND p.tanggal BETWEEN '$start_r' AND '$end_r' GROUP BY p.tanggal ORDER BY p.tanggal DESC"; $res_rep = $conn->query($sql_rep); while($rr = $res_rep->fetch_assoc()): $tgl = $rr['tanggal']; $jml_izin = $conn->query("SELECT COUNT(*) FROM absensi_izin ai JOIN siswa s ON ai.nis=s.nis WHERE ai.tanggal='$tgl' AND s.kelompok_id={$kelompok['id']} AND ai.status='diterima'")->fetch_assoc()['COUNT(*)']; $jml_absen = $total_siswa - ($rr['hadir'] + $rr['telat'] + $jml_izin); ?><tr><td><?= formatTanggalID($tgl) ?></td><td style="text-align:center; color:green;"><b><?= $rr['hadir'] ?></b></td><td style="text-align:center; color:orange;"><b><?= $rr['telat'] ?></b></td><td style="text-align:center; color:blue;"><b><?= $jml_izin ?></b></td><td style="text-align:center; color:red;"><b><?= $jml_absen ?></b></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
<?php endif; ?>
<?php else: ?><p>Anda tidak memiliki kelompok bimbingan.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<div class="bottom-nav">
<a href="?page=dashboard_pembimbing" class="nav-item <?= $page=='dashboard_pembimbing'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
<?php if($_SESSION['role'] == 'guru' && !empty($_SESSION['kelas_id'])): ?>
<a href="?page=dashboard_walikelas" class="nav-item <?= $page=='dashboard_walikelas'?'active':'' ?>"><i class="fas fa-user-graduate"></i> Wali Kelas</a>
<?php endif; ?>
<a href="?page=logout" class="nav-item"><i class="fas fa-sign-out-alt"></i> Keluar</a>
</div>
<?php endif; ?>

<!-- DASHBOARD WALI KELAS -->
<?php if (isset($_SESSION['role']) && $page == 'dashboard_walikelas'): ?>
<?php 
$walikelas_start = isset($_GET['wl_start']) ? $_GET['wl_start'] : date('Y-m-01');
$walikelas_end = isset($_GET['wl_end']) ? $_GET['wl_end'] : date('Y-m-d');
?>
<div class="card" style="margin-top:20px;">
<h4>Rekap Wali Kelas (Read Only)</h4>
<p>Kelas ID: <b><?= $_SESSION['kelas_id'] ?></b></p>
<form method="GET" class="filter-bar">
<input type="hidden" name="page" value="dashboard_walikelas">
<div><label>Tanggal Mulai</label><input type="date" name="wl_start" class="form-control" value="<?= $walikelas_start ?>"></div>
<div><label>Tanggal Akhir</label><input type="date" name="wl_end" class="form-control" value="<?= $walikelas_end ?>"></div>
<div class="btn-group"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
</form>
<table><thead><tr><th>NIS</th><th>Nama</th><th>Kelompok</th><th>Hadir</th><th>Izin</th></tr></thead><tbody><?php $cid = $_SESSION['kelas_id']; $sql = "SELECT s.*, kp.nama_kelompok, (SELECT COUNT(*) FROM presensi p WHERE p.nis=s.nis AND p.tanggal BETWEEN '$walikelas_start' AND '$walikelas_end') as jml_hadir, (SELECT COUNT(*) FROM absensi_izin a WHERE a.nis=s.nis AND a.tanggal BETWEEN '$walikelas_start' AND '$walikelas_end' AND a.status='diterima') as jml_izin FROM siswa s LEFT JOIN kelompok_pkl kp ON s.kelompok_id = kp.id WHERE s.kelas_id = $cid ORDER BY s.nama"; $res = $conn->query($sql); while($r = $res->fetch_assoc()): ?><tr><td><?= $r['nis'] ?></td><td><?= $r['nama'] ?></td><td><?= $r['nama_kelompok'] ?: '-' ?></td><td><?= $r['jml_hadir'] ?></td><td><?= $r['jml_izin'] ?></td></tr><?php endwhile; ?></tbody></table>
</div>
<?php endif; ?>

<!-- DASHBOARD ADMIN -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin' && $page == 'admin'): ?>
<?php
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'master';
$activeSubTab = isset($_GET['subtab']) ? $_GET['subtab'] : 'kelas';
?>
<div class="card" style="margin-top: 20px;">
<div class="admin-menu-wrapper">
<div class="main-tabs">
<a href="?page=admin&tab=master" class="tab <?= $activeTab == 'master' ? 'active' : '' ?>">Master</a>
<a href="?page=admin&tab=periode" class="tab <?= $activeTab == 'periode' ? 'active' : '' ?>">Periode PKL</a>
<a href="?page=admin&tab=pembimbing" class="tab <?= $activeTab == 'pembimbing' ? 'active' : '' ?>">Alokasi Pembimbing</a>
<a href="?page=admin&tab=alokasi" class="tab <?= $activeTab == 'alokasi' ? 'active' : '' ?>">Alokasi Siswa PKL</a>
<a href="?page=admin&tab=libur" class="tab <?= $activeTab == 'libur' ? 'active' : '' ?>">Jadwal Libur</a>
<a href="?page=admin&tab=rekap" class="tab <?= $activeTab == 'rekap' ? 'active' : '' ?>">Rekap Global</a>
</div>
<?php if ($activeTab == 'master'): ?>
<div class="main-tabs" style="gap: 5px; padding-bottom: 0; border-bottom: none; margin-top: 10px;">
<a href="?page=admin&tab=master&subtab=kelas" class="tab <?= $activeSubTab == 'kelas' ? 'active' : '' ?>" style="min-width: 100px;">Kelas</a>
<a href="?page=admin&tab=master&subtab=siswa" class="tab <?= $activeSubTab == 'siswa' ? 'active' : '' ?>" style="min-width: 100px;">Siswa</a>
<a href="?page=admin&tab=master&subtab=user" class="tab <?= $activeSubTab == 'user' ? 'active' : '' ?>" style="min-width: 100px;">User</a>
<a href="?page=admin&tab=master&subtab=industri" class="tab <?= $activeSubTab == 'industri' ? 'active' : '' ?>" style="min-width: 100px;">Industri</a>
</div>
<?php elseif ($activeTab == 'libur'): ?>
<div class="main-tabs" style="gap: 5px; padding-bottom: 0; border-bottom: none; margin-top: 10px;">
<a href="?page=admin&tab=libur&subtab=jadwal" class="tab <?= $activeSubTab == 'jadwal' ? 'active' : '' ?>" style="min-width: 130px;">Jadwal Kerja</a>
<a href="?page=admin&tab=libur&subtab=periode" class="tab <?= $activeSubTab == 'periode' ? 'active' : '' ?>" style="min-width: 130px;">Periode Libur</a>
</div>
<?php endif; ?>
</div>

<!-- Sub-Tabs Mobile -->
<?php if ($activeTab == 'master'): ?>
<div class="admin-sub-tabs">
<a href="?page=admin&tab=master&subtab=kelas" class="tab <?= $activeSubTab == 'kelas' ? 'active' : '' ?>">Kelas</a>
<a href="?page=admin&tab=master&subtab=siswa" class="tab <?= $activeSubTab == 'siswa' ? 'active' : '' ?>">Siswa</a>
<a href="?page=admin&tab=master&subtab=user" class="tab <?= $activeSubTab == 'user' ? 'active' : '' ?>">User</a>
<a href="?page=admin&tab=master&subtab=industri" class="tab <?= $activeSubTab == 'industri' ? 'active' : '' ?>">Industri</a>
</div>
<?php elseif ($activeTab == 'libur'): ?>
<div class="admin-sub-tabs">
<a href="?page=admin&tab=libur&subtab=jadwal" class="tab <?= $activeSubTab == 'jadwal' ? 'active' : '' ?>">Jadwal Kerja</a>
<a href="?page=admin&tab=libur&subtab=periode" class="tab <?= $activeSubTab == 'periode' ? 'active' : '' ?>">Periode Libur</a>
</div>
<?php endif; ?>

<div class="bottom-nav-admin">
<a href="?page=admin&tab=periode" class="<?= $activeTab=='periode'?'active':'' ?>"><i class="fas fa-calendar"></i> Periode</a>
<a href="?page=admin&tab=pembimbing" class="<?= $activeTab=='pembimbing'?'active':'' ?>"><i class="fas fa-user-tie"></i> Pembimbing</a>
<a href="?page=admin&tab=alokasi" class="<?= $activeTab=='alokasi'?'active':'' ?>"><i class="fas fa-users"></i> Alokasi Siswa</a>
<a href="?page=admin&tab=master" class="<?= $activeTab=='master'?'active':'' ?>"><i class="fas fa-database"></i> Master</a>
<a href="?page=admin&tab=libur" class="<?= $activeTab=='libur'?'active':'' ?>"><i class="fas fa-calendar-times"></i> Libur</a>
<a href="?page=admin&tab=rekap" class="<?= $activeTab=='rekap'?'active':'' ?>"><i class="fas fa-chart-line"></i> Rekap</a>
</div>

<!-- KONTEN PERIODE PKL -->
<?php if ($activeTab == 'master'): ?>
<?php if ($activeSubTab == 'kelas'): ?>
<div class="content-header"><button type="button" class="btn btn-primary" onclick="openModal('modalKelas')">+ Tambah Kelas</button></div>
<div class="data-table-wrapper">
<form method="POST" id="bulkFormKelas"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="kelas"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="kelas">
<table><thead><tr><th><input type="checkbox" id="checkAllKelas"></th><th>Nama Kelas</th><th>Jurusan</th><th>Aksi</th></tr></thead><tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT * FROM kelas LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM kelas")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr><td><input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>"></td><td><b><?= $r['nama_kelas'] ?></b></td><td><?= $r['jurusan'] ?></td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditKelas(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&delete_kelas=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
<button type="submit" class="btn btn-danger">Hapus Terpilih</button>
</form>
</div>
<?= showPagination($pg, $total, $limit, "?page=admin&tab=master&subtab=kelas") ?>
<?php endif; ?>

<?php if ($activeSubTab == 'siswa'): ?>
<div class="content-header"><button type="button" class="btn btn-primary" onclick="openModal('modalSiswa')">+ Tambah Siswa</button></div>
<div class="data-table-wrapper">
<form method="POST" id="bulkFormSiswa"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="siswa"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="siswa">
<table><thead><tr><th><input type="checkbox" id="checkAllSiswa"></th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Aksi</th></tr></thead><tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT s.*, k.nama_kelas, k.jurusan FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM siswa")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr><td><input type="checkbox" name="selected_ids[]" value="<?= $r['nis'] ?>"></td><td><?= $r['nis'] ?></td><td><?= $r['nama'] ?></td><td><?= $r['nama_kelas'] ? $r['nama_kelas'].' ('.$r['jurusan'].')' : '-' ?></td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditSiswa(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&delete_siswa=<?= $r['nis'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
<button type="submit" class="btn btn-danger">Hapus Terpilih</button>
</form>
</div>
<?= showPagination($pg, $total, $limit, "?page=admin&tab=master&subtab=siswa") ?>
<?php endif; ?>

<?php if ($activeSubTab == 'user'): ?>
<div class="content-header"><button type="button" class="btn btn-primary" onclick="setUserModalSource('master','user');openModal('modalUser')">+ Tambah User</button></div>
<div class="data-table-wrapper">
<form method="POST" id="bulkFormUser"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="users"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="user">
<table><thead><tr><th><input type="checkbox" id="checkAllUser"></th><th>Username</th><th>Nama</th><th>Role</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; 
$q = $conn->query("SELECT u.*, k.nama_kelas, k.jurusan, i.nama_industri FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id LEFT JOIN industri i ON u.industri_id = i.id WHERE u.role IN ('guru','industri') ORDER BY u.role, u.nama_lengkap LIMIT $limit OFFSET $offset"); 
$total = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('guru','industri')")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): 
$ket = '';
if ($r['role'] == 'guru' && $r['nama_kelas']) $ket = 'Wali: ' . $r['nama_kelas'] . ' (' . $r['jurusan'] . ')';
elseif ($r['role'] == 'industri' && $r['nama_industri']) $ket = 'Industri: ' . $r['nama_industri'];
?>
<tr><td><input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>"></td><td><?= $r['username'] ?></td><td><?= $r['nama_lengkap'] ?></td><td><?= ucfirst($r['role']) ?></td><td><?= $ket ?: '-' ?></td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditUser(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&delete_user=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
<button type="submit" class="btn btn-danger">Hapus Terpilih</button>
</form>
</div>
<?= showPagination($pg, $total, $limit, "?page=admin&tab=master&subtab=user") ?>
<?php endif; ?>

<?php if ($activeSubTab == 'industri'): ?>
<div class="content-header"><button type="button" class="btn btn-primary" onclick="openModal('modalIndustri')">+ Tambah Industri</button></div>
<div class="data-table-wrapper">
<form method="POST" id="bulkFormIndustri"><input type="hidden" name="action_bulk_delete" value="1"><input type="hidden" name="table_name" value="industri"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="industri">
<table><thead><tr><th><input type="checkbox" id="checkAllIndustri"></th><th>Nama Industri</th><th>Alamat</th><th>Pembimbing</th><th>Radius</th><th>Aksi</th></tr></thead><tbody>
<?php $limit = 10; $pg = isset($_GET['pg']) ? $_GET['pg'] : 1; $offset = ($pg - 1) * $limit; $q = $conn->query("SELECT * FROM industri LIMIT $limit OFFSET $offset"); $total = $conn->query("SELECT COUNT(*) as cnt FROM industri")->fetch_assoc()['cnt']; while($r = $q->fetch_assoc()): ?>
<tr><td><input type="checkbox" name="selected_ids[]" value="<?= $r['id'] ?>"></td><td><b><?= $r['nama_industri'] ?></b></td><td><?= $r['alamat'] ?: '-' ?></td><td><?= $r['nama_pembimbing'] ?: '-' ?></td><td><?= $r['radius'] ?> m</td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditIndustri(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&delete_industri=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
<button type="submit" class="btn btn-danger">Hapus Terpilih</button>
</form>
</div>
<?= showPagination($pg, $total, $limit, "?page=admin&tab=master&subtab=industri") ?>
<?php endif; ?>

<?php elseif ($activeTab == 'alokasi'): ?>
<div class="alokasi-controls">
<h5><i class="fas fa-users-cog"></i> Alokasi Siswa ke Kelompok PKL</h5>
<p style="font-size:12px;color:#666;">Pilih kelompok untuk menambahkan/memindahkan siswa</p>
<select id="selectKelompokAlokasi" class="form-control" onchange="loadAllocation()">
<option value="">-- Pilih Kelompok --</option>
<?php $qk = $conn->query("SELECT kp.*, i.nama_industri, p.nama_periode FROM kelompok_pkl kp LEFT JOIN industri i ON kp.id_industri = i.id LEFT JOIN periode_pkl p ON kp.id_periode = p.id ORDER BY kp.nama_kelompok ASC"); while($rk = $qk->fetch_assoc()): ?>
<option value="<?= $rk['id'] ?>"><?= $rk['nama_kelompok'] ?> - <?= $rk['nama_industri'] ?> (<?= $rk['nama_periode'] ?>)</option>
<?php endwhile; ?>
</select>
</div>
<div id="allocationArea" style="display:none;"></div>

<?php elseif ($activeTab == 'pembimbing'): ?>
<button type="button" class="btn btn-primary" onclick="openModal('modalKelompok')" style="margin-bottom:10px;">+ Tambah Alokasi Pembimbing</button>
<div class="data-table-wrapper">
<table><thead><tr><th>Nama Kelompok</th><th>Periode</th><th>Industri</th><th>Pembimbing Sekolah</th><th>Pembimbing Industri</th><th>Aksi</th></tr></thead><tbody>
<?php $q = $conn->query("SELECT kp.*, p.nama_periode, i.nama_industri, u1.nama_lengkap as guru, u2.nama_lengkap as industri FROM kelompok_pkl kp LEFT JOIN periode_pkl p ON kp.id_periode = p.id LEFT JOIN industri i ON kp.id_industri = i.id LEFT JOIN users u1 ON kp.id_guru_pembimbing = u1.id LEFT JOIN users u2 ON kp.id_industri_pembimbing = u2.id ORDER BY kp.id DESC"); while($r = $q->fetch_assoc()): ?>
<tr><td><?= $r['nama_kelompok'] ?></td><td><?= $r['nama_periode'] ?: '-' ?></td><td><?= $r['nama_industri'] ?: '-' ?></td><td><?= $r['guru'] ?: '-' ?></td><td><?= $r['industri'] ?: '-' ?></td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditKelompok(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&delete_kelompok=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
</div>

<?php elseif ($activeTab == 'periode'): ?>
<button type="button" class="btn btn-primary" onclick="openModal('modalPeriodePKL')" style="margin-bottom:10px;">+ Tambah Periode PKL</button>
<div class="data-table-wrapper">
<table><thead><tr><th>Nama Periode</th><th>Tingkat</th><th>Tahun Ajaran</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php $q = $conn->query("SELECT * FROM periode_pkl ORDER BY is_aktif DESC, tanggal_mulai DESC"); while($r = $q->fetch_assoc()): ?>
<tr><td><b><?= $r['nama_periode'] ?></b></td><td><?= $r['tingkat_kelas'] ?: '-' ?></td><td><?= $r['tahun_ajaran'] ?: '-' ?></td><td><?= formatTanggalID($r['tanggal_mulai']) ?></td><td><?= formatTanggalID($r['tanggal_selesai']) ?></td><td><?php if($r['is_aktif']): ?><span style="background:green;color:white;padding:3px 8px;border-radius:4px;">Aktif</span><?php else: ?>-<?php endif; ?></td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditPeriodePKL(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&delete_periode_pkl=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
</div>
<?php $aktif = $conn->query("SELECT * FROM periode_pkl WHERE is_aktif=1")->fetch_assoc(); if($aktif): ?>
<div style="background:#d1fae5;padding:15px;border-radius:8px;margin-top:15px;"><b>Periode PKL Aktif:</b> <?= $aktif['nama_periode'] ?> - <?= $aktif['tingkat_kelas'] ?> - <?= $aktif['tahun_ajaran'] ?> (<?= formatTanggalID($aktif['tanggal_mulai']) ?> - <?= formatTanggalID($aktif['tanggal_selesai']) ?>)</div>
<?php endif; ?>

<?php elseif ($activeTab == 'libur'): ?>
<?php if ($activeSubTab == 'jadwal' || empty($activeSubTab)): ?>
<button type="button" class="btn btn-primary" onclick="openModal('modalIndustriLibur')" style="margin-bottom:10px;">+ Tambah Jadwal Kerja</button>
<div class="data-table-wrapper">
<h5>Jadwal Kerja Mingguan (Hari Aktif per Industri)</h5>
<table><thead><tr><th>Industri</th><th>Nama Jadwal</th><th>Hari Aktif</th><th>Aksi</th></tr></thead><tbody>
<?php $q = $conn->query("SELECT il.*, i.nama_industri FROM industri_libur il LEFT JOIN industri i ON il.industri_id = i.id ORDER BY i.nama_industri"); while($r = $q->fetch_assoc()): ?>
<tr><td><b><?= $r['nama_industri'] ?: '-' ?></b></td><td><?= $r['nama_libur'] ?></td><td><?= $r['hari_aktif'] ?></td><td><button type="button" class="btn btn-sm btn-warning" onclick='openEditIndustriLibur(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?page=admin&tab=libur&subtab=jadwal&action=delete_industri_libur&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
</div>
<p style="color:#666;font-size:12px;margin-top:10px;">* Contoh: Libur Mingguan = hari_aktif="Senin,Selasa,Rabu,Kamis,Jumat" (Sabtu & Minggu libur)</p>

<?php elseif ($activeSubTab == 'periode'): ?>
<button type="button" class="btn btn-primary" onclick="openModal('modalPeriodeLiburAdmin')" style="margin-bottom:10px;">+ Tambah Periode Libur</button>
<div class="data-table-wrapper">
<h5>Periode Libur (Tanggal Spesifik)</h5>
<table><thead><tr><th>Nama Libur</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th><th>Industri</th><th>Aksi</th></tr></thead><tbody>
<?php $qp = $conn->query("SELECT pl.*, i.nama_industri FROM periode_libur pl LEFT JOIN industri i ON pl.industri_id = i.id ORDER BY pl.tanggal_mulai DESC"); while($rp = $qp->fetch_assoc()): ?>
<tr><td><b><?= $rp['nama_libur'] ?></b></td><td><?= formatTanggalID($rp['tanggal_mulai']) ?></td><td><?= formatTanggalID($rp['tanggal_selesai']) ?></td><td><?= $rp['nama_industri'] ?: 'Semua Industri' ?></td><td><a href="?page=admin&tab=libur&subtab=periode&action=delete_periode_libur&id=<?= $rp['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; ?>
</tbody></table>
</div>
<?php endif; ?>

<?php elseif ($activeTab == 'rekap'): ?>
<form method="GET" class="filter-bar filter-rekap" style="gap: 8px; padding: 10px;">
<input type="hidden" name="page" value="admin"><input type="hidden" name="tab" value="rekap">
<div style="flex: 1; min-width: 120px;"><label style="font-size: 11px;">Mulai</label><input type="date" name="start_date" class="form-control" value="<?= isset($_GET['start_date'])?$_GET['start_date']:date('Y-m-01') ?>"></div>
<div style="flex: 1; min-width: 120px;"><label style="font-size: 11px;">Akhir</label><input type="date" name="end_date" class="form-control" value="<?= isset($_GET['end_date'])?$_GET['end_date']:date('Y-m-d') ?>"></div>
<div class="btn-group" style="align-self: flex-end;"><button type="submit" class="btn btn-primary" style="padding: 8px 12px; font-size: 12px;">Tampilkan</button></div>
</form>
<?php if(isset($_GET['start_date'])): $start = $_GET['start_date']; $end = $_GET['end_date']; ?>
<div class="data-table-wrapper"><table><thead><tr><th>Tanggal</th><th>Industri</th><th>Hadir</th><th>Terlambat</th><th>Izin</th></tr></thead><tbody><?php $sql = "SELECT p.tanggal, kp.nama_kelompok, SUM(CASE WHEN p.status_masuk='tepat waktu' THEN 1 ELSE 0 END) as hadir_tepat, SUM(CASE WHEN p.status_masuk='terlambat' THEN 1 ELSE 0 END) as terlambat FROM presensi p JOIN siswa s ON p.nis = s.nis LEFT JOIN kelompok_pkl kp ON s.kelompok_id = kp.id WHERE p.tanggal BETWEEN '$start' AND '$end' GROUP BY p.tanggal, kp.nama_kelompok ORDER BY p.tanggal DESC"; $res = $conn->query($sql); while($row = $res->fetch_assoc()): ?><tr><td><?= formatTanggalID($row['tanggal']) ?></td><td><?= $row['nama_kelompok'] ?: '-' ?></td><td style="color:green;"><?= $row['hadir_tepat'] ?></td><td style="color:orange;"><?= $row['terlambat'] ?></td><td style="color:blue;">0</td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
<?php endif; ?>
</div>
<?php endif; ?>
</div>

<!-- MODALS -->
<div id="modalApprove" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalApprove')">&times;</span><h4>Konfirmasi Approval</h4><form method="POST"><input type="hidden" name="action_approve" value="1"><input type="hidden" name="type" id="appType"><input type="hidden" name="id" id="appId"><input type="hidden" name="status" id="appStatus"><div class="form-group"><textarea id="appNote" name="catatan" class="form-control" rows="3" placeholder="Catatan (Opsional)..."></textarea></div><button type="submit" class="btn btn-primary" style="width:100%;">Proses</button></form></div></div>

<!-- Modal Kelas -->
<div id="modalKelas" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalKelas')">&times;</span><h4 id="modalKelasTitle">Tambah Kelas</h4><form method="POST"><input type="hidden" name="action_kelas" value="add"><input type="hidden" name="id_kelas" id="kelasId"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="kelas"><div class="form-group"><input type="text" name="nama_kelas" id="kelasNama" class="form-control" placeholder="Contoh: XI TKJ 1" required></div><div class="form-group"><input type="text" name="jurusan" id="kelasJurusan" class="form-control" placeholder="Contoh: Teknik Komputer Jaringan" required></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan Kelas</button></form></div></div>

<!-- Modal Alokasi Pembimbing -->
<div id="modalKelompok" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalKelompok')">&times;</span><h4 id="modalKelompokTitle">Tambah Alokasi Pembimbing</h4><form method="POST"><input type="hidden" name="action_kelompok" value="add"><input type="hidden" name="id_kel" id="kelId"><input type="hidden" name="tab" value="pembimbing"><div class="form-group"><input type="text" name="nama_kelompok" id="kelNama" class="form-control" placeholder="Nama Kelompok (Contoh: XI TKJ 1 - PKL 2025)" required></div><div class="form-group"><select name="id_periode" id="kelPeriode" class="form-control"><option value="">Pilih Periode PKL (Opsional)</option><?php $p = $conn->query("SELECT * FROM periode_pkl ORDER BY is_aktif DESC, tanggal_mulai DESC"); while($rp=$p->fetch_assoc()) echo "<option value='{$rp['id']}'>{$rp['nama_periode']} ({$rp['tanggal_mulai']} - {$rp['tanggal_selesai']})</option>"; ?></select></div><div class="form-group"><select name="id_industri" id="kelIndustri" class="form-control"><option value="">Pilih Industri (Opsional)</option><?php $i = $conn->query("SELECT * FROM industri ORDER BY nama_industri ASC"); while($ri=$i->fetch_assoc()) echo "<option value='{$ri['id']}'>{$ri['nama_industri']}</option>"; ?></select></div><div class="form-group"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><label style="margin:0;">Pilih Pembimbing Sekolah (Opsional)</label><button type="button" class="btn btn-sm btn-secondary" onclick="setUserModalSource('master','user');openModal('modalUser');document.getElementById('userRole').value='guru';document.getElementById('userRole').dispatchEvent(new Event('change'));">+ Tambah Pembimbing</button></div><select name="id_guru" id="kelGuru" class="form-control"><option value="">Pilih Pembimbing Sekolah (Opsional)</option><?php $g = $conn->query("SELECT u.*, k.nama_kelas, k.jurusan FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id WHERE u.role='guru' ORDER BY u.nama_lengkap ASC"); while($rg=$g->fetch_assoc()) { $label = $rg['nama_lengkap']; if($rg['nama_kelas']) $label .= ' - ' . $rg['nama_kelas'] . ' (' . $rg['jurusan'] . ')'; echo "<option value='{$rg['id']}'>{$label}</option>"; } ?></select></div><div class="form-group"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><label style="margin:0;">Pilih Pembimbing Industri (Opsional)</label><button type="button" class="btn btn-sm btn-secondary" onclick="openModal('modalUser');document.getElementById('userRole').value='industri';document.getElementById('userRole').dispatchEvent(new Event('change'));">+ Tambah Pembimbing</button></div><select name="id_industri_pembimbing" id="kelInd" class="form-control"><option value="">Pilih Pembimbing Industri (Opsional)</option><?php $ind = $conn->query("SELECT u.*, i.nama_industri FROM users u LEFT JOIN industri i ON u.nama_lengkap = i.nama_pembimbing WHERE u.role='industri' ORDER BY u.nama_lengkap ASC"); while($rind=$ind->fetch_assoc()) { $label = $rind['nama_lengkap']; if($rind['nama_industri']) $label .= ' - ' . $rind['nama_industri']; echo "<option value='{$rind['id']}'>{$label}</option>"; } ?></select></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal User -->
<div id="modalUser" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalUser')">&times;</span><h4 id="modalUserTitle">Tambah User</h4><form method="POST" id="formUser"><input type="hidden" name="action_user" value="add"><input type="hidden" name="id_user" id="userId"><input type="hidden" name="tab" id="userTab" value="master"><input type="hidden" name="subtab" id="userSubtab" value="user"><div class="form-group"><input type="text" name="username" id="userUsername" class="form-control" placeholder="Username" required></div><div class="form-group"><input type="text" name="nama_lengkap" id="userNama" class="form-control" placeholder="Nama Lengkap" required></div><div class="form-group"><select name="role" id="userRole" class="form-control" required onchange="toggleUserFields()"><option value="guru">Guru</option><option value="industri">Industri</option></select></div><div class="form-group" id="fieldGuru"><select name="kelas_id" id="userKelasId" class="form-control"><option value="">Pilih Kelas (Wali Kelas)</option><?php $qk = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC"); while($rk=$qk->fetch_assoc()) echo "<option value='{$rk['id']}'>{$rk['nama_kelas']} ({$rk['jurusan']})</option>"; ?></select></div><div class="form-group" id="fieldIndustri" style="display:none;"><select name="industri_id" id="userIndustriId" class="form-control"><option value="">Pilih Industri</option><?php $qi = $conn->query("SELECT * FROM industri ORDER BY nama_industri ASC"); while($ri=$qi->fetch_assoc()) echo "<option value='{$ri['id']}'>{$ri['nama_industri']}</option>"; ?></select></div><div class="form-group"><input type="password" name="password" id="userPass" class="form-control" placeholder="Password (Isi untuk baru/ganti)"></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal Siswa -->
<div id="modalSiswa" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalSiswa')">&times;</span><h4 id="modalSiswaTitle">Tambah Siswa</h4><form method="POST"><input type="hidden" name="action_siswa" value="add"><input type="hidden" name="old_nis" id="siswaOldNis"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="siswa"><div class="form-group"><input type="text" name="nis" id="siswaNis" class="form-control" placeholder="NIS" required></div><div class="form-group"><input type="text" name="nama" id="siswaNama" class="form-control" placeholder="Nama Lengkap" required></div><div class="form-group"><select name="kelas_id" id="siswaKelasId" class="form-control"><option value="">Pilih Kelas</option><?php $qk = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC"); while($rk=$qk->fetch_assoc()) echo "<option value='{$rk['id']}'>{$rk['nama_kelas']} - {$rk['jurusan']}</option>"; ?></select></div><div class="form-group"><small style="color:#666;">Alokasi Kelompok PKL diatur melalui menu <b>Alokasi PKL</b>.</small></div><div class="form-group"><input type="password" name="password" id="siswaPass" class="form-control" placeholder="Password (Isi untuk baru/ganti)"></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal Industri -->
<div id="modalIndustri" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalIndustri')">&times;</span><h4 id="modalIndustriTitle">Tambah Industri</h4><form method="POST"><input type="hidden" name="action_industri" value="add"><input type="hidden" name="id_industri" id="industriId"><input type="hidden" name="tab" value="master"><input type="hidden" name="subtab" value="industri"><div class="form-group"><input type="text" name="nama_industri" id="industriNama" class="form-control" placeholder="Nama Industri" required></div><div class="form-group"><textarea name="alamat" id="industriAlamat" class="form-control" placeholder="Alamat"></textarea></div><div class="form-group"><input type="text" name="no_telp" id="industriTelp" class="form-control" placeholder="No. Telepon"></div><div class="form-group"><input type="email" name="email" id="industriEmail" class="form-control" placeholder="Email"></div><div class="form-group"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><label style="margin:0;">Nama Pembimbing Industri</label><button type="button" class="btn btn-sm btn-secondary" onclick="setUserModalSource('master','user');openModal('modalUser');document.getElementById('userRole').value='industri';document.getElementById('userRole').dispatchEvent(new Event('change'));">+ Tambah User</button></div><select name="nama_pembimbing" id="industriPembimbing" class="form-control"><option value="">Pilih Pembimbing (Opsional)</option><?php $up = $conn->query("SELECT * FROM users WHERE role='industri' ORDER BY nama_lengkap ASC"); while($rup=$up->fetch_assoc()) echo "<option value='{$rup['nama_lengkap']}'>{$rup['nama_lengkap']}</option>"; ?></select></div><div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;"><div class="form-group"><input type="text" name="latitude" id="industriLat" class="form-control" placeholder="Latitude"></div><div class="form-group"><input type="text" name="longitude" id="industriLng" class="form-control" placeholder="Longitude"></div><div class="form-group"><input type="number" name="radius" id="industriRadius" class="form-control" placeholder="Radius" value="100"></div></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal Periode PKL -->
<div id="modalPeriodePKL" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalPeriodePKL')">&times;</span><h4 id="modalPeriodePKLTitle">Tambah Periode PKL</h4><form method="POST"><input type="hidden" name="action_periode_pkl" value="add"><input type="hidden" name="id_periode_pkl" id="periodePKLId"><input type="hidden" name="tab" value="periode">
<div class="form-group"><input type="text" name="nama_periode" id="periodePKLNama" class="form-control" placeholder="Contoh: PKL 2025" required></div>
<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
<div class="form-group"><select name="tingkat_kelas" id="periodePKLTingkat" class="form-control" required><option value="">Pilih Tingkat</option><option value="XII">XII</option><option value="XI">XI</option></select></div>
<div class="form-group"><input type="text" name="tahun_ajaran" id="periodePKLTahun" class="form-control" placeholder="Contoh: 2025/2026" required></div>
</div>
<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
<div class="form-group"><input type="date" name="tanggal_mulai" id="periodePKLMulai" class="form-control" required></div>
<div class="form-group"><input type="date" name="tanggal_selesai" id="periodePKLSelesai" class="form-control" required></div>
</div>
<div class="form-group"><textarea name="keterangan" id="periodePKLKet" class="form-control" rows="2" placeholder="Keterangan (Opsional)"></textarea></div>
<div class="form-group"><label><input type="checkbox" name="is_aktif" id="periodePKLAktif" value="1"> Jadikan periode aktif</label></div>
<button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal Industri Libur -->
<div id="modalIndustriLibur" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalIndustriLibur')">&times;</span><h4 id="modalIndustriLiburTitle">Tambah Jadwal Libur</h4><form method="POST"><input type="hidden" name="action_industri_libur" value="add"><input type="hidden" name="id_industri_libur" id="industriLiburId"><input type="hidden" name="tab" value="libur"><div class="form-group"><select name="industri_id" id="industriLiburIndustriId" class="form-control" required><option value="">Pilih Industri</option><?php $qi = $conn->query("SELECT * FROM industri ORDER BY nama_industri ASC"); while($ri=$qi->fetch_assoc()) echo "<option value='{$ri['id']}'>{$ri['nama_industri']}</option>"; ?></select></div><div class="form-group"><input type="text" name="nama_libur" id="industriLiburNama" class="form-control" placeholder="Contoh: Libur Mingguan" required></div><div class="form-group"><label>Pilih hari aktif (presensi aktif):</label><div style="display:grid; grid-template-columns: repeat(4,1fr); gap:5px; margin-top:5px;"><label><input type="checkbox" name="hari_aktif[]" value="Senin" checked> Senin</label><label><input type="checkbox" name="hari_aktif[]" value="Selasa" checked> Selasa</label><label><input type="checkbox" name="hari_aktif[]" value="Rabu" checked> Rabu</label><label><input type="checkbox" name="hari_aktif[]" value="Kamis" checked> Kamis</label><label><input type="checkbox" name="hari_aktif[]" value="Jumat" checked> Jumat</label><label><input type="checkbox" name="hari_aktif[]" value="Sabtu"> Sabtu</label><label><input type="checkbox" name="hari_aktif[]" value="Minggu"> Minggu</label></div><small style="color:#666;">*centang hari yang aktif bekerja. Hari tidak dicentang = libur</small></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal Periode Libur (Untuk Admin) -->
<div id="modalPeriodeLiburAdmin" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalPeriodeLiburAdmin')">&times;</span><h4>Tambah Periode Libur</h4><form method="POST"><input type="hidden" name="action_periode_libur" value="add"><div class="form-group"><input type="text" name="nama_libur" class="form-control" placeholder="Contoh: Libur Hari Raya" required></div><div class="form-group"><input type="date" name="tanggal_mulai" class="form-control" required></div><div class="form-group"><input type="date" name="tanggal_selesai" class="form-control" required></div><div class="form-group"><select name="industri_id" class="form-control"><option value="">Semua Industri</option><?php $qi = $conn->query("SELECT * FROM industri ORDER BY nama_industri ASC"); while($ri=$qi->fetch_assoc()) echo "<option value='{$ri['id']}'>{$ri['nama_industri']}</option>"; ?></select></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<!-- Modal Periode Libur (Untuk Industri Dashboard) -->
<div id="modalPeriodeLibur" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('modalPeriodeLibur')">&times;</span><h4>Tambah Periode Libur</h4><form method="POST"><input type="hidden" name="action_periode_libur" value="add"><input type="hidden" name="industri_id" value="<?= $_SESSION['industri_id'] ?? '' ?>"><div class="form-group"><input type="text" name="nama_libur" class="form-control" placeholder="Contoh: Libur Hari Raya" required></div><div class="form-group"><input type="date" name="tanggal_mulai" class="form-control" required></div><div class="form-group"><input type="date" name="tanggal_selesai" class="form-control" required></div><button type="submit" class="btn btn-primary" style="width:100%;">Simpan</button></form></div></div>

<script>
// Modal functions
window.openModal = function(id) { document.getElementById(id).style.display = 'block'; }
window.toggleUserFields = function() {
    var role = document.getElementById('userRole').value;
    document.getElementById('fieldGuru').style.display = role === 'guru' ? 'block' : 'none';
    document.getElementById('fieldIndustri').style.display = role === 'industri' ? 'block' : 'none';
    // Set default redirect tab based on source
    var currentTab = document.getElementById('userTab').value;
    if (currentTab === 'pembimbing' || currentTab === 'master') {
        // Keep current tab values, will be overridden by button clicks
    }
}
window.setUserModalSource = function(tab, subtab) {
    document.getElementById('userTab').value = tab;
    document.getElementById('userSubtab').value = subtab || '';
    document.getElementById('userRole').value = tab === 'pembimbing' ? 'guru' : 'guru';
    toggleUserFields();
}
window.closeModal = function(id) { 
    document.getElementById(id).style.display = 'none'; 
    var form = document.querySelector('#' + id + ' form');
    if (form) {
        form.reset();
        var actionInput = form.querySelector('input[name^="action_"]');
        if (actionInput) actionInput.value = actionInput.name.replace('action_', 'add');
        var titleEl = document.getElementById(id + 'Title');
        if (titleEl) titleEl.innerText = titleEl.innerText.replace('Edit ', 'Tambah ');
        if (id === 'modalUser') toggleUserFields();
        if (id === 'modalIndustriLibur') {
            var defaults = ['Senin','Selasa','Rabu','Kamis','Jumat'];
            document.querySelectorAll('#modalIndustriLibur input[name="hari_aktif[]"]').forEach(cb => {
                cb.checked = defaults.includes(cb.value);
            });
        }
    }
}
window.onclick = function(event) { if (event.target.classList.contains('modal')) { var modal = event.target; closeModal(modal.id); } }

// Handle page load to check if modal should be opened after redirect
document.addEventListener('DOMContentLoaded', function() {
    // Check if there was a redirect after form submission - optionally auto-open modal
    // This allows user to stay on same page after add operation
});
</script>
<script>
window.approveModal = function(type, id, status) {
    document.getElementById('appType').value = type;
    document.getElementById('appId').value = id;
    document.getElementById('appStatus').value = status;
    openModal('modalApprove');
}
// Edit functions
window.openEditKelas = function(data) {
    document.getElementById('modalKelasTitle').innerText = 'Edit Kelas';
    document.querySelector('#modalKelas input[name="action_kelas"]').value = 'update';
    document.getElementById('kelasId').value = data.id;
    document.getElementById('kelasNama').value = data.nama_kelas;
    document.getElementById('kelasJurusan').value = data.jurusan;
    openModal('modalKelas');
}
window.openEditSiswa = function(data) {
    document.getElementById('modalSiswaTitle').innerText = 'Edit Siswa';
    document.querySelector('#modalSiswa input[name="action_siswa"]').value = 'update';
    document.getElementById('siswaOldNis').value = data.nis;
    document.getElementById('siswaNis').value = data.nis;
    document.getElementById('siswaNama').value = data.nama;
    if (data.kelas_id) document.getElementById('siswaKelasId').value = data.kelas_id;
    document.getElementById('siswaPass').value = '';
    document.getElementById('siswaPass').placeholder = 'Kosongkan jika tidak diganti';
    openModal('modalSiswa');
}
window.openEditUser = function(data) {
    document.getElementById('modalUserTitle').innerText = 'Edit User';
    document.querySelector('#modalUser input[name="action_user"]').value = 'update';
    document.getElementById('userId').value = data.id;
    document.getElementById('userUsername').value = data.username;
    document.getElementById('userNama').value = data.nama_lengkap;
    document.getElementById('userRole').value = data.role;
    toggleUserFields();
    if (data.kelas_id) document.getElementById('userKelasId').value = data.kelas_id;
    if (data.industri_id) document.getElementById('userIndustriId').value = data.industri_id;
    document.getElementById('userPass').value = '';
    document.getElementById('userPass').placeholder = 'Kosongkan jika tidak diganti';
    openModal('modalUser');
}
window.openEditKelompok = function(data) {
    document.getElementById('modalKelompokTitle').innerText = 'Edit Alokasi Pembimbing';
    document.querySelector('#modalKelompok input[name="action_kelompok"]').value = 'update';
    document.getElementById('kelId').value = data.id;
    document.getElementById('kelNama').value = data.nama_kelompok;
    document.getElementById('kelPeriode').value = data.id_periode;
    document.getElementById('kelIndustri').value = data.id_industri;
    document.getElementById('kelGuru').value = data.id_guru_pembimbing;
    document.getElementById('kelInd').value = data.id_industri_pembimbing;
    openModal('modalKelompok');
}
window.openEditIndustri = function(data) {
    document.getElementById('modalIndustriTitle').innerText = 'Edit Industri';
    document.querySelector('#modalIndustri input[name="action_industri"]').value = 'update';
    document.getElementById('industriId').value = data.id;
    document.getElementById('industriNama').value = data.nama_industri;
    document.getElementById('industriAlamat').value = data.alamat || '';
    document.getElementById('industriTelp').value = data.no_telp || '';
    document.getElementById('industriEmail').value = data.email || '';
    document.getElementById('industriPembimbing').value = data.nama_pembimbing || '';
    document.getElementById('industriLat').value = data.latitude || '';
    document.getElementById('industriLng').value = data.longitude || '';
    document.getElementById('industriRadius').value = data.radius || 100;
    openModal('modalIndustri');
}
window.openEditPeriodePKL = function(data) {
    document.getElementById('modalPeriodePKLTitle').innerText = 'Edit Periode PKL';
    document.querySelector('#modalPeriodePKL input[name="action_periode_pkl"]').value = 'update';
    document.getElementById('periodePKLId').value = data.id;
    document.getElementById('periodePKLNama').value = data.nama_periode;
    document.getElementById('periodePKLTingkat').value = data.tingkat_kelas || '';
    document.getElementById('periodePKLTahun').value = data.tahun_ajaran || '';
    document.getElementById('periodePKLMulai').value = data.tanggal_mulai;
    document.getElementById('periodePKLSelesai').value = data.tanggal_selesai;
    document.getElementById('periodePKLKet').value = data.keterangan || '';
    document.getElementById('periodePKLAktif').checked = data.is_aktif == 1;
    openModal('modalPeriodePKL');
}
window.openEditIndustriLibur = function(data) {
    document.getElementById('modalIndustriLiburTitle').innerText = 'Edit Jadwal Libur';
    document.querySelector('#modalIndustriLibur input[name="action_industri_libur"]').value = 'update';
    document.getElementById('industriLiburId').value = data.id;
    document.getElementById('industriLiburIndustriId').value = data.industri_id;
    document.getElementById('industriLiburNama').value = data.nama_libur;
    var hari = data.hari_aktif.split(',');
    var checkboxes = document.querySelectorAll('#modalIndustriLibur input[name="hari_aktif[]"]');
    checkboxes.forEach(cb => { cb.checked = hari.includes(cb.value); });
    openModal('modalIndustriLibur');
}
// Alokasi functions
function loadAllocation() {
    var kelId = document.getElementById('selectKelompokAlokasi').value;
    var area = document.getElementById('allocationArea');
    if (!kelId) { area.style.display = 'none'; return; }
    fetch('?action_ajax=get_siswa_kelompok&kel_id=' + kelId)
    .then(response => response.json())
    .then(data => {
        var html = '<div class="siswa-dual-list-container">';
        html += '<div class="siswa-col"><div class="siswa-header">Siswa Belum Teralokasi <span class="count-badge">'+data.non_members.length+'</span></div><div class="siswa-box"><div class="siswa-search"><input type="text" placeholder="Cari..." onkeyup="filterSiswa(this, \'nonList\')"></div><ul class="siswa-list" id="nonList">';
        data.non_members.forEach(function(s) { html += '<li><input type="checkbox" value="'+s.nis+'" class="check-non"> <div class="info"><b>'+s.nama+'</b><small>'+s.nis+' - '+(s.nama_kelas ? s.nama_kelas : '')+'</small></div></li>'; });
        html += '</ul></div></div>';
        html += '<div class="siswa-actions"><button class="btn btn-primary" onclick="moveSelected(\'to\')">&gt;&gt;</button><button class="btn btn-primary" onclick="moveSelected(\'from\')">&lt;&lt;</button></div>';
        html += '<div class="siswa-col"><div class="siswa-header">Anggota Kelompok <span class="count-badge">'+data.members.length+'</span></div><div class="siswa-box"><div class="siswa-search"><input type="text" placeholder="Cari..." onkeyup="filterSiswa(this, \'memberList\')"></div><ul class="siswa-list" id="memberList">';
        data.members.forEach(function(s) { html += '<li><input type="checkbox" value="'+s.nis+'" class="check-member"> <div class="info"><b>'+s.nama+'</b><small>'+s.nis+' - '+(s.nama_kelas ? s.nama_kelas : '')+'</small></div></li>'; });
        html += '</ul></div></div></div><div style="margin-top:10px;"><button class="btn btn-success" onclick="saveAllocation()">Simpan Perubahan</button></div>';
        area.innerHTML = html; area.style.display = 'block';
        window.currentKelId = kelId;
    });
}
window.filterSiswa = function(input, listId) {
    var filter = input.value.toLowerCase();
    var items = document.getElementById(listId).getElementsByTagName('li');
    for (var i = 0; i < items.length; i++) {
        items[i].style.display = items[i].innerText.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    }
}
window.moveSelected = function(direction) {
    if (direction === 'to') {
        var checkboxes = document.querySelectorAll('#nonList .check-non:checked');
        checkboxes.forEach(cb => { var li = cb.closest('li'); document.getElementById('memberList').appendChild(li); cb.classList.remove('check-non'); cb.classList.add('check-member'); });
    } else {
        var checkboxes = document.querySelectorAll('#memberList .check-member:checked');
        checkboxes.forEach(cb => { var li = cb.closest('li'); document.getElementById('nonList').appendChild(li); cb.classList.remove('check-member'); cb.classList.add('check-non'); });
    }
    document.querySelector('#nonList').closest('.siswa-col').querySelector('.count-badge').innerText = document.querySelectorAll('#nonList li').length;
    document.querySelector('#memberList').closest('.siswa-col').querySelector('.count-badge').innerText = document.querySelectorAll('#memberList li').length;
}
window.saveAllocation = function() {
    var nisList = []; document.querySelectorAll('#memberList .check-member').forEach(cb => { nisList.push(cb.value); });
    if (nisList.length === 0) { alert('Tidak ada anggota yang dipilih.'); return; }
    var formData = new FormData();
    formData.append('action_ajax', 'update_siswa_kelompok');
    formData.append('target_kel_id', window.currentKelId);
    nisList.forEach(n => formData.append('nis[]', n));
    fetch('', { method: 'POST', body: formData }).then(response => response.json()).then(result => { if(result.status === 'success') { alert('Berhasil menyimpan ' + result.moved + ' siswa.'); loadAllocation(); } else { alert('Gagal menyimpan.'); } });
}
// Checkbox all
document.getElementById('checkAllKelas')?.addEventListener('change', function(e) { document.querySelectorAll('#bulkFormKelas input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked); });
document.getElementById('checkAllSiswa')?.addEventListener('change', function(e) { document.querySelectorAll('#bulkFormSiswa input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked); });
document.getElementById('checkAllUser')?.addEventListener('change', function(e) { document.querySelectorAll('#bulkFormUser input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked); });
document.getElementById('checkAllIndustri')?.addEventListener('change', function(e) { document.querySelectorAll('#bulkFormIndustri input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked); });
document.getElementById('checkAllKelompok')?.addEventListener('change', function(e) { document.querySelectorAll('#bulkFormKelompok input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked); });
document.getElementById('checkAllIndustri2')?.addEventListener('change', function(e) { document.querySelectorAll('#bulkFormIndustri2 input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked); });
</script>
</body>
</html>
