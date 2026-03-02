<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
//\ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

$db = db();
$user = current_user($db);

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$params = array();
$where = '';
if ($q !== '') {
    $where = 'WHERE t.title LIKE :q OR t.body LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$stmt = $db->prepare(
    'SELECT t.id, t.title, t.created_at, t.is_pinned, u.username,
            (SELECT COUNT(*) FROM replies r WHERE r.topic_id = t.id) AS replies
     FROM topics t
     JOIN users u ON u.id = t.user_id
     ' . $where . '
     ORDER BY t.is_pinned DESC, t.created_at DESC'
);
$stmt->execute($params);
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notificationsStmt = $db->prepare(
        'SELECT n.id, n.topic_id, n.reply_id, n.created_at, u.username, t.title
         FROM notifications n
         JOIN users u ON u.id = n.actor_id
         JOIN topics t ON t.id = n.topic_id
         WHERE n.user_id = :u AND n.is_read = 0
         ORDER BY n.created_at DESC
         LIMIT 50'
    );
    $notificationsStmt->execute(array(':u' => (int)$user['id']));
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="5">
    <title>БисЧан</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">БисЧан</div>
    <nav class="nav">
        <?php if ($user): ?>
            <?php
            if (count($notifications) > 0){
            echo '
            <a href="user.php?id='.htmlspecialchars($user["id"]).'">
                <div title="У вас есть уведомления!" style="text-align: center;position: absolute; width: 20px; height: 20px; border-radius: 50%; background: red;">'. count($notifications) .'</div>
                <span class="user">Привет, '.htmlspecialchars($user["display_name"]).'</span>
            </a>
            ';
            } else {
                echo '
            <a href="user.php?id='.htmlspecialchars($user["id"]).'">
                <span class="user">Привет, '.htmlspecialchars($user["display_name"]).'</span>
            </a>
            ';
            }
            ?>
            
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
    <form class="search" method="get" action="index.php">
        <input type="text" name="q" placeholder="Поиск по темам" value="<?= htmlspecialchars($q) ?>">
        <button type="submit">Искать</button>
    </form>

    <?php if (count($topics) === 0): ?>
        <div class="empty">Похоже, произошла какая-то ошибка, пожалуйста подождите, мы пытаемся решить проблему.</div>
    <?php else: ?>
        <ul class="topic-list">
            <?php foreach ($topics as $t): ?>
                <li class="topic">
                    <a class="topic-title" href="topic.php?id=<?= (int)$t['id'] ?>">
                        <?php if ((int)$t['is_pinned'] === 1): ?>
                            <span class="badge">Закреплено</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($t['title']) ?>
                    </a>
                    <div class="meta">
                        <span>Автор: <?php
                        $topics_author = not_current_user_by_username($db, $t['username']);
                        echo "<a href='user.php?id=".$topics_author['id']."'>".htmlspecialchars($topics_author['display_name'])." (".htmlspecialchars($t['username']).")</a>";
                        ?></span>
                        <span>Ответов: <?= (int)$t['replies'] ?></span>
                        <span><?= htmlspecialchars($t['created_at']) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
</body>
</html>
