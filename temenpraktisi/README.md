## Rencana Implementasi ##
### A. Struktur Database ##
```
1. periode_prakerin - Mengelola periode aktif prakerin
2. industri - Data perusahaan/industri mitra
3. penempatan_siswa - Mapping siswa ke industri
4. pembimbing - Data pembimbing (sekolah & industri)
5. ploting_pembimbing - Alokasi pembimbing ke siswa
6. laporan_akhir - Laporan prakerin siswa
7. pengumuman - Sistem pengumuman
8. orangtua - Akun orang tua/wali
9. log_aktivitas - Riwayat aktivitas sistem
```
### B. Sistem Multi-Level Login ### 
```
Satu halaman login untuk semua role
Autentikasi berdasarkan tabel yang sesuai
Session management yang aman
```
### C. Dashboard Berdasarkan Role ### 
```
Admin: Akses penuh ke semua fitur
Pembimbing: Monitoring siswa bimbingan
Siswa: Presensi dan laporan
Orang Tua: Monitoring anak
```
### D. Fitur Presensi Multi-Lokasi ### 
```
Presensi berdasarkan lokasi industri
Validasi radius untuk setiap industri
Integrasi dengan sistem deteksi wajah yang ada
```
### E. Manajemen Laporan ### 
```
Upload laporan akhir oleh siswa
Review dan approval oleh pembimbing
Tracking status laporan
```
### F. Sistem Pengumuman ### 
```
Pengumuman berdasarkan target audience
Jadwal aktif pengumuman
Tampilan berbeda per role
```
### G. Backup Database ###
```
Backup otomatis dengan timestamp
Download dan manajemen file backup
Keunggulan Sistem
Single Code Application - Semua kode dalam satu file
Responsive Design - Mendukung berbagai device
Security - Enkripsi password, session management
Multi-Location - Presensi di berbagai industri
Comprehensive Reporting - Laporan lengkap untuk semua stakeholder
Easy Maintenance - Backup dan restore database
```

```
 "Aplikasi Presensi (wajah dan gelokasi) Prakerin (Praktik Kerja Industri) atau Penempatan PKL di SMK Berbasis Website dengan web program native (semua dalma gabungan ke dalam index.php) yang mampu memanajemen kegiatan prakerin siswa atau penempatan PKL. Tidak hanya admin saja dalam pengelolaan, tapi juga siswa dan pembimbing (sekolah dan industri/dudi) serta orang tua siswa ikut berperan dalam aplikasi guna menjembatani antar wali kelas, siswa dan pembimbing(sekolah dan industri/dudi). Dilengkapi dengan menu laporan akhir prakerin, ploting/mapping pembimbing (dari sekolah maupun dari industri) dan penempatan perusahaan pada pendaftar siswa prakerin. Dengan pemantauan hasil presensi siswa prakerin oleh masing-masing pembimbing (Guru dan sekolah) sesuai hasil mapping/alokasi kelompok atau grup prakerin yang dibimbing " .

Tentang Aplikasi yang akan dibangun 
-Bisa Berjalan Di Windows, Linux dan MacOs
-Aplikasi Berbasis Website
-Support Tampilan Handphone/Laptop/Tablet
-Menggunakan PHP-Native (PHP7.4, MySQL 5.7, Javascript (Bootstrap atau lainnya))
-Menggunakan Template AdminLTE atau lainnya 
-Menggunakan (Library: Sweet Alert 2,DataTables,Bootstrap Versi 3, Enkripsi Data, CSRF Token, PHP-OpenStreetMap)
-Menerapkan CRUD MySQL Robust/ERD
-Single code application (tersimpan semua kode yang terkelompokkan "config, instalasi database/tabel/query, fungsi-fungsi php/js/library, html, css" dalam index.php dengan lengkap)
-Presensi Multi lokasi berdasar industri 


Dengan fitur-fitur aplikasi web prgram berikut:
1. MultiLevel Akun (untuk beberapa user seperti Administrator, Pembimbing (sekolah dan Industri), Wali Kelas, Orang Tua dan Siswa)
2. Halaman Login (Untuk halaman login hanya 1 saja yang mampu digunakan untuk login semua role/jenis user)
3. Dashboard (Menu ini ringkasan dari menu yang ada dan mampu membedakan role yang sedang login sesuai hak akses role tersebut)
4. Data Periode (Menu ini digunakan untuk mengatur periode prakerin yang aktif berjalan)
5. Data Industri/Perusahaan/DuDi (Menu ini digunakan untuk menampung data Industri/Perusahaan/DuDi)
6. Data Penempatan Siswa (Menu ini untuk memploting/Mapping/alokasi penempatan siswa sesuai perusahaan aktif)
7. Data Ploting Pembimbing (Menu ini untuk ploting/Mapping/alokasi pembimbing dari sekolah maupun industri)
8. Menu Presensi (Menu ini untuk masing-masing Siswa dapat melakukan Presensi wajah dan titik lokasi di tempat prakerinnya untuk masuk dan pulang, dengan kelengkapan notif dan validasi nya).
9. Menu Laporan (Menu ini digunakan untuk memonitoring presensi maupun laporan akhir siswa prakerin dengan "By Filter")
10. Menu Pengumuman (Menu ini digunakan untuk memberikan pengumuman kepada siswa)
11. Manajemen User (Menu ini digunakan untuk menambahkan Administrator, Pembimbing dan Siswa. Dilengkapi fitur import massal excel)
12. Tentang Aplikasi (Menu ini digunakan untuk mensetting aplikasi mulai dari nama aplikasi, telp email serta logo dari perusahaan/instansi)
13. Backup Database (Menu ini digunakan untuk membackup database langsung dari aplikasi dan dapat diunduh dalam bentuk SQL file)
)
14. Log Status (Menu ini digunakan untuk melihat riwayat login dan logout dari aktivitas login user yang ada)
15. Profil (Menu ini digunakan untuk mengatur profil dari akun yang sedang login. Dapat menambahkan foto profil sesuai dengan keinginan masing masing)
16. Logout (Menu ini digunakan untuk Keluar dari aplikasi)
```
