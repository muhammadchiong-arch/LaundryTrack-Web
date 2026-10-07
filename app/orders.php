<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$status = input('status', 'active');
$search = input('q');
$from = input('from');
$to = input('to');
$pay = input('pay');
$page = max(1, (int) input('page', '1'));
$perPage = 30;

$keep = array_filter(['status' => $status, 'q' => $search, 'from' => $from, 'to' => $to, 'pay' => $pay]);
handle_advance_post('app/orders.php?' . http_build_query($keep + ['page' => $page]));

// Filters other than status (the status pills show counts for these).
$where = ['1=1'];
$params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $digits = preg_replace('/\D+/', '', $search);
    $parts = ['o.order_no LIKE ?', 'c.name LIKE ?'];
    array_push($params, $like, $like);
    if (strlen($digits) >= 3) {
        $parts[] = 'c.phone LIKE ?';
        $params[] = '%' . $digits . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'o.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'o.created_at < ? + INTERVAL 1 DAY';
    $params[] = $to . ' 00:00:00';
}
if ($pay === 'unpaid') {
    $where[] = 'o.amount_due > COALESCE(p.paid, 0) + 0.004';
}
$from_sql = 'FROM orders o JOIN customers c ON c.id = o.customer_id JOIN services s ON s.id = o.service_id
             LEFT JOIN (SELECT order_id, SUM(amount) paid FROM transactions GROUP BY order_id) p ON p.order_id = o.id';
$baseWhere = implode(' AND ', $where);

$counts = array_fill_keys(STATUSES, 0);
foreach (q("SELECT o.status, COUNT(*) n $from_sql WHERE $baseWhere GROUP BY o.status", $params) as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$total = array_sum($counts);
$filters = ['active' => ['Active', $total - $counts['Completed']]];
foreach (STATUSES as $s) {
    $filters[$s] = [$s, $counts[$s]];
}
$filters['all'] = ['All', $total];

if (!isset($filters[$status])) {
    $status = 'active';
}
if ($status === 'active') {
    $where[] = "o.status <> 'Completed'";
} elseif ($status !== 'all') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);
$found = (int) q_val("SELECT COUNT(*) $from_sql WHERE $whereSql", $params);
$pages = max(1, (int) ceil($found / $perPage));
$page = min($page, $pages);
$orders = q(
    "SELECT o.id, o.order_no, o.status, o.weight_kg, o.amount_due, o.created_at, o.updated_at,
            c.name AS customer_name, c.phone, s.name AS service_name, COALESCE(p.paid, 0) AS paid
     $from_sql WHERE $whereSql
     ORDER BY " . ($status === 'Completed' || $status === 'all' ? 'o.created_at DESC' : 'o.created_at ASC') . '
     LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
)->fetchAll();

function orders_link(array $keep, array $change): string
{
    return url('app/orders.php?' . http_build_query(array_filter(array_merge($keep, $change), fn ($v) => $v !== '' && $v !== null)));
}

$title = 'Orders';
$active = 'orders';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="page-head">
  <p class="lead">Every order from drop-off to pickup. Tap an order for details and payments.</p>
  <a class="btn btn-primary" href="<?= e(url('app/order-new.php')) ?>"><?= icon('plus') ?>New order</a>
</div>

<nav class="pills" aria-label="Filter by status">
  <?php foreach ($filters as $key => [$label, $n]): ?>
    <a class="pill<?= $status === $key ? ' on' : '' ?>" href="<?= e(orders_link($keep, ['status' => $key, 'page' => null])) ?>"<?= $status === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?><span class="pill-n"><?= $n ?></span></a>
  <?php endforeach; ?>
</nav>

<form class="filter-bar" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <label class="search-pill grow"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Order no., customer name or phone" aria-label="Search orders"></label>
  <label class="mini-field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="mini-field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <label class="mini-field"><span>Payment</span>
    <select name="pay"><option value="">Any</option><option value="unpaid"<?= $pay === 'unpaid' ? ' selected' : '' ?>>Not fully paid</option></select>
  </label>
  <button class="btn btn-outline" type="submit">Apply</button>
  <?php if ($search !== '' || $from || $to || $pay): ?><a class="btn btn-ghost" href="<?= e(orders_link([], ['status' => $status])) ?>">Clear</a><?php endif; ?>
</form>

<section class="panel flush">
  <?php if (!$orders): ?>
    <p class="empty"><?= $search !== '' ? 'No orders match “' . e($search) . '”.' : 'No orders here yet.' ?></p>
  <?php else: ?>
    <div class="table orders-table" role="table" aria-label="Orders">
      <div class="tr th" role="row">
        <span role="columnheader">Order</span><span role="columnheader">Customer</span><span role="columnheader">Service</span>
        <span role="columnheader">Weight</span><span role="columnheader">Total</span><span role="columnheader">Payment</span>
        <span role="columnheader">Status</span><span role="columnheader" class="ta-r">Action</span>
      </div>
      <?php foreach ($orders as $o): [$pk, $pl] = payment_state((float) $o['amount_due'], (float) $o['paid']); ?>
        <div class="tr" role="row" data-href="<?= e(url('app/order.php?id=' . $o['id'])) ?>">
          <span role="cell" class="c-no"><a class="order-no" href="<?= e(url('app/order.php?id=' . $o['id'])) ?>"><?= e($o['order_no']) ?></a><span class="muted-sm"><?= e(fmt_when($o['created_at'])) ?></span></span>
          <span role="cell" class="c-cust"><b class="truncate"><?= e($o['customer_name']) ?></b><span class="muted-sm"><?= e(fmt_phone($o['phone'])) ?></span></span>
          <span role="cell" class="c-svc" data-kg="<?= e(kg($o['weight_kg'])) ?>"><?= e($o['service_name']) ?></span>
          <span role="cell" class="c-kg num"><?= e(kg($o['weight_kg'])) ?></span>
          <span role="cell" class="c-total num"><?= e(money($o['amount_due'])) ?></span>
          <span role="cell" class="c-pay pay-<?= e($pk) ?>"><?= e($pl) ?></span>
          <span role="cell" class="c-status"><?= status_chip($o['status']) ?></span>
          <span role="cell" class="c-act"><?= advance_button($o) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(orders_link($keep, ['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?> · <?= $found ?> orders</span>
    <?php if ($page < $pages): ?><a class="btn btn-outline btn-sm" href="<?= e(orders_link($keep, ['page' => $page + 1])) ?>">Next</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
