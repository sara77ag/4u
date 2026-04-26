<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$dbPath = __DIR__ . '/database.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$newPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 10);
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_date = date('now') WHERE user_id = ?");
$stmt->execute([$hashedPassword, $_SESSION['user_id']]);

$stmt = $pdo->prepare("SELECT email, name, username FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'mailer.php';
$sent = sendPasswordResetEmail($user['email'], $user['name'], $user['username'], $newPassword);

echo json_encode(['success' => $sent]);
?>