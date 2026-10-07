<?php
/**
 * Shown once right after sign-in (Web v2 loading screen). Hidden until motion.js plays it,
 * so without JavaScript the page simply appears. A tap or any key skips it.
 * @var array $me
 */
$firstName = explode(' ', trim($me['name']))[0];
?>
<div class="intro" data-intro-screen role="status" aria-live="polite" hidden>
  <div class="intro-icon" data-intro="icon"><?php $lmState = 'processing'; $lmClass = ''; require __DIR__ . '/logo_motion.php'; ?></div>
  <div class="intro-text">
    <span class="intro-word" data-intro="word"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span>Laundry<span>Track</span></span></span>
    <span class="intro-line" data-intro="line">Hello, <?= e($firstName) ?></span>
    <span class="intro-sub" data-intro="sub"><?= $me['role'] === 'admin' ? "Loading today's orders" : 'Loading the order list' ?></span>
  </div>
  <div class="intro-bar" data-intro="bar" aria-hidden="true"><div data-intro="fill"></div></div>
</div>
