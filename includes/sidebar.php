<?php
$current = basename($_SERVER['PHP_SELF']);
$adminNav = [
  ['dashboard.php','Dashboard','&#9632;'],
  ['customers.php','Manage Customer Account','&#128100;'],
  ['gallons.php','Manage Gallons','&#128167;'],
  ['payments.php','Manage Payment','&#128179;'],
  ['deliveries.php','Manage Delivery Process','&#128666;'],
  ['notifications.php','Manage Notification','&#128276;'],
];
$customerNav = [
  ['gallons.php','Gallon','&#128167;'],
  ['payment.php','Payment','&#128179;'],
  ['delivery.php','Delivery Process','&#128666;'],
  ['notifications.php','Notification','&#128276;'],
];
$nav = ($ROLE === 'admin') ? $adminNav : $customerNav;
$__unread = $__unread ?? 0;
?>
<aside class="side" id="sidebar">
  <a class="brand" href="<?= e($BASE) ?>/index.php">
    <span class="brand__mark">&#128167;</span>
    <span><strong>TubigKo</strong><small>Water Refilling Station</small></span>
  </a>
  <nav class="nav">
    <p class="nav__label"><?= $ROLE === 'admin' ? 'Administration' : 'My Account' ?></p>
    <?php foreach ($nav as $item): ?>
      <a class="nav__link<?= $current === $item[0] ? ' is-active' : '' ?>"
         href="<?= e($BASE) ?>/<?= e($ROLE) ?>/<?= e($item[0]) ?>">
        <span class="nav__icon"><?= $item[2] ?></span><?= $item[1] ?>
        <?php if ($item[0] === 'notifications.php'): ?>
          <span class="notif-count" style="position:static;margin-left:.4rem"<?= $__unread > 0 ? '' : ' hidden' ?>><?= $__unread > 9 ? '9+' : (int)$__unread ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <span class="muted" style="font-size:.78rem;padding:0 .3rem">
      Signed in as <?= isset($__me) && $__me ? e($__me['full_name']) : '' ?>
    </span>
  </div>
</aside>