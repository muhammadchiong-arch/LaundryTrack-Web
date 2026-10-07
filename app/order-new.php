<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin', 'staff');

$services = q('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$mode = input('mode', 'existing') === 'new' ? 'new' : 'existing';
$customerId = (int) input('customer_id');
$picked = $customerId ? q_one('SELECT id, name, phone FROM customers WHERE id = ?', [$customerId]) : null;
$v = [
    'name' => input('name'), 'phone' => input('phone'), 'address' => input('address'),
    'service_id' => (int) input('service_id', (string) ($services[0]['id'] ?? 0)),
    'weight_kg' => input('weight_kg'), 'notes' => input('notes'),
    'pay_amount' => input('pay_amount'), 'pay_method' => input('pay_method', 'cash'), 'pay_reference' => input('pay_reference'),
];
$errors = [];

$dupes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'existing') {
        if (!$picked) $errors['customer'] = 'Search and pick a customer, or add a new one.';
    } else {
        $phone = phone_digits($v['phone']);
        if ($v['name'] === '') $errors['name'] = 'Enter the customer\'s name.';
        if (strlen($phone) < 7 || strlen($phone) > 13) {
            $errors['phone'] = 'Enter a phone number, e.g. 0917 123 4567.';
        } elseif (!input('confirm_new')) {
            // Avoid duplicate customer records: offer the existing one first.
            $dupes = q('SELECT id, name, phone FROM customers WHERE phone = ? ORDER BY id LIMIT 3', [$phone])->fetchAll();
            if ($dupes) $errors['phone'] = 'This number is already saved. Use the existing customer, or tick "Add as a new customer" below.';
        }
    }
    $service = null;
    foreach ($services as $s) {
        if ((int) $s['id'] === $v['service_id']) $service = $s;
    }
    if (!$service) $errors['service_id'] = 'Choose a service.';
    $weight = round((float) $v['weight_kg'], 2);
    if ($weight < 0.1 || $weight > 200) $errors['weight_kg'] = 'Enter the weight in kg (0.1 to 200).';
    $due = $service ? round($weight * (float) $service['price_per_kg'], 2) : 0;
    [$payErr, $payAmount, $payMethod, $payRef] = validate_payment(['amount' => $v['pay_amount'], 'method' => $v['pay_method'], 'reference' => $v['pay_reference']], $due, true);
    foreach ($payErr as $k => $msg) $errors['pay_' . $k] = $msg;

    if (!$errors) {
        if ($mode === 'new') {
            q('INSERT INTO customers (name, phone, address, created_by) VALUES (?, ?, ?, ?)',
              [mb_substr($v['name'], 0, 100), $phone, mb_substr($v['address'], 0, 255) ?: null, $me['id']]);
            $customerId = (int) db()->lastInsertId();
        }
        [$orderId, $orderNo] = create_order(['customer_id' => $customerId, 'service' => $service, 'weight' => $weight,
            'notes' => mb_substr($v['notes'], 0, 255), 'pay_amount' => $payAmount, 'pay_method' => $payMethod, 'pay_reference' => $payRef], $me['id']);
        flash('Order ' . $orderNo . ' created. Give the customer this number.');
        redirect('app/order.php?id=' . $orderId);
    }
}

