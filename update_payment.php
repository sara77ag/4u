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

$dateTime = $input['date_of_transaction'] ?? '';
if ($dateTime !== '') {
    $dateTime = str_replace('T', ' ', $dateTime);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $dateTime)) {
        $dateTime .= ':00';
    }
}

$stmt = $pdo->prepare("
    UPDATE transactions SET
        transaction_amount = ?,
        date_of_transaction = ?,
        payment_period = ?,
        next_due = ?,
        status = ?
    WHERE transaction_id = ?
");

$stmt->execute([
    $input['transaction_amount'] ?? 0,
    $dateTime,
    $input['payment_period'] ?? '',
    $input['next_due'] ?? '',
    $input['status'] ?? '',
    $input['id']
]);

echo json_encode(['success' => true]);