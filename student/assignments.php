<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$assignmentsQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.due_date, a.created_at, c.course_code, c.course_name,
            DATEDIFF(DATE(a.due_date), CURDATE()) AS days_remaining,
            (a.due_date < NOW()) AS is_overdue,
            (a.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) AND a.created_at <= NOW()) AS is_new,
            s.id AS submission_id
     FROM enrollments e
     INNER JOIN courses c ON c.id = e.course_id
     INNER JOIN assignments a ON a.course_id = c.id AND a.status = \'published\'
     LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
     WHERE e.student_id = :student_id
     ORDER BY a.due_date ASC, a.created_at DESC'
);
$assignmentsQuery->execute(['student_id' => $studentId]);
$assignments = $assignmentsQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Available Assignments | ASATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Available assignments</h1>
            <p class="text-secondary mb-0">Published assignments for your enrolled courses</p>
        </div>
        <nav class="d-flex gap-2" aria-label="Student navigation">
            <a class="btn btn-outline-primary" href="dashboard.php">Dashboard</a>
            <?php if (is_file(__DIR__ . '/my_submissions.php')): ?>
                <a class="btn btn-outline-primary" href="my_submissions.php">My submissions</a>
                    <?php endif; ?>
        <form method="post" action="<?= e(app_url('auth/logout.php')) ?>" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-outline-danger">Log out</button>
        </form>
    </nav>
        </nav>
    </header>

    <?php if (!$assignments): ?>
        <div class="alert alert-info">There are no published assignments for your courses yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle bg-white">
                <thead>
                <tr>
                    <th scope="col">Course</th>
                    <th scope="col">Assignment</th>
                    <th scope="col">Due date</th>
                    <th scope="col">Time remaining</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?= student_h($assignment['course_code'] . ' — ' . $assignment['course_name']) ?></td>
                        <td>
                            <?= student_h($assignment['title']) ?>
                            <?php if ((int) $assignment['is_new'] === 1): ?>
                                <span class="badge text-bg-success">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td><?= student_h((new DateTimeImmutable($assignment['due_date']))->format('M j, Y g:i A')) ?></td>
                        <td>
                            <?php if ($assignment['submission_id'] !== null): ?>
                                Submitted
                            <?php elseif ((int) $assignment['is_overdue'] === 1): ?>
                                <span class="text-danger">Overdue</span>
                            <?php elseif ((int) $assignment['days_remaining'] === 0): ?>
                                Due today
                            <?php else: ?>
                                <?= student_h($assignment['days_remaining']) ?> day(s)
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($assignment['submission_id'] !== null): ?>
                                <span class="badge text-bg-success">Submitted</span>
                            <?php elseif ((int) $assignment['is_overdue'] === 1): ?>
                                <span class="badge text-bg-danger">Overdue</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-primary"
                              href="assignment_view.php?id=<?= rawurlencode((string) $assignment['id']) ?>">
                                View / Submit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
