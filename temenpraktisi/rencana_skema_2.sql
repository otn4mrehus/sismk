/* ============================
   1. MASTER PERIODE, JURUSAN, KELAS
   ============================ */

CREATE TABLE periode_pkl (
    id_periode INT AUTO_INCREMENT PRIMARY KEY,
    nama_periode VARCHAR(50) NOT NULL,
    tahun_awal YEAR NOT NULL,
    tahun_akhir YEAR NOT NULL,
    tgl_mulai DATE NOT NULL,
    tgl_selesai DATE NOT NULL,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif'
);

CREATE TABLE jurusan (
    id_jurusan INT AUTO_INCREMENT PRIMARY KEY,
    nama_jurusan VARCHAR(100) NOT NULL
);

CREATE TABLE kelas (
    id_kelas INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(50) NOT NULL,
    id_jurusan INT NOT NULL,
    FOREIGN KEY (id_jurusan) REFERENCES jurusan(id_jurusan)
);


/* ============================
   2. INDUSTRI & PEMBIMBING INDUSTRI
   ============================ */

CREATE TABLE industri (
    id_industri INT AUTO_INCREMENT PRIMARY KEY,
    nama_industri VARCHAR(200) NOT NULL,
    alamat TEXT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    radius_izin INT DEFAULT 100,
    kontak_pic VARCHAR(100),
    telp_pic VARCHAR(50)
);

CREATE TABLE pembimbing_industri (
    id_pembimbing_industri INT AUTO_INCREMENT PRIMARY KEY,
    id_industri INT NOT NULL,
    nama VARCHAR(150) NOT NULL,
    jabatan VARCHAR(100),
    kontak VARCHAR(50),
    FOREIGN KEY (id_industri) REFERENCES industri(id_industri)
);


/* ============================
   3. SISWA & GURU
   ============================ */

CREATE TABLE siswa (
    id_siswa INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(50) UNIQUE NOT NULL,
    nama_siswa VARCHAR(150) NOT NULL,
    gender ENUM('L','P') DEFAULT 'L',
    foto VARCHAR(255),
    id_kelas INT NOT NULL,
    FOREIGN KEY (id_kelas) REFERENCES kelas(id_kelas)
);

CREATE TABLE guru (
    id_guru INT AUTO_INCREMENT PRIMARY KEY,
    nama_guru VARCHAR(150) NOT NULL,
    nip VARCHAR(50),
    kontak VARCHAR(50)
);


/* ============================
   4. KELOMPOK PKL & ANGGOTA
   ============================ */

CREATE TABLE kelompok_pkl (
    id_kelompok INT AUTO_INCREMENT PRIMARY KEY,
    id_periode INT NOT NULL,
    id_industri INT NOT NULL,
    kode_kelompok VARCHAR(50) NOT NULL,
    status ENUM('pengajuan','disetujui','berjalan','selesai','ditolak') DEFAULT 'pengajuan',
    catatan TEXT,
    FOREIGN KEY (id_periode) REFERENCES periode_pkl(id_periode),
    FOREIGN KEY (id_industri) REFERENCES industri(id_industri)
);

CREATE TABLE kelompok_siswa (
    id_kelompok INT NOT NULL,
    id_siswa INT NOT NULL,
    status_pkl ENUM('aktif','bermasalah','dijemput_awal','selesai') DEFAULT 'aktif',
    catatan TEXT,
    tgl_update DATETIME,
    PRIMARY KEY(id_kelompok, id_siswa),
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa)
);


/* ============================
   5. PENUGASAN GURU (PEMBIMBING SEKOLAH)
   ============================ */

CREATE TABLE pembimbing_sekolah (
    id_pembimbing_sekolah INT AUTO_INCREMENT PRIMARY KEY,
    id_kelompok INT NOT NULL,
    id_guru INT NOT NULL,
    peran ENUM('pengantar','monitoring','penjemput') NOT NULL,
    tgl_assign DATE NOT NULL,
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru)
);


/* ============================
   6. ALUR PKL
   ============================ */

CREATE TABLE pengajuan_kelompok (
    id_pengajuan INT AUTO_INCREMENT PRIMARY KEY,
    id_kelompok INT NOT NULL,
    tgl_pengajuan DATE NOT NULL,
    status ENUM('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
    catatan TEXT,
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok)
);

CREATE TABLE pengantaran (
    id_pengantaran INT AUTO_INCREMENT PRIMARY KEY,
    id_kelompok INT NOT NULL,
    id_guru INT NOT NULL,
    tgl_pengantaran DATE NOT NULL,
    bertemu_dengan VARCHAR(150),
    foto VARCHAR(255),
    catatan TEXT,
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru)
);

