<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$search = input('q');
$page = max(1, (int) input('page', '1'));
$perPage = 40;
$where = '1=1';
$params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $digits = preg_replace('/\D+/', '', $search);
    $where = '(c.name LIKE ?' . (strlen($digits) >= 3 ? ' OR c.phone LIKE ?' : '') . ')';
    $params[] = $like;
    if (strlen($digits) >= 3) $params[] = '%' . $digits . '%';
}
$found = (int) q_val("SELECT COUNT(*) FROM customers c WHERE $where", $params);
$pages = max(1, (int) ceil($found / $perPage));
$page = min($page, $pages);
$customers = q(
    "SELECT c.*, COUNT(o.id) AS orders, MAX(o.created_at) AS last_order,
            SUM(CASE WHEN o.status <> 'Completed' THEN 1 ELSE 0 END) AS open_orders
       FROM customers c LEFT JOIN orders o ON o.customer_id = c.id
      WHERE $where GROUP BY c.id ORDER BY COALESCE(MAX(o.created_at), c.created_at) DESC
      LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
)->fetchAll();

$title = 'Customers';
$active = 'customers';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="page-head">
  <form class="search-pill grow max-30" method="get" role="search">
    <?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name or phone" aria-label="Search customers">
  </form>
  <a class="btn btn-primary" href="<?= e(url('app/customer.php?new=1')) ?>"><?= icon('plus') ?>Add customer</a>
</div>

<section class="panel flush">
  <?php if (!$customers): ?>
    <p class="empty"><?= $search !== '' ? 'No customers match “' . e($search) . '”.' : 'No customers yet. They are added when you create their first order.' ?></p>
  <?php else: ?>
    <div class="table customers-table" role="table" aria-label="Customers">
      <div class="tr th" role="row">
        <span role="columnheader">Name</span><span role="columnheader">Phone</span><span role="columnheader">Address</span>
        <span role="columnheader">Orders</span><span role="columnheader">Last order</span><span role="columnheader">Open</span>
      </div>
      <?php foreach ($customers as $c): ?>
        <div class="tr" role="row" data-href="<?= e(url('app/customer.php?id=' . $c['id'])) ?>">
          <span role="cell" class="c-name"><span class="avatar"><?= e(initials($c['name'])) ?></span>
            <span class="list-main"><a href="<?= e(url('app/customer.php?id=' . $c['id'])) ?>"><b><?= e($c['name']) ?></b></a><span class="muted-sm mobile-only"><?= e(fmt_phone($c['phone'])) ?> · <?= (int) $c['orders'] ?> orders</span></span></span>
          <span role="cell" class="c-phone num"><?= e(fmt_phone($c['phone'])) ?></span>
          <span role="cell" class="c-addr truncate muted"><?= e($c['address'] ?? '') ?></span>
          <span role="cell" class="c-orders num"><?= (int) $c['orders'] ?></span>
          <span role="cell" class="c-last"><?= e($c['last_order'] ? fmt_dt($c['last_order'], 'M j, Y') : '—') ?></span>
          <span role="cell" class="c-open"><?= (int) $c['open_orders'] ? '<span class="chip chip-washing">' . (int) $c['open_orders'] . ' active</span>' : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(url('app/customers.php?' . http_build_query(['q' => $search, 'page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?> · <?= $found ?> customers</span>
    <?php if ($page < $pages): ?><a class="btn btn-outline btn-sm" href="<?= e(url('app/customers.php?' . http_build_query(['q' => $search, 'page' => $page + 1]))) ?>">Next</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
