<?php
/**
 * TubigKo — shared helper functions (no DB, no session side effects).
 * PHP 7.4 – 8.3 compatible.
 */
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function peso($n): string
{
    return '&#8369;' . number_format((float)$n, 2);
}

function peso_plain($n): string
{
    return 'PHP ' . number_format((float)$n, 2);
}

function badge_class($status): string
{
    $s = strtolower((string)$status);
    if (in_array($s, ['paid', 'delivered', 'active', 'enabled', 'received by customer'], true)) {
        return 'badge badge--ok';
    }
    if (in_array($s, ['pending', 'ongoing', 'confirmed', 'preparing', 'out for delivery'], true)) {
        return 'badge badge--warn';
    }
    if (in_array($s, ['failed', 'inactive', 'disabled', 'cancelled', 'refunded'], true)) {
        return 'badge badge--bad';
    }
    return 'badge';
}

function delivery_stage_label(string $status): string
{
    switch ($status) {
        case 'pending':          return 'Preparing';
        case 'confirmed':        return 'Confirmed';
        case 'out_for_delivery': return 'Out for delivery';
        case 'delivered':        return 'Received by customer';
        case 'cancelled':        return 'Cancelled';
        default:                 return ucfirst($status);
    }
}

function delivery_group_label(string $status): string
{
    switch ($status) {
        case 'delivered': return 'Delivered';
        case 'cancelled': return 'Cancelled';
        default:          return 'Ongoing';
    }
}

function initials_for(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'U';
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    return strtoupper(substr($parts[0], 0, 2));
}

function base_path(): string
{
    $dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return in_array($dir, ['admin', 'customer', 'api'], true) ? '..' : '.';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function app_log(string $level, string $message): void
{
    $line = sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), strtoupper($level), $message);
    error_log($line);
}