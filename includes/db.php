<?php
/**
 * TubigKo — centralized PDO connection + query helpers.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = $GLOBALS['__config']['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['host'], $cfg['port'], $cfg['name']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (function_exists('app_log')) {
            app_log('error', 'DB connection failed: ' . $e->getMessage());
        }
        http_response_code(500);

        $debug = !empty($GLOBALS['__config']['app']['debug']);
        if ($debug) {
            $msg = 'DB error: ' . $e->getMessage();
            $msg .= '<br>Host: ' . htmlspecialchars((string)$cfg['host'], ENT_QUOTES, 'UTF-8');
            $msg .= '<br>Database: ' . htmlspecialchars((string)$cfg['name'], ENT_QUOTES, 'UTF-8');
            $msg .= '<br>User: ' . htmlspecialchars((string)$cfg['user'], ENT_QUOTES, 'UTF-8');
            $msg .= '<br><br><strong>Have you imported database/schema.sql into phpMyAdmin?</strong>';
            die($msg);
        }
        die('The system is temporarily unavailable. Please try again in a moment.');
    }

    return $pdo;
}

function db_query(string $sql, array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        app_log('error', 'Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return [];
    }
}

function db_one(string $sql, array $params = []): ?array
{
    $rows = db_query($sql, $params);
    return $rows[0] ?? null;
}

function db_exec(string $sql, array $params, ?string &$error = null): bool
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        app_log('error', 'Exec failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        $error = 'Database operation failed: ' . $e->getMessage();
        return false;
    }
}

function db_exec_or_fail(string $sql, array $params = []): void
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}