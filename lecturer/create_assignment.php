<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$lecturerId = lecturer_id();
$coursesQuery = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE lecturer_id = :lecturer_id ORDER BY course_code');
$coursesQuery->execute(['lecturer_id' => $lecturerId]);
$courses = $coursesQuery->fetchAll(PDO::FETCH_ASSOC);

$assignmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$assignment = [
    'course_id' => '',
    'title' => '',
    'description' => '',
    'due_date' => '',
    'attachment_path' => null,
];
$errors = [];

if ($assignmentId !== null) {
    $assignmentQuery = $pdo->prepare(
        'SELECT id, course_id, title, description, due_date, attachment_path
         FROM assignments WHERE id = :id AND lecturer_id = :lecturer_id'
    );
    $assignmentQuery->execute(['id' => $assignmentId, 'lecturer_id' => $lecturerId]);
    $existingAssignment = $assignmentQuery->fetch(PDO::FETCH_ASSOC);
    if (!$existingAssignment) {
        http_response_code(404);
        exit('Assignment not found.');
    }
    $assignment = $existingAssignment;
    $assignment['due_date'] = date('Y-m-d\TH:i', strtotime((string) $assignment['due_date']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    lecturer_verify_csrf();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $dueDateInput = trim((string) ($_POST['due_date'] ?? ''));
    $status = (string) ($_POST['action'] ?? '');

    $assignment['course_id'] = $courseId ?: '';
    $assignment['title'] = $title;
    $assignment['description'] = $description;
    $assignment['due_date'] = $dueDateInput;

    $ownedCourse = false;
    foreach ($courses as $course) {
        if ((int) $course['id'] === (int) $courseId) {
            $ownedCourse = true;
            break;
        }
    }
    if (!$courseId || !$ownedCourse) {
        $errors[] = 'Select one of your courses.';
    }
    if ($title === '' || strlen($title) > 255) {
        $errors[] = 'Enter a title of no more than 255 characters.';
    }
    if ($description === '') {
        $errors[] = 'Enter an assignment description.';
    }
    $dueDate = DateTime::createFromFormat('Y-m-d\TH:i', $dueDateInput);
    if (!$dueDate || $dueDate->format('Y-m-d\TH:i') !== $dueDateInput) {
        $errors[] = 'Enter a valid due date and time.';
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'Choose Save as Draft or Publish.';
    }

    $newAttachmentPath = '';
    if (!$errors) {
        try {
            $newAttachmentPath = lecturer_upload_attachment($_FILES['attachment'] ?? []);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if (!$errors) {
        $attachmentPath = $newAttachmentPath !== '' ? $newAttachmentPath : $assignment['attachment_path'];
        try {
            if ($assignmentId === null) {
                $saveQuery = $pdo->prepare(
                    'INSERT INTO assignments (course_id, lecturer_id, title, description, attachment_path, due_date, status)
                     VALUES (:course_id, :lecturer_id, :title, :description, :attachment_path, :due_date, :status)'
                );
                $saveQuery->execute([
                    'course_id' => $courseId,
                    'lecturer_id' => $lecturerId,
                    'title' => $title,
                    'description' => $description,
                    'attachment_path' => $attachmentPath ?: null,
                    'due_date' => $dueDate->format('Y-m-d H:i:s'),
                    'status' => $status,
                ]);
            } else {
                $saveQuery = $pdo->prepare(
                    'UPDATE assignments
                     SET course_id = :course_id, title = :title, description = :description,
                         attachment_path = :attachment_path, due_date = :due_date, status = :status
                     WHERE id = :id AND lecturer_id = :lecturer_id'
                );
                $saveQuery->execute([
                    'course_id' => $courseId,
                    'title' => $title,
                    'description' => $description,
                    'attachment_path' => $attachmentPath ?: null,
                    'due_date' => $dueDate->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'id' => $assignmentId,
                    'lecturer_id' => $lecturerId,
                ]);
            }
            if ($newAttachmentPath !== '' && !empty($assignment['attachment_path'])) {
                $oldFile = lecturer_resolve_upload((string) $assignment['attachment_path'], 'assignments');
                if ($oldFile !== null) {
                    unlink($oldFile);
                }
            }
            lecturer_redirect('manage_assignments.php?saved=1');
        } catch (Throwable $exception) {
            if ($newAttachmentPath !== '') {
                $newFile = lecturer_resolve_upload($newAttachmentPath, 'assignments');
                if ($newFile !== null) {
                    unlink($newFile);
                }
            }
            throw $exception;
        }
    }
}

lecturer_page_start($assignmentId === null ? 'Create assignment' : 'Edit assignment');
?>
<div class="mb-4">
    <h1 class="h2"><?= $assignmentId === null ? 'Create assignment' : 'Edit assignment' ?></h1>
    <p class="text-muted mb-0">Save a draft for later or publish it to enrolled students.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= lecturer_h($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (!$courses): ?>
    <div class="alert alert-warning">You need an assigned course before you can create an assignment.</div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= lecturer_h(lecturer_csrf_token()) ?>">
    <div class="mb-3">
        <label for="course_id" class="form-label">Course</label>
        <select id="course_id" name="course_id" class="form-select" required>
            <option value="">Choose a course</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id'] ?>" <?= (int) $assignment['course_id'] === (int) $course['id'] ? 'selected' : '' ?>>
                    <?= lecturer_h($course['course_code'] . ' - ' . $course['course_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="title" class="form-label">Title</label>
        <input id="title" name="title" type="text" maxlength="255" class="form-control" value="<?= lecturer_h($assignment['title']) ?>" required>
    </div>
    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" rows="6" class="form-control" required><?= lecturer_h($assignment['description']) ?></textarea>
    </div>
    <div class="mb-3">
        <label for="due_date" class="form-label">Due date and time</label>
        <input id="due_date" name="due_date" type="datetime-local" class="form-control" value="<?= lecturer_h($assignment['due_date']) ?>" required>
    </div>
    <div class="mb-3">
        <label for="attachment" class="form-label">Attachment (optional)</label>
        <input id="attachment" name="attachment" type="file" accept=".pdf,.docx,.zip" class="form-control">
        <div class="form-text">PDF, DOCX or ZIP, up to 10 MB. Uploading a new file replaces the current attachment.</div>
        <?php if (!empty($assignment['attachment_path'])): ?><div class="form-text">An attachment is currently saved.</div><?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" name="action" value="draft" class="btn btn-outline-primary">Save as Draft</button>
        <button type="submit" name="action" value="published" class="btn btn-primary">Publish</button>
        <a class="btn btn-link" href="manage_assignments.php">Cancel</a>
    </div>
</form>
<?php endif; ?>
<?php lecturer_page_end(); ?>
