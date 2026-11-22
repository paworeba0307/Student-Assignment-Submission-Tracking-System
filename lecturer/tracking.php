<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('lecturer');
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Submission Tracking — ASATS';
require __DIR__ . '/../includes/header.php';

$lecturerId = (int) $_SESSION['user']['id'];

$stmt = $pdo->prepare(
    'SELECT a.id AS aid, a.title, a.due_date,
            u.id AS uid, u.full_name AS uname, u.email AS uemail,
            s.id AS sid, s.file_path AS file_name, s.submitted_at
     FROM assignments a
     INNER JOIN enrollments e ON e.course_id = a.course_id
     INNER JOIN users u ON u.id = e.student_id AND u.role = \'student\'
     LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = u.id
     WHERE a.lecturer_id = :lid AND a.status = \'published\'
     ORDER BY a.due_date ASC, u.full_name ASC'
);
$stmt->execute(['lid' => $lecturerId]);
$matrix = $stmt->fetchAll();
function track_file_label(string $student, string $title, string $stored): string
{
    $ext  = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
    $base = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $student . ' ' . $title), '_');
    $base = substr($base !== '' ? $base : 'submission', 0, 60);
    return $ext !== '' ? $base . '.' . $ext : $base;
}
$byAssignment = [];
$totalSubs    = 0;
foreach ($matrix as $r) {
    $aid = (int)$r['aid'];
    if (!isset($byAssignment[$aid])) {
        $byAssignment[$aid] = ['title'=>$r['title'],'due_date'=>$r['due_date'],'students'=>[]];
    }
    $byAssignment[$aid]['students'][] = $r;
    if (!empty($r['sid'])) $totalSubs++;
}
$totalAssign   = count($byAssignment);
$totalStudents = count(array_unique(array_column($matrix, 'uid')));
$totalPending  = count($matrix) - $totalSubs;
?>

<main class="page-shell">
  <div class="site-wrap">

    <div class="page-heading">
      <div>
        <ol class="breadcrumb-row" aria-label="Breadcrumb">
          <li><a href="../index.php">Home</a></li>
          <li aria-hidden="true"><svg width="10" height="10" fill="none" viewBox="0 0 10 10"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></li>
          <li aria-current="page">Submission tracking</li>
        </ol>
        <h1>Submission tracking</h1>
      </div>
      <span class="page-date"><?= date('d M Y') ?></span>
    </div>

    <!-- Summary -->
    <div class="stat-grid">
      <div class="stat-card sc-blue">
        <div class="stat-label">Assignments</div>
        <div class="stat-value"><?= $totalAssign ?></div>
      </div>
      <div class="stat-card sc-green">
        <div class="stat-label">Received</div>
        <div class="stat-value"><?= $totalSubs ?></div>
      </div>
      <div class="stat-card sc-amber">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $totalPending ?></div>
      </div>
      <div class="stat-card sc-red">
        <div class="stat-label">Students</div>
        <div class="stat-value"><?= $totalStudents ?></div>
      </div>
    </div>

    <!-- Per-assignment panels -->
    <?php foreach ($byAssignment as $assignment): ?>
      <?php
      $submitted = array_filter($assignment['students'], fn($r) => !empty($r['sid']));
      $total     = count($assignment['students']);
      $subCount  = count($submitted);
      $dueTs     = strtotime((string)$assignment['due_date']);
      $isPast    = $dueTs !== false && $dueTs < time();
      $pct       = $total > 0 ? round(($subCount / $total) * 100) : 0;
      ?>
      <div class="panel">
        <div class="panel-header">
          <div class="flex-grow-1">
            <div class="panel-title"><?= htmlspecialchars($assignment['title']) ?></div>
            <div class="panel-meta">
              <svg width="11" height="11" fill="none" viewBox="0 0 12 12" aria-hidden="true">
                <rect x="1" y="2" width="10" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M4 1v2M8 1v2M1 5h10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              Due <?= date('d M Y, H:i', $dueTs) ?>
              <span class="badge <?= $isPast ? 'badge-closed' : 'badge-open' ?>">
                <?= $isPast ? 'Closed' : 'Open' ?>
              </span>
            </div>
            <div class="prog-row">
              <div class="prog-bg">
                <div class="prog-fill"
                     style="width:<?= $pct ?>%;background:<?= ($isPast && $pct < 100) ? 'var(--red)' : 'var(--green)' ?>"></div>
              </div>
              <span class="prog-pct"><?= $pct ?>% submitted</span>
            </div>
          </div>
          <div class="panel-tally">
            <div class="panel-tally-n"><?= $subCount ?>/<?= $total ?></div>
            <div class="panel-tally-l">submitted</div>
          </div>
        </div>

        <div style="overflow-x:auto">
          <table class="data-table" aria-label="<?= htmlspecialchars($assignment['title']) ?> submissions">
            <thead>
              <tr>
                <th scope="col">Student</th>
                <th scope="col">Email</th>
                <th scope="col">Status</th>
                <th scope="col">Submitted at</th>
                <th scope="col">File</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($assignment['students'] as $r): ?>
              <?php $has = !empty($r['sid']); ?>
              <tr>
                <td><div class="cell-name"><?= htmlspecialchars((string)$r['uname']) ?></div></td>
                <td class="cell-muted"><?= htmlspecialchars((string)$r['uemail']) ?></td>
                <td>
                  <?php if ($has): ?>
                    <span class="badge badge-sub">Submitted</span>
                  <?php elseif ($isPast): ?>
                    <span class="badge badge-nosub">Not submitted</span>
                  <?php else: ?>
                    <span class="badge badge-pend">Pending</span>
                  <?php endif; ?>
                </td>
                <td class="tabnum">
                  <?php if ($r['submitted_at']): ?>
                    <div class="cell-muted"><?= date('d M Y', strtotime((string)$r['submitted_at'])) ?></div>
                    <div class="cell-dim"><?= date('H:i', strtotime((string)$r['submitted_at'])) ?></div>
                  <?php else: ?>
                    <span class="no-data">—</span>
                  <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($r['file_name'])): ?>
                    <?php $fileLabel = track_file_label((string) $r['uname'], (string) ($r['title'] ?? ''), (string) $r['file_name']); ?>
                    <a class="act-link" href="download_submission.php?id=<?= (int) $r['sid'] ?>"
                       title="Download <?= htmlspecialchars($fileLabel) ?>">
                      <svg width="12" height="12" fill="none" viewBox="0 0 12 12" aria-hidden="true">
                        <path d="M6 1v7M3 5.5l3 3 3-3M1 10h10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      <?= htmlspecialchars($fileLabel) ?>
                    </a>
                  <?php else: ?>
                    <span class="no-data">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
