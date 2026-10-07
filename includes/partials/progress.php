<?php
/**
 * Six-stage progress bar. @var array $order (status) @var array $stamps status => datetime
 * A cancelled order shows how far it got, greyed out.
 */
$cancelled = $order['status'] === 'Cancelled';
$cur = status_index($order['status']);
if ($cancelled) {
    $cur = -1;
    foreach (STATUSES as $i => $s) if (isset($stamps[$s])) $cur = $i;
}
?>
<ol class="progress<?= $cancelled ? ' is-cancelled' : '' ?>" aria-label="Order progress">
  <?php foreach (STATUSES as $i => $s):
      $state = $i < $cur ? 'done' : ($i === $cur ? 'current' : 'todo');
      if ($order['status'] === 'Completed' || ($cancelled && $i <= $cur)) $state = 'done';
  ?>
    <li class="progress-step <?= $state ?>"<?= !$cancelled && $i === $cur ? ' aria-current="step"' : '' ?>>
      <span class="bar"></span>
      <b><?= e($s) ?></b>
      <span class="time"><?= isset($stamps[$s]) && $i <= $cur ? e(fmt_when($stamps[$s])) : ($state === 'todo' ? ($cancelled ? '' : 'Not yet') : '') ?></span>
    </li>
  <?php endforeach; ?>
</ol>
