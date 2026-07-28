<?php
require_once __DIR__ . '/../helpers.php';
require_method('POST');

$in = json_input();

$name  = clean_str($in['studentName'] ?? '');
$room  = clean_str($in['roomNumber'] ?? '');
$block = clean_str($in['block'] ?? '') ?: 'Not specified';
$desc  = clean_str($in['description'] ?? '');
$category = clean_str($in['category'] ?? '');
$priority = clean_str($in['priority'] ?? 'Medium');

$validCategories = ['Electrical','Plumbing','Furniture','Wi-Fi / Internet','Water Supply','Cleanliness / Pest','Security / Safety','Mess / Food','Other'];
$validPriorities = ['Low','Medium','High','Urgent'];

$errors = [];
if ($name === '')                       $errors[] = 'Name is required.';
if ($room === '')                       $errors[] = 'Room number is required.';
if (mb_strlen($desc) < 8)               $errors[] = 'Description must be at least 8 characters.';
if (!in_array($category, $validCategories, true)) $errors[] = 'Invalid category.';
if (!in_array($priority, $validPriorities, true)) $errors[] = 'Invalid priority.';

if ($errors) {
    json_response(['ok' => false, 'error' => implode(' ', $errors)], 422);
}

$pdo = db();

try {
    $ticketNo = next_ticket_no($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO complaints (ticket_no, student_name, room_number, block, category, priority, description, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "Pending")'
    );
    $stmt->execute([$ticketNo, $name, $room, $block, $category, $priority, $desc]);

    $id = (int) $pdo->lastInsertId();
    $ticket = $pdo->prepare('SELECT * FROM complaints WHERE id = ?');
    $ticket->execute([$id]);

    json_response(['ok' => true, 'ticket' => $ticket->fetch()], 201);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => "Couldn't save your ticket. Please try again."], 500);
}
