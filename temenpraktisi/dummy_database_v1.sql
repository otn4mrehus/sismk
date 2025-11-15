SET FOREIGN_KEY_CHECKS = 0;

---------------------------------------------------------
-- DUMMY PERIODE PKL
---------------------------------------------------------
INSERT INTO periode_pkl 
(id, nama_periode, tahun_ajaran, angkatan, tanggal_mulai, tanggal_selesai, status, keterangan)
VALUES
(1, 'PKL Angkatan 2025', '2025/2026', 'XII', '2025-01-10', '2025-06-30', 'aktif', 'Periode PKL utama'),
(2, 'PKL Angkatan 2024', '2024/2025', 'XII', '2024-01-15', '2024-07-01', 'nonaktif', 'Periode sebelumnya'),
(3, 'PKL Semester Ganjil 2026', '2026/2027', 'XI', '2026-08-01', '2026-12-10', 'nonaktif', 'Gelombang baru');

---------------------------------------------------------
-- DUMMY JURUSAN
---------------------------------------------------------
INSERT INTO jurusan (id, kode, nama) VALUES
(1, 'TJKT', 'Teknik Jaringan Komputer & Telekomunikasi'),
(2, 'RPL', 'Rekayasa Perangkat Lunak'),
(3, 'DKV', 'Desain Komunikasi Visual');

---------------------------------------------------------
-- DUMMY KELAS
---------------------------------------------------------
INSERT INTO kelas VALUES
(1, 'XII TJKT 1', '12', 1),
(2, 'XII RPL 1', '12', 2),
(3, 'XII DKV 1', '12', 3);

---------------------------------------------------------
-- DUMMY USERS
---------------------------------------------------------
INSERT INTO users (id, username, password, role, nama, kelas_id, instansi_id, is_active) VALUES
(1, 'admin', 'password_hash_dummy', 'admin', 'Admin Sistem', NULL, NULL, 1),
(2, 'guru_budi', 'password_hash_dummy', 'pembimbing_sekolah', 'Budi Santoso', NULL, NULL, 1),
(3, 'siswa_andi', 'password_hash_dummy', 'siswa', 'Andi Pratama', 1, NULL, 1);

---------------------------------------------------------
-- DUMMY INSTANSI
---------------------------------------------------------
INSERT INTO instansi VALUES
(1, 'PT Maju Jaya', 'Jl Industri No 10', -6.12, 106.98, 150, NULL),
(2, 'CV Kreatif Media', 'Jl Kreatif No 22', -6.21, 106.87, 100, NULL),
(3, 'UD Nusantara', 'Komplek A2 Bisnis', -6.33, 106.76, 120, NULL);

---------------------------------------------------------
-- DUMMY KELOMPOK (dengan periode_pkl_id)
---------------------------------------------------------
INSERT INTO kelompok VALUES
(1, 'Kelompok 1 TJKT', 1, 1, 1, NULL, 2),
(2, 'Kelompok 2 RPL', 2, 2, 1, NULL, 2),
(3, 'Kelompok 3 DKV', 3, 3, 1, NULL, 2);

---------------------------------------------------------
-- DUMMY SISWA
---------------------------------------------------------
INSERT INTO siswa VALUES
(1, 3, '123456789', 1, 1),
(2, 3, '123456780', 1, 1),
(3, 3, '123456781', 1, 1);

---------------------------------------------------------
-- DUMMY PERIODE MINGGUAN
---------------------------------------------------------
INSERT INTO periode_mingguan VALUES
(1, 1, 'monday', 'masuk'),
(2, 1, 'tuesday', 'masuk'),
(3, 1, 'saturday', 'libur');

---------------------------------------------------------
-- DUMMY LIBUR
---------------------------------------------------------
INSERT INTO libur VALUES
(1, '2025-01-01', 'Tahun Baru', NULL),
(2, '2025-04-11', 'Hari Raya', NULL),
(3, '2025-08-17', 'Kemerdekaan', NULL);

---------------------------------------------------------
-- DUMMY PRESENSI
---------------------------------------------------------
INSERT INTO presensi VALUES
(1, 1, 1, 1, '2025-01-10', '2025-01-10 07:00:00', '2025-01-10 15:00:00', 'in1.jpg', 'out1.jpg', -6.12, 106.98, -6.12, 106.98, 'hadir'),
(2, 1, 1, 1, '2025-01-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ijin'),
(3, 1, 1, 1, '2025-01-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sakit');

---------------------------------------------------------
-- DUMMY IJIN
---------------------------------------------------------
INSERT INTO ijin VALUES
(1, 1, '2025-01-11', 'ijin', 'Keperluan keluarga', 'file1.pdf', 'pending', 2, NOW()),
(2, 1, '2025-01-12', 'sakit', 'Demam', 'file2.pdf', 'disetujui', 2, NOW()),
(3, 1, '2025-01-15', 'ijin', 'Administrasi', NULL, 'ditolak', 2, NOW());

---------------------------------------------------------
-- DUMMY JURNAL
---------------------------------------------------------
INSERT INTO jurnal_pkl VALUES
(1, 1, '2025-01-10', 'Instalasi LAN', 'j1.jpg', 'valid', 'Baik'),
(2, 1, '2025-01-11', 'Troubleshooting PC', 'j2.jpg', 'p
