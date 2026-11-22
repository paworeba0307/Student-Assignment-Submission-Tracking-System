<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user'])) {
    redirect(dashboard_url_for_role((string) $_SESSION['user']['role']));
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, full_name, email, password_hash, role
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'The email address or password is incorrect.';
        } else {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;

            redirect(dashboard_url_for_role((string) $user['role']));
        }
    }
}

$message = flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in | ASATS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/auth.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-shell">

    <aside class="auth-side">
        <div class="auth-brand">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            ASATS
        </div>

        <div class="auth-pitch">
            <h2>Assignments, submitted and tracked in one place.</h2>
            <p>Lecturers publish work and see who has handed in. Students see what is due and submit before the deadline.</p>
            <ul class="auth-points">
                <li><span class="dot">&#10003;</span> Publish assignments to your enrolled students</li>
                <li><span class="dot">&#10003;</span> Upload your work and get a clear submitted status</li>
                <li><span class="dot">&#10003;</span> Track pending, submitted and overdue work</li>
            </ul>
        </div>

        <div class="auth-foot">Student Assignment Submission and Tracking System</div>
    </aside>

    <main class="auth-main">
        <section class="auth-card">
            <div class="auth-mobile-brand">
                <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
                ASATS
            </div>

            <h1>Welcome back</h1>
            <p class="sub">Log in with your student or lecturer account.</p>

            <?php if ($message): ?>
                <div class="auth-alert <?= e((string) $message['type']) ?>" role="alert">
                    <?= e((string) $message['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="auth-alert danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(app_url('auth/login.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" maxlength="254"
                           autocomplete="username" placeholder="you@university.ac"
                           required value="<?= e($email) ?>">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input id="password" name="password" type="password"
                               autocomplete="current-password" placeholder="Enter your password" required>
                        <button class="pw-toggle" type="button" data-target="password">Show</button>
                    </div>
                </div>

                <button class="btn-auth" type="submit">Log in</button>
            </form>

            <p class="auth-alt">
                New here? <a href="<?= e(app_url('auth/register.php')) ?>">Create a student account</a>
            </p>
        </section>
    </main>

</div>
<script>
    document.querySelectorAll('.pw-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-target'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
        });
    });
</script>
</body>
</html>