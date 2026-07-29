<?php
require_once __DIR__ . '/../helpers.php';
require_method('POST');

// We are now accepting multipart/form-data
$in = $_POST;

$botCheck = clean_str($in['bot_check'] ?? '');
if ($botCheck !== '') {
    // Honeypot triggered. Silently ignore bot to baffle them.
    json_response(['ok' => true], 200);
}

$pdo = db();
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Rate limiting
check_rate_limit($pdo, $ip);

$name  = clean_str($in['studentName'] ?? '');
$room  = clean_str($in['roomNumber'] ?? '');
$block = clean_str($in['block'] ?? '') ?: 'Not specified';
$desc  = clean_str($in['description'] ?? '');
$category = clean_str($in['category'] ?? '');
$priority = clean_str($in['priority'] ?? 'Medium');

$validCategories = ['Electrical','Plumbing','Carpentry','Wi-Fi / Internet','Water Supply','Cleanliness / Pest','Cleanliness','Security / Safety','Mess / Food','Furniture','Other'];
$validPriorities = ['Low','Medium','High','Emergency','Urgent'];

$errors = [];
if ($name === '')                       $errors[] = 'Name is required.';
if ($room === '')                       $errors[] = 'Room number is required.';
if (mb_strlen($desc) < 8)               $errors[] = 'Description must be at least 8 characters.';
if (!in_array($category, $validCategories, true)) $errors[] = 'Invalid category.';
if (!in_array($priority, $validPriorities, true)) $errors[] = 'Invalid priority.';

if ($errors) {
    json_response(['ok' => false, 'error' => implode(' ', $errors)], 422);
}

// Handle optional file upload
$imagePath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imagePath = handle_file_upload($_FILES['photo']);
}

try {
    $ticketNo = next_ticket_no($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO complaints (ticket_no, student_name, room_number, block, category, priority, description, status, image_path, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, "Pending", ?, ?)'
    );
    $stmt->execute([$ticketNo, $name, $room, $block, $category, $priority, $desc, $imagePath, $ip]);

    $complaintId = (int) $pdo->lastInsertId();

    // Insert initial audit log
    $logStmt = $pdo->prepare(
        'INSERT INTO complaint_logs (complaint_id, old_status, new_status, comment) VALUES (?, ?, ?, ?)'
    );
    $logStmt->execute([$complaintId, 'Pending', 'Pending', 'Ticket created by student.']);

    $pdo->commit();

    // Fetch the created ticket to return
    $ticket = $pdo->prepare('SELECT * FROM complaints WHERE id = ?');
    $ticket->execute([$complaintId]);

    json_response(['ok' => true, 'ticket' => $ticket->fetch()], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'error' => "Couldn't save your ticket. Please try again."], 500);
}
