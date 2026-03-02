<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$admin = require_admin($db);

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$error = '';

if ($id <= 0) {
    http_response_code(404);
    echo 'Ответ не найден.';
    exit;
}

$stmt = $db->prepare('SELECT id, topic_id, body FROM replies WHERE id = :id');
$stmt->execute(array(':id' => $id));
$reply = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reply) {
    http_response_code(404);
    echo 'Ответ не найден.';
    exit;
}

$body = $reply['body'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $body = trim(isset($_POST['body']) ? $_POST['body'] : '');
    if ($body === '') {
        $error = 'Текст не может быть пустым.';
    } else {
        $stmt = $db->prepare('UPDATE replies SET body = :b WHERE id = :id');
        $stmt->execute(array(
            ':b' => $body,
            ':id' => $id,
        ));
        log_action('reply_edit', (int)$admin['id'], $admin['username'], 'reply', $id, array(
            'topic_id' => (int)$reply['topic_id'],
        ));
        header('Location: topic.php?id=' . (int)$reply['topic_id'] . '#reply-' . $id);
        exit;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Редактирование ответа</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">БисЧан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <a href="admin.php">Админка</a>
        <a href="logout.php">Выйти</a>
    </nav>
</header>

<main class="container narrow">
    <h1>Редактирование ответа</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <label>
            Текст
            <textarea name="body" rows="8" required><?= htmlspecialchars($body) ?></textarea>
        </label>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit">Сохранить</button>
    </form>
</main>
</body>
</html>
