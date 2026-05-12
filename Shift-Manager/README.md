Berikut adalah ringkasan fitur teknis Shift Manager Pro dalam format teks biasa (plain text):

============================================================
SHIFT MANAGER PRO - RINGKASAN FITUR TEKNIS
============================================================

1. ARSITEKTUR DATA & PENYIMPANAN

---

· Data tersimpan sepenuhnya di localStorage dengan satu kunci utama: "shift_app_full_backup_v1"
· Struktur data:
  · shifts: array objek {id, name, time} (default: Shift-1(08:00-16:00), Shift-2(16:00-24:00), Shift-3(24:00-08:00))
  · persons: array objek {id, name} (default: Dandi, Emon, Makruf, Riski, Tobar)
  · schedule: objek dengan key "{personId}|{dateISO}|{slot}" (slot=1 atau 2)
    · nilai: null (kosong / "-"), 0 (Off), 1/2/3 (Shift-1/2/3)
  · infoItems: array string untuk konten card Info
  · viewMode: "week" atau "month"
  · currentBaseDate: tanggal dasar penampil (ISO string)
  · theme: "light" atau "dark"
  · togglePersonil, toggleShift, toggleInfo: boolean status sembunyikan card
  · activeTab: "jadwal" atau "pengaturan"

1. FITUR INTI / CRUD

---

2.1 Personil (CRUD)

· Create: tombol "+ Tambah" → prompt nama → ID increment otomatis
· Read: daftar personil dalam .list-item dengan tombol edit/hapus
· Update: klik ✏️ → prompt edit nama → simpan otomatis
· Delete: klik 🗑️ → konfirmasi → hapus personil beserta semua jadwal terkait. Minimal 1 personil.

2.2 Shift (CRUD)

· Create: "+ Tambah Shift" → prompt nama shift & jam kerja
· Update: ✏️ → edit nama & jam
· Delete: hapus shift → semua jadwal yang menggunakan shift tsb direset ke null. Minimal 1 shift.

2.3 Info Card (CRUD)

· Create/Update: tombol ✏️ pada header Info → modal textarea → setiap baris menjadi item list
· Read: daftar item dalam .list-item
· Delete: tidak ada hapus individual, seluruh konten dapat diganti.

1. TABEL JADWAL (SHIFT BOARD)

---

