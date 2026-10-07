<?php
// One booking for staff: decide (confirm / reject / cancel / no-show) and receive the laundry,
// which weighs it, creates the order and optionally takes a payment, all in one form.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');
refresh_bookings();

$b = load_booking((int) input('id'));
if (!$b) {
    http_response_code(404);
    $title = 'Booking not found';
    $message = 'This booking doesn\'t exist.';
    $back = url('app/bookings.php');
    require __DIR__ . '/../includes/layout/error.php';
    exit;
}
$self = 'app/booking.php?id=' . $b['id'];
$services = q('SELECT * FROM services WHERE is_active = 1 OR id = ? ORDER BY sort_order, name', [$b['service_id']])->fetchAll();
$errors = [];
$v = ['service_id' => (int) input('service_id', (string) $b['service_id']), 'weight_kg' => input('weight_kg'), 'notes' => input('notes', (string) $b['notes'])];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    if (in_array($action, ['confirm', 'reject', 'cancel', 'no_show'], true)) {
        $reason = mb_substr(input('reason'), 0, 200);
        $err = change_booking($b, $action, $me['role'], $action === 'cancel' && $reason === '' ? 'Cancelled by the shop' : $reason, $me['id']);
        if ($err && $action === 'reject') {
            $errors['reason'] = $err;
        } else {
            flash($err ?: $b['booking_no'] . ' ' . ['confirm' => 'confirmed.', 'reject' => 'rejected.', 'cancel' => 'cancelled.', 'no_show' => 'marked as no-show.'][$action], $err ? 'warning' : 'success');
            redirect($self);
        }
    } elseif ($action === 'drop_off' && booking_can($b, 'drop_off', $me['role'])) {
        $service = null;
        foreach ($services as $s) if ((int) $s['id'] === $v['service_id']) $service = $s;
        $weight = round((float) $v['weight_kg'], 2);
        if (!$service) $errors['service_id'] = 'Choose a service.';
        if ($weight < 0.1 || $weight > 200) $errors['weight_kg'] = 'Weigh the laundry and enter the weight in kg (0.1 to 200).';
        $due = $service ? round($weight * (float) $service['price_per_kg'], 2) : 0;
        [$payErr, $amount, $method, $ref] = validate_payment($_POST, $due, true);
        $errors += $payErr;
        if (!$errors) {
            try {
                [$orderId, $orderNo] = create_order(['customer_id' => (int) $b['customer_id'], 'service' => $service, 'weight' => $weight,
                    'notes' => mb_substr($v['notes'], 0, 255), 'pay_amount' => $amount, 'pay_method' => $method, 'pay_reference' => $ref,
                    'booking_id' => (int) $b['id']], $me['id']);
            } catch (RuntimeException $ex) {
                flash('This booking was already received by someone else.', 'warning');
                redirect($self);
            }
            log_activity('booking.drop_off', $b['booking_no'] . ' → ' . $orderNo, (int) $b['customer_id']);
            flash('Laundry received. Order ' . $orderNo . ' created. Give the customer this number.');
            redirect('app/order.php?id=' . $orderId);
        }
    }
}
$canDrop = booking_can($b, 'drop_off', $me['role']);

$title = $b['booking_no'];
$active = 'bookings';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<a class="back-link" href="<?= e(url('app/bookings.php')) ?>"><?= icon('back') ?>Bookings</a>

