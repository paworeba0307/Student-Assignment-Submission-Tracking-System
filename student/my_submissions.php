<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';   // login check, $pdo and $studentId

$pageTitle = 'My Submissions — ASATS';
require __DIR__ . '/../includes/header.php';

$stmt = $pdo->prepare(
    'SELECT a.id, a.title, a.due_date, c.course_code, c.course_name,
            s.id AS submission_id, s.file_path AS file_name, s.submitted_at
     FROM enrollments e
     INNER JOIN assignments a ON a.course_id = e.course_id AND a.status = \'published\'
     INNER JOIN courses c ON c.id = a.course_id
     LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
     WHERE e.student_id = :sid
     ORDER BY a.due_date ASC'
);
$stmt->execute(['sid' => $studentId]);
$records = $stmt->fetchAll();

foreach ($records as &$r) {
    $r['description'] = $r['course_code'] . ' · ' . $r['course_name'];
}
unset($r);

$counts = ['total'=>count($records),'submitted'=>0,'pending'=>0,'overdue'=>0];
$rows   = [];
foreach ($records as $r) {
    $due = strtotime((string)$r['due_date']);
    if (!empty($r['submission_id'])) {
        $s = 'submitted'; $counts['submitted']++;
    } elseif ($due !== false && $due < time()) {
        $s = 'overdue';   $counts['overdue']++;
    } else {
        $s = 'pending';   $counts['pending']++;
    }
    $r['_status'] = $s;
    $rows[] = $r;
}
$labels = ['submitted'=>'Submitted','pending'=>'Pending','overdue'=>'Overdue'];
$badges = ['submitted'=>'badge-sub','pending'=>'badge-pend','overdue'=>'badge-over'];
?>

<main class="page-shell">
  <div class="site-wrap">
  <?php if (isset($_GET['submitted'])): ?>
  <div role="alert" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;border-radius:12px;padding:12px 16px;margin:0 0 20px;font-weight:600;">
    Your assignment was submitted successfully.
  </div>
<?php endif; ?>

    <div class="page-heading">
      <div>
        <ol class="breadcrumb-row" aria-label="Breadcrumb">
          <li><a href="<?= e($homeUrl) ?>">Home</a></li>
          <li aria-hidden="true"><svg width="10" height="10" fill="none" viewBox="0 0 10 10"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></li>
          <li aria-current="page">My submissions</li>
        </ol>
        <h1>My submissions</h1>
      </div>
      <span class="page-date"><?= date('d M Y') ?></span>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card sc-blue">
        <div class="stat-label">Total</div>
        <div class="stat-value"><?= $counts['total'] ?></div>
      </div>
      <div class="stat-card sc-green">
        <div class="stat-label">Submitted</div>
        <div class="stat-value"><?= $counts['submitted'] ?></div>
      </div>
      <div class="stat-card sc-amber">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $counts['pending'] ?></div>
      </div>
      <div class="stat-card sc-red">
        <div class="stat-label">Overdue</div>
        <div class="stat-value"><?= $counts['overdue'] ?></div>
      </div>
    </div>

    <!-- Panel -->
    <div class="panel">
      <div class="toolbar">
        <div class="filter-tabs" role="group" aria-label="Filter by status">
          <button class="ftab active" type="button" data-filter="all">All (<?= $counts['total'] ?>)</button>
          <button class="ftab" type="button" data-filter="submitted">Submitted (<?= $counts['submitted'] ?>)</button>
          <button class="ftab" type="button" data-filter="pending">Pending (<?= $counts['pending'] ?>)</button>
          <button class="ftab" type="button" data-filter="overdue">Overdue (<?= $counts['overdue'] ?>)</button>
        </div>
        <div class="search-wrap" role="search">
          <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 14 14" aria-hidden="true">
            <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.4"/>
            <path d="M9.5 9.5L13 13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <input id="trackingSearch" type="search"
                 placeholder="Search assignments…"
                 aria-label="Search assignments">
        </div>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table" aria-label="Assignment list">
          <thead>
            <tr>
              <th scope="col">Assignment</th>
              <th scope="col">Status</th>
              <th scope="col">Due date</th>
              <th scope="col">Time remaining</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="5" style="text-align:center;padding:48px;color:var(--ink-4)">
                No assignments found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php $s = $r['_status']; $due = strtotime((string)$r['due_date']); ?>
              <tr data-status="<?= $s ?>">
                <td>
                  <div class="cell-name"><?= htmlspecialchars((string)$r['title']) ?></div>
                  <div class="cell-sub"><?= htmlspecialchars((string)($r['description'] ?? '')) ?></div>
                </td>
                <td><span class="badge <?= $badges[$s] ?>"><?= $labels[$s] ?></span></td>
                <td class="tabnum">
                  <div class="cell-muted"><?= date('d M Y', $due) ?></div>
                  <div class="cell-dim"><?= date('H:i', $due) ?></div>
                </td>
                <td>
                  <span class="countdown"
                        data-countdown="<?= htmlspecialchars((string)$r['due_date']) ?>"
                        aria-live="polite">Calculating…</span>
                </td>
                <td>
                                    <?php if ($s === 'submitted'): ?>
                    <a class="act-link" href="assignment_view.php?id=<?= (int) $r['id'] ?>"
                       title="<?= htmlspecialchars((string)$r['file_name']) ?>">View file</a>
                  <?php elseif ($s === 'overdue'): ?>
                    <a class="act-link" href="assignment_view.php?id=<?= (int) $r['id'] ?>">View details</a>
                  <?php else: ?>
                    <a class="act-link" href="assignment_view.php?id=<?= (int) $r['id'] ?>">
                      Submit now
                      <svg width="11" height="11" fill="none" viewBox="0 0 11 11" aria-hidden="true">
                        <path d="M2 5.5h7M6 3l3 2.5-3 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
