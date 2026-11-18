<?php
declare(strict_types=1);
$pageTitle = 'Log in — ASATS';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Enter your email address and password to continue.';
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['user'] = ['id'=>1,'name'=>'Demo Student','email'=>$email,'role'=>'student'];
        header('Location: ../student/my_submissions.php');
        exit;
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-screen">
  <form class="auth-card" method="post" novalidate>
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Log in to your ASATS account.</p>

    <?php if ($error): ?>
      <div class="error-box" role="alert">
        <svg width="16" height="16" fill="none" viewBox="0 0 16 16" aria-hidden="true">
          <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 11h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="field">
      <label class="field-label" for="email">Email address</label>
      <input class="field-input <?= ($error && empty($_POST['email'])) ? 'has-error' : '' ?>"
             id="email" name="email" type="email"
             autocomplete="email" placeholder="you@university.ac"
             value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" required>
    </div>

    <div class="field" style="margin-bottom:24px">
      <label class="field-label" for="password">Password</label>
      <input class="field-input <?= ($error && empty($_POST['password'])) ? 'has-error' : '' ?>"
             id="password" name="password" type="password"
             autocomplete="current-password" placeholder="Enter your password" required>
    </div>

    <button class="btn-submit" type="submit">Log in</button>
    <p class="auth-alt">No account yet? <a href="register.php">Register</a></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
