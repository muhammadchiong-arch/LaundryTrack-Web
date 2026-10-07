<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

// Work out the URL folder (e.g. /laundrytrack) from the running script.
if ($config['base_path'] === null) {
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: '');
    $root = str_replace('\\', '/', APP_ROOT);
    $rel = substr($script, strlen($root));
    $name = $_SERVER['SCRIPT_NAME'] ?? '';
    $config['base_path'] = ($rel !== '' && str_ends_with($name, $rel)) ? substr($name, 0, -strlen($rel)) : '';
}
define('BASE_PATH', rtrim($config['base_path'], '/'));

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/orders.php';
require __DIR__ . '/bookings.php';

// Never show PHP errors (with file paths and SQL) to visitors; log them and show a plain page.
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $ex) {
    error_log('LaundryTrack: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $title = 'Something went wrong';
    $message = 'The page could not be finished. Go back and try again. If it keeps happening, tell the shop admin.';
    if (!defined('NO_DB')) {
        define('NO_DB', true);
    }
    require __DIR__ . '/layout/error.php';
});

session_name('laundrytrack');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => (BASE_PATH ?: '') . '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

check_csrf();
