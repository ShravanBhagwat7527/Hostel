<?php
require_once __DIR__ . '/../helpers.php';
require_method('POST');

$warden = require_warden();
$in = json_input();

$id     = (int) ($in['complaint_id'] ?? ($in['id'] ?? 0));
$status = clean_str($in['status'] ?? ($in['new_status'] ?? ''));
$remark = clean_str($in['remark'] ?? ($in['comment'] ?? ''));

$validStatuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

if ($id <= 0 || !in_array($status, $validStatuses, true)) {
    json_response(['ok' => false, 'error' => 'Invalid ticket id or status.'], 422);
}

if ($remark === '') {
    json_response(['ok' => false, 'error' => 'An actionable comment is required.'], 422);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT status FROM complaints WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'Ticket not found.'], 404);
    }

    $oldStatus = $row['status'];

    $updStmt = $pdo->prepare('UPDATE complaints SET status = ?, remark = ?, handled_by = ? WHERE id = ?');
    $updStmt->execute([$status, $remark, $warden['id'], $id]);

    $logStmt = $pdo->prepare(
        'INSERT INTO complaint_logs (complaint_id, warden_username, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)'
    );
    $logStmt->execute([$id, $warden['username'], $oldStatus, $status, $remark]);

    $pdo->commit();

    $ticket = $pdo->prepare('SELECT * FROM complaints WHERE id = ?');
    $ticket->execute([$id]);

    json_response(['ok' => true, 'ticket' => $ticket->fetch()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'error' => 'Failed to update ticket.'], 500);
}
