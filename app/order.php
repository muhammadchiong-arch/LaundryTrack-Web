<?php
// One order: move it along, take payment, and handle corrections.
// All status and money rules live in includes/orders.php.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$id = (int) input('id');
$order = load_order($id);
if (!$order) {
    http_response_code(404);
    $title = 'Order not found';
    $message = 'This order doesn\'t exist. It may have been typed wrong.';
    $back = url('app/orders.php');
    require __DIR__ . '/../includes/layout/error.php';
    exit;
}
$self = 'app/order.php?id=' . $id;
$balance = order_balance($order);
$open = order_is_open($order);
$errors = [];
$fail = function (string $msg) use ($self) { flash($msg, 'warning'); redirect($self); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    if (input('from') !== '' && input('from') !== $order['status']) {
        $fail('Someone else updated this order first. It now shows the current status.');
    }
    if ($action === 'advance') {
        handle_advance_post($self);
    } elseif ($action === 'pay' || $action === 'pay_complete') {
        // Taking the last payment and marking the pickup is one step at the counter.
        if (!$open || $balance <= 0) $fail('There is nothing left to pay on this order.');
        [$errors, $amount, $method, $ref] = validate_payment($_POST, $balance);
        if ($action === 'pay_complete' && !$errors && abs($amount - $balance) > 0.004) {
            $errors['amount'] = 'To complete, collect the full balance of ' . money($balance) . '.';
        }
        if (!$errors) {
            record_payment($id, $amount, $method, $ref, $me['id']);
            if ($action === 'pay_complete') {
                $err = transition_order(load_order($id), 'Completed', $me);
                flash($err ? money($amount) . ' recorded. ' . $err : money($amount) . ' collected. ' . $order['order_no'] . ' is completed.', $err ? 'warning' : 'success');
            } else {
                flash(money($amount) . ' recorded' . ($amount + 0.004 >= $balance ? '. The order is fully paid.' : '.'));
            }
            redirect($self);
        }
    } elseif ($action === 'step_back') {
        $prev = STATUSES[max(0, status_index($order['status']) - 1)];
        if ($err = transition_order($order, $prev, $me, 'Correction')) {
            $fail($err);
        }
        flash($order['order_no'] . ' moved back to ' . $prev . '.');
        redirect($self);
    } elseif ($action === 'cancel') {
        $reason = mb_substr(input('reason'), 0, 200);
        if ($reason === '') {
            $errors['cancel_reason'] = 'Enter why the order is cancelled.';
        } elseif ($err = cancel_order($order, $reason, input('refund_method'), mb_substr(input('refund_reference'), 0, 64), $me)) {
            $fail($err);
        } else {
            $paid = (float) $order['paid'];
            flash($order['order_no'] . ' cancelled' . ($paid > 0.004 ? ' and ' . money($paid) . ' refunded.' : '.'));
            redirect($self);
        }
    } elseif ($action === 'void' && is_admin()) {
        $tx = q_one('SELECT * FROM transactions WHERE id = ? AND order_id = ? AND voided_at IS NULL', [(int) input('tx_id'), $id]);
        $reason = mb_substr(input('reason'), 0, 200);
        if (!$tx) $fail('That payment was already voided.');
        if (!$open) $fail('Payments on ' . strtolower($order['status']) . ' orders are locked.');
        if ($reason === '') $fail('Enter why the payment is being voided.');
        q('UPDATE transactions SET voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ?', [$me['id'], $reason, $tx['id']]);
        log_activity('payment.void', $order['order_no'] . ': ' . money($tx['amount']) . ' ' . $tx['method'] . ' voided (' . $reason . ')', (int) $order['customer_id']);
        flash('Payment voided. It stays on record but no longer counts.');
        redirect($self);
    } elseif ($action === 'edit') {
        if (!$open) $fail('A ' . strtolower($order['status']) . ' order can no longer be edited.');
        $service = q_one('SELECT * FROM services WHERE id = ?', [(int) input('service_id')]);
        $weight = round((float) input('weight_kg'), 2);
        $notes = mb_substr(input('notes'), 0, 255);
        if (!$service) $errors['service_id'] = 'Choose a service.';
        if ($weight < 0.1 || $weight > 200) $errors['weight_kg'] = 'Enter a weight between 0.1 and 200 kg.';
        if (!$errors) {
            // Keep the original price unless the service changed.
            $price = (int) $service['id'] === (int) $order['service_id'] ? (float) $order['price_per_kg'] : (float) $service['price_per_kg'];
            $due = round($weight * $price, 2);
            if ($due + 0.004 < (float) $order['paid']) {
                $errors['weight_kg'] = 'The new total would be less than what was already paid (' . money($order['paid']) . ').';
            } else {
                q('UPDATE orders SET service_id = ?, weight_kg = ?, price_per_kg = ?, amount_due = ?, notes = ? WHERE id = ?',
                  [$service['id'], $weight, $price, $due, $notes ?: null, $id]);
                if (abs($due - (float) $order['amount_due']) > 0.004) {
                    log_activity('order.edit', $order['order_no'] . ': total ' . money($order['amount_due']) . ' → ' . money($due), (int) $order['customer_id']);
                }
                flash('Order details saved.');
                redirect($self);
            }
        }
    }
}

