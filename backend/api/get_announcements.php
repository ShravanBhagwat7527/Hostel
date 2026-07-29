<?php
require_once '../helpers.php';
require_once '../db.php';

header('Content-Type: application/json');

try {
    $pdo = db();
    // Only fetch announcements where expires_at is in the future
    $stmt = $pdo->query("
        SELECT id, title, message, priority_tag, expires_at, created_at 
        FROM announcements 
        WHERE expires_at >= NOW() 
        ORDER BY 
            CASE WHEN priority_tag = 'Urgent' THEN 1 ELSE 2 END,
            created_at DESC
    ");
    
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'count' => count($announcements),
        'data' => $announcements
    ]);
} catch (PDOException $e) {
    json_response(['ok' => false, 'error' => 'Failed to load announcements.'], 500);
}
