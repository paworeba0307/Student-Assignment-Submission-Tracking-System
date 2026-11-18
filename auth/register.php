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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <section class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-2">Create a student account</h1>
                    <p class="text-secondary mb-4">Register to view and submit your course assignments.</p>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= e((string) $message['type']) ?>" role="alert">
                            <?= e((string) $message['message']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(app_url('auth/register.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="full_name">Full name</label>
                            <input class="form-control" id="full_name" name="full_name" type="text"
                                   maxlength="100" autocomplete="name" required value="<?= e($fullName) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control" id="email" name="email" type="email"
                                   maxlength="254" autocomplete="email" required value="<?= e($email) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" name="password" type="password"
                                   minlength="8" maxlength="72" autocomplete="new-password" required>
                            <div class="form-text">Use 8 to 72 bytes.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirm password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation"
                                   type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Create account</button>
                    </form>

                    <p class="mt-4 mb-0 text-center">
                        Already registered? <a href="<?= e(app_url('auth/login.php')) ?>">Log in</a>
                    </p>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
