<?php

$pdo = new PDO('sqlite:' . __DIR__ . '/database.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS files (
        file_id       INTEGER PRIMARY KEY AUTOINCREMENT,
        file_name     TEXT NOT NULL,
        file_type     TEXT NOT NULL,
        file_path     TEXT NOT NULL,
        uploaded_at   TEXT NOT NULL,
        file_category TEXT NOT NULL,
        property_id   INTEGER,
        tenant_id     INTEGER,
        landlord_id   INTEGER,
        user_id       INTEGER,
        payment_id    INTEGER,
        archived      INTEGER NOT NULL DEFAULT 0
    )
");

$existingCols = [];
foreach ($pdo->query("PRAGMA table_info(files)")->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $existingCols[] = $col['name'];
}
if (!in_array('user_id', $existingCols)) {
    $pdo->exec("ALTER TABLE files ADD COLUMN user_id INTEGER");
}
if (!in_array('payment_id', $existingCols)) {
    $pdo->exec("ALTER TABLE files ADD COLUMN payment_id INTEGER");
}

header('Content-Type: application/json');

// GET files    
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $where  = '';
    $param  = 0;

    if (!empty($_GET['property_id'])) {
        $where = 'property_id = ?';
        $param = (int)$_GET['property_id'];
    } elseif (!empty($_GET['landlord_id'])) {
        $where = 'landlord_id = ?';
        $param = (int)$_GET['landlord_id'];
    } elseif (!empty($_GET['tenant_id'])) {
        $where = 'tenant_id = ?';
        $param = (int)$_GET['tenant_id'];
    } elseif (!empty($_GET['payment_id'])) {
        $where = 'payment_id = ?';
        $param = (int)$_GET['payment_id'];
    } elseif (!empty($_GET['user_id'])) {
        $where = 'user_id = ?';
        $param = (int)$_GET['user_id'];
    }

    if (!$where || !$param) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT file_id, file_name, file_type, file_path, file_category, uploaded_at
        FROM files
        WHERE $where AND archived = 0
        ORDER BY file_id ASC
    ");
    $stmt->execute([$param]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// POST: delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'delete') {
        $fileId = (int)($input['file_id'] ?? 0);
        if (!$fileId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing file_id']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT file_path FROM files WHERE file_id = ?");
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $fullPath = __DIR__ . '/' . $row['file_path'];
            if (file_exists($fullPath)) unlink($fullPath);
        }

        $pdo->prepare("DELETE FROM files WHERE file_id = ?")->execute([$fileId]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}