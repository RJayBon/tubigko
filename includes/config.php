<?php
/**
 * TubigKo — central configuration.
 *
 * Works on WAMPP with PHP 7.4 – 8.3.
 * Local detection is broad: localhost, 127.0.0.1, ::1, *.local, *.test,
 * or forced via environment variable TUBIGKO_LOCAL=1.
 */
declare(strict_types=1);

/* -------- 1) Detect localhost (PHP 7.4-compatible) -------- */
$forceLocal = (getenv('TUBIGKO_LOCAL') === '1');

$host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
$isLocal = $forceLocal
    || $host === 'localhost'
    || $host === '127.0.0.1'
    || $host === '::1'
    || strpos($host, 'localhost:') === 0
    || strpos($host, '127.0.0.1:') === 0
    || strpos($host, '[::1]:') === 0
    || substr($host, -6) === '.local'
    || substr($host, -5) === '.test';

/* -------- 2) Return config -------- */
return [
    'db' => $isLocal
        ? [
            // ----- WAMPP / localhost -----
            'host' => '127.0.0.1',
            'port' => '3306',
            'name' => 'tubigko_db',
            'user' => 'root',
            'pass' => '',
        ]
        : [
            // ----- InfinityFree (production) -----
            'host' => 'sql207.infinityfree.com',
            'port' => '3306',
            'name' => 'if0_42804348_tubigko_db',
            'user' => 'if0_42804348',
            'pass' => 'rjayon43',
        ],

    'app' => [
        'name'     => 'TubigKo Water Refilling Station System',
        'env'      => $isLocal ? 'local' : 'production',
        'debug'    => $isLocal,
        'timezone' => 'Asia/Manila',
    ],

    'security' => [
        'session_name'       => 'tubigko_sid',
        'session_lifetime'   => 7200,
        'login_max_attempts' => 5,
        'login_window'       => 900,
    ],

    'paths' => [
        'storage'  => dirname(__DIR__) . '/storage',
        'logs'     => dirname(__DIR__) . '/storage/logs',
        'sessions' => dirname(__DIR__) . '/storage/sessions',
    ],
];