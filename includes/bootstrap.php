<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/alerts.php';
require_once __DIR__ . '/layout.php';

/* Hide raw PHP errors from output, log them instead. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    error_log('[Shelfwise] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Server error</title>'
       . '<p style="font-family:sans-serif;padding:40px;max-width:640px;">'
       . 'Something went wrong. The details have been written to the PHP error log.</p>';
});

start_session();