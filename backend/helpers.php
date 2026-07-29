<?php
// ============================================================
// Shared helpers for the API endpoints.
// ============================================================

require_once __DIR__ . '/db.php';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
}

function start_session_safe(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function require_warden(): array
{
    start_session_safe();
    if (empty($_SESSION['warden_id'])) {
        json_response(['ok' => false, 'error' => 'Not logged in.'], 401);
    }
    return [
        'id'        => $_SESSION['warden_id'],
        'username'  => $_SESSION['warden_username'],
        'full_name' => $_SESSION['warden_name'],
    ];
}

// Generates the next ticket number for the current year, e.g. HC-2026-0007.
// Uses a dedicated counter table + row lock so two simultaneous
// submissions can never collide.
function next_ticket_no(PDO $pdo): string
{
    $year = (int) date('Y');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM ticket_counters WHERE year = ? FOR UPDATE');
        $stmt->execute([$year]);
        $row = $stmt->fetch();

        if ($row === false) {
            $pdo->prepare('INSERT INTO ticket_counters (year, last_number) VALUES (?, 1)')->execute([$year]);
            $next = 1;
        } else {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE ticket_counters SET last_number = ? WHERE year = ?')->execute([$next, $year]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return sprintf('HC-%d-%04d', $year, $next);
}

function clean_str($val): string
{
    return trim((string) $val);
}

// Rate limiting: max 5 complaints per hour per IP
function check_rate_limit(PDO $pdo, string $ip): void
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE ip_address = ? AND created_at >= NOW() - INTERVAL 1 HOUR");
    $stmt->execute([$ip]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= 5) {
        json_response(['ok' => false, 'error' => 'Rate limit exceeded. Try again later.'], 429);
    }
}

// Handle secure file upload
function handle_file_upload(array $file): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if ($file['size'] > 5242880) { // 5MB limit
        json_response(['ok' => false, 'error' => 'File size exceeds 5MB limit.'], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!array_key_exists($mime, $allowedMimes)) {
        json_response(['ok' => false, 'error' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.'], 422);
    }

    $ext = $allowedMimes[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    
    // Relative path to save to DB
    $relPath = 'uploads/' . $filename;
    
    // Absolute path on disk
    $targetPath = __DIR__ . '/uploads/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        json_response(['ok' => false, 'error' => 'Failed to save uploaded file.'], 500);
    }

    return $relPath;
}
