<?php
// One booking for the signed-in customer: status, what to do next, and the order once dropped off.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_customer();
refresh_bookings();

$b = load_booking((int) input('id'));
if (!$b || (int) $b['customer_id'] !== $me['id']) {
    // Another customer's booking looks exactly like a missing one.
    http_response_code(404);
    $title = 'Booking not found';
    $message = 'We could not find that booking in your account.';
    $back = url('my/bookings.php');
    require __DIR__ . '/../includes/layout/error.php';
    exit;
}
$self = 'my/booking.php?id=' . $b['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'cancel') {
    $err = change_booking($b, 'cancel', 'customer', mb_substr(input('reason'), 0, 200) ?: 'Cancelled by customer');
    flash($err ?: 'Booking ' . $b['booking_no'] . ' cancelled. The time is free for others.', $err ? 'error' : 'success');
    redirect($self);
}

$order = null;
$stamps = [];
if ($b['order_id']) {
    $order = q_one('SELECT o.*, ' . sql_paid() . ' AS paid FROM orders o WHERE o.id = ?', [$b['order_id']]);
    foreach (q('SELECT status, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, id', [$order['id']]) as $h) {
        $stamps[$h['status']] = $h['changed_at'];
    }
}
$isNew = input('new') === '1' && $b['status'] === 'Pending';

$title = $b['booking_no'];
$active = 'bookings';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<a class="back-link" href="<?= e(url('my/bookings.php')) ?>"><?= icon('back') ?>My bookings</a>

<?php if ($isNew): ?>
  <ol class="stepper" aria-label="Booking steps">
    <?php foreach (['Service', 'Date & time', 'Details', 'Review', 'Done'] as $i => $label): ?>
      <li class="<?= $i < 4 ? 'done' : 'current' ?>"<?= $i === 4 ? ' aria-current="step"' : '' ?>><span class="stepper-dot"><?= $i < 4 ? icon('check') : 5 ?></span><span class="stepper-label"><?= e($label) ?></span></li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<section class="hero-card">
  <div class="hero-body">
    <div class="hero-meta">
      <span class="muted-strong"><?= e($b['booking_no']) ?> · <?= e($b['service_name']) ?></span>
      <?= booking_chip($b['status']) ?>
    </div>
    <h2 class="hero-title"><?= $isNew ? 'Booking sent. ' : '' ?><?= e(booking_message($b)) ?></h2>
    <?php if ($order): ?>
      <p class="muted">Order <b class="order-no"><?= e($order['order_no']) ?></b> · <?= e(kg($order['weight_kg'])) ?> · <?= status_chip($order['status']) ?></p>
      <?php require __DIR__ . '/../includes/partials/progress.php'; ?>
    <?php endif; ?>
    <?php if (booking_can($b, 'cancel', 'customer')): ?>
      <form method="post" class="cancel-form" data-confirm="Cancel this booking? Your drop-off time will be given to someone else.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn-outline danger btn-sm" type="submit">Cancel booking</button>
      </form>
    <?php endif; ?>
  </div>
  <aside class="hero-aside">
    <div class="stack-sm">
      <span class="kicker kicker-light">DROP-OFF</span>
      <b class="aside-title"><?= e(date('l, M j', strtotime($b['slot_date']))) ?></b>
      <span class="aside-text"><?= e(date('g:i A', strtotime('2000-01-01 ' . $b['slot_time']))) ?> at <?= e(setting('shop_name', 'the shop')) ?><?= setting('shop_address') ? ', ' . e(setting('shop_address')) : '' ?></span>
    </div>
    <dl class="aside-rows">
      <div><dt>Rate</dt><dd><?= e(money($b['price_per_kg'])) ?>/kg</dd></div>
      <?php if ($order): ?>
        <div><dt>Total</dt><dd><?= e(money($order['amount_due'])) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e(money($order['paid'])) ?></dd></div>
      <?php else: ?>
        <div><dt>Estimate</dt><dd><?= $b['est_weight_kg'] ? 'About ' . e(money($b['est_weight_kg'] * $b['price_per_kg'])) : 'Weighed at drop-off' ?></dd></div>
        <div><dt>Payment</dt><dd>Cash or GCash at the shop</dd></div>
      <?php endif; ?>
      <?php if ($b['notes']): ?><div><dt>Notes</dt><dd><?= e($b['notes']) ?></dd></div><?php endif; ?>
    </dl>
  </aside>
</section>
<?php if ($isNew): ?>
  <p class="muted">You'll see the status change here when the shop confirms. <a href="<?= e(url('my/index.php')) ?>">Go to home</a></p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
