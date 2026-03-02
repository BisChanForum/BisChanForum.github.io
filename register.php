<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

$db = db();
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $display_name = trim(isset($_POST['display_name']) ? $_POST['display_name'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $error = 'Введите имя пользователя и пароль.';
    } elseif (mb_strlen($username) < 3) {
        $error = 'Имя пользователя слишком короткое.';
    } elseif (!preg_match('/^[\\p{L}\\p{N}_-]+$/u', $username)) {
        $error = 'Имя пользователя может содержать только буквы, цифры, "_" и "-" .';
    } else {
        try {
            $displayColumn = user_display_name_column($db);
            $stmt = $db->prepare('INSERT INTO users (username, ' . $displayColumn . ', password_hash, created_at)
                                  VALUES (:u, :n, :p, :c)');
            $stmt->execute([
                ':u' => $username,
                ':n' => $display_name,
                ':p' => password_hash($password, PASSWORD_DEFAULT),
                ':c' => date('Y-m-d H:i:s'),
            ]);
            $newUserId = (int)$db->lastInsertId();
            log_action('user_register', $newUserId, $username, 'user', $newUserId, array(
                'display_name' => $display_name,
            ));
            $_SESSION['user_id'] = $newUserId;
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Такое имя уже занято.';
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">БисЧан</div>
    <nav class="nav">
        <a href="index.php">Главная</a>
        <a href="login.php">Вход</a>
    </nav>
</header>

<main class="container narrow">
    <h1>Регистрация</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <label>
            Имя пользователя
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
        </label>
        <label>
            Имя
            <input type="text" name="display_name" value="<?= htmlspecialchars($display_name) ?>" required>
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit">Создать аккаунт</button>
    </form>
</main>
</body>
</html>
