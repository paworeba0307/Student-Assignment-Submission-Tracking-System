<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'lecturer') {
    header('Location: ../index.php'); exit;
}
$pageTitle  = 'Submission Tracking — ASATS';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/database.php';

$lecturerId = (int)($_SESSION['user']['id'] ?? 0);
$matrix = [];
$pdo = getDatabase();
if ($pdo) {
    try {
        $stmt = $pdo->prepare(
            'SELECT a.id AS aid, a.title, a.due_date,
                    u.id AS uid, u.name AS uname, u.email AS uemail,
                    s.id AS sid, s.file_name, s.submitted_at
             FROM assignments AS a
             CROSS JOIN users AS u
             LEFT JOIN submissions AS s ON s.assignment_id = a.id AND s.student_id = u.id
             WHERE a.lecturer_id = :lid AND u.role = \'student\'
             ORDER BY a.due_date ASC, u.name ASC'
        );
        $stmt->execute(['lid' => $lecturerId]);
        $matrix = $stmt->fetchAll();
    } catch (Throwable) {}
}
if (!$matrix) {
    $n = time();
    $matrix = [
        ['aid'=>1,'title'=>'Database Design Project',   'due_date'=>date('Y-m-d H:i:s',$n-86400),   'uid'=>10,'uname'=>'Alice Mokoena', 'uemail'=>'alice@demo.ac', 'sid'=>1,   'file_name'=>'db-design.pdf',   'submitted_at'=>date('Y-m-d H:i:s',$n-2*86400)],
        ['aid'=>1,'title'=>'Database Design Project',   'due_date'=>date('Y-m-d H:i:s',$n-86400),   'uid'=>11,'uname'=>'Brian Dlamini','uemail'=>'brian@demo.ac', 'sid'=>null,'file_name'=>null,              'submitted_at'=>null],
        ['aid'=>1,'title'=>'Database Design Project',   'due_date'=>date('Y-m-d H:i:s',$n-86400),   'uid'=>12,'uname'=>'Carla Nkosi',  'uemail'=>'carla@demo.ac', 'sid'=>3,   'file_name'=>'db-design-v2.pdf','submitted_at'=>date('Y-m-d H:i:s',$n-3*86400)],
        ['aid'=>2,'title'=>'Responsive UI Prototype',   'due_date'=>date('Y-m-d H:i:s',$n+4*86400), 'uid'=>10,'uname'=>'Alice Mokoena', 'uemail'=>'alice@demo.ac', 'sid'=>4,   'file_name'=>'ui-proto.zip',    'submitted_at'=>date('Y-m-d H:i:s',$n-86400)],
        ['aid'=>2,'title'=>'Responsive UI Prototype',   'due_date'=>date('Y-m-d H:i:s',$n+4*86400), 'uid'=>11,'uname'=>'Brian Dlamini','uemail'=>'brian@demo.ac', 'sid'=>null,'file_name'=>null,              'submitted_at'=>null],
        ['aid'=>2,'title'=>'Responsive UI Prototype',   'due_date'=>date('Y-m-d H:i:s',$n+4*86400), 'uid'=>12,'uname'=>'Carla Nkosi',  'uemail'=>'carla@demo.ac', 'sid'=>null,'file_name'=>null,              'submitted_at'=>null],
        ['aid'=>3,'title'=>'Software Engineering Report','due_date'=>date('Y-m-d H:i:s',$n+8*86400),'uid'=>10,'uname'=>'Alice Mokoena', 'uemail'=>'alice@demo.ac', 'sid'=>null,'file_name'=>null,              'submitted_at'=>null],
        ['aid'=>3,'title'=>'Software Engineering Report','due_date'=>date('Y-m-d H:i:s',$n+8*86400),'uid'=>11,'uname'=>'Brian Dlamini','uemail'=>'brian@demo.ac', 'sid'=>7,   'file_name'=>'se-report.docx',  'submitted_at'=>date('Y-m-d H:i:s',$n-86400)],
        ['aid'=>3,'title'=>'Software Engineering Report','due_date'=>date('Y-m-d H:i:s',$n+8*86400),'uid'=>12,'uname'=>'Carla Nkosi',  'uemail'=>'carla@demo.ac', 'sid'=>null,'file_name'=>null,              'submitted_at'=>null],
    ];
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
                    <a class="act-link" href="javascript:void(0)"
                       title="<?= htmlspecialchars((string)$r['file_name']) ?>">
                      <svg width="12" height="12" fill="none" viewBox="0 0 12 12" aria-hidden="true">
                        <path d="M6 1v7M3 5.5l3 3 3-3M1 10h10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      <?= htmlspecialchars((string)$r['file_name']) ?>
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
