<?php
// Customer sign-up: only what the shop needs (name, mobile for the drop-off, email + password to sign in).
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user() ?? current_customer()) {
    redirect(home_for($u));
}

$v = ['name' => input('name'), 'phone' => input('phone'), 'email' => strtolower(input('email'))];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    $phone = phone_digits($v['phone']);
    if (too_many_attempts('register', 5, 60)) {
        $errors['form'] = 'Too many new accounts from this device. Please try again later.';
    }
    if (mb_strlen($v['name']) < 2) $errors['name'] = 'Enter your full name.';
    if (!preg_match('/^09\d{9}$/', $phone)) $errors['phone'] = 'Enter an 11-digit mobile number starting with 09.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email, like maria.santos@gmail.com.';
    } elseif (q_val('SELECT 1 FROM customers WHERE email = ?', [$v['email']]) || q_val('SELECT 1 FROM users WHERE email = ?', [$v['email']])) {
        $errors['email'] = 'This email already has an account. Sign in instead.';
    }
    if (strlen($pass) < 8) $errors['password'] = 'Use at least 8 characters.';

    if (!$errors) {
        record_attempt('register');
        q('INSERT INTO customers (name, phone, email, password_hash) VALUES (?, ?, ?, ?)',
          [mb_substr($v['name'], 0, 100), $phone, $v['email'], password_hash($pass, PASSWORD_DEFAULT)]);
        $id = (int) db()->lastInsertId();
        login_customer(['id' => $id]);
        flash('Your account is ready. Book your first drop-off below.');
        redirect('my/book.php');
    }
}

$title = 'Create an account';
$panelTitle = 'Book a time. <span class="accent">Skip the line.</span>';
$panelText = 'Choose a drop-off time online, then follow your laundry from Received to Ready for Pickup.';
$panelStep = 0;
require __DIR__ . '/includes/layout/public_top.php';
?>
<p class="kicker">NEW CUSTOMER</p>
<h2 class="form-title">Create an account</h2>
<?php if (isset($errors['form'])): ?><div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
<form method="post" class="stack" novalidate data-validate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="name">Full name</label>
    <input id="name" name="name" value="<?= e($v['name']) ?>" placeholder="Maria Santos" autocomplete="name" maxlength="100" required>
    <?= field_error($errors, 'name') ?>
  </div>
  <div class="field">
    <label for="phone">Mobile number</label>
    <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($v['phone']) ?>" placeholder="09XXXXXXXXX" autocomplete="tel" required>
    <?= field_error($errors, 'phone') ?: '<small class="hint">The shop uses it to reach you about your laundry.</small>' ?>
  </div>
  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($v['email']) ?>" placeholder="maria.santos@gmail.com" autocomplete="email" required>
    <?= field_error($errors, 'email') ?>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
    <?= field_error($errors, 'password') ?: '<small class="hint">At least 8 characters.</small>' ?>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button>
</form>
<p class="split-foot">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
<?php require __DIR__ . '/includes/layout/public_bottom.php'; ?>
