<?php
// Customer view of one order. Access comes from index.php (order no. + phone last 4)
// and covers that customer's orders for 2 hours.
require __DIR__ . '/includes/bootstrap.php';

$orderNo = strtoupper(input('o'));
$customerId = (int) ($_SESSION['track_customer'] ?? 0);
if ($customerId && ($_SESSION['track_until'] ?? 0) < time()) {
    unset($_SESSION['track_customer'], $_SESSION['track_until']);
    $customerId = 0;
}

$order = $customerId ? q_one(
    'SELECT o.*, s.name AS service_name, c.name AS customer_name,
            (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.order_id = o.id) AS paid
       FROM orders o JOIN services s ON s.id = o.service_id JOIN customers c ON c.id = o.customer_id
      WHERE o.order_no = ? AND o.customer_id = ?',
    [$orderNo, $customerId]
) : null;

if (!$order) {
    flash('Enter your order number and phone digits to view your order.', 'warning');
    redirect('?o=' . rawurlencode($orderNo));
}

$stamps = [];
foreach (q('SELECT status, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, id', [$order['id']]) as $h) {
    $stamps[$h['status']] = $h['changed_at'];
}
$others = q(
    'SELECT o.order_no, o.status, o.created_at, o.amount_due, s.name AS service_name
       FROM orders o JOIN services s ON s.id = o.service_id
      WHERE o.customer_id = ? AND o.id <> ? ORDER BY o.created_at DESC LIMIT 20',
    [$customerId, $order['id']]
)->fetchAll();

$due = (float) $order['amount_due'];
$paid = (float) $order['paid'];
$balance = max(0, $due - $paid);
[$payKey, $payLabel] = payment_state($due, $paid);
$firstName = explode(' ', trim($order['customer_name']))[0];
$shopPhone = setting('shop_phone');
$shopAddress = setting('shop_address');

$title = $order['order_no'];
require __DIR__ . '/includes/layout/head.php';
?>
<body class="public track-page">
<header class="track-top">
  <a class="brand brand-dark" href="<?= e(url('')) ?>"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span>Laundry<span>Track</span></span></a>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('')) ?>"><?= icon('search') ?>Track another order</a>
</header>
<main class="track-main">
  <p class="kicker"><?= e(strtoupper(setting('shop_name', 'LaundryTrack'))) ?></p>
  <h1 class="display-sm">Hi <?= e($firstName) ?>, here's your laundry.</h1>

  <section class="hero-card">
    <div class="hero-body">
      <div class="hero-meta">
        <span class="muted-strong"><?= e($order['order_no']) ?> · <?= e($order['service_name']) ?> · <?= e(kg($order['weight_kg'])) ?></span>
        <?= status_chip($order['status']) ?>
      </div>
      <h2 class="hero-title"><?= e(status_message($order['status'])) ?></h2>
      <?php require __DIR__ . '/includes/partials/progress.php'; ?>
    </div>
    <aside class="hero-aside">
      <div class="stack-sm">
        <span class="kicker kicker-light">NEXT STEP</span>
        <b class="aside-title"><?php
          echo e(match ($order['status']) {
              'Ready for Pickup' => 'Pick up at the shop',
              'Completed' => 'All done',
              default => next_status($order['status']) . ' is next',
          });
        ?></b>
        <span class="aside-text"><?php
          echo e(match ($order['status']) {
              'Ready for Pickup' => $balance > 0 ? 'Bring your claim slip. Balance to pay at pickup: ' . money($balance) . '.' : 'Bring your claim slip. Your order is fully paid.',
              'Completed' => 'Picked up ' . fmt_when($order['completed_at']) . '. Thank you!',
              default => 'This page shows each step as the shop updates it. Reload to see the latest.',
          });
        ?></span>
      </div>
      <dl class="aside-rows">
        <div><dt>Total</dt><dd><?= e(money($due)) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e(money($paid)) ?></dd></div>
        <div><dt>Payment</dt><dd><?= e($payLabel) ?><?= $balance > 0 ? ' · ' . e(money($balance)) . ' left' : '' ?></dd></div>
        <div><dt>Dropped off</dt><dd><?= e(fmt_dt($order['created_at'])) ?></dd></div>
      </dl>
    </aside>
  </section>

  <section class="panel">
    <div class="panel-head"><h3>Order history</h3></div>
    <?php if (!$others): ?>
      <p class="empty">This is your first order with us.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($others as $o): ?>
          <li><a class="list-row" href="<?= e(url('track.php?o=' . rawurlencode($o['order_no']))) ?>">
            <span class="list-main"><b><?= e($o['order_no']) ?></b><span class="muted"><?= e($o['service_name']) ?> · <?= e(fmt_dt($o['created_at'], 'M j, Y')) ?></span></span>
            <span class="num"><?= e(money($o['amount_due'])) ?></span>
            <?= status_chip($o['status']) ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if ($shopPhone || $shopAddress): ?>
    <p class="track-foot"><?= e(setting('shop_name', 'LaundryTrack')) ?><?= $shopAddress ? ' · ' . e($shopAddress) : '' ?><?= $shopPhone ? ' · ' . e($shopPhone) : '' ?></p>
  <?php endif; ?>
</main>
</body>
</html>
