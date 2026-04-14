#  Sistem Aplikasi Presensi PKL (PHP 7.4 Native + MySQL 5.7)

##  1. Gambaran Umum Sistem

Aplikasi ini merupakan:

> **Sistem Manajemen Presensi PKL berbasis Web**  
> dengan dukungan geolokasi, foto, dan multi-role user.

### Fitur Utama:
- Presensi berbasis **GPS + Foto**
- Manajemen **PKL (kelompok, industri, periode)**
- Multi-role:
  - Admin
  - Guru
  - Industri
  - Siswa

---

##  2. Arsitektur Sistem

### A. Layer Sistem

1. **Presentation Layer**
   - HTML, CSS, JavaScript
   - Responsive (Mobile & Desktop)
   - Kamera + face-api.js

2. **Application Layer**
   - PHP Native
   - Routing berbasis `$_GET`
   - Business logic & CRUD

3. **Data Layer**
   - MySQL 5.7
   - Relasi tabel (Foreign Key)
   - Auto migration

---

### B. Struktur Modul
SYSTEM CORE
├── Authentication & Session
├── Master Data Management
├── PKL Management
├── Presensi Engine (Geo + Foto)
├── Approval System
├── Reporting & Monitoring
├── Settings & Configuration

---

##  3. Blueprint Fitur Sistem

---

###  3.1 Authentication & Security

**Fitur:**
- Login multi-role
- Session timeout (15 menit)
- Password hashing
- Role-based access

**Pengembangan:**
- CSRF Protection
- Rate limiting
- Audit login

---

###  3.2 User & Role Management

**Entitas:**
- Users (admin, guru, industri)
- Siswa

**Fitur:**
- CRUD user
- Assign kelas & industri
- Role-based redirect

---

###  3.3 Master Data

**Modul:**
- Kelas
- Siswa
- Industri
- Guru

**Fitur:**
- CRUD lengkap
- Bulk delete
- Relasi database

---

###  3.4 Manajemen PKL

**Entitas:**
- kelompok_pkl
- periode_pkl
- industri

**Fitur:**
- Pembentukan kelompok
- Assign pembimbing
- Relasi siswa
- AJAX update kelompok

---

###  3.5 Presensi Inti

**Fitur:**
- Presensi masuk & pulang
- Validasi:
  - GPS
  - Waktu
- Upload foto
- Kompresi gambar

**Status:**
- Tepat waktu
- Terlambat
- Pulang cepat

---

###  3.6 Face & Media

**Fitur:**
- Capture kamera
- Integrasi face-api.js

**Upgrade:**
- Face recognition
- Liveness detection

---

###  3.7 Izin & Absensi

**Fitur:**
- Pengajuan izin (sakit/izin)
- Upload lampiran
- Status approval

---

###  3.8 Approval System

**Role:**
- Guru
- Industri

**Fitur:**
- Approve/reject presensi
- Catatan pembimbing

---

###  3.9 Reporting & Monitoring

**Fitur:**
- Statistik presensi
- Filter data
- Pagination

---

###  3.10 Pengaturan Sistem

**Fitur:**
- Lokasi sekolah
- Radius presensi
- Jam kerja
- Identitas sekolah

---

###  3.11 Manajemen Libur

**Modul:**
- periode_libur
- industri_libur

**Fitur:**
- Libur global
- Libur industri
- Hari kerja custom

---

###  3.12 AJAX & Interaktif

**Fitur:**
- Load data dinamis
- Update tanpa reload
- JSON response

---

##  4. Flow Sistem

---

### A. Presensi Siswa
Login
→ Ambil GPS
→ Ambil foto
→ Validasi radius
→ Simpan data
→ Menunggu approval

---

### B. Approval
Login pembimbing
→ Lihat data
→ Approve / Reject
→ Tambah catatan

---

### C. PKL
Admin buat periode
→ Input industri
→ Buat kelompok
→ Assign siswa & pembimbing

---

##  5. Analisis Database

### Kelebihan:
- Relasi cukup baik
- Modular
- Timestamp tersedia

### Kekurangan:
- Tidak ada audit trail
- Tidak ada soft delete
- Belum optimal indexing

---

## ⚠️ 6. SWOT Analysis

---

###  Strength
- Sistem PKL end-to-end
- Presensi GPS + foto
- Multi-role lengkap
- UI responsive
- Approval system

---

###  Weakness
- Monolithic (1 file besar)
- Tidak ada CSRF
- Tidak ada REST API
- Kurang scalable
- Query belum optimal

---

###  Opportunity
- Migrasi ke React / SPA
- Mobile app (Flutter)
- Integrasi AI
- Dashboard BI
- Integrasi sistem sekolah

---

###  Threat
- Fake GPS
- Fake foto
- Risiko keamanan (SQL injection)
- Overload user
- Tidak ada backup otomatis

---

##  7. Rekomendasi Upgrade

---

### A. Arsitektur
- MVC / Modular
- API-based system
- Framework (Laravel / Slim)

---

### B. Security
- CSRF Token
- Input validation
- JWT Auth (opsional)

---

### C. Fitur Tambahan
- Audit trail
- Export Excel/PDF
- Notifikasi WA/Email
- Dashboard grafik

---

### D. Infrastruktur
- Docker deployment
- Backup otomatis
- Logging system

---

##  8. Kesimpulan

Aplikasi ini:

> **Sudah 80% siap untuk produksi skala sekolah**

Namun untuk level lebih tinggi:
- Multi sekolah
- Skala besar
- Enterprise

Diperlukan:
- Refactor arsitektur
- Peningkatan keamanan
- API & frontend modern

---
