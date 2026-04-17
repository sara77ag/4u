<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/database.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category    = $_POST['file_category'] ?? '';
    $property_id = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
    $tenant_id   = !empty($_POST['tenant_id'])   ? (int)$_POST['tenant_id']   : null;
    $landlord_id = !empty($_POST['landlord_id']) ? (int)$_POST['landlord_id'] : null;
    $redirect    = $_POST['redirect'] ?? 'dashboard.php';

    $allowed_categories = ['gas_cert','electric_cert','epc','id_document','contract','other'];
    if (!in_array($category, $allowed_categories)) {
        die('Invalid category');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        die('File upload failed');
    }

    $file     = $_FILES['file'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed_exts)) {
        die('Invalid file type — only PDF, JPG, PNG allowed');
    }

    // Build folder path based on what it belongs to
    if ($property_id) {
        $folder = "files/properties/$property_id";
    } elseif ($tenant_id) {
        $folder = "files/tenants/$tenant_id";
    } elseif ($landlord_id) {
        $folder = "files/landlords/$landlord_id";
    } else {
        $folder = "files/other";
    }

    $fullFolder = __DIR__ . '/' . $folder;
    if (!is_dir($fullFolder)) {
        mkdir($fullFolder, 0755, true);
    }

    $filename  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $fullPath  = $fullFolder . '/' . $filename;
    $storedPath = $folder . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        die('Failed to save file');
    }

    $stmt = $pdo->prepare("
        INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category,
                           property_id, tenant_id, landlord_id, archived)
        VALUES (?, ?, ?, datetime('now'), ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $filename,
        strtoupper($ext),
        $storedPath,
        $category,
        $property_id,
        $tenant_id,
        $landlord_id
    ]);

    header("Location: $redirect");
    exit();
}
?>