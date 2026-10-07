<?php
require __DIR__ . '/includes/bootstrap.php';

if ((int) q_val('SELECT COUNT(*) FROM users') === 0) {
    redirect('setup.php');
}
if ($u = current_user()) {
    redirect(home_for($u));
}

$email = input('email');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (too_many_attempts('login', 8, 15)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account is turned off. Ask the shop admin to turn it back on.';
            } else {
                login_user($user);
                $after = $_SESSION['after_login'] ?? '';
                unset($_SESSION['after_login']);
                if (is_string($after) && str_starts_with($after, BASE_PATH . '/app/') && !str_contains($after, '//')) {
                    header('Location: ' . $after, true, 303);
                    exit;
                }
                redirect(home_for($user));
            }
        } else {
            record_attempt('login');
            $error = 'That email and password don\'t match. Check them and try again.';
        }
    }
}

$title = 'Sign in';
$panelTitle = 'Every order, <span class="accent">one place.</span>';
$panelText = 'Create orders at the counter, move them through each step, and record payments as customers pick up.';
$panelStep = 3;
require __DIR__ . '/includes/layout/public_top.php';
?>
<p class="kicker">STAFF &amp; ADMIN</p>
<h2 class="form-title">Sign in</h2>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack" novalidate data-validate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
</form>
<p class="split-foot">Customer? <a href="<?= e(url('')) ?>">Track your order</a> · Forgot your password? Ask the shop admin to reset it.</p>
<?php require __DIR__ . '/includes/layout/public_bottom.php'; ?>
