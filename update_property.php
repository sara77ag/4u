<?php
header('Content-Type: application/json');

$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
    }
    
    // ======================
    // Get JSON Input
    // ======================
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        echo json_encode(['error' => 'Invalid input']);
        exit;
        }
        
        $id = $input['id'];
        
        if (isset($input['type']) && $input['type'] === 'landlord') {
            $stmt = $pdo->prepare("
                UPDATE landlords SET
                    name = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    id_type = ?,
                    id_number = ?,
                    id_expiry_date = ?,
                    contact_method = ?
                WHERE landlord_id = ?
            ");
        
            $stmt->execute([
                $input['name']           ?? '',
                $input['phone']          ?? '',
                $input['email']          ?? '',
                $input['address']        ?? '',
                $input['id_type']        ?? '',
                $input['id_number']      ?? '',
                $input['id_expiry_date'] ?? '',
                $input['contact_method'] ?? '',
                $id
            ]);
        
            echo json_encode(['success' => true]);
            exit;
        }

// ======================
// UPDATE PROPERTIES
// ======================
$stmt = $pdo->prepare("
    UPDATE properties SET
        property_type = ?,
        monthly_rent = ?,
        room_count = ?,
        bathroom_count = ?,
        epc_rating = ?,
        allowed_pets = ?,
        has_garden = ?,
        council_band = ?,
        gas_cert_due = ?,
        electric_cert_due = ?,
        epc_due_date = ?,
        landlord_id_expiry_date = ?
    WHERE property_id = ?
");

$stmt->execute([
    $input['type']      ?? '',
    $input['price']     ?? 0,
    $input['rooms']     ?? 0,
    $input['bathrooms'] ?? 0,
    $input['epc']       ?? '',
    ($input['pets']   ?? '') === 'Yes' ? 1 : 0,
    ($input['garden'] ?? '') === 'Yes' ? 1 : 0,
    $input['council']   ?? '',
    $input['gas']       ?? '',
    $input['electric']  ?? '',
    $input['epcDate']   ?? '',
    $input['landlordId'] ?? '',
    $id
]);


// ======================
// UPDATE CONTRACT 
// ======================
$check = $pdo->prepare("SELECT contract_id FROM contracts WHERE property_id = ?");
$check->execute([$id]);
$contract = $check->fetch(PDO::FETCH_ASSOC);

if ($contract) {
    $stmt = $pdo->prepare("
        UPDATE contracts SET
            rent = ?,
            frequency = ?,
            contract_start = ?,
            contract_end = ?
        WHERE property_id = ?
    ");
        $stmt->execute([
        $input['rent']      ?? '',
        $input['frequency'] ?? '',
        $input['start']     ?? '',
        $input['end']       ?? '',
        $id
    ]);
}

echo json_encode(['success' => true]);