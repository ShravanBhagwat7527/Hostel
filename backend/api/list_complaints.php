<?php
require_once __DIR__ . '/../helpers.php';
require_method('GET');

$pdo  = db();
$mode = clean_str($_GET['mode'] ?? 'student');

if ($mode === 'warden') {
    // Only logged-in wardens may see the full ticket list.
    require_warden();

    $status   = clean_str($_GET['status'] ?? 'All');
    $category = clean_str($_GET['category'] ?? 'All');
    $priority = clean_str($_GET['priority'] ?? 'All');
    $search   = clean_str($_GET['search'] ?? '');

    $where  = [];
    $params = [];

    if ($status !== 'All')   { $where[] = 'status = ?';   $params[] = $status; }
    if ($category !== 'All') { $where[] = 'category = ?'; $params[] = $category; }
    if ($priority !== 'All') { $where[] = 'priority = ?'; $params[] = $priority; }
    if ($search !== '') {
        $where[] = '(student_name LIKE ? OR room_number LIKE ? OR ticket_no LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like);
    }

    $sql = 'SELECT * FROM complaints';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    $counts = $pdo->query(
        "SELECT
            SUM(status = 'Pending') AS pending,
            SUM(status = 'In Progress') AS in_progress,
            SUM(status = 'Resolved') AS resolved,
            SUM(priority = 'Urgent' AND status NOT IN ('Resolved','Rejected')) AS urgent_open
         FROM complaints"
    )->fetch();

    json_response([
        'ok' => true,
        'tickets' => $tickets,
        'counts' => [
            'pending'     => (int) $counts['pending'],
            'inProgress'  => (int) $counts['in_progress'],
            'resolved'    => (int) $counts['resolved'],
            'urgentOpen'  => (int) $counts['urgent_open'],
        ],
    ]);
}

// ---- student mode: only returns tickets matching name/room, never the whole table ----
$lookup = clean_str($_GET['lookup'] ?? '');

if ($lookup === '') {
    json_response(['ok' => true, 'tickets' => []]);
}

$like = "%$lookup%";
$stmt = $pdo->prepare(
    'SELECT * FROM complaints WHERE student_name LIKE ? OR room_number LIKE ? ORDER BY created_at DESC'
);
$stmt->execute([$like, $like]);

json_response(['ok' => true, 'tickets' => $stmt->fetchAll()]);
