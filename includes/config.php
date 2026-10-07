<?php
// Default settings for XAMPP. To change them, copy config.local.example.php
// to config.local.php (git-ignored) and override only what differs.
$config = [
    'db_host'  => '127.0.0.1',
    'db_port'  => 3306,
    'db_name'  => 'laundrytrack',
    'db_user'  => 'root',
    'db_pass'  => '',
    'timezone' => 'Asia/Manila',
    'currency' => '₱',
    // Leave null to detect the folder automatically (e.g. /laundrytrack).
    'base_path' => null,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

return $config;
