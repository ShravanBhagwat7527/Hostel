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
            SUM(priority IN ('Emergency','Urgent') AND status NOT IN ('Resolved','Rejected')) AS urgent_open
         FROM complaints"
    )->fetch();

    $topCatStmt = $pdo->query(
        "SELECT category FROM complaints GROUP BY category ORDER BY COUNT(*) DESC LIMIT 1"
    );
    $mostCommonCategory = $topCatStmt->fetchColumn() ?: 'N/A';

    json_response([
        'ok' => true,
        'tickets' => $tickets,
        'counts' => [
            'pending'     => (int) $counts['pending'],
            'inProgress'  => (int) $counts['in_progress'],
            'resolved'    => (int) $counts['resolved'],
            'urgentOpen'  => (int) $counts['urgent_open'],
        ],
        'analytics' => [
            'total_open' => (int) $counts['pending'],
            'total_in_progress' => (int) $counts['in_progress'],
            'total_resolved' => (int) $counts['resolved'],
            'most_common_category' => $mostCommonCategory
        ]
    ]);
}

// ---- student mode: Tighten privacy, require exact ticket number AND room number ----
$lookupTicket = clean_str($_GET['lookup_ticket'] ?? '');
$roomNumber = clean_str($_GET['room_number'] ?? '');

if ($lookupTicket === '' || $roomNumber === '') {
    json_response(['ok' => true, 'tickets' => []]);
}

$stmt = $pdo->prepare(
    'SELECT * FROM complaints WHERE ticket_no = ? AND room_number = ? ORDER BY created_at DESC'
);
$stmt->execute([$lookupTicket, $roomNumber]);
$tickets = $stmt->fetchAll();

// If we found the ticket, attach its history
if (count($tickets) > 0) {
    // Only one ticket should match, but loop if multiple just in case
    foreach ($tickets as &$ticket) {
        $logStmt = $pdo->prepare('SELECT * FROM complaint_logs WHERE complaint_id = ? ORDER BY created_at ASC');
        $logStmt->execute([$ticket['id']]);
        $ticket['history'] = $logStmt->fetchAll();
    }
}

json_response(['ok' => true, 'tickets' => $tickets]);
