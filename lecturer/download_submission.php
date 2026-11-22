<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$submissionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$submissionId) {
    http_response_code(400);
    exit('A submission ID is required.');
}

$query = $pdo->prepare(
    'SELECT s.file_path, a.title, u.full_name
     FROM submissions s
     JOIN assignments a ON a.id = s.assignment_id
     JOIN users u ON u.id = s.student_id
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

$ext          = strtolower(pathinfo((string) $submission['file_path'], PATHINFO_EXTENSION));
$base         = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $submission['full_name'] . ' ' . $submission['title']), '_');
$base         = substr($base !== '' ? $base : 'submission', 0, 60);
$downloadName = $ext !== '' ? $base . '.' . $ext : $base;
