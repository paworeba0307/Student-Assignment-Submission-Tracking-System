<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';   // login + student role check, $pdo, $studentId, student_h()

$assignmentId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$selfPath     = 'student/assignment_view.php';
$selfUrl      = app_url($selfPath) . '?id=' . $assignmentId;

/* ---------- Load the assignment (must be published and in a course the student is enrolled in) ---------- */
$stmt = $pdo->prepare(
    'SELECT a.id, a.title, a.description, a.due_date, a.created_at, a.attachment_path,
            c.course_code, c.course_name
     FROM assignments a
     INNER JOIN courses c ON c.id = a.course_id
     INNER JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = :sid
     WHERE a.id = :aid AND a.status = \'published\'
     LIMIT 1'
);
$stmt->execute(['sid' => $studentId, 'aid' => $assignmentId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found, or you are not enrolled in this course.');
}

/* ---------- Load this student's submission (if any) ---------- */
$subStmt = $pdo->prepare(
    'SELECT id, file_path, comment, submitted_at, grade, feedback
     FROM submissions
     WHERE assignment_id = :aid AND student_id = :sid
     LIMIT 1'
);
$subStmt->execute(['aid' => $assignmentId, 'sid' => $studentId]);
$submission = $subStmt->fetch() ?: null;

$now       = new DateTimeImmutable('now');
$dueAt     = new DateTimeImmutable((string) $assignment['due_date']);
$pastDue   = $dueAt < $now;

$uploadRoot      = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$submissionsDir  = $uploadRoot . '/submissions';
$attachmentsDir  = $uploadRoot . '/assignments';

/* ---------- Helpers ---------- */
function av_span(DateTimeImmutable $from, DateTimeImmutable $to): string
{
    $diff  = $from->diff($to);
    $parts = [];
    if ($diff->days >= 1) {
        $parts[] = $diff->days . ($diff->days === 1 ? ' day' : ' days');
    }
    if ($diff->h > 0) {
        $parts[] = $diff->h . ($diff->h === 1 ? ' hour' : ' hours');
    }
    if (!$parts || $diff->days < 1) {
        $parts[] = $diff->i . ($diff->i === 1 ? ' min' : ' mins');
    }
    return implode(' ', array_slice($parts, 0, 2));
}

function av_stored_path(string $dir, string $stored): ?string
{
    $path = $dir . '/' . basename($stored);
    return is_file($path) ? $path : null;
}

function av_friendly_name(string $title, string $stored): string
{
    $ext  = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
    $base = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $title), '_');
    $base = substr($base !== '' ? $base : 'submission', 0, 40);
    return $ext !== '' ? $base . '.' . $ext : $base;
}

