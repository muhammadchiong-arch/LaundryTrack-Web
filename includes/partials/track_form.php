<?php
/** Track form (order no. + last 4 digits). @var string $orderNo @var string $error */
$hasUsers = (int) q_val('SELECT COUNT(*) FROM users') > 0;
$title = 'Track your laundry';
$panelTitle = 'Your laundry, <span class="accent">every step.</span>';
$panelText = 'Enter your order number and the last 4 digits of your phone to see where your laundry is right now.';
$panelStep = 1;
require __DIR__ . '/../layout/public_top.php';
?>
<p class="kicker">TRACK AN ORDER</p>
<h2 class="form-title">Where's my laundry?</h2>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack" novalidate data-validate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="order_no">Order number</label>
    <input id="order_no" name="order_no" value="<?= e($orderNo) ?>" placeholder="LAU-1001" autocomplete="off" autocapitalize="characters" required>
    <small class="hint">It's printed on your claim slip.</small>
  </div>
  <div class="field">
    <label for="last4">Last 4 digits of your phone</label>
    <input id="last4" name="last4" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="4567" autocomplete="off" required>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Track order</button>
</form>
<p class="split-foot">
  Shop staff? <a href="<?= e(url('login.php')) ?>">Sign in</a>
  <?php if (!$hasUsers): ?> · First time here? <a href="<?= e(url('setup.php')) ?>">Set up the shop</a><?php endif; ?>
</p>
<?php require __DIR__ . '/../layout/public_bottom.php'; ?>
