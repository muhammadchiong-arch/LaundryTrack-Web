<?php
// Public landing page (Claude Design "LaundryTrack Landing v7").
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/phosphor.php';

$me = current_user();
$startUrl = $me ? url(home_for($me)) : url('login.php');
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

// Washer drum ring: the design's gradient arc, drawn in short segments.
function drum_ring(): string
{
    $cx = 1376; $cy = 813.5; $r = 289;
    $p = fn ($a) => [$cx + $r * cos(deg2rad($a)), $cy + $r * sin(deg2rad($a))];
    $stops = [[-92, '#A0FBF6'], [-60, '#8DF4F2'], [-30, '#78E5ED'], [0, '#5DCDED'], [30, '#4FB0EF'], [60, '#4591F0'], [90, '#4784F4'], [120, '#4B8BF3'], [150, '#5AB0EC'], [170, '#65CBEC'], [182, '#72DBF0']];
    $col = function ($a) use ($stops) {
        $i = 0;
        while ($i < count($stops) - 2 && $a > $stops[$i + 1][0]) $i++;
        [$a0, $c0] = $stops[$i]; [$a1, $c1] = $stops[$i + 1];
        $t = min(1, max(0, ($a - $a0) / ($a1 - $a0)));
        $rgb = [];
        for ($k = 0; $k < 3; $k++) {
            $v0 = hexdec(substr($c0, 1 + 2 * $k, 2)); $v1 = hexdec(substr($c1, 1 + 2 * $k, 2));
            $rgb[] = (int) round($v0 + ($v1 - $v0) * $t);
        }
        return 'rgb(' . implode(',', $rgb) . ')';
    };
    $out = '';
    for ($a = -92; $a < 182; $a += 4) {
        $b = min(182, $a + 4.6);
        [$x1, $y1] = $p($a); [$x2, $y2] = $p($b);
        $out .= sprintf('<path d="M%.2f %.2fA%d %d 0 0 1 %.2f %.2f" stroke="%s"/>', $x1, $y1, $r, $r, $x2, $y2, $col($a + 2));
    }
    [$ax, $ay] = $p(-92); [$bx, $by] = $p(182);
    return $out . sprintf('<circle cx="%.2f" cy="%.2f" r="18" fill="#A0FBF6"/><circle cx="%.2f" cy="%.2f" r="18" fill="#72DBF0"/>', $ax, $ay, $bx, $by);
}

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
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=2">
<link rel="stylesheet" href="<?= e(url('assets/css/landing.css')) ?>?v=1">
<script>if(!matchMedia('(prefers-reduced-motion: reduce)').matches&&'IntersectionObserver'in window)document.documentElement.classList.add('js-reveal')</script>
<script src="<?= e(url('assets/js/landing.js')) ?>?v=1" defer></script>
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
    <a class="l-btn l-btn-dark" href="<?= e($startUrl) ?>">Get Started</a>
    <button class="l-menu-btn" type="button" aria-label="Menu" aria-expanded="false" aria-controls="l-menu" data-l-menu><?= ph('list', 'ph i-open') ?><?= ph('x', 'ph i-close') ?></button>
  </div>
  <nav class="l-mobile" id="l-menu" aria-label="Mobile" hidden>
    <?php foreach ($nav as [$label, $href, $key]): ?>
      <a href="<?= $href ?>" data-nav-link="<?= $key ?>"><?= e($label) ?><?= ph('caret-right') ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('track.php')) ?>">Track Order<?= ph('caret-right') ?></a>
  </nav>
</header>

