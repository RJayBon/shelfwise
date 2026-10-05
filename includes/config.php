<?php
declare(strict_types=1);

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'db' => [
                'host'    => getenv('DB_HOST') ?: '127.0.0.1',
                'name'    => getenv('DB_NAME') ?: 'shelfwise_db',
                'user'    => getenv('DB_USER') ?: 'root',
                'pass'    => getenv('DB_PASS') ?: '',
                'charset' => 'utf8mb4',
            ],
            'app' => [
                'session_name'         => 'shelfwise_sid',
                'base_path'            => rtrim(getenv('APP_BASE_PATH') ?: '/shelfwise', '/'),
                'login_max_attempts'   => 10,
                'login_window_secs'    => 900,
                'session_idle_timeout' => 7200,
            ],
        ];
    }
    return $cfg;
}