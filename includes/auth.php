<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function dashboard_url_for_role(string $role): string
{
    if ($role === 'lecturer') {
        return app_url('lecturer/dashboard.php');
    }

    if ($role === 'student') {
        return app_url('student/dashboard.php');
    }

    return app_url('auth/login.php');
}

function require_login(): void
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || !isset($user['id'], $user['role'])) {
        flash('danger', 'Please log in to continue.');
        redirect(app_url('auth/login.php'));
    }
}

function require_role(string $role): void
{
    if (!in_array($role, ['lecturer', 'student'], true)) {
        throw new InvalidArgumentException('Unsupported role.');
    }

    require_login();

    if ($_SESSION['user']['role'] !== $role) {
        flash('danger', 'You do not have permission to access that page.');
        redirect(dashboard_url_for_role((string) $_SESSION['user']['role']));
    }
}
