<?php
$pdo = new PDO('sqlite:database.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

session_start();

header('Content-Type: application/json');

// =======================
// ADD NOTE (POST)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $property_id = $_POST['property_id'] ?? null;
    $text = $_POST['note_text'] ?? null;
    $user = $_SESSION['username'] ?? 'Unknown User';

    if (!$property_id || !$text) {
        echo json_encode([
            "success" => false,
            "message" => "Missing data"
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO notes (note_text, created_at, property_id, created_by, archived)
        VALUES (?, datetime('now'), ?, ?, 0)
    ");

    $stmt->execute([$text, $property_id, $user]);

    echo json_encode([
        "success" => true,
        "created_at" => date('Y-m-d H:i:s'),
        "created_by" => $user,
        "note_text" => $text
    ]);

    exit;
}


// =======================
// GET NOTES (GET)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $property_id = $_GET['property_id'] ?? null;

    if (!$property_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT note_text, created_at, created_by
        FROM notes
        WHERE property_id = ? AND archived = 0
        ORDER BY note_id DESC
    ");

    $stmt->execute([$property_id]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}