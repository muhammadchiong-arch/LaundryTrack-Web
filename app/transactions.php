<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$ranges = [
    'today'     => ['Today', date('Y-m-d'), date('Y-m-d')],
    'yesterday' => ['Yesterday', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'week'      => ['Last 7 days', date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'month'     => ['This month', date('Y-m-01'), date('Y-m-d')],
];
$range = input('range', 'today');
$from = input('from');
$to = input('to');
if (isset($ranges[$range]) && !($range === 'custom')) {
    [, $from, $to] = $ranges[$range];
} else {
    $range = 'custom';
    $valid = fn ($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    if (!$valid($from)) $from = date('Y-m-d');
    if (!$valid($to)) $to = $from;
    if ($to < $from) [$from, $to] = [$to, $from];
}
$method = in_array(input('method'), ['cash', 'gcash'], true) ? input('method') : '';

$where = 't.created_at >= ? AND t.created_at < ? + INTERVAL 1 DAY';
$params = [$from . ' 00:00:00', $to . ' 00:00:00'];
// Net per method: payments minus refunds, voided rows left out.
$totals = q("SELECT method, SUM(kind = 'payment') n, SUM(IF(kind = 'refund', -amount, amount)) total FROM transactions t
              WHERE $where AND voided_at IS NULL GROUP BY method", $params)->fetchAll();
$refunds = (float) q_val("SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE $where AND voided_at IS NULL AND kind = 'refund'", $params);
$byMethod = ['cash' => 0.0, 'gcash' => 0.0];
$count = 0;
foreach ($totals as $t) {
    $byMethod[$t['method']] = (float) $t['total'];
    $count += (int) $t['n'];
}
if ($method) {
    $where .= ' AND t.method = ?';
    $params[] = $method;
}
$rows = q(
    "SELECT t.*, o.order_no, o.id AS order_id, c.name AS customer_name, u.name AS user_name
       FROM transactions t JOIN orders o ON o.id = t.order_id JOIN customers c ON c.id = o.customer_id
       LEFT JOIN users u ON u.id = t.received_by
      WHERE $where ORDER BY t.created_at DESC, t.id DESC LIMIT 500",
    $params
)->fetchAll();
$unpaid = q_one(
    "SELECT COUNT(*) n, COALESCE(SUM(o.amount_due - COALESCE(p.paid, 0)), 0) bal
       FROM orders o " . sql_paid_join() . "
      WHERE o.status <> 'Cancelled' AND o.amount_due > COALESCE(p.paid, 0) + 0.004"
);

$title = $me['role'] === 'admin' ? 'Sales' : 'Payments';
$active = 'transactions';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<nav class="pills" aria-label="Date range">
  <?php foreach ($ranges as $key => [$label]): ?>
    <a class="pill<?= $range === $key ? ' on' : '' ?>" href="<?= e(url('app/transactions.php?range=' . $key)) ?>"<?= $range === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<form class="filter-bar" method="get">
  <input type="hidden" name="range" value="custom">
  <label class="mini-field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="mini-field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <label class="mini-field"><span>Method</span>
    <select name="method"><option value="">All</option><option value="cash"<?= $method === 'cash' ? ' selected' : '' ?>>Cash</option><option value="gcash"<?= $method === 'gcash' ? ' selected' : '' ?>>GCash</option></select>
  </label>
  <button class="btn btn-outline" type="submit">Show</button>
</form>

<section class="sum-grid" aria-label="Totals">
  <div class="sum-card"><span>Collected · <?= e($from === $to ? date('M j', strtotime($from)) : date('M j', strtotime($from)) . ' to ' . date('M j', strtotime($to))) ?></span><b><?= e(money($byMethod['cash'] + $byMethod['gcash'])) ?></b><small><?= $count ?> payment<?= $count === 1 ? '' : 's' ?><?= $refunds > 0 ? ' · ' . e(money($refunds)) . ' refunded' : '' ?></small></div>
  <div class="sum-card"><span>Cash</span><b><?= e(money($byMethod['cash'])) ?></b></div>
  <div class="sum-card"><span>GCash</span><b><?= e(money($byMethod['gcash'])) ?></b></div>
  <a class="sum-card warn" href="<?= e(url('app/orders.php?status=all&pay=unpaid')) ?>"><span>Unpaid balance · all days</span><b><?= e(money($unpaid['bal'])) ?></b><small><?= (int) $unpaid['n'] ?> order<?= (int) $unpaid['n'] === 1 ? '' : 's' ?> not fully paid</small></a>
</section>

<section class="panel flush">
  <?php if (!$rows): ?>
    <p class="empty">No payments in this period.</p>
  <?php else: ?>
    <div class="table tx-table" role="table" aria-label="Transactions">
      <div class="tr th" role="row">
        <span role="columnheader">Date</span><span role="columnheader">Order</span><span role="columnheader">Method</span>
        <span role="columnheader">Reference</span><span role="columnheader">Received by</span><span role="columnheader" class="ta-r">Amount</span>
      </div>
      <?php foreach ($rows as $t): $void = $t['voided_at'] !== null; ?>
        <div class="tr<?= $void ? ' voided' : '' ?>" role="row" data-href="<?= e(url('app/order.php?id=' . $t['order_id'])) ?>">
          <span role="cell" class="c-date muted"><?= e(fmt_dt($t['created_at'], 'M j, g:i A')) ?></span>
          <span role="cell" class="c-order"><a class="order-no" href="<?= e(url('app/order.php?id=' . $t['order_id'])) ?>"><?= e($t['order_no']) ?></a> <span class="truncate"><?= e($t['customer_name']) ?></span></span>
          <span role="cell" class="c-method"><?= $t['method'] === 'gcash' ? 'GCash' : 'Cash' ?><?= $t['kind'] === 'refund' ? ' refund' : '' ?><?= $void ? ' · voided' : '' ?></span>
          <span role="cell" class="c-ref muted"><?= e($t['reference'] ?? '') ?></span>
          <span role="cell" class="c-by muted"><?= e($t['user_name'] ?? '') ?></span>
          <span role="cell" class="c-amt num ta-r"><b><?= $t['kind'] === 'refund' ? '−' : '' ?><?= e(money($t['amount'])) ?></b></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
