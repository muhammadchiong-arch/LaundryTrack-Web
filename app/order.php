<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$id = (int) input('id');
$load = fn () => q_one(
    'SELECT o.*, c.name AS customer_name, c.phone, c.address, s.name AS service_name, u.name AS created_by_name,
            (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.order_id = o.id) AS paid
       FROM orders o JOIN customers c ON c.id = o.customer_id JOIN services s ON s.id = o.service_id
       LEFT JOIN users u ON u.id = o.created_by WHERE o.id = ?',
    [$id]
);
$order = $load();
if (!$order) {
    http_response_code(404);
    $title = 'Order not found';
    $message = 'This order doesn\'t exist. It may have been typed wrong.';
    $back = url('app/orders.php');
    require __DIR__ . '/../includes/layout/error.php';
    exit;
}
$self = 'app/order.php?id=' . $id;
$balance = round(max(0, (float) $order['amount_due'] - (float) $order['paid']), 2);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    if ($action === 'advance') {
        handle_advance_post($self);
    } elseif ($action === 'set_status' && is_admin()) {
        $to = input('to');
        if (!in_array($to, STATUSES, true) || $to === $order['status']) {
            flash('Pick a different status.', 'warning');
        } elseif (change_status($id, input('from'), $to, $me['id'])) {
            flash($order['order_no'] . ' set to ' . $to . '.');
        } else {
            flash('Someone else updated this order first. Check its status and try again.', 'warning');
        }
        redirect($self);
    } elseif ($action === 'pay') {
        $amount = round((float) input('amount'), 2);
        $method = input('method');
        $ref = mb_substr(input('reference'), 0, 64);
        if ($amount <= 0) {
            $errors['amount'] = 'Enter an amount above zero.';
        } elseif ($amount > $balance + 0.004) {
            $errors['amount'] = 'That is more than the balance of ' . money($balance) . '.';
        }
        if (!in_array($method, ['cash', 'gcash'], true)) {
            $errors['method'] = 'Choose cash or GCash.';
        } elseif ($method === 'gcash' && $ref === '') {
            $errors['reference'] = 'Enter the GCash reference number.';
        }
        if (!$errors) {
            q('INSERT INTO transactions (order_id, amount, method, reference, received_by) VALUES (?, ?, ?, ?, ?)',
              [$id, $amount, $method, $ref ?: null, $me['id']]);
            flash(money($amount) . ' recorded' . ($amount + 0.004 >= $balance ? '. The order is fully paid.' : '.'));
            redirect($self);
        }
    } elseif ($action === 'delete_payment' && is_admin()) {
        q('DELETE FROM transactions WHERE id = ? AND order_id = ?', [(int) input('tx_id'), $id]);
        flash('Payment removed.');
        redirect($self);
    } elseif ($action === 'edit' && $order['status'] !== 'Completed') {
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
                flash('Order details saved.');
                redirect($self);
            }
        }
    }
}

