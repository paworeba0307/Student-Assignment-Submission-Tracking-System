<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('lecturer');
require_once __DIR__ . '/_common.php';

$lecturerId = (int) $_SESSION['user']['id'];
$selfUrl    = app_url('lecturer/manage_courses.php');

/* ---------- Handle form submissions (before any output) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Your session expired. Please try again.');
        redirect($selfUrl);
    }

    $action = (string) ($_POST['action'] ?? '');

    /* --- Create a course --- */
    if ($action === 'create_course') {
        $code = strtoupper(trim((string) ($_POST['course_code'] ?? '')));
        $name = trim((string) ($_POST['course_name'] ?? ''));

        if (!preg_match('/^[A-Z0-9][A-Z0-9 \-]{1,29}$/', $code)) {
            flash('danger', 'Course code must be 2 to 30 characters (letters, numbers, spaces or hyphens), e.g. NET220.');
            redirect($selfUrl);
        }
        if (strlen($name) < 3 || strlen($name) > 150) {
            flash('danger', 'Course name must be between 3 and 150 characters.');
            redirect($selfUrl);
        }

        try {
            $insert = $pdo->prepare(
                'INSERT INTO courses (course_code, course_name, lecturer_id)
                 VALUES (:code, :name, :lid)'
            );
            $insert->execute(['code' => $code, 'name' => $name, 'lid' => $lecturerId]);
            $newId = (int) $pdo->lastInsertId();

            flash('success', "Course $code created. Now choose the students who are enrolled in it.");
            redirect($selfUrl . '?course=' . $newId);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('danger', "A course with the code $code already exists.");
                redirect($selfUrl);
            }
            throw $exception;
        }
    }

    /* --- Save the list of enrolled students --- */
    if ($action === 'save_enrollments') {
        $courseId = (int) ($_POST['course_id'] ?? 0);

        $owns = $pdo->prepare('SELECT id, course_code FROM courses WHERE id = :id AND lecturer_id = :lid');
        $owns->execute(['id' => $courseId, 'lid' => $lecturerId]);
        $course = $owns->fetch();

        if (!$course) {
            flash('danger', 'That course was not found.');
            redirect($selfUrl);
        }

        $validStudents = $pdo->query("SELECT id FROM users WHERE role = 'student'")->fetchAll(PDO::FETCH_COLUMN);
        $validStudents = array_map('intval', $validStudents);

        $chosen = array_map('intval', (array) ($_POST['students'] ?? []));
        $chosen = array_values(array_unique(array_intersect($chosen, $validStudents)));

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM enrollments WHERE course_id = :c')->execute(['c' => $courseId]);

            $add = $pdo->prepare('INSERT INTO enrollments (course_id, student_id) VALUES (:c, :s)');
            foreach ($chosen as $studentId) {
                $add->execute(['c' => $courseId, 's' => $studentId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        flash('success', 'Enrollment for ' . $course['course_code'] . ' saved (' . count($chosen) . ' students).');
        redirect($selfUrl . '?course=' . $courseId);
    }

    redirect($selfUrl);
}

/* ---------- Load data for the page ---------- */
$coursesQuery = $pdo->prepare(
    'SELECT c.id, c.course_code, c.course_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count,
            (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS assignment_count
     FROM courses c
     WHERE c.lecturer_id = :lid
     ORDER BY c.course_code'
);
$coursesQuery->execute(['lid' => $lecturerId]);
$courses = $coursesQuery->fetchAll();

$selected = null;
$students = [];
$enrolled = [];
$selectedId = (int) ($_GET['course'] ?? 0);

if ($selectedId > 0) {
    foreach ($courses as $course) {
        if ((int) $course['id'] === $selectedId) {
            $selected = $course;
            break;
        }
    }

    if ($selected) {
        $students = $pdo->query(
            "SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name"
        )->fetchAll();

        $enrolledQuery = $pdo->prepare('SELECT student_id FROM enrollments WHERE course_id = :c');
        $enrolledQuery->execute(['c' => $selectedId]);
        $enrolled = array_map('intval', $enrolledQuery->fetchAll(PDO::FETCH_COLUMN));
    }
}

$message = flash();

lecturer_page_start('Manage courses');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Manage courses</h1>
        <p class="text-secondary mb-0">Create your courses and choose which students are enrolled in each one.</p>
    </div>
    <a class="btn btn-primary" href="create_assignment.php">Create assignment</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= e((string) $message['type']) ?>" role="alert">
        <?= e((string) $message['message']) ?>
    </div>
<?php endif; ?>

<section class="card mb-4">
    <div class="card-header">Add a new course</div>
    <div class="card-body">
        <form method="post" action="<?= e($selfUrl) ?>" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_course">
            <div class="col-md-3">
                <label class="form-label" for="course_code">Course code</label>
                <input class="form-control" id="course_code" name="course_code" type="text"
                       maxlength="30" placeholder="e.g. NET220" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="course_name">Course name</label>
                <input class="form-control" id="course_name" name="course_name" type="text"
                       maxlength="150" placeholder="e.g. Network Security" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Create course</button>
            </div>
        </form>
    </div>
</section>

<section class="card mb-4">
    <div class="card-header">Your courses</div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Students</th>
                    <th>Assignments</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$courses): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4">You have no courses yet. Add one above.</td></tr>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?= lecturer_h($course['course_code']) ?></strong> &mdash; <?= lecturer_h($course['course_name']) ?></td>
                        <td><?= (int) $course['student_count'] ?></td>
                        <td><?= (int) $course['assignment_count'] ?></td>
                        <td class="text-end">
                            <a href="manage_courses.php?course=<?= (int) $course['id'] ?>">Manage students</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selected): ?>
<section class="card" id="enroll">
    <div class="card-header">
        Enrolled students for <?= lecturer_h($selected['course_code']) ?> &mdash; <?= lecturer_h($selected['course_name']) ?>
    </div>
    <div class="card-body">
        <?php if (!$students): ?>
            <p class="text-secondary mb-0">No student accounts exist yet. Students can register from the sign-up page.</p>
        <?php else: ?>
            <form method="post" action="<?= e($selfUrl) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_enrollments">
                <input type="hidden" name="course_id" value="<?= (int) $selected['id'] ?>">

                <div class="form-check mb-3 pb-3 border-bottom">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label fw-semibold" for="selectAll">Select all students</label>
                </div>

                <div class="row g-2">
                    <?php foreach ($students as $student): ?>
                        <?php $sid = (int) $student['id']; ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input student-box" type="checkbox"
                                       name="students[]" value="<?= $sid ?>" id="student<?= $sid ?>"
                                       <?= in_array($sid, $enrolled, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="student<?= $sid ?>">
                                    <?= lecturer_h($student['full_name']) ?>
                                    <span class="text-secondary small">&middot; <?= lecturer_h($student['email']) ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="text-secondary small mt-3 mb-3">
                    Only enrolled students can see this course's published assignments. Unticking a student hides the
                    course from them, but any work they already submitted is kept.
                </p>
                <button class="btn btn-primary" type="submit">Save enrollment</button>
            </form>

            <script>
                (function () {
                    var all = document.getElementById('selectAll');
                    var boxes = document.querySelectorAll('.student-box');
                    function sync() {
                        var every = true;
                        boxes.forEach(function (b) { if (!b.checked) { every = false; } });
                        all.checked = every && boxes.length > 0;
                    }
                    all.addEventListener('change', function () {
                        boxes.forEach(function (b) { b.checked = all.checked; });
                    });
                    boxes.forEach(function (b) { b.addEventListener('change', sync); });
                    sync();
                })();
            </script>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php lecturer_page_end(); ?>