· Struktur: baris per personil, kolom: nama personil (sticky), lalu setiap hari memiliki 2 kolom (Slot A & B)
· Header hari: colspan=2 dengan format "Hari, Tanggal Bulan 'Tahun" (contoh: Senin, 12 Mei '26)
· Hari Sabtu & Minggu: teks bold & merah (kelas .th-weekend)
· Dropdown Slot A: opsi "-", "Off", "Shift-1", "Shift-2", "Shift-3" (lebar 85px, mobile 75px)
· Dropdown Slot B: opsi "-", "S1", "S2", "S3" (lebar 60px, mobile 50px)

1. WARNA LATAR SEL (BACKGROUND)

---

Mode Terang (Light):

· kosong ("-") : #f0f0f0 (abu-abu muda)
· Off         : #ffaaaa (merah muda)
· Shift-1     : #fff176 (kuning)
· Shift-2     : #64b5f6 (biru)
· Shift-3     : #a5d6a7 (hijau)

Mode Gelap (Dark):

· kosong       : #2d3748
· Off          : #b33a3a
· Shift-1      : #c9b13b
· Shift-2      : #2c6e9e
· Shift-3      : #2e7d32

1. SISTEM VALIDASI REAL-TIME (Urutan)

---

1. Prioritas Slot A: Slot B tidak boleh diisi jika Slot A kosong (null).
2. Larangan Double Off: kedua slot tidak boleh sama-sama Off.
3. Konstrain Slot A=Off: jika Slot A = Off, Slot B hanya boleh kosong ("-").
4. Larangan shift ganda person: satu orang tidak boleh memiliki shift yang sama (1,2,3) di kedua slot pada hari yang sama.
5. Maksimal 2 orang per shift per hari: jumlah personil yang memiliki shift tertentu (dari kedua slot) tidak boleh melebihi 2.

Setiap pelanggaran → alert dengan pesan bahasa Indonesia → perubahan dibatalkan (rollback ke nilai sebelumnya).

1. MODE TAMPILAN

---

6.1 Filter Minggu / Bulan

· Minggu: menampilkan 7 hari dari Senin hingga Minggu
· Bulan: menampilkan semua tanggal dalam bulan berjalan (dari tanggal 1 hingga akhir bulan)

6.2 Navigasi

· Tombol ◀ / ▶ : geser minggu (7 hari) atau bulan (1 bulan)
· Label rentang: menampilkan periode (contoh: 12/5 - 18/5)

6.3 Light / Dark Mode

· Tombol 🌓 di header → toggle tema → status tersimpan di localStorage

1. ANTARMUKA TAB (JADWAL vs PENGATURAN)

---

· Tab "Jadwal": berisi action bar (backup, restore, CSV, HTML, reset), tabel shift board, dan dua card rekap.
· Tab "Pengaturan": berisi tiga card CRUD (Personil, Shift, Info). Masing-masing card dapat ditoggle (sembunyikan/tampilkan) dengan tombol 🔽/🔼.
· Tab aktif disimpan di localStorage.

1. CARD REKAP & STATISTIK

---

8.1 Rekap Personil

· Menampilkan setiap personil: jumlah Off, Shift-1, Shift-2, Shift-3 pada rentang hari yang ditampilkan.
· Format: "Dandi → Off:2 | S1:1 | S2:0 | S3:1" dengan badge warna.
· Area scrollable jika konten melebihi tinggi.

8.2 Total Shift Board

· Menampilkan total kemunculan Shift-1, Shift-2, Shift-3 di kedua slot seluruh personil.
· Contoh: "S1: 5 | S2: 3 | S3: 2"

1. FITUR EKSPOR

---

9.1 Ekspor CSV

· Header: "Personil", "<Tanggal1>", "<Tanggal2>", ...
· Setiap sel: gabungan nilai Slot A dan Slot B dipisah " | "
· Nilai shift: S1, S2, S3; Off tetap "Off"; kosong "-"
· Encoding: UTF-8 dengan BOM (\uFEFF)
· Nama file: shift_<timestamp>.csv

9.2 Ekspor HTML

· Output file HTML mandiri yang berisi kloningan tabel shift board (lengkap dengan warna sel)
· Menyertakan CSS internal untuk mempertahankan tampilan
· Nama file: shift_<timestamp>.html

1. FITUR RESET

---

· Tombol "⚠️ Reset" pada tab Jadwal
· Menghapus semua data jadwal (scheduleDB) menjadi kosong (null) → semua slot menjadi "-"
· Tidak menghapus master personil/shift atau info card
· Konfirmasi dengan window.confirm

1. BACKUP & RESTORE (PENUH)

---

· Backup: menyimpan seluruh state aplikasi ke file JSON (shift_full_backup_<timestamp>.json)
· Restore: memilih file JSON hasil backup → mengganti semua data (master, schedule, info, preferensi, toggle, tab aktif)
· Setelah restore, tampilan langsung diperbarui.

1. TOGGLE CARD (SEMBUNYIKAN/TAMPILKAN)

---

· Pada tab Pengaturan, setiap card memiliki tombol 🔽/🔼 di pojok kanan header.
· Status toggle (tersembunyi/tampil) disimpan di localStorage dan ikut di-backup.
· Jika card disembunyikan, seluruh konten (daftar, tombol tambah) tidak dirender.

1. RESPONSIVITAS MOBILE (lebar ≤ 640px)

---

· Tools header (select minggu/bulan, navigasi, dark mode) dalam satu baris horizontal dengan overflow auto.
· Action bar (backup, restore, CSV, HTML, reset) hanya menampilkan ikon (teks disembunyikan).
· Recap section: dari 2 kolom menjadi 1 kolom (susun vertikal).
· Lebar dropdown: Slot A 75px, Slot B 50px.
· Tab button padding lebih kecil.

1. MODAL

---

14.1 Modal Welcome

· Muncul setiap kali halaman dimuat (tanpa flag penyembunyian).
· Berisi informasi singkat fitur utama.
· Tombol "Mulai Gunakan" untuk menutup.

14.2 Modal Edit Info

· Muncul saat tombol ✏️ pada card Info ditekan.
· Textarea untuk mengedit konten (satu baris = satu item list).
· Tombol Simpan & Batal.

1. ALUR RENDER UTAMA

---

Setiap perubahan data (dropdown, CRUD, toggle, navigasi) memicu:
saveFullAppState() → localStorage → renderAll()
├─ buildDaysArray()
├─ renderShiftTable()
├─ renderPersonnelList()
├─ renderShiftList()
├─ renderInfoCard()
├─ renderRecapAndTotals()
├─ applyToggleUI()
└─ (mode mobile) ubah action bar menjadi icon only

1. KEAMANAN & PERFORMANCE

---

· Semua input user (nama personil, konten info) di-escape dengan escapeHtml() sebelum di-render.
· Event listener dipasang ulang setiap render untuk menghindari duplikasi.
· Tidak menggunakan library eksternal.
· Semua data disimpan lokal, tanpa cookie/tracking.

1. DEPENDENSI

---

· Hanya vanilla HTML/CSS/JavaScript (ES6)
· Font: 'Inter' dengan fallback system-ui.

1. INFORMASI TAMBAHAN

---

· Aplikasi berjalan sepenuhnya offline setelah dimuat.
· Tidak memerlukan server atau koneksi internet.
· Cocok untuk manajemen shift internal kantor.

============================================================
Akhir Ringkasan Fitur Teknis
============================================================
