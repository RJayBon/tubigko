<?php
/**
 * TubigKo — authentication, sessions, RBAC, CSRF, rate limiting.
 * PHP 7.4 – 8.3 compatible.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $cfg = $GLOBALS['__config']['security'] ?? [];

    /* Try to use storage/sessions; fall back to system default if not writable. */
    $sessionPath = $GLOBALS['__config']['paths']['sessions']
        ?? (dirname(__DIR__) . '/storage/sessions');

    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0775, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
           || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_name($cfg['session_name'] ?? 'tubigko_sid');

    /* setcookie params — array form works on PHP 7.3+ */
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    $lifetime = (int)($cfg['session_lifetime'] ?? 7200);
    if (!isset($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif (time() - $_SESSION['__created'] > $lifetime) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user(): ?array
{
    static $cached = null, $loaded = false;
    if ($loaded) return $cached;
    $loaded = true;
    if (!is_logged_in()) return null;

    $cached = db_one(
        'SELECT id, full_name, username, email, phone, address, barangay, landmark, role, status
         FROM users WHERE id = ?',
        [$_SESSION['user_id']]
    );
    return $cached;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['__created'] = time();
    unset($_SESSION['csrf_token']);
}

function logout_user(): void
{
    $flash = $_SESSION['flash'] ?? [];
    $_SESSION = [];
    session_regenerate_id(true);
    if ($flash) {
        $_SESSION['flash'] = $flash;
    }
}

function require_login(?string $role = null): void
{
    $base = base_path();

    if (!is_logged_in()) {
        flash_set('error', 'Please sign in to continue.');
        header('Location: ' . $base . '/login.php');
        exit;
    }

    if ($role !== null && current_role() !== $role) {
        flash_set('error', 'You are not authorized to view that page.');
        header('Location: ' . $base . '/' . (current_role() === 'admin'
            ? 'admin/dashboard.php' : 'customer/gallons.php'));
        exit;
    }

    $user = current_user();
    if (!$user || $user['status'] !== 'active') {
        logout_user();
        flash_set('error', 'Your account is inactive. Please contact the station.');
        header('Location: ' . $base . '/login.php');
        exit;
    }
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function flash_all(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function login_is_locked(string $identifier): bool
{
    $cfg    = $GLOBALS['__config']['security'];
    $max    = (int)($cfg['login_max_attempts'] ?? 5);
    $window = (int)($cfg['login_window'] ?? 900);
    $ip     = client_ip();
    $key    = strtolower($identifier);

    $row = db_one(
        'SELECT COUNT(*) AS c FROM login_attempts
         WHERE (identifier = ? OR ip = ?) AND attempted_at > (NOW() - INTERVAL ? SECOND)',
        [$key, $ip, $window]
    );
    return $row && (int)$row['c'] >= $max;
}

function login_record_failure(string $identifier): void
{
    db_exec(
        'INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)',
        [strtolower($identifier), client_ip()],
        $err
    );
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)', [], $e);
    }
}

function login_clear_failures(string $identifier): void
{
    db_exec(
        'DELETE FROM login_attempts WHERE identifier = ? OR ip = ?',
        [strtolower($identifier), client_ip()],
        $err
    );
}