<?php
/**
 * SINGLE FILE PRIVATE LINK WEBSITE (SPA)
 * PHP 7.4+ Native + MySQL 5.7+ (Using MySQLi)
 */
require_once __DIR__ . '/config.php';

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die("Error Database: " . $mysqli->connect_error);
}

function dbQuery($sql, $params = []) {
    global $mysqli;
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) return false;
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function dbFetch($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    if (!$stmt) return null;
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    if (!$stmt) return [];
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function dbFetchColumn($sql, $params = [], $column = 0) {
    $stmt = dbQuery($sql, $params);
    if (!$stmt) return null;
    $result = $stmt->get_result();
    $row = $result->fetch_array();
    return $row ? $row[$column] : null;
}

function dbExec($sql, $params = []) {
    global $mysqli;
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) return false;
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->affected_rows;
}

function dbLastInsertId() {
    global $mysqli;
    return $mysqli->insert_id;
}

$mysqli->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmtColActive = $mysqli->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($stmtColActive->num_rows == 0) { $mysqli->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 0"); }
$stmtColRole = $mysqli->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($stmtColRole->num_rows == 0) { $mysqli->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user'"); }

$mysqli->query("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$stmtCheck = $mysqli->query("SHOW TABLES LIKE 'links'");
$tableExists = ($stmtCheck->num_rows > 0);

if (!$tableExists) {
    $mysqli->query("CREATE TABLE links (
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
    $stmtCol = $mysqli->query("SHOW COLUMNS FROM links LIKE 'is_public'");
    if ($stmtCol->num_rows == 0) $mysqli->query("ALTER TABLE links ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER image_path");
    $stmtPath = $mysqli->query("SHOW COLUMNS FROM links LIKE 'image_path'");
    if ($stmtPath->num_rows == 0) $mysqli->query("ALTER TABLE links ADD COLUMN image_path VARCHAR(255) NULL AFTER description");
}

$mysqli->query("CREATE TABLE IF NOT EXISTS post_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    title VARCHAR(150) NOT NULL,
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
)");

$mysqli->query("CREATE TABLE IF NOT EXISTS link_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    filetype VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
)");

$mysqli->query("CREATE TABLE IF NOT EXISTS link_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_id INT NOT NULL,
    shared_to_user_id INT NOT NULL,
    UNIQUE KEY unique_share (link_id, shared_to_user_id),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_to_user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$adminCheck = dbFetch("SELECT * FROM users WHERE username = 'admin'");
if (!$adminCheck) {
    $defaultPassHash = password_hash('admin123', PASSWORD_DEFAULT);
    $mysqli->query("INSERT INTO users (username, password, role, is_active) VALUES ('admin', '$defaultPassHash', 'admin', 1)");
}

session_start();

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    $response = ['status' => 'error', 'message' => 'Invalid request'];

    try {
        if ($action === 'login') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = dbFetch("SELECT * FROM users WHERE username = ?", [$data['username']]);
            if ($user && password_verify($data['password'], $user['password'])) {
                if ($user['is_active'] == 0) {
                    $response = ['status' => 'error', 'message' => 'Akun belum disetujui Administrator.'];
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $response = ['status' => 'success'];
                }
            } else { $response = ['status' => 'error', 'message' => 'Login gagal.']; }
        }
        elseif ($action === 'register') {
            $data = json_decode(file_get_contents('php://input'), true);
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            dbExec("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'user', 0)", [$data['username'], $hash]);
            $response = ['status' => 'success', 'message' => 'Registrasi berhasil. Menunggu persetujuan Admin.'];
        }
        elseif ($action === 'logout') { session_destroy(); $response = ['status' => 'success']; }
        elseif ($action === 'check_session') {
            $response = ['status' => 'success', 'logged_in' => isset($_SESSION['user_id']), 'username' => $_SESSION['username'] ?? null, 'role' => $_SESSION['role'] ?? null, 'user_id' => $_SESSION['user_id'] ?? null];
        }

        elseif ($action === 'get_public_links') {
            $search = $_GET['search'] ?? '';
            $sql = "SELECT l.*, c.name as category_name FROM links l LEFT JOIN categories c ON l.category_id = c.id WHERE 1=1";
            $params = [];
            $uid = $_SESSION['user_id'] ?? null;
            
            if ($uid) {
                $sql .= " AND (l.is_public = 1 OR l.id IN (SELECT link_id FROM link_shares WHERE shared_to_user_id = ?) OR l.user_id = ?)";
                $params[] = $uid; $params[] = $uid;
            } else {
                $sql .= " AND l.is_public = 1";
            }
            
            if ($search) { $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)"; $term = "%$search%"; $params[] = $term; $params[] = $term; }
            $sql .= " ORDER BY l.id DESC";
            $links = dbFetchAll($sql, $params);
            foreach ($links as &$link) {
                $link['image_src'] = (!empty($link['image_path']) && file_exists($link['image_path'])) ? $link['image_path'] : 'https://picsum.photos/seed/'.$link['id'].'/300/200.jpg';
                if ($uid) {
                    $chk = dbFetch("SELECT id FROM link_shares WHERE link_id = ? AND shared_to_user_id = ?", [$link['id'], $uid]);
                    $link['is_shared'] = $chk ? 1 : 0;
                }
            }
            $response = ['status' => 'success', 'data' => $links];
        }

        elseif ($action === 'get_link_detail') {
            $linkId = $_GET['link_id'];
            $link = dbFetch("SELECT l.*, c.name as category_name, u.username as owner_name FROM links l LEFT JOIN categories c ON l.category_id = c.id LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?", [$linkId]);
            
            if (!$link) throw new Exception("Link tidak ditemukan.");
            $canView = false; $uid = $_SESSION['user_id'] ?? null; $role = $_SESSION['role'] ?? null;
            if ($link['is_public'] == 1) { $canView = true; } elseif ($uid) {
                if ($link['user_id'] == $uid || $role == 'admin') { $canView = true; } else {
                    $chk = dbFetch("SELECT id FROM link_shares WHERE link_id = ? AND shared_to_user_id = ?", [$linkId, $uid]);
                    if ($chk) { $canView = true; $link['is_shared'] = 1; }
                }
            }
            if (!$canView) throw new Exception("Anda tidak memiliki akses.");

            $link['image_src'] = (!empty($link['image_path']) && file_exists($link['image_path'])) ? $link['image_path'] : 'https://picsum.photos/seed/'.$link['id'].'/800/400.jpg';
            $link['attachments'] = dbFetchAll("SELECT * FROM link_attachments WHERE link_id = ? ORDER BY id ASC", [$linkId]);
            $link['extra_links'] = dbFetchAll("SELECT * FROM post_links WHERE link_id = ? ORDER BY id ASC", [$linkId]);
            $response = ['status' => 'success', 'data' => $link];
        }

        elseif (isset($_SESSION['user_id'])) {
            $uid = $_SESSION['user_id'];
            $role = $_SESSION['role'];

            if ($action === 'get_users') {
                if ($role !== 'admin') throw new Exception("Unauthorized");
                $users = dbFetchAll("SELECT id, username, role, is_active, created_at FROM users ORDER BY created_at DESC");
                $response = ['status' => 'success', 'data' => $users];
            }
            elseif ($action === 'toggle_user_status') {
                if ($role !== 'admin') throw new Exception("Unauthorized");
                $data = json_decode(file_get_contents('php://input'), true);
                if ($data['id'] == $uid) throw new Exception("Tidak bisa mengubah status sendiri.");
                dbExec("UPDATE users SET is_active = NOT is_active WHERE id = ?", [$data['id']]);
                $response = ['status' => 'success'];
            }
            elseif ($action === 'add_user' || $action === 'update_user') {
                if ($role !== 'admin') throw new Exception("Unauthorized");
                $data = json_decode(file_get_contents('php://input'), true);
                if(empty($data['username']) || empty($data['password'])) throw new Exception("Username dan Password tidak boleh kosong");
                
                $checkUser = dbFetch("SELECT id FROM users WHERE username = ? AND id != ?", [$data['username'], $data['id'] ?? 0]);
                if ($checkUser) throw new Exception("Username sudah digunakan");
                
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $userRole = $data['role'] ?? 'user';
                
                if ($action === 'add_user') {
                    dbExec("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, 1)", [$data['username'], $hash, $userRole]);
                    $response = ['status' => 'success', 'message' => 'User berhasil ditambahkan'];
                } else {
                    if ($data['id'] == $uid) throw new Exception("Tidak bisa mengubah diri sendiri");
                    dbExec("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?", [$data['username'], $hash, $userRole, $data['id']]);
                    $response = ['status' => 'success', 'message' => 'User berhasil diperbarui'];
                }
            }
            elseif ($action === 'delete_user') {
                if ($role !== 'admin') throw new Exception("Unauthorized");
                $data = json_decode(file_get_contents('php://input'), true);
                if ($data['id'] == $uid) throw new Exception("Tidak bisa menghapus diri sendiri");
                dbExec("DELETE FROM users WHERE id = ?", [$data['id']]);
                $response = ['status' => 'success', 'message' => 'User berhasil dihapus'];
            }

            elseif ($action === 'get_share_users') {
                $users = dbFetchAll("SELECT id, username FROM users WHERE id != ? ORDER BY username ASC", [$uid]);
                $response = ['status' => 'success', 'data' => $users];
            }
            elseif ($action === 'get_link_shared_users') {
                $linkId = $_GET['link_id'];
                $stmt = dbQuery("SELECT shared_to_user_id FROM link_shares WHERE link_id = ?", [$linkId]);
                $result = $stmt->get_result();
                $sharedIds = [];
                while ($row = $result->fetch_assoc()) {
                    $sharedIds[] = $row['shared_to_user_id'];
                }
                $response = ['status' => 'success', 'data' => $sharedIds];
            }
            elseif ($action === 'sync_link_shares') {
                $data = json_decode(file_get_contents('php://input'), true);
                $linkId = $data['link_id'];
                $targetIds = $data['target_ids'];
                $owner = dbFetchColumn("SELECT user_id FROM links WHERE id=?", [$linkId]);
                if($owner != $uid && $role !== 'admin') throw new Exception("Unauthorized");
                dbExec("DELETE FROM link_shares WHERE link_id = ?", [$linkId]);
                if (!empty($targetIds)) {
                    $sql = "INSERT IGNORE INTO link_shares (link_id, shared_to_user_id) VALUES ";
                    $vals = []; $par = [];
                    foreach($targetIds as $tid) { $vals[] = "(?,?)"; $par[] = $linkId; $par[] = $tid; }
                    dbExec($sql . implode(',', $vals), $par);
                }
                $response = ['status' => 'success'];
            }

            if ($action === 'get_categories') {
                if ($role === 'admin') {
                    $cats = dbFetchAll("SELECT c.*, u.username as owner_name FROM categories c JOIN users u ON c.user_id = u.id ORDER BY c.name ASC");
                } else { 
                    $cats = dbFetchAll("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC", [$uid]); 
                }
                $response = ['status' => 'success', 'data' => $cats];
            }
            elseif ($action === 'add_category') {
                $data = json_decode(file_get_contents('php://input'), true);
                if(empty($data['name'])) throw new Exception("Nama kategori tidak boleh kosong");
                dbExec("INSERT INTO categories (user_id, name) VALUES (?, ?)", [$uid, $data['name']]);
                $response = ['status' => 'success', 'message' => 'Kategori berhasil ditambahkan'];
            }
            elseif ($action === 'update_category') {
                $data = json_decode(file_get_contents('php://input'), true);
                if ($role !== 'admin') { 
                    $c = dbFetchColumn("SELECT user_id FROM categories WHERE id=?", [$data['id']]); 
                    if($c != $uid) throw new Exception("Unauthorized"); 
                }
                if(empty($data['name'])) throw new Exception("Nama kategori tidak boleh kosong");
                dbExec("UPDATE categories SET name = ? WHERE id = ?", [$data['name'], $data['id']]);
                $response = ['status' => 'success', 'message' => 'Kategori berhasil diperbarui'];
            }
            elseif ($action === 'delete_category') {
                $data = json_decode(file_get_contents('php://input'), true);
                if ($role === 'admin') {
                    dbExec("DELETE FROM categories WHERE id = ?", [$data['id']]);
                } else {
                    dbExec("DELETE FROM categories WHERE id = ? AND user_id = ?", [$data['id'], $uid]);
                }
                $response = ['status' => 'success'];
            }

            elseif ($action === 'get_links') {
                $search = $_GET['search'] ?? ''; $catId = $_GET['cat_id'] ?? null;
                $sql = "SELECT l.*, c.name as category_name, u.username as owner_name FROM links l LEFT JOIN categories c ON l.category_id = c.id LEFT JOIN users u ON l.user_id = u.id WHERE 1=1";
                $params = [];
                if ($role !== 'admin') {
                    $sql .= " AND (l.user_id = ? OR l.is_public = 1 OR l.id IN (SELECT link_id FROM link_shares WHERE shared_to_user_id = ?))";
                    $params[] = $uid; $params[] = $uid;
                }
                if ($search) { $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)"; $term = "%$search%"; $params[] = $term; $params[] = $term; }
                if ($catId) { $sql .= " AND l.category_id = ?"; $params[] = $catId; }
                $sql .= " ORDER BY l.id DESC";
                
                $links = dbFetchAll($sql, $params);
                
                foreach ($links as &$l) {
                    $l['image_src'] = (!empty($l['image_path']) && file_exists($l['image_path'])) ? $l['image_path'] : 'https://picsum.photos/seed/'.$l['id'].'/300/200.jpg';
                    $l['attachment_count'] = dbFetchColumn("SELECT COUNT(*) FROM link_attachments WHERE link_id=?", [$l['id']]);
                    $l['link_count'] = dbFetchColumn("SELECT COUNT(*) FROM post_links WHERE link_id=?", [$l['id']]);
                    if ($l['user_id'] != $uid) {
                        $chk = dbFetch("SELECT id FROM link_shares WHERE link_id = ? AND shared_to_user_id = ?", [$l['id'], $uid]);
                        $l['is_shared'] = $chk ? 1 : 0;
                    }
                }
                $response = ['status' => 'success', 'data' => $links];
            }
            
            elseif ($action === 'delete_attachment') {
                $data = json_decode(file_get_contents('php://input'), true);
                $attId = $data['id'];
                $att = dbFetch("SELECT la.filepath FROM link_attachments la JOIN links l ON la.link_id = l.id WHERE la.id = ?", [$attId]);
                if($att) {
                    if($role !== 'admin') {
                         $chkOwner = dbFetchColumn("SELECT user_id FROM links WHERE id=(SELECT link_id FROM link_attachments WHERE id=?)", [$attId]);
                         if($chkOwner != $uid) throw new Exception("Unauthorized");
                    }
                    if(file_exists($att['filepath'])) unlink($att['filepath']);
                    dbExec("DELETE FROM link_attachments WHERE id = ?", [$attId]);
                    $response = ['status' => 'success'];
                } else { throw new Exception("Attachment not found"); }
            }

            elseif ($action === 'add_link' || $action === 'update_link') {
                $title = $_POST['title'] ?? ''; $url = $_POST['url'] ?? '';
                $desc = $_POST['description'] ?? '';
                $catId = (!empty($_POST['category_id']) && $_POST['category_id'] !== "") ? $_POST['category_id'] : null;
                $isPublic = isset($_POST['is_public']) ?1 : 0;
                
                $hasThumb = isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK;
                $thumbPath = null;
                if ($hasThumb) {
                    $uploadDir = 'uploads/thumbs'; if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fileName = time() . '_' . basename($_FILES['thumbnail']['name']);
                    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . '/' . $fileName)) $thumbPath = $uploadDir . '/' . $fileName;
                }

                $linkId = $_POST['id'] ?? 0;
                if ($action === 'add_link') {
                    dbExec("INSERT INTO links (user_id, category_id, title, url, description, image_path, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)", [$uid, $catId, $title, $url, $desc, $thumbPath, $isPublic]);
                    $linkId = dbLastInsertId();
                } else {
                    if ($thumbPath) {
                        $oldPath = dbFetchColumn("SELECT image_path FROM links WHERE id=?", [$linkId]);
                        if($oldPath && file_exists($oldPath)) unlink($oldPath);
                        dbExec("UPDATE links SET category_id=?, title=?, url=?, description=?, image_path=?, is_public=? WHERE id=?", [$catId, $title, $url, $desc, $thumbPath, $isPublic, $linkId]);
                    } else {
                        dbExec("UPDATE links SET category_id=?, title=?, url=?, description=?, is_public=? WHERE id=?", [$catId, $title, $url, $desc, $isPublic, $linkId]);
                    }
                }

                if (!empty($_POST['extra_links'])) {
                    $extraLinks = json_decode($_POST['extra_links'], true);
                    if (is_array($extraLinks)) {
                        dbExec("DELETE FROM post_links WHERE link_id = ?", [$linkId]);
                        $sqlIns = "INSERT INTO post_links (link_id, url, title) VALUES ";
                        $vals = []; $par = [];
                        foreach($extraLinks as $el) {
                            if(!empty($el['url'])) {
                                $vals[] = "(?,?,?)";
                                $par[] = $linkId; $par[] = $el['url']; $par[] = $el['title'] ?: 'Link Lain';
                            }
                        }
                        if(!empty($vals)) { dbExec($sqlIns . implode(',', $vals), $par); }
                    }
                } else { dbExec("DELETE FROM post_links WHERE link_id = ?", [$linkId]); }

                if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                    $fileDir = "uploads/files/$linkId";
                    if (!file_exists($fileDir)) mkdir($fileDir, 0777, true);
                    
                    foreach ($_FILES['attachments']['name'] as $key => $name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $tmp = $_FILES['attachments']['tmp_name'][$key];
                            $safeName = time() . '_' . basename($name);
                            $target = $fileDir . '/' . $safeName;
                            if (move_uploaded_file($tmp, $target)) {
                                $fType = mime_content_type($target);
                                dbExec("INSERT INTO link_attachments (link_id, filename, filepath, filetype) VALUES (?, ?, ?, ?)", [$linkId, $name, $target, $fType]);
                            }
                        }
                    }
                }

                if (!empty($_POST['share_users'])) {
                    $shareUsers = json_decode($_POST['share_users'], true);
                    if (is_array($shareUsers)) {
                        dbExec("DELETE FROM link_shares WHERE link_id = ?", [$linkId]);
                        $sqlIns = "INSERT IGNORE INTO link_shares (link_id, shared_to_user_id) VALUES ";
                        $vals = []; $par = [];
                        foreach($shareUsers as $uid) {
                            if(!empty($uid)) {
                                $vals[] = "(?,?)";
                                $par[] = $linkId; $par[] = $uid;
                            }
                        }
                        if(!empty($vals)) { dbExec($sqlIns . implode(',', $vals), $par); }
                    }
                } else {
                    if ($action === 'add_link') {
                        dbExec("DELETE FROM link_shares WHERE link_id = ?", [$linkId]);
                    }
                }

                $response = ['status' => 'success', 'link_id' => $linkId];
            }

            elseif ($action === 'batch_delete') {
                $data = json_decode(file_get_contents('php://input'), true);
                $ids = $data['ids'] ?? [];
                if (!empty($ids)) {
                    foreach($ids as $id) {
                        $l = dbFetch("SELECT image_path FROM links WHERE id=?", [$id]);
                        if($l && $l['image_path'] && file_exists($l['image_path'])) unlink($l['image_path']);
                        $dir = "uploads/files/$id"; if(is_dir($dir)) { array_map('unlink', glob("$dir/*")); rmdir($dir); }
                    }
                    $in = str_repeat('?,', count($ids) - 1) . '?';
                    dbExec("DELETE FROM link_shares WHERE link_id IN ($in)", $ids);
                    $sql = "DELETE FROM links WHERE id IN ($in)";
                    $params = $ids;
                    if ($role !== 'admin') { $sql .= " AND user_id = ?"; $params[] = $uid; }
                    dbExec($sql, $params);
                }
                $response = ['status' => 'success'];
            }
            elseif ($action === 'delete_link') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'];
                $l = dbFetch("SELECT user_id, image_path FROM links WHERE id=?", [$id]);
                if(!$l) throw new Exception("Link not found");
                if($l['user_id'] != $uid && $role !== 'admin') throw new Exception("Unauthorized");
                
                if($l['image_path'] && file_exists($l['image_path'])) unlink($l['image_path']);
                $dir = "uploads/files/$id"; if(is_dir($dir)) { array_map('unlink', glob("$dir/*")); rmdir($dir); }
                
                dbExec("DELETE FROM link_shares WHERE link_id=?", [$id]);
                dbExec("DELETE FROM links WHERE id=?", [$id]);
                $response = ['status' => 'success'];
            }
        }
    } catch (Exception $e) { $response = ['status' => 'error', 'message' => $e->getMessage()]; }
    echo json_encode($response); exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Links - Manager</title>
    <script>
        window.CKEDITOR_DISABLE_SECURITY_WARNINGS = true;
    </script>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.querySelectorAll('[class*="cke_notification"]').forEach(function(el){ el.style.display = 'none'; });
            }, 500);
        });
    </script>
    <script src="ckeditor/ckeditor.js"></script>
    <style>
        :root {
            --bg: #f8fafc; 
            --surface: #ffffff; 
            --surface-variant: #f1f5f9; 
            --text: #0f172a; 
            --text-light: #64748b; 
            --border: #e2e8f0; 
            --primary: #3b82f6; 
            --primary-dark: #2563eb; 
            --primary-glow: rgba(59, 130, 246, 0.5); 
            --danger: #ef4444; 
            --success: #10b981; 
            --glass: rgba(255, 255, 255, 0.9); 
            --modal-bg: rgba(255, 255, 255, 0.95); 
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --bg: #0f172a; 
            --surface: #1e293b; 
            --surface-variant: #334155; 
            --text: #f1f5f9; 
            --text-light: #94a3b8; 
            --border: #334155; 
            --primary: #8b5cf6; 
            --primary-dark: #7c3aed; 
            --primary-glow: rgba(139, 92, 246, 0.5); 
            --danger: #f87171; 
            --success: #34d399; 
            --glass: rgba(30, 41, 59, 0.9); 
            --modal-bg: rgba(15, 23, 42, 0.95); 
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }
        .hidden { display: none !important; }
        
        header { background: var(--glass); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); padding: 0.75rem 2rem; position: sticky; top: 0; z-index: 50; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow); }
        .logo { font-weight: 800; font-size: 1.5rem; color: var(--primary); cursor: pointer; display: flex; align-items: center; gap: 10px; text-shadow: 0 0 10px var(--primary-glow); }
        .main-layout { display: flex; flex: 1; height: calc(100vh - 70px); overflow: hidden; position: relative; }
        aside { width: 280px; background: var(--glass); border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; flex-shrink: 0; backdrop-filter: blur(5px); transition: margin-left 0.3s ease; margin-left: 0; }
        main { flex: 1; overflow-y: auto; padding: 2rem; position: relative; transition: margin-left 0.3s ease; margin-left: 0; }
        
        .btn { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); cursor: pointer; font-size: 0.9rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 500; }
        .btn:hover { background: var(--surface-variant); transform: translateY(-1px); }
        .btn-primary { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 0 15px var(--primary-glow); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--danger); color: white; border-color: var(--danger); }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
        
        .grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: 0.3s; display: flex; flex-direction: column; cursor: pointer; position: relative; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), transparent); opacity: 0; transition: 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-color: var(--primary); }
        .card:hover::before { opacity: 1; }
        .card-img { width: 100%; height: 180px; object-fit: cover; background: var(--surface-variant); pointer-events: none; }
        .card-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .card-title { font-weight: 700; font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--text); }
        .card-meta { font-size: 0.8rem; color: var(--text-light); display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-public { background: rgba(59, 130, 246, 0.15); color: var(--primary); border: 1px solid rgba(59, 130, 246, 0.2); }
        .badge-private { background: rgba(148, 163, 184, 0.15); color: var(--text-light); border: 1px solid rgba(148, 163, 184, 0.2); }
        .badge-shared { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-file { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); margin-left: 4px; }

        .detail-header { margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .detail-title { font-size: 2.5rem; font-weight: 800; color: var(--text); margin-bottom: 0.5rem; background: -webkit-linear-gradient(45deg, var(--text), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .detail-content { font-size: 1.1rem; line-height: 1.8; color: var(--text); max-width: 900px; margin: 0 auto 3rem auto; }
        .file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .file-item { border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; text-align: center; position: relative; background: var(--surface); transition: 0.2s; }
        .file-item:hover { border-color: var(--primary); transform: scale(1.02); }
        .file-preview { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem; background: var(--surface-variant); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--text-light); }
        .file-name { font-size: 0.9rem; word-break: break-all; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: var(--text); }
        .file-del-btn { position: absolute; top: 10px; right: 10px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; display: none; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .file-item:hover .file-del-btn { display: flex; }

        .filter-bar { background: var(--surface); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: end; border: 1px solid var(--border); }
        .form-control { padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; width: 100%; background: var(--surface); color: var(--text); transition: 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; display: flex; justify-content: center; align-items: center; backdrop-filter: blur(4px); opacity: 0; animation: fadeIn 0.2s forwards; }
        .modal { background: var(--modal-bg); width: 90%; max-width: 800px; max-height: 90vh; border-radius: 16px; padding: 2.5rem; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid var(--border); transform: scale(0.95); animation: slideUp 0.3s forwards; }
        
        .nav-section { padding: 1rem 1.5rem 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-light); letter-spacing: 1px; }
        .nav-item { padding: 0.75rem 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; margin: 0 0.5rem; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        
        .list-scrollable { max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
        .list-item { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); cursor: pointer; color: var(--text); }
        .list-item:hover { background: var(--surface-variant); }
        .list-item:last-child { border-bottom: none; }
        .extra-link-row { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; animation: slideIn 0.2s; }
        .extra-links-list { display: flex; flex-wrap: wrap; gap: 0.75rem; }

        @keyframes fadeIn { to { opacity: 1; } }
        @keyframes slideUp { to { transform: scale(1); } }
        @keyframes slideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        
        .cke_notification { display: none !important; }
    </style>
</head>
<body>

<div id="app">
    <div id="toastContainer"></div>

    <header>
        <div class="logo" onclick="location.reload()">
            <span style="font-size:1.8rem;">🔗</span> E-Link
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <button class="btn btn-sm" onclick="toggleSidebar()" id="toggleSidebarBtn" title="Toggle Menu">☰ Menu</button>
            <div id="headerAuth"></div>
        </div>
    </header>

    <section id="publicSection" style="padding: 3rem 2rem; max-width: 1300px; margin: 0 auto; width: 100%;">
        <div style="text-align: center; margin-bottom: 4rem;">
            <h1 style="font-size: 3rem; margin-bottom: 0.5rem; font-weight: 800; color: var(--text);">E-Link Pak Manto</h1>
            <p style="color: var(--text-light); font-size: 1.1rem;">Media Menumpang Catatan - Menampung Ingatan </p>
        </div>
        <div style="display:flex; justify-content:center; margin-bottom: 3rem;">
            <input type="text" id="publicSearch" class="form-control" style="max-width: 500px; padding: 1rem;" placeholder="Cari link...">
        </div>
        <div id="publicGrid" class="grid-view"></div>
    </section>

    <section id="authSection" class="hidden" style="height: 90vh; display: flex; align-items: center; justify-content: center; background: var(--bg);">
        <div style="background: var(--surface); padding: 3rem; border-radius: 16px; width: 100%; max-width: 420px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid var(--border);">
            <h2 id="authTitle" style="text-align: center; margin-bottom: 2rem; color: var(--primary); font-weight: 800;">Login</h2>
            <form id="authForm">
                <div style="margin-bottom: 1.5rem;"><label style="display:block; margin-bottom:0.5rem; font-weight:600;">Username</label><input type="text" name="username" class="form-control" required></div>
                <div style="margin-bottom: 2rem;"><label style="display:block; margin-bottom:0.5rem; font-weight:600;">Password</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">Masuk Dashboard</button>
            </form>
            <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem;">
                <span id="authText" style="color: var(--text-light);">Belum punya akun?</span> <a href="#" id="authToggle" style="color: var(--primary); font-weight: 700;">Daftar</a>
            </div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="#" onclick="switchView('public')" style="color: var(--text-light); font-size: 0.85rem; text-decoration: none;">Batal / Kembali</a>
            </div>
        </div>
    </section>

    <section id="adminSection" class="hidden main-layout">
        <aside id="sidebar">
            <div class="nav-section">Menu Utama</div>
            <div style="padding: 0.5rem 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <button class="btn btn-primary" style="width: 100%; justify-content: center; box-shadow: 0 4px 15px rgba(59, 130,246,0.3);" onclick="openLinkModal()">+ Tambah Link</button>
                <button class="btn" style="width: 100%; justify-content: center; background: var(--surface-variant);" onclick="openCatModal(false)">+ Tambah Kategori</button>
            </div>
            <div class="nav-item active" id="navAll" onclick="filterCategory(null)">📂 Semua Link</div>
            <div class="nav-item hidden" id="navUsers" onclick="toggleView('users')">👥 User Management</div>
            
            <div class="nav-section">Kategori</div>
            <div id="catList"></div>
        </aside>
        
        <main id="mainContainer" style="max-width: 1300px; margin: 0 auto;">
            <div id="linksListContent">
                <div class="filter-bar">
                    <div style="flex: 2;"><input type="text" id="searchName" class="form-control" placeholder="Cari judul..."></div>
                    <div style="flex: 1;"><select id="filterCat" class="form-control"><option value="">Semua Kategori</option></select></div>
                    <div><button class="btn btn-primary" onclick="applyFilter()">Filter</button></div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 id="pageTitle" style="font-size: 1.5rem; color: var(--text);">Semua Link</h2>
                    <div>
                         <button class="btn btn-sm" onclick="setView('grid')" id="btnViewGrid">⊞</button>
                         <button class="btn btn-sm" onclick="setView('table')" id="btnViewTable">☰</button>
                    </div>
                </div>

                <div id="gridContainer" class="grid-view hidden"></div>
                <div id="tableContainer" class="hidden" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead><tr style="background: var(--surface-variant); text-align: left;"><th style="padding: 1rem;">Judul</th><th style="padding: 1rem;">Akses</th><th style="padding: 1rem;">Files</th><th style="padding: 1rem;">Aksi</th></tr></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>

            <div id="linkDetailContent" class="hidden" style="max-width: 900px; margin: 0 auto;">
                <button class="btn" onclick="closeDetailView()" style="margin-bottom: 1.5rem;">← Kembali ke List</button>
                
                <div style="margin-top: 1.5rem;">
                    <div id="detailActions" style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap; margin-bottom: 2rem;"></div>

                    <div class="detail-header">
                        <div class="card-meta">
                            <span id="detailCategory" style="font-weight: 700; color: var(--primary);">Kategori</span>
                            <span id="detailDate" style="color: var(--text-light);">Date</span>
                        </div>
                        <div class="detail-title" id="detailTitle">Judul Link</div>
                        <div id="detailDesc" class="detail-content"></div>
                    </div>

                    <div id="detailExtraLinksSection" class="hidden">
                        <h3 style="margin-bottom: 1rem; color: var(--primary); font-size: 1.2rem;">🔗 Daftar Link Lain</h3>
                        <div id="detailExtraLinksList" class="extra-links-list"></div>
                    </div>

                    <div id="detailFilesSection" class="hidden">
                        <h3 style="margin-bottom: 1rem; color: var(--primary); font-size: 1.2rem;">📂 Download Dokumen</h3>
                        <div id="detailFileGrid" class="file-grid"></div>
                    </div>

                    <div style="margin-top: 3rem; border-top: 1px solid var(--border); padding-top: 1.5rem; display: flex; gap: 1rem;">
                        <button id="btnEditLink" class="btn hidden" onclick="editCurrentLink()">✏️ Edit Postingan</button>
                        <button id="btnShareLink" class="btn hidden" onclick="openShareModal(currentLinkId)">👥 Bagikan</button>
                        <button id="btnDeleteLink" class="btn btn-danger hidden" onclick="deleteCurrentLink()">🗑️ Hapus</button>
                    </div>
                </div>
            </div>

            <div id="usersContent" class="hidden">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                    <h2 style="font-size: 1.5rem;">Manajemen User</h2>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn btn-primary" onclick="openUserModal()">+ Tambah User</button>
                        <button class="btn" onclick="toggleView('links')">Kembali ke Link</button>
                    </div>
                </div>
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead><tr style="background: var(--surface-variant); text-align: left;"><th style="padding: 1rem;">User</th><th style="padding: 1rem;">Role</th><th style="padding: 1rem;">Status</th><th style="padding: 1rem;">Aksi</th></tr></thead>
                        <tbody id="usersTableBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </section>
</div>

<div id="modalLink" class="modal-overlay hidden">
    <div class="modal">
        <h2 id="modalTitle" style="margin-bottom: 2rem; color: var(--primary);">Tambah Link</h2>
        <form id="linkForm">
            <input type="hidden" name="id" id="inpId">
            <input type="hidden" name="extra_links" id="inpExtraLinks">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div style="grid-column: span 2;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Judul</label>
                    <input type="text" name="title" id="inpTitle" class="form-control" required>
                </div>
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">URL Utama</label>
                    <input type="url" name="url" id="inpUrl" class="form-control" placeholder="https://...">
                </div>
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Kategori</label>
                    <div style="display:flex; gap:0.5rem;">
                        <select name="category_id" id="inpCat" class="form-control"><option value="">Tanpa Kategori</option></select>
                        <button type="button" class="btn btn-sm" onclick="openCatModal(false)">+ Tambah</button>
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">🔗 Link Tambahan</label>
                <div style="border: 1px solid var(--border); padding: 1rem; border-radius: 8px; background: var(--surface);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:1rem;">
                        <span style="color:var(--text-light); font-size:0.9rem;">Tambahkan referensi lain</span>
                        <button type="button" class="btn btn-sm" onclick="addExtraLinkRow()">+ Tambah</button>
                    </div>
                    <div id="extraLinksContainer"></div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Isi Catatan (Rich Text)</label>
                <textarea name="description" id="editor1" rows="10" cols="80"></textarea>
            </div>

            <div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Thumbnail</label>
                    <input type="file" name="thumbnail" id="inpThumb" class="form-control" accept="image/*">
                </div>
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Lampiran Dokumen</label>
                    <input type="file" name="attachments[]" id="inpFiles" class="form-control" multiple>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <label class="form-check" style="display:flex; gap:0.5rem; align-items:center; cursor:pointer;">
                    <input type="checkbox" name="is_public" id="inpPublic" style="width:18px; height:18px; accent-color: var(--primary);"> 
                    <span style="font-weight:500;">Tampilkan sebagai Publik</span>
                </label>
            </div>

            <div style="margin-top: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">👥 Bagikan ke User (Private)</label>
                <input type="hidden" name="share_users" id="inpShareUsers">
                <div id="shareUsersList" class="list-scrollable" style="max-height: 150px;"></div>
                <p style="font-size:0.8rem; color:var(--text-light); margin-top:0.5rem;">Pilih user yang boleh melihat link ini (tidak perlu dipilih jika Public)</p>
            </div>

            <div style="margin-top: 2.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="closeModal('modalLink')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="modalCat" class="modal-overlay hidden">
    <div class="modal" style="max-width: 400px;">
        <h2 id="catModalTitle" style="margin-bottom: 1.5rem; color: var(--primary);">Kategori</h2>
        <form id="catForm">
            <input type="hidden" id="catId" name="id">
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Nama Kategori</label>
                <input type="text" id="catName" name="name" class="form-control" required placeholder="Contoh: Keuangan">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="closeModal('modalCat')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="modalShare" class="modal-overlay hidden">
    <div class="modal" style="max-width: 500px;">
        <h2>Bagikan ke User</h2>
        <p style="color: var(--text-light); margin-bottom: 1.5rem;">Pilih user yang boleh melihat link ini.</p>
        <div id="shareUserList" class="list-scrollable"></div>
        <div style="margin-top: 2rem; text-align: right;">
            <button class="btn" onclick="closeModal('modalShare')">Batal</button>
            <button class="btn btn-primary" onclick="saveShares()">Simpan</button>
        </div>
    </div>
</div>

<div id="modalUser" class="modal-overlay hidden">
    <div class="modal" style="max-width: 400px;">
        <h2 id="userModalTitle" style="margin-bottom: 1.5rem; color: var(--primary);">Tambah User</h2>
        <form id="userForm">
            <input type="hidden" id="userId" name="id">
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Username</label>
                <input type="text" id="userName" name="username" class="form-control" required placeholder="Username login">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Password</label>
                <input type="password" id="userPass" name="password" class="form-control" required placeholder="Password login">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Role</label>
                <select id="userRole" name="role" class="form-control">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="closeModal('modalUser')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const state = { user: null, role: null, userId: null, links: [], cats: [], users: [], selectedIds: new Set(), currentLinkId: null, viewMode: 'grid', detailLink: null };
    const getSessionId = () => sessionStorage.getItem('uid');

    function initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
    }

    function toggleTheme() {
        const current = document.body.getAttribute('data-theme');
        const newTheme = current === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    }

    function updateThemeIcon(theme) {
        const btn = document.getElementById('themeToggle');
        if(btn) btn.innerText = theme === 'dark' ? '☀️' : '🌙';
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('mainContainer');
        const isHidden = sidebar.classList.contains('hidden');
        
        if (isHidden) {
            sidebar.classList.remove('hidden');
            main.style.marginLeft = '0';
            main.style.maxWidth = '1300px';
            main.style.marginRight = 'auto';
            main.style.marginLeft = 'auto';
            localStorage.setItem('sidebar', 'show');
        } else {
            sidebar.classList.add('hidden');
            main.style.marginLeft = 'auto';
            main.style.marginRight = 'auto';
            main.style.maxWidth = '100%';
            localStorage.setItem('sidebar', 'hide');
        }
    }

    function initSidebar() {
        const sidebarState = localStorage.getItem('sidebar');
        const main = document.getElementById('mainContainer');
        if (sidebarState === 'hide') {
            document.getElementById('sidebar').classList.add('hidden');
            main.style.marginLeft = 'auto';
            main.style.marginRight = 'auto';
            main.style.maxWidth = '100%';
        } else {
            main.style.marginLeft = '0';
            main.style.maxWidth = '1300px';
        }
    }

    async function api(action, method='GET', data=null) {
        const opts = { method };
        let url = `?api=${action}`;
        if (data instanceof FormData) { opts.body = data; }
        else if (data) { opts.headers = {'Content-Type':'application/json'}; opts.body = JSON.stringify(data); }
        try {
            const res = await fetch(url, opts);
            return await res.json();
        } catch(e) { return {status:'error', message:e.message}; }
    }

    function showToast(msg, type='success') {
        const el = document.createElement('div');
        el.style.cssText = `position:fixed; bottom:30px; right:30px; background:${type==='success'?'var(--success)':'var(--danger)'}; color:white; padding:15px 25px; border-radius:12px; z-index:200; animation: fadeIn 0.3s; box-shadow:0 10px 15px -3px rgba(0,0,0,0.2); font-weight:500;`;
        el.innerText = msg; document.body.appendChild(el);
        setTimeout(()=>el.remove(), 3000);
    }

    let isReg = false;
    document.getElementById('authToggle').onclick = (e) => {
        e.preventDefault(); isReg = !isReg;
        document.getElementById('authTitle').innerText = isReg ? 'Daftar User' : 'Login';
        document.getElementById('authText').innerText = isReg ? 'Sudah punya akun?' : 'Belum punya akun?';
        document.getElementById('authToggle').innerText = isReg ? 'Login disini' : 'Daftar';
    };
    document.getElementById('authForm').onsubmit = async (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(e.target));
        const res = await api(isReg ? 'register' : 'login', 'POST', payload);
        if(res.status === 'success') {
            if(!isReg) checkSession();
            else { showToast('Daftar berhasil, tunggu approval'); isReg=false; document.getElementById('authToggle').click(); }
        } else { alert(res.message); }
    };

    function renderHeader() {
        const container = document.getElementById('headerAuth');
        const themeBtn = `<button class="btn btn-sm" onclick="toggleTheme()" id="themeToggle" title="Ganti Tema">🌙</button>`;
        
        if (state.user) {
            const adminBtn = state.role === 'admin' ? `<button class="btn btn-sm" onclick="toggleView('users')">👥 User</button>` : '';
            container.innerHTML = `
                <div style="display:flex; align-items:center; gap:12px;">
                    ${themeBtn}
                    <span style="font-weight:700; color:var(--text);">👤 ${state.user}</span>
                    ${adminBtn}
                    <button class="btn btn-sm btn-outline" onclick="switchView('admin')">Dashboard</button>
                    <button class="btn btn-sm btn-outline" onclick="switchView('public')">Web</button>
                    <button class="btn btn-sm btn-danger" onclick="logout()">Logout</button>
                </div>`;
        } else {
            container.innerHTML = `
                <div style="display:flex; gap:10px;">
                    ${themeBtn}
                    <button class="btn btn-sm btn-primary" onclick="showLogin()">Login</button>
                    <button class="btn btn-sm" onclick="showRegister()">Daftar</button>
                </div>`;
        }
        updateThemeIcon(document.body.getAttribute('data-theme'));
    }

    function showLogin() { isReg = false; document.getElementById('authTitle').innerText = 'Login'; document.getElementById('authToggle').innerText = 'Daftar'; switchView('auth'); }
    function showRegister() { isReg = true; document.getElementById('authTitle').innerText = 'Daftar User'; document.getElementById('authToggle').innerText = 'Login'; switchView('auth'); }

    async function checkSession() {
        const res = await api('check_session');
        if(res.logged_in) {
            state.user = res.username; state.role = res.role; state.userId = res.user_id;
            sessionStorage.setItem('uid', res.user_id);
            switchView('admin');
            renderHeader();
        } else { 
            state.user = null; 
            switchView('public');
            renderHeader();
        }
    }
    function logout() { api('logout'); location.reload(); }

    function switchView(v) {
        document.getElementById('publicSection').classList.add('hidden');
        document.getElementById('authSection').classList.add('hidden');
        document.getElementById('adminSection').classList.add('hidden');
        if(v === 'public') {
            document.getElementById('publicSection').classList.remove('hidden');
            loadPublic();
        } else if(v === 'auth') {
            document.getElementById('authSection').classList.remove('hidden');
        } else if(v === 'admin') {
            document.getElementById('adminSection').classList.remove('hidden');
            loadDashboard();
        }
    }

    function toggleView(v) {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.remove('hidden');
        document.querySelector('main').style.marginLeft = '0';

        document.getElementById('linksListContent').classList.add('hidden');
        document.getElementById('linkDetailContent').classList.add('hidden');
        document.getElementById('usersContent').classList.add('hidden');
        document.querySelectorAll('.nav-item').forEach(e => e.classList.remove('active'));

        if(v === 'links') {
            document.getElementById('linksListContent').classList.remove('hidden');
            document.getElementById('navAll').classList.add('active');
            loadLinks();
        } else if(v === 'users') {
            document.getElementById('usersContent').classList.remove('hidden');
            document.getElementById('navUsers').classList.add('active');
            loadUsers();
        }
    }

    async function loadDashboard() { loadCategories(); loadLinks(); renderHeader(); }
    async function loadPublic() { const res = await api('get_public_links'); if(res.status === 'success') renderGrid(res.data, 'publicGrid', true); }
    async function loadLinks() {
        const q = document.getElementById('searchName').value;
        const c = document.getElementById('filterCat').value;
        let url = `get_links`;
        if(q) url += `&search=${q}`;
        if(c) url += `&cat_id=${c}`;
        const res = await api(url);
        if(res.status === 'success') {
            state.links = res.data;
            renderGrid(res.data, 'gridContainer', false);
            renderTable(res.data);
        }
    }
    async function loadCategories() {
        const res = await api('get_categories');
        if(res.status === 'success') {
            state.cats = res.data;
            const list = document.getElementById('catList');
            const sel = document.getElementById('filterCat');
            const inp = document.getElementById('inpCat');
            list.innerHTML = ''; sel.innerHTML = '<option value="">Semua Kategori</option>'; inp.innerHTML = '<option value="">Tanpa Kategori</option>';
            state.cats.forEach(c => {
                list.innerHTML += `
                    <div class="nav-item" onclick="filterCategory(${c.id})">
                        <span style="flex:1;">🏷️ ${c.name}</span>
                        <div class="nav-actions">
                            <button class="action-btn" onclick="event.stopPropagation(); editCategory(${c.id})" title="Edit" style="background:none; border:none; cursor:pointer; font-size:1rem;">✏️</button>
                            <button class="action-btn" onclick="event.stopPropagation(); deleteCategory(${c.id})" title="Hapus" style="background:none; border:none; cursor:pointer; font-size:1rem; color:var(--danger);">×</button>
                        </div>
                    </div>`;
                sel.innerHTML += `<option value="${c.id}">${c.name}</option>`;
                inp.innerHTML += `<option value="${c.id}">${c.name}</option>`;
            });
        }
    }
    async function loadUsers() {
        const res = await api('get_users');
        if(res.status === 'success') {
            state.users = res.data;
            const tbody = document.getElementById('usersTableBody'); tbody.innerHTML = '';
            res.data.forEach(u => {
                const isActive = u.is_active == 1;
                tbody.innerHTML += `
                    <tr>
                        <td style="padding:1rem;">${u.username}</td>
                        <td style="padding:1rem;">${u.role}</td>
                        <td style="padding:1rem;">${isActive ? '<span class="badge badge-public">Aktif</span>' : '<span class="badge badge-private">Pending</span>'}</td>
                        <td style="padding:1rem;">
                            <button class="btn btn-sm" onclick="toggleUserStatus(${u.id})">${isActive ? 'Nonaktifkan' : 'Aktifkan'}</button>
                            <button class="btn btn-sm" onclick="editUser(${u.id})">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">Hapus</button>
                        </td>
                    </tr>`;
            });
        }
    }

    let currentLinkId = null;
    let cameFromPublic = false;

    async function openDetailView(id) {
        currentLinkId = id;
        const res = await api(`get_link_detail&link_id=${id}`);
        if(res.status === 'error') { showToast(res.message, 'error'); return; }

        state.detailLink = res.data;
        const l = res.data;
        document.getElementById('detailTitle').innerText = l.title;
        document.getElementById('detailCategory').innerText = l.category_name || '-';
        document.getElementById('detailDate').innerText = new Date(l.created_at).toLocaleString('id-ID');
        document.getElementById('detailDesc').innerHTML = l.description;

        const btnHtml = `<a href="${l.url}" target="_blank" class="btn btn-primary" style="font-size:1.1rem; padding: 0.75rem 1.5rem;">🔗 Buka Link Utama</a>`;

        if(l.extra_links && l.extra_links.length > 0) {
            document.getElementById('detailExtraLinksSection').classList.remove('hidden');
            const list = document.getElementById('detailExtraLinksList'); list.innerHTML = '';
            l.extra_links.forEach(el => { list.innerHTML += `<a href="${el.url}" target="_blank" class="btn btn-sm">🔗 ${el.title}</a>`; });
        } else { document.getElementById('detailExtraLinksSection').classList.add('hidden'); }

        if(l.attachments && l.attachments.length > 0) {
            document.getElementById('detailFilesSection').classList.remove('hidden');
            const grid = document.getElementById('detailFileGrid'); grid.innerHTML = '';
            l.attachments.forEach(f => {
                const isImg = f.filetype.startsWith('image');
                const preview = isImg ? `<img src="${f.filepath}" class="file-preview">` : `<div class="file-preview">📄</div>`;
                grid.innerHTML += `
                    <div class="file-item">
                        <button class="file-del-btn" onclick="deleteAttachment(${f.id})">×</button>
                        ${preview}
                        <div class="file-name">${f.filename}</div>
                        <a href="${f.filepath}" download class="btn btn-sm" style="margin-top:0.5rem; width:100%; justify-content:center;">Download</a>
                    </div>`;
            });
        } else { document.getElementById('detailFilesSection').classList.add('hidden'); }

        const isOwner = state.role === 'admin' || l.user_id == state.userId;
        const isShared = l.is_shared === 1;
        document.getElementById('btnEditLink').classList.toggle('hidden', !isOwner);
        document.getElementById('btnDeleteLink').classList.toggle('hidden', !isOwner);
        document.getElementById('btnShareLink').classList.toggle('hidden', !isOwner && !isShared);

        cameFromPublic = !document.getElementById('publicSection').classList.contains('hidden');
        if (cameFromPublic) {
            document.getElementById('sidebar').classList.add('hidden');
            document.querySelector('main').style.marginLeft = '0';
            document.querySelector('main').style.width = '100%';
        }
        document.getElementById('linksListContent').classList.add('hidden');
        document.getElementById('usersContent').classList.add('hidden');
        document.getElementById('linkDetailContent').classList.remove('hidden');
        document.getElementById('publicSection').classList.add('hidden');
        document.getElementById('adminSection').classList.remove('hidden');
        document.getElementById('detailActions').innerHTML = btnHtml;
    }

    function closeDetailView() {
        document.getElementById('linkDetailContent').classList.add('hidden');
        if (cameFromPublic) {
            document.getElementById('adminSection').classList.add('hidden');
            document.getElementById('publicSection').classList.remove('hidden');
            document.getElementById('sidebar').classList.remove('hidden');
            document.querySelector('main').style.marginLeft = '';
            document.querySelector('main').style.width = '';
        } else { document.getElementById('linksListContent').classList.remove('hidden'); }
        currentLinkId = null;
    }

    async function deleteAttachment(id) {
        if(!confirm('Hapus file ini?')) return;
        const res = await api('delete_attachment', 'POST', {id});
        if(res.status === 'success') { showToast('File dihapus'); openDetailView(currentLinkId); loadLinks(); }
    }

    function renderGrid(data, containerId, isPublicCtx) {
        const c = document.getElementById(containerId); c.innerHTML = '';
        data.forEach(l => {
            let badge = l.is_public ? '<span class="badge badge-public">Publik</span>' : '<span class="badge badge-private">Private</span>';
            if(!isPublicCtx && (l.owner_name && l.owner_name !== state.user || l.is_shared == 1)) badge = '<span class="badge badge-shared">Shared</span>';
            const ownerBadge = (l.owner_name && l.owner_name !== state.user && !l.is_public) ? `<span class="badge badge-private">${l.owner_name}</span>` : '';
            const fileBadge = l.attachment_count > 0 ? `<span class="badge badge-file">📎 ${l.attachment_count}</span>` : '';
            const linkBadge = (l.link_count > 0) ? `<span class="badge badge-public" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2);">🔗 ${l.link_count}</span>` : '';

            c.innerHTML += `
                <div class="card" onclick="openDetailView(${l.id})">
                    <img src="${l.image_src}" class="card-img">
                    <div class="card-body">
                        <div class="card-meta">
                            <div>${badge} ${fileBadge} ${linkBadge}</div>
                            <span style="font-size:0.75rem; color:var(--text-light);">${ownerBadge || l.category_name || '-'}</span>
                        </div>
                        <div class="card-title">${l.title}</div>
                        <div style="margin-top:auto; display:flex; gap:0.5rem;">
                            <a href="${l.url}" target="_blank" class="btn btn-sm btn-primary" onclick="event.stopPropagation()">Link</a>
                            <button class="btn btn-sm" onclick="event.stopPropagation(); openDetailView(${l.id})">Detail</button>
                        </div>
                    </div>
                </div>`;
        });
    }
    function renderTable(data) {
        const b = document.getElementById('tableBody'); b.innerHTML = '';
        data.forEach(l => {
            b.innerHTML += `
                <tr style="cursor:pointer;" onclick="openDetailView(${l.id})">
                    <td style="padding:1rem; font-weight:600;">${l.title}</td>
                    <td style="padding:1rem;">${l.is_public?'Publik':'Private'}</td>
                    <td style="padding:1rem;">${l.attachment_count} File</td>
                    <td style="padding:1rem;">
                        <button class="btn btn-sm" onclick="event.stopPropagation(); openDetailView(${l.id})">Detail</button>
                    </td>
                </tr>`;
        });
    }

    function openCatModal(isEdit = false, data = null) {
        const form = document.getElementById('catForm');
        form.reset();
        document.getElementById('catId').value = '';
        
        if (isEdit && data) {
            document.getElementById('catModalTitle').innerText = 'Edit Kategori';
            document.getElementById('catId').value = data.id;
            document.getElementById('catName').value = data.name;
        } else {
            document.getElementById('catModalTitle').innerText = 'Tambah Kategori';
        }
        openModal('modalCat');
    }

    function editCategory(id) {
        const cat = state.cats.find(c => c.id == id);
        if(cat) { openCatModal(true, cat); }
        else { alert('Data tidak ditemukan, silakan refresh.'); }
    }

    document.getElementById('catForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(e.target));
        const isEdit = !!formData.id;
        const action = isEdit ? 'update_category' : 'add_category';
        
        const res = await api(action, 'POST', formData);
        if(res.status === 'success') {
            showToast(res.message || 'Kategori berhasil disimpan');
            closeModal('modalCat');
            loadCategories();
            loadLinks();
        } else { showToast(res.message, 'error'); }
    };

    async function deleteCategory(id) {
        if(!confirm('Hapus kategori ini?')) return;
        const res = await api('delete_category', 'POST', {id});
        if(res.status === 'success') {
            showToast('Kategori dihapus');
            loadCategories();
            loadLinks();
        }
    }

    function initEditor(content = '') {
        if (CKEDITOR.instances['editor1']) {
            CKEDITOR.instances['editor1'].destroy(true);
        }
        
        document.getElementById('editor1').value = content;
        
        CKEDITOR.replace('editor1', {
            height: 300,
            toolbar: [
                { name: 'document', groups: [ 'mode', 'document', 'doctools' ], items: [ 'Source', '-', 'Save', 'NewPage', 'Preview', 'Print', '-', 'Templates' ] },
                { name: 'clipboard', groups: [ 'clipboard', 'undo' ], items: [ 'Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },
                { name: 'editing', groups: [ 'find', 'selection', 'spellchecker' ], items: [ 'Find', 'Replace', '-', 'SelectAll', '-', 'Scayt' ] },
                { name: 'forms', items: [ 'Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField' ] },
                '/',
                { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ], items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'RemoveFormat' ] },
                { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ], items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl', 'Language' ] },
                { name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
                { name: 'insert', items: [ 'Image', 'Flash', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'PageBreak', 'Iframe' ] },
                '/',
                { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
                { name: 'colors', items: [ 'TextColor', 'BGColor' ] },
                { name: 'tools', items: [ 'Maximize', 'ShowBlocks' ] },
                { name: 'about', items: [ 'About' ] }
            ]
        });
    }

    function openLinkModal() {
        document.getElementById('linkForm').reset();
        document.getElementById('inpId').value = '';
        document.getElementById('extraLinksContainer').innerHTML = '';
        document.getElementById('inpShareUsers').value = '';
        document.getElementById('modalTitle').innerText = 'Tambah Link';
        initEditor('');
        loadShareUsersForForm();
        openModal('modalLink');
    }

    async function loadShareUsersForForm() {
        const list = document.getElementById('shareUsersList');
        list.innerHTML = 'Loading...';
        const res = await api('get_share_users');
        if(res.status === 'success') {
            list.innerHTML = '';
            res.data.forEach(u => {
                list.innerHTML += `
                    <div class="list-item" onclick="this.querySelector('input').click()">
                        <label style="display:flex; align-items:center; gap:10px; width:100%; cursor:pointer;">
                            <input type="checkbox" class="share-user-chk" value="${u.id}">
                            ${u.username}
                        </label>
                    </div>`;
            });
        }
    }

    function addExtraLinkRow(val = {}) {
        const container = document.getElementById('extraLinksContainer');
        const div = document.createElement('div');
        div.className = 'extra-link-row';
        div.innerHTML = `
            <input type="text" placeholder="Nama Link" class="form-control el-title" value="${val.title||''}" style="flex:1;">
            <input type="url" placeholder="URL" class="form-control el-url" value="${val.url||''}" style="flex:2;">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">×</button>
        `;
        container.appendChild(div);
    }

    function editCurrentLink() {
        const l = state.detailLink;
        document.getElementById('inpId').value = l.id;
        document.getElementById('inpTitle').value = l.title;
        document.getElementById('inpUrl').value = l.url;
        document.getElementById('inpCat').value = l.category_id || '';
        document.getElementById('inpPublic').checked = (l.is_public == 1);
        document.getElementById('modalTitle').innerText = 'Edit Link';
        
        const container = document.getElementById('extraLinksContainer');
        container.innerHTML = '';
        if(l.extra_links && l.extra_links.length > 0) {
            l.extra_links.forEach(el => addExtraLinkRow(el));
        }
        initEditor(l.description || '');
        
        loadShareUsersForForm().then(() => {
            loadExistingShares(l.id);
        });
        
        openModal('modalLink');
    }

    async function loadExistingShares(linkId) {
        const res = await api(`get_link_shared_users&link_id=${linkId}`);
        if(res.status === 'success' && res.data) {
            document.querySelectorAll('.share-user-chk').forEach(chk => {
                if(res.data.includes(parseInt(chk.value))) {
                    chk.checked = true;
                }
            });
        }
    }

    document.getElementById('linkForm').onsubmit = async (e) => {
        e.preventDefault();
        
        if(CKEDITOR.instances['editor1']) {
            CKEDITOR.instances['editor1'].updateElement();
        }

        const extraLinksData = [];
        document.querySelectorAll('.extra-link-row').forEach(row => {
            const title = row.querySelector('.el-title').value;
            const url = row.querySelector('.el-url').value;
            if(title && url) extraLinksData.push({title, url});
        });
        document.getElementById('inpExtraLinks').value = JSON.stringify(extraLinksData);

        const shareIds = Array.from(document.querySelectorAll('.share-user-chk:checked')).map(c => c.value);
        document.getElementById('inpShareUsers').value = JSON.stringify(shareIds);

        const formData = new FormData(e.target);
        const res = await api(!!formData.get('id') ? 'update_link' : 'add_link', 'POST', formData);
        if(res.status === 'success') {
            showToast('Berhasil disimpan');
            closeModal('modalLink');
            loadLinks();
            if(currentLinkId) openDetailView(currentLinkId);
        }
    };

    async function deleteCurrentLink() {
        if(!confirm('Hapus postingan ini selamanya?')) return;
        const res = await api('delete_link', 'POST', {id: currentLinkId});
        if(res.status === 'success') {
            showToast('Terhapus');
            closeDetailView();
            loadLinks();
        }
    }

    async function toggleUserStatus(id) {
        if(!confirm('Ubah status?')) return;
        await api('toggle_user_status', 'POST', {id});
        loadUsers();
    }

    function openUserModal(isEdit = false, data = null) {
        const form = document.getElementById('userForm');
        form.reset();
        document.getElementById('userId').value = '';
        document.getElementById('userPass').required = true;
        
        if (isEdit && data) {
            document.getElementById('userModalTitle').innerText = 'Edit User';
            document.getElementById('userId').value = data.id;
            document.getElementById('userName').value = data.username;
            document.getElementById('userRole').value = data.role;
            document.getElementById('userPass').placeholder = 'Kosongkan jika tidak diubah';
            document.getElementById('userPass').required = false;
        } else {
            document.getElementById('userModalTitle').innerText = 'Tambah User';
            document.getElementById('userPass').placeholder = 'Password login';
        }
        openModal('modalUser');
    }

    function editUser(id) {
        const user = state.users.find(u => u.id == id);
        if(user) { openUserModal(true, user); }
        else { alert('User tidak ditemukan'); }
    }

    async function deleteUser(id) {
        if(!confirm('Hapus user ini?')) return;
        const res = await api('delete_user', 'POST', {id});
        if(res.status === 'success') {
            showToast(res.message || 'User dihapus');
            loadUsers();
        } else { showToast(res.message, 'error'); }
    }

    document.getElementById('userForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(e.target));
        const isEdit = !!formData.id;
        const action = isEdit ? 'update_user' : 'add_user';
        
        const res = await api(action, 'POST', formData);
        if(res.status === 'success') {
            showToast(res.message || 'User berhasil disimpan');
            closeModal('modalUser');
            loadUsers();
        } else { showToast(res.message, 'error'); }
    };

    async function openShareModal(id) {
        currentLinkId = id;
        openModal('modalShare');
        const list = document.getElementById('shareUserList'); list.innerHTML = 'Loading...';
        const res1 = await api('get_share_users');
        const res2 = await api(`get_link_shared_users&link_id=${id}`);
        if(res1.status === 'success') {
            const sharedIds = res2.data || [];
            list.innerHTML = '';
            res1.data.forEach(u => {
                const chk = sharedIds.includes(u.id) ? 'checked' : '';
                list.innerHTML += `
                    <div class="list-item" onclick="this.querySelector('input').click()">
                        <label style="display:flex; align-items:center; gap:10px; width:100%; cursor:pointer;">
                            <input type="checkbox" class="share-chk" value="${u.id}" ${chk}>
                            ${u.username}
                        </label>
                    </div>`;
            });
        }
    }

    async function saveShares() {
        const ids = Array.from(document.querySelectorAll('.share-chk:checked')).map(c => c.value);
        const res = await api('sync_link_shares', 'POST', {link_id: currentLinkId, target_ids: ids});
        if(res.status === 'success') { showToast('Share disimpan'); closeModal('modalShare'); }
    }

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    function filterCategory(id) {
        document.getElementById('filterCat').value = id || '';
        loadLinks();
    }
    function applyFilter() { loadLinks(); }
    function setView(m) {
        state.viewMode = m;
        document.getElementById('gridContainer').classList.toggle('hidden', m !== 'grid');
        document.getElementById('tableContainer').classList.toggle('hidden', m !== 'table');
    }
    
    initTheme();
    initSidebar();
    setView('grid');
    checkSession();
</script>
</body>
</html>
