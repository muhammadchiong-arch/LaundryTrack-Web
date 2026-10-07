<?php
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        // Re-read every request so a deactivated account is signed out at once.
        $user = q_one('SELECT id, name, email, role FROM users WHERE id = ? AND is_active = 1', [$_SESSION['uid']]);
        if (!$user) {
            unset($_SESSION['uid']);
        } else {
            $user['id'] = (int) $user['id'];
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function home_for(array $user): string
{
    return $user['role'] === 'admin' ? 'app/dashboard.php' : 'app/orders.php';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $title = 'Admins only';
        $message = 'This page is for admins. Ask the shop owner if you need access.';
        $back = url(home_for($user));
        require __DIR__ . '/layout/error.php';
        exit;
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        $title = 'Page expired';
        $message = 'This form was open too long or came from another site. Go back, reload the page and try again.';
        require __DIR__ . '/layout/error.php';
        exit;
    }
}
