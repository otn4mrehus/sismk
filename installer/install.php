<?php
/**
 * Template Instalasi Database Modern & Interaktif
 * Berdasarkan: SISTEM E-PKL SMK
 * Tujuan: Menyediakan proses instalasi database, persistensi konfigurasi,
 *         konfigurasi direktori upload, dan password admin dengan UI modern.
 */

// --- LANGKAH 1: Konfigurasi File Persistensi ---
$config_file = __DIR__ . '/.db_config';

// --- LANGKAH 2: Cek Mode Instalasi ---
$is_install_mode = !file_exists($config_file);

// --- LANGKAH 3: Proses Form Instalasi (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install_db') {
    try {
        $host = $_POST['db_host'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        $name = $_POST['db_name'];
        $upload_dir = $_POST['upload_dir'];
        $admin_password = $_POST['admin_password'];

        // Sanitasi input (opsional tetapi disarankan)
        $upload_dir = preg_replace('/[^a-zA-Z0-9_-]/', '', $upload_dir);
        if (empty($upload_dir)) {
            throw new Exception("Nama direktori upload tidak valid.");
        }

        // Koneksi sementara
        $tempConn = new PDO("mysql:host=$host", $user, $pass);
        $tempConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Buat dan pilih database
        $tempConn->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tempConn->exec("USE `$name`");

        // Skema Tabel
        $sql_tables = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'operator', 'viewer') NOT NULL,
            full_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS data_contoh (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $tempConn->exec($sql_tables);

        // Data Awal
        $default_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $tempConn->exec("INSERT INTO users (username, password, role, full_name) VALUES ('admin', '$default_password_hash', 'admin', 'Administrator')");

        // Buat Direktori Upload
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $subdirs = ['izin', 'sakit', 'lainnya'];
        foreach ($subdirs as $subdir) {
            $full_path = $upload_dir . '/' . $subdir;
            if (!file_exists($full_path)) {
                mkdir($full_path, 0777, true);
            }
        }

        // Simpan Konfigurasi
        $new_config = [
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'name' => $name,
            'upload_dir' => $upload_dir
        ];

        $file_written = @file_put_contents($config_file, json_encode($new_config));
        if ($file_written === false) {
            throw new Exception("Gagal menyimpan file konfigurasi '$config_file'.");
        }

        // Sukses
        $install_success = true;
        $admin_username = 'admin';
        $admin_password_shown = $admin_password; // Hanya untuk ditampilkan, tidak disimpan

    } catch (PDOException $e) {
        $install_error = "Kesalahan Database: " . $e->getMessage();
    } catch (Exception $e) {
        $install_error = "Kesalahan Umum: " . $e->getMessage();
    }
}

// --- LANGKAH 13: (Opsional) Fungsi untuk Reset Instalasi ---
if (isset($_GET['action']) && $_GET['action'] === 'reset_install') {
    if (file_exists($config_file)) {
        unlink($config_file);
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Aplikasi</title>
    <style>
        /* Reset dan Styling Dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            padding: 30px;
            text-align: center;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #34495e;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .btn {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .error {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #e74c3c;
        }
        .success {
            color: #27ae60;
            background-color: #d5f4e6;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #27ae60;
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            font-size: 14px;
        }
        .password-toggle-btn:hover {
            color: #3498db;
        }
        .reset-link {
            display: block;
            margin-top: 15px;
            color: #e74c3c;
            text-decoration: none;
            font-size: 14px;
        }
        .reset-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="container">
        <?php if (isset($install_success) && $install_success): ?>
            <!-- Tampilan Sukses -->
            <h2>Instalasi Berhasil!</h2>
            <div class="success">
                <p>Database telah berhasil diinstal dan dikonfigurasi.</p>
                <p><strong>Username Admin:</strong> <?php echo htmlspecialchars($admin_username); ?></p>
                <p><strong>Password Admin:</strong> <?php echo htmlspecialchars($admin_password_shown); ?></p>
                <p style="margin-top: 10px;"><em>Harap simpan informasi ini dengan aman.</em></p>
            </div>
            <a href="./" class="btn" style="background-color: #27ae60; margin-top: 20px;">Lanjutkan ke Aplikasi</a>
        <?php elseif ($is_install_mode): ?>
            <!-- Tampilan Form Instalasi -->
            <h2>Setup Instalasi Aplikasi</h2>
            <?php if (isset($install_error)): ?>
                <div class="error"><?php echo htmlspecialchars($install_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="install_db">
                <div class="form-group">
                    <label for="db_host">Host Database</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="db_user">User Database</label>
                    <input type="text" id="db_user" name="db_user" value="root" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Password Database</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                <div class="form-group">
                    <label for="db_name">Nama Database Baru</label>
                    <input type="text" id="db_name" name="db_name" value="db_nama_aplikasi" required>
                </div>
                <div class="form-group">
                    <label for="upload_dir">Nama Direktori Upload</label>
                    <input type="text" id="upload_dir" name="upload_dir" value="uploads" required placeholder="Contoh: uploads, files">
                </div>
                <div class="form-group password-toggle">
                    <label for="admin_password">Password Default Admin</label>
                    <input type="password" id="admin_password" name="admin_password" value="admin123" required placeholder="Password untuk akun admin">
                    <button type="button" class="password-toggle-btn" onclick="togglePassword('admin_password')">Lihat</button>
                </div>
                <button type="submit" class="btn">Install Aplikasi</button>
            </form>
            <a href="?action=reset_install" class="reset-link">Ulang Instalasi (Reset Konfigurasi)</a>
        <?php else: ?>
            <!-- Jika bukan mode instalasi dan tidak berhasil -->
            <h2>Aplikasi Sudah Terinstal</h2>
            <p>Silakan akses aplikasi utama.</p>
            <a href="./" class="btn" style="background-color: #27ae60; margin-top: 20px;">Ke Halaman Utama</a>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleBtn = passwordField.nextElementSibling; // Tombol 'Lihat'
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = 'Sembunyikan';
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = 'Lihat';
            }
        }
    </script>

</body>
</html>
