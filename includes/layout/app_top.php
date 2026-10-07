<?php
/** @var string $title @var string $active  (nav key) */
$me = current_user();
$activeCount = (int) q_val("SELECT COUNT(*) FROM orders WHERE status <> 'Completed'");
$nav = [];
if ($me['role'] === 'admin') {
    $nav[] = ['dashboard', 'app/dashboard.php', 'Dashboard', 'grid', null];
}
$nav[] = ['new', 'app/order-new.php', 'New order', 'plus', null];
$nav[] = ['orders', 'app/orders.php', 'Orders', 'basket', $activeCount ?: null];
$nav[] = ['customers', 'app/customers.php', 'Customers', 'users', null];
$nav[] = ['transactions', 'app/transactions.php', $me['role'] === 'admin' ? 'Sales' : 'Transactions', 'cash', null];
if ($me['role'] === 'admin') {
    $nav[] = ['staff', 'app/staff.php', 'Staff', 'badge', null];
    $nav[] = ['settings', 'app/settings.php', 'Settings', 'sliders', null];
}
require __DIR__ . '/head.php';
?>
<body class="app">
<a class="skip-link" href="#main">Skip to content</a>
<?php if (!empty($_SESSION['intro'])) { unset($_SESSION['intro']); require __DIR__ . '/../partials/intro.php'; } ?>
<div class="shell">
  <aside class="sidebar" id="sidebar" aria-label="Main">
  <div class="sidebar-inner">
    <div class="sidebar-top">
      <a class="brand" href="<?= e(url(home_for($me))) ?>"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span class="brand-word">Laundry<span>Track</span></span></a>
      <button class="icon-btn sidebar-close" type="button" data-nav-close aria-label="Close menu"><?= icon('x') ?></button>
    </div>
    <span class="role-badge"><?= e($me['role'] === 'admin' ? 'Admin' : 'Staff') ?></span>
    <nav class="nav">
      <?php foreach ($nav as [$key, $href, $label, $ic, $count]): ?>
        <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?> title="<?= e($label) ?>">
          <?= icon($ic) ?><span class="nav-label"><?= e($label) ?></span>
          <?php if ($count): ?><span class="nav-count"><?= (int) $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
      <button class="nav-link collapse-btn" type="button" data-collapse title="Collapse sidebar"><?= icon('collapse') ?><span class="nav-label">Collapse</span></button>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?>
        <button class="nav-link" type="submit" title="Sign out"><?= icon('sign-out') ?><span class="nav-label">Sign out</span></button>
      </form>
    </div>
  </div>
  </aside>
  <div class="scrim" data-nav-close></div>
  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn menu-btn" type="button" data-nav-open aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
      <h1 class="page-title"><?= e($title) ?></h1>
      <form class="search-pill topbar-search" action="<?= e(url('app/orders.php')) ?>" method="get" role="search">
        <?= icon('search') ?>
        <input type="search" name="q" placeholder="Search order no., name or phone" aria-label="Search orders" value="<?= e($active === 'orders' ? input('q') : '') ?>">
      </form>
      <div class="menu-wrap">
        <button class="account-btn" type="button" data-menu aria-expanded="false" aria-haspopup="true" aria-label="Account menu">
          <span class="avatar"><?= e(initials($me['name'])) ?></span>
          <span class="account-text"><b><?= e($me['name']) ?></b><span><?= e($me['role'] === 'admin' ? 'Admin' : 'Staff') ?></span></span>
        </button>
        <div class="popover" role="menu" hidden>
          <div class="popover-head"><b><?= e($me['name']) ?></b><span><?= e($me['email']) ?></span></div>
          <a class="popover-item" role="menuitem" href="<?= e(url('app/account.php')) ?>"><?= icon('key') ?>Change password</a>
          <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?>
            <button class="popover-item" role="menuitem" type="submit"><?= icon('sign-out') ?>Sign out</button>
          </form>
        </div>
      </div>
    </header>
    <main class="content" id="main">
      <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['kind']) ?>" role="status"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
