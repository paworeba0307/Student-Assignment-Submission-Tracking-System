<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/* ---------- Summary counts (same query as before) ---------- */
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
$row = $summaryQuery->fetch(PDO::FETCH_ASSOC) ?: [];

$summary = [
    'total'     => (int) ($row['total'] ?? 0),
    'submitted' => (int) ($row['submitted'] ?? 0),
    'pending'   => (int) ($row['pending'] ?? 0),
    'overdue'   => (int) ($row['overdue'] ?? 0),
];
$percentDone = $summary['total'] > 0
    ? (int) round($summary['submitted'] / $summary['total'] * 100)
    : 0;

/* ---------- Assignments that still need attention ---------- */
$attention = [];
try {
    $listQuery = $pdo->prepare(
        'SELECT a.id, a.title, a.due_date, c.course_code, c.course_name
         FROM enrollments e
         INNER JOIN assignments a ON a.course_id = e.course_id AND a.status = \'published\'
         INNER JOIN courses c ON c.id = a.course_id
         LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
         WHERE e.student_id = :student_id AND s.id IS NULL
         ORDER BY a.due_date ASC
         LIMIT 5'
    );
    $listQuery->execute(['student_id' => $studentId]);
    $attention = $listQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $attention = []; // keep the dashboard working even if a column name differs
}

/* ---------- Helpers ---------- */
function dash_time_left(string $due): array
{
    $now   = new DateTimeImmutable('now');
    $dueAt = new DateTimeImmutable($due);

    if ($dueAt < $now) {
        return ['Deadline passed', 'overdue'];
    }

    $diff = $now->diff($dueAt);
    $days = (int) $diff->days;

    if ($days >= 1) {
        return [$days . 'd ' . $diff->h . 'h remaining', 'pending'];
    }

    return [$diff->h . 'h ' . $diff->i . 'm remaining', 'pending'];
}

$fullName  = (string) ($_SESSION['user']['full_name'] ?? $_SESSION['user']['name'] ?? 'Student');
$firstName = explode(' ', trim($fullName))[0] ?: 'Student';

