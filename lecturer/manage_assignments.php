<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$lecturerId = lecturer_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    lecturer_verify_csrf();
    $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');
    if (!$assignmentId || !in_array($action, ['publish', 'unpublish', 'delete'], true)) {
        $errors[] = 'Invalid assignment action.';
    } else {
        $lookup = $pdo->prepare(
            'SELECT id, attachment_path FROM assignments WHERE id = :id AND lecturer_id = :lecturer_id'
        );
        $lookup->execute(['id' => $assignmentId, 'lecturer_id' => $lecturerId]);
        $assignment = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) {
            $errors[] = 'Assignment not found or you do not have permission to manage it.';
        } elseif ($action === 'publish' || $action === 'unpublish') {
            $update = $pdo->prepare(
                'UPDATE assignments SET status = :status WHERE id = :id AND lecturer_id = :lecturer_id'
            );
            $update->execute([
                'status' => $action === 'publish' ? 'published' : 'draft',
                'id' => $assignmentId,
                'lecturer_id' => $lecturerId,
            ]);
            lecturer_redirect('manage_assignments.php?updated=1');
        } else {
            $submittedFiles = [];
            $pdo->beginTransaction();
            try {
                $submissions = $pdo->prepare('SELECT file_path FROM submissions WHERE assignment_id = :assignment_id');
                $submissions->execute(['assignment_id' => $assignmentId]);
                $submittedFiles = $submissions->fetchAll(PDO::FETCH_COLUMN);

                $deleteSubmissions = $pdo->prepare('DELETE FROM submissions WHERE assignment_id = :assignment_id');
                $deleteSubmissions->execute(['assignment_id' => $assignmentId]);
                $deleteAssignment = $pdo->prepare(
                    'DELETE FROM assignments WHERE id = :id AND lecturer_id = :lecturer_id'
                );
                $deleteAssignment->execute(['id' => $assignmentId, 'lecturer_id' => $lecturerId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            foreach ($submittedFiles as $path) {
                $file = lecturer_resolve_upload((string) $path, 'submissions');
                if ($file !== null) {
                    unlink($file);
                }
            }
            $attachment = lecturer_resolve_upload((string) ($assignment['attachment_path'] ?? ''), 'assignments');
            if ($attachment !== null) {
                unlink($attachment);
            }
            lecturer_redirect('manage_assignments.php?deleted=1');
        }
    }
}

$courseId = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?: null;
$coursesQuery = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE lecturer_id = :lecturer_id ORDER BY course_code');
$coursesQuery->execute(['lecturer_id' => $lecturerId]);
$courses = $coursesQuery->fetchAll(PDO::FETCH_ASSOC);
if ($courseId !== null && !in_array($courseId, array_map(static function (array $course): int {
    return (int) $course['id'];
}, $courses), true)) {
    http_response_code(404);
    exit('Course not found.');
}

$sql = 'SELECT a.id, a.title, a.description, a.due_date, a.status, a.attachment_path,
               c.course_code, c.course_name,
        COUNT(DISTINCT s.id) AS submission_count,
        (SELECT COUNT(*) FROM enrollments en WHERE en.course_id = a.course_id) AS student_count
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        LEFT JOIN submissions s ON s.assignment_id = a.id
        WHERE a.lecturer_id = :lecturer_id';
$parameters = ['lecturer_id' => $lecturerId];
if ($courseId !== null) {
    $sql .= ' AND a.course_id = :course_id';
    $parameters['course_id'] = $courseId;
}
$sql .= ' GROUP BY a.id, a.title, a.description, a.due_date, a.status, a.attachment_path, c.course_code, c.course_name
          ORDER BY a.created_at DESC';
$assignmentsQuery = $pdo->prepare($sql);
$assignmentsQuery->execute($parameters);
$assignments = $assignmentsQuery->fetchAll(PDO::FETCH_ASSOC);

lecturer_page_start('Manage assignments');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Manage assignments</h1>
        <p class="text-muted mb-0">Edit, publish, unpublish or remove your assignments.</p>
    </div>
    <a class="btn btn-primary" href="create_assignment.php">Create assignment</a>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= lecturer_h(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Assignment saved.</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Assignment status updated.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Assignment and its submissions were deleted.</div><?php endif; ?>

<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-sm-6 col-md-4">
        <label for="course_filter" class="form-label">Filter by course</label>
        <select id="course_filter" name="course_id" class="form-select">
            <option value="">All courses</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id'] ?>" <?= $courseId === (int) $course['id'] ? 'selected' : '' ?>>
                    <?= lecturer_h($course['course_code'] . ' - ' . $course['course_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Assignment</th><th>Course</th><th>Due</th><th>Submissions</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$assignments): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No assignments found.</td></tr>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td>
                            <strong><?= lecturer_h($assignment['title']) ?></strong>
                            <?php if ($assignment['attachment_path']): ?><div class="small text-muted">Attachment included</div><?php endif; ?>
                        </td>
                        <td><?= lecturer_h($assignment['course_code']) ?></td>
                        <td><?= lecturer_h($assignment['due_date']) ?></td>
                       <td><a href="view_submissions.php?id=<?= (int) $assignment['id'] ?>"><?= (int) $assignment['submission_count'] ?> / <?= (int) $assignment['student_count'] ?> students</a></td>
                        <td><span class="badge text-bg-<?= $assignment['status'] === 'published' ? 'success' : 'secondary' ?>"><?= lecturer_h(ucfirst($assignment['status'])) ?></span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="create_assignment.php?id=<?= (int) $assignment['id'] ?>">Edit</a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= lecturer_h(lecturer_csrf_token()) ?>">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="<?= $assignment['status'] === 'published' ? 'unpublish' : 'publish' ?>">
                                        <?= $assignment['status'] === 'published' ? 'Unpublish' : 'Publish' ?>
                                    </button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this assignment and all its submissions? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= lecturer_h(lecturer_csrf_token()) ?>">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php lecturer_page_end(); ?>
