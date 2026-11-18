<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$lecturerId = lecturer_id();

$coursesQuery = $pdo->prepare(
    "SELECT c.id, c.course_code, c.course_name,
            COUNT(DISTINCT CASE WHEN a.status = 'draft' THEN a.id END) AS draft_count,
            COUNT(DISTINCT CASE WHEN a.status = 'published' THEN a.id END) AS published_count
     FROM courses c
     LEFT JOIN assignments a ON a.course_id = c.id AND a.lecturer_id = c.lecturer_id
     WHERE c.lecturer_id = :lecturer_id
     GROUP BY c.id, c.course_code, c.course_name
     ORDER BY c.course_code"
);
$coursesQuery->execute(['lecturer_id' => $lecturerId]);
$courses = $coursesQuery->fetchAll(PDO::FETCH_ASSOC);

$submissionsQuery = $pdo->prepare(
    'SELECT s.id, s.submitted_at, s.file_path,
            u.full_name AS student_name, a.id AS assignment_id, a.title AS assignment_title,
            c.course_code
     FROM submissions s
     JOIN assignments a ON a.id = s.assignment_id
     JOIN courses c ON c.id = a.course_id
     JOIN users u ON u.id = s.student_id
     WHERE a.lecturer_id = :lecturer_id
     ORDER BY s.submitted_at DESC
     LIMIT 10'
);
$submissionsQuery->execute(['lecturer_id' => $lecturerId]);
$latestSubmissions = $submissionsQuery->fetchAll(PDO::FETCH_ASSOC);

lecturer_page_start('Dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Lecturer dashboard</h1>
        <p class="text-muted mb-0">Your courses, assignment status and latest submissions.</p>
    </div>
    <a class="btn btn-primary" href="create_assignment.php">Create assignment</a>
</div>

<section class="card shadow-sm mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Your courses</h2></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Course</th><th>Drafts</th><th>Published</th><th></th></tr></thead>
            <tbody>
            <?php if (!$courses): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No courses are assigned to your account.</td></tr>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?= lecturer_h($course['course_code']) ?></strong> &mdash; <?= lecturer_h($course['course_name']) ?></td>
                        <td><?= (int) $course['draft_count'] ?></td>
                        <td><?= (int) $course['published_count'] ?></td>
                        <td class="text-end"><a href="manage_assignments.php?course_id=<?= (int) $course['id'] ?>">View assignments</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card shadow-sm">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Latest submissions</h2></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Student</th><th>Assignment</th><th>Course</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
            <?php if (!$latestSubmissions): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">There are no submissions yet.</td></tr>
            <?php else: ?>
                <?php foreach ($latestSubmissions as $submission): ?>
                    <tr>
                        <td><?= lecturer_h($submission['student_name']) ?></td>
                        <td><?= lecturer_h($submission['assignment_title']) ?></td>
                        <td><?= lecturer_h($submission['course_code']) ?></td>
                        <td><?= lecturer_h($submission['submitted_at']) ?></td>
                        <td class="text-end"><a href="view_submissions.php?id=<?= (int) $submission['assignment_id'] ?>">View roster</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php lecturer_page_end(); ?>
