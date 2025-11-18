## Main Plan SQL:
```
✔ Struktur PKL lengkap
✔ Manajemen kelompok
✔ Guru & pembimbing industri
✔ Pengajuan → Pengantaran → Monitoring → Penjemputan
✔ Progress tracking
✔ PRESENSI (foto + geolokasi + validasi radius)
✔ Hasil validasi presensi
✔ Log wajah (face recognition)
✔ Audit log
✔ Lampiran dokumen
```

## ALUR LENGKAP BERBASIS TABEL
```
Panitia membuat periode_pkl
Siswa masuk ke kelompok_pkl
Kelompok dikirim ke pengajuan_kelompok
Jika disetujui → status kelompok jadi disetujui
Panitia menjadwalkan pengantaran
Pembimbing melakukan monitoring berkala
Panitia menjadwalkan penjemputan
Semua kegiatan tercatat di progress_kelompok
Semua aksi dicatat di log_aktivitas
```

## HASIL AKHIR
Dengan struktur ini Anda mendapatkan:
```
✔ Alur PKL lengkap, mulai dari pengajuan → penjemputan
✔ Tracking ketat setiap tahapan
✔ Mendukung banyak jurusan, kelompok, industri
✔ Mendukung pembimbing sekolah & pendamping industri
✔ Ramah reporting (per periode / jurusan / industri / pembimbing)
✔ Cocok mobile, mudah dikembangkan untuk presensi, geolokasi, dsb
```
