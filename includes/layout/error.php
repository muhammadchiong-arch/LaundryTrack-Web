<?php
/** @var string $title  @var string $message */
if (!defined('NO_DB')) {
    define('NO_DB', true); // the error page must not need the database
}
$back = $back ?? url('');
require __DIR__ . '/head.php';
?>
<body class="public">
<main class="error-page">
  <div class="error-card">
    <a class="brand brand-dark" href="<?= e(url('')) ?>"><img src="<?= e(url('assets/img/logo.svg')) ?>" alt=""><span>Laundry<span>Track</span></span></a>
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e($back) ?>">Go back</a>
  </div>
</main>
</body>
</html>
