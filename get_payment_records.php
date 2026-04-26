<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([]);
    exit;
}

$tenant_id = $_GET['tenant_id'] ?? null;
$property_id = $_GET['property_id'] ?? null;

if (!$tenant_id || !$property_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        transaction_id,
        transaction_amount,
        date_of_transaction,
        payment_period,
        next_due,
        status
    FROM transactions
    WHERE tenant_id = ?
      AND property_id = ?
      AND archived = 0
    ORDER BY datetime(date_of_transaction) DESC
");

$stmt->execute([$tenant_id, $property_id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));