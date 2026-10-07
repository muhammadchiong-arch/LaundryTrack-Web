<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('admin');

$errors = [];
$saveSetting = fn ($k, $val) => q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, (string) $val]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    if ($action === 'schedule') {
        $days = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['open_days'] ?? [])), fn ($d) => $d >= 1 && $d <= 7)));
        sort($days);
        $open = input('open_time');
        $close = input('close_time');
        $minutes = (int) input('slot_minutes');
        $cap = (int) input('slot_capacity');
        $ahead = (int) input('booking_days');
        $lead = (int) input('lead_minutes');
        if (!$days) $errors['open_days'] = 'Choose at least one open day.';
        if (!preg_match('/^\d{2}:\d{2}$/', $open) || !preg_match('/^\d{2}:\d{2}$/', $close) || $close <= $open) $errors['hours'] = 'Closing time must be after opening time.';
        if (!in_array($minutes, [30, 60, 90, 120], true)) $errors['slot_minutes'] = 'Choose a slot length.';
        if ($cap < 1 || $cap > 50) $errors['slot_capacity'] = 'Enter 1 to 50 bookings per slot.';
        if ($ahead < 1 || $ahead > 60) $errors['booking_days'] = 'Enter 1 to 60 days.';
        if ($lead < 0 || $lead > 1440) $errors['lead_minutes'] = 'Enter 0 to 1440 minutes.';
        if (!$errors) {
            foreach (['open_days' => implode(',', $days), 'open_time' => $open, 'close_time' => $close, 'slot_minutes' => $minutes,
                      'slot_capacity' => $cap, 'booking_days' => $ahead, 'lead_minutes' => $lead] as $k => $val) {
                $saveSetting($k, $val);
            }
            log_activity('settings.schedule', 'Hours ' . $open . ' to ' . $close . ', ' . $minutes . ' min slots, ' . $cap . ' per slot');
            flash('Schedule saved. Existing bookings keep their times.');
            redirect('app/settings.php#schedule');
        }
    } elseif ($action === 'close_date') {
        $d = input('closed_on');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d < date('Y-m-d')) {
            $errors['closed_on'] = 'Choose today or a future date.';
        } else {
            q('INSERT INTO closed_dates (closed_on, note) VALUES (?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note)', [$d, mb_substr(input('note'), 0, 100)]);
            $held = (int) q_val("SELECT COUNT(*) FROM bookings WHERE slot_date = ? AND status IN ('Pending','Confirmed')", [$d]);
            log_activity('settings.closed_date', 'Closed ' . $d);
            flash('Closed on ' . date('M j', strtotime($d)) . '.' . ($held ? ' ' . $held . ' booking' . ($held === 1 ? ' is' : 's are') . ' already on that day: open Bookings to cancel them and tell the customers.' : ''), $held ? 'warning' : 'success');
            redirect('app/settings.php#schedule');
        }
    } elseif ($action === 'reopen_date') {
        q('DELETE FROM closed_dates WHERE closed_on = ?', [input('closed_on')]);
        flash('Day reopened for booking.');
        redirect('app/settings.php#schedule');
    } elseif ($action === 'shop') {
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
        $descs = $_POST['svc_desc'] ?? [];
        $activeIds = array_map('intval', (array) ($_POST['svc_active'] ?? []));
        foreach ((array) $names as $sid => $name) {
            $name = mb_substr(trim((string) $name), 0, 60);
            $price = round((float) ($prices[$sid] ?? 0), 2);
            if ($name === '' || $price <= 0) {
                $errors['services'] = 'Every service needs a name and a price above zero.';
                continue;
            }
            q('UPDATE services SET name = ?, description = ?, price_per_kg = ?, is_active = ? WHERE id = ?',
              [$name, mb_substr(trim((string) ($descs[$sid] ?? '')), 0, 160), $price, in_array((int) $sid, $activeIds, true) ? 1 : 0, (int) $sid]);
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
$sch = schedule();
$closedDates = q('SELECT * FROM closed_dates WHERE closed_on >= CURDATE() ORDER BY closed_on')->fetchAll();
$val = fn ($k, $d) => input($k, (string) $d);
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
            <input class="svc-desc" name="svc_desc[<?= (int) $s['id'] ?>]" value="<?= e($s['description']) ?>" maxlength="160" placeholder="Short description customers see when booking" aria-label="Description for <?= e($s['name']) ?>">
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

  <section class="panel" id="schedule">
    <div class="panel-head"><h3>Booking schedule</h3></div>
    <p class="muted">Customers book a drop-off slot. Each slot accepts a set number of bookings; walk-ins are not limited.</p>
    <form method="post" class="stack" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="schedule">
      <fieldset class="field">
        <legend class="label">Open days</legend>
        <div class="day-picks">
          <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $d): ?>
            <label class="day-pick"><input type="checkbox" name="open_days[]" value="<?= $n ?>"<?= in_array($n, $sch['days'], true) ? ' checked' : '' ?>><span><?= $d ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'open_days') ?>
      </fieldset>
      <div class="field-row">
        <div class="field"><label for="open_time">Opens</label><input id="open_time" name="open_time" type="time" value="<?= e($val('open_time', $sch['open'])) ?>"></div>
        <div class="field"><label for="close_time">Closes</label><input id="close_time" name="close_time" type="time" value="<?= e($val('close_time', $sch['close'])) ?>"><?= field_error($errors, 'hours') ?></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="slot_minutes">Slot length</label>
          <select id="slot_minutes" name="slot_minutes"><?php foreach ([30, 60, 90, 120] as $m): ?><option value="<?= $m ?>"<?= (int) $val('slot_minutes', $sch['minutes']) === $m ? ' selected' : '' ?>><?= $m ?> minutes</option><?php endforeach; ?></select></div>
        <div class="field"><label for="slot_capacity">Bookings per slot</label><input id="slot_capacity" name="slot_capacity" type="number" min="1" max="50" value="<?= e($val('slot_capacity', $sch['capacity'])) ?>"><?= field_error($errors, 'slot_capacity') ?: '<small class="hint">How many drop-offs your counter can handle at once.</small>' ?></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="booking_days">Book up to (days ahead)</label><input id="booking_days" name="booking_days" type="number" min="1" max="60" value="<?= e($val('booking_days', $sch['ahead'])) ?>"><?= field_error($errors, 'booking_days') ?></div>
        <div class="field"><label for="lead_minutes">Earliest booking (minutes before)</label><input id="lead_minutes" name="lead_minutes" type="number" min="0" max="1440" step="15" value="<?= e($val('lead_minutes', $sch['lead'])) ?>"><?= field_error($errors, 'lead_minutes') ?></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save schedule</button></div>
    </form>
    <div class="closed-days">
      <h4>Closed days</h4>
      <?php if ($closedDates): ?>
        <ul class="list">
          <?php foreach ($closedDates as $cd): ?>
            <li><div class="list-row"><span class="list-main"><b><?= e(date('D, M j, Y', strtotime($cd['closed_on']))) ?></b><span><?= e($cd['note'] ?: 'Closed') ?></span></span>
              <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="reopen_date"><input type="hidden" name="closed_on" value="<?= e($cd['closed_on']) ?>"><button class="link-btn" type="submit">Reopen</button></form>
            </div></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted-sm">No holidays set. Add one so customers can't book that day.</p>
      <?php endif; ?>
      <form method="post" class="add-svc add-closed" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="close_date">
        <input type="date" name="closed_on" min="<?= date('Y-m-d') ?>" aria-label="Date to close" required>
        <input name="note" maxlength="100" placeholder="Reason, e.g. All Saints' Day" aria-label="Reason">
        <button class="btn btn-outline" type="submit"><?= icon('plus') ?>Close day</button>
      </form>
      <?= field_error($errors, 'closed_on') ?>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
