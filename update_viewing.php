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
    UPDATE viewing_bookings SET
        viewer_name = ?,
        viewer_phone = ?,
        status = ?,
        date_time = ?,
        user_id = ?,
        email = ?

    WHERE booking_id = ?
");

$stmt->execute([
    $input['viewer_name'] ?? '',
    $input['viewer_phone'] ?? '',
    $input['status'] ?? '',
    $input['date_time'] ?? '',
    $input['user_id'] ?? '',
    $input['email'] ?? '',
    $input['id']
]);

echo json_encode(['success' => true]);