<?php
require_once __DIR__ . '/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$scriptName  = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath    = (str_contains($scriptName, '/student/')
             || str_contains($scriptName, '/auth/')
             || str_contains($scriptName, '/lecturer/'))
             ? '../' : '';
$userRole = $_SESSION['user']['role'] ?? null;
$userName = $_SESSION['user']['full_name'] ?? $_SESSION['user']['name'] ?? null;
$homeUrl = isset($_SESSION['user']['role'])
    ? dashboard_url_for_role((string) $_SESSION['user']['role'])
    : $basePath . 'index.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'ASATS') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body>

<!-- ── Navbar: pure custom, NOT Bootstrap navbar ── -->
<header class="site-header" role="banner">
  <div class="inner">

    <!-- Brand -->
    <a class="nav-brand" href="<?= e($homeUrl) ?>" aria-label="ASATS home">
      <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
      ASATS
    </a>

    <!-- Desktop links -->
    <nav class="nav-links" aria-label="Main">
        <a class="nav-link-item <?= in_array($currentPage, ['dashboard.php', 'index.php'], true) ? 'active' : '' ?>"
        href="<?= e($homeUrl) ?>">Home</a>

<?php if ($userRole === 'lecturer'): ?>
  <a class="nav-link-item <?= $currentPage === 'manage_courses.php' ? 'active' : '' ?>"
   href="<?= $basePath ?>lecturer/manage_courses.php">Courses</a>
  <a class="nav-link-item <?= $currentPage === 'manage_assignments.php' ? 'active' : '' ?>"
     href="<?= $basePath ?>lecturer/manage_assignments.php">Assignments</a>
  <a class="nav-link-item <?= $currentPage === 'tracking.php' ? 'active' : '' ?>"
     href="<?= $basePath ?>lecturer/tracking.php">Submission tracking</a>
<?php elseif ($userRole === 'student'): ?>
  <a class="nav-link-item <?= $currentPage === 'assignments.php' ? 'active' : '' ?>"
     href="<?= $basePath ?>student/assignments.php">Assignments</a>
  <a class="nav-link-item <?= $currentPage === 'my_submissions.php' ? 'active' : '' ?>"
     href="<?= $basePath ?>student/my_submissions.php">My submissions</a>
<?php endif; ?>
</nav>
    <!-- Desktop right side -->
    <div class="nav-right">
      <?php if ($userName): ?>
        <span class="nav-username"><?= htmlspecialchars($userName) ?></span>
        <form method="post" action="<?= $basePath ?>auth/logout.php" style="display:inline">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <button class="pill pill-ghost" type="submit">Log out</button>
</form>
      <?php else: ?>
        <a class="pill pill-ghost" href="<?= $basePath ?>auth/login.php">Log in</a>
        <a class="pill pill-solid" href="<?= $basePath ?>auth/register.php">Register</a>
      <?php endif; ?>
    </div>

    <!-- Mobile toggle -->
    <button class="nav-toggle" type="button"
            aria-expanded="false"
            aria-label="Open navigation"
            id="navToggle">
      <span></span><span></span><span></span>
    </button>

  </div>
</header>

<!-- Mobile nav panel (outside header so it doesn't affect header height) -->
<div class="nav-panel" id="navPanel" aria-hidden="true">
  <a href="<?= e($homeUrl) ?>">Home</a>
  
    <?php if ($userRole === 'lecturer'): ?>
    <a href="<?= $basePath ?>lecturer/manage_courses.php">Courses</a>
    <a href="<?= $basePath ?>lecturer/manage_assignments.php">Assignments</a>
    <a href="<?= $basePath ?>lecturer/tracking.php">Submission tracking</a>
  <?php elseif ($userRole === 'student'): ?>
    <a href="<?= $basePath ?>student/assignments.php">Assignments</a>
    <a href="<?= $basePath ?>student/my_submissions.php">My submissions</a>
  <?php endif; ?>
  <form method="post" action="<?= $basePath ?>auth/logout.php" style="display:inline">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <button type="submit" style="background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer">Log out</button>
</form>
  <?php if ($userName): ?>
    <a style="color:var(--ink-3)"><?= htmlspecialchars($userName) ?></a>
    <a href="<?= $basePath ?>auth/logout.php">Log out</a>
  <?php else: ?>
    <a href="<?= $basePath ?>auth/login.php">Log in</a>
    <a href="<?= $basePath ?>auth/register.php"
       style="color:var(--blue);font-weight:700">Register</a>
  <?php endif; ?>
</div>
