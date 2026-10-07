<?php
// Everything the customer has with the shop: upcoming drop-offs, laundry in progress, and history.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_customer();
refresh_bookings();

$bookings = q(
    "SELECT b.*, s.name AS service_name, o.order_no, o.status AS order_status
       FROM bookings b JOIN services s ON s.id = b.service_id LEFT JOIN orders o ON o.booking_id = b.id
      WHERE b.customer_id = ? ORDER BY b.slot_date DESC, b.slot_time DESC LIMIT 100",
    [$me['id']]
)->fetchAll();
// Orders made at the counter without a booking also belong to this customer.
$walkins = q(
    'SELECT o.id, o.order_no, o.status, o.created_at, o.amount_due, s.name AS service_name, ' . sql_paid() . ' AS paid
       FROM orders o JOIN services s ON s.id = o.service_id WHERE o.customer_id = ? AND o.booking_id IS NULL ORDER BY o.created_at DESC LIMIT 50',
    [$me['id']]
)->fetchAll();
$upcoming = array_filter($bookings, fn ($b) => in_array($b['status'], BOOKING_HOLDS, true));
$past = array_filter($bookings, fn ($b) => !in_array($b['status'], BOOKING_HOLDS, true));

$title = 'My bookings';
$active = 'bookings';
require __DIR__ . '/../includes/layout/app_top.php';

function booking_row(array $b): string
{
    $sub = $b['status'] === 'Dropped off' && $b['order_no']
        ? 'Order ' . e($b['order_no']) . ' · ' . e($b['order_status'])
        : e($b['service_name']);
    return '<li><a class="list-row" href="' . e(url('my/booking.php?id=' . $b['id'])) . '">'
        . '<span class="list-main"><b>' . e(slot_label($b)) . '</b><span>' . e($b['booking_no']) . ' · ' . $sub . '</span></span>'
        . ($b['status'] === 'Dropped off' && $b['order_status'] ? status_chip($b['order_status']) : booking_chip($b['status'])) . '</a></li>';
}
?>
<div class="page-head">
  <p class="lead">Your drop-off times and laundry, newest first.</p>
  <a class="btn btn-primary" href="<?= e(url('my/book.php')) ?>"><?= icon('plus') ?>Book laundry</a>
</div>

<section class="panel">
  <div class="panel-head"><h3>Upcoming drop-offs</h3></div>
  <?php if (!$upcoming): ?>
    <p class="empty">No upcoming drop-offs. <a href="<?= e(url('my/book.php')) ?>">Book a time</a> that suits you.</p>
  <?php else: ?>
    <ul class="list"><?php foreach ($upcoming as $b) echo booking_row($b); ?></ul>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head"><h3>History</h3></div>
  <?php if (!$past && !$walkins): ?>
    <p class="empty">Your past bookings and orders will show here.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($past as $b) echo booking_row($b); ?>
      <?php foreach ($walkins as $o): ?>
        <li><div class="list-row">
          <span class="list-main"><b><?= e($o['order_no']) ?> · Walk-in</b><span><?= e($o['service_name']) ?> · <?= e(fmt_dt($o['created_at'], 'M j, Y')) ?> · <?= e(money($o['amount_due'])) ?></span></span>
          <?= status_chip($o['status']) ?>
        </div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
