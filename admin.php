<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$admin = require_admin($db);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $targetId = (int)(isset($_POST['id']) ? $_POST['id'] : 0);

    if ($targetId <= 0) {
        $error = 'Некорректный идентификатор.';
    } else {
        if (($action === 'user_demote' || $action === 'user_ban') && $targetId === (int)$admin['id']) {
            $error = 'Нельзя применять это действие к самому себе.';
        } elseif ($action === 'user_promote') {
            $stmt = $db->prepare('UPDATE users SET is_admin = 1 WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('user_promote', (int)$admin['id'], $admin['username'], 'user', $targetId);
            $message = 'Права администратора выданы.';
        } elseif ($action === 'user_demote') {
            $stmt = $db->prepare('UPDATE users SET is_admin = 0 WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('user_demote', (int)$admin['id'], $admin['username'], 'user', $targetId);
            $message = 'Права администратора сняты.';
        } elseif ($action === 'user_ban') {
            $stmt = $db->prepare('UPDATE users SET is_banned = 1 WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            $stmt = $db->prepare('UPDATE users SET DisplayName = "Аккаунт заблокирован" WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            $stmt = $db->prepare('UPDATE users SET Avatar = "banned.jpg" WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('user_ban', (int)$admin['id'], $admin['username'], 'user', $targetId);
            $message = 'Пользователь заблокирован.';
        } elseif ($action === 'user_unban') {
            $stmt = $db->prepare('UPDATE users SET is_banned = 0 WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            $stmt = $db->prepare('UPDATE users SET DisplayName = "Аккаунт разблокирован" WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            $stmt = $db->prepare('UPDATE users SET Avatar = "Default.jpg" WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('user_unban', (int)$admin['id'], $admin['username'], 'user', $targetId);
            $message = 'Пользователь разблокирован.';
        } elseif ($action === 'topic_pin') {
            $stmt = $db->prepare('UPDATE topics SET is_pinned = 1 WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('topic_pin', (int)$admin['id'], $admin['username'], 'topic', $targetId);
            $message = 'Тема закреплена.';
        } elseif ($action === 'topic_unpin') {
            $stmt = $db->prepare('UPDATE topics SET is_pinned = 0 WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('topic_unpin', (int)$admin['id'], $admin['username'], 'topic', $targetId);
            $message = 'Тема откреплена.';
        } elseif ($action === 'topic_delete') {
            $db->beginTransaction();
            $stmt = $db->prepare('DELETE FROM replies WHERE topic_id = :id');
            $stmt->execute(array(':id' => $targetId));
            $stmt = $db->prepare('DELETE FROM topics WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            $db->commit();
            log_action('topic_delete', (int)$admin['id'], $admin['username'], 'topic', $targetId);
            $message = 'Тема удалена.';
        } elseif ($action === 'reply_delete') {
            $stmt = $db->prepare('DELETE FROM replies WHERE id = :id');
            $stmt->execute(array(':id' => $targetId));
            log_action('reply_delete', (int)$admin['id'], $admin['username'], 'reply', $targetId);
            $message = 'Ответ удален.';
        }
    }
}

function text_excerpt($text, $length)
{
    $text = trim($text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

$usersStmt = $db->query('SELECT id, username, is_admin, is_banned, created_at FROM users ORDER BY created_at DESC');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$topicsStmt = $db->query(
    'SELECT t.id, t.title, t.created_at, t.is_pinned, u.username
     FROM topics t
     JOIN users u ON u.id = t.user_id
     ORDER BY t.is_pinned DESC, t.created_at DESC
     LIMIT 50'
);
$topics = $topicsStmt->fetchAll(PDO::FETCH_ASSOC);

$repliesStmt = $db->query(
    'SELECT r.id, r.body, r.created_at, r.topic_id, u.username
     FROM replies r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC
     LIMIT 50'
);
$replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Админка</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">БисЧан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <a href="new_topic.php">Новая тема</a>
        <a href="logs.php">Логи</a>
        <a href="logout.php">Выйти</a>
    </nav>
</header>

<main class="container">
    <h1>Админка</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($message): ?>
        <div class="empty"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="admin-section">
        <h2>Пользователи</h2>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>Статус</th>
                <th>Создан</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><a href="user.php?id=<?php echo $u['id']; ?>"><?= htmlspecialchars($u['username']) ?></a></td>
                    <td>
                        <?php if ((int)$u['is_admin'] === 1): ?>Админ<?php endif; ?>
                        <?php if ((int)$u['is_banned'] === 1): ?> Заблокирован<?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['created_at']) ?></td>
                    <td class="admin-actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="user_promote">
                            <button type="submit">Сделать админом</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="user_demote">
                            <button type="submit">Снять админа</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="user_ban">
                            <button type="submit">Забанить</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="user_unban">
                            <button type="submit">Разбанить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="admin-section">
        <h2>Темы (последние 50)</h2>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Тема</th>
                <th>Автор</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($topics as $t): ?>
                <tr>
                    <td><?= (int)$t['id'] ?></td>
                    <td>
                        <?php if ((int)$t['is_pinned'] === 1): ?>
                            <span class="badge">Закреплено</span>
                        <?php endif; ?>
                        <a href="topic.php?id=<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a>
                    </td>
                    <td><?= htmlspecialchars($t['username']) ?></td>
                    <td><?= htmlspecialchars($t['created_at']) ?></td>
                    <td class="admin-actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="topic_pin">
                            <button type="submit">Закрепить</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="topic_unpin">
                            <button type="submit">Открепить</button>
                        </form>
                        <a href="admin_edit_topic.php?id=<?= (int)$t['id'] ?>">Редактировать</a>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="topic_delete">
                            <button type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="admin-section">
        <h2>Ответы (последние 50)</h2>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Тема</th>
                <th>Автор</th>
                <th>Текст</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($replies as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><a href="topic.php?id=<?= (int)$r['topic_id'] ?>#reply-<?= (int)$r['id'] ?>">Перейти</a></td>
                    <td><?= htmlspecialchars($r['username']) ?></td>
                    <td><?= htmlspecialchars(text_excerpt($r['body'], 80)) ?></td>
                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                    <td class="admin-actions">
                        <a href="admin_edit_reply.php?id=<?= (int)$r['id'] ?>">Редактировать</a>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="reply_delete">
                            <button type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
