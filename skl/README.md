# Instalasi
## Direktori
### 1. Perintah
```
sudo mkdir -p upload/{foto,skl} assets/images data sessions tools
```
### 2. Bentuk struktur direktori
```
upload/
├── foto/
└── skl/

assets/
└── images/    -->> LOGO disini

data/

sessions/

tools/
```

## Penyiapan data dan log 
### 1.Data Log Akses
```
sudo touch access_log.csv  # Jika belum ada atau langsung
sudo chmod 0777 access_log.csv
sudo touch data/access_log.csv  # Jika belum ada atau langsung
sudo chmod 0777 data/access_log.csv
```
### 2.Data Siswa dan Aksesnya
```
sudo touch data/siswa.csv
sudo chmod 0777 data/siswa.csv
sudo nano data/siswa.csv
```
##### Format siswa.csv
pastikan format Foto dan Dokumen SKL dengan format ini
```
name,nisn,birth_place,birth_date,class,status,photo,skl
SiFulan,008111120,SERANG,11 April 2001,XII AP 1,LULUS,foto_008111120.jpg,skl_008111120.pdf
```
### 3. Data Siswa dan Aksesnya
```
sudo touch data/settings.csv
sudo chmod 0777 data/settings.csv
sudo nano data/settings.csv
```
##### Format settings.csv (Akun Login - admin) dan Tanggal Pengumuman
```
announcement_time,2026-05-04T17:30
admin_username,admin
admin_password,123
"2026-05-06 17:30:00","SMKN ? Kota Serang"
```

## Running
### 1. Admin
```
http://ip_address/skl/?page=admin_login
Gunakan sesuai setting: admin / 123
```

### 2. Beranda
```
http://ip_address/skl/
```