$stamps = [];
$history = q(
    'SELECT h.status, h.note, h.changed_at, u.name AS user_name FROM order_status_history h
       LEFT JOIN users u ON u.id = h.changed_by WHERE h.order_id = ? ORDER BY h.changed_at, h.id',
    [$id]
)->fetchAll();
foreach ($history as $h) {
    $stamps[$h['status']] = $h['changed_at'];
}
$payments = q(
    'SELECT t.*, u.name AS user_name, v.name AS voided_by_name FROM transactions t
       LEFT JOIN users u ON u.id = t.received_by LEFT JOIN users v ON v.id = t.voided_by
      WHERE t.order_id = ? ORDER BY t.created_at, t.id',
    [$id]
)->fetchAll();
$services = q('SELECT * FROM services WHERE is_active = 1 OR id = ? ORDER BY sort_order, name', [$order['service_id']])->fetchAll();
[$payKey, $payLabel] = payment_state((float) $order['amount_due'], (float) $order['paid']);
$next = $open ? next_status($order['status']) : null;
$finalStep = $next === 'Completed';
$editOpen = $errors && input('action') === 'edit';
$cancelOpen = isset($errors['cancel_reason']);
$smsText = 'Hi ' . explode(' ', trim($order['customer_name']))[0] . ', your laundry ' . $order['order_no'] . ' is ready for pickup at ' . setting('shop_name', 'the shop') . '.'
         . ($balance > 0 ? ' Balance: ' . money($balance) . '.' : '');

$title = $order['order_no'];
$active = 'orders';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<a class="back-link" href="<?= e(url('app/orders.php')) ?>"><?= icon('back') ?>Orders</a>

