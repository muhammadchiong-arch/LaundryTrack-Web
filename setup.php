<?php
// First run only: creates the admin account. Turns itself off once any user exists.
require __DIR__ . '/includes/bootstrap.php';

if ((int) q_val('SELECT COUNT(*) FROM users') > 0) {
    flash('The shop is already set up. Sign in instead.', 'warning');
    redirect('login.php');
}

$v = ['shop_name' => input('shop_name', 'LaundryTrack'), 'name' => input('name'), 'email' => input('email')];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    if ($v['shop_name'] === '') $errors['shop_name'] = 'Enter the shop name.';
    if ($v['name'] === '') $errors['name'] = 'Enter your name.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
    if (strlen($pass) < 8) $errors['password'] = 'Use at least 8 characters.';
    elseif ($pass !== ($_POST['password2'] ?? '')) $errors['password2'] = 'The passwords don\'t match.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        // Re-check inside the transaction so two browsers can't both create an admin.
        if ((int) q_val('SELECT COUNT(*) FROM users FOR UPDATE') === 0) {
            q("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
              [$v['name'], $v['email'], password_hash($pass, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            q("INSERT INTO settings (`key`, `value`) VALUES ('shop_name', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$v['shop_name']]);
            $pdo->commit();
            login_user(['id' => $id]);
            flash('Welcome! Your shop is ready. Add your staff under Staff.');
            redirect('app/dashboard.php');
        }
        $pdo->rollBack();
        redirect('login.php');
    }
}

$title = 'Set up';
$panelTitle = 'Set up your <span class="accent">shop.</span>';
$panelText = 'Create the admin account. You can add staff, services and prices once you are in.';
$panelStep = 0;
require __DIR__ . '/includes/layout/public_top.php';
?>
<p class="kicker">FIRST RUN</p>
<h2 class="form-title">Create the admin</h2>
<form method="post" class="stack" novalidate data-validate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="shop_name">Shop name</label>
    <input id="shop_name" name="shop_name" value="<?= e($v['shop_name']) ?>" required maxlength="80">
    <?= field_error($errors, 'shop_name') ?>
  </div>
  <div class="field">
    <label for="name">Your name</label>
    <input id="name" name="name" value="<?= e($v['name']) ?>" required maxlength="100" autocomplete="name">
    <?= field_error($errors, 'name') ?>
  </div>
  <div class="field">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="<?= e($v['email']) ?>" required autocomplete="username">
    <?= field_error($errors, 'email') ?>
  </div>
  <div class="field-row">
    <div class="field">
      <label for="password">Password</label>
      <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
      <?= field_error($errors, 'password') ?: '<small class="hint">At least 8 characters.</small>' ?>
    </div>
    <div class="field">
      <label for="password2">Confirm password</label>
      <input id="password2" type="password" name="password2" minlength="8" required autocomplete="new-password" data-match="password">
      <?= field_error($errors, 'password2') ?>
    </div>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Create admin and continue</button>
</form>
<?php require __DIR__ . '/includes/layout/public_bottom.php'; ?>
