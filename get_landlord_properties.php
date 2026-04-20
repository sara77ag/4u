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

$landlord_id = $_GET['landlord_id'] ?? null;

if (!$landlord_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        p.property_id,
        p.property_address,
        p.property_type,
        p.monthly_rent,
        t.name AS tenant_name,
        c.contract_start,
        c.contract_end,
        c.rent AS contract_rent,
        CASE WHEN c.contract_id IS NOT NULL THEN 'occupied' ELSE 'available' END AS status
    FROM properties p
    LEFT JOIN contracts c ON c.property_id = p.property_id
        AND date('now') BETWEEN c.contract_start AND c.contract_end
    LEFT JOIN tenants t ON t.tenant_id = c.tenant_id
    WHERE p.landlord_id = ?
    AND p.archived = 0
");

$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($properties);