$stamps = [];
$history = q(
    'SELECT h.status, h.changed_at, u.name AS user_name FROM order_status_history h
       LEFT JOIN users u ON u.id = h.changed_by WHERE h.order_id = ? ORDER BY h.changed_at, h.id',
    [$id]
)->fetchAll();
foreach ($history as $h) {
    $stamps[$h['status']] = $h['changed_at'];
}
$payments = q(
    'SELECT t.*, u.name AS user_name FROM transactions t LEFT JOIN users u ON u.id = t.received_by
      WHERE t.order_id = ? ORDER BY t.created_at, t.id',
    [$id]
)->fetchAll();
$services = q('SELECT * FROM services WHERE is_active = 1 OR id = ? ORDER BY sort_order, name', [$order['service_id']])->fetchAll();
[$payKey, $payLabel] = payment_state((float) $order['amount_due'], (float) $order['paid']);
$next = next_status($order['status']);
$editOpen = $errors && input('action') === 'edit';

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
          <span class="muted-strong"><?= e($order['service_name']) ?> · <?= e(kg($order['weight_kg'])) ?> · <?= e(fmt_dt($order['created_at'])) ?></span>
          <?= status_chip($order['status']) ?>
        </div>
        <h2 class="order-number"><?= e($order['order_no']) ?></h2>
        <?php require __DIR__ . '/../includes/partials/progress.php'; ?>
        <div class="hero-actions">
          <?php if ($next): ?>
            <form method="post" class="inline-form"<?= $next === 'Completed' && $balance > 0 ? ' data-confirm="' . e('This order still has ' . money($balance) . ' unpaid. Mark it completed anyway?') . '"' : '' ?>>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="advance">
              <input type="hidden" name="order_id" value="<?= $id ?>">
              <input type="hidden" name="from" value="<?= e($order['status']) ?>">
              <button class="btn btn-primary btn-lg" type="submit"><?= e($next === 'Completed' ? 'Mark completed' : 'Move to ' . $next) ?><?= icon('arrow') ?></button>
            </form>
          <?php else: ?>
            <span class="done-note"><?= icon('check') ?>Completed <?= e(fmt_when($order['completed_at'])) ?></span>
          <?php endif; ?>
          <button class="btn btn-ghost" type="button" data-print><?= icon('printer') ?>Print claim slip</button>
        </div>
        <?php if (is_admin()): ?>
          <form method="post" class="set-status" data-confirm="Change this order's status? It will be added to the history.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="from" value="<?= e($order['status']) ?>">
            <label for="to">Admin: set status</label>
            <select id="to" name="to">
              <?php foreach (STATUSES as $s): ?><option<?= $s === $order['status'] ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm" type="submit">Set</button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h3>Details</h3>
        <?php if ($order['status'] !== 'Completed'): ?><button class="link-btn" type="button" data-toggle="edit-form" aria-expanded="<?= $editOpen ? 'true' : 'false' ?>">Edit</button><?php endif; ?>
      </div>
      <dl class="details">
        <div><dt>Customer</dt><dd><a href="<?= e(url('app/customer.php?id=' . $order['customer_id'])) ?>"><?= e($order['customer_name']) ?></a> · <a href="tel:<?= e($order['phone']) ?>"><?= e(fmt_phone($order['phone'])) ?></a></dd></div>
        <div><dt>Service</dt><dd><?= e($order['service_name']) ?> · <?= e(money($order['price_per_kg'])) ?>/kg</dd></div>
        <div><dt>Weight</dt><dd><?= e(kg($order['weight_kg'])) ?></dd></div>
        <div><dt>Total</dt><dd class="num"><?= e(money($order['amount_due'])) ?></dd></div>
        <?php if ($order['notes']): ?><div><dt>Notes</dt><dd><?= e($order['notes']) ?></dd></div><?php endif; ?>
        <div><dt>Created</dt><dd><?= e(fmt_dt($order['created_at'], 'M j, Y g:i A')) ?><?= $order['created_by_name'] ? ' by ' . e($order['created_by_name']) : '' ?></dd></div>
      </dl>
      <?php if ($order['status'] !== 'Completed'): ?>
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
          <li><?= status_chip($h['status']) ?><span class="muted"><?= e(fmt_dt($h['changed_at'], 'M j, Y g:i A')) ?><?= $h['user_name'] ? ' · ' . e($h['user_name']) : '' ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <aside class="order-side">
    <section class="panel">
      <div class="panel-head"><h3>Payment</h3><span class="pay-tag pay-<?= e($payKey) ?>"><?= e($payLabel) ?></span></div>
      <dl class="sum-rows">
        <div><dt>Total</dt><dd><?= e(money($order['amount_due'])) ?></dd></div>
        <div><dt>Paid</dt><dd><?= e(money($order['paid'])) ?></dd></div>
        <div class="sum-strong"><dt>Balance</dt><dd><?= e(money($balance)) ?></dd></div>
      </dl>
      <?php if ($payments): ?>
        <ul class="pay-list">
          <?php foreach ($payments as $p): ?>
            <li>
              <span class="list-main"><b><?= e(money($p['amount'])) ?> · <?= $p['method'] === 'gcash' ? 'GCash' : 'Cash' ?></b>
                <span class="muted-sm"><?= e(fmt_when($p['created_at'])) ?><?= $p['reference'] ? ' · Ref ' . e($p['reference']) : '' ?><?= $p['user_name'] ? ' · ' . e($p['user_name']) : '' ?></span></span>
              <?php if (is_admin()): ?>
                <form method="post" data-confirm="Remove this <?= e(money($p['amount'])) ?> payment?"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_payment"><input type="hidden" name="tx_id" value="<?= (int) $p['id'] ?>">
                  <button class="link-btn danger" type="submit">Remove</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($balance > 0): ?>
        <form method="post" class="stack pay-form" novalidate data-validate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="pay">
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
            <input id="reference" name="reference" maxlength="64" value="<?= e(input('reference')) ?>" inputmode="numeric">
            <?= field_error($errors, 'reference') ?>
          </div>
          <button class="btn btn-primary btn-block" type="submit">Record payment</button>
        </form>
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
