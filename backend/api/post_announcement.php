<?php
require_once '../helpers.php';
require_once '../db.php';

$warden = require_warden(); // This handles session and returns warden details

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$priority_tag = $_POST['priority_tag'] ?? 'General';
$expires_at = $_POST['expires_at'] ?? ''; // Expects format: YYYY-MM-DD HH:MM:SS or HTML5 datetime-local

if (empty($title) || empty($message) || empty($expires_at)) {
    json_response(['ok' => false, 'error' => 'Title, message, and expiration date are required.'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO announcements (warden_username, title, message, priority_tag, expires_at)
        VALUES (:username, :title, :message, :priority, :expires_at)
    ");
    
    $stmt->execute([
        ':username'   => $warden['username'],
        ':title'      => $title,
        ':message'    => $message,
        ':priority'   => $priority_tag,
        ':expires_at' => date('Y-m-d H:i:s', strtotime($expires_at))
    ]);

    json_response(['ok' => true, 'message' => 'Announcement broadcasted successfully.']);
} catch (PDOException $e) {
    json_response(['ok' => false, 'error' => 'Database error occurred.'], 500);
}
