<?php
// Public home: customers track an order with its number + the last 4 digits of their phone.
require __DIR__ . '/includes/bootstrap.php';

$orderNo = strtoupper(input('order_no', input('o')));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $last4 = preg_replace('/\D+/', '', input('last4'));
    // "1001" and "lau1001" both mean LAU-1001.
    if (preg_match('/^(?:LAU)?-?\s*(\d{1,10})$/', str_replace(' ', '', $orderNo), $m)) {
        $orderNo = 'LAU-' . $m[1];
    }

    if (too_many_attempts('track', 10, 15)) {
        $error = 'Too many tries. Please wait 15 minutes, or ask the shop for your order status.';
    } elseif ($orderNo === '' || strlen($last4) !== 4) {
        $error = 'Enter your order number and the last 4 digits of your phone number.';
    } else {
        $row = q_one(
            'SELECT o.id, o.customer_id, c.phone FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.order_no = ?',
            [$orderNo]
        );
        if ($row && strlen($row['phone']) >= 4 && hash_equals(substr($row['phone'], -4), $last4)) {
            session_regenerate_id(true);
            $_SESSION['track_customer'] = (int) $row['customer_id'];
            $_SESSION['track_until'] = time() + 2 * 3600;
            redirect('track.php?o=' . rawurlencode($orderNo));
        }
        record_attempt('track');
        $error = "We couldn't find an order with that number and phone. Check the number on your claim slip.";
    }
}

$hasUsers = (int) q_val('SELECT COUNT(*) FROM users') > 0;
$title = 'Track your laundry';
$panelTitle = 'Your laundry, <span class="accent">every step.</span>';
$panelText = 'Enter your order number and the last 4 digits of your phone to see where your laundry is right now.';
$panelStep = 1;
require __DIR__ . '/includes/layout/public_top.php';
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
<?php require __DIR__ . '/includes/layout/public_bottom.php'; ?>
