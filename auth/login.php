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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <section class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-2">Log in to ASATS</h1>
                    <p class="text-secondary mb-4">Use your student or lecturer account.</p>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= e((string) $message['type']) ?>" role="alert">
                            <?= e((string) $message['message']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(app_url('auth/login.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control" id="email" name="email" type="email"
                                   maxlength="254" autocomplete="username" required value="<?= e($email) ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" name="password" type="password"
                                   autocomplete="current-password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Log in</button>
                    </form>

                    <p class="mt-4 mb-0 text-center">
                        Need a student account? <a href="<?= e(app_url('auth/register.php')) ?>">Register</a>
                    </p>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