$title = 'New order';
$active = 'new';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<form method="post" class="new-order" novalidate data-validate data-new-order>
  <?= csrf_field() ?>
  <div class="new-order-main">
    <section class="panel step-panel">
      <div class="step-head"><span class="step-n">1</span><h3>Customer</h3></div>
      <div class="segmented" role="radiogroup" aria-label="Customer">
        <label><input type="radio" name="mode" value="existing"<?= $mode === 'existing' ? ' checked' : '' ?>><span>Existing customer</span></label>
        <label><input type="radio" name="mode" value="new"<?= $mode === 'new' ? ' checked' : '' ?>><span>New customer</span></label>
      </div>

      <div class="stack" data-show-when="mode=existing">
        <input type="hidden" name="customer_id" value="<?= $picked ? (int) $picked['id'] : '' ?>" data-customer-id>
        <div class="picked<?= $picked ? '' : ' is-empty' ?>" data-picked>
          <span class="avatar" data-picked-ini><?= $picked ? e(initials($picked['name'])) : '' ?></span>
          <span class="list-main"><b data-picked-name><?= $picked ? e($picked['name']) : '' ?></b><span class="muted-sm" data-picked-phone><?= $picked ? e(fmt_phone($picked['phone'])) : '' ?></span></span>
          <button class="link-btn" type="button" data-unpick>Change</button>
        </div>
        <div class="field" data-search-box<?= $picked ? ' hidden' : '' ?>>
          <label for="customer_q">Search by name or phone</label>
          <label class="search-pill"><?= icon('search') ?><input id="customer_q" type="search" autocomplete="off" placeholder="e.g. Reyes or 0917…" data-customer-search data-endpoint="<?= e(url('app/api/customers.php')) ?>"></label>
          <ul class="results" data-results role="listbox" aria-label="Matching customers"></ul>
          <?= field_error($errors, 'customer') ?>
        </div>
      </div>

      <div class="stack" data-show-when="mode=new">
        <div class="field-row">
          <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" value="<?= e($v['name']) ?>" maxlength="100" autocomplete="off" data-required-when="mode=new">
            <?= field_error($errors, 'name') ?>
          </div>
          <div class="field">
            <label for="phone">Mobile number</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($v['phone']) ?>" placeholder="0917 123 4567" autocomplete="off" data-required-when="mode=new">
            <?= field_error($errors, 'phone') ?: '<small class="hint">The last 4 digits let the customer track the order.</small>' ?>
          </div>
          <?php if ($dupes): ?>
            <div class="dupes">
              <?php foreach ($dupes as $d): ?>
                <a class="btn btn-sm btn-soft" href="<?= e(url('app/order-new.php?customer_id=' . $d['id'])) ?>">Use <?= e($d['name']) ?> · <?= e(fmt_phone($d['phone'])) ?></a>
              <?php endforeach; ?>
              <label class="check"><input type="checkbox" name="confirm_new" value="1"> Add as a new customer anyway</label>
            </div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="address">Address <span class="optional">Optional</span></label>
          <input id="address" name="address" value="<?= e($v['address']) ?>" maxlength="255">
        </div>
      </div>
    </section>

    <section class="panel step-panel">
      <div class="step-head"><span class="step-n">2</span><h3>Laundry</h3></div>
      <div class="choice-grid" role="radiogroup" aria-label="Service">
        <?php foreach ($services as $s): ?>
          <label class="choice">
            <input type="radio" name="service_id" value="<?= (int) $s['id'] ?>" data-price="<?= e($s['price_per_kg']) ?>" data-name="<?= e($s['name']) ?>"<?= (int) $s['id'] === $v['service_id'] ? ' checked' : '' ?>>
            <span><b><?= e($s['name']) ?></b><span class="muted-sm"><?= e(money($s['price_per_kg'])) ?> per kg</span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'service_id') ?>
      <div class="field-row">
        <div class="field">
          <label for="weight_kg">Weight (kg)</label>
          <input id="weight_kg" name="weight_kg" type="number" inputmode="decimal" step="0.1" min="0.1" max="200" value="<?= e($v['weight_kg']) ?>" placeholder="0.0" required data-weight>
          <?= field_error($errors, 'weight_kg') ?>
        </div>
        <div class="field">
          <label for="notes">Notes <span class="optional">Optional</span></label>
          <input id="notes" name="notes" value="<?= e($v['notes']) ?>" maxlength="255" placeholder="Stains, delicate items…">
        </div>
      </div>
    </section>

    <section class="panel step-panel">
      <div class="step-head"><span class="step-n">3</span><h3>Payment now <span class="optional">Optional</span></h3></div>
      <div class="field-row">
        <div class="field">
          <label for="pay_amount">Amount received</label>
          <input id="pay_amount" name="pay_amount" type="number" inputmode="decimal" step="0.01" min="0" value="<?= e($v['pay_amount']) ?>" placeholder="0.00 · pay at pickup" data-pay>
          <?= field_error($errors, 'pay_amount') ?>
        </div>
        <div class="field">
          <span class="label">Method</span>
          <div class="segmented" role="radiogroup" aria-label="Method">
            <label><input type="radio" name="pay_method" value="cash"<?= $v['pay_method'] !== 'gcash' ? ' checked' : '' ?>><span>Cash</span></label>
            <label><input type="radio" name="pay_method" value="gcash"<?= $v['pay_method'] === 'gcash' ? ' checked' : '' ?>><span>GCash</span></label>
          </div>
        </div>
      </div>
      <div class="field" data-show-when="pay_method=gcash">
        <label for="pay_reference">GCash reference no.</label>
        <input id="pay_reference" name="pay_reference" value="<?= e($v['pay_reference']) ?>" maxlength="64" inputmode="numeric">
        <?= field_error($errors, 'pay_reference') ?>
      </div>
    </section>
  </div>

  <aside class="summary">
    <span class="kicker kicker-light">ORDER SUMMARY</span>
    <dl class="aside-rows">
      <div><dt>Service</dt><dd data-sum-service>—</dd></div>
      <div><dt>Weight</dt><dd data-sum-weight>—</dd></div>
      <div><dt>Rate</dt><dd data-sum-rate>—</dd></div>
      <div><dt>Paid now</dt><dd data-sum-paid><?= e(money(0)) ?></dd></div>
    </dl>
    <div class="summary-total"><span>Total</span><b data-sum-total data-currency="<?= e($config['currency']) ?>"><?= e(money(0)) ?></b></div>
    <button class="btn btn-light btn-lg btn-block" type="submit">Create order</button>
    <p class="summary-note">The order number is created when you save. Status starts at Received.</p>
  </aside>
</form>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
