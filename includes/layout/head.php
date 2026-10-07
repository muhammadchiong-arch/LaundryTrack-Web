<?php /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FAF7F2">
<title><?= e($title) ?> · <?= e(defined('NO_DB') ? 'LaundryTrack' : setting('shop_name', 'LaundryTrack')) ?></title>
<link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
<script>try{if(localStorage.getItem('lt-side')==='1')document.documentElement.classList.add('side-collapsed')}catch(e){}</script>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=3">
<script src="<?= e(url('assets/js/app.js')) ?>?v=3" defer></script>
<script src="<?= e(url('assets/js/motion.js')) ?>?v=2" defer></script>
</head>
