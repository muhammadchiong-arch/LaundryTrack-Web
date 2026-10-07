<?php
// Admin: who did what. Status moves are in each order's history; this covers decisions and money corrections.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin');

$labels = [
    'booking' => 'Bookings', 'order' => 'Orders', 'payment' => 'Payments', 'staff' => 'Staff', 'customer' => 'Customers', 'settings' => 'Settings',
];
$type = input('type');
$where = '1=1';
$params = [];
if (isset($labels[$type])) {
    $where = 'a.action LIKE ?';
    $params[] = $type . '.%';
}
$rows = q(
    "SELECT a.*, u.name AS user_name, c.name AS customer_name FROM activity_log a
       LEFT JOIN users u ON u.id = a.user_id LEFT JOIN customers c ON c.id = a.customer_id
      WHERE $where ORDER BY a.created_at DESC, a.id DESC LIMIT 200",
    $params
)->fetchAll();

$title = 'Activity';
$active = 'activity';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<p class="lead">Booking decisions, cancellations, refunds, voided payments, and staff and settings changes. The latest 200 are shown.</p>
<nav class="pills" aria-label="Filter activity">
  <a class="pill<?= !isset($labels[$type]) ? ' on' : '' ?>" href="<?= e(url('app/activity.php')) ?>">All</a>
  <?php foreach ($labels as $k => $label): ?>
    <a class="pill<?= $type === $k ? ' on' : '' ?>" href="<?= e(url('app/activity.php?type=' . $k)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<section class="panel flush">
  <?php if (!$rows): ?>
    <p class="empty">Nothing recorded yet.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($rows as $r): ?>
        <li><div class="list-row">
          <span class="list-main"><b><?= e($r['detail']) ?></b>
            <span><?= e(fmt_when($r['created_at'])) ?> · <?= $r['user_name'] ? e($r['user_name']) : ($r['customer_name'] ? e($r['customer_name']) . ' (customer)' : 'System') ?></span></span>
          <span class="role-tag"><?= e($labels[explode('.', $r['action'])[0]] ?? $r['action']) ?></span>
        </div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
