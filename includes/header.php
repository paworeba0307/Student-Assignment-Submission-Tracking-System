<?php
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
$userName = $_SESSION['user']['name'] ?? null;
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
    <a class="nav-brand" href="<?= $basePath ?>index.php" aria-label="ASATS home">
      <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
      ASATS
    </a>

    <!-- Desktop links -->
    <nav class="nav-links" aria-label="Main">
      <a class="nav-link-item <?= $currentPage === 'index.php' ? 'active' : '' ?>"
         href="<?= $basePath ?>index.php">Home</a>

      <?php if ($userRole === 'lecturer'): ?>
        <a class="nav-link-item <?= $currentPage === 'tracking.php' ? 'active' : '' ?>"
           href="<?= $basePath ?>lecturer/tracking.php">Submission tracking</a>
      <?php elseif ($userRole === 'student'): ?>
        <a class="nav-link-item <?= $currentPage === 'my_submissions.php' ? 'active' : '' ?>"
           href="<?= $basePath ?>student/my_submissions.php">My submissions</a>
      <?php endif; ?>
    </nav>

    <!-- Desktop right side -->
    <div class="nav-right">
      <?php if ($userName): ?>
        <span class="nav-username"><?= htmlspecialchars($userName) ?></span>
        <a class="pill pill-ghost" href="<?= $basePath ?>auth/logout.php">Log out</a>
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
  <a href="<?= $basePath ?>index.php">Home</a>
  <?php if ($userRole === 'lecturer'): ?>
    <a href="<?= $basePath ?>lecturer/tracking.php">Submission tracking</a>
  <?php elseif ($userRole === 'student'): ?>
    <a href="<?= $basePath ?>student/my_submissions.php">My submissions</a>
  <?php endif; ?>
  <div class="sep"></div>
  <?php if ($userName): ?>
    <a style="color:var(--ink-3)"><?= htmlspecialchars($userName) ?></a>
    <a href="<?= $basePath ?>auth/logout.php">Log out</a>
  <?php else: ?>
    <a href="<?= $basePath ?>auth/login.php">Log in</a>
    <a href="<?= $basePath ?>auth/register.php"
       style="color:var(--blue);font-weight:700">Register</a>
  <?php endif; ?>
</div>
