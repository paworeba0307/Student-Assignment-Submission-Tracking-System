<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
    flash('danger', 'Your session expired. Please try again.');
    redirect(app_url('auth/login.php'));
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParameters['path'],
        $cookieParameters['domain'],
        $cookieParameters['secure'],
        $cookieParameters['httponly']
    );
}

session_destroy();
redirect(app_url('auth/login.php'));
