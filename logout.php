<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$user = current_user($db);
if ($user) {
    log_action('user_logout', (int)$user['id'], $user['username'], 'user', (int)$user['id']);
}
session_destroy();
header('Location: index.php');
exit;
