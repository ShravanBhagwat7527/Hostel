<?php
require_once __DIR__ . '/../helpers.php';
require_method('POST');
start_session_safe();

$_SESSION = [];
session_destroy();

json_response(['ok' => true]);
