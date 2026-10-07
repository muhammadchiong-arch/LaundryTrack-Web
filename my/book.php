<?php
// Customer booking in four short steps, then a confirmation page (step 5).
// The draft lives in the session, so Back/refresh never loses what was picked,
// and every step is checked again on the server before the booking is saved.
require __DIR__ . '/../includes/bootstrap.php';
$me = require_customer();

$services = q('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$draft = $_SESSION['draft'] ?? [];
$step = max(1, min(4, (int) input('step', '1')));
$errors = [];
$serviceOf = function ($id) use ($services) {
    foreach ($services as $s) if ((int) $s['id'] === (int) $id) return $s;
    return null;
};
// Don't let anyone skip ahead past an unfinished step.
if ($step >= 2 && !$serviceOf($draft['service_id'] ?? 0)) $step = 1;
if ($step >= 3 && (empty($draft['date']) || empty($draft['time']))) $step = 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        if (!$serviceOf(input('service_id'))) {
            $errors['service_id'] = 'Choose a laundry service.';
        } else {
            $draft['service_id'] = (int) input('service_id');
        }
    } elseif ($step === 2) {
        $date = input('date');
        $time = input('time');
        if ($err = validate_slot($date, $time)) {
            $errors['time'] = $err;
            $draft['date'] = $date;
        } else {
            $draft['date'] = $date;
            $draft['time'] = $time;
        }
    } elseif ($step === 3) {
        $kg = input('est_weight_kg');
        if ($kg !== '' && ((float) $kg < 0.5 || (float) $kg > 100)) {
            $errors['est_weight_kg'] = 'Enter an estimate between 0.5 and 100 kg, or leave it blank.';
        }
        $draft['est_weight_kg'] = $kg === '' ? null : round((float) $kg, 1);
        $draft['notes'] = mb_substr(input('notes'), 0, 255);
    } elseif ($step === 4) {
        $res = create_booking($me['id'], $serviceOf($draft['service_id']), $draft['date'], $draft['time'], $draft['est_weight_kg'] ?? null, $draft['notes'] ?? '');
        if (is_string($res)) {
            $errors['time'] = $res;
            $_SESSION['draft'] = $draft;
            flash($res, 'error');
            redirect('my/book.php?step=2&date=' . rawurlencode($draft['date']));
        }
        unset($_SESSION['draft']);
        log_activity('booking.create', $res[1] . ' for ' . $draft['date'] . ' ' . $draft['time'] . ' by customer', $me['id']);
        redirect('my/booking.php?id=' . $res[0] . '&new=1');
    }
    $_SESSION['draft'] = $draft;
    if (!$errors) {
        redirect('my/book.php?step=' . ($step + 1));
    }
}

$service = $serviceOf($draft['service_id'] ?? 0);
$dates = $step === 2 ? bookable_dates() : [];
$pickDate = input('date', $draft['date'] ?? '');
if ($step === 2) {
    $okDates = array_column(array_filter($dates, fn ($d) => $d['ok']), 'date');
    if (!in_array($pickDate, array_column($dates, 'date'), true)) $pickDate = $okDates[0] ?? '';
}
$slots = ($step === 2 && $pickDate) ? availability($pickDate) : [];
$steps = ['Service', 'Date & time', 'Details', 'Review', 'Done'];
$est = function () use ($service, $draft) {
    if (!$service || empty($draft['est_weight_kg'])) return null;
    return round($service['price_per_kg'] * $draft['est_weight_kg'], 2);
};

$title = 'Book laundry';
$active = 'book';
require __DIR__ . '/../includes/layout/app_top.php';
?>
<ol class="stepper" aria-label="Booking steps">
  <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
    <li class="<?= $n < $step ? 'done' : ($n === $step ? 'current' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
      <span class="stepper-dot"><?= $n < $step ? icon('check') : $n ?></span><span class="stepper-label"><?= e($label) ?></span>
    </li>
  <?php endforeach; ?>
</ol>

