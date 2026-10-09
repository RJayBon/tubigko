<?php
require_once __DIR__ . '/includes/bootstrap.php';

logout_user();
flash_set('success', 'You have been signed out.');
header('Location: index.php');
exit;