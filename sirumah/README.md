# Aplikasi Progress Renovasi Rumah

Aplikasi web manajemen proyek renovasi berbasis client-side (SPA) untuk melacak progres harian setiap sub-pekerjaan, dengan dukungan multi-pengguna, visualisasi grafik, dan penyimpanan lokal.

---

## 📄 PRD – Progress Renovasi Rumah

### 1. Pendahuluan
Aplikasi **Progress Renovasi Rumah** adalah alat manajemen proyek renovasi berbasis web (single page application) yang berfungsi untuk melacak progres harian dari setiap sub-pekerjaan dalam proyek renovasi rumah. Aplikasi mendukung multi-pengguna dengan peran admin/user, serta menyediakan visualisasi data dalam bentuk grafik dan tabel.

### 2. Tujuan Produk
- Memudahkan pemilik proyek/kontraktor dalam memonitor penyelesaian pekerjaan renovasi.
- Memberikan transparansi progres per area dan per sub-pekerjaan.
- Memungkinkan kolaborasi antar tim dengan sistem login dan kontrol akses.
- Menyediakan fitur pencatatan harian (daily note) untuk setiap item pekerjaan.
- Mendukung ekspor/impor data untuk backup atau migrasi.

### 3. Ruang Lingkup
Aplikasi berjalan sepenuhnya di sisi klien (client-side) dengan teknologi HTML/CSS/JS murni, penyimpanan lokal menggunakan `localStorage`, dan tidak memerlukan backend (opsional untuk sync ke server). Target pengguna adalah manajer proyek, pekerja renovasi, dan pemilik rumah.

### 4. Pengguna & Peran
| Peran      | Hak Akses                                                                                 |
|------------|-------------------------------------------------------------------------------------------|
| **Admin**  | Semua fitur + manajemen pengguna (approve/delete user) + reset data.                      |
| **User**   | Edit data (tambah/edit/hapus area/sub, checklist hari, catatan, pengaturan proyek, off days, import/export, sync). |
| **Tamu**   | Hanya melihat data (read-only), tidak dapat mengubah apa pun.                            |

### 5. Fitur Fungsional (Prioritas Tinggi)

| ID   | Fitur                         | Deskripsi                                                                 |
|------|-------------------------------|---------------------------------------------------------------------------|
| F01  | **Dashboard Tabel Progress**   | Tabel matriks (sub-pekerjaan vs hari) dengan checkbox, progress bar per sub, per area, dan keseluruhan. |
| F02  | **Checklist Harian**           | Klik pada sel untuk menandai suatu sub-pekerjaan selesai di hari tertentu. |
| F03  | **Manajemen Area & Sub**       | Tambah/edit/hapus area pekerjaan dan sub-pekerjaan (dengan target hari). |
| F04  | **Pengaturan Hari Off**        | Tentukan hari off rutin mingguan (misal Minggu) dan tanggal off khusus (dengan catatan). |
| F05  | **Catatan Harian per Sub**     | Ikon catatan pada setiap sel hari untuk menambah/mengedit teks catatan. |
| F06  | **Grafik Rekapitulasi**        | Bar chart progress per area, donut keseluruhan, line chart kumulatif, dan detail sub pekerjaan. |
| F07  | **Autentikasi & Role**         | Login/logout, registrasi (perlu persetujuan admin), hash password SHA-256. |
| F08  | **Manajemen User (Admin)**     | Lihat daftar user, approve/revoke akses, hapus user.                      |
| F09  | **Import/Export Data**         | Export/import JSON (backup), download template CSV, import CSV.           |
| F10  | **Sync ke Server (Opsional)**   | Kirim data lokal ke endpoint API (untuk disimpan di server).              |
| F11  | **Tema Gelap/Terang**          | Toggle tema dengan penyimpanan preferensi.                                |
| F12  | **Reset Data ke Default**       | Reset semua data ke contoh awal (hanya admin/user yang login).           |

### 6. Fitur Non-Fungsional

| NFR   | Deskripsi                                                                 |
|-------|---------------------------------------------------------------------------|
| NFR01 | **Responsif** – Tabel mendukung scroll horizontal, modal dan popup menyesuaikan layar kecil. |
| NFR02 | **Performa** – Rendering tabel menggunakan DOM murni (tanpa framework berat) + virtual scroll dari browser. |
| NFR03 | **Keamanan** – Password di-hash di client; session disimpan di localStorage; tidak ada data sensitif dikirim tanpa enkripsi. |
| NFR04 | **Offline First** – Semua data tersimpan di localStorage, aplikasi dapat digunakan tanpa koneksi internet (kecuali sync server). |
| NFR05 | **Aksesibilitas** – Mendukung `prefers-reduced-motion`, atribut ARIA pada modal, warna kontras cukup. |

### 7. Batasan (Constraints)
- Kapasitas `localStorage` terbatas (~5-10 MB), cocok untuk proyek dengan maksimal ~500 sub-pekerjaan dan ~365 hari.
- Tidak ada mekanisme sinkronisasi konflik jika dua pengguna mengedit bersamaan (kecuali server diimplementasikan dengan logika versioning).
- Ketergantungan pada CDN eksternal (Tailwind, Chart.js, Font Awesome, Google Fonts) – perlu koneksi internet saat pertama kali diakses.

### 8. Asumsi
- Proyek renovasi memiliki tanggal mulai dan durasi tetap (total hari kerja).
- Satu sub-pekerjaan hanya bisa dikerjakan oleh satu tim dan progresnya diukur dari jumlah hari checklist (tidak persentase bobot).
- Pengguna yang login dipercaya untuk mengedit data (tidak ada audit log).

