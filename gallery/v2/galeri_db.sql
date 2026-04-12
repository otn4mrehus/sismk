SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS galeri_db DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE galeri_db;

CREATE TABLE categories (
  id int(10) UNSIGNED NOT NULL,
  name varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  slug varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photos (
  id int(10) UNSIGNED NOT NULL,
  user_id int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0 untuk guest/tamu',
  username varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  title varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  description text COLLATE utf8mb4_unicode_ci,
  category varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'kategori ID(s) dipisahkan koma',
  filename varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  filepath varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  is_private tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=public, 1=private',
  views int(10) UNSIGNED NOT NULL DEFAULT '0',
  batch_id varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'group ID untuk batch upload',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  file_hash varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MD5 hash untuk deteksi duplikasi',
  album_id varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  album_title varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  photo_label varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  is_album_cover tinyint(1) NOT NULL DEFAULT '0',
  album_sort_order int(10) UNSIGNED DEFAULT '0',
  album_order int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photo_edits (
  id int(10) UNSIGNED NOT NULL,
  photo_id int(10) UNSIGNED NOT NULL,
  editor_id int(10) UNSIGNED NOT NULL,
  editor_name varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  field_name varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  old_value text COLLATE utf8mb4_unicode_ci,
  new_value text COLLATE utf8mb4_unicode_ci,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id int(10) UNSIGNED NOT NULL,
  username varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  password varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  role enum('user','admin','superadmin') COLLATE utf8mb4_unicode_ci DEFAULT 'user' COMMENT 'superadmin untuk system, admin untuk manajemen',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE views (
  id int(10) UNSIGNED NOT NULL,
  photo_id int(10) UNSIGNED NOT NULL,
  user_id int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0 untuk guest/tamu',
  ip varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IPv4 atau IPv6',
  user_agent varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE categories
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY slug (slug),
  ADD KEY idx_slug (slug);

ALTER TABLE photos
  ADD PRIMARY KEY (id),
  ADD KEY idx_user_id (user_id),
  ADD KEY idx_category (category),
  ADD KEY idx_batch_id (batch_id),
  ADD KEY idx_created_at (created_at),
  ADD KEY idx_file_hash (file_hash),
  ADD KEY idx_album_id (album_id),
  ADD KEY idx_is_album_cover (is_album_cover);

ALTER TABLE photo_edits
  ADD PRIMARY KEY (id),
  ADD KEY idx_photo_id (photo_id),
  ADD KEY idx_editor_id (editor_id),
  ADD KEY idx_created_at (created_at);

ALTER TABLE users
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY username (username),
  ADD KEY idx_username (username),
  ADD KEY idx_role (role);

ALTER TABLE views
  ADD PRIMARY KEY (id),
  ADD KEY idx_photo_id (photo_id),
  ADD KEY idx_user_id (user_id),
  ADD KEY idx_created_at (created_at);


ALTER TABLE categories
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE photos
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE photo_edits
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE users
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE views
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