<div class="order-layout">
  <div class="order-main">
    <section class="hero-card single">
      <div class="hero-body">
        <div class="hero-meta">
          <span class="muted-strong"><?= e($order['service_name']) ?> · <?= e(kg($order['weight_kg'])) ?> · <?= e(fmt_dt($order['created_at'])) ?><?= $order['booking_no'] ? ' · booked as ' . e($order['booking_no']) : ' · walk-in' ?></span>
          <?= status_chip($order['status']) ?>
        </div>
        <h2 class="order-number"><?= e($order['order_no']) ?></h2>
        <?php require __DIR__ . '/../includes/partials/progress.php'; ?>
        <div class="hero-actions">
          <?php if ($order['status'] === ORDER_CANCELLED): ?>
            <span class="done-note cancelled"><?= icon('x') ?>Cancelled<?= $order['cancel_reason'] ? ': ' . e($order['cancel_reason']) : '' ?></span>
          <?php elseif (!$next): ?>
            <span class="done-note"><?= icon('check') ?>Completed <?= e(fmt_when($order['completed_at'])) ?></span>
          <?php elseif ($finalStep && $balance > 0): ?>
            <a class="btn btn-primary btn-lg" href="#pay">Collect <?= e(money($balance)) ?> and complete<?= icon('arrow') ?></a>
          <?php else: ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="advance">
              <input type="hidden" name="order_id" value="<?= $id ?>">
              <input type="hidden" name="from" value="<?= e($order['status']) ?>">
              <button class="btn btn-primary btn-lg" type="submit"><?= e($finalStep ? 'Mark completed' : 'Move to ' . $next) ?><?= icon('arrow') ?></button>
            </form>
          <?php endif; ?>
          <?php if ($order['status'] === 'Ready for Pickup'): ?>
            <a class="btn btn-outline" href="sms:<?= e($order['phone']) ?>?body=<?= e(rawurlencode($smsText)) ?>"><?= icon('phone') ?>Text customer</a>
          <?php endif; ?>
          <?php if ($order['status'] !== ORDER_CANCELLED): ?>
            <button class="btn btn-ghost" type="button" data-print><?= icon('printer') ?>Print claim slip</button>
          <?php endif; ?>
        </div>
        <?php if ($open || (is_admin() && $order['status'] === 'Completed')): ?>
          <div class="order-tools">
            <?php if (is_admin() && status_index($order['status']) > 0 && $order['status'] !== ORDER_CANCELLED): ?>
              <form method="post" class="inline-form" data-confirm="Move <?= e($order['order_no']) ?> back to <?= e(STATUSES[status_index($order['status']) - 1]) ?>? It will be recorded as a correction.">
                <?= csrf_field() ?><input type="hidden" name="action" value="step_back"><input type="hidden" name="from" value="<?= e($order['status']) ?>">
                <button class="link-btn" type="submit">Undo last step</button>
              </form>
            <?php endif; ?>
            <?php if ($open): ?>
              <button class="link-btn danger" type="button" data-toggle="cancel-form" aria-expanded="<?= $cancelOpen ? 'true' : 'false' ?>">Cancel order</button>
            <?php endif; ?>
          </div>
          <?php if ($open): ?>
            <form method="post" id="cancel-form" class="stack cancel-box" novalidate<?= $cancelOpen ? '' : ' hidden' ?> data-confirm="Cancel <?= e($order['order_no']) ?>? This can't be undone.">
              <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="from" value="<?= e($order['status']) ?>">
              <div class="field">
                <label for="cancel_reason">Reason</label>
                <input id="cancel_reason" name="reason" maxlength="200" placeholder="e.g. Customer took the laundry back" required>
                <?= field_error($errors, 'cancel_reason') ?>
              </div>
              <?php if ((float) $order['paid'] > 0.004): ?>
                <p class="muted-sm">The customer paid <?= e(money($order['paid'])) ?>. It will be recorded as refunded.</p>
                <div class="segmented" role="radiogroup" aria-label="Refund method">
                  <label><input type="radio" name="refund_method" value="cash" checked><span>Refund cash</span></label>
                  <label><input type="radio" name="refund_method" value="gcash"><span>Refund GCash</span></label>
                </div>
                <div class="field" data-show-when="refund_method=gcash"><label for="refund_reference">GCash reference no.</label><input id="refund_reference" name="refund_reference" inputmode="numeric" maxlength="20"></div>
              <?php endif; ?>
              <div class="form-actions"><button class="btn btn-outline danger" type="submit">Cancel order<?= (float) $order['paid'] > 0.004 ? ' and refund' : '' ?></button></div>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h3>Details</h3>
        <?php if ($open): ?><button class="link-btn" type="button" data-toggle="edit-form" aria-expanded="<?= $editOpen ? 'true' : 'false' ?>">Edit</button><?php endif; ?>
      </div>
      <dl class="details">
        <div><dt>Customer</dt><dd><a href="<?= e(url('app/customer.php?id=' . $order['customer_id'])) ?>"><?= e($order['customer_name']) ?></a> · <a href="tel:<?= e($order['phone']) ?>"><?= e(fmt_phone($order['phone'])) ?></a></dd></div>
        <div><dt>Service</dt><dd><?= e($order['service_name']) ?> · <?= e(money($order['price_per_kg'])) ?>/kg</dd></div>
        <div><dt>Weight</dt><dd><?= e(kg($order['weight_kg'])) ?></dd></div>
        <div><dt>Total</dt><dd class="num"><?= e(money($order['amount_due'])) ?></dd></div>
        <?php if ($order['notes']): ?><div><dt>Notes</dt><dd><?= e($order['notes']) ?></dd></div><?php endif; ?>
        <div><dt>Created</dt><dd><?= e(fmt_dt($order['created_at'], 'M j, Y g:i A')) ?><?= $order['created_by_name'] ? ' by ' . e($order['created_by_name']) : '' ?></dd></div>
      </dl>
      <?php if ($open): ?>
        <form method="post" id="edit-form" class="stack edit-form" novalidate data-validate<?= $editOpen ? '' : ' hidden' ?>>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="edit">
          <div class="field-row">
            <div class="field">
              <label for="service_id">Service</label>
              <select id="service_id" name="service_id">
                <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (int) $s['id'] === (int) $order['service_id'] ? ' selected' : '' ?>><?= e($s['name']) ?> · <?= e(money($s['price_per_kg'])) ?>/kg</option><?php endforeach; ?>
              </select>
              <?= field_error($errors, 'service_id') ?>
            </div>
            <div class="field">
              <label for="weight_kg">Weight (kg)</label>
              <input id="weight_kg" name="weight_kg" type="number" inputmode="decimal" step="0.1" min="0.1" max="200" value="<?= e(input('weight_kg', (string) (float) $order['weight_kg'])) ?>" required>
              <?= field_error($errors, 'weight_kg') ?>
            </div>
          </div>
          <div class="field">
            <label for="notes">Notes</label>
            <input id="notes" name="notes" maxlength="255" value="<?= e(input('notes', (string) $order['notes'])) ?>" placeholder="Stains, delicate items, fragrance…">
          </div>
          <div class="form-actions"><button class="btn btn-primary" type="submit">Save details</button></div>
        </form>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h3>History</h3></div>
      <ol class="timeline">
        <?php foreach (array_reverse($history) as $h): ?>
          <li><?= status_chip($h['status']) ?><span class="muted"><?= e(fmt_dt($h['changed_at'], 'M j, Y g:i A')) ?><?= $h['user_name'] ? ' · ' . e($h['user_name']) : '' ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <aside class="order-side">
    <section class="panel" id="pay">
      <div class="panel-head"><h3>Payment</h3><span class="pay-tag pay-<?= e($order['status'] === ORDER_CANCELLED ? 'refunded' : $payKey) ?>"><?= e($order['status'] === ORDER_CANCELLED ? 'Cancelled' : $payLabel) ?></span></div>
      <dl class="sum-rows">
        <div><dt>Total</dt><dd><?= e(money($order['amount_due'])) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e(money($order['paid'])) ?></dd></div>
        <div class="sum-strong"><dt>Balance</dt><dd><?= e(money($balance)) ?></dd></div>
      </dl>
      <?php if ($payments): ?>
        <ul class="pay-list">
          <?php foreach ($payments as $p): $void = $p['voided_at'] !== null; ?>
            <li class="<?= $void ? 'voided' : '' ?>">
              <span class="list-main"><b><?= $p['kind'] === 'refund' ? 'Refund −' : '' ?><?= e(money($p['amount'])) ?> · <?= $p['method'] === 'gcash' ? 'GCash' : 'Cash' ?><?= $void ? ' · Voided' : '' ?></b>
                <span class="muted-sm"><?= e(fmt_when($p['created_at'])) ?><?= $p['reference'] ? ' · Ref ' . e($p['reference']) : '' ?><?= $p['user_name'] ? ' · ' . e($p['user_name']) : '' ?><?= $void ? ' · voided by ' . e($p['voided_by_name'] ?? 'admin') . ': ' . e($p['void_reason']) : '' ?></span></span>
              <?php if (is_admin() && !$void && $open && $p['kind'] === 'payment'): ?>
                <button class="link-btn danger" type="button" data-toggle="void-<?= (int) $p['id'] ?>" aria-expanded="false">Void</button>
                <form method="post" id="void-<?= (int) $p['id'] ?>" class="void-form" hidden><?= csrf_field() ?>
                  <input type="hidden" name="action" value="void"><input type="hidden" name="tx_id" value="<?= (int) $p['id'] ?>">
                  <input name="reason" maxlength="200" placeholder="Why? e.g. typed the wrong amount" aria-label="Reason for voiding" required>
                  <button class="btn btn-sm btn-outline danger" type="submit">Void payment</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($open && $balance > 0): ?>
        <form method="post" class="stack pay-form" novalidate data-validate>
          <?= csrf_field() ?>
          <input type="hidden" name="from" value="<?= e($order['status']) ?>">
          <div class="field">
            <label for="amount">Amount received</label>
            <input id="amount" name="amount" type="number" inputmode="decimal" step="0.01" min="0.01" max="<?= e($balance) ?>" value="<?= e(input('amount', number_format($balance, 2, '.', ''))) ?>" required>
            <?= field_error($errors, 'amount') ?>
          </div>
          <div class="segmented" role="radiogroup" aria-label="Method">
            <?php $m = input('method', 'cash'); ?>
            <label><input type="radio" name="method" value="cash"<?= $m !== 'gcash' ? ' checked' : '' ?>><span>Cash</span></label>
            <label><input type="radio" name="method" value="gcash"<?= $m === 'gcash' ? ' checked' : '' ?>><span>GCash</span></label>
          </div>
          <?= field_error($errors, 'method') ?>
          <div class="field" data-show-when="method=gcash">
            <label for="reference">GCash reference no.</label>
            <input id="reference" name="reference" maxlength="20" value="<?= e(input('reference')) ?>" inputmode="numeric">
            <?= field_error($errors, 'reference') ?>
          </div>
          <?php if ($finalStep): ?>
            <button class="btn btn-primary btn-block" type="submit" name="action" value="pay_complete">Collect and mark completed</button>
            <button class="btn btn-ghost btn-block" type="submit" name="action" value="pay">Record payment only</button>
          <?php else: ?>
            <button class="btn btn-primary btn-block" type="submit" name="action" value="pay">Record payment</button>
          <?php endif; ?>
        </form>
      <?php elseif (!$open && $order['status'] === 'Completed'): ?>
        <p class="muted-sm">Payments are locked because this order is completed.</p>
      <?php endif; ?>
    </section>
  </aside>
</div>

<div class="print-slip" aria-hidden="true">
  <b class="slip-shop"><?= e(setting('shop_name', 'LaundryTrack')) ?></b>
  <?php if (setting('shop_address') || setting('shop_phone')): ?><span><?= e(trim(setting('shop_address') . ' · ' . setting('shop_phone'), ' ·')) ?></span><?php endif; ?>
  <span class="slip-label">ORDER NUMBER</span>
  <b class="slip-no"><?= e($order['order_no']) ?></b>
  <span><?= e($order['customer_name']) ?></span>
  <span><?= e($order['service_name']) ?> · <?= e(kg($order['weight_kg'])) ?> · <?= e(money($order['amount_due'])) ?></span>
  <span>Paid <?= e(money($order['paid'])) ?> · Balance <?= e(money($balance)) ?></span>
  <span>Dropped off <?= e(fmt_dt($order['created_at'], 'M j, Y g:i A')) ?></span>
  <span class="slip-note">Keep this slip. Track your order with this number and the last 4 digits of your phone.</span>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
