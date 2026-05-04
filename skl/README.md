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
```
name,nisn,birth_place,birth_date,class,status,photo,skl
ACHMAD ABU SOFYAN,0084640520,SERANG,11 April 2008,XII AK 1,LULUS,foto_0084640520.jpg,skl_0084640520.pdf
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
```

### 2. Beranda
```
http://ip_address/skl/
```