<main id="main">
  <section class="l-hero l-wrap" id="home" data-section="home">
    <div class="l-hero-copy" data-reveal>
      <h1>Manage Laundry. <span>Track Every Order.</span></h1>
      <p>A simple web-based platform that helps laundry businesses organize orders, monitor laundry progress, and keep customers informed.</p>
      <div class="l-ctas">
        <a class="l-btn l-btn-primary l-btn-lg" href="<?= e($startUrl) ?>">Get Started<?= ph('arrow-right') ?></a>
        <a class="l-btn l-btn-ghost l-btn-lg" href="#how">See How It Works</a>
      </div>
    </div>
    <div class="l-hero-visual" data-reveal="2">
      <?php foreach ([[10, 8, 3.25, .95], [78, 4, 2, .85], [86, 40, 1.25, .8], [4, 46, 1.5, .75], [64, 74, 2.5, .9]] as $i => [$x, $y, $s, $o]): ?>
        <span class="l-bubble" data-bubble style="left:<?= $x ?>%;top:<?= $y ?>%;width:<?= $s ?>rem;height:<?= $s ?>rem;opacity:<?= $o ?>" aria-hidden="true"></span>
      <?php endforeach; ?>
      <div class="l-drum">
        <svg viewBox="1040 477 672 672" role="img" aria-label="Laundry is being processed" data-drum>
          <defs>
            <linearGradient id="dNavy" gradientUnits="userSpaceOnUse" x1="1239" y1="519" x2="1513" y2="1108"><stop offset="0" stop-color="#425D8E"/><stop offset=".12" stop-color="#3F5A8A"/><stop offset=".35" stop-color="#24386A"/><stop offset="1" stop-color="#1A2A56"/></linearGradient>
            <linearGradient id="dGroove" gradientUnits="userSpaceOnUse" x1="1239" y1="519" x2="1513" y2="1108"><stop offset="0" stop-color="#5A7EB1"/><stop offset=".12" stop-color="#557DAE"/><stop offset=".35" stop-color="#37578F"/><stop offset="1" stop-color="#2A4A86"/></linearGradient>
            <radialGradient id="dGlass" cx=".38" cy=".32" r=".8"><stop offset="0" stop-color="#2E4A80"/><stop offset=".6" stop-color="#1F2F5E"/><stop offset="1" stop-color="#18234A"/></radialGradient>
            <clipPath id="dBub"><circle cx="1376" cy="813.5" r="196"/></clipPath>
          </defs>
          <g transform="translate(1376 813.5)"><g>
            <animateTransform attributeName="transform" type="scale" values="1;1.008;1" keyTimes="0;.5;1" calcMode="spline" keySplines=".45 0 .55 1;.45 0 .55 1" dur="4.2s" repeatCount="indefinite"/>
            <g transform="translate(-1376 -813.5)">
            <circle cx="1376" cy="813.5" r="324" fill="url(#dNavy)"/>
            <circle cx="1376" cy="813.5" r="289" fill="none" stroke="url(#dGroove)" stroke-width="36"/>
            <g fill="none" stroke-width="36">
              <animateTransform attributeName="transform" type="rotate" from="0 1376 813.5" to="360 1376 813.5" dur="4.2s" repeatCount="indefinite"/>
              <?= drum_ring() ?>
            </g>
            <circle cx="1376" cy="813.5" r="244.5" fill="#1D2A55"/>
            <circle cx="1376" cy="813.5" r="202" fill="url(#dGlass)"/>
            <g clip-path="url(#dBub)">
              <?php foreach ([[1290, 9, 4.6, -0.5, 10], [1420, 13, 5.4, -2.2, -14], [1350, 7, 3.8, -3.1, 6], [1470, 8, 4.2, -1.4, -8], [1240, 11, 5.0, -3.9, 12]] as [$bx, $br, $bd, $bb, $bdx]): ?>
                <g opacity="0">
                  <animateTransform attributeName="transform" type="translate" values="0 0;<?= $bdx ?> -300" dur="<?= $bd ?>s" begin="<?= $bb ?>s" repeatCount="indefinite" calcMode="spline" keyTimes="0;1" keySplines=".3 0 .6 1"/>
                  <animate attributeName="opacity" values="0;.95;.95;0" keyTimes="0;.18;.7;1" dur="<?= $bd ?>s" begin="<?= $bb ?>s" repeatCount="indefinite"/>
                  <circle cx="<?= $bx ?>" cy="960" r="<?= $br ?>" fill="rgba(160,205,255,.22)" stroke="rgba(215,236,255,.75)" stroke-width="2.5"/>
                  <circle cx="<?= $bx - $br * .35 ?>" cy="<?= 960 - $br * .35 ?>" r="<?= $br * .28 ?>" fill="rgba(255,255,255,.85)"/>
                </g>
              <?php endforeach; ?>
            </g>
            </g>
          </g></g>
        </svg>
      </div>
      <div class="l-status-card" role="status" aria-live="polite" aria-label="Example order status">
        <div class="l-sc-top"><b>Order LAU-1052</b><span>Wash &amp; Fold, 6 kg</span></div>
        <div class="l-sc-mid">
          <span class="l-lm" data-lm="processing"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><svg class="l-lm-ring" viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22.5"/></svg><span class="l-lm-check" aria-hidden="true"><?= icon('check') ?></span></span>
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
      <span class="l-lm l-lm-big" data-lm="done"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span class="l-lm-check" aria-hidden="true"><?= icon('check') ?></span></span>
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
        <a class="l-btn l-btn-peri l-btn-lg" href="<?= e($startUrl) ?>">Get Started<?= ph('arrow-right') ?></a>
      </div>
      <span class="l-lm l-lm-cta" data-lm="processing" aria-hidden="true"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><svg class="l-lm-ring" viewBox="0 0 48 48"><circle cx="24" cy="24" r="22.5"/></svg></span>
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
