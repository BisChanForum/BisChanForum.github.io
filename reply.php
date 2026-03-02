<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

$db = db();
$user = require_login($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf();
$topicId = (int)(isset($_POST['topic_id']) ? $_POST['topic_id'] : 0);
$body = trim(isset($_POST['body']) ? $_POST['body'] : '');
$parentId = (int)(isset($_POST['parent_id']) ? $_POST['parent_id'] : 0);

if ($topicId <= 0 || $body === '') {
    header('Location: topic.php?id=' . $topicId);
    exit;
}

$topicOwnerId = 0;
$ownerStmt = $db->prepare('SELECT user_id FROM topics WHERE id = :id');
$ownerStmt->execute(array(':id' => $topicId));
$ownerRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);
if ($ownerRow) {
    $topicOwnerId = (int)$ownerRow['user_id'];
}

$parentValue = null;
if ($parentId > 0) {
    $pstmt = $db->prepare('SELECT id FROM replies WHERE id = :id AND topic_id = :t');
    $pstmt->execute([
        ':id' => $parentId,
        ':t' => $topicId,
    ]);
    if ($pstmt->fetch(PDO::FETCH_ASSOC)) {
        $parentValue = $parentId;
    }
}


$stmt = $db->prepare('INSERT INTO replies (topic_id, user_id, parent_id, body, created_at)
                      VALUES (:t, :u, :p, :b, :c)');
$stmt->execute([
    ':t' => $topicId,
    ':u' => $user['id'],
    ':p' => $parentValue,
    ':b' => $body,
    ':c' => date('Y-m-d H:i:s'),
]);

$replyId = (int)$db->lastInsertId();
log_action('reply_create', (int)$user['id'], $user['username'], 'reply', $replyId, array(
    'topic_id' => $topicId,
    'parent_id' => $parentValue,
));

if ($topicOwnerId > 0 && $topicOwnerId !== (int)$user['id']) {
    $pstmt = $db->prepare('SELECT user_id FROM replies WHERE id = :id');
    $pstmt->execute(Array(
        ':id' => $parentValue,
    ));
    $reply_author = (int)$pstmt->fetch(PDO::FETCH_ASSOC)['user_id'];
    $nstmt = $db->prepare('INSERT INTO notifications (user_id, actor_id, topic_id, reply_id, is_read, created_at)
                           VALUES (:u, :a, :t, :r, 0, :c)');
    $nstmt->execute(array(
        ':u' => $reply_author,
        ':a' => (int)$user['id'],
        ':t' => $topicId,
        ':r' => $replyId,
        ':c' => date('Y-m-d H:i:s'),
    ));
}

header('Location: topic.php?id=' . $topicId);
exit;