function av_send_file(string $path, string $downloadName): void
{
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

/* ---------- File downloads (only the student's own submission or the lecturer's attachment) ---------- */
if (isset($_GET['download'])) {
    if ($_GET['download'] === 'submission' && $submission) {
        $path = av_stored_path($submissionsDir, (string) $submission['file_path']);
        if ($path) {
            av_send_file($path, av_friendly_name((string) $assignment['title'], (string) $submission['file_path']));
        }
    }
        if ($_GET['download'] === 'attachment' && !empty($assignment['attachment_path'])) {
        $path = av_stored_path($attachmentsDir, (string) $assignment['attachment_path']);
        if ($path) {
        av_send_file($path, av_friendly_name((string) $assignment['title'] . ' attachment', (string) $assignment['attachment_path']));
    }
    http_response_code(404);
    exit('File not found.');
}
}

/* ---------- Remove submission (only before the due date) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        redirect($selfUrl . '&error=session');
    }
    if (!$submission || $pastDue) {
        redirect($selfUrl . '&error=locked');
    }

    $pdo->prepare('DELETE FROM submissions WHERE id = :id AND student_id = :sid')
        ->execute(['id' => $submission['id'], 'sid' => $studentId]);

    $old = av_stored_path($submissionsDir, (string) $submission['file_path']);
    if ($old) {
        @unlink($old);
    }

    redirect($selfUrl . '&removed=1');
}

/* ---------- Work out the status rows ---------- */
$isSubmitted = $submission !== null;
$submittedAt = $isSubmitted ? new DateTimeImmutable((string) $submission['submitted_at']) : null;
$isGraded    = $isSubmitted && $submission['grade'] !== null;

if ($isSubmitted) {
    $statusText  = 'Submitted for grading';
    $statusClass = 'ok';
    if ($submittedAt > $dueAt) {
        $timeText  = 'Assignment was submitted ' . av_span($dueAt, $submittedAt) . ' late';
        $timeClass = 'bad';
    } else {
        $timeText  = 'Assignment was submitted ' . av_span($submittedAt, $dueAt) . ' early';
        $timeClass = 'ok';
    }
} else {
    $statusText  = 'No submission yet';
    $statusClass = 'plain';
    if ($pastDue) {
        $timeText  = 'Assignment is overdue by ' . av_span($dueAt, $now);
        $timeClass = 'bad';
    } else {
        $timeText  = av_span($now, $dueAt) . ' remaining';
        $timeClass = 'warn';
    }
}

$storedFile   = $isSubmitted ? (string) $submission['file_path'] : '';
$fileExists   = $isSubmitted && av_stored_path($submissionsDir, $storedFile) !== null;
$fileLabel    = $isSubmitted ? av_friendly_name((string) $assignment['title'], $storedFile) : '';
$hasAttachment = !empty($assignment['attachment_path'])
    && av_stored_path($attachmentsDir, (string) $assignment['attachment_path']) !== null;

$attachmentPath  = $hasAttachment ? av_stored_path($attachmentsDir, (string) $assignment['attachment_path']) : null;
$attachmentLabel = $hasAttachment
    ? av_friendly_name((string) $assignment['title'] . ' attachment', (string) $assignment['attachment_path'])
    : '';
$attachmentDate  = $attachmentPath ? date('j F Y, g:i A', (int) filemtime($attachmentPath)) : '';

$pageTitle = $assignment['title'] . ' — ASATS';
require __DIR__ . '/../includes/header.php';
?>
<style>
  .av { max-width: 960px; margin: 0 auto; padding: 28px 20px 60px; }
  .av-crumb { font-size: .85rem; color: #6b7280; margin-bottom: 14px; }
  .av-crumb a { color: #2563eb; text-decoration: none; }
  .av-banner { border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; border: 1px solid; }
  .av-banner.ok  { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
  .av-banner.bad { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

  .av-head { background: #2563eb; color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 18px; }
  .av-head small { opacity: .85; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; font-size: .75rem; }
  .av-head h1 { margin: 6px 0 0; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.01em; }

  .av-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px 24px; margin-bottom: 18px; }
  .av-done { display: inline-block; background: #15803d; color: #fff; font-size: .8rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; margin-bottom: 14px; }
  .av-todo { display: inline-block; background: #f3f4f6; color: #4b5563; font-size: .8rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; margin-bottom: 14px; }
  .av-dates { border-top: 1px solid #eef0f3; border-bottom: 1px solid #eef0f3; padding: 12px 0; margin-bottom: 14px; line-height: 1.8; }
  .av-desc { line-height: 1.6; color: #374151; }

  .av-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 26px; }
  .av-btn { display: inline-block; padding: 10px 18px; border-radius: 8px; font: inherit; font-weight: 600; font-size: .92rem; text-decoration: none; border: 1px solid #d1d5db; background: #e5e7eb; color: #111827; cursor: pointer; }
  .av-btn:hover { background: #d9dce1; }
  .av-btn.primary { background: #2563eb; border-color: #2563eb; color: #fff; }
  .av-btn.primary:hover { background: #1d4ed8; }
  .av-btn.ghost { background: #fff; }
  .av-btn[disabled] { opacity: .5; cursor: not-allowed; }

  .av h2 { font-size: 1.35rem; font-weight: 800; margin: 0 0 14px; }
  .av-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #dee2e6; }
  .av-table th, .av-table td { padding: 12px 14px; border: 1px solid #dee2e6; text-align: left; vertical-align: top; }
  .av-table th { width: 220px; background: #f8f9fa; font-weight: 700; }
  .av-table td.ok    { background: #d1e7dd; }
  .av-table td.bad   { background: #f8d7da; }
  .av-table td.warn  { background: #fff3cd; }
  .av-table td.files { background: #f3f4f6; }
  .av-file { display: flex; flex-wrap: wrap; gap: 8px 28px; align-items: baseline; }
  .av-file a { color: #2563eb; font-weight: 600; text-decoration: none; }
  .av-file a:hover { text-decoration: underline; }
  .av-muted { color: #6b7280; font-size: .88rem; }
</style>

<main class="av">
  <div class="av-crumb">
    <a href="<?= student_h(app_url('student/assignments.php')) ?>">Assignments</a> /
    <?= student_h($assignment['course_code']) ?> / <?= student_h($assignment['title']) ?>
  </div>

  <?php if (isset($_GET['submitted'])): ?>
    <div class="av-banner ok" role="alert">Your assignment was submitted successfully.</div>
  <?php elseif (isset($_GET['removed'])): ?>
    <div class="av-banner ok" role="alert">Your submission was removed. You can add a new one before the due date.</div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="av-banner bad" role="alert">
      <?= $_GET['error'] === 'locked'
          ? 'The submission could not be removed because the due date has passed.'
          : 'Your session expired. Please try again.' ?>
    </div>
  <?php endif; ?>

  <div class="av-head">
    <small><?= student_h($assignment['course_code']) ?> &middot; <?= student_h($assignment['course_name']) ?></small>
    <h1><?= student_h($assignment['title']) ?></h1>
  </div>

  <section class="av-box">
    <?php if ($isSubmitted): ?>
      <span class="av-done">&#10003; Done</span>
    <?php else: ?>
      <span class="av-todo">To do: make a submission</span>
    <?php endif; ?>

    <div class="av-dates">
      <div><strong>Opened:</strong> <?= student_h(date('l, j F Y, g:i A', strtotime((string) $assignment['created_at']))) ?></div>
      <div><strong>Due:</strong> <?= student_h($dueAt->format('l, j F Y, g:i A')) ?></div>
    </div>

    <div class="av-desc"><?= nl2br(student_h($assignment['description'])) ?></div>

        <?php if ($hasAttachment): ?>
      <div class="av-attach">
        <span class="av-attach-name">
          <span class="av-tree" aria-hidden="true"></span>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M3.5 1.5h6L13 5v9.5H3.5z" stroke="#6b7280" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M9.5 1.5V5H13" stroke="#6b7280" stroke-width="1.2" stroke-linejoin="round"/>
          </svg>
          <a href="<?= student_h($selfUrl) ?>&amp;download=attachment"><?= student_h($attachmentLabel) ?></a>
        </span>
        <span class="av-attach-date"><?= student_h($attachmentDate) ?></span>
      </div>
    <?php endif; ?>
  </section>

  <div class="av-actions">
    <?php if (!$isSubmitted): ?>
      <a class="av-btn primary" href="submit_assignment.php?id=<?= (int) $assignmentId ?>">Add submission</a>
    <?php else: ?>
      <a class="av-btn" href="submit_assignment.php?id=<?= (int) $assignmentId ?>">Edit submission</a>

      <?php if (!$pastDue): ?>
        <form method="post" action="<?= student_h($selfUrl) ?>"
              onsubmit="return confirm('Remove your submission? You can submit again before the due date.');">
          <input type="hidden" name="csrf_token" value="<?= student_h(csrf_token()) ?>">
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="id" value="<?= (int) $assignmentId ?>">
          <button class="av-btn" type="submit">Remove submission</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
    <a class="av-btn ghost" href="<?= student_h(app_url('student/my_submissions.php')) ?>">Back to my submissions</a>
  </div>

  <h2>Submission status</h2>
  <table class="av-table">
    <tr>
      <th>Submission status</th>
      <td class="<?= student_h($statusClass === 'plain' ? '' : $statusClass) ?>"><?= student_h($statusText) ?></td>
    </tr>
    <tr>
      <th>Grading status</th>
      <td>
        <?php if ($isGraded): ?>
          Graded &mdash; <strong><?= student_h(rtrim(rtrim(number_format((float) $submission['grade'], 2, '.', ''), '0'), '.')) ?></strong>
        <?php else: ?>
          Not graded
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th>Time remaining</th>
      <td class="<?= student_h($timeClass) ?>"><?= student_h($timeText) ?></td>
    </tr>
    <?php if ($isSubmitted): ?>
      <tr>
        <th>Last modified</th>
        <td><?= student_h($submittedAt->format('l, j F Y, g:i A')) ?></td>
      </tr>
      <tr>
        <th>File submissions</th>
        <td class="files">
          <div class="av-file">
            <?php if ($fileExists): ?>
              <a href="<?= student_h($selfUrl) ?>&amp;download=submission"><?= student_h($fileLabel) ?></a>
            <?php else: ?>
              <span class="av-muted">File not found on the server</span>
            <?php endif; ?>
            <span class="av-muted"><?= student_h($submittedAt->format('j F Y, g:i A')) ?></span>
          </div>
        </td>
      </tr>
      <?php if (!empty($submission['comment'])): ?>
        <tr>
          <th>Submission comments</th>
          <td><?= nl2br(student_h($submission['comment'])) ?></td>
        </tr>
      <?php endif; ?>
      <?php if (!empty($submission['feedback'])): ?>
        <tr>
          <th>Feedback from lecturer</th>
          <td><?= nl2br(student_h($submission['feedback'])) ?></td>
        </tr>
      <?php endif; ?>
    <?php endif; ?>
  </table>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>