<div class="book-layout">
<section class="panel book-panel">
<?php if ($step === 1): ?>
  <div class="panel-head"><h3>Which service do you need?</h3></div>
  <form method="post" action="<?= e(url('my/book.php?step=1')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="choice-grid service-choices" role="radiogroup" aria-label="Laundry service">
      <?php foreach ($services as $s): ?>
        <label class="choice">
          <input type="radio" name="service_id" value="<?= (int) $s['id'] ?>"<?= (int) ($draft['service_id'] ?? 0) === (int) $s['id'] ? ' checked' : '' ?> required>
          <span><b><?= e($s['name']) ?></b><span class="choice-price"><?= e(money($s['price_per_kg'])) ?> per kg</span><?php if ($s['description']): ?><span class="muted-sm"><?= e($s['description']) ?></span><?php endif; ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?= field_error($errors, 'service_id') ?>
    <div class="form-actions"><button class="btn btn-primary btn-lg" type="submit">Continue<?= icon('arrow') ?></button></div>
  </form>

<?php elseif ($step === 2): ?>
  <div class="panel-head"><h3>When will you drop it off?</h3><a class="link-btn" href="<?= e(url('my/book.php?step=1')) ?>">Change service</a></div>
  <nav class="date-strip" aria-label="Choose a date">
    <?php foreach ($dates as $d): $sel = $d['date'] === $pickDate; ?>
      <?php if ($d['ok']): ?>
        <a class="date-chip<?= $sel ? ' on' : '' ?>" href="<?= e(url('my/book.php?step=2&date=' . $d['date'])) ?>"<?= $sel ? ' aria-current="date"' : '' ?>>
          <span><?= e($d['date'] === date('Y-m-d') ? 'Today' : date('D', strtotime($d['date']))) ?></span><b><?= date('j', strtotime($d['date'])) ?></b><span><?= date('M', strtotime($d['date'])) ?></span></a>
      <?php else: ?>
        <span class="date-chip off" aria-disabled="true" title="<?= e($d['reason']) ?>"><span><?= e(date('D', strtotime($d['date']))) ?></span><b><?= date('j', strtotime($d['date'])) ?></b><span><?= e($d['reason']) ?></span></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <?php if (!$pickDate): ?>
    <p class="empty">No drop-off times are open in the next <?= schedule()['ahead'] ?> days. Please check again later or call the shop<?= setting('shop_phone') ? ' at ' . e(setting('shop_phone')) : '' ?>.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('my/book.php?step=2')) ?>" class="stack" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($pickDate) ?>">
      <fieldset class="slot-set">
        <legend><?= e(date('l, F j', strtotime($pickDate))) ?></legend>
        <div class="slot-grid" role="radiogroup" aria-label="Drop-off time">
          <?php foreach ($slots as $sl): ?>
            <label class="slot<?= $sl['ok'] ? '' : ' off' ?>">
              <input type="radio" name="time" value="<?= e($sl['time']) ?>"<?= $sl['ok'] ? '' : ' disabled' ?><?= ($draft['time'] ?? '') === $sl['time'] && ($draft['date'] ?? '') === $pickDate && $sl['ok'] ? ' checked' : '' ?>>
              <span><b><?= e($sl['label']) ?></b><small><?= $sl['ok'] ? ($sl['left'] === 1 ? '1 place left' : $sl['left'] . ' places left') : e($sl['why'] === 'Past' ? 'Too soon' : 'Full') ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <?= field_error($errors, 'time') ?>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('my/book.php?step=1')) ?>">Back</a><button class="btn btn-primary btn-lg" type="submit">Continue<?= icon('arrow') ?></button></div>
    </form>
  <?php endif; ?>

