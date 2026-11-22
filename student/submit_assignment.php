<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$assignmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
}

if (!$assignmentId || $assignmentId < 1) {
    http_response_code(400);
    exit('A valid assignment ID is required.');
}

$assignmentQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.description, a.attachment_path, a.due_date,
            c.course_code, c.course_name,
            (a.due_date <= NOW()) AS deadline_passed,
            s.id AS submission_id, s.file_path AS submitted_file_path,
            s.comment AS submission_comment, s.submitted_at,
            (s.submitted_at > a.due_date) AS submission_is_late
     FROM assignments a
     INNER JOIN courses c ON c.id = a.course_id
     INNER JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = :student_id
     LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
     WHERE a.id = :assignment_id AND a.status = \'published\'
     LIMIT 1'
);
$assignmentQuery->execute([
    'student_id' => $studentId,
    'assignment_id' => $assignmentId,
]);
$assignment = $assignmentQuery->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found.');
}

$errors = [];
$success = isset($_GET['submitted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!student_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } elseif ((int) $assignment['deadline_passed'] === 1) {
        $errors[] = 'The deadline has passed. New submissions and resubmissions are closed.';
    } elseif (!isset($_FILES['submission_file']) || !is_array($_FILES['submission_file'])) {
        $errors[] = 'Choose a PDF, DOCX, or ZIP file to submit.';
    } else {
        $file = $_FILES['submission_file'];
        $rawComment = $_POST['comment'] ?? '';
        if (!is_string($rawComment)) {
            $errors[] = 'The comment must be plain text.';
            $rawComment = '';
        }
        $comment = trim($rawComment);
        $maxBytes = 10 * 1024 * 1024;
        $allowedTypes = [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the 10 MB limit.',
                UPLOAD_ERR_NO_FILE => 'Choose a PDF, DOCX, or ZIP file to submit.',
                default => 'The file could not be uploaded. Please try again.',
            };
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'The uploaded file is invalid.';
        } elseif ($file['size'] < 1 || $file['size'] > $maxBytes) {
            $errors[] = 'The file must be no larger than 10 MB.';
        } else {
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

            if (!isset($allowedTypes[$extension]) || !in_array($mimeType, $allowedTypes[$extension], true)) {
                $errors[] = 'Only valid PDF, DOCX, and ZIP files are allowed.';
            }
        }

        if (!$errors) {
            $uploadDirectory = dirname(__DIR__) . '/uploads/submissions';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                $errors[] = 'The submission upload directory could not be created.';
            } elseif (!is_writable($uploadDirectory)) {
                $errors[] = 'The submission upload directory is not writable.';
            } else {
                $filename = bin2hex(random_bytes(16)) . '.' . $extension;
                $absolutePath = $uploadDirectory . '/' . $filename;
                $relativePath = 'uploads/submissions/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                    $errors[] = 'The file could not be saved. Please try again.';
                } else {
                    $oldFilePath = $assignment['submitted_file_path'];
                    try {
                        $pdo->beginTransaction();
                        $deadlineCheck = $pdo->prepare(
                            'SELECT a.id
                             FROM assignments a
                             INNER JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = :student_id
                             WHERE a.id = :assignment_id
                               AND a.status = \'published\'
                               AND a.due_date > NOW()
                             FOR UPDATE'
                        );
                        $deadlineCheck->execute([
                            'student_id' => $studentId,
                            'assignment_id' => $assignmentId,
                        ]);

                        if (!$deadlineCheck->fetchColumn()) {
                            $pdo->rollBack();
                            if (!unlink($absolutePath)) {
                                error_log('ASATS could not remove a rejected submission upload: ' . $absolutePath);
                            }
                            $errors[] = 'The deadline has passed or you are no longer enrolled in this course.';
                        } else {
                            $saveSubmission = $pdo->prepare(
                                'INSERT INTO submissions (assignment_id, student_id, file_path, comment, submitted_at)
                                 VALUES (:assignment_id, :student_id, :file_path, :comment, NOW())
                                 ON DUPLICATE KEY UPDATE
                                     file_path = VALUES(file_path),
                                     comment = VALUES(comment),
                                     submitted_at = VALUES(submitted_at)'
                            );
                            $saveSubmission->execute([
                                'assignment_id' => $assignmentId,
                                'student_id' => $studentId,
                                'file_path' => $relativePath,
                                'comment' => $comment,
                            ]);
                            $pdo->commit();

                            if ($oldFilePath) {
                                $oldFilename = basename(str_replace('\\', '/', (string) $oldFilePath));
                                $oldAbsolutePath = $uploadDirectory . '/' . $oldFilename;
                                if (is_file($oldAbsolutePath) && !unlink($oldAbsolutePath)) {
                                    error_log('ASATS could not remove a replaced submission file: ' . $oldAbsolutePath);
                                }
                            }

                           header('Location: assignment_view.php?id=' . rawurlencode((string) $assignmentId) . '&submitted=1');
                            exit;
                        }
                    } catch (PDOException $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if (is_file($absolutePath) && !unlink($absolutePath)) {
                            error_log('ASATS could not remove a failed submission upload: ' . $absolutePath);
                        }
                        error_log('ASATS submission database error: ' . $exception->getMessage());
                        $errors[] = 'Your submission could not be saved. Please try again.';
                    }
                }
            }
        }
    }
}

