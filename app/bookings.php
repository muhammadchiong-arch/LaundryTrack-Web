<?php
// Staff booking queue: what needs a decision, today's drop-offs, upcoming, and past.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');
refresh_bookings();

$tab = input('tab', 'today');
$tabs = [
    'review'   => ['Needs review', "b.status = 'Pending'", 'b.slot_date, b.slot_time'],
    'today'    => ['Today', "b.slot_date = CURDATE() AND b.status IN ('Pending','Confirmed','Dropped off')", 'b.slot_time'],
    'upcoming' => ['Upcoming', "b.slot_date > CURDATE() AND b.status IN ('Pending','Confirmed')", 'b.slot_date, b.slot_time'],
    'past'     => ['Closed', "b.status IN ('Rejected','Cancelled','No-show','Expired','Dropped off') AND (b.slot_date < CURDATE() OR b.status <> 'Dropped off')", 'b.slot_date DESC, b.slot_time DESC'],
];
if (!isset($tabs[$tab])) $tab = 'today';

// Quick actions from the list (confirm, no-show). Reject and drop-off happen on the booking page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = load_booking((int) input('booking_id'));
    $action = input('action');
    if ($b && in_array($action, ['confirm', 'no_show'], true)) {
        $err = change_booking($b, $action, $me['role'], '', $me['id']);
        flash($err ?: $b['booking_no'] . ($action === 'confirm' ? ' confirmed.' : ' marked as no-show.'), $err ? 'warning' : 'success');
    }
    redirect('app/bookings.php?tab=' . $tab);
}

$counts = [];
foreach ($tabs as $k => [, $where]) {
    $counts[$k] = (int) q_val("SELECT COUNT(*) FROM bookings b WHERE $where");
}
[$tabLabel, $where, $orderBy] = $tabs[$tab];
$rows = q(
    "SELECT b.*, c.name AS customer_name, c.phone, s.name AS service_name, o.id AS order_id, o.order_no
       FROM bookings b JOIN customers c ON c.id = b.customer_id JOIN services s ON s.id = b.service_id
       LEFT JOIN orders o ON o.booking_id = b.id
      WHERE $where ORDER BY $orderBy LIMIT 200"
)->fetchAll();
$s = schedule();

$title = 'Bookings';
$active = 'bookings';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="page-head">
  <p class="lead">Online drop-off bookings. Confirm new ones, then turn each into an order when the laundry arrives.</p>
  <a class="btn btn-outline" href="<?= e(url('app/schedule.php')) ?>"><?= icon('calendar') ?>Today's schedule</a>
</div>

<nav class="pills" aria-label="Booking lists">
  <?php foreach ($tabs as $k => [$label]): ?>
    <a class="pill<?= $tab === $k ? ' on' : '' ?>" href="<?= e(url('app/bookings.php?tab=' . $k)) ?>"<?= $tab === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?><span class="pill-n"><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
</nav>

<section class="panel flush">
  <?php if (!$rows): ?>
    <p class="empty"><?= ['review' => 'Nothing to review. New online bookings will show here.', 'today' => 'No drop-offs booked for today.', 'upcoming' => 'No upcoming bookings yet.', 'past' => 'No closed bookings yet.'][$tab] ?></p>
  <?php else: ?>
    <div class="table bookings-table" role="table" aria-label="<?= e($tabLabel) ?>">
      <div class="tr th" role="row">
        <span role="columnheader">Drop-off</span><span role="columnheader">Customer</span><span role="columnheader">Service</span>
        <span role="columnheader">Status</span><span role="columnheader" class="ta-r">Action</span>
      </div>
      <?php foreach ($rows as $b): ?>
        <div class="tr" role="row" data-href="<?= e(url('app/booking.php?id=' . $b['id'])) ?>">
          <span role="cell" class="c-when"><b><?= e(slot_label($b)) ?></b><a class="order-no muted-sm" href="<?= e(url('app/booking.php?id=' . $b['id'])) ?>"><?= e($b['booking_no']) ?></a></span>
          <span role="cell" class="c-cust"><b class="truncate"><?= e($b['customer_name']) ?></b><span class="muted-sm"><?= e(fmt_phone($b['phone'])) ?></span></span>
          <span role="cell" class="c-svc"><?= e($b['service_name']) ?><?= $b['est_weight_kg'] ? '<span class="muted-sm">about ' . e(kg($b['est_weight_kg'])) . '</span>' : '' ?></span>
          <span role="cell" class="c-status"><?= booking_chip($b['status']) ?></span>
          <span role="cell" class="c-act">
            <?php if (booking_can($b, 'confirm', $me['role'])): ?>
              <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-soft" type="submit">Confirm</button></form>
            <?php elseif (booking_can($b, 'no_show', $me['role'])): ?>
              <form method="post" class="inline-form" data-confirm="Mark <?= e($b['booking_no']) ?> as a no-show?"><?= csrf_field() ?><input type="hidden" name="action" value="no_show"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-outline" type="submit">No-show</button></form>
            <?php endif; ?>
            <?php if (booking_can($b, 'drop_off', $me['role'])): ?>
              <a class="btn btn-sm btn-primary" href="<?= e(url('app/booking.php?id=' . $b['id'] . '#dropoff')) ?>">Receive laundry</a>
            <?php elseif ($b['order_id']): ?>
              <a class="btn btn-sm btn-outline" href="<?= e(url('app/order.php?id=' . $b['order_id'])) ?>"><?= e($b['order_no']) ?></a>
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<p class="muted-sm">Each <?= (int) $s['minutes'] ?>-minute slot takes up to <?= (int) $s['capacity'] ?> bookings. Pending bookings not confirmed by the end of their day expire; confirmed ones that never arrive become no-shows. Admins can change this in Settings.</p>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
