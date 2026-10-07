<?php
// Public landing page (Claude Design "LaundryTrack Landing v7").
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/phosphor.php';

$me = current_user();
$cust = current_customer();
// Visitors are the shop's customers: the main action is booking a drop-off.
$bookUrl = $cust ? url('my/book.php') : ($me ? url(home_for($me)) : url('register.php'));
$bookLabel = $me ? 'Open workspace' : 'Book laundry';
$signUrl = ($me || $cust) ? url(home_for($me ?? $cust)) : url('login.php');
$shopName = setting('shop_name', 'LaundryTrack');
$shopPhone = setting('shop_phone');
$shopAddress = setting('shop_address');

$stages = [
    ['Received', 'tray-arrow-down', 'Staff logs the bags', 'Logged at the counter, 9:12 AM'],
    ['Washing', 'drop', 'In the machine', 'Started 9:40 AM'],
    ['Drying', 'wind', 'Tumble or line dry', 'Started 10:25 AM'],
    ['Folding', 't-shirt', 'Folded and bagged', 'Started 11:05 AM'],
    ['Ready for Pickup', 'bag', 'Waiting at the counter', 'Ready since 11:30 AM'],
    ['Completed', 'seal-check', 'Claimed and recorded', 'Claimed 4:15 PM'],
];
$heroStage = 3;
$problems = [
    ['notepad', 'Manual order recording', 'Orders live on paper slips that get lost or misread.'],
    ['question', 'Hard to check status', 'Staff have to look through bags to answer one question.'],
    ['phone-call', 'Repeated follow-ups', 'Customers call or message to ask if their laundry is done.'],
    ['files', 'Scattered information', 'Customer details, payments and orders sit in different places.'],
    ['chart-line-down', 'No view of the day', 'Owners cannot easily see what came in or what is still pending.'],
];
$roles = [
    ['user', 'Customer', 'Tracks the order with its order number and sees when it is ready.', 'r-cust'],
    ['storefront', 'Staff / Sales', 'Creates orders at the counter and updates the status as the laundry moves.', 'r-staff'],
    ['chart-bar', 'Admin', 'Monitors orders, customers, transactions and daily activity.', 'r-admin'],
];
$steps = [
    ['Create Order', "Staff records the customer's laundry order."],
    ['Generate Order Number', 'LaundryTrack provides an order number for tracking.'],
    ['Process Laundry', 'Staff updates the laundry status as processing continues.'],
    ['Track Status', 'Customers check their order using the order number.'],
    ['Ready for Pickup', 'Customer knows when the laundry is ready.'],
    ['Completed', 'The order is finalized and recorded.'],
];
$features = [
    ['basket', 'Order Management', 'Organize and manage laundry orders.'],
    ['path', 'Status Tracking', 'Monitor laundry from Received to Completed.'],
    ['magnifying-glass', 'Customer Tracking', 'Give customers a simple way to check their order status.'],
    ['receipt', 'Sales & Transactions', 'Keep organized records of laundry transactions.'],
    ['address-book', 'Customer Management', 'Maintain organized customer information.'],
    ['chart-bar', 'Business Overview', 'Allow administrators to monitor business activity.'],
];
$values = [
    ['folders', 'Better Organization', 'Keep orders and customer information organized.'],
    ['lightning', 'Faster Daily Operations', 'Help staff quickly create and update orders.'],
    ['eye', 'Better Customer Visibility', 'Customers can check their laundry status without repeatedly asking staff.'],
    ['monitor', 'Business Monitoring', 'Administrators can view orders, customers, transactions, and business activity.'],
];
$nav = [['Home', '#home', 'home'], ['Features', '#features', 'features'], ['How It Works', '#how', 'how'], ['For Businesses', '#business', 'business']];

