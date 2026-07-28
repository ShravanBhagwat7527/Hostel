<?php
// ============================================================
// Run this ONCE from the command line to create a warden login,
// after you've imported database/schema.sql:
//
//   php setup_warden.php <username> <password> "<Full Name>"
//
// Example:
//   php setup_warden.php warden1 "S3cure-Pass!" "Mr. R. Kulkarni"
//
// Password hashing uses PHP's password_hash() (bcrypt), so a
// real hash is generated on your own server rather than shipped
// in the SQL file.
// ============================================================

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/db.php';

[, $username, $password, $fullName] = array_pad($argv, 4, null);

if (!$username || !$password || !$fullName) {
    echo "Usage: php setup_warden.php <username> <password> \"<Full Name>\"\n";
    exit(1);
}

$pdo = db();
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    'INSERT INTO wardens (username, password_hash, full_name) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name)'
);
$stmt->execute([$username, $hash, $fullName]);

echo "Warden '{$username}' created/updated successfully.\n";
