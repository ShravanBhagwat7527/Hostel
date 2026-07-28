<?php
require_once __DIR__ . '/../helpers.php';
require_method('POST');

$warden = require_warden();
$in = json_input();

$id     = (int) ($in['id'] ?? 0);
$status = clean_str($in['status'] ?? '');
$remark = clean_str($in['remark'] ?? '');

$validStatuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

if ($id <= 0 || !in_array($status, $validStatuses, true)) {
    json_response(['ok' => false, 'error' => 'Invalid ticket id or status.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('UPDATE complaints SET status = ?, remark = ?, handled_by = ? WHERE id = ?');
$stmt->execute([$status, $remark, $warden['id'], $id]);

if ($stmt->rowCount() === 0) {
    json_response(['ok' => false, 'error' => 'Ticket not found.'], 404);
}

$ticket = $pdo->prepare('SELECT * FROM complaints WHERE id = ?');
$ticket->execute([$id]);

json_response(['ok' => true, 'ticket' => $ticket->fetch()]);
