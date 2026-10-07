<?php
require __DIR__ . '/includes/bootstrap.php';

// Sign-out is POST-only (with the CSRF check in bootstrap) so links on other sites can't sign you out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout_user();
    session_start();
    flash('You are signed out.');
}
redirect('login.php');
