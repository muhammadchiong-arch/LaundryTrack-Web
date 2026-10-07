<?php
require __DIR__ . '/../includes/bootstrap.php';
redirect(home_for(require_login()));
