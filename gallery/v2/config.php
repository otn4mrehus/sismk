<?php
define('DB_HOST', '_HOST-DB_');
define('DB_USER', '_USER-DB_');
define('DB_PASS', '_PASS-DB_');
define('DB_NAME', '_DB_');

$conn = null;

function dbConnect() {
    global $conn;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Koneksi database gagal: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

function dbQuery($sql, $params = []) {
    $conn = dbConnect();
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query error: " . $conn->error);
    }
    if (!empty($params)) {
        $types = '';
        $values = [];
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    return $stmt;
}

function dbGetAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

function dbGetOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function dbInsert($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    $insertId = $stmt->insert_id;
    $stmt->close();
    return $insertId;
}

function dbUpdate($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function dbGetLastId() {
    $conn = dbConnect();
    return $conn->insert_id;
}