### 9. Kriteria Sukses
- Pengguna dapat mencatat progres harian dalam waktu kurang dari 5 detik per klik.
- Tabel tetap dapat di-scroll dengan mulus hingga 100 hari dan 50 sub-pekerjaan.
- Semua fitur CRUD (Area, Sub, Off Days, Catatan) dapat dilakukan tanpa error dan langsung tampil.
- Ekspor/impor CSV/JSON tidak merusak struktur data.

---

## 🧱 Blueprint Teknis

### 1. Arsitektur Sistem
- **Client-side SPA** – Semua logika berjalan di browser.
- **Penyimpanan** – `localStorage` sebagai database utama (key: `renovasi_progress_data`, `renovasi_users`, `renovasi_session`, `renovasi_theme`).
- **Server (opsional)** – Endpoint `POST` untuk sync data (tidak disediakan dalam kode, hanya placeholder).
- **Library eksternal**:
  - Tailwind CSS (styling)
  - Chart.js 4.4.0 (grafik)
  - Font Awesome 6.5.0 (ikon)
  - Plus Jakarta Sans (font)

### 2. Struktur Data (State Aplikasi)

```
interface State {
  projectName: string;
  startDate: string; // YYYY-MM-DD
  totalDays: number;
  dailyNotes: Record<string, string>; // key: `${subId}_${dayIndex}`
  weeklyOff: {
    enabled: boolean;
    days: number[]; // 0=Minggu, ..., 6=Sabtu
  };
  customOffDates: Array<{
    date: string; // YYYY-MM-DD
    note: string;
  }>;
  areas: Area[];
}

interface Area {
  id: string;
  name: string;
  description: string;
  subItems: SubItem[];
}

interface SubItem {
  id: string;
  name: string;
  checkedDays: number[]; // indeks hari (0-based)
  targetDays: number;
}
````
### 3. Komponen UI Utama
```
Komponen	                           |  Fungsi
#view-table	                           |  Menampilkan matriks progress + ringkasan.#
#view-chart                            |  Menampilkan grafik dan tabel rekap.
#modal-overlay                         |  Modal dinamis untuk form (login, area, sub, off days, dll).
#note-popup	                           |  Popup kecil untuk catatan harian per sub (posisi dekat ikon).
#toast-wrap	                           |  Menampilkan notifikasi temporary (sukses/error/info).
#table-container + #table-top-scroll   |  Sinkronisasi scroll horizontal.
```
### 4. Alur Autentikasi
- **Registrasi**
  - User memasukkan username & password → hash dengan crypto.subtle.digest('SHA-256')
  - Data disimpan ke renovasi_users dengan approved: false, role: 'user'.

- **Login**
  - Cari user dengan username match & hash password sama.
  - Jika approved: true, set currentUser dan simpan ke renovasi_session.

- **Admin Approve**
  - Admin membuka modal manajemen user → klik "Approve" → ubah approved: true.

- **Logout** → hapus renovasi_session.

### 5. Alur Sinkronisasi ke Server (Opsional)
Keterangan: Endpoint API tidak diimplementasikan dalam kode; pengguna harus mengganti SERVER_API_URL dengan server nyata.

### 6. Keamanan
  - Aspek	Implementasi
  - Password	Hash SHA-256 di sisi klien sebelum disimpan.
  - Session	Username disimpan di localStorage (raw). Tidak ada token JWT.
  - RBAC	Setiap aksi edit/reset/delete/modal manajemen user dicek if(!currentUser).
  - XSS	Setiap output teks ke HTML menggunakan escHtml() & escAttr().
  - CSRF	Tidak relevan karena tidak ada cookie session.
    
### 7. Rencana Implementasi (Roadmap)
Tahap	Fokus	Estimasi
  - 	Core table & checklist toggle, progress bar, save ke localStorage	2 hari
  - 	CRUD area & sub-pekerjaan, target days	1 hari
  - 	Autentikasi + role + manajemen user (admin)	1.5 hari
  - 	Hari off (mingguan & custom) + catatan harian (daily notes)	1 hari
  - 	Grafik (Chart.js) + recap table	1 hari
  - 	Import/export CSV, JSON, template CSV	1 hari
  - 	Sync server, tema, scroll sinkronisasi, polish UI	1 hari
  - 
### 8. Catatan Pengembangan
  - Cara menjalankan: Simpan kode sebagai file .html dan buka di browser modern (Chrome/Edge/Firefox).
  - Customisasi server: Ganti konstanta SERVER_API_URL dengan endpoint Anda.
  - Peningkatan ke depan:
  - Tambahkan bobot pekerjaan (weighted completion).
  - Dukungan multi-proyek.
  - Realtime sync dengan WebSocket.
  - PWA (service worker) untuk akses offline penuh.

📦 Cara Penggunaan
  - Simpan kode HTML lengkap (yang diberikan) ke dalam file index.html.
  - Buka file tersebut menggunakan web browser (Chrome, Firefox, Edge).
  - Login dengan akun default:
  - Username: admin - Password: 123456
  - Mulai kelola proyek renovasi Anda.

⚠️ Catatan: Aplikasi ini sepenuhnya berjalan di sisi klien. Data tersimpan di localStorage browser. Jangan lupa untuk melakukan ekspor JSON secara berkala sebagai cadangan.

