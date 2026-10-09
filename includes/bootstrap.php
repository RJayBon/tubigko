<?php
/**
 * TubigKo — single bootstrap entry point.
 * Works on PHP 7.4 – 8.3 (WAMPP-friendly).
 */
declare(strict_types=1);

if (defined('TUBIGKO_BOOTSTRAPPED')) {
    return;
}
define('TUBIGKO_BOOTSTRAPPED', true);

$GLOBALS['__config'] = require __DIR__ . '/config.php';

date_default_timezone_set($GLOBALS['__config']['app']['timezone']);
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['__config']['app']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

/* -------- Ensure storage directories exist (auto-heal) -------- */
$__storagePaths = $GLOBALS['__config']['paths'];
foreach (['storage', 'logs', 'sessions'] as $key) {
    $dir = $__storagePaths[$key] ?? null;
    if ($dir && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/* -------- Point PHP error_log to our log file (best effort) -------- */
$__logDir = $__storagePaths['logs'] ?? null;
if ($__logDir && is_dir($__logDir) && is_writable($__logDir)) {
    ini_set('error_log', $__logDir . '/php-error.log');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data.php';