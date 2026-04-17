<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/database.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('Invalid file ID');

$stmt = $pdo->prepare("SELECT * FROM files WHERE file_id = ? AND archived = 0");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) die('File not found');

$fullPath = __DIR__ . '/' . $file['file_path'];
if (!file_exists($fullPath)) die('File missing from disk');

$mimeTypes = [
    'PDF' => 'application/pdf',
    'JPG' => 'image/jpeg',
    'JPEG' => 'image/jpeg',
    'PNG' => 'image/png'
];

$mime = $mimeTypes[$file['file_type']] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit();
?>