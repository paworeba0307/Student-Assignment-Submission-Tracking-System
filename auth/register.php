<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user'])) {
    redirect(dashboard_url_for_role((string) $_SESSION['user']['role']));
}

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullNameInput = $_POST['full_name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $confirmationInput = $_POST['password_confirmation'] ?? '';
    $fullName = is_string($fullNameInput) ? trim($fullNameInput) : '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $passwordConfirmation = is_string($confirmationInput) ? $confirmationInput : '';

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if (strlen($fullName) < 2 || strlen($fullName) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 bytes.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'The password confirmation does not match.';
    }

    if (!$errors) {
        $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);

        if ($statement->fetch()) {
            $errors[] = 'An account with that email address already exists.';
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role)
                 VALUES (:full_name, :email, :password_hash, :role)'
            );

            try {
                $statement->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'student',
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }

                $errors[] = 'An account with that email address already exists.';
            }

            if (!$errors) {
                flash('success', 'Your student account has been created. You can now log in.');
                redirect(app_url('auth/login.php'));
            }
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
    <title>Create account | ASATS</title>
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
            <h2>Join your courses and never miss a deadline.</h2>
            <p>Create a student account to see the assignments your lecturers publish and submit your work online.</p>
            <ul class="auth-points">
                <li><span class="dot">&#10003;</span> See new assignments as soon as they are published</li>
                <li><span class="dot">&#10003;</span> Submit files before the due date</li>
                <li><span class="dot">&#10003;</span> Check what is pending, submitted or overdue</li>
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

            <h1>Create your account</h1>
            <p class="sub">Register as a student. It takes under a minute.</p>

            <?php if ($message): ?>
                <div class="auth-alert <?= e((string) $message['type']) ?>" role="alert">
                    <?= e((string) $message['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="auth-alert danger" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(app_url('auth/register.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="field">
                    <label for="full_name">Full name</label>
                    <input id="full_name" name="full_name" type="text" maxlength="100"
                           autocomplete="name" placeholder="Your full name"
                           required value="<?= e($fullName) ?>">
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" maxlength="254"
                           autocomplete="email" placeholder="you@university.ac"
                           required value="<?= e($email) ?>">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input id="password" name="password" type="password" minlength="8" maxlength="72"
                               autocomplete="new-password" placeholder="Create a password" required>
                        <button class="pw-toggle" type="button" data-target="password">Show</button>
                    </div>
                    <div class="hint">Use 8 to 72 characters.</div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="pw-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               minlength="8" maxlength="72" autocomplete="new-password"
                               placeholder="Repeat your password" required>
                        <button class="pw-toggle" type="button" data-target="password_confirmation">Show</button>
                    </div>
                </div>

                <button class="btn-auth" type="submit">Create account</button>
            </form>

            <p class="auth-alt">
                Already registered? <a href="<?= e(app_url('auth/login.php')) ?>">Log in</a>
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