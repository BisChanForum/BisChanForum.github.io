<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$user = require_login($db);
$error = '';
$title = '';
$body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $body = trim(isset($_POST['body']) ? $_POST['body'] : '');

    if ($title === '' || $body === '') {
        $error = 'Заполните заголовок и текст.';
    } else {
        $stmt = $db->prepare('INSERT INTO topics (user_id, title, body, created_at)
                              VALUES (:u, :t, :b, :c)');
        $stmt->execute([
            ':u' => $user['id'],
            ':t' => $title,
            ':b' => $body,
            ':c' => date('Y-m-d H:i:s'),
        ]);
        $id = (int)$db->lastInsertId();
        log_action('topic_create', (int)$user['id'], $user['username'], 'topic', $id, array(
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
    <title>Новая тема</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">БисЧан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <?php if (is_admin($user)): ?>
            <a href="admin.php">Админка</a>
        <?php endif; ?>
        <a href="logout.php">Выйти</a>
    </nav>
</header>

<main class="container narrow">
    <h1>Новая тема</h1>
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
            <textarea name="body" rows="8" required><?= htmlspecialchars($body) ?></textarea>
        </label>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit">Опубликовать</button>
    </form>
</main>
</body>
</html>
