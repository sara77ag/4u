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
$colStmt = $pdo->query("PRAGMA table_info(files)");
foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $existingCols[] = $col['name'];
}
if (!in_array('user_id', $existingCols)) {
    $pdo->exec("ALTER TABLE files ADD COLUMN user_id INTEGER");
}
if (!in_array('payment_id', $existingCols)) {
    $pdo->exec("ALTER TABLE files ADD COLUMN payment_id INTEGER");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category    = $_POST['file_category'] ?? '';
    $property_id = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
    $tenant_id   = !empty($_POST['tenant_id'])   ? (int)$_POST['tenant_id']   : null;
    $landlord_id = !empty($_POST['landlord_id']) ? (int)$_POST['landlord_id'] : null;
    $payment_id  = !empty($_POST['payment_id'])  ? (int)$_POST['payment_id']  : null;
    $user_id     = !empty($_POST['user_id'])     ? (int)$_POST['user_id']     : null;
    $redirect    = array_key_exists('redirect', $_POST) ? $_POST['redirect'] : null;

    $isAjax = ($redirect === '' || $redirect === null);

    $allowed_categories = [
        'gas_cert', 'electric_cert', 'epc', 'id_document',
        'contract', 'other', 'ownership_proof',
        'payment_receipt', 'invoice',
    ];
    if (!in_array($category, $allowed_categories)) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Invalid category']); exit(); }
        die('Invalid category');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'File upload failed']); exit(); }
        die('File upload failed');
    }

    $file         = $_FILES['file'];
    $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed_exts)) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Invalid file type']); exit(); }
        die('Invalid file type — only PDF, JPG, PNG allowed');
    }

    if ($property_id)      $folder = "files/properties/$property_id";
    elseif ($tenant_id)    $folder = "files/tenants/$tenant_id";
    elseif ($landlord_id)  $folder = "files/landlords/$landlord_id";
    elseif ($payment_id)   $folder = "files/payments/$payment_id";
    elseif ($user_id)      $folder = "files/users/$user_id";
    else                   $folder = "files/other";

    $fullFolder = __DIR__ . '/' . $folder;
    if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }

    $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $fullPath   = $fullFolder . '/' . $filename;
    $storedPath = $folder . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Failed to save file']); exit(); }
        die('Failed to save file');
    }

    $stmt = $pdo->prepare("
        INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category,
                           property_id, tenant_id, landlord_id, payment_id, user_id, archived)
        VALUES (?, ?, ?, datetime('now'), ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $filename, strtoupper($ext), $storedPath, $category,
        $property_id, $tenant_id, $landlord_id, $payment_id, $user_id,
    ]);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'file_path' => $storedPath]);
        exit();
    }

    header("Location: $redirect");
    exit();
}
?>