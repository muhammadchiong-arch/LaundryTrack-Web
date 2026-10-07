<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$id = (int) input('id');
$isNew = !$id;
$c = $isNew ? ['name' => '', 'phone' => '', 'email' => '', 'address' => '', 'notes' => ''] : q_one('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) {
    http_response_code(404);
    $title = 'Customer not found';
    $message = 'This customer doesn\'t exist.';
    $back = url('app/customers.php');
    require __DIR__ . '/../includes/layout/error.php';
    exit;
}
$errors = [];

$hasAccount = !$isNew && !empty($c['password_hash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'toggle_account' && $hasAccount) {
    if ($me['role'] !== 'admin') {
        flash('Only an admin can turn online accounts on or off.', 'warning');
    } else {
        q('UPDATE customers SET is_active = 1 - is_active WHERE id = ?', [$id]);
        log_activity('customer.account', $c['name'] . ' online account turned ' . ($c['is_active'] ? 'off' : 'on'), $id);
        flash('Online account turned ' . ($c['is_active'] ? 'off. They can no longer sign in or book.' : 'on.'));
    }
    redirect('app/customer.php?id=' . $id);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = ['name' => mb_substr(input('name'), 0, 100), 'phone' => phone_digits(input('phone')),
          'email' => input('email'), 'address' => mb_substr(input('address'), 0, 255), 'notes' => mb_substr(input('notes'), 0, 255)];
    if ($v['name'] === '') $errors['name'] = 'Enter the customer\'s name.';
    if (strlen($v['phone']) < 7 || strlen($v['phone']) > 13) $errors['phone'] = 'Enter a phone number, e.g. 0917 123 4567.';
    if ($hasAccount) {
        $v['email'] = $c['email']; // the sign-in email of an online account is changed only by the customer's own request
    } elseif ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email or leave it blank.';
    } elseif ($v['email'] !== '' && q_val('SELECT 1 FROM customers WHERE email = ? AND id <> ?', [$v['email'], $id])) {
        $errors['email'] = 'Another customer already uses this email.';
    }
    if (!$errors) {
        $vals = [$v['name'], $v['phone'], $v['email'] ?: null, $v['address'] ?: null, $v['notes'] ?: null];
        if ($isNew) {
            q('INSERT INTO customers (name, phone, email, address, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)', [...$vals, $me['id']]);
            $id = (int) db()->lastInsertId();
            flash($v['name'] . ' added.');
        } else {
            q('UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, notes = ? WHERE id = ?', [...$vals, $id]);
            flash('Customer details saved.');
        }
        redirect('app/customer.php?id=' . $id);
    }
    $c = array_merge($c, $v, ['phone' => input('phone')]);
}

$orders = [];
$bookings = [];
$stats = ['n' => 0, 'spent' => 0, 'balance' => 0];
if (!$isNew) {
    $bookings = q('SELECT b.*, s.name AS service_name FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.customer_id = ? ORDER BY b.slot_date DESC, b.slot_time DESC LIMIT 10', [$id])->fetchAll();
    $orders = q(
        'SELECT o.id, o.order_no, o.status, o.amount_due, o.weight_kg, o.created_at, s.name AS service_name,
                ' . sql_paid() . ' AS paid
           FROM orders o JOIN services s ON s.id = o.service_id WHERE o.customer_id = ? ORDER BY o.created_at DESC',
        [$id]
    )->fetchAll();
    foreach ($orders as $o) {
        $stats['n']++;
        $stats['spent'] += (float) $o['paid'];
        $stats['balance'] += $o['status'] === ORDER_CANCELLED ? 0 : max(0, (float) $o['amount_due'] - (float) $o['paid']);
    }
}

$title = $isNew ? 'Add customer' : $c['name'];
$active = 'customers';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<a class="back-link" href="<?= e(url('app/customers.php')) ?>"><?= icon('back') ?>Customers</a>

