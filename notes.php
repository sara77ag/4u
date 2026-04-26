<?php
$pdo = new PDO('sqlite:database.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

session_start();

header('Content-Type: application/json');

// =======================
// ADD NOTE
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_note'])) {
$entity_type = $_POST['entity_type'] ?? null;
$entity_id   = $_POST['entity_id'] ?? null;
$text        = $_POST['note_text'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$entity_type || !$entity_id || !$text) {
    echo json_encode([ "success" => false,"message" => "Missing data" ]);
    exit;
}

    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $created_by = $stmtUser->fetchColumn() ?? 'Unknown';

    $property_id = $tenant_id = $landlord_id = $payment_id = $viewing_id = $date_id = $user_note_id = null;

    switch ($entity_type) {
        case 'property':
            $property_id = $entity_id;
            break;
        case 'tenant':
            $tenant_id = $entity_id;
            break;
        case 'landlord':
            $landlord_id = $entity_id;
            break;
        case 'payment':
            $payment_id = $entity_id;
            break;
        case 'viewing':
            $viewing_id = $entity_id;
            break;
        case 'date':
            $date_id = $entity_id;
            break;
        case 'importantdate':
            $date_id = $entity_id;
            break;
        case 'user':
            $user_note_id = $entity_id;
            break;
    }


    $stmt = $pdo->prepare("
        INSERT INTO notes (note_text, created_at, created_by, type, property_id, landlord_id, tenant_id, payment_id, viewing_id, date_id, user_id, archived)
        VALUES (?, datetime('now'), ?, ?,  ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([ $text, $created_by, $entity_type, $property_id, $landlord_id, $tenant_id, $payment_id, $viewing_id, $date_id, $user_note_id
    ]);

    echo json_encode([
        "success" => true,
        "created_at" => date('d-m-Y H:i:s'),
        "created_by" => $created_by,
        "note_text" => $text
    ]);

    exit;
}


// =======================
// GET NOTES
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $entity_type = $_GET['entity_type'] ?? null;
    $entity_id   = $_GET['entity_id'] ?? null;

    if (!$entity_type || !$entity_id) {
        echo json_encode([]);
        exit;
    }

    $where  = ['type = ?'];  
    $params = [$entity_type]; 

    switch ($entity_type) {
        case 'property':
            $where[] = "property_id = ?";
            $params[] = $entity_id;
            break;
        case 'tenant':
            $where[] = "tenant_id = ?";
            $params[] = $entity_id;
            break;
        case 'landlord':
            $where[] = "landlord_id = ?";
            $params[] = $entity_id;
            break;
        case 'payment':
            $where[] = "payment_id = ?";
            $params[] = $entity_id;
            break;
        case 'viewing':
            $where[] = "viewing_id = ?";
            $params[] = $entity_id;
            break;
        case 'date':
            $where[] = "date_id = ?";
            $params[] = $entity_id;
            break;
        case 'importantdate':
            $where[] = "date_id = ?";
            $params[] = $entity_id;
            break;
        case 'user':
            $where[] = "user_id = ?";
            $params[] = $entity_id;
            break;
        default:
            echo json_encode([]);
            exit;
    }

    $stmt = $pdo->prepare("
        SELECT note_id, note_text, created_at, created_by
        FROM notes
        WHERE " . implode(' AND ', $where) . "
        AND archived = 0
        ORDER BY datetime(created_at) DESC
    ");

    $stmt->execute($params);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// =======================
// DELETE 
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {

    $note_id = $_POST['note_id'] ?? null;

    if (!$note_id) {
        echo json_encode(["success" => false]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE notes SET archived = 1, date_archived = datetime('now') WHERE note_id = ?");
    $stmt->execute([$note_id]);

    echo json_encode(["success" => true]);
    exit;
}
