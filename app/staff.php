<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin');

$errors = [];
$v = ['name' => input('name'), 'email' => input('email'), 'role' => input('role', 'staff')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    $target = (int) input('user_id');
    if ($action === 'add') {
        $pass = (string) ($_POST['password'] ?? '');
        if ($v['name'] === '') $errors['name'] = 'Enter a name.';
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
        elseif (q_val('SELECT COUNT(*) FROM users WHERE email = ?', [$v['email']])) $errors['email'] = 'Someone already uses this email.';
        if (!in_array($v['role'], ['admin', 'staff'], true)) $errors['role'] = 'Choose a role.';
        if (strlen($pass) < 8) $errors['password'] = 'Use at least 8 characters.';
        if (!$errors) {
            q('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
              [mb_substr($v['name'], 0, 100), $v['email'], password_hash($pass, PASSWORD_DEFAULT), $v['role']]);
            flash($v['name'] . ' can now sign in with ' . $v['email'] . '.');
            redirect('app/staff.php');
        }
    } elseif ($target === $me['id']) {
        flash('You can\'t turn off or change your own account here.', 'warning');
        redirect('app/staff.php');
    } elseif ($action === 'toggle') {
        q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$target]);
        $u = q_one('SELECT name, is_active FROM users WHERE id = ?', [$target]);
        if ($u) flash($u['name'] . ($u['is_active'] ? ' can sign in again.' : ' is turned off and can no longer sign in.'));
        redirect('app/staff.php');
    } elseif ($action === 'role') {
        $role = input('new_role');
        if (in_array($role, ['admin', 'staff'], true)) {
            q('UPDATE users SET role = ? WHERE id = ?', [$role, $target]);
            flash('Role updated.');
        }
        redirect('app/staff.php');
    } elseif ($action === 'reset') {
        $pass = (string) ($_POST['new_password'] ?? '');
        if (strlen($pass) < 8) {
            flash('The new password needs at least 8 characters.', 'error');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $target]);
            flash('Password reset. Give the new password to the staff member.');
        }
        redirect('app/staff.php');
    }
}

$users = q('SELECT * FROM users ORDER BY is_active DESC, role, name')->fetchAll();
$title = 'Staff';
$active = 'staff';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="page-head">
  <p class="lead">Staff can create orders, update statuses, record payments and manage customers. Admins can also see the dashboard and change staff and settings.</p>
</div>

<div class="order-layout">
  <div class="order-main">
    <section class="panel flush">
      <ul class="list people">
        <?php foreach ($users as $u): $self = (int) $u['id'] === $me['id']; ?>
          <li class="person<?= $u['is_active'] ? '' : ' off' ?>">
            <span class="avatar lg"><?= e(initials($u['name'])) ?></span>
            <span class="list-main"><b><?= e($u['name']) ?><?= $self ? ' <span class="muted-sm">(you)</span>' : '' ?></b>
              <span class="muted-sm"><?= e($u['email']) ?> · <?= $u['last_login_at'] ? 'Last sign-in ' . e(fmt_when($u['last_login_at'])) : 'Never signed in' ?></span></span>
            <?php if ($self): ?>
              <span class="role-tag"><?= e(ucfirst($u['role'])) ?></span>
            <?php else: ?>
              <span class="person-actions">
                <form method="post" class="inline-form"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <select name="new_role" aria-label="Role for <?= e($u['name']) ?>" data-autosubmit>
                    <option value="staff"<?= $u['role'] === 'staff' ? ' selected' : '' ?>>Staff</option>
                    <option value="admin"<?= $u['role'] === 'admin' ? ' selected' : '' ?>>Admin</option>
                  </select>
                  <noscript><button class="btn btn-sm btn-outline" type="submit">Save</button></noscript>
                </form>
                <button class="btn btn-sm btn-outline" type="button" data-toggle="reset-<?= (int) $u['id'] ?>" aria-expanded="false">Reset password</button>
                <form method="post" class="inline-form"<?= $u['is_active'] ? ' data-confirm="' . e('Turn off ' . $u['name'] . '? They will be signed out and can\'t sign in until you turn them back on.') . '"' : '' ?>><?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-sm <?= $u['is_active'] ? 'btn-outline danger' : 'btn-soft' ?>" type="submit"><?= $u['is_active'] ? 'Turn off' : 'Turn on' ?></button>
                </form>
              </span>
              <form method="post" class="reset-form" id="reset-<?= (int) $u['id'] ?>" hidden><?= csrf_field() ?>
                <input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="text" name="new_password" minlength="8" required placeholder="New password (8+ characters)" aria-label="New password for <?= e($u['name']) ?>" autocomplete="off">
                <button class="btn btn-sm btn-primary" type="submit">Set password</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
  <aside class="order-side">
    <section class="panel">
      <div class="panel-head"><h3>Add staff</h3></div>
      <form method="post" class="stack" novalidate data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field">
          <label for="name">Name</label>
          <input id="name" name="name" value="<?= e($v['name']) ?>" maxlength="100" required autocomplete="off">
          <?= field_error($errors, 'name') ?>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" value="<?= e($v['email']) ?>" required autocomplete="off">
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="field">
          <span class="label">Role</span>
          <div class="segmented" role="radiogroup" aria-label="Role">
            <label><input type="radio" name="role" value="staff"<?= $v['role'] !== 'admin' ? ' checked' : '' ?>><span>Staff</span></label>
            <label><input type="radio" name="role" value="admin"<?= $v['role'] === 'admin' ? ' checked' : '' ?>><span>Admin</span></label>
          </div>
        </div>
        <div class="field">
          <label for="password">Temporary password</label>
          <input id="password" name="password" type="text" minlength="8" required autocomplete="off">
          <?= field_error($errors, 'password') ?: '<small class="hint">At least 8 characters. They can change it after signing in.</small>' ?>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('plus') ?>Add staff</button>
      </form>
    </section>
  </aside>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
