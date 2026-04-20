<?php
header('Content-Type: application/json');

$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([]);
    exit();
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit();
}

$like = '%' . $q . '%';
$results = [];

// Properties
$stmt = $pdo->prepare("
    SELECT property_id AS id, property_address AS label, property_type AS sub
    FROM properties
    WHERE archived = 0 AND property_address LIKE ?
    LIMIT 5
");
$stmt->execute([$like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = ['type' => 'property', 'id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'url' => 'properties.php'];
}

// Landlords
$stmt = $pdo->prepare("
    SELECT landlord_id AS id, name AS label, email AS sub
    FROM landlords
    WHERE archived = 0 AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
    LIMIT 5
");
$stmt->execute([$like, $like, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = ['type' => 'landlord', 'id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'url' => 'landlords.php'];
}

// Tenants
$stmt = $pdo->prepare("
    SELECT tenant_id AS id, name AS label, email AS sub
    FROM tenants
    WHERE archived = 0 AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
    LIMIT 5
");
$stmt->execute([$like, $like, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = ['type' => 'tenant', 'id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'url' => 'tenants.php'];
}

// Viewing bookings
$stmt = $pdo->prepare("
    SELECT booking_id AS id, viewer_name AS label, email AS sub
    FROM viewing_bookings
    WHERE archived = 0 AND (viewer_name LIKE ? OR email LIKE ?)
    LIMIT 5
");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = ['type' => 'viewing', 'id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'url' => 'viewing.php'];
}

echo json_encode($results);