<?php elseif ($step === 3): ?>
  <div class="panel-head"><h3>About your laundry</h3></div>
  <form method="post" action="<?= e(url('my/book.php?step=3')) ?>" class="stack" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="est_weight_kg">Estimated weight (kg) <span class="optional">Optional</span></label>
      <input id="est_weight_kg" name="est_weight_kg" type="number" inputmode="decimal" step="0.5" min="0.5" max="100" placeholder="e.g. 5" value="<?= e((string) ($draft['est_weight_kg'] ?? '')) ?>" data-est-kg data-price="<?= e($service['price_per_kg']) ?>" data-currency="<?= e($config['currency']) ?>">
      <?= field_error($errors, 'est_weight_kg') ?: '<small class="hint" data-est-out>A full laundry bag is about 5 to 7 kg. The shop weighs it when you drop it off.</small>' ?>
    </div>
    <div class="field">
      <label for="notes">Notes for the shop <span class="optional">Optional</span></label>
      <textarea id="notes" name="notes" rows="3" maxlength="255" placeholder="e.g. Separate the whites, no fabric softener"><?= e($draft['notes'] ?? '') ?></textarea>
    </div>
    <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('my/book.php?step=2')) ?>">Back</a><button class="btn btn-primary btn-lg" type="submit">Review booking<?= icon('arrow') ?></button></div>
  </form>

<?php else: ?>
  <div class="panel-head"><h3>Review your booking</h3></div>
  <dl class="details review">
    <div><dt>Service</dt><dd><?= e($service['name']) ?> · <?= e(money($service['price_per_kg'])) ?>/kg <a class="link-btn" href="<?= e(url('my/book.php?step=1')) ?>">Change</a></dd></div>
    <div><dt>Drop-off</dt><dd><?= e(date('l, F j', strtotime($draft['date']))) ?> at <?= e(date('g:i A', strtotime('2000-01-01 ' . $draft['time']))) ?> <a class="link-btn" href="<?= e(url('my/book.php?step=2')) ?>">Change</a></dd></div>
    <div><dt>Estimated cost</dt><dd><?= $est() !== null ? 'About ' . e(money($est())) . ' for ' . e(kg($draft['est_weight_kg'])) : 'Based on the weight at drop-off' ?></dd></div>
    <?php if (!empty($draft['notes'])): ?><div><dt>Notes</dt><dd><?= e($draft['notes']) ?></dd></div><?php endif; ?>
  </dl>
  <div class="bring">
    <b>What to bring</b>
    <ul>
      <li>Your laundry, bagged, to <?= e(setting('shop_name', 'the shop')) ?><?= setting('shop_address') ? ', ' . e(setting('shop_address')) : '' ?>.</li>
      <li>The final price is <?= e(money($service['price_per_kg'])) ?> per kg, weighed at the counter.</li>
      <li>Pay in cash or GCash at the shop, at drop-off or at pickup. There is no online payment.</li>
    </ul>
  </div>
  <form method="post" action="<?= e(url('my/book.php?step=4')) ?>" class="form-actions">
    <?= csrf_field() ?>
    <a class="btn btn-ghost" href="<?= e(url('my/book.php?step=3')) ?>">Back</a>
    <button class="btn btn-primary btn-lg" type="submit">Confirm booking</button>
  </form>
<?php endif; ?>
</section>

<aside class="summary book-summary" aria-label="Your booking so far">
  <span class="kicker kicker-light">YOUR BOOKING</span>
  <dl class="aside-rows">
    <div><dt>Service</dt><dd><?= $service ? e($service['name']) : 'Not chosen' ?></dd></div>
    <div><dt>Rate</dt><dd><?= $service ? e(money($service['price_per_kg'])) . '/kg' : 'Not chosen' ?></dd></div>
    <div><dt>Drop-off</dt><dd><?= !empty($draft['time']) ? e(date('M j', strtotime($draft['date'])) . ', ' . date('g:i A', strtotime('2000-01-01 ' . $draft['time']))) : 'Not chosen' ?></dd></div>
    <div><dt>Estimate</dt><dd><?= $est() !== null ? e(money($est())) : 'After weighing' ?></dd></div>
  </dl>
  <p class="summary-note">You pay at the shop after your laundry is weighed.</p>
</aside>
</div>
<?php require __DIR__ . '/../includes/layout/app_bottom.php'; ?>
