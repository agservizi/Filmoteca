<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/url.php';

const AUTH_SESSION_KEY = 'filmoteca_admin';
const AUTH_CSRF_NAMESPACE = 'csrf_admin';

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name('filmoteca_admin_session');
        session_start();
    }
}

function auth_login(string $username, string $password): bool
{
    auth_start_session();

    $envUser = env('ADMIN_USERNAME', env('ADMIN_USER'));
    $envPasswordHash = env('ADMIN_HASHED_PASSWORD');
    $envPassword = env('ADMIN_PASSWORD');

    if (!$envUser) {
        return false;
    }

    if (!hash_equals($envUser, $username)) {
        return false;
    }

    $authenticated = false;
    if ($envPasswordHash) {
        $authenticated = password_verify($password, $envPasswordHash);
    } elseif ($envPassword) {
        $authenticated = hash_equals($envPassword, $password);
    }

    if (!$authenticated) {
        return false;
    }

    $_SESSION[AUTH_SESSION_KEY] = [
        'username' => $username,
        'authenticated_at' => time(),
    ];

    return true;
}

function auth_logout(): void
{
    auth_start_session();
    unset($_SESSION[AUTH_SESSION_KEY]);
    session_regenerate_id(true);
}

function auth_check(): bool
{
    auth_start_session();
    return isset($_SESSION[AUTH_SESSION_KEY]['username']);
}

function auth_require(): void
{
    if (!auth_check()) {
        header('Location: ' . app_path('admin/login.php'));
        exit;
    }
}

function csrf_token(string $form): string
{
    auth_start_session();
    $tokens = $_SESSION[AUTH_CSRF_NAMESPACE] ?? [];
    if (isset($tokens[$form]) && is_string($tokens[$form]) && $tokens[$form] !== '') {
        return $tokens[$form];
    }
    $token = bin2hex(random_bytes(32));
    $tokens[$form] = $token;
    $_SESSION[AUTH_CSRF_NAMESPACE] = $tokens;
    return $token;
}

function csrf_verify(string $form, ?string $token): bool
{
    auth_start_session();
    if (!$token) {
        return false;
    }
    $tokens = $_SESSION[AUTH_CSRF_NAMESPACE] ?? [];
    if (!isset($tokens[$form])) {
        return false;
    }
    $valid = hash_equals($tokens[$form], $token);
    if ($valid) {
        unset($tokens[$form]);
        $_SESSION[AUTH_CSRF_NAMESPACE] = $tokens;
    }
    return $valid;
}