$pageTitle = 'Dashboard — ASATS';
require __DIR__ . '/../includes/header.php';
?>
<style>
  .dash { max-width: 1100px; margin: 0 auto; padding: 32px 20px 56px; }
  .dash-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 28px; }
  .dash-head h1 { font-size: 1.9rem; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
  .dash-head p { margin: 0; color: #6b7280; }
  .dash-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .dash-btn { display: inline-block; padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: .92rem; text-decoration: none; border: 1px solid #2563eb; }
  .dash-btn.primary { background: #2563eb; color: #fff; }
  .dash-btn.ghost { background: #fff; color: #2563eb; }
  .dash-btn:hover { opacity: .9; }

  .dash-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
  .dash-stat { background: #fff; border: 1px solid #e5e7eb; border-top: 4px solid var(--c); border-radius: 14px; padding: 20px 22px; }
  .dash-stat .label { font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; }
  .dash-stat .value { font-size: 2.4rem; font-weight: 800; color: var(--c); line-height: 1.1; margin-top: 8px; }
  .dash-stat.blue   { --c: #2563eb; }
  .dash-stat.green  { --c: #059669; }
  .dash-stat.orange { --c: #d97706; }
  .dash-stat.red    { --c: #dc2626; }

  .dash-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 22px 24px; margin-bottom: 24px; }
  .dash-card h2 { font-size: 1.05rem; font-weight: 700; margin: 0 0 14px; }
  .dash-progress-top { display: flex; justify-content: space-between; font-size: .9rem; color: #4b5563; margin-bottom: 8px; }
  .dash-bar { height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
  .dash-bar span { display: block; height: 100%; background: #059669; border-radius: 999px; }

  .dash-list { list-style: none; margin: 0; padding: 0; }
  .dash-item { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-top: 1px solid #f0f1f3; }
  .dash-item:first-child { border-top: 0; }
  .dash-title { font-weight: 700; }
  .dash-meta { font-size: .85rem; color: #6b7280; }
  .dash-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
  .dash-badge { font-size: .75rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
  .dash-badge.pending { background: #fef3c7; color: #b45309; }
  .dash-badge.overdue { background: #fee2e2; color: #b91c1c; }
  .dash-left.pending { color: #b45309; font-weight: 600; font-size: .85rem; }
  .dash-left.overdue { color: #b91c1c; font-weight: 600; font-size: .85rem; }
  .dash-link { font-weight: 700; color: #2563eb; text-decoration: none; font-size: .9rem; }
  .dash-empty { text-align: center; padding: 28px 10px; color: #6b7280; }

  @media (max-width: 800px) { .dash-stats { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 420px) { .dash-stats { grid-template-columns: 1fr; } }
</style>

<main class="dash">
  <div class="dash-head">
    <div>
      <h1>Welcome back, <?= student_h($firstName) ?></h1>
      <p><?= student_h(date('l, j F Y')) ?> &middot; Here is where your assignments stand.</p>
    </div>
    <div class="dash-actions">
      <a class="dash-btn primary" href="assignments.php">Browse assignments</a>
      <a class="dash-btn ghost" href="my_submissions.php">My submissions</a>
    </div>
  </div>

  <section class="dash-stats" aria-label="Assignment counts">
    <div class="dash-stat blue">
      <div class="label">Available</div>
      <div class="value"><?= student_h($summary['total']) ?></div>
    </div>
    <div class="dash-stat green">
      <div class="label">Submitted</div>
      <div class="value"><?= student_h($summary['submitted']) ?></div>
    </div>
    <div class="dash-stat orange">
      <div class="label">Pending</div>
      <div class="value"><?= student_h($summary['pending']) ?></div>
    </div>
    <div class="dash-stat red">
      <div class="label">Overdue</div>
      <div class="value"><?= student_h($summary['overdue']) ?></div>
    </div>
  </section>

  <section class="dash-card" aria-label="Progress">
    <h2>Your progress</h2>
    <div class="dash-progress-top">
      <span><?= student_h($summary['submitted']) ?> of <?= student_h($summary['total']) ?> assignments submitted</span>
      <strong><?= student_h($percentDone) ?>%</strong>
    </div>
    <div class="dash-bar" role="progressbar" aria-valuenow="<?= student_h($percentDone) ?>" aria-valuemin="0" aria-valuemax="100">
      <span style="width: <?= student_h($percentDone) ?>%"></span>
    </div>
  </section>

  <section class="dash-card" aria-label="Needs attention">
    <h2>Needs your attention</h2>

    <?php if (!$attention): ?>
      <div class="dash-empty">You are all caught up. No assignments are waiting for you.</div>
    <?php else: ?>
      <ul class="dash-list">
        <?php foreach ($attention as $item): ?>
          <?php [$leftText, $state] = dash_time_left((string) $item['due_date']); ?>
          <li class="dash-item">
            <div>
              <div class="dash-title"><?= student_h($item['title']) ?></div>
              <div class="dash-meta">
                <?= student_h($item['course_code']) ?> &middot; Due
                <?= student_h(date('j M Y, H:i', strtotime((string) $item['due_date']))) ?>
              </div>
            </div>
            <div class="dash-right">
              <span class="dash-left <?= student_h($state) ?>"><?= student_h($leftText) ?></span>
              <span class="dash-badge <?= student_h($state) ?>"><?= $state === 'overdue' ? 'Overdue' : 'Pending' ?></span>
              <?php if ($state === 'pending'): ?>
                <a class="dash-link" href="assignment_view.php?id=<?= (int) $item['id'] ?>">Submit now &rarr;</a>
              <?php else: ?>
                <a class="dash-link" href="assignment_view.php?id=<?= (int) $item['id'] ?>">View</a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>