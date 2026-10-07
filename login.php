<?php
// One sign-in for everyone: shop staff/admins (users) and customers with an online account.
require __DIR__ . '/includes/bootstrap.php';

if ((int) q_val('SELECT COUNT(*) FROM users') === 0) {
    redirect('setup.php');
}
if ($u = current_user() ?? current_customer()) {
    redirect(home_for($u));
}

$email = strtolower(input('email'));
$error = '';

// Only return to a page inside the app or the customer area after sign-in.
function after_login_target(string $prefix): ?string
{
    $after = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);
    return (is_string($after) && str_starts_with($after, BASE_PATH . $prefix) && !str_contains($after, '//')) ? $after : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    if (too_many_attempts('login', 8, 15)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Enter your email and password.';
    } else {
        $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        $cust = $user ? null : q_one('SELECT * FROM customers WHERE email = ? AND password_hash IS NOT NULL', [$email]);
        $acct = $user ?? $cust;
        if ($acct && password_verify($pass, $acct['password_hash'])) {
            if (!$acct['is_active']) {
                $error = $user ? 'This account is turned off. Ask the shop admin to turn it back on.'
                               : 'This account is turned off. Please contact the shop.';
            } elseif ($user) {
                login_user($user);
                if ($to = after_login_target('/app/')) { header('Location: ' . $to, true, 303); exit; }
                redirect(home_for($user));
            } else {
                login_customer($cust);
                if ($to = after_login_target('/my/')) { header('Location: ' . $to, true, 303); exit; }
                redirect('my/index.php');
            }
        } else {
            record_attempt('login');
            $error = 'That email and password don\'t match. Check them and try again.';
        }
    }
}

$title = 'Sign in';
$panelTitle = 'Every order, <span class="accent">one place.</span>';
$panelText = 'Book a drop-off time, follow every step, and know exactly when your laundry is ready.';
$panelStep = 3;
require __DIR__ . '/includes/layout/public_top.php';
?>
<p class="kicker">WELCOME BACK</p>
<h2 class="form-title">Sign in</h2>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack" novalidate data-validate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" placeholder="maria.santos@gmail.com" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
</form>
<p class="split-foot">New customer? <a href="<?= e(url('register.php')) ?>">Create an account</a> to book online.<br>
  Dropped off without an account? <a href="<?= e(url('track.php')) ?>">Track your order</a>.<br>
  Forgot your password? Ask the shop to reset it.</p>
<?php require __DIR__ . '/includes/layout/public_bottom.php'; ?>