$title = 'Laundry management and order tracking';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FAF7F2">
<meta name="description" content="Manage laundry and track every order. Orders, statuses, payments and customer tracking in one simple system.">
<title><?= e($shopName) ?> · Manage laundry. Track every order.</title>
<link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=3">
<link rel="stylesheet" href="<?= e(url('assets/css/landing.css')) ?>?v=2">
<script>if(!matchMedia('(prefers-reduced-motion: reduce)').matches&&'IntersectionObserver'in window)document.documentElement.classList.add('js-reveal')</script>
<script src="<?= e(url('assets/js/landing.js')) ?>?v=2" defer></script>
<script src="<?= e(url('assets/js/motion.js')) ?>?v=1" defer></script>
</head>
<body class="landing">
<a class="skip-link" href="#main">Skip to content</a>
<header class="l-nav" data-l-nav>
  <div class="l-nav-in">
    <a class="l-brand" href="#home"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span>Laundry<span>Track</span></span></a>
    <nav class="l-links" aria-label="Main">
      <?php foreach ($nav as [$label, $href, $key]): ?>
        <a href="<?= $href ?>" data-nav-link="<?= $key ?>"><?= e($label) ?><span class="l-underline" aria-hidden="true"></span></a>
      <?php endforeach; ?>
      <a href="<?= e(url('track.php')) ?>">Track Order<span class="l-underline" aria-hidden="true"></span></a>
    </nav>
    <a class="l-signin" href="<?= e($signUrl) ?>"><?= ($me || $cust) ? 'My account' : 'Sign in' ?></a>
    <a class="l-btn l-btn-dark" href="<?= e($bookUrl) ?>"><?= e($bookLabel) ?></a>
    <button class="l-menu-btn" type="button" aria-label="Menu" aria-expanded="false" aria-controls="l-menu" data-l-menu><span class="l-burger" aria-hidden="true"><span></span><span></span></span></button>
  </div>
  <nav class="l-mobile" id="l-menu" aria-label="Mobile" hidden>
    <?php foreach ($nav as [$label, $href, $key]): ?>
      <a href="<?= $href ?>" data-nav-link="<?= $key ?>"><?= e($label) ?><?= ph('caret-right') ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('track.php')) ?>">Track Order<?= ph('caret-right') ?></a>
    <a href="<?= e($signUrl) ?>"><?= ($me || $cust) ? 'My account' : 'Sign in' ?><?= ph('caret-right') ?></a>
  </nav>
</header>

