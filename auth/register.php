<?php
declare(strict_types=1);
$pageTitle = 'Register — ASATS';
$error = '';
$post  = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string)($post['name'] ?? ''));
    $email    = trim((string)($post['email'] ?? ''));
    $password = (string)($post['password'] ?? '');
    $role     = in_array($post['role'] ?? '', ['student','lecturer'], true)
                ? (string)$post['role'] : 'student';
    if ($name === '') {
        $error = 'Enter your full name.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['user'] = ['id'=>1,'name'=>$name,'email'=>$email,'role'=>$role];
        header('Location: ' . ($role === 'lecturer'
            ? '../lecturer/tracking.php' : '../student/my_submissions.php'));
        exit;
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-screen">
  <form class="auth-card" method="post" novalidate>
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-sub">Join ASATS — takes under a minute.</p>

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
      <label class="field-label" for="name">Full name</label>
      <input class="field-input" id="name" name="name" type="text"
             autocomplete="name" placeholder="Your full name"
             value="<?= htmlspecialchars((string)($post['name'] ?? '')) ?>" required>
    </div>
    <div class="field">
      <label class="field-label" for="email">Email address</label>
      <input class="field-input" id="email" name="email" type="email"
             autocomplete="email" placeholder="you@university.ac"
             value="<?= htmlspecialchars((string)($post['email'] ?? '')) ?>" required>
    </div>
    <div class="field">
      <label class="field-label" for="password">
        Password
        <span class="field-hint">— minimum 8 characters</span>
      </label>
      <input class="field-input" id="password" name="password" type="password"
             autocomplete="new-password" placeholder="Create a password"
             minlength="8" required>
    </div>
    <div class="field" style="margin-bottom:26px">
      <label class="field-label" for="role">I am a</label>
      <select class="field-input" id="role" name="role">
        <option value="student"  <?= ($post['role'] ?? 'student') === 'student'  ? 'selected' : '' ?>>Student</option>
        <option value="lecturer" <?= ($post['role'] ?? '')         === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
      </select>
    </div>

    <button class="btn-submit" type="submit">Create account</button>
    <p class="auth-alt">Already have an account? <a href="login.php">Log in</a></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
