-- Galeri Foto Sekolah Database Schema
-- MySQL 5.7+

-- Create database
CREATE DATABASE IF NOT EXISTS galeri_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE galeri_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'superadmin') DEFAULT 'user' COMMENT 'superadmin untuk system, admin untuk manajemen',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos table
CREATE TABLE IF NOT EXISTS photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 untuk guest/tamu',
    username VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) COMMENT 'kategori ID(s) dipisahkan koma',
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=public, 1=private',
    views INT UNSIGNED NOT NULL DEFAULT 0,
    batch_id VARCHAR(50) COMMENT 'group ID untuk batch upload',
    album_id VARCHAR(50) COMMENT 'album ID utama untuk grouping sub-grup',
    album_title VARCHAR(255) COMMENT 'judul album/sub-grup',
    photo_label VARCHAR(255) DEFAULT NULL COMMENT 'label/keterangan per foto',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_hash VARCHAR(32) COMMENT 'MD5 hash untuk deteksi duplikasi',
    INDEX idx_user_id (user_id),
    INDEX idx_category (category),
    INDEX idx_batch_id (batch_id),
    INDEX idx_album_id (album_id),
    INDEX idx_created_at (created_at),
    INDEX idx_file_hash (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Views/Visits table
CREATE TABLE IF NOT EXISTS views (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 untuk guest/tamu',
    ip VARCHAR(45) COMMENT 'IPv4 atau IPv6',
    user_agent VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_photo_id (photo_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photo edits log table
CREATE TABLE IF NOT EXISTS photo_edits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id INT UNSIGNED NOT NULL,
    editor_id INT UNSIGNED NOT NULL,
    editor_name VARCHAR(50) NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_photo_id (photo_id),
    INDEX idx_editor_id (editor_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO users (id, username, password, role, created_at) VALUES 
(1, 'admin', '$2y$10$mF94LiZ0DTgNUUViuvz9WOvGTUShLFQ5wlB63m10B20Xbgaj0G6xi', 'admin', '2026-03-07 10:19:58')
ON DUPLICATE KEY UPDATE username = username;

-- Insert default user (password: user123)
INSERT INTO users (id, username, password, role, created_at) VALUES 
(2, 'user', '$2y$10$2ZznNhM6XBmqim2iZFhNreoltxo473fzN5n2rF1S0HggkXU9LxSN2', 'user', '2026-03-07 10:19:58')
ON DUPLICATE KEY UPDATE username = username;

-- Insert default categories
INSERT INTO categories (id, name, slug, created_at) VALUES 
(1, 'Alam', 'alam', '2026-03-07 10:19:58'),
(2, 'Portrait', 'portrait', '2026-03-07 10:19:58'),
(3, 'Teknologi', 'teknologi', '2026-03-07 10:19:58'),
(4, 'Seni', 'seni', '2026-03-07 10:19:58'),
(5, 'Lainnya', 'lainnya', '2026-03-07 10:19:58'),
(6, 'Mushola', 'mushola', '2026-03-07 11:33:15')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert existing photos from CSV
INSERT INTO photos (id, user_id, username, title, description, category, filename, filepath, is_private, views, batch_id, album_id, album_title, photo_label, created_at, file_hash) VALUES 
(1, 9999, 'superadmin', 'N8N', 'Server AgentAI', '3', 'N8N-STB_09-03-2026_13.38.png', 'upload/foto/2026/03/teknologi/n8n/N8N-STB_09-03-2026_13.38.png', 0, 0, 'batch_69ae6ae1b0540', 'album-n8n', 'N8N Server', 'Server utama', '2026-03-09 13:38:29', 'fbd99bdb0bf8d76e2611b95b4f7ff3c2'),
(2, 0, 'Suher', 'RSync', 'Server', '3', 'RSync_09-03-2026_13.40.png', 'upload/foto/2026/03/teknologi/rsync/RSync_09-03-2026_13.40.png', 0, 0, 'batch_69ae6b53a4091', 'album-rsync', 'RSync Server', 'Konfigurasi', '2026-03-09 13:40:22', '252fc7c564646dc798928ba0b19050ae')
ON DUPLICATE KEY UPDATE title = VALUES(title);
