<?php
/**
 * Six-stage progress bar. @var array $order (status) @var array $stamps status => datetime
 */
$cur = status_index($order['status']);
?>
<ol class="progress" aria-label="Order progress">
  <?php foreach (STATUSES as $i => $s):
      $state = $i < $cur ? 'done' : ($i === $cur ? 'current' : 'todo');
      if ($order['status'] === 'Completed') $state = 'done';
  ?>
    <li class="progress-step <?= $state ?>"<?= $i === $cur ? ' aria-current="step"' : '' ?>>
      <span class="bar"></span>
      <b><?= e($s) ?></b>
      <span class="time"><?= isset($stamps[$s]) && $i <= $cur ? e(fmt_when($stamps[$s])) : ($state === 'todo' ? 'Not yet' : '') ?></span>
    </li>
  <?php endforeach; ?>
</ol>