<div class="order-layout">
  <div class="order-main">
    <section class="panel">
      <div class="hero-meta"><span class="muted-strong"><?= e($b['booking_no']) ?> · booked <?= e(fmt_when($b['created_at'])) ?></span><?= booking_chip($b['status']) ?></div>
      <h2 class="order-number booking-when"><?= e(slot_label($b)) ?></h2>
      <dl class="details">
        <div><dt>Customer</dt><dd><a href="<?= e(url('app/customer.php?id=' . $b['customer_id'])) ?>"><?= e($b['customer_name']) ?></a> · <a href="tel:<?= e($b['phone']) ?>"><?= e(fmt_phone($b['phone'])) ?></a></dd></div>
        <div><dt>Service</dt><dd><?= e($b['service_name']) ?> · <?= e(money($b['price_per_kg'])) ?>/kg</dd></div>
        <div><dt>Estimate</dt><dd><?= $b['est_weight_kg'] ? 'About ' . e(kg($b['est_weight_kg'])) : 'Not given' ?></dd></div>
        <?php if ($b['notes']): ?><div><dt>Notes</dt><dd><?= e($b['notes']) ?></dd></div><?php endif; ?>
        <?php if ($b['status_reason']): ?><div><dt>Reason</dt><dd><?= e($b['status_reason']) ?></dd></div><?php endif; ?>
        <?php if ($b['handled_by_name']): ?><div><dt>Handled by</dt><dd><?= e($b['handled_by_name']) ?></dd></div><?php endif; ?>
        <?php if ($b['order_id']): ?><div><dt>Order</dt><dd><a class="order-no" href="<?= e(url('app/order.php?id=' . $b['order_id'])) ?>"><?= e($b['order_no']) ?></a> · <?= status_chip($b['order_status']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if (booking_can($b, 'confirm', $me['role']) || booking_can($b, 'no_show', $me['role'])): ?>
        <div class="hero-actions">
          <?php if (booking_can($b, 'confirm', $me['role'])): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><button class="btn btn-primary" type="submit"><?= icon('check') ?>Confirm booking</button></form>
          <?php endif; ?>
          <?php if (booking_can($b, 'no_show', $me['role'])): ?>
            <form method="post" class="inline-form" data-confirm="Mark this booking as a no-show?"><?= csrf_field() ?><input type="hidden" name="action" value="no_show"><button class="btn btn-outline" type="submit">Mark no-show</button></form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (booking_can($b, 'reject', $me['role'])): ?>
        <form method="post" class="set-status reason-form" novalidate>
          <?= csrf_field() ?>
          <label for="reason">Can't take it?</label>
          <input id="reason" name="reason" maxlength="200" placeholder="Reason the customer will see, e.g. fully booked for dry cleaning" value="<?= e(input('reason')) ?>">
          <button class="btn btn-outline btn-sm danger" type="submit" name="action" value="reject">Reject</button>
          <button class="btn btn-ghost btn-sm" type="submit" name="action" value="cancel" data-confirm-click="Cancel this booking for the customer?">Cancel</button>
          <?= field_error($errors, 'reason') ?>
        </form>
      <?php endif; ?>
    </section>
  </div>

  <aside class="order-side">
    <?php if ($canDrop): ?>
      <section class="panel" id="dropoff">
        <div class="panel-head"><h3>Receive laundry</h3></div>
        <p class="muted-sm">Weigh the laundry, then save. This creates the order (status Received) and its order number.</p>
        <form method="post" class="stack" novalidate data-validate data-new-order-lite>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="drop_off">
          <div class="field">
            <label for="service_id">Service</label>
            <select id="service_id" name="service_id">
              <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" data-price="<?= e($s['price_per_kg']) ?>"<?= (int) $s['id'] === $v['service_id'] ? ' selected' : '' ?>><?= e($s['name']) ?> · <?= e(money($s['price_per_kg'])) ?>/kg</option><?php endforeach; ?>
            </select>
            <?= field_error($errors, 'service_id') ?>
          </div>
          <div class="field">
            <label for="weight_kg">Actual weight (kg)</label>
            <input id="weight_kg" name="weight_kg" type="number" inputmode="decimal" step="0.1" min="0.1" max="200" value="<?= e($v['weight_kg']) ?>" placeholder="<?= $b['est_weight_kg'] ? e((string) (float) $b['est_weight_kg']) : '0.0' ?>" required autofocus>
            <?= field_error($errors, 'weight_kg') ?>
          </div>
          <div class="field">
            <label for="notes">Notes</label>
            <input id="notes" name="notes" maxlength="255" value="<?= e($v['notes']) ?>">
          </div>
          <div class="field">
            <label for="amount">Paid now <span class="optional">Optional</span></label>
            <input id="amount" name="amount" type="number" inputmode="decimal" step="0.01" min="0" value="<?= e(input('amount')) ?>" placeholder="0.00 · pay at pickup">
            <?= field_error($errors, 'amount') ?>
          </div>
          <div class="segmented" role="radiogroup" aria-label="Method">
            <?php $m = input('method', 'cash'); ?>
            <label><input type="radio" name="method" value="cash"<?= $m !== 'gcash' ? ' checked' : '' ?>><span>Cash</span></label>
            <label><input type="radio" name="method" value="gcash"<?= $m === 'gcash' ? ' checked' : '' ?>><span>GCash</span></label>
          </div>
          <div class="field" data-show-when="method=gcash">
            <label for="reference">GCash reference no.</label>
            <input id="reference" name="reference" inputmode="numeric" maxlength="20" value="<?= e(input('reference')) ?>">
            <?= field_error($errors, 'reference') ?>
          </div>
          <button class="btn btn-primary btn-block btn-lg" type="submit">Receive and create order</button>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
