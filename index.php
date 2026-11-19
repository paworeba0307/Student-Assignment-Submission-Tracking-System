<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!empty($_SESSION['user'])) {
    header('Location: ' . ($_SESSION['user']['role'] === 'lecturer'
        ? 'lecturer/tracking.php' : 'student/my_submissions.php'));
    exit;
}
$pageTitle = 'ASATS — Student Assignment Tracking';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="site-wrap">
    <div class="row align-items-center g-5">

      <div class="col-lg-6">
        <span class="hero-eyebrow">Student Assignment Tracking System</span>
        <h1>One place for<br><span>every assignment.</span></h1>
        <p class="hero-lead">
          Lecturers publish assignments, students upload work before the deadline,
          and everyone can see what's been submitted and what's outstanding —
          all in one place.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="auth/register.php" class="btn-primary">Get started</a>
          <a href="auth/login.php"    class="btn-secondary">Log in</a>
        </div>
        <p style="margin-top:16px;font-size:12px;color:var(--ink-4)">
          Runs on XAMPP · PHP · MySQL &mdash; demo data loads automatically
        </p>
      </div>

      <div class="col-lg-6 col-preview">
        <div class="preview">
          <div class="preview-top">
            <span>My submissions</span>
            <div class="preview-dots">
              <span></span><span></span><span></span>
            </div>
          </div>
          <div class="preview-stats">
            <div class="preview-stat">
              <div class="preview-stat-n" style="color:var(--blue)">8</div>
              <div class="preview-stat-l">Total</div>
            </div>
            <div class="preview-stat">
              <div class="preview-stat-n" style="color:var(--green)">5</div>
              <div class="preview-stat-l">Submitted</div>
            </div>
            <div class="preview-stat">
              <div class="preview-stat-n" style="color:var(--amber)">3</div>
              <div class="preview-stat-l">Pending</div>
            </div>
          </div>
          <div class="preview-rows">
            <div class="preview-row">
              <div class="preview-av" style="background:var(--blue-light)"></div>
              <div class="flex-grow-1">
                <div class="preview-name">Database Design Project</div>
                <div class="preview-sub">ICT 301 &middot; Due 20 Nov</div>
              </div>
              <span class="badge badge-sub">Submitted</span>
            </div>
            <div class="preview-row">
              <div class="preview-av" style="background:var(--amber-light)"></div>
              <div class="flex-grow-1">
                <div class="preview-name">Responsive UI Prototype</div>
                <div class="preview-sub">ICT 245 &middot; Due 23 Nov</div>
              </div>
              <span class="badge badge-pend">Pending</span>
            </div>
            <div class="preview-row">
              <div class="preview-av" style="background:var(--red-light)"></div>
              <div class="flex-grow-1">
                <div class="preview-name">Software Engineering Report</div>
                <div class="preview-sub">SWE 210 &middot; Due 16 Nov</div>
              </div>
              <span class="badge badge-over">Overdue</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
