<?php
// Customer home: what's happening now, the next drop-off, and one tap to book.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_customer();
refresh_bookings();

// The laundry currently at the shop (most urgent first: ready, then in progress).
$current = q_one(
    "SELECT o.*, s.name AS service_name, " . sql_paid() . " AS paid FROM orders o JOIN services s ON s.id = o.service_id
      WHERE o.customer_id = ? AND o.status NOT IN ('Completed','Cancelled')
      ORDER BY o.status = 'Ready for Pickup' DESC, o.created_at DESC LIMIT 1",
    [$me['id']]
);
$next = q_one(
    "SELECT b.*, s.name AS service_name, s.price_per_kg FROM bookings b JOIN services s ON s.id = b.service_id
      WHERE b.customer_id = ? AND b.status IN ('Pending','Confirmed') ORDER BY b.slot_date, b.slot_time LIMIT 1",
    [$me['id']]
);
$recent = q(
    "SELECT h.status, h.changed_at, o.order_no FROM order_status_history h JOIN orders o ON o.id = h.order_id
      WHERE o.customer_id = ? ORDER BY h.changed_at DESC, h.id DESC LIMIT 5",
    [$me['id']]
)->fetchAll();

$stamps = [];
if ($current) {
    foreach (q('SELECT status, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, id', [$current['id']]) as $h) {
        $stamps[$h['status']] = $h['changed_at'];
    }
}
$first = explode(' ', trim($me['name']))[0];
$title = 'Home';
$active = 'home';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="page-head">
  <div>
    <p class="date-line"><?= e(date('D, M j')) ?></p>
    <h2 class="display-sm">Hi <?= e($first) ?>.</h2>
  </div>
</div>

<?php if ($current): $order = $current; $bal = order_balance($current); ?>
  <section class="hero-card">
    <div class="hero-body">
      <div class="hero-meta"><span class="muted-strong"><?= e($current['order_no']) ?> · <?= e($current['service_name']) ?> · <?= e(kg($current['weight_kg'])) ?></span><?= status_chip($current['status']) ?></div>
      <h3 class="hero-title"><?= e(status_message($current['status'])) ?></h3>
      <?php require __DIR__ . '/../includes/partials/progress.php'; ?>
    </div>
    <aside class="hero-aside">
      <div class="stack-sm">
        <span class="kicker kicker-light">NEXT STEP</span>
        <b class="aside-title"><?= $current['status'] === 'Ready for Pickup' ? 'Pick up at the shop' : e(next_status($current['status']) . ' is next') ?></b>
        <span class="aside-text"><?= $current['status'] === 'Ready for Pickup' ? ($bal > 0 ? 'Balance to pay at pickup: ' . e(money($bal)) . '.' : 'Fully paid. Just bring your claim slip.') : 'This page updates as the shop moves your laundry along.' ?></span>
      </div>
      <dl class="aside-rows">
        <div><dt>Total</dt><dd><?= e(money($current['amount_due'])) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e(money($current['paid'])) ?></dd></div>
      </dl>
    </aside>
  </section>
<?php endif; ?>

<div class="home-grid">
  <section class="panel">
    <div class="panel-head"><h3>Next drop-off</h3><?php if ($next): ?><a href="<?= e(url('my/booking.php?id=' . $next['id'])) ?>">Details</a><?php endif; ?></div>
    <?php if ($next): ?>
      <div class="next-drop">
        <b class="next-when"><?= e(slot_label($next)) ?></b>
        <span class="muted"><?= e($next['booking_no']) ?> · <?= e($next['service_name']) ?></span>
        <?= booking_chip($next['status']) ?>
        <p class="muted-sm"><?= e(booking_message($next)) ?></p>
      </div>
    <?php else: ?>
      <p class="empty"><?= $current ? 'Nothing booked yet.' : 'No laundry with us right now.' ?></p>
      <a class="btn btn-primary btn-block" href="<?= e(url('my/book.php')) ?>"><?= icon('plus') ?>Book a drop-off</a>
    <?php endif; ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h3>Recent updates</h3><a href="<?= e(url('my/bookings.php')) ?>">All bookings</a></div>
    <?php if (!$recent): ?>
      <p class="empty">Updates about your laundry will show here.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($recent as $r): ?>
          <li><div class="list-row"><span class="list-main"><b><?= e($r['order_no']) ?></b><span><?= e(fmt_when($r['changed_at'])) ?></span></span><?= status_chip($r['status']) ?></div></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
