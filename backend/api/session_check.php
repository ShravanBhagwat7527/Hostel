<?php
require_once __DIR__ . '/../helpers.php';
require_method('GET');
start_session_safe();

if (empty($_SESSION['warden_id'])) {
    json_response(['ok' => true, 'logged_in' => false]);
}

json_response([
    'ok' => true,
    'logged_in' => true,
    'warden' => [
        'id'        => $_SESSION['warden_id'],
        'username'  => $_SESSION['warden_username'],
        'full_name' => $_SESSION['warden_name'],
    ],
]);
