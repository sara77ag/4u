<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$dbPath = __DIR__ . '/database.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$tenant_id = $_GET['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        c.contract_id,
        c.contract_start,
        c.contract_end,
        c.rent,
        c.frequency,
        p.property_address,
        l.name AS landlord_name,
        CASE
            WHEN date('now') BETWEEN c.contract_start AND c.contract_end THEN 'current'
            WHEN date('now') > c.contract_end THEN 'previous'
            ELSE 'upcoming'
        END AS status
    FROM contracts c
    JOIN properties p ON p.property_id = c.property_id
    LEFT JOIN landlords l ON l.landlord_id = p.landlord_id
    WHERE c.tenant_id = ?
    ORDER BY c.contract_start DESC
");

$stmt->execute([$tenant_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));