CREATE TABLE monitoring (
    id_monitoring INT AUTO_INCREMENT PRIMARY KEY,
    id_kelompok INT NOT NULL,
    id_guru INT NOT NULL,
    id_pembimbing_industri INT,
    tgl_monitoring DATE NOT NULL,
    foto VARCHAR(255),
    hasil_monitoring TEXT,
    saran TEXT,
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru),
    FOREIGN KEY (id_pembimbing_industri) REFERENCES pembimbing_industri(id_pembimbing_industri)
);

CREATE TABLE penjemputan (
    id_penjemputan INT AUTO_INCREMENT PRIMARY KEY,
    id_kelompok INT NOT NULL,
    id_guru INT NOT NULL,
    tgl_penjemputan DATE NOT NULL,
    catatan TEXT,
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru)
);


/* ============================
   6A. PENJEMPUTAN SISWA INDIVIDU
   ============================ */

CREATE TABLE penjemputan_siswa (
    id_penjemputan_siswa INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_kelompok INT NOT NULL,
    id_guru INT NOT NULL,
    tgl_penjemputan DATE NOT NULL,
    alasan TEXT,
    foto VARCHAR(255),
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa),
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru)
);


/* ============================
   7. PROGRESS / TIMELINE
   ============================ */

CREATE TABLE progress_kelompok (
    id_progress INT AUTO_INCREMENT PRIMARY KEY,
    id_kelompok INT NOT NULL,
    tahap ENUM('pengajuan','pengantaran','monitoring','penjemputan','selesai'),
    status ENUM('pending','proses','done') DEFAULT 'pending',
    tgl_update DATETIME NOT NULL,
    keterangan TEXT,
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok)
);


/* ============================
   8. JURNAL SISWA
   ============================ */

CREATE TABLE jurnal_siswa (
    id_jurnal INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_kelompok INT NOT NULL,
    tanggal DATE NOT NULL,
    kegiatan TEXT NOT NULL,
    hasil_kegiatan TEXT,
    foto VARCHAR(255),
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    status_approval ENUM('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
    catatan_pembimbing_industri TEXT,
    id_pembimbing_industri INT,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa),
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_pembimbing_industri) REFERENCES pembimbing_industri(id_pembimbing_industri)
);


/* ============================
   9. PERIODE LIBUR
   ============================ */

CREATE TABLE periode_libur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_periode VARCHAR(100) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


/* ============================
   10. ABSENSI IZIN
   ============================ */

CREATE TABLE absensi_izin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) NOT NULL,
    tanggal DATE NOT NULL,
    jenis ENUM('sakit','ijin') NOT NULL,
    keterangan TEXT,
    lampiran VARCHAR(255),
    status ENUM('pending','diterima','ditolak') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


/* ============================
   11. PRESENSI PKL (FOTO + GEO)
   ============================ */

CREATE TABLE presensi_pkl (
    id_presensi INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_kelompok INT NOT NULL,
    id_periode INT NOT NULL,
    jenis ENUM('masuk','pulang') NOT NULL,
    waktu_presensi DATETIME NOT NULL,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    status_masuk ENUM('tepat_waktu','terlambat') DEFAULT 'tepat_waktu',
    status_pulang ENUM('pulang_cepat','tepat_waktu','tidak_presensi') DEFAULT 'tepat_waktu',
    foto VARCHAR(255) NOT NULL,
    keterangan_terlambat TEXT,
    keterangan_pulang_cepat TEXT,
    keterangan TEXT,
    FOREIGN KEY (id_siswa) REFERENCES siswa(id_siswa),
    FOREIGN KEY (id_kelompok) REFERENCES kelompok_pkl(id_kelompok),
    FOREIGN KEY (id_periode) REFERENCES periode_pkl(id_periode)
);


/* ============================
   12. LAMPIRAN DOKUMEN
   ============================ */

CREATE TABLE lampiran_kegiatan (
    id_lampiran INT AUTO_INCREMENT PRIMARY KEY,
    kategori ENUM('pengajuan','pengantaran','monitoring','penjemputan') NOT NULL,
    id_referensi INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    keterangan TEXT
);


/* ============================
   13. AUDIT LOG
   ============================ */

CREATE TABLE log_aktivitas (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    aksi VARCHAR(200) NOT NULL,
    tabel VARCHAR(100),
    id_data INT,
    waktu DATETIME NOT NULL,
    deskripsi TEXT
);
