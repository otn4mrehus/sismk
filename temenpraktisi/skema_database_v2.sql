SET FOREIGN_KEY_CHECKS = 0;

---------------------------------------------------------
-- 1. TABEL JURUSAN
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS jurusan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(20) UNIQUE,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 2. TABEL KELAS
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(20) NOT NULL,
    tingkat ENUM('10','11','12') NOT NULL,
    jurusan_id INT,
    FOREIGN KEY (jurusan_id) REFERENCES jurusan(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 3. TABEL USERS
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM(
        'admin','siswa','guru','walikelas',
        'pembimbing_industri','pembimbing_sekolah'
    ) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    kelas_id INT NULL,
    instansi_id INT NULL,
    is_active BOOLEAN DEFAULT 1,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 4. TABEL PERIODE PKL (BARU)
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS periode_pkl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_periode VARCHAR(100) NOT NULL COMMENT 'Nama periode, misal PKL Angkatan 2025',
    tahun_ajaran VARCHAR(20) NOT NULL COMMENT 'Tahun ajaran, misal 2025/2026',
    angkatan VARCHAR(10) NOT NULL COMMENT 'XI, XII, dll',
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status ENUM('aktif','nonaktif') DEFAULT 'nonaktif',
    keterangan TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 5. TABEL INSTANSI (DUDI)
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS instansi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_instansi VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    radius INT NOT NULL,
    logo VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 6. TABEL SEKOLAH
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS sekolah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_sekolah VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    waktu_masuk TIME DEFAULT '07:00:00',
    waktu_pulang TIME DEFAULT '15:00:00',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    radius INT DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 7. TABEL KELOMPOK PKL (DITAMBAH periode_pkl_id)
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS kelompok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    instansi_id INT NOT NULL,
    kelas_id INT NOT NULL,
    periode_pkl_id INT NULL,
    pembimbing_industri_id INT NULL,
    pembimbing_sekolah_id INT NULL,
    FOREIGN KEY (instansi_id) REFERENCES instansi(id),
    FOREIGN KEY (kelas_id) REFERENCES kelas(id),
    FOREIGN KEY (periode_pkl_id) REFERENCES periode_pkl(id),
    FOREIGN KEY (pembimbing_industri_id) REFERENCES users(id),
    FOREIGN KEY (pembimbing_sekolah_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 8. TABEL SISWA
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    nis VARCHAR(20) NOT NULL,
    kelas_id INT NOT NULL,
    kelompok_id INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (kelas_id) REFERENCES kelas(id),
    FOREIGN KEY (kelompok_id) REFERENCES kelompok(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 9. TABEL PERIODE MINGGUAN
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS periode_mingguan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instansi_id INT NOT NULL,
    hari ENUM(
        'monday','tuesday','wednesday','thursday',
        'friday','saturday','sunday'
    ) NOT NULL,
    status ENUM('masuk','libur') NOT NULL,
    FOREIGN KEY (instansi_id) REFERENCES instansi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 10. TABEL LIBUR
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS libur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan VARCHAR(255) NOT NULL,
    instansi_id INT NULL,
    FOREIGN KEY (instansi_id) REFERENCES instansi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 11. TABEL PRESENSI
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS presensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    kelompok_id INT NOT NULL,
    tanggal DATE NOT NULL,
    waktu_masuk DATETIME NULL,
    waktu_pulang DATETIME NULL,
    foto_masuk VARCHAR(255),
    foto_pulang VARCHAR(255),
    lat_masuk DECIMAL(10,8),
    lng_masuk DECIMAL(11,8),
    lat_pulang DECIMAL(10,8),
    lng_pulang DECIMAL(11,8),
    status ENUM('hadir','ijin','sakit','alpa') DEFAULT 'hadir',
    FOREIGN KEY (siswa_id) REFERENCES siswa(id),
    FOREIGN KEY (kelompok_id) REFERENCES kelompok(id),
    FOREIGN KEY (instansi_id) REFERENCES instansi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 12. TABEL IJIN
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS ijin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jenis ENUM('sakit','ijin') NOT NULL,
    keterangan TEXT NOT NULL,
    file_pendukung VARCHAR(255),
    status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
    disetujui_oleh INT NULL,
    tanggal_pengajuan DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id),
    FOREIGN KEY (disetujui_oleh) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 13. TABEL JURNAL PKL
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS jurnal_pkl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT,
    tanggal DATE,
    topik_pekerjaan TEXT,
    dokumentasi VARCHAR(255),
    status VARCHAR(50),
    catatan TEXT,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 14. TABEL LAPORAN PKL
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS laporan_pkl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT,
    instansi_id INT,
    file_laporan VARCHAR(255),
    tgl_kirim TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id),
    FOREIGN KEY (instansi_id) REFERENCES instansi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 15. TABEL PENILAIAN PKL
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS penilaian_pkl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT,
    aspek_teknis TEXT,
    aspek_nonteknis TEXT,
    nilai_teknis INT,
    nilai_nonteknis INT,
    nilai_akhir INT,
    catatan TEXT,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------------------------------------------------------
-- 16. TABEL PEMBIMBING KELOMPOK
---------------------------------------------------------
CREATE TABLE IF NOT EXISTS pembimbing_kelompok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_id INT NOT NULL,
    kelompok_id INT NOT NULL,
    FOREIGN KEY (guru_id) REFERENCES users(id),
    FOREIGN KEY (kelompok_id) REFERENCES kelompok(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
