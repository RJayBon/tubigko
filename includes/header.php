<?php
require_once __DIR__ . '/bootstrap.php';

$BASE       = $BASE ?? '.';
$PAGE_TITLE = $PAGE_TITLE ?? 'Dashboard';
$ROLE       = $ROLE ?? 'admin';

$__me       = current_user();
$__initials = $__me ? initials_for((string)$__me['full_name']) : 'U';

if ($__me) {
    $__unread = ($__me['role'] === 'admin')
        ? admin_unread_count()
        : count_unread_notifications((int)$__me['id']);
} else {
    $__unread = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($PAGE_TITLE) ?> &middot; TubigKo</title>
<meta name="description" content="TubigKo Water Refilling Station System.">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="tubigko-notif-api" content="<?= e($BASE) ?>/api/notifications.php">
<meta name="tubigko-user" content="<?= (int)($__me['id'] ?? 0) ?>">
<meta name="tubigko-role" content="<?= e($__me['role'] ?? 'guest') ?>">
<link rel="stylesheet" href="<?= e($BASE) ?>/assets/css/style.css">
</head>
<body class="app">
<div class="layout">
<?php include __DIR__ . '/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <button class="icon-btn only-mobile" id="menuToggle" aria-label="Toggle menu">&#9776;</button>
      <div>
        <h1 class="topbar__title"><?= e($PAGE_TITLE) ?></h1>
        <p class="topbar__sub">
          <?= $ROLE === 'admin' ? 'Administrator workspace' : 'Customer workspace' ?>
          <?= $__me ? ' &middot; ' . e($__me['full_name']) : '' ?>
        </p>
      </div>
      <div class="topbar__right">
        <a class="icon-btn" href="<?= e($BASE) ?>/<?= e($ROLE) ?>/notifications.php"
           aria-label="Notifications" style="position:relative">
          &#128276;<span class="notif-count"<?= $__unread > 0 ? '' : ' hidden' ?>><?= $__unread > 9 ? '9+' : (int)$__unread ?></span>
        </a>
        <div class="avatar" aria-hidden="true"><?= e($__initials) ?></div>
        <a class="btn btn--ghost" href="<?= e($BASE) ?>/logout.php">Sign out</a>
      </div>
    </header>
    <div class="content">
      <?php foreach (flash_all() as $type => $msg): ?>
        <div class="flash flash--<?= e($type) ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>