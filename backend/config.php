<?php
// ============================================================
// Database + app configuration.
// Edit these values for your environment, or (better) set them
// as real environment variables on your server and leave the
// getenv() fallbacks in place.
// ============================================================

return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'hostel_complaint_portal',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];
