<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function current_admin_username(): ?string
{
    return $_SESSION['admin_username'] ?? null;
}

function require_admin_login(): void
{
    if (!isset($_SESSION['admin_user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function login_admin(int $userId, string $username): void
{
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = $userId;
    $_SESSION['admin_username'] = $username;
}

function logout_admin(): void
{
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Ungültige Anfrage (CSRF-Token fehlt oder ist abgelaufen). Bitte Seite neu laden.');
    }
}
