<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null || $message !== null) {
        if ($type === null || $message === null) {
            throw new InvalidArgumentException('Flash messages require both a type and a message.');
        }

        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];

        return null;
    }

    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    return is_array($flash) ? $flash : null;
}

function app_url(string $path = ''): string
{
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $segments = array_values(array_filter(explode('/', trim($scriptDirectory, '/')), 'strlen'));
    $currentDirectory = $segments ? end($segments) : '';

    if (in_array($currentDirectory, ['auth', 'lecturer', 'student'], true)) {
        array_pop($segments);
    }

    $basePath = $segments ? '/' . implode('/', $segments) : '';

    return $basePath . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_token_is_valid(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}
