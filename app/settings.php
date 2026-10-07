<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    if ($action === 'shop') {
        $shop = ['shop_name' => mb_substr(input('shop_name'), 0, 80), 'shop_phone' => mb_substr(input('shop_phone'), 0, 40), 'shop_address' => mb_substr(input('shop_address'), 0, 200)];
        if ($shop['shop_name'] === '') {
            $errors['shop_name'] = 'Enter the shop name.';
        } else {
            foreach ($shop as $k => $val) {
                q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, $val]);
            }
            flash('Shop details saved.');
            redirect('app/settings.php');
        }
    } elseif ($action === 'services') {
        $names = $_POST['svc_name'] ?? [];
        $prices = $_POST['svc_price'] ?? [];
        $activeIds = array_map('intval', (array) ($_POST['svc_active'] ?? []));
        foreach ((array) $names as $sid => $name) {
            $name = mb_substr(trim((string) $name), 0, 60);
            $price = round((float) ($prices[$sid] ?? 0), 2);
            if ($name === '' || $price <= 0) {
                $errors['services'] = 'Every service needs a name and a price above zero.';
                continue;
            }
            q('UPDATE services SET name = ?, price_per_kg = ?, is_active = ? WHERE id = ?', [$name, $price, in_array((int) $sid, $activeIds, true) ? 1 : 0, (int) $sid]);
        }
        if (!$errors) {
            flash('Services saved. New prices apply to new orders only.');
            redirect('app/settings.php');
        }
    } elseif ($action === 'add_service') {
        $name = mb_substr(input('new_name'), 0, 60);
        $price = round((float) input('new_price'), 2);
        if ($name === '' || $price <= 0) {
            $errors['add_service'] = 'Enter a service name and a price above zero.';
        } else {
            $sort = (int) q_val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM services');
            q('INSERT INTO services (name, price_per_kg, sort_order) VALUES (?, ?, ?)', [$name, $price, $sort]);
            flash($name . ' added.');
            redirect('app/settings.php');
        }
    }
}

$services = q('SELECT * FROM services ORDER BY sort_order, name')->fetchAll();
$title = 'Settings';
$active = 'settings';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<div class="settings-grid">
  <section class="panel">
    <div class="panel-head"><h3>Shop</h3></div>
    <p class="muted">Shown on claim slips and on the customer tracking page.</p>
    <form method="post" class="stack" novalidate data-validate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="shop">
      <div class="field">
        <label for="shop_name">Shop name</label>
        <input id="shop_name" name="shop_name" value="<?= e(input('shop_name', setting('shop_name'))) ?>" maxlength="80" required>
        <?= field_error($errors, 'shop_name') ?>
      </div>
      <div class="field">
        <label for="shop_phone">Phone</label>
        <input id="shop_phone" name="shop_phone" type="tel" value="<?= e(input('shop_phone', setting('shop_phone'))) ?>" maxlength="40">
      </div>
      <div class="field">
        <label for="shop_address">Address</label>
        <input id="shop_address" name="shop_address" value="<?= e(input('shop_address', setting('shop_address'))) ?>" maxlength="200">
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save shop details</button></div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h3>Services and prices</h3></div>
    <p class="muted">Orders keep the price they were created with. Turn a service off to hide it from new orders.</p>
    <form method="post" class="stack" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="services">
      <div class="svc-list">
        <?php foreach ($services as $s): ?>
          <div class="svc-row">
            <input name="svc_name[<?= (int) $s['id'] ?>]" value="<?= e($s['name']) ?>" maxlength="60" aria-label="Service name" required>
            <label class="price-input"><span><?= e($config['currency']) ?></span><input name="svc_price[<?= (int) $s['id'] ?>]" type="number" step="0.01" min="0.01" inputmode="decimal" value="<?= e($s['price_per_kg']) ?>" aria-label="Price per kg for <?= e($s['name']) ?>" required><span>/kg</span></label>
            <label class="switch"><input type="checkbox" name="svc_active[]" value="<?= (int) $s['id'] ?>"<?= $s['is_active'] ? ' checked' : '' ?>><span>Active</span></label>
          </div>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'services') ?>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save services</button></div>
    </form>
    <form method="post" class="add-svc" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_service">
      <input name="new_name" maxlength="60" placeholder="New service, e.g. Comforter" aria-label="New service name">
      <label class="price-input"><span><?= e($config['currency']) ?></span><input name="new_price" type="number" step="0.01" min="0.01" inputmode="decimal" placeholder="0.00" aria-label="Price per kg"><span>/kg</span></label>
      <button class="btn btn-outline" type="submit"><?= icon('plus') ?>Add</button>
    </form>
    <?= field_error($errors, 'add_service') ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
