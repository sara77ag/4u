<?php
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dbPath = __DIR__ . '/database.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE tenants SET
        name           = ?,
        phone          = ?,
        email          = ?,
        id_type        = ?,
        id_number      = ?,
        id_expiry_date = ?,
        contact_method = ?
    WHERE tenant_id = ?
");

$stmt->execute([
    $input['name']           ?? '',
    $input['phone']          ?? '',
    $input['email']          ?? '',
    $input['id_type']        ?? '',
    $input['id_number']      ?? '',
    $input['id_expiry_date'] ?? '',
    $input['contact_method'] ?? '',
    $input['id']
]);

echo json_encode(['success' => true]);
