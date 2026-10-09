<?php
/**
 * TubigKo — live notifications feed (JSON).
 * GET /api/notifications.php?since_id=N[&mark_read=1]
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

$me = current_user();
if (!$me || $me['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'inactive']);
    exit;
}

$userId   = (int)$me['id'];
$isAdmin  = ($me['role'] === 'admin');
$sinceId  = isset($_GET['since_id'])  ? max(0, (int)$_GET['since_id']) : 0;
$markRead = isset($_GET['mark_read']) && $_GET['mark_read'] === '1';

if ($isAdmin) {
    $rows = db_query(
        'SELECT id, notif_code, title, message, type, created_at
         FROM notifications
         WHERE id > ?
         ORDER BY id ASC
         LIMIT 20',
        [$sinceId]
    );
    $activeIds = db_query('SELECT id FROM notifications ORDER BY id DESC LIMIT 200');
} else {
    $rows = db_query(
        'SELECT id, notif_code, title, message, type, created_at
         FROM notifications
         WHERE (user_id = ? OR user_id IS NULL) AND id > ?
         ORDER BY id ASC
         LIMIT 20',
        [$userId, $sinceId]
    );
    $activeIds = db_query(
        'SELECT id FROM notifications
         WHERE user_id = ? OR user_id IS NULL
         ORDER BY id DESC LIMIT 200',
        [$userId]
    );
}

$items = array_map(function ($n) {
    return [
        'db_id'   => (int)$n['id'],
        'id'      => $n['notif_code'],
        'title'   => $n['title'],
        'message' => $n['message'],
        'type'    => $n['type'],
        'date'    => $n['created_at'],
    ];
}, $rows);

$activeIdList = array_map(function ($r) { return (int)$r['id']; }, $activeIds);

$cursor = $sinceId;
foreach ($items as $it) {
    if ($it['db_id'] > $cursor) $cursor = $it['db_id'];
}

if ($markRead) {
    if ($isAdmin) {
        admin_mark_notifications_seen($userId, $cursor);
    } else {
        mark_notifications_read($userId);
    }
}

$unread = $isAdmin
    ? admin_unread_count()
    : count_unread_notifications($userId);

echo json_encode([
    'ok'          => true,
    'unread'      => $unread,
    'items'       => $items,
    'cursor'      => $cursor,
    'active_ids'  => $activeIdList,
    'is_admin'    => $isAdmin,
    'server_time' => date('c'),
]);