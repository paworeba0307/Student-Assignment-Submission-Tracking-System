<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();
require_role('lecturer');

require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('config/db.php must provide a PDO connection in $pdo.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function lecturer_id(): int
{
    $id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
    if (!is_numeric($id) || (int) $id < 1) {
        throw new RuntimeException('The authenticated lecturer session does not contain a valid user ID.');
    }

    return (int) $id;
}

function lecturer_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lecturer_csrf_token(): string
{
    if (empty($_SESSION['lecturer_csrf_token'])) {
        $_SESSION['lecturer_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['lecturer_csrf_token'];
}

function lecturer_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(lecturer_csrf_token(), $token)) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }
}

function lecturer_redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function lecturer_page_start(string $title): void
{
    $safeTitle = lecturer_h($title);
    ob_start();
    require __DIR__ . '/../includes/logout_button.php';
    $logoutButton = ob_get_clean();
    $current  = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $aDash    = $current === 'dashboard.php' ? 'active' : '';
    $aAssign  = in_array($current, ['manage_assignments.php', 'create_assignment.php', 'view_submissions.php'], true) ? 'active' : '';
    $aTrack   = $current === 'tracking.php' ? 'active' : '';
    $aCourses = $current === 'manage_courses.php' ? 'active' : '';
    $userName = lecturer_h((string) ($_SESSION['user']['full_name'] ?? 'Lecturer'));

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} | ASATS Lecturer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/lecturer.css" rel="stylesheet">
</head>
<body class="bg-light">
<header class="lt-header">
    <div class="lt-inner">
        <a class="lt-brand" href="dashboard.php">
            <span class="lt-mark"><i></i><i></i><i></i></span>
            ASATS <small>Lecturer</small>
        </a>
        <nav class="lt-links" aria-label="Lecturer navigation">
            <a class="{$aDash}" href="dashboard.php">Dashboard</a>
            <a class="{$aAssign}" href="manage_assignments.php">Assignments</a>
            <a class="{$aCourses}" href="manage_courses.php">Courses</a>
            <a class="{$aTrack}" href="tracking.php">Submission tracking</a>
        </nav>
        <div class="lt-user">
            <span>{$userName}</span>
            {$logoutButton}
        </div>
    </div>
</header>
<main class="container py-4">
HTML;
}

function lecturer_page_end(): void
{
    echo <<<'HTML'
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
}

function lecturer_upload_attachment(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The attachment upload failed. Please try again.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Attachments must be no larger than 10 MB.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedMimeTypes = [
        'pdf' => ['application/pdf'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip',
        ],
        'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
    ];
    if (!isset($allowedMimeTypes[$extension]) || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Attachments must be PDF, DOCX or ZIP files.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file((string) $file['tmp_name']);
    if (!in_array($mimeType, $allowedMimeTypes[$extension], true)) {
        throw new RuntimeException('The attachment contents do not match its file type.');
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads/assignments';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The assignment upload directory could not be created.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file((string) $file['tmp_name'], $uploadDirectory . '/' . $fileName)) {
        throw new RuntimeException('The attachment could not be saved.');
    }

    return 'uploads/assignments/' . $fileName;
}

function lecturer_resolve_upload(string $relativePath, string $subdirectory): ?string
{
    $expectedPrefix = 'uploads/' . $subdirectory . '/';
    if (strpos($relativePath, $expectedPrefix) !== 0) {
        return null;
    }

    $uploadRoot = realpath(dirname(__DIR__) . '/uploads/' . $subdirectory);
    $resolvedPath = realpath(dirname(__DIR__) . '/' . $relativePath);
    if ($uploadRoot === false || $resolvedPath === false) {
        return null;
    }

    $prefix = rtrim($uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($resolvedPath, $prefix) !== 0 || !is_file($resolvedPath)) {
        return null;
    }

    return $resolvedPath;
}