<main id="main">
  <section class="l-hero l-wrap" id="home" data-section="home">
    <div class="l-hero-copy" data-reveal>
      <h1>Book your laundry. <span>Track every order.</span></h1>
      <p>Pick a drop-off time online, then follow your laundry from Received to Ready for Pickup.</p>
      <div class="l-ctas">
        <a class="l-btn l-btn-primary l-btn-lg" href="<?= e($bookUrl) ?>"><?= e($bookLabel) ?><?= ph('arrow-right') ?></a>
        <a class="l-btn l-btn-ghost l-btn-lg" href="<?= e(url('track.php')) ?>">Track an order</a>
      </div>
    </div>
    <div class="l-hero-visual" data-reveal="2">
      <?php foreach ([[10, 8, 3.25, .95], [78, 4, 2, .85], [86, 40, 1.25, .8], [4, 46, 1.5, .75], [64, 74, 2.5, .9]] as $i => [$x, $y, $s, $o]): ?>
        <span class="l-bubble" data-bubble style="left:<?= $x ?>%;top:<?= $y ?>%;width:<?= $s ?>rem;height:<?= $s ?>rem;opacity:<?= $o ?>" aria-hidden="true"></span>
      <?php endforeach; ?>
      <div class="l-drum"><?php $bare = true; require __DIR__ . '/includes/partials/washer.php'; ?></div>
      <div class="l-status-card" role="status" aria-live="polite" aria-label="Example order status">
        <div class="l-sc-top"><b>Order LAU-1052</b><span>Wash &amp; Fold, 6 kg</span></div>
        <div class="l-sc-mid">
          <span class="l-lm l-lm-card" data-hero-mark><?php $lmState = 'processing'; $lmClass = ''; require __DIR__ . '/includes/partials/logo_motion.php'; ?></span>
          <div class="l-sc-text" data-hero-text><b data-hero-status><?= e($stages[$heroStage][0]) ?></b><span data-hero-note><?= e($stages[$heroStage][3]) ?></span></div>
        </div>
        <div class="l-sc-bars" aria-hidden="true">
          <?php foreach ($stages as $i => $s): ?><span class="<?= $i < $heroStage ? 'done' : ($i === $heroStage ? 'cur' : '') ?>"></span><?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="l-band-white">
    <div class="l-wrap l-split">
      <div class="l-stack" data-reveal>
        <h2 class="l-h2">Laundry orders should not be difficult to track.</h2>
        <p class="l-lead">Many shops still run on handwritten slips and phone calls. That works until the day gets busy.</p>
      </div>
      <ul class="l-problems">
        <?php foreach ($problems as [$ic, $t, $d]): ?>
          <li data-reveal><span class="l-prob-ic"><?= ph($ic) ?></span><span><b><?= e($t) ?></b><span><?= e($d) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="l-wrap l-sec l-col" id="features" data-section="features">
    <h2 class="l-h2 l-max40" data-reveal>One simple system for the entire laundry workflow.</h2>
    <div class="l-roles" data-reveal>
      <span class="l-roles-line" aria-hidden="true"></span>
      <?php foreach ($roles as [$ic, $name, $d, $cls]): ?>
        <div class="l-role"><span class="l-role-ic <?= $cls ?>"><?= ph($ic) ?></span><b><?= e($name) ?></b><span><?= e($d) ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="l-pill-note" data-reveal>Same orders, same statuses. Each person sees the part they need.</p>
  </section>

  <section class="l-band-white" id="how" data-section="how">
    <div class="l-wrap l-sec l-col">
      <h2 class="l-h2" data-reveal>How it works</h2>
      <ol class="l-steps">
        <?php foreach ($steps as $i => [$t, $d]): ?>
          <li data-reveal<?= $i === 5 ? ' class="last"' : '' ?>><span class="l-step-n">0<?= $i + 1 ?></span><b><?= e($t) ?></b><span><?= e($d) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <div class="l-curve" aria-hidden="true"><svg viewBox="0 0 1440 64" preserveAspectRatio="none"><path d="M0 64C360 4 1080 4 1440 64Z"/></svg></div>
  <section class="l-status">
    <div class="l-wrap l-sec l-col">
      <div class="l-stack l-max38" data-reveal>
        <h2 class="l-h2">Six statuses. Everyone reads the same one.</h2>
        <p class="l-lead">Staff move the order forward. The customer's tracking page updates with it.</p>
      </div>
      <ol class="l-statuses" data-reveal data-statuses>
        <?php foreach ($stages as $i => [$label, $ic, $d]): ?>
          <li class="<?= $i < $heroStage ? 'done' : ($i === $heroStage ? 'cur' : '') ?>"><span class="l-st-ic"><?= ph($ic) ?></span><span class="l-st-txt"><b><?= e($label) ?></b><span><?= e($d) ?></span></span></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>
  <div class="l-curve l-curve-bottom" aria-hidden="true"><svg viewBox="0 0 1440 64" preserveAspectRatio="none"><path d="M0 0H1440C1080 60 360 60 0 0Z"/></svg></div>

  <section class="l-band-white">
    <div class="l-wrap l-sec l-col">
      <h2 class="l-h2" data-reveal>What's inside</h2>
      <div class="l-features">
        <?php foreach ($features as [$ic, $t, $d]): ?>
          <div class="l-feature" data-reveal><?= ph($ic) ?><span><b><?= e($t) ?></b><span><?= e($d) ?></span></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="l-wrap l-sec l-split l-business" id="business" data-section="business">
    <div class="l-stack l-sticky" data-reveal>
      <h2 class="l-h2">Built for the way laundry businesses work.</h2>
      <span class="l-lm l-lm-big"><?php $lmState = 'done'; $lmClass = ''; require __DIR__ . '/includes/partials/logo_motion.php'; ?></span>
    </div>
    <div class="l-values">
      <?php foreach ($values as [$ic, $t, $d]): ?>
        <div class="l-value" data-reveal><span class="l-val-ic"><?= ph($ic) ?></span><span><b><?= e($t) ?></b><span><?= e($d) ?></span></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="l-cta-wrap" id="contact">
    <div class="l-cta" data-reveal>
      <div class="l-stack">
        <h2>Make every laundry order easier to track.</h2>
        <p>LaundryTrack connects customers, staff, and administrators in one simple laundry management system.</p>
        <a class="l-btn l-btn-peri l-btn-lg" href="<?= e($bookUrl) ?>"><?= e($bookLabel) ?><?= ph('arrow-right') ?></a>
      </div>
      <span class="l-lm l-lm-cta" aria-hidden="true"><?php $lmState = 'processing'; $lmClass = ''; require __DIR__ . '/includes/partials/logo_motion.php'; ?></span>
    </div>
  </section>
</main>

<footer class="l-footer">
  <span class="l-foot-brand"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><b><?= e($shopName) ?></b></span>
  <span class="l-foot-links">
    <?php if ($shopPhone): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $shopPhone)) ?>"><?= e($shopPhone) ?></a><?php endif; ?>
    <a href="#how">How It Works</a>
    <a href="<?= e(url('track.php')) ?>">Track Order</a>
    <a href="<?= e(url('login.php')) ?>">Sign in</a>
  </span>
  <span>&copy; <?= date('Y') ?> <?= e($shopName) ?><?= $shopAddress ? ' · ' . e($shopAddress) : '' ?></span>
</footer>
</body>
</html>
