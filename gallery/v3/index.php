<?php
// AJAX handlers - MUST be at the very top before ANY output
$ajax_action = $_GET['action'] ?? '';

// Handler: get_users
if ($ajax_action === 'get_users') {
    @header('Content-Type: application/json');
    @header('Cache-Control: no-cache');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in', 'users' => []]);
        exit;
    }
    require_once __DIR__ . '/config.php';
    $conn = dbConnect();
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $rs = $stmt->get_result();
    $list = [];
    while ($row = $rs->fetch_assoc()) {
        $list[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'users' => $list]);
    exit;
}

// Handler: get_edit_history
if ($ajax_action === 'get_edit_history') {
    @header('Content-Type: application/json');
    @header('Cache-Control: no-cache');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
        echo json_encode([]);
        exit;
    }
    require_once __DIR__ . '/config.php';
    $conn = dbConnect();
    
    $photoId = intval($_GET['photo_id'] ?? 0);
    
    if ($photoId) {
        $stmt = $conn->prepare("SELECT pe.*, p.title as photo_title, u.username as editor_name 
            FROM photo_edits pe 
            LEFT JOIN photos p ON pe.photo_id = p.id 
            LEFT JOIN users u ON pe.editor_id = u.id 
            WHERE pe.photo_id = ? 
            ORDER BY pe.created_at DESC");
        $stmt->bind_param("i", $photoId);
    } else {
        $stmt = $conn->prepare("SELECT pe.*, p.title as photo_title, u.username as editor_name 
            FROM photo_edits pe 
            LEFT JOIN photos p ON pe.photo_id = p.id 
            LEFT JOIN users u ON pe.editor_id = u.id 
            ORDER BY pe.created_at DESC");
    }
    
    $stmt->execute();
    $rs = $stmt->get_result();
    $history = [];
    while ($row = $rs->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode($history);
    exit;
}

// Handler: get_shares
if ($ajax_action === 'get_shares') {
    @header('Content-Type: application/json');
    @header('Cache-Control: no-cache');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        echo json_encode([]);
        exit;
    }
    $pid = (int)($_GET['photo_id'] ?? 0);
    if (!$pid) {
        echo json_encode([]);
        exit;
    }
    require_once __DIR__ . '/config.php';
    $conn = dbConnect();
    
    $stmt = $conn->prepare("SELECT user_id FROM photos WHERE id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $rs = $stmt->get_result();
    $photo = $rs->fetch_assoc();
    $stmt->close();
    
    if (!$photo) {
        echo json_encode([]);
        exit;
    }
    
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    
    if ($role !== 'admin' && $role !== 'superadmin' && $uid != $photo['user_id']) {
        echo json_encode([]);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT ps.id, ps.photo_id, ps.shared_with_user_id, ps.created_at, u.username 
        FROM photo_shares ps LEFT JOIN users u ON ps.shared_with_user_id = u.id 
        WHERE ps.photo_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $rs = $stmt->get_result();
    $list = [];
    while ($row = $rs->fetch_assoc()) {
        $list[] = $row;
    }
    $stmt->close();
    echo json_encode($list);
    exit;
}

// Handler: get_album_shares
if ($ajax_action === 'get_album_shares') {
    @header('Content-Type: application/json');
    @header('Cache-Control: no-cache');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        echo json_encode([]);
        exit;
    }
    $album_id = $_GET['album_id'] ?? '';
    if (empty($album_id)) {
        echo json_encode([]);
        exit;
    }
    require_once __DIR__ . '/config.php';
    dbConnect();
    
    $album = dbGetOne("SELECT album_id, user_id FROM photos WHERE album_id = ? LIMIT 1", [$album_id]);
    if (!$album) {
        echo json_encode([]);
        exit;
    }
    
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    
    if ($role !== 'admin' && $role !== 'superadmin' && $uid != $album['user_id']) {
        echo json_encode([]);
        exit;
    }
    
    $shares = dbGetAll("SELECT asid.id, asid.album_id, asid.shared_with_user_id, asid.created_at, u.username 
        FROM album_shares asid LEFT JOIN users u ON asid.shared_with_user_id = u.id 
        WHERE asid.album_id = ?", [$album_id]);
    echo json_encode($shares);
    exit;
}

// Handler: share_album
if ($ajax_action === 'share_album') {
    @header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? '';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    require_once __DIR__ . '/config.php';
    
    $album_id = $_POST['album_id'] ?? '';
    $user_ids = $_POST['user_ids'] ?? '';
    
    if (empty($album_id) || empty($user_ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $album = dbGetOne("SELECT user_id FROM photos WHERE album_id = ? LIMIT 1", [$album_id]);
    if (!$album) {
        echo json_encode(['success' => false, 'message' => 'Album not found']);
        exit;
    }
    
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    
    if ($role !== 'admin' && $role !== 'superadmin' && $uid != $album['user_id']) {
        echo json_encode(['success' => false, 'message' => 'No permission']);
        exit;
    }
    
    $ids = array_filter(array_map('intval', explode(',', $user_ids)));
    $added = 0;
    foreach ($ids as $userId) {
        if ($userId == $uid) continue;
        try {
            dbInsert("INSERT INTO album_shares (album_id, shared_with_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP",
                [$album_id, $userId]);
            $added++;
        } catch (Exception $e) {}
    }
    
    echo json_encode(['success' => true, 'message' => "Berhasil bagikan ke {$added} user"]);
    exit;
}

// Handler: remove_album_share
if ($ajax_action === 'remove_album_share') {
    @header('Content-Type: application/json');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    require_once __DIR__ . '/config.php';
    
    $album_id = $_POST['album_id'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if (empty($album_id) || !$userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    dbUpdate("DELETE FROM album_shares WHERE album_id = ? AND shared_with_user_id = ?", [$album_id, $userId]);
    echo json_encode(['success' => true, 'message' => 'Share removed']);
    exit;
}

// Normal page load - start here
session_start();

require_once __DIR__ . '/config.php';

define('UPLOAD_DIR', __DIR__ . '/upload/foto');

if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

dbConnect();

dbUpdate("CREATE TABLE IF NOT EXISTS photo_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    shared_with_user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_share (photo_id, shared_with_user_id)
)");

dbUpdate("CREATE TABLE IF NOT EXISTS album_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id VARCHAR(255) NOT NULL,
    shared_with_user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_album_share (album_id, shared_with_user_id)
)");

function getPhotoShares($photoId) {
    return dbGetAll("SELECT ps.*, u.username FROM photo_shares ps 
        LEFT JOIN users u ON ps.shared_with_user_id = u.id 
        WHERE ps.photo_id = ?", [$photoId]);
}

function getSharedPhotos($userId) {
    return dbGetAll("SELECT p.* FROM photos p 
        INNER JOIN photo_shares ps ON p.id = ps.photo_id 
        WHERE ps.shared_with_user_id = ?", [$userId]);
}

function isPhotoSharedWithUser($photoId, $userId) {
    $share = dbGetOne("SELECT id FROM photo_shares WHERE photo_id = ? AND shared_with_user_id = ?", [$photoId, $userId]);
    return $share !== null;
}

if (isset($_GET['action']) && $_GET['action'] === 'autocomplete') {
    $search = trim($_GET['search'] ?? '');
    $type = $_GET['type'] ?? 'all';
    $results = [];
    
    if ($search) {
        $searchLower = strtolower($search);
        $currentUserId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['role'] ?? null;
        
        if ($type === 'all' || $type === 'title') {
            $allPhotos = dbGetAll("SELECT id, title, album_title, photo_label, description, filepath, is_private, user_id FROM photos ORDER BY title ASC");
            foreach ($allPhotos as $p) {
                if ($currentUserId) {
                    if (!isSuperAdmin() && $userRole !== 'admin' && $p['user_id'] != $currentUserId && $p['is_private'] == 1 && !isPhotoSharedWithUser($p['id'], $currentUserId)) {
                        continue;
                    }
                } else {
                    if ($p['is_private'] == 1) continue;
                }
                
                if (stripos($p['title'], $search) !== false) {
                    $p['match_type'] = 'title';
                    $p['match_text'] = $p['title'];
                    $results[] = $p;
                }
            }
        }
        
        if ($type === 'all' || $type === 'album') {
            $allPhotos = dbGetAll("SELECT DISTINCT album_id, album_title FROM photos WHERE album_title IS NOT NULL AND album_title != '' ORDER BY album_title ASC");
            foreach ($allPhotos as $p) {
                if ($currentUserId) {
                    $checkPhoto = dbGetOne("SELECT id, is_private, user_id FROM photos WHERE album_id = ? ORDER BY created_at DESC LIMIT 1", [$p['album_id']]);
                    if ($checkPhoto) {
                        if (!isSuperAdmin() && $userRole !== 'admin' && $checkPhoto['user_id'] != $currentUserId && $checkPhoto['is_private'] == 1 && !isPhotoSharedWithUser($checkPhoto['id'], $currentUserId)) {
                            continue;
                        }
                    }
                }
                
                if (stripos($p['album_title'], $search) !== false) {
                    $exists = false;
                    foreach ($results as $r) {
                        if ($r['id'] == $p['album_id']) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $results[] = [
                            'id' => $p['album_id'],
                            'title' => $p['album_title'],
                            'album_title' => $p['album_title'],
                            'photo_label' => '',
                            'description' => 'Album: ' . $p['album_title'],
                            'match_type' => 'album',
                            'match_text' => $p['album_title']
                        ];
                    }
                }
            }
        }
        
        if ($type === 'all' || $type === 'label') {
            $allLabels = dbGetAll("SELECT DISTINCT photo_label FROM photos WHERE photo_label IS NOT NULL AND photo_label != '' ORDER BY photo_label ASC");
            foreach ($allLabels as $l) {
                if (stripos($l['photo_label'], $search) !== false) {
                    $exists = false;
                    foreach ($results as $r) {
                        if (($r['match_type'] === 'label' || $r['match_type'] === 'title') && strtolower($r['photo_label'] ?? '') === strtolower($l['photo_label'])) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $results[] = [
                            'id' => 0,
                            'title' => $l['photo_label'],
                            'album_title' => '',
                            'photo_label' => $l['photo_label'],
                            'description' => 'Label: ' . $l['photo_label'],
                            'match_type' => 'label',
                            'match_text' => $l['photo_label']
                        ];
                    }
                }
            }
        }
        
        usort($results, function($a, $b) {
            $scoreA = ($a['match_type'] === 'title') ? 3 : (($a['match_type'] === 'album') ? 2 : 1);
            $scoreB = ($b['match_type'] === 'title') ? 3 : (($b['match_type'] === 'album') ? 2 : 1);
            return $scoreB - $scoreA;
        });
        
        $results = array_slice($results, 0, 30);
    }
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCategories() {
    return dbGetAll("SELECT * FROM categories ORDER BY name");
}

function getPhotos($filterPrivate = true) {
    $sql = "SELECT * FROM photos";
    if ($filterPrivate && !isset($_SESSION['user_id'])) {
        $sql .= " WHERE is_private = 0";
    } elseif ($filterPrivate && isset($_SESSION['user_id']) && !isSuperAdmin() && $_SESSION['role'] !== 'admin') {
        $userId = $_SESSION['user_id'];
        $sql .= " WHERE (is_private = 0 OR (is_private = 1 AND user_id = ?) OR id IN (SELECT photo_id FROM photo_shares WHERE shared_with_user_id = ?))";
        return dbGetAll($sql, [$userId, $userId]);
    }
    $sql .= " ORDER BY album_sort_order DESC, created_at DESC";
    return dbGetAll($sql);
}

function getUsers() {
    return dbGetAll("SELECT * FROM users ORDER BY username");
}

function getViews() {
    return dbGetAll("SELECT * FROM views ORDER BY created_at DESC");
}

function getPhotoEditHistory($photoId = null) {
    if ($photoId) {
        return dbGetAll("SELECT * FROM photo_edits WHERE photo_id = ? ORDER BY created_at DESC", [$photoId]);
    }
    return dbGetAll("SELECT * FROM photo_edits ORDER BY created_at DESC");
}

function getPhotoEditHistoryByBatch($batchId) {
    $photoIds = dbGetAll("SELECT id FROM photos WHERE batch_id = ? OR id = ?", [$batchId, $batchId]);
    if (empty($photoIds)) return [];
    
    $ids = array_column($photoIds, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return dbGetAll("SELECT * FROM photo_edits WHERE photo_id IN ($placeholders) ORDER BY created_at DESC", $ids);
}

function getUserAlbums($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    $role = $_SESSION['role'] ?? '';
    
    if ($role === 'admin' || $role === 'superadmin') {
        return dbGetAll("SELECT album_id, album_title, MAX(created_at) as latest_photo FROM photos WHERE album_id IS NOT NULL AND album_id != '' GROUP BY album_id, album_title ORDER BY latest_photo DESC");
    }
    
    if ($userId) {
        // Get own albums + albums shared with user
        return dbGetAll("SELECT DISTINCT p.album_id, p.album_title, MAX(p.created_at) as latest_photo 
            FROM photos p 
            LEFT JOIN album_shares albs ON p.album_id = albs.album_id
            WHERE (p.user_id = ? OR albs.shared_with_user_id = ?) 
            AND p.album_id IS NOT NULL AND p.album_id != '' 
            GROUP BY p.album_id, p.album_title 
            ORDER BY latest_photo DESC", [$userId, $userId]);
    }
    
    return [];
}

$action = $_GET['action'] ?? 'home';
$message = '';
$messageType = '';

$expandedGroupsParam = $_GET['expanded'] ?? '';
$expandedGroupsState = $expandedGroupsParam ? explode(',', $expandedGroupsParam) : [];

$categories = getCategories();

define('SUPERADMIN_ID', 9999);
define('SUPERADMIN_USERNAME', 'superadmin');
define('SUPERADMIN_PASSWORD', 'superadmin123');

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function filterPhotosByVisibility($photos) {
    $filtered = [];
    foreach ($photos as $p) {
        if (!isSuperAdmin() && $p['user_id'] == SUPERADMIN_ID) {
            continue;
        }
        $currentUserId = $_SESSION['user_id'] ?? 0;
        if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
            if ($p['is_private'] == 1 && $p['user_id'] != $currentUserId) {
                if (!isPhotoSharedWithUser($p['id'], $currentUserId)) {
                    continue;
                }
            }
        }
        $filtered[] = $p;
    }
    return $filtered;
}

$photos = getPhotos(true);
$photos = filterPhotosByVisibility($photos);

if (isset($_SESSION['user_id'])) {
    $userPhotos = array_filter($photos, function($p) {
        return $p['user_id'] == $_SESSION['user_id'];
    });
    // Sort by created_at DESC (newest first)
    usort($userPhotos, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $myPhotoCount = count($userPhotos);
    $myPrivateCount = count(array_filter($userPhotos, function($p) { return $p['is_private'] == 1; }));
    $myPublicCount = count(array_filter($userPhotos, function($p) { return $p['is_private'] == 0; }));
}

// Fungsi untuk mendapatkan IP pengunjung
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function logPhotoView($photoId) {
    $userId = $_SESSION['user_id'] ?? 0;
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    dbInsert("INSERT INTO views (photo_id, user_id, ip, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())", 
        [$photoId, $userId, $ip, $userAgent]);
    
    dbUpdate("UPDATE photos SET views = views + 1 WHERE id = ?", [$photoId]);
}

// Fungsi untuk menentukan tipe perangkat dari user agent
function getDeviceType($userAgent) {
    if (preg_match('/(android|iphone|ipod|windows phone)/i', $userAgent)) {
        return 'Mobile';
    } elseif (preg_match('/(ipad|tablet)/i', $userAgent)) {
        return 'Tablet';
    } else {
        return 'Desktop';
    }
}

// Handle AJAX log view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log_view') {
    $photoId = intval($_POST['photo_id'] ?? 0);
    if ($photoId) {
        logPhotoView($photoId);
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// Handle POST requests with CSRF verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token for all posts except login and logout? We'll verify for all state-changing posts.
    // For login, we don't have token yet, so we skip.
    $skipCsrf = ['login', 'logout'];
    $isLoginOrLogout = isset($_POST['login']) || isset($_POST['logout']);
    if (!$isLoginOrLogout) {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            $message = 'Invalid CSRF token.';
            $messageType = 'danger';
            // Optionally, you can stop further processing, but we'll let it continue and just set message.
        }
    }

    if (isset($_POST['login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = $_POST['redirect'] ?? '';

        if ($username === SUPERADMIN_USERNAME && $password === SUPERADMIN_PASSWORD) {
            $_SESSION['user_id'] = SUPERADMIN_ID;
            $_SESSION['username'] = SUPERADMIN_USERNAME;
            $_SESSION['role'] = 'superadmin';
            $message = 'Login superadmin berhasil!';
            $messageType = 'success';
            if ($redirect === 'upload') {
                header('Location: ?action=upload&login_required=1');
                exit;
            }
        } else {
            $user = dbGetOne("SELECT * FROM users WHERE username = ?", [$username]);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $message = 'Login berhasil!';
                $messageType = 'success';
                if ($redirect === 'upload') {
                    header('Location: ?action=upload&login_required=1');
                    exit;
                }
            }
        }
        if (!isset($_SESSION['user_id'])) {
            $message = 'Username atau password salah!';
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: ?action=home');
        exit;
    }
    
    if (!isset($_SESSION['captcha_code'])) {
        $_SESSION['captcha_code'] = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    if (isset($_POST['upload']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if (!isset($_SESSION['user_id'])) {
            $inputCaptcha = $_POST['captcha'] ?? '';
            if ($inputCaptcha !== $_SESSION['captcha_code']) {
                $message = 'Kode captcha salah!';
                $messageType = 'danger';
            } else {
                $guestName = trim($_POST['guest_name'] ?? '');
                $existingUsers = dbGetAll("SELECT username FROM users");
                $existingUsernames = array_column($existingUsers, 'username');
                if (in_array($guestName, $existingUsernames)) {
                    $message = 'Nama tidak boleh sama dengan username yang sudah terdaftar!';
                    $messageType = 'danger';
                } else {
                    $_SESSION['captcha_code'] = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                    $title = trim($_POST['title'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $categoryArray = $_POST['category'] ?? [];
                    $category = is_array($categoryArray) ? implode(',', $categoryArray) : $categoryArray;
                    $photoLabel = trim($_POST['photo_label'] ?? '');
                    $isPrivate = 0;
                    
                    // Album handling for guest
                    $newAlbumTitle = trim($_POST['new_album_title'] ?? '');
                    $selectedAlbum = trim($_POST['album_title'] ?? '');
                    $albumTitle = !empty($newAlbumTitle) ? $newAlbumTitle : $selectedAlbum;
                    $albumId = !empty($albumTitle) ? preg_replace('/[^a-z0-9]+/', '-', strtolower($albumTitle)) : uniqid('album_');
                    
                    $catName = '';
                    if (!empty($categoryArray) && is_array($categoryArray)) {
                        $firstCatId = $categoryArray[0];
                        $cat = dbGetOne("SELECT slug FROM categories WHERE id = ?", [$firstCatId]);
                        $catName = $cat['slug'] ?? '';
                    }
                    
                    $uploaded = 0;
                    $files = $_FILES['photos'];
                    $batchId = uniqid('batch_');
                    
                    $fileCount = count($files['name']);
                    // Limit to 5 files for guests
                    $maxGuestFiles = 5;
                    $fileCount = min($fileCount, $maxGuestFiles);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($files['name'][$i] && $files['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $files['tmp_name'][$i];
                            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;
                            
                            $fileHash = md5_file($tmpName);
                            $hashExists = dbGetOne("SELECT id FROM photos WHERE file_hash = ?", [$fileHash]);
                            if ($hashExists) {
                                continue;
                            }
                            
                            $tahun = date('Y');
                            $bulan = date('m');
                            $slugTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
                            $subDir = UPLOAD_DIR . "/{$tahun}/{$bulan}/{$catName}/{$slugTitle}";
                            if (!file_exists($subDir)) mkdir($subDir, 0755, true);
                            
                            $newFilename = pathinfo($files['name'][$i], PATHINFO_FILENAME) . '_' . date('d-m-Y_H.i') . '.' . $ext;
$targetPath = $subDir . '/' . $newFilename;

                            compressImage($tmpName, $targetPath, 300);

                            $relativePath = "upload/foto/{$tahun}/{$bulan}/{$catName}/{$slugTitle}/{$newFilename}";
                            $sortOrder = ($fileCount - $i) * 10;

                            dbInsert("INSERT INTO photos (user_id, username, title, description, category, filename, filepath, is_private, views, batch_id, album_id, album_title, photo_label, album_sort_order, created_at, file_hash) VALUES (0, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW(), ?)",
                                [$guestName, $title, $description, $category, $newFilename, $relativePath, $batchId, $albumId, $albumTitle, $photoLabel, $sortOrder, $fileHash]);
                            $uploaded++;
                        }
                    }
                    
                    if ($uploaded > 0) {
                        $message = "Berhasil upload {$uploaded} foto!";
                        $messageType = 'success';
                    } else {
                        $message = 'Gagal upload foto. Periksa format, ukuran file, atau ada duplikasi.';
                        $messageType = 'danger';
                    }
                }
            }
        } else {
            $userId = $_SESSION['user_id'];
            $username = $_SESSION['username'];
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryArray = $_POST['category'] ?? [];
            $category = is_array($categoryArray) ? implode(',', $categoryArray) : $categoryArray;
            $isPrivate = isset($_POST['is_private']) ? 1 : 0;
            
            // Album handling
            $newAlbumTitle = trim($_POST['new_album_title'] ?? '');
            $selectedAlbum = trim($_POST['album_title'] ?? '');
            $albumTitle = !empty($newAlbumTitle) ? $newAlbumTitle : $selectedAlbum;
            $albumId = !empty($albumTitle) ? preg_replace('/[^a-z0-9]+/', '-', strtolower($albumTitle)) : uniqid('album_');
            $photoLabel = trim($_POST['photo_label'] ?? '');
            
            $catName = '';
            if (!empty($categoryArray) && is_array($categoryArray)) {
                $firstCatId = $categoryArray[0];
                $cat = dbGetOne("SELECT slug FROM categories WHERE id = ?", [$firstCatId]);
                $catName = $cat['slug'] ?? '';
            }
            
            $uploaded = 0;
            $files = $_FILES['photos'];
            $batchId = uniqid('batch_');
            
            $fileCount = count($files['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['name'][$i] && $files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;
                    
                    $fileHash = md5_file($tmpName);
                    $hashExists = dbGetOne("SELECT id FROM photos WHERE file_hash = ?", [$fileHash]);
                    if ($hashExists) {
                        continue;
                    }
                    
                    $tahun = date('Y');
                    $bulan = date('m');
                    $slugTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
                    $subDir = UPLOAD_DIR . "/{$tahun}/{$bulan}/{$catName}/{$slugTitle}";
                    if (!file_exists($subDir)) mkdir($subDir, 0755, true);
                    
                    $newFilename = pathinfo($files['name'][$i], PATHINFO_FILENAME) . '_' . date('d-m-Y_H.i') . '.' . $ext;
$targetPath = $subDir . '/' . $newFilename;
                        
                        compressImage($tmpName, $targetPath, 300);
                        
                        $relativePath = "upload/foto/{$tahun}/{$bulan}/{$catName}/{$slugTitle}/{$newFilename}";
                        $sortOrder = ($fileCount - $i) * 10;
                        
                        dbInsert("INSERT INTO photos (user_id, username, title, description, category, filename, filepath, is_private, views, batch_id, album_id, album_title, photo_label, album_sort_order, created_at, file_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW(), ?)",
                            [$userId, $username, $title, $description, $category, $newFilename, $relativePath, $isPrivate, $batchId, $albumId, $albumTitle, $photoLabel, $sortOrder, $fileHash]);
                        $uploaded++;
                }
            }
            
            if ($uploaded > 0) {
                $message = "Berhasil upload {$uploaded} foto!";
                $messageType = 'success';
            } else {
                $message = 'Gagal upload foto. Periksa format, ukuran file, atau ada duplikasi.';
                $messageType = 'danger';
            }
        }
    }
    
    if (isset($_POST['add_category']) && isset($_SESSION['user_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $catName = trim($_POST['category_name'] ?? '');
        if ($catName) {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($catName));
            dbInsert("INSERT INTO categories (name, slug, created_at) VALUES (?, ?, NOW())", [$catName, $slug]);
            $message = 'Kategori ditambahkan!';
            $messageType = 'success';
        }
    }
    
    if (isset($_POST['delete_photo']) && isset($_SESSION['user_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $photoId = intval($_POST['photo_id']);
        $deleteBatch = isset($_POST['delete_batch']) && $_POST['delete_batch'] == 1;
        
        $photoToDelete = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        
        if (!$photoToDelete) {
            $message = 'Foto tidak ditemukan!';
            $messageType = 'danger';
        } else {
            $canDelete = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $photoToDelete['user_id']);
            
            if (!$canDelete) {
                $message = 'Tidak memiliki izin menghapus foto ini.';
                $messageType = 'danger';
            } else {
                $batchIdToDelete = $photoToDelete['batch_id'] ?? $photoToDelete['id'];
                $filesDeleted = 0;
                
                if ($deleteBatch) {
                    $photosToDelete = dbGetAll("SELECT * FROM photos WHERE batch_id = ? OR id = ?", [$batchIdToDelete, $batchIdToDelete]);
                } else {
                    $photosToDelete = [$photoToDelete];
                }
                
                foreach ($photosToDelete as $photo) {
                    $fullPath = __DIR__ . '/' . $photo['filepath'];
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                        $filesDeleted++;
                    }
                    dbUpdate("DELETE FROM photos WHERE id = ?", [$photo['id']]);
                }
                
                $message = $deleteBatch ? "Semua foto dalam grup dihapus! ({$filesDeleted} file)" : "Foto dihapus!";
                $messageType = 'success';
            }
        }
    }
    
    // Handle set cover photo
    if (isset($_POST['set_cover']) && isset($_SESSION['user_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $photoId = intval($_POST['photo_id']);
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        
        if ($photo) {
            $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $photo['user_id']);
            
            if ($canEdit) {
                $albumId = $photo['album_id'] ?? $photo['batch_id'];
                
                // Unset all covers in this album first
                dbUpdate("UPDATE photos SET is_album_cover = 0 WHERE album_id = ? OR batch_id = ?", [$albumId, $albumId]);
                
                // Set this photo as cover
                dbUpdate("UPDATE photos SET is_album_cover = 1 WHERE id = ?", [$photoId]);
                
                $message = "Foto dijadikan cover album!";
                $messageType = 'success';
            }
        }
    }
    
    if (isset($_POST['edit_photo']) && isset($_SESSION['user_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $photoId = intval($_POST['photo_id']);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryArray = $_POST['category'] ?? [];
        $category = is_array($categoryArray) ? implode(',', $categoryArray) : $categoryArray;
        $isPrivate = isset($_POST['is_private']) ? 1 : 0;
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        
        if ($photo) {
            $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
            
            if ($canEdit) {
                dbUpdate("UPDATE photos SET title = ?, description = ?, category = ?, is_private = ? WHERE id = ?",
                    [$title, $description, $category, $isPrivate, $photoId]);
                $message = 'Foto berhasil diperbarui!';
                $messageType = 'success';
            } else {
                $message = 'Gagal memperbarui foto atau Anda tidak memiliki izin.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Foto tidak ditemukan.';
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['add_user']) && isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $newRole = $_POST['new_role'] ?? 'user';
        
        if ($newUsername && $newPassword) {
            $exists = dbGetOne("SELECT id FROM users WHERE username = ?", [$newUsername]);
            
            if (!$exists) {
                dbInsert("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())",
                    [$newUsername, password_hash($newPassword, PASSWORD_DEFAULT), $newRole]);
                $message = 'User berhasil ditambahkan!';
                $messageType = 'success';
            } else {
                $message = 'Username sudah ada!';
                $messageType = 'danger';
            }
        }
    }
    
    if (isset($_POST['edit_user']) && isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $userId = intval($_POST['user_id']);
        $editUsername = trim($_POST['edit_username'] ?? '');
        $editPassword = $_POST['edit_password'] ?? '';
        $editRole = $_POST['edit_role'] ?? 'user';
        
        if ($editPassword) {
            dbUpdate("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?",
                [$editUsername, password_hash($editPassword, PASSWORD_DEFAULT), $editRole, $userId]);
        } else {
            dbUpdate("UPDATE users SET username = ?, role = ? WHERE id = ?",
                [$editUsername, $editRole, $userId]);
        }
        $message = 'User diperbarui!';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_user']) && isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $userId = intval($_POST['user_id']);
        
        if ($userId == $_SESSION['user_id']) {
            $message = 'Tidak bisa menghapus akun sendiri!';
            $messageType = 'danger';
        } else {
            dbUpdate("DELETE FROM users WHERE id = ?", [$userId]);
            $message = 'User dihapus!';
            $messageType = 'success';
        }
    }
    
    // AJAX handler for inline edit and multi-edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_edit') {
        header('Content-Type: application/json');
        
        // Check session
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoId = intval($_POST['photo_id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        
        if (!$photoId || !$field) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        if (!$photo) {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
            exit;
        }
        
        $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
        
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        $oldValue = $photo[$field] ?? '';
        
        if ($field === 'title') {
            dbUpdate("UPDATE photos SET title = ? WHERE id = ?", [$value, $photoId]);
        } elseif ($field === 'photo_label') {
            dbUpdate("UPDATE photos SET photo_label = ? WHERE id = ?", [$value, $photoId]);
        } elseif ($field === 'is_private') {
            $intValue = intval($value);
            dbUpdate("UPDATE photos SET is_private = ? WHERE id = ?", [$intValue, $photoId]);
        } elseif ($field === 'category') {
            dbUpdate("UPDATE photos SET category = ? WHERE id = ?", [$value, $photoId]);
        } elseif ($field === 'description') {
            dbUpdate("UPDATE photos SET description = ? WHERE id = ?", [$value, $photoId]);
        } elseif ($field === 'album_title') {
            $albumId = !empty($value) ? preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) : null;
            dbUpdate("UPDATE photos SET album_title = ?, album_id = ? WHERE id = ?", [$value, $albumId, $photoId]);
        } elseif ($field === 'album_id') {
            if (strpos($value, 'new_album:') === 0) {
                $newAlbumName = substr($value, 9);
                $albumId = 'album_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($newAlbumName));
                dbUpdate("UPDATE photos SET album_title = ?, album_id = ? WHERE id = ?", [$newAlbumName, $albumId, $photoId]);
            } elseif ($value === 'new_album') {
                $albumId = 'album_' . uniqid();
                $newTitle = 'Album Baru ' . date('d/m/Y');
                dbUpdate("UPDATE photos SET album_title = ?, album_id = ? WHERE id = ?", [$newTitle, $albumId, $photoId]);
            } elseif (!empty($value)) {
                $existingAlbum = dbGetOne("SELECT album_title FROM photos WHERE album_id = ? LIMIT 1", [$value]);
                if ($existingAlbum) {
                    dbUpdate("UPDATE photos SET album_id = ?, album_title = ? WHERE id = ?", [$value, $existingAlbum['album_title'], $photoId]);
                }
            }
        } elseif ($field === 'new_album_title') {
            $newAlbumName = $_POST['new_album_title'] ?? $value;
            if (!empty($newAlbumName)) {
                $albumIdNew = preg_replace('/[^a-z0-9]+/', '-', strtolower($newAlbumName));
                $albumIdOld = $photo['album_id'] ?? $photo['batch_id'];
                if (!empty($albumIdOld)) {
                    dbUpdate("UPDATE photos SET album_title = ?, album_id = ? WHERE album_id = ? OR batch_id = ?", [$newAlbumName, $albumIdNew, $albumIdOld, $albumIdOld]);
                }
            }
        } elseif ($field === 'created_at') {
            $dateValue = date('Y-m-d H:i:s', strtotime($value));
            dbUpdate("UPDATE photos SET created_at = ? WHERE id = ?", [$dateValue, $photoId]);
        }
        
        // Log the edit
        $editorId = $_SESSION['user_id'] ?? 0;
        $editorName = $_SESSION['username'] ?? 'unknown';
        
        dbInsert("INSERT INTO photo_edits (photo_id, editor_id, editor_name, field_name, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$photoId, $editorId, $editorName, $field, $oldValue, $value]);
        
        echo json_encode(['success' => true, 'message' => 'Updated successfully']);
        exit;
    }
    
    // AJAX handler for multi-edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'multi_edit') {
        header('Content-Type: application/json');
        
        // Check session
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoIds = $_POST['photo_ids'] ?? '';
        if (!$photoIds) {
            echo json_encode(['success' => false, 'message' => 'No photos selected']);
            exit;
        }
        
        $ids = array_filter(array_map('intval', explode(',', $photoIds)));
        $title = $_POST['title'] ?? '';
        $photoLabel = $_POST['photo_label'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        $isPrivate = $_POST['is_private'] ?? '';
        
        $editorId = $_SESSION['user_id'] ?? 0;
        $editorName = $_SESSION['username'] ?? 'unknown';
        
        $updated = 0;
        
        foreach ($ids as $photoId) {
            $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
            if (!$photo) continue;
            
            $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
            if (!$canEdit) continue;
            
            $updates = [];
            $params = [];
            
            if (!empty($title)) {
                $updates[] = 'title = ?';
                $params[] = $title;
                dbInsert("INSERT INTO photo_edits (photo_id, editor_id, editor_name, field_name, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$photoId, $editorId, $editorName, 'title', $photo['title'], $title]);
            }
            if (!empty($description)) {
                $updates[] = 'description = ?';
                $params[] = $description;
                dbInsert("INSERT INTO photo_edits (photo_id, editor_id, editor_name, field_name, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$photoId, $editorId, $editorName, 'description', $photo['description'], $description]);
            }
            if (!empty($photoLabel)) {
                $updates[] = 'photo_label = ?';
                $params[] = $photoLabel;
                dbInsert("INSERT INTO photo_edits (photo_id, editor_id, editor_name, field_name, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$photoId, $editorId, $editorName, 'photo_label', $photo['photo_label'], $photoLabel]);
            }
            if (!empty($category)) {
                $updates[] = 'category = ?';
                $params[] = $category;
                dbInsert("INSERT INTO photo_edits (photo_id, editor_id, editor_name, field_name, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$photoId, $editorId, $editorName, 'category', $photo['category'], $category]);
            }
            if ($isPrivate !== '') {
                $updates[] = 'is_private = ?';
                $params[] = $isPrivate;
                dbInsert("INSERT INTO photo_edits (photo_id, editor_id, editor_name, field_name, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$photoId, $editorId, $editorName, 'is_private', $photo['is_private'], $isPrivate]);
            }
            
            if (!empty($updates)) {
                $params[] = $photoId;
                dbUpdate("UPDATE photos SET " . implode(', ', $updates) . " WHERE id = ?", $params);
                $updated++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "Berhasil update {$updated} foto"]);
        exit;
    }
    
    // AJAX handler for multi-delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'multi_delete') {
        header('Content-Type: application/json');
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoIds = $_POST['photo_ids'] ?? '';
        if (!$photoIds) {
            echo json_encode(['success' => false, 'message' => 'No photos selected']);
            exit;
        }
        
        $ids = array_filter(array_map('intval', explode(',', $photoIds)));
        $deleted = 0;
        
        foreach ($ids as $photoId) {
            $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
            if (!$photo) continue;
            
            $canDelete = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
            if (!$canDelete) continue;
            
            $fullPath = __DIR__ . '/' . $photo['filepath'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            dbUpdate("DELETE FROM photos WHERE id = ?", [$photoId]);
            $deleted++;
        }
        
        echo json_encode(['success' => true, 'message' => "Berhasil hapus {$deleted} foto"]);
        exit;
    }
    
    // AJAX handler for multi-edit photos (user's own photos)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'multi_edit_photos') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoIds = $_POST['photo_ids'] ?? '';
        if (!$photoIds) {
            echo json_encode(['success' => false, 'message' => 'No photos selected']);
            exit;
        }
        
        $ids = array_filter(array_map('intval', explode(',', $photoIds)));
        $updated = 0;
        
        $title = $_POST['title'] ?? '';
        $photoLabel = $_POST['photo_label'] ?? '';
        $description = $_POST['description'] ?? '';
        $isPrivate = $_POST['is_private'] ?? '';
        
        foreach ($ids as $photoId) {
            $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
            if (!$photo) continue;
            
            $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
            if (!$canEdit) continue;
            
            $updates = [];
            $params = [];
            
            if ($title !== '') {
                $updates[] = 'title = ?';
                $params[] = $title;
            }
            if ($photoLabel !== '') {
                $updates[] = 'photo_label = ?';
                $params[] = $photoLabel;
            }
            if ($description !== '') {
                $updates[] = 'description = ?';
                $params[] = $description;
            }
            if ($isPrivate !== '') {
                $updates[] = 'is_private = ?';
                $params[] = (int)$isPrivate;
            }
            
            if (!empty($updates)) {
                $params[] = $photoId;
                dbUpdate("UPDATE photos SET " . implode(', ', $updates) . " WHERE id = ?", $params);
                $updated++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "Berhasil update {$updated} foto"]);
        exit;
    }
    
    // AJAX handler for multi-delete users
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'multi_delete_users') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $userIds = $_POST['user_ids'] ?? '';
        if (!$userIds) {
            echo json_encode(['success' => false, 'message' => 'No users selected']);
            exit;
        }
        
        $ids = array_filter(array_map('intval', explode(',', $userIds)));
        $deleted = 0;
        
        foreach ($ids as $userId) {
            if ($userId == $_SESSION['user_id']) continue;
            if ($userId == SUPERADMIN_ID) continue;
            
            dbUpdate("DELETE FROM users WHERE id = ?", [$userId]);
            $deleted++;
        }
        
        echo json_encode(['success' => true, 'message' => "Berhasil hapus {$deleted} user"]);
        exit;
    }
    
    // AJAX handler for reorder photos within album
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder_photo' && isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoId = intval($_POST['photo_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        
        if (!$photoId || !in_array($direction, ['up', 'down'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        if (!$photo) {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
            exit;
        }
        
        $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        $albumId = !empty($photo['album_id']) ? $photo['album_id'] : ($photo['batch_id'] ?? null);
        if (!$albumId) {
            echo json_encode(['success' => false, 'message' => 'No album group']);
            exit;
        }
        
        // Get all photos in this album/batch ordered by album_sort_order DESC (higher = first/top)
        $allPhotosInAlbum = dbGetAll("SELECT id, album_sort_order FROM photos WHERE (album_id = ? OR batch_id = ?) ORDER BY album_sort_order DESC, id DESC", [$albumId, $albumId]);
        
        // If no album_sort_order set yet, initialize from created_at order
        $needsInit = false;
        foreach ($allPhotosInAlbum as $p) {
            if (empty($p['album_sort_order']) || $p['album_sort_order'] == 0) {
                $needsInit = true;
                break;
            }
        }
        
        if ($needsInit) {
            // Initialize album_sort_order based on current created_at order
            $orderedPhotos = dbGetAll("SELECT id, created_at FROM photos WHERE (album_id = ? OR batch_id = ?) ORDER BY created_at DESC, id DESC", [$albumId, $albumId]);
            $sortOrder = count($orderedPhotos) * 10;
            foreach ($orderedPhotos as $p) {
                dbUpdate("UPDATE photos SET album_sort_order = ? WHERE id = ?", [$sortOrder, $p['id']]);
                $sortOrder -= 10;
            }
            // Refresh the list
            $allPhotosInAlbum = dbGetAll("SELECT id, album_sort_order FROM photos WHERE (album_id = ? OR batch_id = ?) ORDER BY album_sort_order DESC, id DESC", [$albumId, $albumId]);
        }
        
        $photoIndex = -1;
        $photoIdKey = $photoId;
        foreach ($allPhotosInAlbum as $i => $p) {
            if ($p['id'] == $photoIdKey) {
                $photoIndex = $i;
                break;
            }
        }
        
        if ($photoIndex === -1) {
            $photoBatchId = $photo['batch_id'] ?? null;
            if ($photoBatchId) {
                $allPhotosInBatch = dbGetAll("SELECT id, album_sort_order FROM photos WHERE batch_id = ? ORDER BY album_sort_order DESC, id DESC", [$photoBatchId]);
                foreach ($allPhotosInBatch as $i => $p) {
                    if ($p['id'] == $photoIdKey) {
                        $photoIndex = $i;
                        $allPhotosInAlbum = $allPhotosInBatch;
                        break;
                    }
                }
            }
        }
        
        if ($photoIndex === -1) {
            echo json_encode(['success' => false, 'message' => 'Photo not found in album group']);
            exit;
        }
        
        $totalPhotos = count($allPhotosInAlbum);
        
        // In DESC order (higher sort_order = first/top)
        // "Up" = move to smaller index (higher sort_order)
        // "Down" = move to larger index (lower sort_order)
        
        if ($direction === 'up') {
            if ($photoIndex <= 0) {
                echo json_encode(['success' => false, 'message' => 'Sudah di posisi teratas']);
                exit;
            }
            $swapIndex = $photoIndex - 1;
        } elseif ($direction === 'down') {
            if ($photoIndex >= $totalPhotos - 1) {
                echo json_encode(['success' => false, 'message' => 'Sudah di posisi terbawah']);
                exit;
            }
            $swapIndex = $photoIndex + 1;
        }
        
        // Swap by adjusting album_sort_order
        $currentSortOrder = $allPhotosInAlbum[$photoIndex]['album_sort_order'];
        $swapSortOrder = $allPhotosInAlbum[$swapIndex]['album_sort_order'];
        
        dbUpdate("UPDATE photos SET album_sort_order = ? WHERE id = ?", [$swapSortOrder, $photoId]);
        dbUpdate("UPDATE photos SET album_sort_order = ? WHERE id = ?", [$currentSortOrder, $allPhotosInAlbum[$swapIndex]['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Posisi berhasil diperbarui']);
        exit;
    }
    
    // AJAX handler for renaming group/album title
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename_group') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $groupId = $_POST['group_id'] ?? '';
        $newTitle = trim($_POST['new_title'] ?? '');
        
        if (!$groupId || !$newTitle) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $checkPhotos = dbGetAll("SELECT id, user_id FROM photos WHERE album_id = ? OR batch_id = ? LIMIT 1", [$groupId, $groupId]);
        if (empty($checkPhotos)) {
            $checkPhotos = dbGetAll("SELECT id, user_id FROM photos WHERE id = ?", [$groupId]);
        }
        
        if (empty($checkPhotos)) {
            echo json_encode(['success' => false, 'message' => 'Group not found']);
            exit;
        }
        
        $firstPhoto = $checkPhotos[0];
        $canEdit = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $firstPhoto['user_id'];
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        $newAlbumId = preg_replace('/[^a-z0-9]+/', '-', strtolower($newTitle));
        dbUpdate("UPDATE photos SET album_title = ?, album_id = ? WHERE album_id = ? OR batch_id = ?", [$newTitle, $newAlbumId, $groupId, $groupId]);
        
        echo json_encode(['success' => true, 'message' => 'Album renamed successfully']);
        exit;
    }

    // Handle add photos to existing group
    if (isset($_POST['add_to_group']) && isset($_SESSION['user_id']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $targetBatchId = trim($_POST['target_group'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $photoLabel = trim($_POST['photo_label'] ?? '');
        $isAlbumCover = isset($_POST['is_album_cover']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');
        $category = '';
        
        // Find target photos by batch_id or album_id
        $targetPhotos = dbGetAll("SELECT * FROM photos WHERE batch_id = ? OR album_id = ? OR id = ?", [$targetBatchId, $targetBatchId, $targetBatchId]);
        
        $albumTitle = '';
        $albumId = '';
        
        if (!empty($targetPhotos)) {
            $firstTarget = $targetPhotos[0];
            $title = !empty($title) ? $title : $firstTarget['title'];
            $description = !empty($description) ? $description : $firstTarget['description'];
            $category = $firstTarget['category'];
            $albumTitle = $firstTarget['album_title'] ?? $title;
            $albumId = $firstTarget['album_id'] ?? $targetBatchId;
            
            // If setting as cover, unset other covers in this album first
            if ($isAlbumCover) {
                dbUpdate("UPDATE photos SET is_album_cover = 0 WHERE album_id = ?", [$albumId]);
            }
        }
        
        if ($targetBatchId && !empty($_FILES['photos']['name'][0])) {
            $userId = $_SESSION['user_id'];
            $username = $_SESSION['username'];
            
            $files = $_FILES['photos'];
            $fileCount = count($files['name']);
            $uploaded = 0;
            $errors = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['name'][$i] && $files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $errors[] = "File {$files['name'][$i]}: Format tidak didukung";
                        continue;
                    }
                    
                    $fileHash = md5_file($tmpName);
                    $hashExists = dbGetOne("SELECT id FROM photos WHERE file_hash = ?", [$fileHash]);
                    if ($hashExists) {
                        $errors[] = "File {$files['name'][$i]}: Sudah ada (duplikat)";
                        continue;
                    }
                    
                    $firstPhoto = !empty($targetPhotos) ? $targetPhotos[0] : null;
                    if ($firstPhoto) {
                        $filepath = $firstPhoto['filepath'];
                        $dirPath = dirname(__DIR__ . '/' . $filepath);
                    } else {
                        $tahun = date('Y');
                        $bulan = date('m');
                        $dirPath = UPLOAD_DIR . "/{$tahun}/{$bulan}/lainnya";
                    }
                    
                    if (!file_exists($dirPath)) {
                        if (!mkdir($dirPath, 0755, true)) {
                            $errors[] = "Gagal membuat direktori: {$dirPath}";
                            continue;
                        }
                    }
                    
                    $newFilename = pathinfo($files['name'][$i], PATHINFO_FILENAME) . '_' . date('d-m-Y_H.i') . '.' . $ext;
                    $targetPath = $dirPath . '/' . $newFilename;
                    
                    try {
                        compressImage($tmpName, $targetPath, 300);
                    } catch (Exception $e) {
                        $errors[] = "File {$files['name'][$i]}: Gagal compress - " . $e->getMessage();
                        continue;
                    }
                    
                    if (!file_exists($targetPath)) {
                        $errors[] = "File {$files['name'][$i]}: Gagal menyimpan file";
                        continue;
                    }
                    
                    $relativePath = !empty($firstPhoto['filepath']) ? $firstPhoto['filepath'] : "upload/foto/{$tahun}/{$bulan}/lainnya/{$newFilename}";
                    $relativePath = preg_replace('/[^\/]+$/', $newFilename, $relativePath);
                    
                    $maxSortOrder = 0;
                    if (!empty($targetPhotos)) {
                        foreach ($targetPhotos as $tp) {
                            $sortVal = !empty($tp['album_sort_order']) ? $tp['album_sort_order'] : 0;
                            if ($sortVal > $maxSortOrder) $maxSortOrder = $sortVal;
                        }
                    }
                    $newSortOrder = $maxSortOrder - (($i + 1) * 10);
                    
                    try {
                        dbInsert("INSERT INTO photos (user_id, username, title, description, category, filename, filepath, is_private, views, batch_id, album_id, album_title, photo_label, is_album_cover, album_sort_order, created_at, file_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, NOW(), ?)",
                            [$userId, $username, $title, $description, $category, $newFilename, $relativePath, $firstPhoto ? $firstPhoto['is_private'] : 0, $targetBatchId, $albumId, $albumTitle, $photoLabel, $isAlbumCover, $newSortOrder, $fileHash]);
                        $uploaded++;
                    } catch (Exception $e) {
                        $errors[] = "File {$files['name'][$i]}: Gagal simpan ke database - " . $e->getMessage();
                        @unlink($targetPath);
                    }
                }
            }
            
            if ($uploaded > 0) {
                if (!empty($errors)) {
                    $message = "Berhasil upload {$uploaded} foto. Beberapa error: " . implode(', ', $errors);
                    $messageType = 'warning';
                } else {
                    $message = "Berhasil menambah {$uploaded} foto ke album!";
                    $messageType = 'success';
                }
            } elseif (!empty($errors)) {
                $message = "Gagal upload: " . implode(', ', $errors);
                $messageType = 'danger';
            }
        }
    }
    
    // AJAX handler for share photo to user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share_photo') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoId = intval($_POST['photo_id'] ?? 0);
        $userId = intval($_POST['user_id'] ?? 0);
        
        if (!$photoId || !$userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        if (!$photo) {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
            exit;
        }
        
        $canShare = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
        if (!$canShare) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        if ($userId == $photo['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Cannot share to yourself']);
            exit;
        }
        
        try {
            dbInsert("INSERT INTO photo_shares (photo_id, shared_with_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP",
                [$photoId, $userId]);
            echo json_encode(['success' => true, 'message' => 'Photo shared successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // AJAX handler for share photo to multiple users
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share_photo_multiple') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoId = intval($_POST['photo_id'] ?? 0);
        $userIds = $_POST['user_ids'] ?? '';
        
        if (!$photoId || !$userIds) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        if (!$photo) {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
            exit;
        }
        
        $canShare = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
        if (!$canShare) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        $ids = array_filter(array_map('intval', explode(',', $userIds)));
        $currentUserId = $_SESSION['user_id'];
        
        $added = 0;
        foreach ($ids as $userId) {
            if ($userId == $currentUserId) continue;
            if ($userId == $photo['user_id']) continue;
            
            try {
                dbInsert("INSERT INTO photo_shares (photo_id, shared_with_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP",
                    [$photoId, $userId]);
                $added++;
            } catch (Exception $e) {
                // Continue on error
            }
        }
        
        echo json_encode(['success' => true, 'message' => "Berhasil berbagi ke {$added} user"]);
        exit;
    }
    
    // AJAX handler for remove share
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_share') {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $photoId = intval($_POST['photo_id'] ?? 0);
        $userId = intval($_POST['user_id'] ?? 0);
        
        if (!$photoId || !$userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $photo = dbGetOne("SELECT * FROM photos WHERE id = ?", [$photoId]);
        if (!$photo) {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
            exit;
        }
        
        $canShare = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin' || $_SESSION['user_id'] == $photo['user_id'];
        if (!$canShare) {
            echo json_encode(['success' => false, 'message' => 'No permission']);
            exit;
        }
        
        dbUpdate("DELETE FROM photo_shares WHERE photo_id = ? AND shared_with_user_id = ?", [$photoId, $userId]);
        echo json_encode(['success' => true, 'message' => 'Share removed successfully']);
        exit;
    }
    
    // AJAX handler for get shares
    if (isset($_GET['action']) && $_GET['action'] === 'get_shares') {
        header('Content-Type: application/json');
        
        if (empty($_SESSION['user_id'])) {
            echo json_encode([]);
            exit;
        }
        
        $photoId = intval($_GET['photo_id'] ?? 0);
        if (!$photoId) {
            echo json_encode([]);
            exit;
        }
        
        require_once __DIR__ . '/config.php';
        $conn = dbConnect();
        
        $stmt = $conn->prepare("SELECT user_id FROM photos WHERE id = ?");
        $stmt->bind_param("i", $photoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $photo = $result->fetch_assoc();
        $stmt->close();
        
        if (!$photo) {
            echo json_encode([]);
            exit;
        }
        
        $currentUserId = $_SESSION['user_id'];
        $userRole = $_SESSION['role'] ?? '';
        
        if ($userRole !== 'admin' && $userRole !== 'superadmin' && $currentUserId != $photo['user_id']) {
            echo json_encode([]);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT ps.id, ps.photo_id, ps.shared_with_user_id, ps.created_at, u.username 
            FROM photo_shares ps LEFT JOIN users u ON ps.shared_with_user_id = u.id 
            WHERE ps.photo_id = ?");
        $stmt->bind_param("i", $photoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $shares = [];
        while ($row = $result->fetch_assoc()) {
            $shares[] = $row;
        }
        $stmt->close();
        
        echo json_encode($shares);
        exit;
    }
}

/**
 * Kompresi gambar - target 300KB tanpa mengurangi resolusi
 */
function compressImage($source, $destination, $maxSizeKB = 300) {
    $info = @getimagesize($source);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
        throw new Exception('Invalid image file');
    }
    $originalWidth = $info[0];
    $originalHeight = $info[1];
    
    if ($info['mime'] === 'image/jpeg') {
        $image = @imagecreatefromjpeg($source);
        if (!$image) {
            throw new Exception('Failed to read JPEG file');
        }
        $quality = 85;
        
        do {
            ob_start();
            imagejpeg($image, null, $quality);
            $output = ob_get_clean();
            $sizeKB = strlen($output) / 1024;
            $quality -= 5;
        } while ($sizeKB > $maxSizeKB && $quality > 10);
        
        imagejpeg($image, $destination, $quality);
        imagedestroy($image);
        
    } elseif ($info['mime'] === 'image/png') {
        $image = @imagecreatefrompng($source);
        if (!$image) {
            throw new Exception('Failed to read PNG file');
        }
        imagesavealpha($image, true);
        
        $quality = 9;
        do {
            ob_start();
            imagepng($image, null, $quality);
            $output = ob_get_clean();
            $sizeKB = strlen($output) / 1024;
            if ($sizeKB > $maxSizeKB && $quality > 0) {
                $quality--;
            } else {
                break;
            }
        } while ($sizeKB > $maxSizeKB && $quality >= 0);
        
        imagepng($image, $destination, $quality);
        imagedestroy($image);
    } else {
        if (!copy($source, $destination)) {
            throw new Exception('Failed to copy file');
        }
    }
}

$categories = getCategories();
$photos = getPhotos(true);
$photos = filterPhotosByVisibility($photos);

$filterCategory = $_GET['category'] ?? '';
$filterMine = isset($_GET['mine']) && isset($_SESSION['user_id']);
$searchQuery = $_GET['search'] ?? '';
$filterMonthYear = $_GET['month_year'] ?? '';
$filteredPhotos = $photos;

if ($searchQuery) {
    $searchLower = strtolower($searchQuery);
    $filteredPhotos = array_filter($filteredPhotos, function($p) use ($searchLower) {
        return strpos(strtolower($p['title']), $searchLower) !== false;
    });
}

if ($filterCategory) {
    $filteredPhotos = array_filter($filteredPhotos, function($p) use ($filterCategory) {
        $cats = explode(',', $p['category']);
        return in_array($filterCategory, $cats);
    });
}

if ($filterMonthYear) {
    $filteredPhotos = array_filter($filteredPhotos, function($p) use ($filterMonthYear) {
        return date('Y-m', strtotime($p['created_at'])) === $filterMonthYear;
    });
}

if ($filterMine) {
    $filteredPhotos = array_filter($filteredPhotos, function($p) {
        return $p['user_id'] == $_SESSION['user_id'];
    });
}

if (!isset($_SESSION['user_id'])) {
    $filteredPhotos = array_filter($filteredPhotos, function($p) {
        return $p['is_private'] == 0;
    });
} else {
    $filteredPhotos = array_filter($filteredPhotos, function($p) {
        global $_SESSION;
        $userId = $_SESSION['user_id'] ?? 0;
        $role = $_SESSION['role'] ?? '';
        return $p['is_private'] == 0 || $userId == $p['user_id'] || $role === 'admin' || $role === 'superadmin';
    });
}

// Sort photos: by is_album_cover first, then by created_at
usort($filteredPhotos, function($a, $b) {
    // First priority: is_album_cover
    $coverA = $a['is_album_cover'] ?? 0;
    $coverB = $b['is_album_cover'] ?? 0;
    if ($coverA != $coverB) {
        return $coverB - $coverA; // cover photos first
    }
    
    // Second priority: created_at (newest first)
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Group by album (album_id) - each album can have multiple sub-groups
$photoGroups = [];
$albums = []; // For album-level grouping
foreach ($filteredPhotos as $photo) {
    $albumId = !empty($photo['album_id']) ? $photo['album_id'] : (!empty($photo['batch_id']) ? $photo['batch_id'] : 'batch_' . $photo['id']);
    $albumTitle = !empty($photo['album_title']) ? $photo['album_title'] : $photo['title'];
    
    if (!isset($photoGroups[$albumId])) {
        $photoGroups[$albumId] = [];
        $albums[$albumId] = [
            'title' => $albumTitle,
            'count' => 0,
            'first_photo' => null
        ];
    }
    $photoGroups[$albumId][] = $photo;
    $albums[$albumId]['count']++;
    
    // Prioritize cover photo as first_photo
    $isCover = $photo['is_album_cover'] ?? 0;
    if ($albums[$albumId]['first_photo'] === null || $isCover == 1) {
        $albums[$albumId]['first_photo'] = $photo;
    }
}

// Sort album groups by latest photo created_at DESC
uasort($albums, function($a, $b) {
    $dateA = !empty($a['first_photo']['created_at']) ? strtotime($a['first_photo']['created_at']) : 0;
    $dateB = !empty($b['first_photo']['created_at']) ? strtotime($b['first_photo']['created_at']) : 0;
    return $dateB - $dateA;
});

// Also sort photoGroups to match the sorted album order
$sortedPhotoGroups = [];
foreach ($albums as $albumId => $albumInfo) {
    if (isset($photoGroups[$albumId])) {
        $sortedPhotoGroups[$albumId] = $photoGroups[$albumId];
    }
}
$photoGroups = $sortedPhotoGroups;

// Generate CSRF token for forms
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Foto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #1e293b;
            --accent: #f59e0b;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #334155;
            --text-light: #64748b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--secondary) 0%, #0f172a 100%);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        @media (max-width: 991px) {
            .navbar > .container-fluid {
                flex-wrap: nowrap !important;
            }
            .navbar .d-flex.align-items-center {
                flex-wrap: nowrap !important;
                overflow: visible !important;
            }
            .navbar-nav-mobile {
                display: flex !important;
                flex-direction: row !important;
                margin-left: auto !important;
                padding: 0 !important;
                border: none !important;
            }
            .navbar .navbar-nav.ms-auto {
                display: none !important;
            }
            .navbar-nav-mobile .nav-link,
            .navbar-nav-mobile .btn-upload,
            .navbar-nav-mobile .user-info,
            .navbar-nav-mobile .btn {
                padding: 0.3rem 0.4rem !important;
                font-size: 0.9rem;
                color: rgba(255,255,255,0.85) !important;
                text-decoration: none;
            }
            .navbar-nav-mobile .nav-link:hover {
                color: white !important;
                background: rgba(255,255,255,0.1);
                border-radius: 4px;
            }
            .navbar-nav-mobile .user-info {
                color: white;
                padding: 0.3rem 0.4rem;
            }
            .navbar-brand {
                font-size: 0.95rem !important;
                white-space: nowrap;
            }
            .navbar-brand i {
                font-size: 1rem;
            }
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .navbar-brand i {
            color: var(--accent);
        }
        
        .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            transition: all 0.3s;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
        }
        
        .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.1);
        }
        
        .btn-upload {
            background: linear-gradient(135deg, var(--accent) 0%, #d97706 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }
        
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
            color: white;
        }
        
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: rgba(255,255,255,0.05);
            transform: rotate(15deg);
        }
        
        .hero h1 {
            color: white;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
        }
        
        .category-filter {
            padding: 1.5rem 0;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .category-btn {
            border: 2px solid var(--primary);
            background: transparent;
            color: var(--primary);
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            transition: all 0.3s;
            margin: 0.25rem;
        }
        
        .category-btn:hover, .category-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .gallery-grid {
            padding: 2rem 0;
            overflow-x: hidden;
            position: relative;
            z-index: 1;
        }
        
        .photo-card {
            background: var(--card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
            cursor: pointer;
            position: relative;
            z-index: 1;
        }
        
        .photo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .image-container {
            position: relative;
            padding-top: 75%;
            overflow: hidden;
        }
        
        .image-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .photo-card:hover .image-container img {
            transform: scale(1.1);
        }
        
        .photo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 50%);
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            align-items: flex-end;
            padding: 1rem;
        }
        
        .photo-card:hover .photo-overlay {
            opacity: 1;
        }
        
        .photo-info {
            padding: 0.75rem;
        }
        
        .photo-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: var(--secondary);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .photo-description {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .photo-meta {
            font-size: 0.7rem;
            color: var(--text-light);
        }
        
        .photo-author {
            font-size: 0.7rem;
            color: var(--primary);
            font-weight: 500;
        }
        
        .photo-date {
            font-size: 0.65rem;
            color: #94a3b8;
        }
        
        .badge-category {
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
        }
        
        .badge-private {
            background: #ef4444;
            color: white;
        }
        
        .badge-public {
            background: #22c55e;
            color: white;
        }
        
        /* Album card styles */
        .album-card {
            background: var(--card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .album-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .album-header {
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .album-title {
            font-weight: 600;
            margin: 0;
            font-size: 1rem;
        }
        
        .album-grid {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
        }
        
        .album-grid img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .album-card:hover .album-grid img {
            transform: scale(1.1);
        }
        
        .album-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .album-card:hover .album-overlay {
            opacity: 1;
        }
        
        .album-overlay span {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .album-footer {
            padding: 0.75rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        
        .add-album-card {
            background: #f0f7ff;
            border: 2px dashed var(--primary);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 250px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-album-card:hover {
            background: #e0edff;
            border-color: var(--primary-dark);
        }
        
        .add-album-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .add-album-card p {
            color: var(--primary);
            font-weight: 500;
            margin: 0;
        }
        
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--secondary) 0%, #0f172a 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        .file-upload-area {
            border: 2px dashed var(--primary);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f0f7ff;
        }
        
        .file-upload-area:hover {
            background: #e0edff;
            border-color: var(--primary-dark);
        }
        
        .file-upload-area i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .preview-images {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scroll-behavior: smooth;
        }
        
        .preview-images::-webkit-scrollbar {
            height: 6px;
        }
        
        .preview-images::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .preview-images::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .preview-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            flex-shrink: 0;
        }
        
        .preview-count {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox img {
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .lightbox-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
        }
        
        .toast {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        footer {
            background: var(--secondary);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 1.75rem;
            }
            
            .category-btn {
                font-size: 0.75rem;
                padding: 0.3rem 0.7rem;
                border-radius: 20px;
            }
            
            .photo-card {
                margin-bottom: 1rem;
            }
            
            .image-container {
                padding-top: 100%;
            }
            
            .badge-category {
                background: transparent;
                color: var(--primary);
                padding: 0;
                font-size: 0.65rem;
                font-weight: normal;
            }
            
            .photo-title {
                font-size: 0.75rem;
                font-weight: 500;
                line-height: 1.2;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                height: auto;
                min-height: 1.2em;
            }
            
            .photo-info {
                padding: 0.5rem;
            }
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-weight: 500;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal slider styles */
        #batchSliderModal .modal-body {
            padding: 0;
        }
        #batchSliderModal .photo-slider {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            overflow: hidden;
        }
        #batchSliderModal .photo-slider-track {
            display: flex;
            transition: transform 0.3s ease;
            height: 100%;
        }
        #batchSliderModal .photo-slider-track .slider-image-container {
            min-width: 100%;
            height: 100%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #batchSliderModal .photo-slider-track img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            flex-shrink: 0;
            cursor: pointer;
        }
        #batchSliderModal .slider-image-label {
            position: absolute;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            white-space: nowrap;
        }
        #batchSliderModal .photo-slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            z-index: 2;
        }
        #batchSliderModal .photo-slider-nav.prev { left: 16px; }
        #batchSliderModal .photo-slider-nav.next { right: 16px; }
        #batchSliderModal .photo-slider-dots {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 2;
        }
        #batchSliderModal .photo-slider-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.6);
            cursor: pointer;
        }
        #batchSliderModal .photo-slider-dot.active {
            background: white;
        }

        /* Stats page styles */
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 1rem;
        }
        .filter-box {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        /* Autocomplete styles */
        .autocomplete-dropdown {
            position: absolute;
            z-index: 1000;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            max-height: 350px;
            overflow-y: auto;
        }
        
        .autocomplete-group {
            border-bottom: 1px solid #e9ecef;
        }
        
        .autocomplete-group:last-child {
            border-bottom: none;
        }
        
        .autocomplete-group-title {
            padding: 8px 12px;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
        }
        
        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.15s;
        }
        
        .autocomplete-item:hover {
            background: #f0f4f8;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        /* Table responsive styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        #photosTable {
            min-width: 800px;
        }
        
        #photosTable th, #photosTable td {
            white-space: nowrap;
            vertical-align: middle;
        }
        
        #photosTable .photo-row:hover {
            background-color: #f8f9fa;
        }
        
        #photosTable .edit-input {
            min-width: 150px;
        }
        
        #usersTable {
            min-width: 600px;
        }
        
        #usersTable th, #usersTable td {
            white-space: nowrap;
            vertical-align: middle;
        }
        
        .badge-shared {
            background: #8b5cf6 !important;
            color: white;
        }
        
        .btn-action {
            padding: 0.15rem 0.4rem !important;
            font-size: 0.7rem !important;
            line-height: 1.4 !important;
            border-radius: 0.2rem !important;
        }
        
        .btn-action i {
            font-size: 0.7rem !important;
        }
        
        .btn-group-sm > .btn-action {
            padding: 0.1rem 0.3rem !important;
        }
        
        @media (max-width: 768px) {
            #photosTable, #myPhotosTable, #usersTable {
                font-size: 0.75rem;
            }
            
            #photosTable img, #myPhotosTable img {
                width: 30px !important;
                height: 30px !important;
            }
            
            #usersTable {
                font-size: 0.75rem;
            }
            
            .btn-group-sm .btn {
                padding: 0.1rem 0.25rem;
                font-size: 0.65rem;
            }
            
            .table td, .table th {
                padding: 0.3rem 0.4rem !important;
            }
            
            .btn-group-sm {
                gap: 1px;
            }
            
            .btn-group-sm .btn {
                min-width: 24px;
            }
            
            .table {
                margin-bottom: 0.5rem;
            }
            
            .table-responsive {
                border-radius: 8px;
                overflow: hidden;
            }
            
            .group-header {
                padding: 0.5rem !important;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php if ($message): ?>
    <div class="toast-container">
        <div class="toast show" role="alert">
            <div class="toast-header bg-<?= $messageType ?> text-white">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <strong class="me-auto">Notifikasi</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"><?= $message ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid px-2">
            <div class="d-flex align-items-center flex-grow-1">
                <a class="navbar-brand" href="?action=home">
                    <i class="fas fa-camera-retro me-1"></i>Galeri SMKN 6
                </a>
                <div class="navbar-nav-mobile d-lg-none d-flex flex-row align-items-center ms-auto">
			  <?php if (!isset($_SESSION['user_id'])): ?>
                    <a class="nav-link" href="?action=home" title="Beranda"><i class="fas fa-home"></i></a>
 			  <?php endif; ?>
                    <a class="nav-link" href="?action=gallery" title="Galeri"><i class="fas fa-images"></i></a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal" title="Login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="nav-link" href="?action=dashboard" title="Dashboard"><i class="fas fa-user-circle"></i></a>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
                    <a class="nav-link" href="?action=stats" title="Statistik"><i class="fas fa-chart-bar"></i></a>
                    <a class="nav-link" href="?action=photos" title="Semua Foto"><i class="fas fa-photo-video"></i></a>
                    <a class="nav-link" href="?action=users" title="Kelola Users"><i class="fas fa-users"></i></a>
                    <?php endif; ?>
                    <a class="nav-link" href="?action=myphotos" title="Foto Saya"><i class="fas fa-folder"></i></a>
                    <button class="btn btn-upload btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal" title="Upload Foto">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </button>
                    <div class="user-info" title="Profil: <?= htmlspecialchars($_SESSION['username']) ?>">
                        <i class="fas fa-user"></i>
                    </div>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <button type="submit" name="logout" class="btn btn-outline-light btn-sm" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="?action=home" title="Beranda"><i class="fas fa-home"></i></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?action=gallery" title="Galeri"><i class="fas fa-images"></i></a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="?action=dashboard" title="Dashboard"><i class="fas fa-user-circle"></i></a>
                    </li>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="?action=stats" title="Statistik"><i class="fas fa-chart-bar"></i></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?action=photos" title="Semua Foto"><i class="fas fa-photo-video"></i></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?action=users" title="Kelola Users"><i class="fas fa-users"></i></a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="?action=myphotos" title="Foto Saya"><i class="fas fa-folder"></i></a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item ms-2">
                        <button class="btn btn-upload" data-bs-toggle="modal" data-bs-target="#uploadModal" title="Upload Foto">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </button>
                    </li>
                    <li class="nav-item ms-2">
                        <div class="user-info" title="Profil: <?= htmlspecialchars($_SESSION['username']) ?>">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="d-none d-lg-inline"><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <span class="badge bg-warning text-dark ms-1">Admin</span>
                            <?php elseif ($_SESSION['role'] === 'superadmin'): ?>
                            <span class="badge bg-danger text-white ms-1">Superadmin</span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="nav-item ms-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <button type="submit" name="logout" class="btn btn-outline-light btn-sm" title="Logout">
                                <i class="fas fa-sign-out-alt"></i>
                            </button>
                        </form>
                    </li>
                    <?php else: ?>
                    <li class="nav-item ms-2">
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <?php if ($action === 'home'): ?>
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1>Galeri Sekolah </h1>
                    <p class="lead mb-4">Unggah, Berbagi dan Berkabar dengan Galeri Kegiatan SMKN 6 Kota Serang.</p>
                    <!--<button class="btn btn-upload btn-lg" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-cloud-upload-alt me-2"></i>Upload Gambar/Foto
                    </button>-->
                </div>
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="text-center">
                        <i class="fas fa-photo-film" style="font-size: 8rem; color: rgba(255,255,255,0.2);"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <?php if ($action === 'dashboard' && isset($_SESSION['user_id'])): ?>
    <section class="dashboard-section py-5" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); min-height: 80vh;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Saya</h2>
                    <p class="text-muted">Kelola foto dan aktivitas Anda</p>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                <i class="fas fa-images fa-2x text-primary"></i>
                            </div>
                            <h3 class="fw-bold"><?= $myPhotoCount ?></h3>
                            <p class="text-muted mb-0">Total Foto</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                <i class="fas fa-globe fa-2x text-success"></i>
                            </div>
                            <h3 class="fw-bold"><?= $myPublicCount ?></h3>
                            <p class="text-muted mb-0">Foto Publik</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                <i class="fas fa-lock fa-2x text-warning"></i>
                            </div>
                            <h3 class="fw-bold"><?= $myPrivateCount ?></h3>
                            <p class="text-muted mb-0">Foto Private</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Upload Foto Baru</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Foto (Unlimited)</label>
                                    <div class="file-upload-area" onclick="document.getElementById('dashPhotos').click()">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p class="mb-1">Klik untuk memilih foto</p>
                                        <small class="text-muted">JPG, PNG (Max 300KB setelah kompresi)</small>
                                    </div>
                                    <input type="file" name="photos[]" id="dashPhotos" accept="image/jpeg,image/png" multiple style="display:none" onchange="previewDashFiles()">
                                    <div class="preview-images" id="dashPreview"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kategori (bisa pilih lebih dari satu)</label>
                                    <div class="input-group">
                                        <select name="category[]" class="form-select" id="dashCategorySelect" multiple required size="3">
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Tekan Ctrl/Cmd + klik untuk pilih multiple kategori</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Judul</label>
                                    <input type="text" name="title" class="form-control" required placeholder="Judul foto">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi foto (opsional)"></textarea>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_private" class="form-check-input" id="dashPrivateCheck">
                                        <label class="form-check-label" for="dashPrivateCheck">
                                            <i class="fas fa-lock me-1"></i> Private (hanya saya yang dapat melihat)
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" name="upload" class="btn btn-primary w-100 py-2">
                                    <i class="fas fa-upload me-2"></i>Upload Foto
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-clock me-2 text-primary"></i>Foto Terbaru Saya</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php 
                            $dashPage = empty($_GET['dash_page']) ? 1 : max(1, intval($_GET['dash_page']));
                            $dashStart = ($dashPage - 1) * 7;
                            $dashPhotos = is_array($userPhotos) ? $userPhotos : array();
                            $dashShow = array_slice($dashPhotos, $dashStart, 7);
                            $dashTotal = count($dashPhotos);
                            $dashPages = max(1, ceil($dashTotal / 7));
                            ?>
                            <?php if ($dashTotal == 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-images fa-3x mb-3 text-secondary"></i>
                                <p>Anda belum memiliki foto</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($dashShow as $photo): ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars($photo['filepath']) ?>" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($photo['title']) ?></h6>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($photo['created_at'])) ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($dashPages > 1): ?>
                            <div class="card-footer bg-white border-0 py-2">
                                <div class="d-flex justify-content-center align-items-center gap-1 flex-wrap">
                                    <?php if ($dashPage > 1): ?>
                                    <a href="?action=dashboard&dash_page=<?= $dashPage - 1 ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
                                    <?php endif; ?>
                                    <?php for ($i = 1; $i <= $dashPages; $i++): ?>
                                    <a href="?action=dashboard&dash_page=<?= $i ?>" class="btn btn-sm <?= $i == $dashPage ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $i ?></a>
                                    <?php endfor; ?>
                                    <?php if ($dashPage < $dashPages): ?>
                                    <a href="?action=dashboard&dash_page=<?= $dashPage + 1 ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php elseif ($dashTotal > 7): ?>
                            <div class="card-footer bg-white border-0 text-center">
                                <a href="?action=myphotos" class="btn btn-outline-primary btn-sm">Lihat semua foto</a>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($action === 'photos' && isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
    <section class="py-5" style="background: #f8fafc; min-height: 80vh;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold"><i class="fas fa-photo-video me-2 text-primary"></i>Manajemen Foto</h2>
                        <p class="text-muted mb-0">Kelola semua foto di sistem</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success" id="multiEditBtn" disabled onclick="showMultiEditModal()">
                            <i class="fas fa-edit me-1"></i> Multi Edit
                        </button>
                        <button class="btn btn-danger" id="multiDeleteBtn" disabled onclick="multiDeletePhotos()">
                            <i class="fas fa-trash me-1"></i> Hapus Terpilih
                        </button>
                        <button class="btn btn-info" onclick="showEditHistoryModal()">
                            <i class="fas fa-history me-1"></i> Riwayat Edit
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="photosTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3" style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="selectAllPhotos" onchange="toggleSelectAllPhotos()">
                                    </th>
                                    <th class="px-3">Foto</th>
                                    <th>Judul</th>
                                    <th>User</th>
                                    <th>Kategori</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th class="text-end px-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($photos as $photo): 
                                    $photoCats = explode(',', $photo['category']);
                                ?>
                                <tr data-photo-id="<?= $photo['id'] ?>" class="photo-row">
                                    <td class="px-3">
                                        <input type="checkbox" class="form-check-input photo-checkbox" value="<?= $photo['id'] ?>" onchange="updateMultiButtons()">
                                    </td>
                                    <td class="px-3">
                                        <img src="<?= htmlspecialchars($photo['filepath']) ?>" style="width: 60px; height: 60px; object-fit: cover;" class="rounded">
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="title" data-id="<?= $photo['id'] ?>">
                                            <strong class="photo-title-display cursor-pointer" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'title')"><?= htmlspecialchars($photo['title']) ?></strong>
                                            <input type="text" class="form-control form-control-sm edit-input" value="<?= htmlspecialchars($photo['title']) ?>" style="display:none;" onblur="saveInlineEdit(this, 'title', <?= $photo['id'] ?>)" onkeydown="handleInlineEditKey(event, this, 'title', <?= $photo['id'] ?>)">
                                        </div>
                                        <div class="inline-edit mt-1" data-field="photo_label" data-id="<?= $photo['id'] ?>">
                                            <small class="text-success photo-label-display cursor-pointer" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'photo_label')">
                                                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($photo['photo_label'] ?: 'Tambah label...') ?>
                                            </small>
                                            <input type="text" class="form-control form-control-sm edit-input" placeholder="Label foto" value="<?= htmlspecialchars($photo['photo_label'] ?? '') ?>" style="display:none;" onblur="saveInlineEdit(this, 'photo_label', <?= $photo['id'] ?>)" onkeydown="handleInlineEditKey(event, this, 'photo_label', <?= $photo['id'] ?>)">
                                        </div>
                                        <div class="mt-1">
                                            <?php if (($photo['is_album_cover'] ?? 0) == 1): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-image me-1"></i> Cover</span>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <input type="hidden" name="set_cover" value="1">
                                                <button type="submit" name="toggle_cover" class="btn btn-sm btn-outline-<?= ($photo['is_album_cover'] ?? 0) == 1 ? 'warning' : 'secondary' ?>" title="<?= ($photo['is_album_cover'] ?? 0) == 1 ? 'Sudah cover' : 'Jadikan cover' ?>">
                                                    <i class="fas fa-image"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 150px;"><?= htmlspecialchars($photo['description'] ?: '-') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($photo['username']) ?></td>
                                    <td>
                                        <div class="inline-edit" data-field="category" data-id="<?= $photo['id'] ?>">
                                            <span class="photo-category-display cursor-pointer" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'category')">
                                            <?php 
                                            $catNames = [];
                                            foreach ($photoCats as $catId) {
                                                foreach ($categories as $cat) {
                                                    if ($cat['id'] == $catId) {
                                                        $catNames[] = $cat['name'];
                                                        break;
                                                    }
                                                }
                                            }
                                            echo htmlspecialchars(implode(', ', $catNames) ?: 'Lainnya');
                                            ?>
                                            </span>
                                            <select class="form-select form-select-sm edit-input" style="display:none; min-width: 120px;" onchange="handleMyCategorySelect(this, 'category', <?= $photo['id'] ?>)">
                                                <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= in_array($cat['id'], $photoCats) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge cursor-pointer <?= $photo['is_private'] == 1 ? 'bg-danger' : 'bg-success' ?>" 
                                              onclick="event.stopPropagation(); togglePhotoStatus(<?= $photo['id'] ?>, <?= $photo['is_private'] ?>)" 
                                              title="Klik untuk ubah status">
                                            <?php if ($photo['is_private'] == 1): ?><i class="fas fa-lock"></i> Private<?php else: ?><i class="fas fa-globe"></i> Public<?php endif; ?>
                                        </span>
                                    </td>
                                    <td><small><?= date('d/m/Y', strtotime($photo['created_at'])) ?></small></td>
                                    <td class="text-end px-2">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Aksi foto">
                                            <span class="input-group-text px-1 py-0 bg-light border" style="font-size: 10px;">
                                                <?= !empty($photo['album_sort_order']) ? $photo['album_sort_order'] : '0' ?>
                                            </span>
                                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" title="Pindah ke atas" onclick="reorderPhoto(<?= $photo['id'] ?>, 'up')">
                                                <i class="fas fa-arrow-up fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" title="Pindah ke bawah" onclick="reorderPhoto(<?= $photo['id'] ?>, 'down')">
                                                <i class="fas fa-arrow-down fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit" onclick="toggleInlineEdit(<?= $photo['id'] ?>)">
                                                <i class="fas fa-edit fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info py-1 px-2" title="Share ke user" onclick="sharePhoto(<?= $photo['id'] ?>)">
                                                <i class="fas fa-share-alt fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success py-1 px-2" title="Tambah ke album" onclick="addToGroup(<?= $photo['id'] ?>)">
                                                <i class="fas fa-folder-plus fa-xs"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus foto ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <button type="submit" name="delete_photo" class="btn btn-sm btn-outline-danger py-1 px-2" title="Hapus">
                                                    <i class="fas fa-trash fa-xs"></i>
                                                </button>
                                            </form>
                                            <?php 
                                            $photoBatchId = $photo['batch_id'] ?? $photo['id'];
                                            $batchPhotos = array_filter($photos, function($p) use ($photoBatchId) {
                                                return ($p['batch_id'] ?? $p['id']) == $photoBatchId;
                                            });
                                            $isFirstInBatch = $photo['id'] == reset($batchPhotos)['id'];
                                            ?>
                                            <div class="inline-edit mt-1" data-field="album_id" data-id="<?= $photo['id'] ?>">
                                                <small class="text-primary photo-album-display" onclick="toggleAlbumEdit(<?= $photo['id'] ?>)" style="cursor:pointer;">
                                                    <i class="fas fa-folder me-1"></i>
                                                    <?php 
                                                    $userAlbums = getUserAlbums(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
                                                    $currentAlbumId = $photo['album_id'] ?? $photo['batch_id'];
                                                    $currentAlbumTitle = $photo['album_title'] ?? 'Tanpa Album';
                                                    echo htmlspecialchars($currentAlbumTitle);
                                                    ?>
                                                </small>
                                                <select class="form-select form-select-sm edit-input" style="display:none; width: auto;" onchange="handleAlbumSelect(this, 'album_id', <?= $photo['id'] ?>)">
                                                    <option value="">-- Pindahkan ke Album --</option>
                                                    <option value="new_album" data-is-new="1" style="font-weight:bold; color:#2563eb;">+ Buat Album Baru</option>
                                                    <?php foreach ($userAlbums as $album): ?>
                                                    <option value="<?= htmlspecialchars($album['album_id']) ?>" <?= ($currentAlbumId == $album['album_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($album['album_title'] ?: 'Album ' . $album['album_id']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" class="form-control form-control-sm edit-input" placeholder="Ketik nama album..." style="display:none; width: 150px;" onkeydown="handleNewAlbumKey(event, this, 'album_id', <?= $photo['id'] ?>)" onblur="saveNewAlbum(this, 'album_id', <?= $photo['id'] ?>)">
                                            </div>
                                            <?php if ($isFirstInBatch && count($batchPhotos) > 1): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus semua <?= count($batchPhotos) ?> foto dalam grup ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <input type="hidden" name="delete_batch" value="1">
                                                <button type="submit" name="delete_photo" class="btn btn-outline-danger" title="Hapus grup">
                                                    <i class="fas fa-trash-alt"></i> <?= count($batchPhotos) ?>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus foto ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <button type="submit" name="delete_photo" class="btn btn-outline-danger" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Multi Edit Modal for Admin -->
    <div class="modal fade" id="multiEditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Multi Edit Foto ( <span id="multiEditCount">0</span> foto )</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="multiEditForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="photo_ids" id="multiPhotoIds" value="">
                        <div class="mb-3">
                            <label class="form-label">Judul ( kosongkan jika tidak diubah )</label>
                            <input type="text" name="title" class="form-control" placeholder="Judul baru untuk semua">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label Foto ( kosongkan jika tidak diubah )</label>
                            <input type="text" name="photo_label" class="form-control" placeholder="Label foto baru untuk semua">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi ( kosongkan jika tidak diubah )</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi baru"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_private" class="form-select">
                                <option value="">-- Tidak diubah --</option>
                                <option value="0">Public</option>
                                <option value="1">Private</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-grow-1" onclick="executeMultiEdit()">
                                <i class="fas fa-save me-2"></i>Simpan Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- My Photos Page - for regular users to manage their own photos -->
    <?php if ($action === 'myphotos' && isset($_SESSION['user_id'])): ?>
    <?php 
    $myPhotos = array_filter($photos, function($p) {
        return $p['user_id'] == $_SESSION['user_id'];
    });
    
    // Sort by album_sort_order DESC, then created_at DESC
    usort($myPhotos, function($a, $b) {
        $sortA = !empty($a['album_sort_order']) ? $a['album_sort_order'] : 0;
        $sortB = !empty($b['album_sort_order']) ? $b['album_sort_order'] : 0;
        if ($sortA != $sortB) {
            return $sortB - $sortA;
        }
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Group photos by batch_id for reorder buttons
    $myPhotoBatches = [];
    foreach ($myPhotos as $p) {
        $batchId = $p['batch_id'] ?? 'single_' . $p['id'];
        if (!isset($myPhotoBatches[$batchId])) {
            $myPhotoBatches[$batchId] = [];
        }
        $myPhotoBatches[$batchId][] = $p;
    }
    
    // Group photos by album for grouped view
    $groupedPhotos = [];
    foreach ($myPhotos as $p) {
        $groupKey = !empty($p['album_id']) ? $p['album_id'] : ($p['batch_id'] ?? 'single_' . $p['id']);
        if (!isset($groupedPhotos[$groupKey])) {
            $groupedPhotos[$groupKey] = [
                'title' => $p['album_title'] ?: $p['title'],
                'album_id' => $p['album_id'] ?? $groupKey,
                'photos' => []
            ];
        }
        $groupedPhotos[$groupKey]['photos'][] = $p;
    }

    // Sort photos within each group
    foreach ($groupedPhotos as $groupKey => $group) {
        usort($groupedPhotos[$groupKey]['photos'], function($a, $b) {
            $sortA = !empty($a['album_sort_order']) ? $a['album_sort_order'] : 0;
            $sortB = !empty($b['album_sort_order']) ? $b['album_sort_order'] : 0;
            if ($sortA != $sortB) {
                return $sortB - $sortA;
            }
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }

    $expandedGroupsState = $_COOKIE['expanded_groups'] ?? '';
    ?>
    <section class="py-5" style="background: #f8fafc; min-height: 80vh;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold"><i class="fas fa-images me-2 text-primary"></i>Foto Saya</h2>
                        <p class="text-muted mb-0">Kelola foto dan grup Anda</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (count($myPhotos) > 0): ?>
                        <div class="input-group" style="min-width: 250px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" placeholder="Cari album, label, atau judul..." id="myPhotosSearch" autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" onclick="clearMyPhotosSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <button class="btn btn-success" id="myMultiEditBtn" disabled onclick="showMyMultiEditModal()">
                            <i class="fas fa-edit me-1"></i> Multi Edit
                        </button>
                        <button class="btn btn-danger" id="myMultiDeleteBtn" disabled onclick="myMultiDeletePhotos()">
                            <i class="fas fa-trash me-1"></i> Hapus Terpilih
                        </button>
                        <button class="btn btn-outline-primary" id="toggleViewModeBtn" onclick="togglePhotoViewMode()">
                            <i class="fas fa-expand-arrows-alt me-1"></i> <span id="viewModeText">Grouped</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (empty($myPhotos)): ?>
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center py-5">
                    <i class="fas fa-images fa-4x text-secondary mb-3"></i>
                    <h5>Belum ada foto</h5>
                    <p class="text-muted">Upload foto pertama Anda sekarang!</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-cloud-upload-alt me-2"></i>Upload Foto
                    </button>
                </div>
            </div>
            <?php else: ?>
            <?php 
            // Group photos by album/batch for toggle view - use paginated photos
            $groupedPhotos = [];
            foreach ($myPhotos as $p) {
                $groupKey = !empty($p['album_id']) ? $p['album_id'] : ($p['batch_id'] ?? 'single_' . $p['id']);
                if (!isset($groupedPhotos[$groupKey])) {
                    $groupedPhotos[$groupKey] = [
                        'title' => $p['album_title'] ?: $p['title'],
                        'album_id' => $p['album_id'] ?? $groupKey,
                        'photos' => []
                    ];
                }
                $groupedPhotos[$groupKey]['photos'][] = $p;
            }
            // Sort photos within each group by album_sort_order DESC, created_at DESC
            foreach ($groupedPhotos as $groupKey => $group) {
                usort($groupedPhotos[$groupKey]['photos'], function($a, $b) {
                    $sortA = !empty($a['album_sort_order']) ? $a['album_sort_order'] : 0;
                    $sortB = !empty($b['album_sort_order']) ? $b['album_sort_order'] : 0;
                    if ($sortA != $sortB) return $sortB - $sortA;
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            }
            // Sort groups by latest photo date (newest first)
            uasort($groupedPhotos, function($a, $b) {
                $latestA = !empty($a['photos'][0]) ? strtotime($a['photos'][0]['created_at']) : 0;
                $latestB = !empty($b['photos'][0]) ? strtotime($b['photos'][0]['created_at']) : 0;
                return $latestB - $latestA;
            });
            ?>
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-0">
                    <div id="groupedView">
                        <?php foreach ($groupedPhotos as $groupKey => $group): ?>
                        <div class="group-header bg-light border-bottom px-3 py-2 d-flex align-items-center justify-content-between cursor-pointer" onclick="toggleGroup('<?= $groupKey ?>')">
                            <div class="d-flex align-items-center flex-grow-1" onclick="event.stopPropagation();">
                                <i class="fas <?= in_array($groupKey, $expandedGroupsState) ? 'fa-chevron-down' : 'fa-chevron-right' ?> me-2 group-toggle-icon cursor-pointer" id="icon-<?= $groupKey ?>" onclick="toggleGroup('<?= $groupKey ?>')"></i>
                                <i class="fas fa-folder text-warning me-2"></i>
                                <span class="group-title-display" id="gtitle-<?= $groupKey ?>">
                                    <strong class="cursor-pointer" onclick="event.stopPropagation(); editGroupTitle('<?= $groupKey ?>')"><?= htmlspecialchars($group['title']) ?></strong>
                                </span>
                                <input type="text" class="form-control form-control-sm w-50 d-none" id="gedit-<?= $groupKey ?>" value="<?= htmlspecialchars($group['title']) ?>" onblur="saveGroupTitle('<?= $groupKey ?>', '<?= htmlspecialchars($group['album_id']) ?>')" onkeydown="if(event.key==='Enter')saveGroupTitle('<?= $groupKey ?>', '<?= htmlspecialchars($group['album_id']) ?>')" onclick="event.stopPropagation();">
                                <span class="badge bg-secondary ms-2"><?= count($group['photos']) ?> foto</span>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-info" title="Bagikan Album ke User" onclick="event.stopPropagation(); shareAlbum('<?= $groupKey ?>', '<?= htmlspecialchars($group['title']) ?>')">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" title="Edit Nama Album" onclick="event.stopPropagation(); editGroupTitle('<?= $groupKey ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" title="Hapus Album" onclick="event.stopPropagation(); deleteGroup('<?= $groupKey ?>', <?= count($group['photos']) ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <table class="table table-hover mb-0 group-table" id="group-<?= $groupKey ?>" style="display: <?= in_array($groupKey, $expandedGroupsState) ? 'table' : 'none' ?>;">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-2" style="width: 35px;"><input type="checkbox" class="form-check-input" onchange="toggleSelectAllInGroup('<?= $groupKey ?>', this)"></th>
                                    <th class="px-2" style="width: 50px;">Foto</th>
                                    <th>Judul</th>
                                    <th>Status</th>
                                    <th>Kategori</th>
                                    <th>Tanggal</th>
                                    <th class="px-2">Cover</th>
                                    <th class="px-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $groupPhotos = $group['photos'];
                                foreach ($groupPhotos as $idx => $photo): $photoCats = explode(',', $photo['category']); 
                                $isFirst = $idx === 0;
                                $isLast = $idx === count($groupPhotos) - 1;
                                ?>
                                <tr data-photo-id="<?= $photo['id'] ?>">
                                    <td class="px-3"><input type="checkbox" class="form-check-input my-photo-checkbox group-checkbox-<?= $groupKey ?>" value="<?= $photo['id'] ?>" onchange="updateMyMultiButtons()"></td>
                                    <td class="px-3"><img src="<?= htmlspecialchars($photo['filepath']) ?>" style="width: 60px; height: 60px; object-fit: cover;" class="rounded"></td>
                                    <td>
                                        <div class="inline-edit" data-field="title" data-id="<?= $photo['id'] ?>">
                                            <strong class="photo-title-display cursor-pointer" onclick="event.stopPropagation(); toggleMyInlineEditField(<?= $photo['id'] ?>, 'title')"><?= htmlspecialchars($photo['title']) ?></strong>
                                            <input type="text" class="form-control form-control-sm edit-input" value="<?= htmlspecialchars($photo['title']) ?>" style="display:none;" onblur="saveMyInlineEditField(this, 'title', <?= $photo['id'] ?>)" onkeydown="handleMyInlineEditKeyField(event, this, 'title', <?= $photo['id'] ?>)">
                                        </div>
                                        <div class="inline-edit mt-1" data-field="photo_label" data-id="<?= $photo['id'] ?>">
                                            <small class="text-success photo-label-display cursor-pointer" onclick="event.stopPropagation(); toggleMyInlineEditField(<?= $photo['id'] ?>, 'photo_label')">
                                                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($photo['photo_label'] ?: 'Tambah label...') ?>
                                            </small>
                                            <input type="text" class="form-control form-control-sm edit-input" placeholder="Label foto" value="<?= htmlspecialchars($photo['photo_label'] ?? '') ?>" style="display:none;" onblur="saveMyInlineEditField(this, 'photo_label', <?= $photo['id'] ?>)" onkeydown="handleMyInlineEditKeyField(event, this, 'photo_label', <?= $photo['id'] ?>)">
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($photo['description'] ?: '-') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge cursor-pointer <?= $photo['is_private'] == 1 ? 'bg-danger' : 'bg-success' ?>" 
                                              onclick="event.stopPropagation(); togglePhotoStatus(<?= $photo['id'] ?>, <?= $photo['is_private'] ?>)" 
                                              title="Klik untuk ubah status">
                                            <?php if ($photo['is_private'] == 1): ?><i class="fas fa-lock"></i> Private<?php else: ?><i class="fas fa-globe"></i> Public<?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="category" data-id="<?= $photo['id'] ?>">
                                            <span class="photo-category-display cursor-pointer small" onclick="event.stopPropagation(); toggleMyInlineEditField(<?= $photo['id'] ?>, 'category')">
                                                <?php 
                                                $photoCats = explode(',', $photo['category']);
                                                if (empty($photoCats[0]) || trim($photoCats[0]) === ''): ?>
                                                <span class="text-muted small">Klik untuk pilih...</span>
                                                <?php else:
                                                foreach ($photoCats as $catValue): 
                                                    $catValue = trim($catValue);
                                                    if (!empty($catValue)):
                                                        $displayName = $catValue;
                                                        $color = 'secondary';
                                                        if (is_numeric($catValue)) {
                                                            foreach ($categories as $cat) {
                                                                if ($cat['id'] == $catValue) {
                                                                    $displayName = $cat['name'];
                                                                    $color = !empty($cat['color']) ? $cat['color'] : 'secondary';
                                                                    break;
                                                                }
                                                            }
                                                        } else {
                                                            foreach ($categories as $cat) {
                                                                if ($cat['name'] === $catValue) {
                                                                    $color = !empty($cat['color']) ? $cat['color'] : 'secondary';
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                ?>
                                                <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($displayName) ?></span>
                                                <?php endif; endforeach; 
                                                endif; ?>
                                            </span>
                                            <select class="form-select form-select-sm edit-input" style="display:none; min-width: 120px;" onchange="handleMyCategorySelect(this, 'category', <?= $photo['id'] ?>)">
                                                <option value="">-- Pilih Kategori --</option>
                                                <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= in_array((string)$cat['id'], $photoCats) || in_array($cat['name'], $photoCats) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="created_at" data-id="<?= $photo['id'] ?>">
                                            <span class="photo-date-display cursor-pointer small text-muted" onclick="event.stopPropagation(); toggleMyInlineEditField(<?= $photo['id'] ?>, 'created_at')">
                                                <?= date('d/m/Y', strtotime($photo['created_at'])) ?>
                                            </span>
                                            <input type="date" class="form-control form-control-sm edit-input" value="<?= date('Y-m-d', strtotime($photo['created_at'])) ?>" style="display:none;" onblur="saveMyInlineEditField(this, 'created_at', <?= $photo['id'] ?>)" onkeydown="handleMyInlineEditKeyField(event, this, 'created_at', <?= $photo['id'] ?>)">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if (($photo['is_album_cover'] ?? 0) == 1): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-image"></i></span>
                                        <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                            <input type="hidden" name="set_cover" value="1">
                                            <button type="submit" name="toggle_cover" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Jadikan cover album">
                                                <i class="far fa-image fa-xs"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end px-2">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Aksi foto">
                                            <span class="input-group-text px-1 py-0 bg-light border" style="font-size: 10px;">
                                                <?= !empty($photo['album_sort_order']) ? $photo['album_sort_order'] : '0' ?>
                                            </span>
                                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" title="Pindah ke atas" onclick="reorderPhoto(<?= $photo['id'] ?>, 'up')">
                                                <i class="fas fa-arrow-up fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" title="Pindah ke bawah" onclick="reorderPhoto(<?= $photo['id'] ?>, 'down')">
                                                <i class="fas fa-arrow-down fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit" onclick="toggleMyInlineEdit(<?= $photo['id'] ?>)">
                                                <i class="fas fa-edit fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info py-1 px-2" title="Share ke user" onclick="sharePhoto(<?= $photo['id'] ?>)">
                                                <i class="fas fa-share-alt fa-xs"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus foto ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <button type="submit" name="delete_photo" class="btn btn-sm btn-outline-danger py-1 px-2" title="Hapus">
                                                    <i class="fas fa-trash fa-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="flatView" style="display: none;">
                        <table class="table table-hover mb-0" id="myPhotosTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-2" style="width: 35px;">
                                        <input type="checkbox" class="form-check-input" id="selectAllMyPhotos" onchange="toggleSelectAllMyPhotos()">
                                    </th>
                                    <th class="px-2" style="width: 50px;">Foto</th>
                                    <th>Judul</th>
                                    <th>Album</th>
                                    <th>Status</th>
                                    <th>Kategori</th>
                                    <th>Tanggal</th>
                                    <th class="px-2">Cover</th>
                                    <th class="px-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myPhotos as $photo): 
                                    $photoCats = explode(',', $photo['category']);
                                ?>
                                <tr data-photo-id="<?= $photo['id'] ?>" class="photo-row">
                                    <td class="px-3">
                                        <input type="checkbox" class="form-check-input my-photo-checkbox" value="<?= $photo['id'] ?>" onchange="updateMyMultiButtons()">
                                    </td>
                                    <td class="px-3">
                                        <img src="<?= htmlspecialchars($photo['filepath']) ?>" style="width: 60px; height: 60px; object-fit: cover;" class="rounded">
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="title" data-id="<?= $photo['id'] ?>">
                                            <strong class="photo-title-display cursor-pointer" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'title')"><?= htmlspecialchars($photo['title']) ?></strong>
                                            <input type="text" class="form-control form-control-sm edit-input" value="<?= htmlspecialchars($photo['title']) ?>" style="display:none;" onblur="saveMyInlineEdit(this, 'title', <?= $photo['id'] ?>)" onkeydown="handleMyInlineEditKey(event, this, 'title', <?= $photo['id'] ?>)">
                                        </div>
                                        <div class="inline-edit mt-1" data-field="photo_label" data-id="<?= $photo['id'] ?>">
                                            <small class="text-success photo-label-display cursor-pointer" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'photo_label')">
                                                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($photo['photo_label'] ?: 'Tambah label...') ?>
                                            </small>
                                            <input type="text" class="form-control form-control-sm edit-input" placeholder="Label foto" value="<?= htmlspecialchars($photo['photo_label'] ?? '') ?>" style="display:none;" onblur="saveMyInlineEdit(this, 'photo_label', <?= $photo['id'] ?>)" onkeydown="handleMyInlineEditKey(event, this, 'photo_label', <?= $photo['id'] ?>)">
                                        </div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 150px;"><?= htmlspecialchars($photo['description'] ?: '-') ?></small>
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="album_id" data-id="<?= $photo['id'] ?>">
                                            <small class="text-primary photo-album-display" onclick="toggleMyAlbumEdit(<?= $photo['id'] ?>)" style="cursor:pointer;">
                                                <i class="fas fa-folder me-1"></i>
                                                <?php 
                                                $userMyAlbums = getUserAlbums(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
                                                $currentMyAlbumId = $photo['album_id'] ?? $photo['batch_id'];
                                                $currentMyAlbumTitle = $photo['album_title'] ?? 'Tanpa Album';
                                                echo htmlspecialchars($currentMyAlbumTitle);
                                                ?>
                                            </small>
                                            <select class="form-select form-select-sm edit-input" style="display:none; width: auto;" onchange="handleMyAlbumSelect(this, 'album_id', <?= $photo['id'] ?>)">
                                                <option value="">-- Pindahkan ke Album --</option>
                                                <option value="new_album" data-is-new="1" style="font-weight:bold; color:#2563eb;">+ Buat Album Baru</option>
                                                <?php foreach ($userMyAlbums as $album): ?>
                                                <option value="<?= htmlspecialchars($album['album_id']) ?>" <?= ($currentMyAlbumId == $album['album_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($album['album_title'] ?: 'Album ' . $album['album_id']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control form-control-sm edit-input" placeholder="Ketik nama album..." style="display:none; width: 150px;" onkeydown="handleNewMyAlbumKey(event, this, 'album_id', <?= $photo['id'] ?>)" onblur="saveNewMyAlbum(this, 'album_id', <?= $photo['id'] ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge cursor-pointer <?= $photo['is_private'] == 1 ? 'bg-danger' : 'bg-success' ?>" 
                                              onclick="togglePhotoStatus(<?= $photo['id'] ?>, <?= $photo['is_private'] ?>)" 
                                              title="Klik untuk ubah status">
                                            <?php if ($photo['is_private'] == 1): ?><i class="fas fa-lock"></i> Private<?php else: ?><i class="fas fa-globe"></i> Public<?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="category" data-id="<?= $photo['id'] ?>">
                                            <span class="photo-category-display cursor-pointer small" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'category')">
                                                <?php 
                                                $photoCats = explode(',', $photo['category']);
                                                if (empty($photoCats[0]) || trim($photoCats[0]) === ''): ?>
                                                <span class="text-muted small">Klik untuk pilih...</span>
                                                <?php else:
                                                foreach ($photoCats as $catValue): 
                                                    $catValue = trim($catValue);
                                                    if (!empty($catValue)):
                                                        $displayName = $catValue;
                                                        $color = 'secondary';
                                                        if (is_numeric($catValue)) {
                                                            foreach ($categories as $cat) {
                                                                if ($cat['id'] == $catValue) {
                                                                    $displayName = $cat['name'];
                                                                    $color = !empty($cat['color']) ? $cat['color'] : 'secondary';
                                                                    break;
                                                                }
                                                            }
                                                        } else {
                                                            foreach ($categories as $cat) {
                                                                if ($cat['name'] === $catValue) {
                                                                    $color = !empty($cat['color']) ? $cat['color'] : 'secondary';
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                ?>
                                                <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($displayName) ?></span>
                                                <?php endif; endforeach; 
                                                endif; ?>
                                            </span>
                                            <select class="form-select form-select-sm edit-input" style="display:none; min-width: 120px;" onchange="handleMyCategorySelect(this, 'category', <?= $photo['id'] ?>)">
                                                <option value="">-- Pilih Kategori --</option>
                                                <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= in_array((string)$cat['id'], $photoCats) || in_array($cat['name'], $photoCats) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="inline-edit" data-field="created_at" data-id="<?= $photo['id'] ?>">
                                            <span class="photo-date-display cursor-pointer small text-muted" onclick="toggleMyInlineEditField(<?= $photo['id'] ?>, 'created_at')">
                                                <?= date('d/m/Y', strtotime($photo['created_at'])) ?>
                                            </span>
                                            <input type="date" class="form-control form-control-sm edit-input" value="<?= date('Y-m-d', strtotime($photo['created_at'])) ?>" style="display:none;" onblur="saveMyInlineEditField(this, 'created_at', <?= $photo['id'] ?>)" onkeydown="handleMyInlineEditKey(event, this, 'created_at', <?= $photo['id'] ?>)">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if (($photo['is_album_cover'] ?? 0) == 1): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-image"></i></span>
                                        <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                            <input type="hidden" name="set_cover" value="1">
                                            <button type="submit" name="toggle_cover" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Jadikan cover album">
                                                <i class="far fa-image fa-xs"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end px-2">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Aksi foto">
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit" onclick="toggleMyInlineEdit(<?= $photo['id'] ?>)">
                                                <i class="fas fa-edit fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info py-1 px-2" title="Share ke user" onclick="sharePhoto(<?= $photo['id'] ?>)">
                                                <i class="fas fa-share-alt fa-xs"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success py-1 px-2" title="Tambah ke album" onclick="addToMyGroup(<?= $photo['id'] ?>)">
                                                <i class="fas fa-folder-plus fa-xs"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus foto ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <button type="submit" name="delete_photo" class="btn btn-sm btn-outline-danger py-1 px-2" title="Hapus">
                                                    <i class="fas fa-trash fa-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- My Multi Edit Modal -->
    <div class="modal fade" id="myMultiEditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Multi Edit Foto Saya ( <span id="myMultiEditCount">0</span> foto )</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="myMultiEditForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="photo_ids" id="myMultiPhotoIds" value="">
                        <div class="mb-3">
                            <label class="form-label">Judul ( kosongkan jika tidak diubah )</label>
                            <input type="text" name="title" class="form-control" placeholder="Judul baru untuk semua">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label Foto ( kosongkan jika tidak diubah )</label>
                            <input type="text" name="photo_label" class="form-control" placeholder="Label foto baru untuk semua">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi ( kosongkan jika tidak diubah )</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi baru"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_private" class="form-select">
                                <option value="">-- Tidak diubah --</option>
                                <option value="0">Public</option>
                                <option value="1">Private</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-grow-1" onclick="executeMyMultiEdit()">
                                <i class="fas fa-save me-2"></i>Simpan Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add to Group Modal (for Admin) -->
    <div class="modal fade" id="addToGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Foto ke Album</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="addToGroupForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="target_batch_id" id="targetBatchId" value="">
                        <div class="mb-3">
                            <label class="form-label">Pilih Album Tujuan</label>
                            <select name="target_group" class="form-select" id="targetGroupSelect" required>
                                <option value="">-- Pilih Album --</option>
                                <?php foreach ($photoGroups as $albumId => $groupPhotos): ?>
                                <?php $firstPhoto = $groupPhotos[0]; ?>
                                <option value="<?= htmlspecialchars($albumId) ?>"><?= htmlspecialchars($firstPhoto['album_title'] ?: $firstPhoto['title']) ?> (<?= count($groupPhotos) ?> foto)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label Foto <small class="text-muted">(keterangan singkat per foto)</small></label>
                            <input type="text" name="photo_label" class="form-control" placeholder="Contoh: Foto depan, Bagian dalam, dll">
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_album_cover" class="form-check-input" id="addGroupCoverCheck">
                                <label class="form-check-label" for="addGroupCoverCheck">
                                    <i class="fas fa-image me-1"></i> Jadikan Cover Album
                                </label>
                            </div>
                            <small class="text-muted">Foto ini akan menjadi tampilan utama album</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tambah Foto (Unlimited)</label>
                            <div class="file-upload-area" onclick="document.getElementById('addToGroupPhotos').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p class="mb-1" id="addToGroupText">Klik untuk memilih foto</p>
                                <small class="text-muted">JPG, PNG</small>
                            </div>
                            <input type="file" name="photos[]" id="addToGroupPhotos" accept="image/jpeg,image/png" multiple style="display:none" onchange="previewAddToGroupFiles()">
                            <div class="preview-images" id="addToGroupPreview"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="add_to_group" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-plus me-2"></i>Tambah ke Album
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add to My Group Modal -->
    <div class="modal fade" id="addToMyGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Foto ke Grup Saya</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="addToMyGroupForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="target_batch_id" id="myTargetBatchId" value="">
                            <div class="mb-3">
                            <label class="form-label">Pilih Album Tujuan</label>
                            <select name="target_group" class="form-select" id="myTargetGroupSelect" required>
                                <option value="">-- Pilih Album --</option>
                                <?php 
                                $myPhotoGroups = [];
                                foreach ($myPhotos as $p) {
                                    $albumId = !empty($p['album_id']) ? $p['album_id'] : ($p['batch_id'] ?? 'batch_' . $p['id']);
                                    if (!isset($myPhotoGroups[$albumId])) {
                                        $myPhotoGroups[$albumId] = [];
                                    }
                                    $myPhotoGroups[$albumId][] = $p;
                                }
                                // Sort by created_at DESC (newest first)
                                uasort($myPhotoGroups, function($a, $b) {
                                    $latestA = !empty($a[0]) ? strtotime($a[0]['created_at']) : 0;
                                    $latestB = !empty($b[0]) ? strtotime($b[0]['created_at']) : 0;
                                    return $latestB - $latestA;
                                });
                                ?>
                                <?php foreach ($myPhotoGroups as $albumId => $groupPhotos): ?>
                                <?php $firstPhoto = $groupPhotos[0]; ?>
                                <option value="<?= htmlspecialchars($albumId) ?>"><?= htmlspecialchars($firstPhoto['album_title'] ?: $firstPhoto['title']) ?> (<?= count($groupPhotos) ?> foto)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label Foto <small class="text-muted">(keterangan singkat per foto)</small></label>
                            <input type="text" name="photo_label" class="form-control" placeholder="Contoh: Foto depan, Bagian dalam, dll">
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_album_cover" class="form-check-input" id="addMyGroupCoverCheck">
                                <label class="form-check-label" for="addMyGroupCoverCheck">
                                    <i class="fas fa-image me-1"></i> Jadikan Cover Album
                                </label>
                            </div>
                            <small class="text-muted">Foto ini akan menjadi tampilan utama album</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tambah Foto (Unlimited)</label>
                            <div class="file-upload-area" onclick="document.getElementById('addToMyGroupPhotos').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p class="mb-1" id="addToMyGroupText">Klik untuk memilih foto</p>
                                <small class="text-muted">JPG, PNG</small>
                            </div>
                            <input type="file" name="photos[]" id="addToMyGroupPhotos" accept="image/jpeg,image/png" multiple style="display:none" onchange="previewAddToMyGroupFiles()">
                            <div class="preview-images" id="addToMyGroupPreview"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="add_to_group" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-plus me-2"></i>Tambah ke Grup
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Photo Modal -->
    <div class="modal fade" id="sharePhotoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-share-alt me-2"></i>Bagikan Foto ke User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sharePhotoId" value="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cari User</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="shareUserSearch" placeholder="Ketik nama user..." autocomplete="off">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pilih User (bisa pilih lebih dari satu)</label>
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;" id="shareUsersListContainer">
                                    <div class="text-muted text-center py-3">Memuat...</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary w-100" onclick="addSelectedShareUsers()">
                                <i class="fas fa-plus me-2"></i>Tambah User Terpilih
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User yang sudah diundang:</label>
                            <div id="currentSharedUsersList" class="list-group" style="max-height: 300px; overflow-y: auto;">
                                <div class="text-muted text-center py-3">Memuat...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Album Modal -->
    <div class="modal fade" id="shareAlbumModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-share-alt me-2"></i>Bagikan Album ke User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="shareAlbumId" value="">
                    <p class="text-muted">Album: <strong id="shareAlbumName"></strong></p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cari User</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="shareAlbumUserSearch" placeholder="Ketik nama user..." autocomplete="off">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pilih User (bisa pilih lebih dari satu)</label>
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;" id="shareAlbumUsersListContainer">
                                    <div class="text-muted text-center py-3">Memuat...</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary w-100" onclick="addSelectedAlbumShareUsers()">
                                <i class="fas fa-plus me-2"></i>Tambah User Terpilih
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User yang sudah diundang:</label>
                            <div id="currentAlbumSharedUsersList" class="list-group" style="max-height: 300px; overflow-y: auto;">
                                <div class="text-muted text-center py-3">Memuat...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($action === 'users' && isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
    <?php $allUsers = getUsers(); ?>
    <section class="py-5" style="background: #f8fafc; min-height: 80vh;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold"><i class="fas fa-users me-2 text-primary"></i>Manajemen User</h2>
                        <p class="text-muted mb-0">Kelola semua user di sistem</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-danger" id="multiDeleteUsersBtn" disabled onclick="multiDeleteUsers()">
                            <i class="fas fa-trash me-1"></i> Hapus Terpilih
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Tambah User</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="new_username" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="new_role" class="form-select">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <button type="submit" name="add_user" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Tambah User
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="usersTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="px-3" style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllUsers" onchange="toggleSelectAllUsers()">
                                            </th>
                                            <th class="px-4">ID</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Dibuat</th>
                                            <th class="text-end px-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allUsers as $user): ?>
                                        <tr data-user-id="<?= $user['id'] ?>">
                                            <td class="px-3">
                                                <input type="checkbox" class="form-check-input user-checkbox" value="<?= $user['id'] ?>" onchange="updateMultiUserButtons()" <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                            </td>
                                            <td class="px-4"><?= $user['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-info ms-1">Anda</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-crown me-1"></i>Admin</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-user me-1"></i>User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($user['created_at'])) ?></small></td>
                                            <td class="text-end px-4">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Username</label>
                                                                <input type="text" name="edit_username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
                                                                <input type="password" name="edit_password" class="form-control">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Role</label>
                                                                <select name="edit_role" class="form-select">
                                                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                </select>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="submit" name="edit_user" class="btn btn-primary flex-grow-1">
                                                                    <i class="fas fa-save me-2"></i>Simpan
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Edit History Modal -->
    <div class="modal fade" id="editHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-history me-2"></i>Riwayat Edit Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Filter Foto</label>
                        <select class="form-select" id="editHistoryFilter" onchange="loadEditHistory()">
                            <option value="">Semua Foto</option>
                            <?php foreach ($photos as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="editHistoryTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Foto</th>
                                    <th>Editor</th>
                                    <th>Field</th>
                                    <th>Sebelum</th>
                                    <th>Sesudah</th>
                                </tr>
                            </thead>
                            <tbody id="editHistoryBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showEditHistoryModal() {
            loadEditHistory();
            var modal = new bootstrap.Modal(document.getElementById('editHistoryModal'));
            modal.show();
        }

        function loadEditHistory() {
            var filter = document.getElementById('editHistoryFilter').value;
            var tbody = document.getElementById('editHistoryBody');
            var photoId = filter || '';
            
            fetch('?action=get_edit_history' + (photoId ? '&photo_id=' + photoId : ''))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada riwayat edit</td></tr>';
                        return;
                    }
                    
                    var html = '';
                    data.forEach(function(item) {
                        html += '<tr>';
                        html += '<td><small>' + item.created_at + '</small></td>';
                        html += '<td><small>' + (item.photo_title || 'Unknown') + '</small></td>';
                        html += '<td><small>' + (item.editor_name || 'Unknown') + '</small></td>';
                        html += '<td><span class="badge bg-info">' + item.field_name + '</span></td>';
                        html += '<td><small class="text-muted">' + (item.old_value || '-') + '</small></td>';
                        html += '<td><small>' + (item.new_value || '-') + '</small></td>';
                        html += '</tr>';
                    });
                    tbody.innerHTML = html;
                })
                .catch(function(err) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error: ' + err + '</td></tr>';
                });
        }
    </script>

    <?php if ($action === 'stats' && isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
    <?php
        $views = getViews();
        $users = getUsers();
        $categories = getCategories();
        $photos = getPhotos(true);
        $photos = filterPhotosByVisibility($photos);

        // Filter
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $categoryFilter = $_GET['category'] ?? '';
        $uploaderFilter = $_GET['uploader'] ?? '';
        $deviceFilter = $_GET['device'] ?? '';

        $filteredViews = $views;
        $filteredPhotos = $photos;

        if ($dateFrom) {
            $filteredViews = array_filter($filteredViews, function($v) use ($dateFrom) {
                return strtotime($v['created_at']) >= strtotime($dateFrom);
            });
        }
        if ($dateTo) {
            $filteredViews = array_filter($filteredViews, function($v) use ($dateTo) {
                return strtotime($v['created_at']) <= strtotime($dateTo . ' 23:59:59');
            });
        }

        // Filter berdasarkan kategori: kita perlu mencari photo_id yang termasuk kategori
        if ($categoryFilter) {
            $photoIdsInCategory = [];
            foreach ($photos as $p) {
                $cats = explode(',', $p['category']);
                if (in_array($categoryFilter, $cats)) {
                    $photoIdsInCategory[] = $p['id'];
                }
            }
            $filteredViews = array_filter($filteredViews, function($v) use ($photoIdsInCategory) {
                return in_array($v['photo_id'], $photoIdsInCategory);
            });
        }

        if ($uploaderFilter) {
            // Cari semua foto dari uploader tersebut
            $photoIdsByUploader = [];
            foreach ($photos as $p) {
                if ($p['user_id'] == $uploaderFilter) {
                    $photoIdsByUploader[] = $p['id'];
                }
            }
            $filteredViews = array_filter($filteredViews, function($v) use ($photoIdsByUploader) {
                return in_array($v['photo_id'], $photoIdsByUploader);
            });
        }

        if ($deviceFilter) {
            $filteredViews = array_filter($filteredViews, function($v) use ($deviceFilter) {
                $device = getDeviceType($v['user_agent']);
                return $device === $deviceFilter;
            });
        }

        // Data untuk grafik
        // 1. Views per hari (line chart)
        $viewsPerDay = [];
        foreach ($filteredViews as $v) {
            $date = substr($v['created_at'], 0, 10);
            if (!isset($viewsPerDay[$date])) $viewsPerDay[$date] = 0;
            $viewsPerDay[$date]++;
        }
        ksort($viewsPerDay);
        $dates = array_keys($viewsPerDay);
        $counts = array_values($viewsPerDay);

        // 2. Views per kategori (pie)
        $viewsPerCategory = [];
        foreach ($filteredViews as $v) {
            $photoId = $v['photo_id'];
            // cari photo
            foreach ($photos as $p) {
                if ($p['id'] == $photoId) {
                    $cats = explode(',', $p['category']);
                    foreach ($cats as $catId) {
                        if (!isset($viewsPerCategory[$catId])) $viewsPerCategory[$catId] = 0;
                        $viewsPerCategory[$catId]++;
                    }
                    break;
                }
            }
        }
        $catLabels = [];
        $catData = [];
        foreach ($viewsPerCategory as $catId => $count) {
            foreach ($categories as $c) {
                if ($c['id'] == $catId) {
                    $catLabels[] = $c['name'];
                    $catData[] = $count;
                    break;
                }
            }
        }

        // 3. Views per device (bar)
        $deviceCount = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0];
        foreach ($filteredViews as $v) {
            $device = getDeviceType($v['user_agent']);
            $deviceCount[$device]++;
        }

        // 4. Top 5 foto
        $viewsPerPhoto = [];
        foreach ($filteredViews as $v) {
            $pid = $v['photo_id'];
            if (!isset($viewsPerPhoto[$pid])) $viewsPerPhoto[$pid] = 0;
            $viewsPerPhoto[$pid]++;
        }
        arsort($viewsPerPhoto);
        $topPhotos = array_slice($viewsPerPhoto, 0, 5, true);
        $topPhotoTitles = [];
        $topPhotoCounts = [];
        foreach ($topPhotos as $pid => $count) {
            foreach ($photos as $p) {
                if ($p['id'] == $pid) {
                    $topPhotoTitles[] = $p['title'];
                    $topPhotoCounts[] = $count;
                    break;
                }
            }
        }
    ?>
    <section class="py-5" style="background: #f8fafc; min-height: 80vh;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Statistik Kunjungan Foto</h2>
                    <p class="text-muted">Monitor aktivitas pengaksesan foto dengan filter multi-kriteria</p>
                </div>
            </div>

            <!-- Filter Box -->
            <div class="filter-box">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="action" value="stats">
                    <div class="col-md-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-select">
                            <option value="">Semua</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Uploader</label>
                        <select name="uploader" class="form-select">
                            <option value="">Semua</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $uploaderFilter == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Perangkat</label>
                        <select name="device" class="form-select">
                            <option value="">Semua</option>
                            <option value="Desktop" <?= $deviceFilter == 'Desktop' ? 'selected' : '' ?>>Desktop</option>
                            <option value="Mobile" <?= $deviceFilter == 'Mobile' ? 'selected' : '' ?>>Mobile</option>
                            <option value="Tablet" <?= $deviceFilter == 'Tablet' ? 'selected' : '' ?>>Tablet</option>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Terapkan Filter</button>
                        <a href="?action=stats" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i>Reset</a>
                    </div>
                </form>
            </div>

            <!-- Statistik Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon"><i class="fas fa-eye"></i></div>
                        <h4 class="fw-bold"><?= count($filteredViews) ?></h4>
                        <p class="text-muted mb-0">Total Views (Filtered)</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon"><i class="fas fa-users"></i></div>
                        <h4 class="fw-bold"><?= count(array_unique(array_column($filteredViews, 'user_id'))) ?></h4>
                        <p class="text-muted mb-0">Pengunjung Unik</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon"><i class="fas fa-photo-video"></i></div>
                        <h4 class="fw-bold"><?= count(array_unique(array_column($filteredViews, 'photo_id'))) ?></h4>
                        <p class="text-muted mb-0">Foto Dilihat</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon"><i class="fas fa-calendar-alt"></i></div>
                        <h4 class="fw-bold"><?= count($viewsPerDay) ?></h4>
                        <p class="text-muted mb-0">Hari Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Grafik -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="stats-card">
                        <h5 class="fw-bold mb-3"><i class="fas fa-chart-line me-2 text-primary"></i>Tren Kunjungan Harian</h5>
                        <canvas id="viewsLineChart" style="width:100%; max-height:300px;"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="stats-card">
                        <h5 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Kategori Terpopuler</h5>
                        <canvas id="categoryPieChart" style="width:100%; max-height:250px;"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="stats-card">
                        <h5 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Perangkat Pengakses</h5>
                        <canvas id="deviceBarChart" style="width:100%; max-height:250px;"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="stats-card">
                        <h5 class="fw-bold mb-3"><i class="fas fa-star me-2 text-primary"></i>5 Foto Teratas</h5>
                        <canvas id="topPhotosBarChart" style="width:100%; max-height:250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabel Detail Views -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="stats-card">
                        <h5 class="fw-bold mb-3"><i class="fas fa-table me-2 text-primary"></i>Detail Kunjungan</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Foto</th>
                                        <th>Pengunjung</th>
                                        <th>IP Address</th>
                                        <th>Perangkat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $displayViews = array_slice($filteredViews, 0, 50);
                                    foreach ($displayViews as $v): 
                                        $photoTitle = '';
                                        foreach ($photos as $p) {
                                            if ($p['id'] == $v['photo_id']) {
                                                $photoTitle = $p['title'];
                                                break;
                                            }
                                        }
                                        $visitor = $v['user_id'] != 0 ? 'User #'.$v['user_id'] : 'Tamu';
                                        $device = getDeviceType($v['user_agent']);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['created_at']) ?></td>
                                        <td><?= htmlspecialchars($photoTitle) ?></td>
                                        <td><?= $visitor ?></td>
                                        <td><?= htmlspecialchars($v['ip']) ?></td>
                                        <td><span class="badge bg-info"><?= $device ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($filteredViews) > 50): ?>
                            <p class="text-muted text-center">Menampilkan 50 dari <?= count($filteredViews) ?> kunjungan</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Line Chart
        new Chart(document.getElementById('viewsLineChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [{
                    label: 'Jumlah Views',
                    data: <?= json_encode($counts) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Pie Chart Kategori
        new Chart(document.getElementById('categoryPieChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catData) ?>,
                    backgroundColor: ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#65a30d', '#0d9488']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Device Bar Chart
        new Chart(document.getElementById('deviceBarChart'), {
            type: 'bar',
            data: {
                labels: ['Desktop', 'Mobile', 'Tablet'],
                datasets: [{
                    label: 'Jumlah Views',
                    data: [<?= $deviceCount['Desktop'] ?>, <?= $deviceCount['Mobile'] ?>, <?= $deviceCount['Tablet'] ?>],
                    backgroundColor: '#f59e0b'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Top Photos Bar Chart
        new Chart(document.getElementById('topPhotosBarChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($topPhotoTitles) ?>,
                datasets: [{
                    label: 'Views',
                    data: <?= json_encode($topPhotoCounts) ?>,
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
    <?php endif; ?>
    
    <section class="category-filter">
        <div class="container">
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center mobile-search-row">
                        <input type="hidden" name="action" value="gallery">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>">
                        <input type="hidden" name="month_year" value="<?= htmlspecialchars($filterMonthYear) ?>">
                        <div class="flex-grow-1 mobile-search-input" style="min-width: 200px;">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari judul foto..." value="<?= htmlspecialchars($searchQuery) ?>" id="searchInput" autocomplete="off">
                            </div>
                        </div>
                        <div class="mobile-month-select" style="min-width: 150px;">
                            <select name="month_year" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Bulan</option>
                                <?php
                                $monthYearOptions = [];
                                foreach ($photos as $p) {
                                    $my = date('Y-m', strtotime($p['created_at']));
                                    if (!in_array($my, $monthYearOptions)) {
                                        $monthYearOptions[] = $my;
                                    }
                                }
                                rsort($monthYearOptions);
                                foreach ($monthYearOptions as $my):
                                $monthName = date('F Y', strtotime($my . '-01'));
                                ?>
                                <option value="<?= $my ?>" <?= $filterMonthYear == $my ? 'selected' : '' ?>><?= $monthName ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($searchQuery || $filterCategory || $filterMonthYear): ?>
                        <a href="?action=gallery" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-center align-items-center">
                <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" onclick="var btns = document.querySelector('.category-buttons'); var icon = this.querySelector('.toggle-icon'); btns.classList.toggle('d-none'); icon.classList.toggle('rotate');" style="border-radius: 20px;">
                    <i class="fas fa-sliders-h"></i>
                    <span class="d-none d-sm-inline">Kategori Foto</span>
                    <i class="fas fa-chevron-down toggle-icon" style="transition: transform 0.3s;"></i>
                </button>
                <style>
                .toggle-icon.rotate {
                    transform: rotate(180deg);
                }
                </style>
                <div class="category-buttons d-none w-100 mt-3 p-3 bg-light rounded" style="animation: fadeIn 0.3s ease;">
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="?action=gallery<?= $filterMonthYear ? '&month_year=' . $filterMonthYear : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn category-btn <?= !$filterCategory ? 'active' : '' ?>">
                            Semua
                        </a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="?action=gallery&category=<?= $cat['id'] ?><?= $filterMonthYear ? '&month_year=' . $filterMonthYear : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn category-btn <?= $filterCategory == $cat['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .category-btn {
                border-radius: 20px;
                padding: 0.4rem 1rem;
                font-size: 0.85rem;
                transition: all 0.3s ease;
            }
            .category-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }

            @media (max-width: 576px) {
                .mobile-search-row {
                    flex-wrap: nowrap !important;
                }
                .mobile-search-input {
                    flex: 1 1 auto !important;
                    min-width: 0 !important;
                    width: calc(100% - 120px) !important;
                }
                .mobile-month-select {
                    flex: 0 0 auto !important;
                    width: 110px !important;
                    min-width: 110px !important;
                }
                .mobile-search-input .input-group {
                    display: flex;
                }
                .mobile-search-input .form-control {
                    font-size: 14px;
                    padding: 0.4rem 0.5rem;
                }
                .mobile-month-select .form-select {
                    font-size: 14px;
                    padding: 0.4rem 0.5rem;
                }
            }
            </style>
        </div>
    </section>
    
    <section class="gallery-grid">
        <div class="container">
            <?php
            $filterTitle = 'Semua Foto';
            $filterParts = [];
            if ($searchQuery) $filterParts[] = 'Judul: "' . htmlspecialchars($searchQuery) . '"';
            if ($filterCategory) {
                $catName = '';
                foreach ($categories as $c) {
                    if ($c['id'] == $filterCategory) {
                        $catName = $c['name'];
                        break;
                    }
                }
                $filterParts[] = 'Kategori: ' . $catName;
            }
            if ($filterMonthYear) $filterParts[] = 'Bulan: ' . date('F Y', strtotime($filterMonthYear . '-01'));
            if (!empty($filterParts)) $filterTitle = implode(' | ', $filterParts);
            ?>
            <h2 class="mb-4 fw-bold">
                <i class="fas fa-images me-2 text-primary"></i>
                <?= $filterTitle ?>
                <small class="text-muted fw-normal" style="font-size: 0.7em;">(<?= count($albums) ?> album)</small>
            </h2>
            
            <?php 
            $albumPage = isset($_GET['album_page']) ? max(1, intval($_GET['album_page'])) : 1;
            $albumsPerPage = 9;
            $totalAlbums = count($albums);
            $totalAlbumPages = ceil($totalAlbums / $albumsPerPage);
            $albumOffset = ($albumPage - 1) * $albumsPerPage;
            $albumsSlice = array_slice($albums, $albumOffset, $albumsPerPage, true);
            ?>
            <?php if (empty($photoGroups)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>Belum ada foto</h3>
                <p>Upload foto pertama Anda sekarang!</p>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($albumsSlice as $albumId => $albumInfo): ?>
                <?php 
                $photosInGroup = $photoGroups[$albumId];
                $firstPhoto = $albumInfo['first_photo'];
                $isBatch = count($photosInGroup) > 1;
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="album-card">
                        <div class="album-header">
                            <h5 class="album-title">
                                <i class="fas fa-folder-open me-2 text-warning"></i>
                                <?= htmlspecialchars($albumInfo['title']) ?>
                            </h5>
                            <span class="badge bg-primary"><?= $albumInfo['count'] ?> foto</span>
                        </div>
                        <div class="album-grid" <?= $isBatch ? 'onclick="openBatchSliderModal(\'' . htmlspecialchars($albumId) . '\')"' : 'onclick="openLightbox(\'' . htmlspecialchars($firstPhoto['filepath']) . '\', ' . $firstPhoto['id'] . ')"' ?>>
                            <img src="<?= htmlspecialchars($firstPhoto['filepath']) ?>" alt="<?= htmlspecialchars($firstPhoto['title']) ?>" loading="lazy">
                            <?php if ($isBatch): ?>
                            <div class="album-overlay">
                                <div class="text-white d-flex align-items-center justify-content-center">
                                    <span><i class="fas fa-images me-2"></i> <?= count($photosInGroup) ?> foto</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="album-footer">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($firstPhoto['username']) ?>
                                <span class="ms-2"><i class="fas fa-calendar me-1"></i><?= date('d M y', strtotime($firstPhoto['created_at'])) ?></span>
                            </small>
                        </div>
                        <!-- Hidden container for batch images -->
                        <div class="d-none batch-images" id="batch-<?= htmlspecialchars($albumId) ?>">
                            <?php foreach ($photosInGroup as $p): ?>
                            <img src="<?= htmlspecialchars($p['filepath']) ?>" data-photo-id="<?= $p['id'] ?>" data-photo-label="<?= htmlspecialchars($p['photo_label'] ?? '') ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if ($totalAlbumPages > 1): ?>
                <div class="col-12">
                    <nav aria-label="Album pagination">
                        <ul class="pagination justify-content-center flex-wrap gap-1 mt-4">
                            <?php 
                            $queryParams = $_GET;
                            ?>
                            <?php if ($albumPage > 1): ?>
                            <?php $queryParams['album_page'] = $albumPage - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($queryParams) ?>"><i class="fas fa-chevron-left"></i></a></li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalAlbumPages; $i++): ?>
                            <?php $queryParams['album_page'] = $i; ?>
                            <li class="page-item <?= $i == $albumPage ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query($queryParams) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($albumPage < $totalAlbumPages): ?>
                            <?php $queryParams['album_page'] = $albumPage + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($queryParams) ?>"><i class="fas fa-chevron-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close"><i class="fas fa-times"></i></button>
        <img id="lightbox-img" src="" alt="">
    </div>
    
    <!-- Modal for batch slider -->
    <div class="modal fade" id="batchSliderModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>Galeri Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="photo-slider">
                        <div class="photo-slider-track" id="batchSliderTrack"></div>
                        <button class="photo-slider-nav prev" onclick="slideBatch(-1)"><i class="fas fa-chevron-left"></i></button>
                        <button class="photo-slider-nav next" onclick="slideBatch(1)"><i class="fas fa-chevron-right"></i></button>
                        <div class="photo-slider-dots" id="batchSliderDots"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sign-in-alt me-2"></i>Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="loginForm">
                        <input type="hidden" name="redirect" id="loginRedirect" value="">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                    <hr>
                    <!--<div class="text-muted small">
                        <p><strong>Akun Demo:</strong></p>
                        <p>Superadmin: superadmin / superadmin123</p>
                        <p>Admin: admin / admin123</p>
                        <p>User: user / user123</p>
                    </div>-->
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle me-1"></i> 
                            <strong>Tamu:</strong> Maksimal 5 foto. Login untuk upload lebih banyak.
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <?php if (!isset($_SESSION['user_id'])): ?>
                                Pilih Foto (Max 5)
                                <?php else: ?>
                                Pilih Foto (Unlimited)
                                <?php endif; ?>
                            </label>
                            <div class="file-upload-area" onclick="document.getElementById('photos').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p class="mb-1" id="uploadText">Klik untuk memilih foto</p>
                                <small class="text-muted">JPG, PNG (Max 300KB setelah kompresi)</small>
                            </div>
                            <input type="file" name="photos[]" id="photos" accept="image/jpeg,image/png" 
                                <?php if (!isset($_SESSION['user_id'])): ?>
                                max="5" 
                                <?php endif; ?>
                                multiple style="display:none" onchange="previewFiles(); updateUploadText()">
                            <div class="preview-images" id="preview"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori (bisa pilih lebih dari satu)</label>
                            <div class="input-group">
                                <select name="category[]" class="form-select" id="categorySelect" multiple required size="3">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Tekan Ctrl/Cmd + klik untuk pilih multiple kategori</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Judul</label>
                            <input type="text" name="title" class="form-control" required placeholder="Judul foto">
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Album / Grup Utama</label>
                            <div class="input-group">
                                <select name="album_title" class="form-select" id="albumSelect">
                                    <option value="">-- Buat Album Baru --</option>
                                    <?php 
                                    $albums = dbGetAll("SELECT album_id, album_title, MAX(created_at) as latest_photo FROM photos WHERE album_title != '' AND album_title IS NOT NULL GROUP BY album_id, album_title ORDER BY latest_photo DESC");
                                    foreach ($albums as $album): ?>
                                    <option value="<?= htmlspecialchars($album['album_title']) ?>"><?= htmlspecialchars($album['album_title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-success" onclick="document.getElementById('albumSelect').value=''; document.getElementById('newAlbumName').style.display='block'; document.getElementById('newAlbumName').focus()">
                                    <i class="fas fa-plus"></i> Baru
                                </button>
                            </div>
                            <input type="text" name="new_album_title" id="newAlbumName" class="form-control mt-2" placeholder="Nama Album Baru" style="display:none;">
                            <small class="text-muted">Pilih album existing atau buat baru</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label Foto <small class="text-muted">(keterangan singkat per foto, opsional)</small></label>
                            <input type="text" name="photo_label" class="form-control" placeholder="Contoh: Foto depan, Bagian dalam, dll">
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi foto (opsional)"></textarea>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Visibilitas</label>
                            <div class="d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="is_private" class="form-check-input" id="privateSwitch" onchange="handlePrivateSwitch(this)">
                                    <label class="form-check-label" for="privateSwitch">
                                        <span id="visibilityLabel"><i class="fas fa-globe me-1"></i> Public</span>
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted" id="visibilityHint">Foto dapat dilihat oleh semua orang</small>
                        </div>
                        <?php endif; ?>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Nama Anda <span class="text-danger">*</span></label>
                            <input type="text" name="guest_name" class="form-control" required placeholder="Masukkan nama Anda">
                            <small class="text-muted">Nama tidak boleh sama dengan username pengguna lain</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kode Verifikasi <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="captcha" class="form-control" required placeholder="Masukkan 4 angka" maxlength="4" pattern="[0-9]{4}" style="letter-spacing: 0.5rem; text-align: center;">
                                <img id="captchaImg" src="captcha.php?t=<?= time() ?>" alt="Captcha" style="height: 38px; border: 1px solid #ced4da; border-radius: 0 4px 4px 0; cursor: pointer;" onclick="refreshCaptcha()" title="Klik untuk ganti kode">
                                <button type="button" class="btn btn-outline-secondary" onclick="refreshCaptcha()" title="Ganti kode">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted">Masukkan 4 angka yang ditampilkan</small>
                        </div>
                        <?php endif; ?>
                        <button type="submit" name="upload" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i>Upload Foto
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addCategoryForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori</label>
                            <input type="text" name="category_name" class="form-control" placeholder="Contoh: Wedding, Olaraga, Makanan" required>
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Tambah Kategori
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Album Modal -->
    <div class="modal fade" id="addAlbumModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Tambah Album Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="GET">
                        <input type="hidden" name="action" value="upload">
                        <div class="mb-3">
                            <label class="form-label">Nama Album Baru</label>
                            <input type="text" name="new_album_name" class="form-control" placeholder="Contoh: Kegiatan UAS 2026" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Lanjut Upload
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
    <div class="modal fade" id="adminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cog me-2"></i>Kelola Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div class="input-group">
                            <input type="text" name="category_name" class="form-control" placeholder="Nama kategori baru" required>
                            <button type="submit" name="add_category" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah
                            </button>
                        </div>
                    </form>
                    <h6>Kategori Tersedia:</h6>
                    <ul class="list-group">
                        <?php foreach ($categories as $cat): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($cat['name']) ?>
                            <span class="badge bg-secondary"><?= $cat['slug'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <footer>
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 Galeri Sekolah V1.0.</p>
            <small class="text-white-50">ICT-Center SMKN 6 Kota Serang</small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables for batch slider
        var currentBatchImages = [];
        var currentBatchIndex = 0;

        // Lightbox functions
        function openLightbox(src, photoId) {
            try {
                document.getElementById('lightbox-img').src = src;
                document.getElementById('lightbox').classList.add('active');
                // Log view via AJAX
                if (photoId) {
                    fetch('?action=log_view', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'photo_id=' + photoId
                    }).catch(e => console.error('Log view error:', e));
                }
            } catch(e) {
                console.error('Lightbox error:', e);
                alert('Error opening image');
            }
        }

        function closeLightbox() {
            try {
                document.getElementById('lightbox').classList.remove('active');
            } catch(e) {}
        }

        // Batch slider functions
        function openBatchSliderModal(batchId) {
            var container = document.getElementById('batch-' + batchId);
            if (!container) return;
            var images = container.querySelectorAll('img');
            currentBatchImages = Array.from(images).map(img => ({
                src: img.src,
                id: img.dataset.photoId,
                label: img.dataset.photoLabel || ''
            }));
            currentBatchIndex = 0;
            
            var track = document.getElementById('batchSliderTrack');
            track.innerHTML = '';
            var dotsContainer = document.getElementById('batchSliderDots');
            dotsContainer.innerHTML = '';
            
            currentBatchImages.forEach((item, i) => {
                var container = document.createElement('div');
                container.className = 'slider-image-container';
                
                var img = document.createElement('img');
                img.src = item.src;
                img.onclick = function() { openLightbox(this.src, item.id); };
                
                var label = document.createElement('div');
                label.className = 'slider-image-label';
                label.textContent = item.label || '';
                
                container.appendChild(img);
                if (item.label) {
                    container.appendChild(label);
                }
                track.appendChild(container);
            });
            
            // Log view for first image
            if (currentBatchImages.length > 0 && currentBatchImages[0].id) {
                fetch('?action=log_view', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'photo_id=' + currentBatchImages[0].id
                }).catch(e => console.error('Log view error:', e));
            }
            
            updateBatchDots();
            var modal = new bootstrap.Modal(document.getElementById('batchSliderModal'));
            modal.show();
        }

        function slideBatch(direction) {
            var total = currentBatchImages.length;
            if (total === 0) return;
            currentBatchIndex = (currentBatchIndex + direction + total) % total;
            var track = document.getElementById('batchSliderTrack');
            track.style.transform = 'translateX(-' + (currentBatchIndex * 100) + '%)';
            updateBatchDots();
            // Log view for current image
            if (currentBatchImages[currentBatchIndex].id) {
                fetch('?action=log_view', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'photo_id=' + currentBatchImages[currentBatchIndex].id
                }).catch(e => console.error('Log view error:', e));
            }
        }

        function updateBatchDots() {
            var dotsContainer = document.getElementById('batchSliderDots');
            dotsContainer.innerHTML = '';
            for (var i = 0; i < currentBatchImages.length; i++) {
                var dot = document.createElement('span');
                dot.className = 'photo-slider-dot' + (i === currentBatchIndex ? ' active' : '');
                dot.onclick = (function(index) {
                    return function() { goToBatchSlide(index); };
                })(i);
                dotsContainer.appendChild(dot);
            }
        }

        function goToBatchSlide(index) {
            currentBatchIndex = index;
            var track = document.getElementById('batchSliderTrack');
            track.style.transform = 'translateX(-' + (index * 100) + '%)';
            updateBatchDots();
            if (currentBatchImages[currentBatchIndex].id) {
                fetch('?action=log_view', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'photo_id=' + currentBatchImages[currentBatchIndex].id
                }).catch(e => console.error('Log view error:', e));
            }
        }

        // File preview functions
        function previewFiles() {
            var input = document.getElementById('photos');
            var preview = document.getElementById('preview');
            preview.innerHTML = '';

            var files = input.files;
            var fileCount = files.length;
            
            // Check if guest user (no session) - limit to 5 files
            var isGuest = <?php echo isset($_SESSION['user_id']) ? 'false' : 'true'; ?>;
            var maxFiles = isGuest ? 5 : 50;
            
            if (fileCount > maxFiles) {
                alert('Maksimal ' + maxFiles + ' foto untuk akun tamu. Login untuk upload lebih banyak.');
                // Reset input
                input.value = '';
                fileCount = maxFiles;
            }

            if (fileCount > 0) {
                var countLabel = document.createElement('div');
                countLabel.className = 'mb-2';
                countLabel.innerHTML = '<strong>File dipilih:</strong> <span class="preview-count">' + fileCount + '</span>';
                preview.appendChild(countLabel);
            }

            var maxPreview = 20;
            for (var i = 0; i < Math.min(fileCount, maxPreview); i++) {
                var reader = new FileReader();
                reader.onload = (function(file, index) {
                    return function(e) {
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-thumb';
                        img.title = file.name;
                        preview.appendChild(img);
                    };
                })(files[i], i);
                reader.readAsDataURL(files[i]);
            }
            
            if (fileCount > maxPreview) {
                var moreLabel = document.createElement('div');
                moreLabel.className = 'mb-2 text-muted';
                moreLabel.innerHTML = '<em>...dan ' + (fileCount - maxPreview) + ' file lainnya</em>';
                preview.appendChild(moreLabel);
            }
        }

        function refreshCaptcha() {
            document.getElementById('captchaImg').src = 'captcha.php?t=' + new Date().getTime();
        }

        function updateUploadText() {
            var input = document.getElementById('photos');
            var fileCount = input.files.length;
            var uploadText = document.getElementById('uploadText');
            if (fileCount > 0) {
                uploadText.innerHTML = '<i class="fas fa-check-circle text-success"></i> ' + fileCount + ' file dipilih';
            } else {
                uploadText.textContent = 'Klik untuk memilih foto';
            }
        }

        function previewDashFiles() {
            var input = document.getElementById('dashPhotos');
            var preview = document.getElementById('dashPreview');
            preview.innerHTML = '';

            var files = input.files;
            var fileCount = files.length;

            if (fileCount > 0) {
                var countLabel = document.createElement('div');
                countLabel.className = 'mb-2';
                countLabel.innerHTML = '<strong>File dipilih:</strong> <span class="preview-count">' + fileCount + '</span>';
                preview.appendChild(countLabel);
            }

            var maxPreview = 20;
            for (var i = 0; i < Math.min(fileCount, maxPreview); i++) {
                var reader = new FileReader();
                reader.onload = (function(file) {
                    return function(e) {
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-thumb';
                        img.title = file.name;
                        preview.appendChild(img);
                    };
                })(files[i]);
                reader.readAsDataURL(files[i]);
            }
            
            if (fileCount > maxPreview) {
                var moreLabel = document.createElement('div');
                moreLabel.className = 'mb-2 text-muted';
                moreLabel.innerHTML = '<em>...dan ' + (fileCount - maxPreview) + ' file lainnya</em>';
                preview.appendChild(moreLabel);
            }
        }

        function handlePrivateSwitch(checkbox) {
            var label = document.getElementById('visibilityLabel');
            var hint = document.getElementById('visibilityHint');
            var isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

            if (checkbox.checked) {
                label.innerHTML = '<i class="fas fa-lock me-1"></i> Private';
                hint.textContent = 'Hanya Anda yang dapat melihat foto ini';

                if (!isLoggedIn) {
                    checkbox.checked = false;
                    label.innerHTML = '<i class="fas fa-globe me-1"></i> Public';
                    hint.textContent = 'Foto dapat dilihat oleh semua orang';
                    alert('Anda harus login untuk upload foto private!\n\nMengarahkan ke halaman login...');
                    window.location.href = '?action=login&redirect=upload';
                    return false;
                }
            } else {
                label.innerHTML = '<i class="fas fa-globe me-1"></i> Public';
                hint.textContent = 'Foto dapat dilihat oleh semua orang';
            }
        }

        // Auto-hide toast
        document.querySelectorAll('.toast').forEach(function(toast) {
            setTimeout(function() {
                var bsToast = new bootstrap.Toast(toast);
                bsToast.show();
            }, 100);
            setTimeout(function() {
                var bsToast = new bootstrap.Toast(toast);
                bsToast.hide();
            }, 5000);
        });

        // Scroll to gallery if action=gallery
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'gallery') {
            document.querySelector('.category-filter').scrollIntoView({ behavior: 'smooth' });
        }

        // Autocomplete search
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            let autocompleteTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(autocompleteTimeout);
                const query = this.value;
                if (query.length < 2) {
                    hideAutocomplete();
                    return;
                }
                autocompleteTimeout = setTimeout(() => {
                    fetchAutocomplete(query);
                }, 300);
            });

            function fetchAutocomplete(query) {
                fetch('?action=autocomplete&search=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        showAutocomplete(data);
                    })
                    .catch(err => console.error(err));
            }

            function showAutocomplete(results) {
                let container = document.getElementById('autocompleteContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'autocompleteContainer';
                    container.className = 'autocomplete-dropdown';
                    container.style.cssText = 'position:absolute;z-index:1000;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.15);max-height:350px;overflow-y:auto;width:100%;display:none;';
                    searchInput.parentElement.appendChild(container);
                }
                
                if (results.length === 0) {
                    container.innerHTML = '<div class="p-3 text-muted"><i class="fas fa-search me-2"></i>Tidak ada hasil pencarian</div>';
                    container.style.display = 'block';
                    return;
                }

                // Group by match_type
                const grouped = { title: [], album: [], label: [] };
                results.forEach(function(r) {
                    const type = r.match_type || 'title';
                    if (!grouped[type]) grouped[type] = [];
                    grouped[type].push(r);
                });

                let html = '';
                
                if (grouped.title && grouped.title.length > 0) {
                    html += '<div class="autocomplete-group"><div class="autocomplete-group-title"><i class="fas fa-image me-2"></i>Judul Foto (' + grouped.title.length + ')</div>';
                    html += grouped.title.slice(0, 10).map(function(r) {
                        return '<div class="autocomplete-item" onclick="selectAutocomplete(\'' + (r.title || '').replace(/'/g, "\\'") + '\')">' +
                            '<div class="d-flex align-items-center">' +
                            '<img src="' + (r.filepath || '') + '" style="width:40px;height:40px;object-fit:cover;margin-right:10px;border-radius:4px;">' +
                            '<div><strong>' + (r.title || 'Tanpa judul') + '</strong>' +
                            '<br><small class="text-muted">' + (r.album_title || 'Tanpa album') + '</small></div></div></div>';
                    }).join('');
                    html += '</div>';
                }
                
                if (grouped.album && grouped.album.length > 0) {
                    html += '<div class="autocomplete-group"><div class="autocomplete-group-title"><i class="fas fa-folder me-2"></i>Album (' + grouped.album.length + ')</div>';
                    html += grouped.album.map(function(r) {
                        return '<div class="autocomplete-item" onclick="selectAutocomplete(\'' + (r.album_title || '').replace(/'/g, "\\'") + '\')">' +
                            '<div><i class="fas fa-folder text-warning me-2"></i><strong>' + (r.album_title || 'Tanpa album') + '</strong></div>';
                    }).join('');
                    html += '</div>';
                }
                
                if (grouped.label && grouped.label.length > 0) {
                    html += '<div class="autocomplete-group"><div class="autocomplete-group-title"><i class="fas fa-tags me-2"></i>Label (' + grouped.label.length + ')</div>';
                    html += grouped.label.map(function(r) {
                        return '<div class="autocomplete-item" onclick="selectAutocomplete(\'' + (r.photo_label || '').replace(/'/g, "\\'") + '\')">' +
                            '<div><i class="fas fa-tag text-success me-2"></i><strong>' + (r.photo_label || '') + '</strong></div>';
                    }).join('');
                    html += '</div>';
                }
                
                container.innerHTML = html;
                container.style.display = 'block';
            }

            function hideAutocomplete() {
                const container = document.getElementById('autocompleteContainer');
                if (container) container.style.display = 'none';
            }

            window.selectAutocomplete = function(title) {
                searchInput.value = title;
                hideAutocomplete();
                searchInput.form.submit();
            };

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !document.getElementById('autocompleteContainer')?.contains(e.target)) {
                    hideAutocomplete();
                }
            });
        }

        // MyPhotos Autocomplete search - for album, label, title
        const myPhotosSearchInput = document.getElementById('myPhotosSearch');
        if (myPhotosSearchInput) {
            let myPhotosAutocompleteTimeout;
            myPhotosSearchInput.addEventListener('input', function() {
                clearTimeout(myPhotosAutocompleteTimeout);
                const query = this.value;
                if (query.length < 2) {
                    hideMyPhotosAutocomplete();
                    filterMyPhotos('');
                    return;
                }
                myPhotosAutocompleteTimeout = setTimeout(() => {
                    fetchMyPhotosAutocomplete(query);
                }, 300);
            });

            function fetchMyPhotosAutocomplete(query) {
                fetch('?action=autocomplete&search=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        showMyPhotosAutocomplete(data);
                    })
                    .catch(err => console.error(err));
            }

            function showMyPhotosAutocomplete(results) {
                let container = document.getElementById('myPhotosAutocompleteContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'myPhotosAutocompleteContainer';
                    container.className = 'autocomplete-dropdown';
                    container.style.cssText = 'position:absolute;z-index:1000;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.15);max-height:350px;overflow-y:auto;width:100%;display:none;';
                    myPhotosSearchInput.parentElement.appendChild(container);
                }
                
                if (results.length === 0) {
                    container.innerHTML = '<div class="p-3 text-muted"><i class="fas fa-search me-2"></i>Tidak ada hasil</div>';
                    container.style.display = 'block';
                    return;
                }

                // Group by match_type
                const grouped = { title: [], album: [], label: [] };
                results.forEach(function(r) {
                    const type = r.match_type || 'title';
                    if (!grouped[type]) grouped[type] = [];
                    grouped[type].push(r);
                });

                let html = '';
                
                if (grouped.title && grouped.title.length > 0) {
                    html += '<div class="autocomplete-group"><div class="autocomplete-group-title"><i class="fas fa-image me-2"></i>Judul Foto (' + grouped.title.length + ')</div>';
                    html += grouped.title.slice(0, 10).map(function(r) {
                        return '<div class="autocomplete-item" onclick="selectMyPhotosAutocomplete(\'' + (r.title || '').replace(/'/g, "\\'") + '\')">' +
                            '<div class="d-flex align-items-center">' +
                            '<img src="' + (r.filepath || '') + '" style="width:40px;height:40px;object-fit:cover;margin-right:10px;border-radius:4px;">' +
                            '<div><strong>' + (r.title || 'Tanpa judul') + '</strong>' +
                            '<br><small class="text-muted">' + (r.album_title || 'Tanpa album') + '</small></div></div></div>';
                    }).join('');
                    html += '</div>';
                }
                
                if (grouped.album && grouped.album.length > 0) {
                    html += '<div class="autocomplete-group"><div class="autocomplete-group-title"><i class="fas fa-folder me-2"></i>Album (' + grouped.album.length + ')</div>';
                    html += grouped.album.map(function(r) {
                        return '<div class="autocomplete-item" onclick="selectMyPhotosAutocomplete(\'' + (r.album_title || '').replace(/'/g, "\\'") + '\')">' +
                            '<div><i class="fas fa-folder text-warning me-2"></i><strong>' + (r.album_title || 'Tanpa album') + '</strong></div>';
                    }).join('');
                    html += '</div>';
                }
                
                if (grouped.label && grouped.label.length > 0) {
                    html += '<div class="autocomplete-group"><div class="autocomplete-group-title"><i class="fas fa-tags me-2"></i>Label (' + grouped.label.length + ')</div>';
                    html += grouped.label.map(function(r) {
                        return '<div class="autocomplete-item" onclick="selectMyPhotosAutocomplete(\'' + (r.photo_label || '').replace(/'/g, "\\'") + '\')">' +
                            '<div><i class="fas fa-tag text-success me-2"></i><strong>' + (r.photo_label || '') + '</strong></div>';
                    }).join('');
                    html += '</div>';
                }
                
                container.innerHTML = html;
                container.style.display = 'block';
            }

            function hideMyPhotosAutocomplete() {
                const container = document.getElementById('myPhotosAutocompleteContainer');
                if (container) container.style.display = 'none';
            }

            window.selectMyPhotosAutocomplete = function(title) {
                myPhotosSearchInput.value = title;
                hideMyPhotosAutocomplete();
                filterMyPhotos(title);
            };

            window.clearMyPhotosSearch = function() {
                myPhotosSearchInput.value = '';
                filterMyPhotos('');
            };

            function filterMyPhotos(query) {
                const lowerQuery = query.toLowerCase();
                const rows = document.querySelectorAll('#groupedView tr[data-photo-id], #flatView tr[data-photo-id]');
                rows.forEach(function(row) {
                    const title = row.querySelector('.photo-title-display')?.textContent || '';
                    const album = row.querySelector('.photo-album-display')?.textContent || '';
                    const label = row.querySelector('.photo-label-display')?.textContent || '';
                    
                    const match = title.toLowerCase().includes(lowerQuery) || 
                                  album.toLowerCase().includes(lowerQuery) || 
                                  label.toLowerCase().includes(lowerQuery);
                    
                    if (lowerQuery === '' || match) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Also filter group headers
                const groupHeaders = document.querySelectorAll('.group-header');
                groupHeaders.forEach(function(header) {
                    const groupTable = header.nextElementSibling;
                    const visibleRows = groupTable ? groupTable.querySelectorAll('tr[data-photo-id]') : [];
                    let hasVisible = false;
                    visibleRows.forEach(function(row) {
                        if (row.style.display !== 'none') {
                            hasVisible = true;
                        }
                    });
                    if (lowerQuery === '' || hasVisible) {
                        header.style.display = '';
                    } else {
                        header.style.display = 'none';
                    }
                });
            }

            myPhotosSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = myPhotosSearchInput.value;
                    filterMyPhotos(query);
                    hideMyPhotosAutocomplete();
                } else if (e.key === 'Escape') {
                    hideMyPhotosAutocomplete();
                }
            });

            document.addEventListener('click', function(e) {
                if (!myPhotosSearchInput.contains(e.target) && !document.getElementById('myPhotosAutocompleteContainer')?.contains(e.target)) {
                    hideMyPhotosAutocomplete();
                }
            });
        }

        // Table selection functions
        function toggleSelectAllPhotos() {
            var selectAll = document.getElementById('selectAllPhotos');
            var checkboxes = document.querySelectorAll('.photo-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            updateMultiButtons();
        }

        function updateMultiButtons() {
            var checkboxes = document.querySelectorAll('.photo-checkbox:checked');
            var multiEditBtn = document.getElementById('multiEditBtn');
            var multiDeleteBtn = document.getElementById('multiDeleteBtn');
            
            if (checkboxes.length > 0) {
                multiEditBtn.disabled = false;
                multiDeleteBtn.disabled = false;
                multiEditBtn.innerHTML = '<i class="fas fa-edit me-1"></i> Multi Edit (' + checkboxes.length + ')';
                multiDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus (' + checkboxes.length + ')';
            } else {
                multiEditBtn.disabled = true;
                multiDeleteBtn.disabled = true;
                multiEditBtn.innerHTML = '<i class="fas fa-edit me-1"></i> Multi Edit';
                multiDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus Terpilih';
            }
        }

        function showMultiEditModal() {
            var checkboxes = document.querySelectorAll('.photo-checkbox:checked');
            var ids = Array.from(checkboxes).map(function(cb) { return cb.value; });
            
            document.getElementById('multiPhotoIds').value = ids.join(',');
            document.getElementById('multiEditCount').textContent = ids.length;
            
            var modal = new bootstrap.Modal(document.getElementById('multiEditModal'));
            modal.show();
        }

        function executeMultiEdit() {
            var form = document.getElementById('multiEditForm');
            var formData = new FormData(form);
            formData.append('action', 'multi_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Error: Invalid server response');
                    return null;
                }
            })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        function multiDeletePhotos() {
            var checkboxes = document.querySelectorAll('.photo-checkbox:checked');
            var ids = Array.from(checkboxes).map(function(cb) { return cb.value; });
            
            if (ids.length === 0) {
                alert('Pilih foto yang akan dihapus');
                return;
            }
            
            if (!confirm('Hapus ' + ids.length + ' foto yang dipilih?')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_ids', ids.join(','));
            formData.append('action', 'multi_delete');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        function toggleInlineEdit(photoId) {
            var row = document.querySelector('tr[data-photo-id="' + photoId + '"]');
            if (!row) return;
            
            var inputs = row.querySelectorAll('input.edit-input');
            var displays = row.querySelectorAll('.photo-title-display, .photo-label-display');
            
            // Find first hidden input to show
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].style.display === 'none') {
                    inputs[i].style.display = 'block';
                    inputs[i].focus();
                    if (displays[i]) displays[i].style.display = 'none';
                    break;
                }
            }
        }

        function toggleAlbumEdit(photoId) {
            var row = document.querySelector('tr[data-photo-id="' + photoId + '"]');
            if (!row) return;
            
            var display = row.querySelector('.photo-album-display');
            var select = row.querySelector('select.edit-input');
            
            if (display && select) {
                if (select.style.display === 'none') {
                    select.style.display = 'inline-block';
                    select.focus();
                    display.style.display = 'none';
                } else {
                    select.style.display = 'none';
                    display.style.display = 'inline';
                }
            }
        }

        function handleAlbumSelect(select, field, photoId) {
            if (select.value === 'new_album') {
                select.style.display = 'none';
                var textInput = select.nextElementSibling;
                textInput.style.display = 'inline-block';
                textInput.focus();
                select.value = '';
            } else if (select.value) {
                saveInlineEdit(select, field, photoId);
            }
        }

        function handleNewAlbumKey(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveNewAlbum(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var display = input.previousElementSibling.previousElementSibling;
                display.style.display = 'inline';
            }
        }

        function saveInlineEdit(input, field, photoId) {
            var value = input.value;
            var row = input.closest('tr');
            var display = row.querySelector('.photo-title-display, .photo-label-display');
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    input.style.display = 'none';
                    if (display) {
                        display.style.display = 'inline';
                        display.textContent = value;
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    input.style.display = 'none';
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(e) {
                input.style.display = 'none';
                if (display) display.style.display = 'inline';
                console.error('Save error:', e);
            });
        }

        function handleInlineEditKey(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveInlineEdit(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-title-display, .photo-label-display');
                if (display) display.style.display = 'inline';
            }
        }

        function toggleMyInlineEdit(photoId) {
            var row = document.querySelector('#myPhotosTable tr[data-photo-id="' + photoId + '"]');
            if (!row) {
                row = document.querySelector('.group-table tr[data-photo-id="' + photoId + '"]');
            }
            if (!row) return;
            
            var inlineEdits = row.querySelectorAll('.inline-edit');
            for (var i = 0; i < inlineEdits.length; i++) {
                var display = inlineEdits[i].querySelector('.photo-title-display, .photo-label-display, .photo-category-display, .photo-status-display');
                var input = inlineEdits[i].querySelector('input.edit-input, select.edit-input');
                
                if (input && display) {
                    if (input.style.display === 'none' || input.style.display === '') {
                        input.style.display = 'block';
                        input.focus();
                        display.style.display = 'none';
                        break;
                    } else {
                        input.style.display = 'none';
                        display.style.display = 'inline';
                    }
                }
            }
        }

        function toggleMyInlineEditField(photoId, field) {
            var row = document.querySelector('#myPhotosTable tr[data-photo-id="' + photoId + '"]');
            if (!row) {
                row = document.querySelector('.group-table tr[data-photo-id="' + photoId + '"]');
            }
            if (!row) return;
            
            var fieldContainer = row.querySelector('.inline-edit[data-field="' + field + '"]');
            if (!fieldContainer) return;
            
            var display = fieldContainer.querySelector('.photo-title-display, .photo-label-display, .photo-category-display, .photo-status-display, .photo-date-display');
            var input = fieldContainer.querySelector('input.edit-input, select.edit-input');
            
            if (input && display) {
                if (input.style.display === 'none' || input.style.display === '') {
                    input.style.display = 'block';
                    if (input.tagName === 'INPUT' && input.type !== 'date') {
                        input.focus();
                    }
                    display.style.display = 'none';
                } else {
                    input.style.display = 'none';
                    display.style.display = 'inline';
                }
            }
        }

        function togglePhotoStatus(photoId, currentStatus) {
            var newStatus = currentStatus == 1 ? 0 : 1;
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', 'is_private');
            formData.append('value', newStatus);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data && data.success) {
                    var row = document.querySelector('#myPhotosTable tr[data-photo-id="' + photoId + '"]');
                    if (!row) {
                        row = document.querySelector('.group-table tr[data-photo-id="' + photoId + '"]');
                    }
                    if (row) {
                        var badge = row.querySelector('span.badge');
                        if (badge) {
                            if (newStatus == 1) {
                                badge.className = 'badge bg-danger cursor-pointer';
                                badge.innerHTML = '<i class="fas fa-lock"></i> Private';
                                badge.onclick = function() { togglePhotoStatus(photoId, 1); };
                            } else {
                                badge.className = 'badge bg-success cursor-pointer';
                                badge.innerHTML = '<i class="fas fa-globe"></i> Public';
                                badge.onclick = function() { togglePhotoStatus(photoId, 0); };
                            }
                        }
                    }
                } else {
                    alert('Error: ' + (data ? data.message : 'Unknown error'));
                }
            })
            .catch(function(e) {
                console.error('Error:', e);
                alert('Terjadi kesalahan saat mengubah status');
            });
        }

        function saveMyInlineEditField(input, field, photoId) {
            var value = input.value;
            var row = input.closest('tr');
            var display = row.querySelector('.photo-title-display, .photo-label-display, .photo-category-display, .photo-status-display, .photo-date-display');
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    input.style.display = 'none';
                    if (display) {
                        display.style.display = 'inline';
                        if (field === 'created_at') {
                            var parts = value.split('-');
                            if (parts.length === 3) {
                                display.textContent = parts[2] + '/' + parts[1] + '/' + parts[0];
                            } else {
                                display.textContent = value;
                            }
                        } else {
                            display.textContent = value;
                        }
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    input.style.display = 'none';
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(e) {
                input.style.display = 'none';
                if (display) display.style.display = 'inline';
                console.error('Save error:', e);
            });
        }

        function handleMyInlineEditKey(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveMyInlineEditField(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-title-display, .photo-label-display, .photo-category-display, .photo-date-display');
                if (display) display.style.display = 'inline';
            }
        }

        function handleMyInlineEditKeyField(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveMyInlineEditField(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-title-display, .photo-label-display, .photo-category-display, .photo-status-display, .photo-date-display');
                if (display) display.style.display = 'inline';
            }
        }

        function handleMyCategorySelect(select, field, photoId) {
            var value = select.value;
            var row = select.closest('tr');
            var display = row.querySelector('.photo-category-display');
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    select.style.display = 'none';
                    if (display) {
                        display.style.display = 'inline';
                        var badgeHtml = '<span class="badge bg-secondary">' + value + '</span>';
                        display.innerHTML = badgeHtml;
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    select.style.display = 'none';
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(e) {
                select.style.display = 'none';
                if (display) display.style.display = 'inline';
                console.error('Save error:', e);
            });
        }

        function handleMyStatusSelect(select, field, photoId) {
            var value = select.value;
            var row = select.closest('tr');
            var display = row.querySelector('.photo-status-display');
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    select.style.display = 'none';
                    if (display) {
                        display.style.display = 'inline';
                        var badgeHtml = value == '1' ? '<span class="badge bg-danger"><i class="fas fa-lock"></i></span>' : '<span class="badge bg-success"><i class="fas fa-globe"></i></span>';
                        display.innerHTML = badgeHtml;
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    select.style.display = 'none';
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(e) {
                select.style.display = 'none';
                if (display) display.style.display = 'inline';
                console.error('Save error:', e);
            });
        }

        function saveNewAlbum(input, field, photoId) {
            var newAlbumName = input.value.trim();
            if (!newAlbumName) {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-album-display');
                if (display) display.style.display = 'inline';
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', 'album_id');
            formData.append('value', 'new_album:' + newAlbumName);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    input.style.display = 'none';
                    var row = input.closest('tr');
                    var display = row.querySelector('.photo-album-display');
                    if (display) {
                        display.style.display = 'inline';
                        display.innerHTML = '<i class="fas fa-folder me-1"></i>' + newAlbumName;
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    input.style.display = 'none';
                    var row = input.closest('tr');
                    var display = row.querySelector('.photo-album-display');
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(e) {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-album-display');
                if (display) display.style.display = 'inline';
                console.error('Save error:', e);
            });
        }
        
        function handleMyInlineEditKeyField(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveMyInlineEditField(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-title-display, .photo-category-display, .photo-status-display');
                if (display) display.style.display = 'inline';
            }
        }
        
        function quickSaveStatus(select, photoId) {
            var value = select.value;
            var row = select.closest('tr');
            var container = row.querySelector('.inline-edit[data-field="is_private"]');
            var display = container.querySelector('.photo-status-display');
            
            select.disabled = true;
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', 'is_private');
            formData.append('value', value);
            formData.append('action', 'ajax_edit');
            
            fetch('', { method: 'POST', body: formData })
            .then(function(r) { return r.text(); })
            .then(function(text) {
                select.disabled = false;
                try {
                    var data = JSON.parse(text);
                    if (data.success) {
                        window.location.href = '?action=myphotos&_t=' + Date.now();
                    } else {
                        alert('Error: ' + (data.message || 'Failed'));
                        select.style.display = 'none';
                        display.style.display = 'inline';
                    }
                } catch(e) {
                    window.location.href = '?action=myphotos&_t=' + Date.now();
                }
            })
            .catch(function(err) {
                select.disabled = false;
                alert('Network error: ' + err.message);
            });
        }

        function toggleMyAlbumEdit(photoId) {
            var row = document.querySelector('#myPhotosTable tr[data-photo-id="' + photoId + '"]');
            if (!row) return;
            
            var display = row.querySelector('.photo-album-display');
            var select = row.querySelector('select.edit-input');
            
            if (display && select) {
                if (select.style.display === 'none') {
                    select.style.display = 'inline-block';
                    select.focus();
                    display.style.display = 'none';
                } else {
                    select.style.display = 'none';
                    display.style.display = 'inline';
                }
            }
        }

        function handleMyAlbumSelect(select, field, photoId) {
            if (select.value === 'new_album') {
                select.style.display = 'none';
                var textInput = select.nextElementSibling;
                textInput.style.display = 'inline-block';
                textInput.focus();
                select.value = '';
            } else if (select.value) {
                saveMyInlineEdit(select, field, photoId);
            }
        }

        function handleNewMyAlbumKey(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveNewMyAlbum(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var display = input.previousElementSibling.previousElementSibling;
                display.style.display = 'inline';
            }
        }

        function saveNewMyAlbum(input, field, photoId) {
            var newAlbumName = input.value.trim();
            if (!newAlbumName) {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display = row.querySelector('.photo-album-display');
                if (display) display.style.display = 'inline';
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', 'album_id');
            formData.append('value', 'new_album:' + newAlbumName);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Album berhasil dibuat');
                    input.style.display = 'none';
                    var row = input.closest('tr');
                    var display = row.querySelector('.photo-album-display');
                    if (display) {
                        display.style.display = 'inline';
                        display.textContent = value;
                    }
                } else {
                    alert('Error: ' + data.message);
                    input.style.display = 'none';
                    var row = input.closest('tr');
                    var display = row.querySelector('.photo-album-display');
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
                input.style.display = 'none';
            });
        }

        function saveMyInlineEdit(input, field, photoId) {
            var value = input.value;
            var row = input.closest('tr');
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('action', 'ajax_edit');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Error: Invalid server response');
                    input.style.display = 'none';
                    var display = row.querySelector('.photo-title-display');
                    if (display) display.style.display = 'inline';
                    return null;
                }
            })
            .then(function(data) {
                if (!data) return;
                if (data.success) {
                    var flatView = document.getElementById('flatView');
                    var groupedView = document.getElementById('groupedView');
                    var currentMode = (flatView && flatView.style.display !== 'none') ? 'flat' : 'grouped';
                    
                    var expandedGroups = [];
                    if (groupedView) {
                        document.querySelectorAll('.group-table').forEach(function(el) {
                            if (el.style.display === 'table') {
                                expandedGroups.push(el.id.replace('group-', ''));
                            }
                        });
                    }
                    
                    var url = new URL(window.location);
                    if (currentMode === 'flat') {
                        url.searchParams.set('_r', Date.now());
                    } else {
                        if (expandedGroups.length > 0) {
                            url.searchParams.set('expanded', expandedGroups.join(','));
                        } else {
                            url.searchParams.delete('expanded');
                        }
                        url.searchParams.set('_r', Date.now());
                    }
                    window.location.href = url.toString();
                } else {
                    alert('Error: ' + data.message);
                    input.style.display = 'none';
                    var display;
                    if (field === 'photo_label') {
                        display = row.querySelector('.photo-label-display');
                    } else if (field === 'album_id') {
                        display = row.querySelector('.photo-album-display');
                    } else {
                        display = row.querySelector('.photo-title-display');
                    }
                    if (display) display.style.display = 'inline';
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
                input.style.display = 'none';
                var display;
                if (field === 'photo_label') {
                    display = row.querySelector('.photo-label-display');
                } else if (field === 'album_id') {
                    display = row.querySelector('.photo-album-display');
                } else {
                    display = row.querySelector('.photo-title-display');
                }
                if (display) display.style.display = 'inline';
            });
        }

        function handleMyInlineEditKey(event, input, field, photoId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveMyInlineEdit(input, field, photoId);
            } else if (event.key === 'Escape') {
                input.style.display = 'none';
                var row = input.closest('tr');
                var display;
                if (field === 'photo_label') {
                    display = row.querySelector('.photo-label-display');
                } else if (field === 'album_id') {
                    display = row.querySelector('.photo-album-display');
                } else {
                    display = row.querySelector('.photo-title-display');
                }
                if (display) display.style.display = 'inline';
            }
        }

        function reorderPhoto(photoId, direction) {
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('direction', direction);
            formData.append('action', 'reorder_photo');
            
            var expandedGroups = [];
            document.querySelectorAll('.group-table[style*="table"]').forEach(function(el) {
                var id = el.id.replace('group-', '');
                expandedGroups.push(id);
            });
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Non-JSON response:', text);
                        throw new Error('Invalid response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(function(data) {
                if (data.success) {
                    var flatView = document.getElementById('flatView');
                    var groupedView = document.getElementById('groupedView');
                    var currentMode = (flatView && flatView.style.display !== 'none') ? 'flat' : 'grouped';
                    
                    var expandedGroups = [];
                    document.querySelectorAll('.group-table').forEach(function(el) {
                        if (el.style.display === 'table') {
                            expandedGroups.push(el.id.replace('group-', ''));
                        }
                    });
                    
                    var url = new URL(window.location);
                    if (expandedGroups.length > 0) {
                        url.searchParams.set('expanded', expandedGroups.join(','));
                    } else {
                        url.searchParams.delete('expanded');
                    }
                    url.searchParams.set('_r', Date.now());
                    window.location.href = url.toString();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function addToMyGroup(photoId) {
            var modal = new bootstrap.Modal(document.getElementById('addToMyGroupModal'));
            modal.show();
        }

        function previewAddToMyGroupFiles() {
            var input = document.getElementById('addToMyGroupPhotos');
            var preview = document.getElementById('addToMyGroupPreview');
            preview.innerHTML = '';

            var files = input.files;
            var fileCount = files.length;

            if (fileCount > 0) {
                var countLabel = document.createElement('div');
                countLabel.className = 'mb-2';
                countLabel.innerHTML = '<strong>File dipilih:</strong> <span class="preview-count">' + fileCount + '</span>';
                preview.appendChild(countLabel);
            }

            var maxPreview = 20;
            for (var i = 0; i < Math.min(fileCount, maxPreview); i++) {
                var reader = new FileReader();
                reader.onload = (function(file) {
                    return function(e) {
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-thumb';
                        img.title = file.name;
                        preview.appendChild(img);
                    };
                })(files[i]);
                reader.readAsDataURL(files[i]);
            }
            
            if (fileCount > maxPreview) {
                var moreLabel = document.createElement('div');
                moreLabel.className = 'mb-2 text-muted';
                moreLabel.innerHTML = '<em>...dan ' + (fileCount - maxPreview) + ' file lainnya</em>';
                preview.appendChild(moreLabel);
            }
            
            var textEl = document.getElementById('addToMyGroupText');
            if (textEl) {
                textEl.innerHTML = '<i class="fas fa-check-circle text-success"></i> ' + fileCount + ' file dipilih';
            }
        }

        function showAddAlbumModal() {
            var modal = new bootstrap.Modal(document.getElementById('addAlbumModal'));
            modal.show();
        }
        
        function togglePhotoViewMode() {
            var groupedView = document.getElementById('groupedView');
            var flatView = document.getElementById('flatView');
            var viewModeText = document.getElementById('viewModeText');
            
            if (groupedView && flatView) {
                if (groupedView.style.display !== 'none') {
                    groupedView.style.display = 'none';
                    flatView.style.display = 'block';
                    viewModeText.textContent = 'All';
                } else {
                    groupedView.style.display = 'block';
                    flatView.style.display = 'none';
                    viewModeText.textContent = 'Grouped';
                }
            }
        }
        
        function toggleGroup(groupKey, skipHistory) {
            var table = document.getElementById('group-' + groupKey);
            var icon = document.getElementById('icon-' + groupKey);
            if (table) {
                var isHidden = table.style.display === 'none' || table.style.display === '';
                if (isHidden) {
                    table.style.display = 'table';
                    if (icon) { icon.classList.remove('fa-chevron-right'); icon.classList.add('fa-chevron-down'); }
                } else {
                    table.style.display = 'none';
                    if (icon) { icon.classList.remove('fa-chevron-down'); icon.classList.add('fa-chevron-right'); }
                }
                if (!skipHistory) {
                    var url = new URL(window.location);
                    var expanded = url.searchParams.get('expanded') || '';
                    var groups = expanded ? expanded.split(',') : [];
                    var idx = groups.indexOf(groupKey);
                    if (isHidden) {
                        if (idx === -1) groups.push(groupKey);
                    } else {
                        if (idx > -1) groups.splice(idx, 1);
                    }
                    url.searchParams.set('expanded', groups.join(','));
                    window.history.pushState({}, '', url.toString());
                }
            }
        }
        
        // My Photos Multi-Select Functions
        function toggleSelectAllMyPhotos() {
            var selectAll = document.getElementById('selectAllMyPhotos');
            if (!selectAll) return;
            
            var checkboxes = document.querySelectorAll('#myPhotosTable .my-photo-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            updateMyMultiButtons();
        }
        
        function toggleSelectAllInGroup(groupKey, checkbox) {
            var checkboxes = document.querySelectorAll('.group-checkbox-' + groupKey);
            checkboxes.forEach(function(cb) {
                cb.checked = checkbox.checked;
            });
            updateMyMultiButtons();
        }
        
        function updateMyMultiButtons() {
            var checkboxes = document.querySelectorAll('.my-photo-checkbox:checked');
            var multiEditBtn = document.getElementById('myMultiEditBtn');
            var multiDeleteBtn = document.getElementById('myMultiDeleteBtn');
            
            if (checkboxes.length > 0) {
                if (multiEditBtn) {
                    multiEditBtn.disabled = false;
                    multiEditBtn.innerHTML = '<i class="fas fa-edit me-1"></i> Multi Edit (' + checkboxes.length + ')';
                }
                if (multiDeleteBtn) {
                    multiDeleteBtn.disabled = false;
                    multiDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus (' + checkboxes.length + ')';
                }
            } else {
                if (multiEditBtn) {
                    multiEditBtn.disabled = true;
                    multiEditBtn.innerHTML = '<i class="fas fa-edit me-1"></i> Multi Edit';
                }
                if (multiDeleteBtn) {
                    multiDeleteBtn.disabled = true;
                    multiDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus Terpilih';
                }
            }
        }
        
        function showMyMultiEditModal() {
            var checkboxes = document.querySelectorAll('.my-photo-checkbox:checked');
            var ids = Array.from(checkboxes).map(function(cb) { return cb.value; });
            
            if (ids.length === 0) {
                alert('Pilih foto yang akan diedit');
                return;
            }
            
            document.getElementById('myMultiPhotoIds').value = ids.join(',');
            document.getElementById('myMultiEditCount').textContent = ids.length;
            
            var modal = new bootstrap.Modal(document.getElementById('myMultiEditModal'));
            modal.show();
        }
        
        function executeMyMultiEdit() {
            var form = document.getElementById('myMultiEditForm');
            var formData = new FormData(form);
            formData.append('action', 'multi_edit_photos');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('myMultiEditModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }
        
        function myMultiDeletePhotos() {
            var checkboxes = document.querySelectorAll('.my-photo-checkbox:checked');
            var ids = Array.from(checkboxes).map(function(cb) { return cb.value; });
            
            if (ids.length === 0) {
                alert('Pilih foto yang akan dihapus');
                return;
            }
            
            if (!confirm('Hapus ' + ids.length + ' foto yang dipilih?')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_ids', ids.join(','));
            formData.append('action', 'multi_delete_photos');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }
        
        function deleteGroup(groupKey, photoCount) {
            if (confirm('Hapus semua ' + photoCount + ' foto dalam album ini?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="photo_id" value="' + groupKey + '"><input type="hidden" name="delete_batch" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleSelectAllInGroup(groupKey, checkbox) {
            var checkboxes = document.querySelectorAll('.group-checkbox-' + groupKey);
            checkboxes.forEach(function(cb) {
                cb.checked = checkbox.checked;
            });
        }
        
        function editGroupTitle(groupKey) {
            var titleSpan = document.getElementById('gtitle-' + groupKey);
            var editInput = document.getElementById('gedit-' + groupKey);
            if (titleSpan && editInput) {
                titleSpan.classList.add('d-none');
                editInput.classList.remove('d-none');
                editInput.focus();
                editInput.select();
            }
        }
        
        function saveGroupTitle(groupKey, albumId) {
            var titleSpan = document.getElementById('gtitle-' + groupKey);
            var editInput = document.getElementById('gedit-' + groupKey);
            var newTitle = editInput.value.trim();
            var oldTitle = titleSpan.querySelector('strong').textContent;
            
            if (!newTitle) {
                alert('Nama album tidak boleh kosong');
                editInput.focus();
                return;
            }
            
            if (newTitle === oldTitle) {
                titleSpan.classList.remove('d-none');
                editInput.classList.add('d-none');
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('group_id', albumId || groupKey);
            formData.append('new_title', newTitle);
            formData.append('action', 'rename_group');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.text(); })
            .then(function(text) {
                try {
                    var data = JSON.parse(text);
                    if (data.success) {
                        titleSpan.classList.remove('d-none');
                        editInput.classList.add('d-none');
                        titleSpan.querySelector('strong').textContent = newTitle;
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Gagal mengubah'));
                        editInput.focus();
                    }
                } catch(e) {
                    titleSpan.classList.remove('d-none');
                    editInput.classList.add('d-none');
                    titleSpan.querySelector('strong').textContent = newTitle;
                }
            })
            .catch(function(err) {
                console.error(err);
                editInput.value = oldTitle;
                editInput.classList.add('d-none');
                titleSpan.classList.remove('d-none');
            });
        }

        // Share Photo functions
        var shareUsersCache = [];
        
        function sharePhoto(photoId) {
            document.getElementById('sharePhotoId').value = photoId;
            loadUsersForShare();
            loadSharedUsers(photoId);
            var modal = new bootstrap.Modal(document.getElementById('sharePhotoModal'));
            modal.show();
        }

        function loadUsersForShare() {
            var container = document.getElementById('shareUsersListContainer');
            if (container) {
                container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</div>';
            }
            
            console.log('Loading users...');
            
            fetch('?action=get_users')
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    var users = [];
                    if (data && data.success && Array.isArray(data.users)) {
                        users = data.users;
                    } else if (Array.isArray(data)) {
                        users = data;
                    } else {
                        console.error('Invalid response format:', data);
                        throw new Error('Invalid response format');
                    }
                    shareUsersCache = users;
                    renderShareUsersList(users);
                })
                .catch(function(err) {
                    console.error('Error loading users:', err);
                    var container = document.getElementById('shareUsersListContainer');
                    if (container) {
                        container.innerHTML = '<div class="text-danger text-center py-3">Error: ' + err.message + '</div>';
                    }
                });
        }

        function renderShareUsersList(users) {
            var container = document.getElementById('shareUsersListContainer');
            if (!container) return;
            
            if (!users || users.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-3"><i class="fas fa-users me-2"></i>Tidak ada user lain</div>';
                return;
            }

            container.innerHTML = users.map(function(user) {
                return '<div class="form-check py-1">' +
                    '<input type="checkbox" class="form-check-input share-user-checkbox" id="shareUser_' + user.id + '" value="' + user.id + '">' +
                    '<label class="form-check-label" for="shareUser_' + user.id + '">' +
                    '<i class="fas fa-user text-muted me-2"></i>' + user.username + '</label>' +
                '</div>';
            }).join('');
        }

        function filterShareUsers(query) {
            if (!query) {
                renderShareUsersList(shareUsersCache);
                return;
            }
            var filtered = shareUsersCache.filter(function(u) {
                return u.username.toLowerCase().includes(query.toLowerCase());
            });
            renderShareUsersList(filtered);
        }

        function loadSharedUsers(photoId) {
            var container = document.getElementById('currentSharedUsersList');
            if (container) {
                container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</div>';
            }
            
            fetch('?action=get_shares&photo_id=' + photoId)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(shares) {
                    var container = document.getElementById('currentSharedUsersList');
                    if (!container) return;
                    
                    console.log('Shares response:', shares);
                    
                    if (!shares || !Array.isArray(shares) || shares.length === 0) {
                        container.innerHTML = '<div class="text-muted text-center py-3">Belum ada user yang diundang</div>';
                        return;
                    }
                    
                    container.innerHTML = shares.map(function(share) {
                        return '<div class="list-group-item d-flex justify-content-between align-items-center py-2">' +
                            '<div><i class="fas fa-user me-2 text-primary"></i>' + (share.username || 'User #' + share.shared_with_user_id) + '</div>' +
                            '<button class="btn btn-sm btn-outline-danger" onclick="removeShare(' + photoId + ', ' + share.shared_with_user_id + ')">' +
                            '<i class="fas fa-times"></i></button>' +
                        '</div>';
                    }).join('');
                })
                .catch(function(err) {
                    console.error('Error loading shares:', err);
                    var container = document.getElementById('currentSharedUsersList');
                    if (container) {
                        container.innerHTML = '<div class="text-danger text-center py-3">Error: ' + err.message + '</div>';
                    }
                });
        }

        function addSelectedShareUsers() {
            var photoId = document.getElementById('sharePhotoId').value;
            var checkboxes = document.querySelectorAll('.share-user-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Pilih minimal satu user');
                return;
            }
            
            var userIds = Array.from(checkboxes).map(function(cb) { return cb.value; });
            var csrfToken = '<?= $csrfToken ?>';
            
            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('photo_id', photoId);
            formData.append('user_ids', userIds.join(','));
            formData.append('action', 'share_photo_multiple');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Berhasil berbagi foto ke ' + userIds.length + ' user');
                    loadSharedUsers(photoId);
                    document.querySelectorAll('.share-user-checkbox').forEach(function(cb) {
                        cb.checked = false;
                    });
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        function addShareUser() {
            var photoId = document.getElementById('sharePhotoId').value;
            var userId = document.getElementById('shareUserSelect').value;
            
            if (!userId) {
                alert('Pilih user terlebih dahulu');
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('user_id', userId);
            formData.append('action', 'share_photo');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Berhasil berbagi foto');
                    loadSharedUsers(photoId);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        function removeShare(photoId, userId) {
            if (!confirm('Hapus akses user ini?')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('photo_id', photoId);
            formData.append('user_id', userId);
            formData.append('action', 'remove_share');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    loadSharedUsers(photoId);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        // Album Share Functions
        var albumShareUsersCache = [];
        
        function shareAlbum(albumId, albumTitle) {
            document.getElementById('shareAlbumId').value = albumId;
            document.getElementById('shareAlbumName').textContent = albumTitle;
            loadAlbumUsersForShare();
            loadAlbumSharedUsers(albumId);
            var modal = new bootstrap.Modal(document.getElementById('shareAlbumModal'));
            modal.show();
        }

        function loadAlbumUsersForShare() {
            var container = document.getElementById('shareAlbumUsersListContainer');
            if (container) {
                container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</div>';
            }
            
            fetch('?action=get_users')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    var users = [];
                    if (data && data.success && Array.isArray(data.users)) {
                        users = data.users;
                    } else if (Array.isArray(data)) {
                        users = data;
                    }
                    albumShareUsersCache = users;
                    renderAlbumShareUsersList(users);
                })
                .catch(function(err) {
                    console.error(err);
                });
        }

        function renderAlbumShareUsersList(users) {
            var container = document.getElementById('shareAlbumUsersListContainer');
            if (!container) return;
            
            if (!users || users.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-3">Tidak ada user lain</div>';
                return;
            }

            container.innerHTML = users.map(function(user) {
                return '<div class="form-check py-1">' +
                    '<input type="checkbox" class="form-check-input album-share-user-checkbox" id="albumShareUser_' + user.id + '" value="' + user.id + '">' +
                    '<label class="form-check-label" for="albumShareUser_' + user.id + '">' +
                    '<i class="fas fa-user text-muted me-2"></i>' + user.username + '</label>' +
                '</div>';
            }).join('');
        }

        function loadAlbumSharedUsers(albumId) {
            var container = document.getElementById('currentAlbumSharedUsersList');
            if (container) {
                container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</div>';
            }
            
            fetch('?action=get_album_shares&album_id=' + encodeURIComponent(albumId))
                .then(function(response) { return response.json(); })
                .then(function(shares) {
                    var container = document.getElementById('currentAlbumSharedUsersList');
                    if (!container) return;
                    
                    if (!shares || shares.length === 0) {
                        container.innerHTML = '<div class="text-muted text-center py-3">Belum ada user yang diundang</div>';
                        return;
                    }
                    
                    container.innerHTML = shares.map(function(share) {
                        return '<div class="list-group-item d-flex justify-content-between align-items-center py-2">' +
                            '<div><i class="fas fa-user me-2 text-primary"></i>' + (share.username || 'User #' + share.shared_with_user_id) + '</div>' +
                            '<button class="btn btn-sm btn-outline-danger" onclick="removeAlbumShare(\'' + albumId + '\', ' + share.shared_with_user_id + ')">' +
                            '<i class="fas fa-times"></i></button>' +
                        '</div>';
                    }).join('');
                })
                .catch(function(err) {
                    console.error(err);
                });
        }

        function addSelectedAlbumShareUsers() {
            var albumId = document.getElementById('shareAlbumId').value;
            var checkboxes = document.querySelectorAll('.album-share-user-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Pilih minimal satu user');
                return;
            }
            
            var userIds = Array.from(checkboxes).map(function(cb) { return cb.value; });
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('album_id', albumId);
            formData.append('user_ids', userIds.join(','));
            formData.append('action', 'share_album');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Berhasil berbagi album ke ' + userIds.length + ' user');
                    loadAlbumSharedUsers(albumId);
                    document.querySelectorAll('.album-share-user-checkbox').forEach(function(cb) {
                        cb.checked = false;
                    });
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        function removeAlbumShare(albumId, userId) {
            if (!confirm('Hapus akses user ini?')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('album_id', albumId);
            formData.append('user_id', userId);
            formData.append('action', 'remove_album_share');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    loadAlbumSharedUsers(albumId);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }

        // Setup share user search listener
        document.addEventListener('DOMContentLoaded', function() {
            var shareUserSearch = document.getElementById('shareUserSearch');
            if (shareUserSearch) {
                shareUserSearch.addEventListener('input', function() {
                    filterShareUsers(this.value);
                });
            }
        });

        // User management functions
        function toggleSelectAllUsers() {
            var selectAll = document.getElementById('selectAllUsers');
            var checkboxes = document.querySelectorAll('.user-checkbox:not(:disabled)');
            checkboxes.forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            updateMultiUserButtons();
        }

        function updateMultiUserButtons() {
            var checkboxes = document.querySelectorAll('.user-checkbox:checked');
            var multiDeleteBtn = document.getElementById('multiDeleteUsersBtn');
            
            if (checkboxes.length > 0) {
                multiDeleteBtn.disabled = false;
                multiDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus (' + checkboxes.length + ')';
            } else {
                multiDeleteBtn.disabled = true;
                multiDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i> Hapus Terpilih';
            }
        }

        function multiDeleteUsers() {
            var checkboxes = document.querySelectorAll('.user-checkbox:checked');
            var ids = Array.from(checkboxes).map(function(cb) { return cb.value; });
            
            if (ids.length === 0) {
                alert('Pilih user yang akan dihapus');
                return;
            }
            
            if (!confirm('Hapus ' + ids.length + ' user yang dipilih?')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('csrf_token', '<?= $csrfToken ?>');
            formData.append('user_ids', ids.join(','));
            formData.append('action', 'multi_delete_users');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(function(err) {
                alert('Error: ' + err);
            });
        }
    </script>
</body>
</html>
