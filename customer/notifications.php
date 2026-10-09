<?php
$BASE = '..'; $ROLE = 'customer'; $PAGE_TITLE = 'Notification';
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('customer');

$me = current_user();

$mine        = load_notifications((int)$me['id']);
$unreadCount = count(array_filter($mine, function ($n) { return !$n['read']; }));
$maxId       = 0;
foreach ($mine as $n) { if ($n['db_id'] > $maxId) $maxId = $n['db_id']; }

if ($unreadCount > 0) {
    mark_notifications_read((int)$me['id']);
}

include __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <div class="card__head">
    <h3>View Notification</h3>
    <div class="spacer toolbar no-print" style="gap:.6rem">
      <span class="badge badge--warn">
        <span data-notif-unread><?= (int)$unreadCount ?></span> unread
      </span>
    </div>
  </div>
  <div data-notif-list data-notif-max-id="<?= (int)$maxId ?>">
    <?php if (!$mine): ?><p class="empty">You have no notifications yet.</p><?php endif; ?>
    <?php foreach ($mine as $n): ?>
      <article class="notif<?= $n['read'] ? '' : ' is-unread' ?>" data-notif-id="<?= (int)$n['db_id'] ?>">
        <div class="notif__icon">
          <?= $n['type'] === 'Delivery' ? '&#128666;' : ($n['type'] === 'Payment' ? '&#128179;' : '&#128276;') ?>
        </div>
        <div>
          <h4><?= e($n['title']) ?></h4>
          <p><?= e($n['message']) ?></p>
          <small><?= e($n['date']) ?> &middot; <span class="badge"><?= e($n['type']) ?></span></small>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>