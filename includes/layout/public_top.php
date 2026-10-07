<?php
/**
 * Split card used by the public pages (track, sign in, setup): navy story panel + content.
 * @var string $title @var string $panelTitle (HTML allowed) @var string $panelText
 */
$panelStep = $panelStep ?? 0;
require __DIR__ . '/head.php';
?>
<body class="public">
<main class="split-wrap">
  <div class="split-card">
    <section class="split-panel">
      <a class="brand" href="<?= e(url('')) ?>"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span>Laundry<span>Track</span></span></a>
      <div class="split-washer" aria-hidden="true"><?php $bare = true; require __DIR__ . '/../partials/washer.php'; ?></div>
      <div class="split-panel-body">
        <h1 class="display"><?= $panelTitle ?></h1>
        <p><?= e($panelText) ?></p>
        <ol class="step-strip" aria-label="Order steps" style="--fill: <?= round($panelStep / 5 * 83.33, 2) ?>%">
          <?php foreach (STATUSES as $i => $s): ?>
            <li class="<?= $i <= $panelStep ? 'on' : '' ?>"><span class="dot"></span><span><?= e($s) ?></span></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>
    <section class="split-main">
      <div class="split-inner">
        <?php foreach (take_flashes() as $f): ?>
          <div class="alert alert-<?= e($f['kind']) ?>" role="status"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
