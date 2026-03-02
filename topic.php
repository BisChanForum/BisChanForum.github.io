<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';

$db = db();
$user = current_user($db);
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$replyTo = (int)(isset($_GET['reply_to']) ? $_GET['reply_to'] : 0);
$editWindowMinutes = 15;

$stmt = $db->prepare(
    'SELECT t.id, t.user_id, t.title, t.body, t.created_at, t.is_pinned, u.username,
    (SELECT COUNT(*) FROM replies r WHERE r.topic_id = t.id) AS replies
     FROM topics t
     JOIN users u ON u.id = t.user_id
     WHERE t.id = :id'
);
$stmt->execute([':id' => $id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
    http_response_code(404);
    echo 'Тема не найдена.';
    exit;
}

$rstmt = $db->prepare(
    'SELECT r.id, r.parent_id, r.user_id, r.body, r.created_at, u.username
     FROM replies r
     JOIN users u ON u.id = r.user_id
     WHERE r.topic_id = :id
     ORDER BY r.created_at ASC'
);
$rstmt->execute([':id' => $id]);
$replies = $rstmt->fetchAll(PDO::FETCH_ASSOC);

$replyIds = array();
$replyByParent = array();
foreach ($replies as $reply) {
    $replyIdValue = (int)$reply['id'];
    $replyIds[$replyIdValue] = true;
    $parentKey = isset($reply['parent_id']) ? (int)$reply['parent_id'] : 0;
    if (!isset($replyByParent[$parentKey])) {
        $replyByParent[$parentKey] = array();
    }
    $replyByParent[$parentKey][] = $reply;
}

if ($replyTo > 0 && !isset($replyIds[$replyTo])) {
    $replyTo = 0;
}

function render_replies($parentId, $replyByParent, $topicId, $depth, PDO $db, $currentUser, $editWindowMinutes)
{
    if (!isset($replyByParent[$parentId])) {
        return;
    }
    echo '<ul class="reply-list">';
    foreach ($replyByParent[$parentId] as $r) {
        $replyId = (int)$r['id'];
        $reply_author = not_current_user_by_username($db, $r['username']);
        echo '<li class="reply" id="reply-' . $replyId . '">';
        echo '<div class="meta">';
        echo '<img width="10%" height="10%" src="' . htmlspecialchars(avatar_url($reply_author)) . '">';
        $color_username = '';
        if (is_admin($reply_author)) { $color_username = 'red'; }
        $replyDisplayName = isset($reply_author['display_name']) && $reply_author['display_name'] !== '' ? $reply_author['display_name'] : $reply_author['username'];
        echo '<span><a style="color:' . $color_username . ';" href="user.php?id=' . $reply_author['id'] . '">' . htmlspecialchars($replyDisplayName) . ' (' . htmlspecialchars($reply_author['username']) . ')</a></span>';
        echo '<span>' . htmlspecialchars($r['created_at']) . '</span>';
        echo '</div>';
        echo '<div class="body">' . nl2br(htmlspecialchars($r['body'])) . '</div>';
        echo '<div class="reply-actions">';
        echo '<a href="topic.php?id=' . $topicId . '&reply_to=' . $replyId . '#reply-form">Ответить</a>';
        if (can_edit_owned($currentUser, (int)$r['user_id'], $r['created_at'], $editWindowMinutes)) {
            echo ' <a href="edit_reply.php?id=' . $replyId . '">Редактировать</a>';
        }
        echo '</div>';
        render_replies($replyId, $replyByParent, $topicId, $depth + 1, $db, $currentUser, $editWindowMinutes);
        echo '</li>';
    }
    echo '</ul>';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($topic['title']) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">Бисчан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <?php if ($user): ?>
            <a href="new_topic.php">Новая тема</a>
            <?php if (is_admin($user)): ?>
                <a href="admin.php">Админка</a>
            <?php endif; ?>
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <a href="login.php">Вход</a>
            <a href="register.php">Регистрация</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">
    <article class="topic-full">
        <h1>
            <?php if ((int)$topic['is_pinned'] === 1): ?>
                <span class="badge">Закреплено</span>
            <?php endif; ?>
            <?= htmlspecialchars($topic['title']) ?>
        </h1>
        <div class="meta">
            <span>Автор: <?php
                        $topics_author = not_current_user_by_username($db, $topic['username']);
                        echo "<a href='user.php?id=".$topics_author['id']."'>".htmlspecialchars($topics_author['display_name'])." (".htmlspecialchars($topic['username']).")</a>";
                        ?></span>
                        <span>Ответов: <?= (int)$topic['replies'] ?></span>
            <span><?= htmlspecialchars($topic['created_at']) ?></span>
        </div>
        <div class="body"><?= nl2br(htmlspecialchars($topic['body'])) ?></div>
        <?php if (can_edit_owned($user, (int)$topic['user_id'], $topic['created_at'], $editWindowMinutes)): ?>
            <div class="reply-actions">
                <a href="edit_topic.php?id=<?= (int)$topic['id'] ?>">Редактировать</a>
            </div>
        <?php endif; ?>
    </article>

    <section class="replies">
        <h2>Ответы</h2>
        <?php if (count($replies) === 0): ?>
            <div class="empty">Ответов пока нет.</div>
        <?php else: ?>
            <?php render_replies(0, $replyByParent, (int)$topic['id'], 0, $db, $user, $editWindowMinutes); ?>
        <?php endif; ?>
    </section>

    <section class="reply-form" id="reply-form">
        <?php if ($user): ?>
            <h3>Ваш ответ</h3>
            <?php if ($replyTo > 0): ?>
                <div class="note">Ответ на комментарий #<?= (int)$replyTo ?></div>
            <?php endif; ?>
            <form method="post" action="reply.php" class="form">
                <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                <input type="hidden" name="parent_id" value="<?= $replyTo > 0 ? (int)$replyTo : 0 ?>">
                <textarea name="body" rows="6" required></textarea>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit">Ответить</button>
            </form>
        <?php else: ?>
            <div class="empty">Чтобы отвечать, войдите в аккаунт.</div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
