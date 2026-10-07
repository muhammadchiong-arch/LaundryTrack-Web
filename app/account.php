<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = q_val('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $hash)) $errors['current_password'] = 'That isn\'t your current password.';
    if (strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
    elseif ($new !== ($_POST['new_password2'] ?? '')) $errors['new_password2'] = 'The passwords don\'t match.';
    if (!$errors) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        session_regenerate_id(true);
        flash('Password changed.');
        redirect('app/account.php');
    }
}

$title = 'Change password';
$active = '';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<section class="panel narrow-panel">
  <div class="panel-head"><h3><?= e($me['name']) ?></h3><span class="muted-sm"><?= e($me['email']) ?></span></div>
  <form method="post" class="stack" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="current_password">Current password</label>
      <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
      <?= field_error($errors, 'current_password') ?>
    </div>
    <div class="field">
      <label for="new_password">New password</label>
      <input id="new_password" type="password" name="new_password" minlength="8" required autocomplete="new-password">
      <?= field_error($errors, 'new_password') ?: '<small class="hint">At least 8 characters.</small>' ?>
    </div>
    <div class="field">
      <label for="new_password2">Confirm new password</label>
      <input id="new_password2" type="password" name="new_password2" minlength="8" required autocomplete="new-password" data-match="new_password">
      <?= field_error($errors, 'new_password2') ?>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Change password</button></div>
  </form>
</section>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