<div class="order-layout<?= $isNew ? ' narrow' : '' ?>">
  <div class="order-main">
    <?php if (!$isNew): ?>
      <section class="stat-strip three" aria-label="Summary">
        <div class="stat"><span>Orders</span><b><?= $stats['n'] ?></b></div>
        <div class="stat"><span>Total paid</span><b><?= e(money($stats['spent'])) ?></b></div>
        <div class="stat"><span>Balance</span><b><?= e(money($stats['balance'])) ?></b></div>
      </section>
      <section class="panel">
        <div class="panel-head"><h3>Orders</h3><a class="btn btn-primary btn-sm" href="<?= e(url('app/order-new.php?customer_id=' . $id)) ?>"><?= icon('plus') ?>New order</a></div>
        <?php if (!$orders): ?>
          <p class="empty">No orders yet.</p>
        <?php else: ?>
          <ul class="list">
            <?php foreach ($orders as $o): [$pk, $pl] = payment_state((float) $o['amount_due'], (float) $o['paid']); ?>
              <li><a class="list-row" href="<?= e(url('app/order.php?id=' . $o['id'])) ?>">
                <span class="list-main"><b class="order-no"><?= e($o['order_no']) ?></b><span class="muted"><?= e($o['service_name']) ?> · <?= e(kg($o['weight_kg'])) ?> · <?= e(fmt_dt($o['created_at'], 'M j, Y')) ?></span></span>
                <span class="list-amount"><b class="num"><?= e(money($o['amount_due'])) ?></b><span class="pay-<?= e($pk) ?>"><?= e($pl) ?></span></span>
                <?= status_chip($o['status']) ?>
              </a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
      <?php if ($bookings): ?>
        <section class="panel">
          <div class="panel-head"><h3>Bookings</h3></div>
          <ul class="list">
            <?php foreach ($bookings as $bk): ?>
              <li><a class="list-row" href="<?= e(url('app/booking.php?id=' . $bk['id'])) ?>"><span class="list-main"><b><?= e(slot_label($bk)) ?></b><span><?= e($bk['booking_no']) ?> · <?= e($bk['service_name']) ?></span></span><?= booking_chip($bk['status']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <aside class="order-side">
    <?php if ($hasAccount): ?>
      <section class="panel">
        <div class="panel-head"><h3>Online account</h3><span class="pay-tag <?= $c['is_active'] ? 'pay-paid' : 'pay-unpaid' ?>"><?= $c['is_active'] ? 'Active' : 'Turned off' ?></span></div>
        <p class="muted-sm">Signs in with <?= e($c['email']) ?><?= $c['last_login_at'] ? ' · last sign-in ' . e(fmt_when($c['last_login_at'])) : '' ?>.</p>
        <?php if ($me['role'] === 'admin'): ?>
          <form method="post" data-confirm="<?= $c['is_active'] ? 'Turn off this online account? The customer will not be able to sign in or book.' : 'Turn this online account back on?' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_account">
            <button class="btn btn-sm <?= $c['is_active'] ? 'btn-outline danger' : 'btn-soft' ?>" type="submit"><?= $c['is_active'] ? 'Turn off account' : 'Turn on account' ?></button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <section class="panel">
      <div class="panel-head"><h3><?= $isNew ? 'Customer details' : 'Details' ?></h3></div>
      <form method="post" class="stack" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="name">Name</label>
          <input id="name" name="name" value="<?= e($c['name']) ?>" maxlength="100" required>
          <?= field_error($errors, 'name') ?>
        </div>
        <div class="field">
          <label for="phone">Mobile number</label>
          <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($errors ? $c['phone'] : fmt_phone($c['phone'])) ?>" required>
          <?= field_error($errors, 'phone') ?: '<small class="hint">Customers track orders with the last 4 digits.</small>' ?>
        </div>
        <div class="field">
          <label for="email">Email <span class="optional">Optional</span></label>
          <input id="email" name="email" type="email" value="<?= e($c['email'] ?? '') ?>"<?= $hasAccount ? ' readonly aria-describedby="email-lock"' : '' ?>>
          <?php if ($hasAccount): ?><small class="hint" id="email-lock">This is the customer's sign-in email, so it can't be changed here.</small><?php endif; ?>
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="field">
          <label for="address">Address <span class="optional">Optional</span></label>
          <input id="address" name="address" value="<?= e($c['address'] ?? '') ?>" maxlength="255">
        </div>
        <div class="field">
          <label for="notes">Notes <span class="optional">Optional</span></label>
          <textarea id="notes" name="notes" rows="2" maxlength="255"><?= e($c['notes'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= $isNew ? 'Add customer' : 'Save changes' ?></button>
      </form>
    </section>
  </aside>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
