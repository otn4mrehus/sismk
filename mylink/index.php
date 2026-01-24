<?php
/**
 * SINGLE FILE PRIVATE LINK WEBSITE (SPA) - USER SHARING SYSTEM
 * PHP 7.4+ / MySQL 5.7+
 * 
 * PERBAIKAN & FITUR:
 * 1. User Approval System: User register = Inactive (Menunggu Admin).
 * 2. Multi-Select Share: Modal sharing user dengan checklist multiple.
 * 3. Code Cleanup: Perbaikan bug API dan struktur kode.
 */

// --- KONFIGURASI DATABASE ---
 $db_host = '_HOST_';
 $db_name = '_DATABASE_';
 $db_user = '_USER_';
 $db_pass = '_PASS_';

// --- KONEKSI & AUTO-INSTALL ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Setup Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        is_active TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // MIGRATION: Cek kolom tambahan
    $stmtColActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($stmtColActive->rowCount() == 0) { $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 0"); }
    $stmtColRole = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmtColRole->rowCount() == 0) { $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user'"); }

    // 2. Setup Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Setup Links
    $stmtCheck = $pdo->query("SHOW TABLES LIKE 'links'");
    $tableExists = ($stmtCheck->rowCount() > 0);

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category_id INT,
            url VARCHAR(255) NOT NULL,
            title VARCHAR(150) NOT NULL,
            description TEXT,
            image_path VARCHAR(255) NULL,
            is_public TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )");
    } else {
        // Migrations Links
        $stmtCol = $pdo->query("SHOW COLUMNS FROM links LIKE 'is_public'");
        if ($stmtCol->rowCount() == 0) $pdo->exec("ALTER TABLE links ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER image_path");
        $stmtPath = $pdo->query("SHOW COLUMNS FROM links LIKE 'image_path'");
        if ($stmtPath->rowCount() == 0) $pdo->exec("ALTER TABLE links ADD COLUMN image_path VARCHAR(255) NULL AFTER description");
    }

    // 4. Setup Link Shares (Fitur Share ke User lain)
    $pdo->exec("CREATE TABLE IF NOT EXISTS link_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_id INT NOT NULL,
        shared_to_user_id INT NOT NULL,
        UNIQUE KEY unique_share (link_id, shared_to_user_id),
        FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_to_user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 5. SETUP PERMANENT ADMIN
    $stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmtAdmin->execute();
    if ($stmtAdmin->rowCount() == 0) {
        $defaultPassHash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, role, is_active) VALUES ('admin', '$defaultPassHash', 'admin', 1)");
    }

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

session_start();

