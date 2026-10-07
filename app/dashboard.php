<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin');
handle_advance_post('app/dashboard.php');

$ordersToday = (int) q_val('SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()');
$salesToday = (float) q_val('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE created_at >= CURDATE()');
$unpaid = (float) q_val(
    "SELECT COALESCE(SUM(GREATEST(o.amount_due - COALESCE(p.paid, 0), 0)), 0)
       FROM orders o LEFT JOIN (SELECT order_id, SUM(amount) paid FROM transactions GROUP BY order_id) p ON p.order_id = o.id"
);

$lanes = array_fill_keys(array_slice(STATUSES, 0, 5), []);
$laneCounts = array_fill_keys(array_keys($lanes), 0);
foreach (q("SELECT status, COUNT(*) n FROM orders WHERE status <> 'Completed' GROUP BY status") as $r) {
    $laneCounts[$r['status']] = (int) $r['n'];
}
$rows = q(
    "SELECT o.id, o.order_no, o.status, o.weight_kg, o.amount_due, o.created_at, c.name AS customer_name, c.phone, s.name AS service_name,
            (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.order_id = o.id) AS paid
       FROM orders o JOIN customers c ON c.id = o.customer_id JOIN services s ON s.id = o.service_id
      WHERE o.status <> 'Completed' ORDER BY o.created_at"
)->fetchAll();
foreach ($rows as $r) {
    if (count($lanes[$r['status']]) < 8) {
        $lanes[$r['status']][] = $r;
    }
}
$inProgress = array_sum($laneCounts) - $laneCounts['Ready for Pickup'];

$recent = q(
    'SELECT h.status, h.changed_at, o.id, o.order_no, c.name AS customer_name, u.name AS user_name
       FROM order_status_history h JOIN orders o ON o.id = h.order_id JOIN customers c ON c.id = o.customer_id
       LEFT JOIN users u ON u.id = h.changed_by
      ORDER BY h.changed_at DESC, h.id DESC LIMIT 8'
)->fetchAll();

$ready = $laneCounts['Ready for Pickup'];
if ($ready) {
    $headline = $ready . ($ready === 1 ? ' order' : ' orders') . ' ready for pickup';
} elseif ($inProgress) {
    $headline = $inProgress . ($inProgress === 1 ? ' order' : ' orders') . ' in progress';
} else {
    $headline = $ordersToday ? 'All caught up' : 'No orders yet today';
}
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$title = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="page-head">
  <div>
    <p class="date-line"><?= e(date('D, M j')) ?> · <?= e($greeting) ?>, <?= e(explode(' ', $me['name'])[0]) ?></p>
    <h2 class="display-sm"><?= e($headline) ?></h2>
  </div>
  <a class="btn btn-primary" href="<?= e(url('app/order-new.php')) ?>"><?= icon('plus') ?>New order</a>
</div>

<section class="stat-strip" aria-label="Today">
  <div class="stat"><span>Orders today</span><b><?= $ordersToday ?></b></div>
  <div class="stat"><span>Collected today</span><b><?= e(money($salesToday)) ?></b></div>
  <a class="stat" href="<?= e(url('app/orders.php')) ?>"><span>In progress</span><b><?= $inProgress ?></b></a>
  <a class="stat" href="<?= e(url('app/orders.php?status=' . rawurlencode('Ready for Pickup'))) ?>"><span>Ready for Pickup</span><b><?= $laneCounts['Ready for Pickup'] ?></b></a>
  <a class="stat" href="<?= e(url('app/orders.php?status=all&pay=unpaid')) ?>"><span>Unpaid balance</span><b><?= e(money($unpaid)) ?></b></a>
</section>

<section class="board-wrap" aria-label="Order board">
  <div class="section-head"><h3>Order board</h3><a href="<?= e(url('app/orders.php')) ?>">All orders</a></div>
  <div class="board">
    <?php foreach ($lanes as $status => $cards): ?>
      <div class="lane">
        <div class="lane-head"><span class="lane-dot dot-<?= e(status_slug($status)) ?>"></span><b><?= e($status) ?></b><span class="lane-n"><?= $laneCounts[$status] ?></span></div>
        <?php foreach ($cards as $o): ?>
          <div class="lane-card">
            <a class="lane-card-link" href="<?= e(url('app/order.php?id=' . $o['id'])) ?>">
              <span class="lane-card-top"><b class="order-no"><?= e($o['order_no']) ?></b><span><?= e(fmt_short($o['created_at'])) ?></span></span>
              <b class="lane-card-name"><?= e($o['customer_name']) ?></b>
              <span class="muted-sm"><?= e($o['service_name']) ?> · <?= e(kg($o['weight_kg'])) ?> · <?= e(money($o['amount_due'])) ?> · <?= e(payment_state((float) $o['amount_due'], (float) $o['paid'])[1]) ?></span>
              <?php if ($status === 'Ready for Pickup'): ?><span class="muted-sm"><?= e(fmt_phone($o['phone'])) ?></span><?php endif; ?>
            </a>
            <?= advance_button($o, 'btn btn-sm btn-soft btn-block') ?>
          </div>
        <?php endforeach; ?>
        <?php if ($laneCounts[$status] > count($cards)): ?>
          <a class="lane-more" href="<?= e(url('app/orders.php?status=' . rawurlencode($status))) ?>">+<?= $laneCounts[$status] - count($cards) ?> more</a>
        <?php elseif (!$cards): ?>
          <span class="lane-empty">No orders</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><h3>Recent updates</h3></div>
  <?php if (!$recent): ?>
    <p class="empty">Status changes will show here as orders move along.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($recent as $r): ?>
        <li><a class="list-row" href="<?= e(url('app/order.php?id=' . $r['id'])) ?>">
          <span class="list-main"><b><?= e($r['order_no']) ?> · <?= e($r['customer_name']) ?></b><span class="muted"><?= e(fmt_when($r['changed_at'])) ?><?= $r['user_name'] ? ' · by ' . e($r['user_name']) : '' ?></span></span>
          <?= status_chip($r['status']) ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
