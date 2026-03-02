<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$user = require_login($db);
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$error = '';
$editWindowMinutes = 15;

if ($id <= 0) {
    http_response_code(404);
    echo 'Тема не найдена.';
    exit;
}

$stmt = $db->prepare('SELECT id, user_id, title, body, created_at FROM topics WHERE id = :id');
$stmt->execute(array(':id' => $id));
$topic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
    http_response_code(404);
    echo 'Тема не найдена.';
    exit;
}

if (!can_edit_owned($user, (int)$topic['user_id'], $topic['created_at'], $editWindowMinutes)) {
    http_response_code(403);
    echo 'Вы не можете редактировать эту тему.';
    exit;
}

$title = $topic['title'];
$body = $topic['body'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $body = trim(isset($_POST['body']) ? $_POST['body'] : '');

    if ($title === '' || $body === '') {
        $error = 'Заполните заголовок и текст.';
    } else {
        $stmt = $db->prepare('UPDATE topics SET title = :t, body = :b WHERE id = :id');
        $stmt->execute(array(
            ':t' => $title,
            ':b' => $body,
            ':id' => $id,
        ));
        log_action('topic_edit', (int)$user['id'], $user['username'], 'topic', $id, array(
            'title' => $title,
        ));
        header('Location: topic.php?id=' . $id);
        exit;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Редактирование темы</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">Бисчан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <a href="logout.php">Выйти</a>
    </nav>
</header>

<main class="container narrow">
    <h1>Редактирование темы</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <label>
            Заголовок
            <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required>
        </label>
        <label>
            Текст
            <textarea name="body" rows="10" required><?= htmlspecialchars($body) ?></textarea>
        </label>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit">Сохранить</button>
    </form>
</main>
</body>
</html>
