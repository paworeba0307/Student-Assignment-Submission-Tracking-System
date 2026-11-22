<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_login();
require_role('student');
require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('config/db.php must provide a PDO connection in $pdo.');
}

$studentId = filter_var($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if (!$studentId || $studentId < 1) {
    http_response_code(403);
    exit('A valid student session is required.');
}

function student_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function student_csrf_token(): string
{
    if (empty($_SESSION['student_csrf_token'])) {
        $_SESSION['student_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['student_csrf_token'];
}

function student_verify_csrf_token(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['student_csrf_token'])
        && hash_equals($_SESSION['student_csrf_token'], $token);
}
