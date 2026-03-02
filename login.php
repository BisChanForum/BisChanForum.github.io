<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $stmt = $db->prepare('SELECT id, username, password_hash, is_banned FROM users WHERE username = :u');
    $stmt->execute(array(':u' => $username));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Неверное имя пользователя или пароль.';
    } elseif ((int)$user['is_banned'] === 1) {
        $error = 'Ваш аккаунт заблокирован.';
    } else {
        $_SESSION['user_id'] = (int)$user['id'];
        log_action('user_login', (int)$user['id'], $user['username'], 'user', (int)$user['id']);
        header('Location: index.php');
        exit;
    }
}

if (!$error && (isset($_GET['banned']) || isset($_SESSION['banned_notice']))) {
    $error = 'Ваш аккаунт заблокирован.';
    if (isset($_SESSION['banned_notice'])) {
        unset($_SESSION['banned_notice']);
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">БисЧан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <a href="register.php">Регистрация</a>
    </nav>
</header>

<main class="container narrow">
    <h1>Вход</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <label>
            Имя пользователя
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit">Войти</button>
    </form>
</main>
</body>
</html>
