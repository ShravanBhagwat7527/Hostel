<?php
require_once __DIR__ . '/../helpers.php';
require_method('POST');
start_session_safe();

$in = json_input();
$username = clean_str($in['username'] ?? '');
$password = (string) ($in['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(['ok' => false, 'error' => 'Username and password are required.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, username, password_hash, full_name FROM wardens WHERE username = ? AND is_active = 1');
$stmt->execute([$username]);
$warden = $stmt->fetch();

if (!$warden || !password_verify($password, $warden['password_hash'])) {
    json_response(['ok' => false, 'error' => 'Invalid username or password.'], 401);
}

// Prevent session fixation
session_regenerate_id(true);

$_SESSION['warden_id']       = $warden['id'];
$_SESSION['warden_username'] = $warden['username'];
$_SESSION['warden_name']     = $warden['full_name'];

json_response([
    'ok' => true,
    'warden' => [
        'id'        => $warden['id'],
        'username'  => $warden['username'],
        'full_name' => $warden['full_name'],
    ],
]);
