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
        $user = q_one('SELECT id, name, email, role, must_change_password FROM users WHERE id = ? AND is_active = 1', [$_SESSION['uid']]);
        if (!$user) {
            unset($_SESSION['uid']);
        } else {
            $user['id'] = (int) $user['id'];
        }
    }
    return $user;
}

// Signed-in customer with an online account (role 'customer'), or null.
function current_customer(): ?array
{
    static $c = false;
    if ($c !== false) {
        return $c;
    }
    $c = null;
    if (!empty($_SESSION['cid'])) {
        $c = q_one('SELECT id, name, email, phone, address FROM customers WHERE id = ? AND is_active = 1 AND password_hash IS NOT NULL', [$_SESSION['cid']]);
        if (!$c) {
            unset($_SESSION['cid']);
        } else {
            $c['id'] = (int) $c['id'];
            $c['role'] = 'customer';
        }
    }
    return $c;
}

function login_customer(array $c): void
{
    session_regenerate_id(true);
    unset($_SESSION['uid']);
    $_SESSION['cid'] = (int) $c['id'];
    q('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [$c['id']]);
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        if (current_user()) {
            redirect(home_for(current_user()));
        }
        flash('Sign in or create an account to book.', 'warning');
        redirect('login.php');
    }
    return $c;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['cid']);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['intro'] = true; // play the welcome intro on the first page after sign-in
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
    return $user['role'] === 'customer' ? 'my/index.php' : 'app/dashboard.php';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    // After an admin reset, the temporary password must be replaced before anything else.
    if (!empty($user['must_change_password']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'account.php') {
        flash('Choose a new password to continue. The one you used was temporary.', 'warning');
        redirect('app/account.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $title = 'Admins only';
        $message = 'This page is for the shop admin. Ask the owner if you need access.';
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