// --- BACKEND API ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    $response = ['status' => 'error', 'message' => 'Invalid request'];

    try {
        // 1. AUTH
        if ($action === 'login') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($data['password'], $user['password'])) {
                // Cek Approval System
                if ($user['is_active'] == 0) {
                    $response = ['status' => 'error', 'message' => 'Akun Anda belum disetujui oleh Administrator.'];
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'user';
                    $response = ['status' => 'success'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Username atau password salah.'];
            }
        }
        elseif ($action === 'register') {
            $data = json_decode(file_get_contents('php://input'), true);
            // Cek duplikat username
            $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $cek->execute([$data['username']]);
            if($cek->rowCount() > 0){
                $response = ['status' => 'error', 'message' => 'Username sudah digunakan.'];
            } else {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                // Default is_active = 0 (Butuh approval admin)
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'user', 0)");
                $stmt->execute([$data['username'], $hash]);
                $response = ['status' => 'success', 'message' => 'Registrasi berhasil. Silakan tunggu persetujuan Admin.'];
            }
        }
        elseif ($action === 'logout') { 
            session_destroy(); 
            $response = ['status' => 'success']; 
        }
        elseif ($action === 'check_session') {
            $response = [
                'status' => 'success', 
                'logged_in' => isset($_SESSION['user_id']), 
                'username' => $_SESSION['username'] ?? null,
                'role' => $_SESSION['role'] ?? null
            ];
        }

        // 2. PUBLIC API
        elseif ($action === 'get_public_links') {
            $search = $_GET['search'] ?? '';
            $catName = $_GET['cat'] ?? '';

            $sql = "SELECT links.*, categories.name as category_name 
                    FROM links 
                    LEFT JOIN categories ON links.category_id = categories.id 
                    WHERE links.is_public = 1";
            $params = [];
            if ($search) {
                $sql .= " AND (links.title LIKE ? OR links.description LIKE ?)";
                $term = "%$search%";
                $params[] = $term; $params[] = $term;
            }
            if ($catName) {
                $sql .= " AND categories.name = ?";
                $params[] = $catName;
            }
            $sql .= " ORDER BY links.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $links = $stmt->fetchAll();

            foreach ($links as &$link) {
                if (!empty($link['image_path']) && file_exists($link['image_path'])) {
                    $link['image_src'] = $link['image_path'];
                } else {
                    $link['image_src'] = 'https://picsum.photos/seed/'.$link['id'].'/200/200.jpg';
                }
            }
            $response = ['status' => 'success', 'data' => $links];
        }

        // 3. ADMIN DATA (LOGIN REQUIRED)
        elseif (isset($_SESSION['user_id'])) {
            $uid = $_SESSION['user_id'];
            $role = $_SESSION['role'];

            // --- USER MANAGEMENT (ADMIN ONLY) ---
            if ($action === 'get_users') {
                if ($role !== 'admin') throw new Exception("Unauthorized");
                $stmt = $pdo->query("SELECT id, username, role, is_active, created_at FROM users ORDER BY created_at DESC");
                $response = ['status' => 'success', 'data' => $stmt->fetchAll()];
            }
            elseif ($action === 'update_user_status') {
                if ($role !== 'admin') throw new Exception("Unauthorized");
                $data = json_decode(file_get_contents('php://input'), true);
                $targetId = $data['id'];
                $status = $data['status']; // 0 atau 1
                
                // Cegah admin non-aktifkan diri sendiri
                if ($targetId == $uid && $status == 0) {
                    throw new Exception("Tidak bisa menonaktifkan diri sendiri.");
                }

                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$status, $targetId]);
                $response = ['status' => 'success'];
            }

            // --- SHARING LOGIC (MULTI-SELECT) ---
            elseif ($action === 'get_share_users') {
                // Ambil daftar user yang bisa di-share (Kecuali diri sendiri & admin system jika perlu)
                // PERBAIKAN BUG: Array key response yang benar
                $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id != ? ORDER BY username ASC");
                $stmt->execute([$uid]);
                $response = ['status' => 'success', 'data' => $stmt->fetchAll()];
            }
            elseif ($action === 'get_link_shared_users') {
                // Ambil user yang sudah punya akses ke link tertentu
                $linkId = $_GET['link_id'];
                $stmt = $pdo->prepare("SELECT shared_to_user_id FROM link_shares WHERE link_id = ?");
                $stmt->execute([$linkId]);
                $sharedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $response = ['status' => 'success', 'data' => $sharedIds];
            }
            elseif ($action === 'share_link') {
                // Bagikan link ke beberapa user sekaligus (Multi Select)
                $data = json_decode(file_get_contents('php://input'), true);
                $linkId = $data['link_id'];
                $targetIds = $data['target_ids']; // Array of user IDs
                
                if (empty($targetIds)) {
                    // Jika kosong, tidak perlu insert
                    $response = ['status' => 'success'];
                } else {
                    // Gunakan INSERT IGNORE untuk handle duplikat ID
                    $sql = "INSERT IGNORE INTO link_shares (link_id, shared_to_user_id) VALUES ";
                    $values = [];
                    $params = [];
                    foreach ($targetIds as $tid) {
                        $values[] = "(?, ?)";
                        $params[] = $linkId;
                        $params[] = $tid;
                    }
                    
                    $fullSql = $sql . implode(',', $values);
                    $stmt = $pdo->prepare($fullSql);
                    $stmt->execute($params);
                    $response = ['status' => 'success'];
                }
            }
            elseif ($action === 'unshare_link') {
                // Membatalkan share ke user tertentu
                $data = json_decode(file_get_contents('php://input'), true);
                $linkId = $data['link_id'];
                $targetId = $data['target_user_id'];
                
                $stmt = $pdo->prepare("DELETE FROM link_shares WHERE link_id = ? AND shared_to_user_id = ?");
                $stmt->execute([$linkId, $targetId]);
                $response = ['status' => 'success'];
            }

            // --- CATEGORIES ---
            if ($action === 'get_categories') {
                if ($role === 'admin') {
                    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
                    $stmt->execute([$uid]);
                }
                $response = ['status' => 'success', 'data' => $stmt->fetchAll()];
            }
            elseif ($action === 'add_category') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
                $stmt->execute([$uid, $data['name']]);
                $response = ['status' => 'success'];
            }
            elseif ($action === 'update_category') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['id']]);
                $response = ['status' => 'success'];
            }
            elseif ($action === 'delete_category') {
                $data = json_decode(file_get_contents('php://input'), true);
                if ($role === 'admin') {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$data['id']]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
                    $stmt->execute([$data['id'], $uid]);
                }
                $response = ['status' => 'success'];
            }

            // --- LINKS ---
            elseif ($action === 'search_suggestions') {
                $q = $_GET['q'] ?? '';
                if ($q) {
                    if ($role === 'admin') {
                        $stmt = $pdo->prepare("SELECT DISTINCT title FROM links WHERE title LIKE ? LIMIT 5");
                        $stmt->execute(["%$q%"]);
                    } else {
                        $stmt = $pdo->prepare("SELECT DISTINCT title FROM links WHERE user_id = ? AND title LIKE ? LIMIT 5");
                        $stmt->execute([$uid, "%$q%"]);
                    }
                    $response = ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)];
                }
            }

            elseif ($action === 'get_links') {
                $search = $_GET['search'] ?? '';
                $catId = isset($_GET['cat_id']) ? $_GET['cat_id'] : null;
                $dateStart = $_GET['date_start'] ?? '';
                $dateEnd = $_GET['date_end'] ?? '';

                // LOGIC: User bisa lihat Link sendiri, Link yang di-share, dan Link Publik.
                // Admin bisa lihat SEMUA.
                $sql = "SELECT links.*, categories.name as category_name, users.username as owner_name 
                        FROM links 
                        LEFT JOIN categories ON links.category_id = categories.id 
                        LEFT JOIN users ON links.user_id = users.id
                        WHERE 1=1"; 
                
                $params = [];
                if ($role !== 'admin') {
                    // User biasa: Milik sendiri, ATAU Publik, ATAU Di-share ke saya
                    $sql .= " AND (links.user_id = ? OR links.is_public = 1 OR links.id IN (SELECT link_id FROM link_shares WHERE shared_to_user_id = ?))";
                    $params[] = $uid;
                    $params[] = $uid;
                }

                if ($search) {
                    $sql .= " AND (links.title LIKE ? OR links.description LIKE ?)";
                    $term = "%$search%";
                    $params[] = $term; $params[] = $term;
                }
                if ($catId) { $sql .= " AND links.category_id = ?"; $params[] = $catId; }
                if ($dateStart) { $sql .= " AND DATE(links.created_at) >= ?"; $params[] = $dateStart; }
                if ($dateEnd) { $sql .= " AND DATE(links.created_at) <= ?"; $params[] = $dateEnd; }
                
                $sql .= " ORDER BY links.id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $links = $stmt->fetchAll();

                foreach ($links as &$link) {
                    if (!empty($link['image_path']) && file_exists($link['image_path'])) {
                        $link['image_src'] = $link['image_path'];
                    } else {
                        $link['image_src'] = 'https://picsum.photos/seed/'.$link['id'].'/200/200.jpg';
                    }
                }
                $response = ['status' => 'success', 'data' => $links];
            }

            elseif ($action === 'add_link' || $action === 'update_link') {
                $title = $_POST['title'] ?? '';
                $url = $_POST['url'] ?? '';
                $desc = $_POST['description'] ?? '';
                $catId = (!empty($_POST['category_id']) && $_POST['category_id'] !== "") ? $_POST['category_id'] : null;
                $isPublic = isset($_POST['is_public']) ? 1 : 0;
                
                $hasNewImage = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
                $imagePath = null;

                // Folder Logic
                $uploadDir = 'uploads';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $catFolderName = "General";
                if ($catId) {
                    $stmtCat = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmtCat->execute([$catId]);
                    $catNameRaw = $stmtCat->fetchColumn();
                    if ($catNameRaw) {
                        $catFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $catNameRaw);
                    }
                }
                $targetDir = $uploadDir . '/' . $catFolderName;
                if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

                if ($hasNewImage) {
                    $fileName = time() . '_' . basename($_FILES['image']['name']);
                    $targetFilePath = $targetDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                        $imagePath = $targetFilePath;
                    }
                }

                if ($action === 'add_link') {
                    $stmt = $pdo->prepare("INSERT INTO links (user_id, category_id, title, url, description, image_path, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$uid, $catId, $title, $url, $desc, $imagePath, $isPublic]);
                } else {
                    $id = $_POST['id'] ?? 0;
                    if ($hasNewImage) {
                        $stmtOld = $pdo->prepare("SELECT image_path FROM links WHERE id = ?");
                        $stmtOld->execute([$id]);
                        $oldPath = $stmtOld->fetchColumn();
                        if ($oldPath && file_exists($oldPath)) unlink($oldPath);

                        $stmt = $pdo->prepare("UPDATE links SET category_id=?, title=?, url=?, description=?, image_path=?, is_public=? WHERE id=?");
                        $stmt->execute([$catId, $title, $url, $desc, $imagePath, $isPublic, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE links SET category_id=?, title=?, url=?, description=?, is_public=? WHERE id=?");
                        $stmt->execute([$catId, $title, $url, $desc, $isPublic, $id]);
                    }
                }
                $response = ['status' => 'success'];
            }

            elseif ($action === 'batch_delete') {
                $data = json_decode(file_get_contents('php://input'), true);
                $ids = $data['ids'] ?? [];
                if (!empty($ids)) {
                    // Hapus fisik file
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmtPath = $pdo->prepare("SELECT image_path FROM links WHERE id IN ($placeholders)");
                    $stmtPath->execute($ids);
                    while ($row = $stmtPath->fetch()) {
                        if ($row['image_path'] && file_exists($row['image_path'])) unlink($row['image_path']);
                    }
                    
                    // Hapus DB
                    $sql = "DELETE FROM links WHERE id IN ($placeholders)";
                    $params = $ids;
                    if ($role !== 'admin') {
                        $sql .= " AND user_id = ?";
                        $params[] = $uid;
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                $response = ['status' => 'success'];
            }
            elseif ($action === 'delete_link') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'];
                
                // Hapus File
                $stmtPath = $pdo->prepare("SELECT image_path FROM links WHERE id = ?");
                $stmtPath->execute([$id]);
                $path = $stmtPath->fetchColumn();
                if ($path && file_exists($path)) unlink($path);
                
                // Hapus Shares (Cascade biasanya handle ini, tapi manual lebih aman jika cascade mati)
                $stmtDelShare = $pdo->prepare("DELETE FROM link_shares WHERE link_id = ?");
                $stmtDelShare->execute([$id]);

                if ($role === 'admin') {
                    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
                    $stmt->execute([$id]);
                } else {
                    // Cek kepemilikan sebelum hapus
                    $stmtCheck = $pdo->prepare("SELECT user_id FROM links WHERE id = ?");
                    $stmtCheck->execute([$id]);
                    $owner = $stmtCheck->fetchColumn();
                    if ($owner == $uid) {
                        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
                        $stmt->execute([$id]);
                    }
                }
                $response = ['status' => 'success'];
            }
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyLinks - Manager</title>
    <style>
        :root {
            --primary: #2563eb; --primary-dark: #1d4ed8; --bg: #f3f4f6;
            --surface: #ffffff; --text: #1f2937; --text-light: #6b7280;
            --border: #e5e7eb; --danger: #ef4444; --success: #10b981; --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }
        .hidden { display: none !important; }

        /* Components */
        .btn { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; background: white; transition: 0.2s; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-primary { background: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--danger); color: white; border-color: var(--danger); }
        .btn-success { background: var(--success); color: white; border-color: var(--success); }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
        .form-control { padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; width: 100%; }
        .form-check { display: flex; align-items: center; gap: 0.5rem; }

        /* Header */
        header { background: var(--surface); padding: 1rem 2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-weight: 800; font-size: 1.5rem; color: var(--primary); cursor: pointer; }

        /* Layouts */
        .main-layout { display: flex; flex: 1; overflow: hidden; }
        aside { width: 260px; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        main { flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; width: 100%; }

        /* Auth */
        .auth-container { display: flex; align-items: center; justify-content: center; height: 90vh; }
        .auth-card { background: var(--surface); padding: 2rem; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }

        /* Sidebar */
        .nav-item { padding: 0.75rem 1rem; cursor: pointer; display: flex; justify-content: space-between; font-size: 0.95rem; align-items: center; }
        .nav-item:hover, .nav-item.active { background: #eff6ff; color: var(--primary); }
        .nav-actions { opacity: 0; display: flex; gap: 0.5rem; transition: 0.2s; }
        .nav-item:hover .nav-actions { opacity: 1; }
        .action-btn { background: none; border: none; cursor: pointer; font-size: 1rem; }

        /* Filters */
        .filter-bar { background: var(--surface); padding: 1rem; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-light); margin-bottom: 0.3rem; }

        /* Content */
        .view-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        
        /* Grid View */
        .grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .card-img { width: 100%; height: 160px; object-fit: cover; background: #eee; }
        .card-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .card-tag { display: inline-flex; align-items: center; font-size: 0.75rem; color: var(--text-light); margin-bottom: 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-title { font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem; line-height: 1.4; }
        .card-desc { font-size: 0.9rem; color: var(--text-light); margin-bottom: 1.5rem; flex: 1; }
        .card-actions { margin-top: auto; display: flex; justify-content: flex-end; gap: 0.5rem; }
        
        .badge-public { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 4px; }
        .badge-private { background: #f3f4f6; color: #374151; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 4px; }
        .badge-shared { background: #fef3c7; color: #d97706; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 4px; }

        /* Table View */
        .table-container { overflow-x: auto; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; font-size: 0.9rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f9fafb; font-weight: 600; color: var(--text-light); position: sticky; top: 0; }
        .table-img { width: 40px; height: 40px; border-radius: 4px; object-fit: cover; vertical-align: middle; }
        .row-selected { background-color: #eff6ff; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999; }
        .modal { background: var(--surface); width: 90%; max-width: 500px; border-radius: 8px; padding: 1.5rem; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; }
        .modal-header { font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; }
        
        /* Toast */
        .toast { position: fixed; bottom: 20px; right: 20px; padding: 10px 20px; background: #333; color: white; border-radius: 4px; z-index: 10000; animation: fadeIn 0.3s; }
        .toast.success { background: var(--primary); }
        .toast.error { background: var(--danger); }
        .toast.warning { background: var(--warning); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Auto-complete & Share List */
        .list-scrollable { max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 4px; margin-top: 0.5rem; }
        .list-item { padding: 0.75rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: background 0.2s;}
        .list-item:hover { background: #f9fafb; }
        .share-selected { background: #eff6ff; }
        .share-selected:hover { background: #dbeafe; }

        @media (max-width: 768px) {
            .main-layout { flex-direction: column; }
            aside { width: 100%; max-height: 150px; border-right: none; border-bottom: 1px solid var(--border); overflow-y: auto; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .grid-view { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div id="app">
    <div id="toastContainer"></div>

    <!-- 1. PUBLIC SECTION -->
    <section id="publicSection">
        <header>
            <div class="logo" onclick="location.reload()">MyLinks</div>
            <div id="publicAuthButtons">
                <button class="btn btn-outline" onclick="checkSession(true)">Login / Dashboard</button>
            </div>
        </header>
        
        <div class="container">
            <div style="text-align: center; margin-bottom: 3rem;">
                <h1 style="font-size: 2.5rem; color: var(--text); margin-bottom: 0.5rem;">Koleksi Link Publik</h1>
                <p style="color: var(--text-light);">Jelajahi sumber daya favorit yang dibagikan.</p>
            </div>

            <div class="filter-bar" style="justify-content: center;">
                <div class="filter-group" style="flex: 0 0 300px; position: relative;">
                    <input type="text" id="publicSearch" class="form-control" placeholder="Cari judul link..." autocomplete="off">
                </div>
            </div>

            <div id="publicGrid" class="grid-view"></div>
            <div id="emptyPublic" style="text-align: center; color: var(--text-light); display: none;">
                <p>Belum ada link publik yang ditampilkan.</p>
            </div>
        </div>
    </section>

    <!-- 2. AUTH SECTION -->
    <section id="authSection" class="hidden">
        <div class="auth-container">
            <div class="auth-card">
                <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary);" id="authTitle">Admin Login</h2>
                <div id="loginMessage" style="margin-bottom: 1rem; padding: 0.75rem; border-radius: 4px; font-size: 0.85rem;" class="hidden"></div>
                <form id="authForm">
                    <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                    <button type="submit" class="btn btn-primary" style="width: 100%">Masuk Dashboard</button>
                </form>
                <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
                    <button type="button" class="btn btn-sm" onclick="switchView('public')">Kembali ke Halaman Publik</button>
                </div>
                <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                    <span id="authText">Belum punya akun?</span> <a href="#" id="authToggle" style="color: var(--primary);">Daftar User</a>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. ADMIN DASHBOARD SECTION -->
    <section id="adminDashboardSection" class="hidden">
        <header>
            <div class="logo">MyLinks <span id="roleBadge" style="font-size: 0.8rem; color: var(--text-light); font-weight: 400; background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">Admin</span></div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="text-align: right;">
                    <div id="userName" style="font-weight: 700;">User</div>
                    <div id="roleText" style="font-size: 0.75rem; color: var(--text-light);">Administrator</div>
                </div>
                <button onclick="switchView('public')" class="btn btn-sm btn-outline">Lihat Web</button>
                <button onclick="logout()" class="btn btn-sm btn-danger">Logout</button>
            </div>
        </header>
        
        <div class="main-layout">
            <aside>
                <div style="padding: 1rem; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-light);">Menu</div>
                <div class="nav-item active" id="navAll" onclick="toggleAdminView('links')">
                    <span>📂 Semua Link</span>
                </div>
                <div id="adminMenuUsers" class="nav-item hidden" onclick="toggleAdminView('users')">
                    <span>👥 Manajemen User</span>
                </div>
                <div class="nav-item" onclick="resetCatForm(); openModal('modalCat')">➕ Tambah Kategori</div>
                
                <div style="padding: 1rem; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-light); margin-top: 1rem;">Kategori</div>
                <div id="catList"></div>
            </aside>
            
            <main>
                <!-- VIEW: LINKS -->
                <div id="linksContent">
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label>Cari Nama</label>
                            <input type="text" id="searchName" class="form-control" placeholder="Ketik judul link..." autocomplete="off">
                        </div>
                        <div class="filter-group"><label>Kategori</label><select id="filterCat" class="form-control"><option value="">Semua Kategori</option></select></div>
                        <div class="filter-group" style="flex: 0 0 130px;"><label>Tgl Mulai</label><input type="date" id="dateStart" class="form-control"></div>
                        <div class="filter-group" style="flex: 0 0 130px;"><label>Tgl Akhir</label><input type="date" id="dateEnd" class="form-control"></div>
                        <div class="filter-group" style="flex: 0 0 auto; display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="applyFilter()">Filter</button>
                            <button class="btn" onclick="resetFilter()">Reset</button>
                        </div>
                    </div>

                    <div class="view-controls">
                        <h2 id="pageTitle">Semua Link</h2>
                        <div style="display: flex; gap: 0.5rem;">
                            <button id="btnBatchDelete" class="btn btn-danger hidden" onclick="batchDelete()">Hapus Terpilih (<span id="selectedCount">0</span>)</button>
                            <div style="border-left: 1px solid var(--border); margin: 0 0.5rem;"></div>
                            <button class="btn" onclick="setView('grid')" id="btnViewGrid">⊞ Grid</button>
                            <button class="btn" onclick="setView('table')" id="btnViewTable">☰ List</button>
                            <button class="btn btn-primary" onclick="openLinkModal()">+ Tambah Link</button>
                        </div>
                    </div>

                    <div id="linksGrid" class="grid-view hidden"></div>
                    <div id="linksTableContainer" class="table-container hidden">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                                    <th style="width: 60px;">Img</th>
                                    <th>Judul & URL</th>
                                    <th>Kategori</th>
                                    <th>Akses</th>
                                    <th>Pemilik</th>
                                    <th>Tanggal</th>
                                    <th style="width: 140px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="linksTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW: USERS (ADMIN ONLY) -->
                <div id="usersContent" class="hidden">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 style="font-size: 1.5rem;">Manajemen Pengguna</h2>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-sm btn-outline" onclick="toggleAdminView('links')">Kembali ke Link</button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                    <th style="width: 200px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </section>
</div>

<!-- MODAL LINK -->
<div id="modalLink" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header" id="linkModalTitle">Tambah Link</div>
        <form id="linkForm">
            <input type="hidden" name="id" id="linkId">
            <div class="form-group"><label>Judul</label><input type="text" name="title" id="linkTitle" class="form-control" required></div>
            <div class="form-group"><label>URL</label><input type="url" name="url" id="linkUrl" class="form-control" required></div>
            <div class="form-group">
                <label>Kategori</label>
                <select name="category_id" id="linkCategory" class="form-control">
                    <option value="">-- Tanpa Kategori --</option>
                </select>
            </div>
            <div class="form-group"><label>Deskripsi</label><textarea name="description" id="linkDesc" class="form-control" rows="3"></textarea></div>
            
            <div class="form-group form-check">
                <input type="checkbox" name="is_public" id="linkIsPublic" style="width: 20px; height: 20px;">
                <label for="linkIsPublic" style="margin:0;">Tampilkan di Halaman Utama (Publik)</label>
            </div>

            <div class="form-group">
                <label>Foto</label>
                <input type="file" name="image" id="linkImage" class="hidden" onchange="previewFile(this)">
                <div id="fileDrop" onclick="document.getElementById('linkImage').click()" style="padding: 1rem; border: 2px dashed var(--border); text-align: center; cursor: pointer; font-size: 0.9rem; color: var(--text-light);">Klik upload (Biarkan kosong jika tidak ingin mengubah)</div>
            </div>
            <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn" onclick="closeModal('modalLink')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SHARE TO USER -->
<div id="modalShare" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header">Bagikan Link ke User Lain</div>
        <div style="margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-light);">
            Pilih user yang diperbolehkan mengakses link ini:
        </div>
        <div style="margin-bottom: 1rem;">
            <input type="text" id="shareSearchUser" class="form-control" placeholder="Cari user...">
            <div id="shareUserList" class="list-scrollable">
                <!-- List User render via JS -->
                <div style="padding:1rem; text-align:center;">Loading users...</div>
            </div>
        </div>
        <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button type="button" class="btn" onclick="closeModal('modalShare')">Tutup</button>
            <button class="btn btn-primary" onclick="saveShares()">Simpan Akses</button>
        </div>
    </div>
</div>

<!-- MODAL CATEGORY -->
<div id="modalCat" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header" id="catModalTitle">Tambah Kategori</div>
        <form id="catForm">
            <input type="hidden" id="catId" name="id">
            <div class="form-group"><label>Nama Kategori</label><input type="text" name="name" id="catName" class="form-control" required></div>
            <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn" onclick="closeModal('modalCat')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    // STATE
    const state = { currentUser: null, role: null, categories: [], links: [], users: [], selectedIds: new Set(), currentShareLinkId: null, viewMode: 'grid', filters: { search: '', catId: '', start: '', end: '' } };

    // API HELPER
    async function api(action, method = 'GET', data = null) {
        const opts = { method };
        let url = `?api=${action}`;
        if (data instanceof FormData) { opts.body = data; } 
        else if (data) { opts.headers = { 'Content-Type': 'application/json' }; opts.body = JSON.stringify(data); }
        try {
            const res = await fetch(url, opts);
            return await res.json();
        } catch (e) { return { status: 'error', message: e.message }; }
    }

    // UTILS
    function showToast(msg, type = 'success') {
        const el = document.createElement('div'); el.className = `toast ${type}`; el.innerText = msg;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }
    function openModal(id) {
        const el = document.getElementById(id);
        if(el) { el.classList.remove('hidden'); el.style.display = 'flex'; }
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if(el) { el.classList.add('hidden'); el.style.display = 'none'; }
    }

    // --- VIEW MANAGEMENT ---
    function switchView(viewName) {
        document.getElementById('publicSection').classList.add('hidden');
        document.getElementById('authSection').classList.add('hidden');
        document.getElementById('adminDashboardSection').classList.add('hidden');

        if (viewName === 'public') {
            document.getElementById('publicSection').classList.remove('hidden');
            loadPublicLinks();
        } else if (viewName === 'auth') {
            document.getElementById('authSection').classList.remove('hidden');
        } else if (viewName === 'admin') {
            document.getElementById('adminDashboardSection').classList.remove('hidden');
            loadDashboard();
        }
    }

    // --- PUBLIC LOGIC ---
    async function loadPublicLinks() {
        const search = document.getElementById('publicSearch').value;
        const res = await api('get_public_links&search=' + search);
        if (res.status === 'success') {
            const container = document.getElementById('publicGrid');
            const emptyMsg = document.getElementById('emptyPublic');
            container.innerHTML = '';
            
            if (res.data.length === 0) {
                emptyMsg.style.display = 'block';
            } else {
                emptyMsg.style.display = 'none';
                res.data.forEach(l => {
                    container.innerHTML += `
                        <div class="card">
                            <img src="${l.image_src}" class="card-img" onerror="this.src='https://via.placeholder.com/300x200?text=No+Image'">
                            <div class="card-body">
                                <div class="card-tag">🏷️ ${l.category_name || 'Umum'}</div>
                                <div class="card-title">${l.title}</div>
                                <div class="card-desc">${l.description}</div>
                                <div class="card-actions">
                                    <a href="${l.url}" target="_blank" class="btn btn-primary">Buka Link</a>
                                </div>
                            </div>
                        </div>`;
                });
            }
        }
    }
    document.getElementById('publicSearch').addEventListener('input', () => loadPublicLinks());

    // --- AUTH LOGIC ---
    let isReg = false;
    document.getElementById('authToggle').onclick = (e) => {
        e.preventDefault();
        isReg = !isReg;
        document.getElementById('authTitle').innerText = isReg ? 'Daftar User Baru' : 'Admin Login';
        document.getElementById('authText').innerText = isReg ? 'Sudah punya akun?' : 'Belum punya akun?';
        document.getElementById('authToggle').innerText = isReg ? 'Login' : 'Daftar';
    };
    document.getElementById('authForm').onsubmit = async (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(e.target));
        const res = await api(isReg ? 'register' : 'login', 'POST', payload);
        if (res.status === 'success') {
            if(!isReg) checkSession();
            else {
                showToast(res.message);
                // Reset form and switch back to login text
                isReg = false;
                document.getElementById('authForm').reset();
                document.getElementById('authTitle').innerText = 'Admin Login';
                document.getElementById('authText').innerText = 'Belum punya akun?';
                document.getElementById('authToggle').innerText = 'Daftar';
            }
        } else {
            const msgBox = document.getElementById('loginMessage');
            msgBox.innerText = res.message;
            msgBox.className = res.status === 'success' ? 'alert success' : 'alert error';
            msgBox.style.backgroundColor = res.status === 'success' ? '#d1fae5' : '#fee2e2';
            msgBox.style.color = res.status === 'success' ? '#065f46' : '#991b1b';
            msgBox.classList.remove('hidden');
        }
    };
    async function checkSession(redirect = false) {
        const res = await api('check_session');
        if (res.logged_in) {
            state.currentUser = res.username;
            state.role = res.role;
            document.getElementById('userName').innerText = res.username;
            document.getElementById('roleText').innerText = res.role === 'admin' ? 'Administrator' : 'User Biasa';
            document.getElementById('roleBadge').innerText = res.role === 'admin' ? 'ADMIN' : 'USER';
            document.getElementById('roleBadge').style.background = res.role === 'admin' ? '#fee2e2' : '#e5e7eb';
            document.getElementById('roleBadge').style.color = res.role === 'admin' ? '#991b1b' : '#374151';
            
            // Tampilkan menu admin jika rolenya admin
            if (res.role === 'admin') {
                document.getElementById('adminMenuUsers').classList.remove('hidden');
            } else {
                document.getElementById('adminMenuUsers').classList.add('hidden');
            }

            switchView('admin');
        } else {
            if (redirect) switchView('auth');
        }
    }
    function logout() { api('logout'); location.reload(); }

    // --- ADMIN DATA & LOGIC ---
    async function loadDashboard() { 
        loadLinks(); 
        loadCategories();
        if(state.role === 'admin') loadUsers(); 
    }

    async function loadCategories() {
        const res = await api('get_categories');
        if (res.status === 'success') {
            state.categories = res.data;
            const list = document.getElementById('catList');
            const select = document.getElementById('filterCat');
            const linkSelect = document.getElementById('linkCategory');
            list.innerHTML = '';
            select.innerHTML = '<option value="">Semua Kategori</option>';
            linkSelect.innerHTML = '<option value="">-- Tanpa Kategori --</option>';
            
            state.categories.forEach(c => {
                const ownerBadge = state.role === 'admin' ? `<span style="font-size:0.7rem; color:#9ca3af;">(${c.username || 'Unknown'})</span>` : '';
                list.innerHTML += `
                    <div class="nav-item" onclick="filterCategory(${c.id}, this)">
                        <span>🏷️ ${c.name} ${ownerBadge}</span>
                        <div class="nav-actions">
                            <button class="action-btn" onclick="editCategory(${c.id}, event)" title="Edit">✏️</button>
                            <button class="action-btn" onclick="deleteCategory(${c.id}, event)" title="Hapus" style="color:var(--danger)">×</button>
                        </div>
                    </div>`;
                select.innerHTML += `<option value="${c.id}">${c.name}</option>`;
                linkSelect.innerHTML += `<option value="${String(c.id)}">${c.name}</option>`;
            });
        }
    }

    async function loadLinks() {
        const { search, catId, start, end } = state.filters;
        let query = `get_links`;
        if (search) query += `&search=${search}`;
        if (catId) query += `&cat_id=${catId}`;
        if (start) query += `&date_start=${start}`;
        if (end) query += `&date_end=${end}`;
        const res = await api(query);
        if (res.status === 'success') { state.links = res.data; render(); }
    }

    // FILTERS & AUTOCOMPLETE
    const adminInput = document.getElementById('searchName');
    let debounceTimer;
    adminInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        const val = e.target.value;
        debounceTimer = setTimeout(async () => {
            const res = await api('search_suggestions&q=' + val);
            // (Optional: Bisa tambahkan UI suggestion disini jika mau)
        }, 300);
    });

    function applyFilter() {
        state.filters.search = document.getElementById('searchName').value;
        state.filters.catId = document.getElementById('filterCat').value;
        state.filters.start = document.getElementById('dateStart').value;
        state.filters.end = document.getElementById('dateEnd').value;
        loadLinks();
    }
    function resetFilter() {
        document.getElementById('searchName').value = '';
        document.getElementById('filterCat').value = '';
        document.getElementById('dateStart').value = '';
        document.getElementById('dateEnd').value = '';
        state.filters = { search: '', catId: '', start: '', end: '' };
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        document.getElementById('navAll').classList.add('active');
        document.getElementById('pageTitle').innerText = 'Semua Link';
        loadLinks();
    }
    function filterCategory(id, el) {
        if(el) { document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active')); el.classList.add('active'); }
        else { document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active')); document.getElementById('navAll').classList.add('active'); }
        state.filters.catId = id;
        document.getElementById('filterCat').value = id || '';
        document.getElementById('pageTitle').innerText = id ? 'Filter Kategori' : 'Semua Link';
        loadLinks();
    }

    // --- VIEW TOGGLING (GRID VS TABLE VS USERS) ---
    function toggleAdminView(view) {
        document.getElementById('linksContent').classList.add('hidden');
        document.getElementById('usersContent').classList.add('hidden');
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));

        if (view === 'users') {
            document.getElementById('usersContent').classList.remove('hidden');
            document.getElementById('adminMenuUsers').classList.add('active');
            loadUsers();
        } else {
            document.getElementById('linksContent').classList.remove('hidden');
            document.getElementById('navAll').classList.add('active');
        }
    }

    // RENDER
    function setView(mode) {
        state.viewMode = mode;
        document.getElementById('btnViewGrid').classList.toggle('btn-primary', mode === 'grid');
        document.getElementById('btnViewTable').classList.toggle('btn-primary', mode === 'table');
        document.getElementById('linksGrid').classList.toggle('hidden', mode !== 'grid');
        document.getElementById('linksTableContainer').classList.toggle('hidden', mode !== 'table');
        document.getElementById('btnBatchDelete').classList.toggle('hidden', mode !== 'table');
        render();
    }
    function render() { renderGrid(); renderTable(); }
    function renderGrid() {
        const container = document.getElementById('linksGrid'); container.innerHTML = '';
        state.links.forEach(l => {
            // Logic Badge: Public > Shared > Private
            let badgeHtml = '';
            if(l.is_public == 1) badgeHtml = `<span class="badge-public">🌐 Publik</span>`;
            else if (l.owner_name && l.owner_name !== state.currentUser) badgeHtml = `<span class="badge-shared">🤝 Shared</span>`;
            else badgeHtml = `<span class="badge-private">🔒 Private</span>`;

            const ownerInfo = state.role === 'admin' ? `<div style="font-size:0.75rem; color:#9ca3af; margin-top:4px;">Oleh: ${l.owner_name || 'Unknown'}</div>` : '';

            container.innerHTML += `
                <div class="card">
                    <img src="${l.image_src}" class="card-img" onerror="this.src='https://via.placeholder.com/300x200?text=No+Image'">
                    <div class="card-body">
                        <div class="card-meta">${badgeHtml} • ${l.category_name || '-'}</div>
                        <div class="card-title">${l.title}</div>
                        ${ownerInfo}
                        <div class="card-actions">
                            <a href="${l.url}" target="_blank" class="btn btn-sm btn-primary">Buka</a>
                            <button class="btn btn-sm" onclick="editLink(${l.id})">Edit</button>
                            <button class="btn btn-sm" onclick="openShareModal(${l.id})">👥 Share</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteLink(${l.id})">Hapus</button>
                        </div>
                    </div>
                </div>`;
        });
    }
    function renderTable() {
        const tbody = document.getElementById('linksTableBody'); tbody.innerHTML = '';
        state.links.forEach(l => {
            const isChecked = state.selectedIds.has(l.id) ? 'checked' : '';
            const rowClass = state.selectedIds.has(l.id) ? 'row-selected' : '';
            
            let badge = l.is_public == 1 ? '🌐 Publik' : (l.owner_name && l.owner_name !== state.currentUser ? '🤝 Shared' : '🔒 Private');
            
            tbody.innerHTML += `
                <tr class="${rowClass}">
                    <td><input type="checkbox" class="row-check" value="${l.id}" ${isChecked} onchange="toggleSelect(${l.id}, this)"></td>
                    <td><img src="${l.image_src}" class="table-img" onerror="this.src='https://via.placeholder.com/40?text=Err'"></td>
                    <td>
                        <div style="font-weight: 600;">${l.title}</div>
                        <div style="font-size: 0.8rem; color: var(--text-light);">${l.url}</div>
                    </td>
                    <td><span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">${l.category_name || '-'}</span></td>
                    <td><span style="font-size: 0.8rem; font-weight: 600; color: ${l.is_public == 1 ? 'var(--primary)' : (l.owner_name !== state.currentUser ? 'var(--warning)' : 'var(--text-light)')}">${badge}</span></td>
                    <td style="font-size: 0.8rem;">${l.owner_name || '-'}</td>
                    <td>${new Date(l.created_at).toLocaleDateString()}</td>
                    <td style="text-align: right;">
                        <a href="${l.url}" target="_blank" class="btn btn-sm btn-primary">Buka</a>
                        <button class="btn btn-sm" onclick="editLink(${l.id})">Edit</button>
                        <button class="btn btn-sm" onclick="openShareModal(${l.id})">Share</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteLink(${l.id})">x</button>
                    </td>
                </tr>`;
        });
        updateMultiSelectUI();
    }
    function toggleSelect(id, checkbox) {
        if (checkbox.checked) state.selectedIds.add(id); else state.selectedIds.delete(id);
        renderTable();
    }
    function toggleSelectAll(master) {
        if (master.checked) state.links.forEach(l => state.selectedIds.add(l.id));
        else state.selectedIds.clear();
        renderTable();
    }
    function updateMultiSelectUI() {
        const count = state.selectedIds.size;
        document.getElementById('selectedCount').innerText = count;
        document.getElementById('btnBatchDelete').classList.toggle('hidden', count === 0);
        document.getElementById('selectAll').checked = (count > 0 && count === state.links.length);
    }
    async function batchDelete() {
        if (state.selectedIds.size === 0) return;
        if (!confirm(`Hapus ${state.selectedIds.size} link terpilih?`)) return;
        const res = await api('batch_delete', 'POST', { ids: Array.from(state.selectedIds) });
        if (res.status === 'success') { state.selectedIds.clear(); showToast('Link dihapus'); loadLinks(); }
    }

    // --- FITUR SHARE KE USER LAIN (MULTI-SELECT) ---
    async function openShareModal(linkId) {
        state.currentShareLinkId = linkId;
        openModal('modalShare');
        // Reset search
        document.getElementById('shareSearchUser').value = '';
        await loadShareUsers(); 
    }

    async function loadShareUsers() {
        // 1. Ambil semua user yang bisa di-share (Kecuali diri sendiri)
        const res = await api('get_share_users');
        if(res.status !== 'success') return;

        // 2. Ambil user yang sudah punya akses ke link ini
        const resShared = await api('get_link_shared_users&link_id=' + state.currentShareLinkId);
        const sharedUserIds = (resShared.status === 'success') ? resShared.data : [];

        const listEl = document.getElementById('shareUserList');
        const inputEl = document.getElementById('shareSearchUser');
        
        const allUsers = res.data;
        const searchVal = inputEl.value.toLowerCase();
        const filteredUsers = allUsers.filter(u => u.username.toLowerCase().includes(searchVal));

        listEl.innerHTML = '';

        if (filteredUsers.length === 0) {
            listEl.innerHTML = '<div style="padding: 1rem; color: var(--text-light); text-align: center;">Tidak ada user ditemukan</div>';
            return;
        }

        filteredUsers.forEach(u => {
            const isShared = sharedUserIds.includes(u.id);
            const roleBadge = u.role === 'admin' ? '<span style="font-size: 0.7rem; color: var(--text-light); border: 1px solid var(--border); padding: 0 4px; border-radius: 4px;">Admin</span>' : '';
            
            const div = document.createElement('div');
            div.className = 'list-item ' + (isShared ? 'share-selected' : '');
            div.innerHTML = `
                <div style="display:flex; align-items: center; gap: 10px; width: 100%;">
                    <input type="checkbox" class="share-check" value="${u.id}" ${isShared ? 'checked' : ''}>
                    <div style="flex: 1;">
                        ${u.username} ${roleBadge}
                    </div>
                </div>
            `;
            // Handle klik row untuk toggle checkbox
            div.onclick = (e) => {
                if (e.target.type !== 'checkbox') {
                    const cb = div.querySelector('.share-check');
                    cb.checked = !cb.checked;
                    toggleShareSelect(cb);
                }
            };
            div.querySelector('.share-check').onchange = (e) => toggleShareSelect(e.target);
            
            listEl.appendChild(div);
        });
    }

    function toggleShareSelect(checkbox) {
        const row = checkbox.closest('.list-item');
        if(checkbox.checked) row.classList.add('share-selected');
        else row.classList.remove('share-selected');
    }

    // Debounce search pada modal share
    const shareSearchInput = document.getElementById('shareSearchUser');
    let shareDebounce;
    shareSearchInput.addEventListener('input', () => {
        clearTimeout(shareDebounce);
        shareDebounce = setTimeout(loadShareUsers, 300);
    });

    async function saveShares() {
        const checkboxes = document.querySelectorAll('.share-check:checked');
        const ids = Array.from(checkboxes).map(c => parseInt(c.value));
        
        // Jika tidak ada yg dipilih, kita bisa delete semua shares untuk link ini
        // Tapi API 'share_link' saat ini hanya INSERT IGNORE.
        // Strategi: Kita hapus semua share yg ada dulu (unshare all), lalu insert yg baru.
        
        // Step 1: Unshare All (Manual)
        // Note: Idealnya buat API batch unshare, tapi untuk simplifikasi kita loop
        // ATAU, kita ubah logika DB menjadi REPLACE (tapi unique keynya composite).
        // Solusi paling aman dengan API yg ada: Kita ambil list shared lama, bandingkan.
        
        // Untuk simplifikasi kode single-file ini:
        // Kita kirim array ID baru. Di backend (PHP), idealnya kita reset dan insert ulang.
        // Karena PHP 'share_link' hanya INSERT IGNORE, user yg tidak dicentang akan tetap ada sharingnya.
        // Mari kita perbaiki logika PHP di 'share_link' agar support reset?
        // Tidak, user minta perbaikan kode utuh. Kita sesuaikan sedikit logika JS:
        
        // Cari ID yg hilang (dicentang sebelumnya, sekarang tidak)
        const resSharedOld = await api('get_link_shared_users&link_id=' + state.currentShareLinkId);
        const oldIds = (resSharedOld.status === 'success') ? resSharedOld.data : [];
        
        // Hapus ID yg tidak ada di list baru
        const toRemove = oldIds.filter(id => !ids.includes(id));
        
        for (const rId of toRemove) {
            await api('unshare_link', 'POST', { link_id: state.currentShareLinkId, target_user_id: rId });
        }

        // Insert ID baru (yang belum ada)
        // Filter ids agar hanya yg benar2 baru (karena INSERT IGNORE)
        // Tapi karena gapake API list detail, kita kirim semua ids saja ke INSERT IGNORE. Biar DB handle duplikat.
        
        if (ids.length > 0) {
            const res = await api('share_link', 'POST', { link_id: state.currentShareLinkId, target_ids: ids });
            if (res.status !== 'success') {
                showToast('Gagal menyimpan akses', 'error');
                return;
            }
        }
        
        showToast('Akses berhasil disimpan');
        closeModal('modalShare');
        loadLinks(); // Refresh badge
    }

    // --- LINK CRUD ---
    function openLinkModal() {
        document.getElementById('linkForm').reset();
        document.getElementById('linkId').value = '';
        document.getElementById('linkIsPublic').checked = false; 
        document.getElementById('linkModalTitle').innerText = 'Tambah Link';
        document.getElementById('fileDrop').innerText = 'Klik upload foto';
        openModal('modalLink');
    }
    
    function editLink(id) {
        const l = state.links.find(x => x.id == id);
        if(!l) { alert('Gagal: Data Link tidak ditemukan'); return; }

        document.getElementById('linkId').value = l.id;
        document.getElementById('linkTitle').value = l.title;
        document.getElementById('linkUrl').value = l.url;
        document.getElementById('linkDesc').value = l.description;
        document.getElementById('linkCategory').value = String(l.category_id || '');
        document.getElementById('linkIsPublic').checked = (l.is_public == 1);
        document.getElementById('linkModalTitle').innerText = 'Edit Link';
        document.getElementById('fileDrop').innerText = 'Klik untuk ganti foto (biarkan kosong jika tidak ingin mengubah)';
        openModal('modalLink');
    }

    document.getElementById('linkForm').onsubmit = async (e) => {
        e.preventDefault();
        const isEdit = !!document.getElementById('linkId').value;
        if (isEdit) {
            if (!confirm("Apakah Anda yakin ingin menyimpan perubahan data link ini?")) return;
        }

        const formData = new FormData(e.target);
        const res = await api(isEdit ? 'update_link' : 'add_link', 'POST', formData);
        if (res.status === 'success') {
            showToast(isEdit ? 'Data berhasil diperbarui' : 'Data berhasil disimpan');
            closeModal('modalLink');
            loadLinks();
        } else {
            showToast(res.message, 'error');
        }
    };

    async function deleteLink(id) {
        if(!confirm("PERINGATAN: Link ini akan dihapus permanen dan file gambar terkait juga akan hilang. Lanjutkan?")) return;
        const res = await api('delete_link', 'POST', { id });
        if(res.status === 'success') { showToast('Dihapus permanen'); loadLinks(); }
    }

    // --- CATEGORY CRUD ---
    function resetCatForm() {
        document.getElementById('catForm').reset();
        document.getElementById('catId').value = '';
        document.getElementById('catModalTitle').innerText = 'Tambah Kategori';
    }

    function editCategory(id, e) {
        if(e) e.stopPropagation();
        const cat = state.categories.find(c => c.id == id);
        if(!cat) { alert('Gagal: Data Kategori tidak ditemukan'); return; }
        document.getElementById('catId').value = cat.id;
        document.getElementById('catName').value = cat.name;
        document.getElementById('catModalTitle').innerText = 'Edit Kategori';
        openModal('modalCat');
    }

    document.getElementById('catForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(e.target));
        const isEdit = !!formData.id;
        
        const res = await api(isEdit ? 'update_category' : 'add_category', 'POST', formData);
        
        if(res.status === 'success') {
            showToast('Kategori disimpan');
            closeModal('modalCat');
            loadCategories();
            loadLinks();
        } else {
            showToast(res.message, 'error');
        }
    };

    async function deleteCategory(id, e) {
        e.stopPropagation();
        if(!confirm("Hapus kategori? Link di dalamnya akan menjadi umum.")) return;
        const res = await api('delete_category', 'POST', { id });
        if(res.status === 'success') { showToast('Kategori dihapus'); loadCategories(); loadLinks(); }
    }

    // --- USER MANAGEMENT ---
    async function loadUsers() {
        const res = await api('get_users');
        if (res.status === 'success') {
            const tbody = document.getElementById('usersTableBody'); tbody.innerHTML = '';
            
            res.data.forEach(u => {
                const isActive = u.is_active == 1;
                const statusBadge = isActive 
                    ? '<span style="background: #d1fae5; color: #047857; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;">Aktif</span>' 
                    : '<span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;">Menunggu Approval</span>';
                
                let actionBtn = '-';
                if (u.role !== 'admin') {
                    actionBtn = isActive 
                        ? `<button class="btn btn-sm btn-outline" onclick="updateUserStatus(${u.id}, 0)">Blokir</button>` 
                        : `<button class="btn btn-sm btn-success" onclick="updateUserStatus(${u.id}, 1)">Setujui</button>`;
                } else {
                    actionBtn = '<span style="font-size:0.8rem; color:gray;">Superadmin</span>';
                }

                tbody.innerHTML += `
                    <tr>
                        <td style="font-weight: 600;">${u.username}</td>
                        <td style="text-transform: capitalize;">${u.role}</td>
                        <td>${statusBadge}</td>
                        <td>${new Date(u.created_at).toLocaleDateString()}</td>
                        <td style="text-align: right;">${actionBtn}</td>
                    </tr>`;
            });
        }
    }

    async function updateUserStatus(id, status) {
        if(!confirm('Ubah status akun ini?')) return;
        const res = await api('update_user_status', 'POST', { id, status });
        if (res.status === 'success') { 
            showToast('Status user diperbarui'); 
            loadUsers(); 
        } else {
            showToast(res.message, 'error');
        }
    }

    // --- INIT ---
    setView('grid');
    switchView('public'); 
</script>
</body>
</html>
