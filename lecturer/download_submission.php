<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$submissionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$submissionId) {
    http_response_code(400);
    exit('A submission ID is required.');
}

$query = $pdo->prepare(
    'SELECT s.file_path, a.title
     FROM submissions s
     JOIN assignments a ON a.id = s.assignment_id
     WHERE s.id = :submission_id AND a.lecturer_id = :lecturer_id'
);
$query->execute(['submission_id' => $submissionId, 'lecturer_id' => lecturer_id()]);
$submission = $query->fetch(PDO::FETCH_ASSOC);
if (!$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

$filePath = lecturer_resolve_upload((string) $submission['file_path'], 'submissions');
if ($filePath === null) {
    http_response_code(404);
    exit('The submitted file is not available.');
}

$downloadName = basename($filePath);
header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
