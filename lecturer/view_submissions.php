<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$lecturerId = lecturer_id();
$assignmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$assignmentId) {
    http_response_code(400);
    exit('An assignment ID is required.');
}

$assignmentQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.description, a.due_date, a.status, c.course_code, c.course_name
     FROM assignments a
     JOIN courses c ON c.id = a.course_id
     WHERE a.id = :id AND a.lecturer_id = :lecturer_id'
);
$assignmentQuery->execute(['id' => $assignmentId, 'lecturer_id' => $lecturerId]);
$assignment = $assignmentQuery->fetch(PDO::FETCH_ASSOC);
if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    lecturer_verify_csrf();
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $grade = trim((string) ($_POST['grade'] ?? ''));
    $feedback = trim((string) ($_POST['feedback'] ?? ''));
    if (!$studentId || strlen($grade) > 30 || strlen($feedback) > 5000) {
        $errors[] = 'Grade must be at most 30 characters and feedback at most 5,000 characters.';
    } else {
        $enrolledStudent = $pdo->prepare(
            'SELECT e.student_id FROM enrollments e
             JOIN assignments a ON a.course_id = e.course_id
             WHERE a.id = :assignment_id AND a.lecturer_id = :lecturer_id AND e.student_id = :student_id'
        );
        $enrolledStudent->execute([
            'assignment_id' => $assignmentId,
            'lecturer_id' => $lecturerId,
            'student_id' => $studentId,
        ]);
        if (!$enrolledStudent->fetchColumn()) {
            $errors[] = 'That student is not enrolled in this assignment\'s course.';
        } else {
            $saveGrade = $pdo->prepare(
                'UPDATE submissions s
                 JOIN assignments a ON a.id = s.assignment_id
                 SET s.grade = :grade, s.feedback = :feedback
                 WHERE s.assignment_id = :assignment_id AND s.student_id = :student_id
                   AND a.lecturer_id = :lecturer_id'
            );
            $saveGrade->execute([
                'grade' => $grade === '' ? null : $grade,
                'feedback' => $feedback === '' ? null : $feedback,
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'lecturer_id' => $lecturerId,
            ]);
            if ($saveGrade->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM submissions WHERE assignment_id = :assignment_id AND student_id = :student_id');
                $exists->execute(['assignment_id' => $assignmentId, 'student_id' => $studentId]);
                if (!$exists->fetchColumn()) {
                    $errors[] = 'A grade or feedback can only be saved after a student submits work.';
                }
            }
            if (!$errors) {
                lecturer_redirect('view_submissions.php?id=' . $assignmentId . '&saved=1');
            }
        }
    }
}

$studentsQuery = $pdo->prepare(
    'SELECT u.id AS student_id, u.full_name, u.email,
            s.id AS submission_id, s.file_path, s.submitted_at, s.grade, s.feedback
     FROM enrollments e
     JOIN users u ON u.id = e.student_id
     LEFT JOIN submissions s ON s.assignment_id = :assignment_id AND s.student_id = e.student_id
     WHERE e.course_id = (SELECT course_id FROM assignments WHERE id = :assignment_id_for_course)
     ORDER BY u.full_name'
);
$studentsQuery->execute([
    'assignment_id' => $assignmentId,
    'assignment_id_for_course' => $assignmentId,
]);
$students = $studentsQuery->fetchAll(PDO::FETCH_ASSOC);

lecturer_page_start('View submissions');
?>
<div class="mb-4">
    <a href="manage_assignments.php" class="text-decoration-none">&larr; Back to assignments</a>
    <h1 class="h2 mt-2 mb-1"><?= lecturer_h($assignment['title']) ?></h1>
    <p class="text-muted mb-1"><?= lecturer_h($assignment['course_code'] . ' - ' . $assignment['course_name']) ?></p>
    <p class="mb-0">Due <?= lecturer_h($assignment['due_date']) ?> &middot; <?= lecturer_h(ucfirst($assignment['status'])) ?></p>
</div>

<?php if ($assignment['description'] !== ''): ?>
    <div class="card card-body mb-4"><h2 class="h6">Description</h2><p class="mb-0"><?= nl2br(lecturer_h($assignment['description'])) ?></p></div>
<?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= lecturer_h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Grade and feedback saved.</div><?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Student</th><th>Status</th><th>Submitted at</th><th>Submission file</th><th>Grade &amp; feedback</th></tr></thead>
            <tbody>
            <?php if (!$students): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No students are enrolled in this course.</td></tr>
            <?php else: ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= lecturer_h($student['full_name']) ?><div class="small text-muted"><?= lecturer_h($student['email']) ?></div></td>
                        <td><span class="badge text-bg-<?= $student['submission_id'] ? 'success' : 'secondary' ?>"><?= $student['submission_id'] ? 'Submitted' : 'Not submitted' ?></span></td>
                        <td><?= lecturer_h($student['submitted_at'] ?: '—') ?></td>
                        <td>
                            <?php if ($student['submission_id']): ?>
                                <a href="download_submission.php?id=<?= (int) $student['submission_id'] ?>">Download</a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['submission_id']): ?>
                                <form method="post" class="d-grid gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= lecturer_h(lecturer_csrf_token()) ?>">
                                    <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                                    <input name="grade" class="form-control form-control-sm" maxlength="30" aria-label="Grade for <?= lecturer_h($student['full_name']) ?>" placeholder="Grade" value="<?= lecturer_h($student['grade']) ?>">
                                    <textarea name="feedback" class="form-control form-control-sm" maxlength="5000" rows="2" aria-label="Feedback for <?= lecturer_h($student['full_name']) ?>" placeholder="Feedback"><?= lecturer_h($student['feedback']) ?></textarea>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save grade / feedback</button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php lecturer_page_end(); ?>
