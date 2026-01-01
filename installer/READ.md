LANGKAH INSTLASI 
* <style>: Mengandung CSS modern untuk tampilan yang bersih, rapi, dan profesional.
```
body              : Latar belakang gradient yang menarik.
.container        : Kotak utama dengan bayangan dan radius.
Form Elements     : Input dan tombol memiliki styling yang konsisten, termasuk efek :focus dan :hover.
.error & .success : Warna dan gaya khusus untuk pesan status.
.password-toggle  : Untuk menempatkan tombol show/hide secara absolut di dalam input.
```
* <script>: Fungsi sederhana togglePassword untuk mengganti tipe input password menjadi teks dan sebaliknya.
* Form: Menyertakan semua field yang diperlukan, termasuk yang baru (upload_dir, admin_password) dengan placeholder dan nilai default.
* Tampilan Sukses: Menampilkan username dan password admin setelah instalasi berhasil.
* Tombol Reset: Tautan untuk menghapus file konfigurasi dan memulai ulang proses instalasi.
* Integrasi (Opsional)
Seperti sebelumnya, jika Anda ingin form ini muncul otomatis saat aplikasi belum diinstal, sertakan logika berikut di awal index.php utama Anda:
````
// Di awal index.php utama Anda
if (!file_exists('.db_config')) {
    include 'installer.php';
    exit; // Hentikan eksekusi bagian utama jika sedang menginstal
}

// ... lanjutkan dengan logika utama dan koneksi database ...
````
lakukan pemberian permission access direktori uploads
```
chmod -R 777 uploads
```