$attachmentUrl = null;
if (!empty($assignment['attachment_path'])) {
    $attachmentFilename = basename(str_replace('\\', '/', (string) $assignment['attachment_path']));
    $attachmentPath = dirname(__DIR__) . '/uploads/assignments/' . $attachmentFilename;
    if (is_file($attachmentPath)) {
        $attachmentUrl = '../uploads/assignments/' . rawurlencode($attachmentFilename);
    }
}
$isLate = (int) $assignment['submission_is_late'] === 1;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= student_h($assignment['title']) ?> | ASATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
    <nav class="mb-3" aria-label="Breadcrumb">
        <a href="assignments.php">Available assignments</a>
        <span class="text-secondary"> / Assignment details</span>
    </nav>

    <?php if ($success): ?>
        <div class="alert alert-success" role="status">Your submission was saved.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= student_h($error) ?></div>
    <?php endforeach; ?>

    <article class="card mb-4">
        <div class="card-body">
            <p class="text-secondary mb-1"><?= student_h($assignment['course_code'] . ' — ' . $assignment['course_name']) ?></p>
            <h1 class="h2"><?= student_h($assignment['title']) ?></h1>
            <p><strong>Due:</strong> <?= student_h((new DateTimeImmutable($assignment['due_date']))->format('M j, Y g:i A')) ?></p>
            <div class="mb-3"><?= nl2br(student_h($assignment['description'])) ?></div>
            <?php if ($attachmentUrl): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= student_h($attachmentUrl) ?>" download>
                    Download lecturer attachment
                </a>
            <?php elseif (!empty($assignment['attachment_path'])): ?>
                <p class="text-secondary mb-0">The lecturer attachment is currently unavailable.</p>
            <?php endif; ?>
        </div>
    </article>

    <section class="card">
        <div class="card-body">
            <h2 class="h4">Your submission</h2>
            <?php if ($assignment['submission_id'] !== null): ?>
                <p class="mb-2">
                    Submitted <?= student_h((new DateTimeImmutable($assignment['submitted_at']))->format('M j, Y g:i A')) ?>
                    <?php if ($isLate): ?>
                        <span class="badge text-bg-danger">Submitted late</span>
                    <?php endif; ?>
                </p>
                <p class="text-secondary">
                    Current file: <?= student_h(basename(str_replace('\\', '/', (string) $assignment['submitted_file_path']))) ?>
                </p>
                <?php if ($assignment['submission_comment'] !== null && $assignment['submission_comment'] !== ''): ?>
                    <p><strong>Your comment:</strong> <?= nl2br(student_h($assignment['submission_comment'])) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ((int) $assignment['deadline_passed'] === 1): ?>
                <div class="alert alert-warning mb-0">
                    The deadline has passed. <?= $assignment['submission_id'] !== null ? 'Your existing submission is retained.' : 'Submissions are closed.' ?>
                </div>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" value="<?= student_h($assignment['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= student_h(student_csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="submission_file">
                            <?= $assignment['submission_id'] !== null ? 'Upload a replacement file' : 'Choose your file' ?>
                        </label>
                        <input class="form-control" type="file" id="submission_file" name="submission_file"
                               accept=".pdf,.docx,.zip" required>
                        <div class="form-text">PDF, DOCX, or ZIP; maximum size 10 MB.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="comment">Comment (optional)</label>
                        <textarea class="form-control" id="comment" name="comment" rows="3"><?= student_h($_POST['comment'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <?= $assignment['submission_id'] !== null ? 'Replace submission' : 'Submit assignment' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
