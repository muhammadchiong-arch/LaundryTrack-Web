<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_customer();

$errors = [];
$v = ['name' => input('name', $me['name']), 'phone' => input('phone', fmt_phone($me['phone']))];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (input('action') === 'details') {
        $phone = phone_digits($v['phone']);
        if (mb_strlen($v['name']) < 2) $errors['name'] = 'Enter your full name.';
        if (!preg_match('/^09\d{9}$/', $phone)) $errors['phone'] = 'Enter an 11-digit mobile number starting with 09.';
        if (!$errors) {
            q('UPDATE customers SET name = ?, phone = ? WHERE id = ?', [mb_substr($v['name'], 0, 100), $phone, $me['id']]);
            flash('Details saved.');
            redirect('my/profile.php');
        }
    } elseif (input('action') === 'password') {
        $hash = q_val('SELECT password_hash FROM customers WHERE id = ?', [$me['id']]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $hash)) $errors['current_password'] = 'That isn\'t your current password.';
        if (strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
        if (!$errors) {
            q('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('Password changed.');
            redirect('my/profile.php');
        }
    }
}

$title = 'Profile';
$active = 'profile';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="settings-grid">
  <section class="panel">
    <div class="panel-head"><h3>Your details</h3><span class="muted-sm"><?= e($me['email']) ?></span></div>
    <form method="post" class="stack" novalidate data-validate>
      <?= csrf_field() ?><input type="hidden" name="action" value="details">
      <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($v['name']) ?>" maxlength="100" required autocomplete="name"><?= field_error($errors, 'name') ?></div>
      <div class="field"><label for="phone">Mobile number</label><input id="phone" name="phone" type="tel" value="<?= e($v['phone']) ?>" required autocomplete="tel"><?= field_error($errors, 'phone') ?></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save details</button></div>
    </form>
  </section>
  <section class="panel">
    <div class="panel-head"><h3>Password</h3></div>
    <form method="post" class="stack" novalidate data-validate>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field"><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"><?= field_error($errors, 'current_password') ?></div>
      <div class="field"><label for="new_password">New password</label><input id="new_password" type="password" name="new_password" minlength="8" required autocomplete="new-password"><?= field_error($errors, 'new_password') ?: '<small class="hint">At least 8 characters.</small>' ?></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Change password</button></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
