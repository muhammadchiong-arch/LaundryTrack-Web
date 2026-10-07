<?php
// Day view of the drop-off schedule: how full each slot is and who is coming.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');
refresh_bookings();

$date = input('date', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = date('Y-m-d');
$s = schedule();
$closed = closed_reason($date, $s);
$rows = q(
    "SELECT b.id, b.booking_no, b.status, TIME_FORMAT(b.slot_time, '%H:%i') t, c.name AS customer_name, s.name AS service_name
       FROM bookings b JOIN customers c ON c.id = b.customer_id JOIN services s ON s.id = b.service_id
      WHERE b.slot_date = ? AND b.status IN ('Pending','Confirmed','Dropped off','No-show') ORDER BY b.slot_time, b.id",
    [$date]
)->fetchAll();
$bySlot = [];
foreach ($rows as $r) $bySlot[$r['t']][] = $r;

$title = 'Schedule';
$active = 'bookings';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<a class="back-link" href="<?= e(url('app/bookings.php')) ?>"><?= icon('back') ?>Bookings</a>
<div class="page-head">
  <h2 class="display-sm"><?= e(date('l, F j', strtotime($date))) ?></h2>
  <form class="filter-bar" method="get">
    <a class="btn btn-outline btn-sm" href="<?= e(url('app/schedule.php?date=' . date('Y-m-d', strtotime($date . ' -1 day')))) ?>">Previous day</a>
    <input type="date" name="date" value="<?= e($date) ?>" aria-label="Date" onchange="this.form.submit()">
    <a class="btn btn-outline btn-sm" href="<?= e(url('app/schedule.php?date=' . date('Y-m-d', strtotime($date . ' +1 day')))) ?>">Next day</a>
  </form>
</div>
<section class="panel flush">
  <?php if ($closed): ?>
    <p class="empty">The shop is closed this day<?= $closed !== 'Closed' ? ' (' . e($closed) . ')' : '' ?>. Online booking is off.</p>
  <?php else: ?>
    <ul class="list sched">
      <?php foreach (slot_times($s) as $t): $list = $bySlot[$t] ?? []; $held = count(array_filter($list, fn ($r) => in_array($r['status'], BOOKING_HOLDS, true))); ?>
        <li class="sched-row">
          <span class="sched-time"><b><?= e(date('g:i A', strtotime("2000-01-01 $t"))) ?></b><span class="muted-sm"><?= $held ?> of <?= (int) $s['capacity'] ?> booked</span></span>
          <span class="sched-meter" aria-hidden="true"><?php for ($i = 0; $i < $s['capacity']; $i++): ?><i class="<?= $i < $held ? 'on' : '' ?>"></i><?php endfor; ?></span>
          <span class="sched-list">
            <?php foreach ($list as $r): ?>
              <a class="sched-item" href="<?= e(url('app/booking.php?id=' . $r['id'])) ?>"><b><?= e($r['customer_name']) ?></b> <span class="muted-sm"><?= e($r['service_name']) ?></span> <?= booking_chip($r['status']) ?></a>
            <?php endforeach; ?>
            <?php if (!$list): ?><span class="muted-sm">Open</span><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
