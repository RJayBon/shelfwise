<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use POST.');
}
csrf_check();
logout();

flash('info', 'You have been signed out.');
redirect('login.php');