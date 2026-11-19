<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$summaryQuery = $pdo->prepare(
    'SELECT COUNT(a.id) AS total,
            COALESCE(SUM(s.id IS NOT NULL), 0) AS submitted,
            COALESCE(SUM(s.id IS NULL AND a.due_date < NOW()), 0) AS overdue,
            COALESCE(SUM(s.id IS NULL AND a.due_date >= NOW()), 0) AS pending
     FROM enrollments e
     INNER JOIN assignments a ON a.course_id = e.course_id AND a.status = \'published\'
     LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
     WHERE e.student_id = :student_id'
);
$summaryQuery->execute(['student_id' => $studentId]);
$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC) ?: [
    'total' => 0,
    'submitted' => 0,
    'pending' => 0,
    'overdue' => 0,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard | ASATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Student dashboard</h1>
            <p class="text-secondary mb-0">Your assignment overview</p>
        </div>
        <nav class="d-flex gap-2" aria-label="Student navigation">
            <a class="btn btn-primary" href="assignments.php">Available assignments</a>
            <?php if (is_file(__DIR__ . '/my_submissions.php')): ?>
                <a class="btn btn-outline-primary" href="my_submissions.php">My submissions</a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="row g-3" aria-label="Assignment counts">
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-secondary">Available</h2>
                <p class="display-6 mb-0"><?= student_h($summary['total']) ?></p>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-secondary">Submitted</h2>
                <p class="display-6 mb-0"><?= student_h($summary['submitted']) ?></p>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-secondary">Pending</h2>
                <p class="display-6 mb-0"><?= student_h($summary['pending']) ?></p>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-secondary">Overdue</h2>
                <p class="display-6 mb-0"><?= student_h($summary['overdue']) ?></p>
            </div></div>
        </div>
    </section>

    <p class="mt-4"><a href="assignments.php">Browse your course assignments</a></p>
</main>
</body>
</html>
