<?php
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';

$db = db();
$user = current_user($db);
$uploadMessage = '';
$uploadError = '';
$profileMessage = '';
$profileError = '';
$notifications = array();
$get_id = 0;

function text_len($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : 'avatar';

    if ($action === 'notifications_read') {
        if ($user) {
            $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u');
            $stmt->execute(array(':u' => (int)$user['id']));
            $profileMessage = 'Уведомления отмечены как прочитанные.';
        }
    } elseif ($action === 'profile') {
        if (!$user) {
            http_response_code(403);
            $profileError = 'Нужно войти в аккаунт, чтобы менять профиль.';
        } else {
            $get_id = (int)(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
            if ($get_id !== (int)$user['id']) {
                http_response_code(403);
                $profileError = 'Нельзя менять профиль другого пользователя.';
            } else {
                $display_name = trim(isset($_POST['display_name']) ? $_POST['display_name'] : '');
                $bio = trim(isset($_POST['bio']) ? $_POST['bio'] : '');
                $website = trim(isset($_POST['website']) ? $_POST['website'] : '');

                if (text_len($display_name) > 191) {
                    $profileError = 'Имя слишком длинное.';
                } elseif (text_len($bio) > 1000) {
                    $profileError = 'Описание слишком длинное.';
                } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
                    $profileError = 'Ссылка некорректна.';
                } else {
                    $displayColumn = user_display_name_column($db);
                    $bioColumn = user_bio_column($db);
                    $websiteColumn = user_website_column($db);
                    $stmt = $db->prepare('UPDATE users SET ' . $displayColumn . ' = :n, ' . $bioColumn . ' = :b, ' . $websiteColumn . ' = :w WHERE id = :id');
                    $stmt->execute(array(
                        ':n' => $display_name,
                        ':b' => $bio,
                        ':w' => $website,
                        ':id' => (int)$user['id'],
                    ));
                    $profileMessage = 'Профиль обновлен.';
                    $user = current_user($db);
                }
            }
        }
    } else {
        if (!$user) {
            http_response_code(403);
            $uploadError = 'Нужно войти в аккаунт, чтобы менять аватар.';
        } else {
            $get_id = (int)(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
            if ($get_id !== (int)$user['id']) {
                http_response_code(403);
                $uploadError = 'Нельзя менять аватар другого пользователя.';
            } elseif (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = 'Не удалось загрузить файл.';
            } else {
                $maxSize = 2 * 1024 * 1024;
                if ($_FILES['avatar']['size'] > $maxSize) {
                    $uploadError = 'Файл слишком большой. Максимум 2 МБ.';
                } else {
                    $tmp = $_FILES['avatar']['tmp_name'];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmp);
                    $allowed = array(
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                    );
                    if (!isset($allowed[$mime])) {
                        $uploadError = 'Неподдерживаемый тип файла.';
                    } elseif (!@getimagesize($tmp)) {
                        $uploadError = 'Файл не является изображением.';
                    } else {
                        $ext = $allowed[$mime];
                        $filename = 'user_' . (int)$user['id'] . '_' . bin2hex(secure_random_bytes(8)) . '.' . $ext;
                        $targetPath = __DIR__ . '/avatars/' . $filename;
                        if (!move_uploaded_file($tmp, $targetPath)) {
                            $uploadError = 'Не удалось сохранить файл.';
                        } else {
                            $oldAvatar = isset($user['avatar']) ? (string)$user['avatar'] : '';
                            if ($oldAvatar !== '' && $oldAvatar !== 'Default.jpg') {
                                $oldPath = __DIR__ . '/avatars/' . basename($oldAvatar);
                                if (is_file($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                            $avatarColumn = user_avatar_column($db);
                            $stmt = $db->prepare('UPDATE users SET ' . $avatarColumn . ' = :a WHERE id = :id');
                            $stmt->execute(array(
                                ':a' => $filename,
                                ':id' => (int)$user['id'],
                            ));
                            $uploadMessage = 'Аватар обновлен.';
                            $user = current_user($db);
                        }
                    }
                }
            }
        }
    }
}

if ($get_id <= 0) {
    $get_id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
}
if ($get_id <= 0) {
    die("<meta charset='UTF-8'> Ты ахуел в край!!!");
}
$user_id = $user ? (int)$user['id'] : 0;
if ($get_id === $user_id && $user) {
    $profile = $user;
} else {
    $profile = not_current_user_by_id($db, $get_id);
}

if ($profile && $user && $get_id === $user_id) {
    $notificationsStmt = $db->prepare(
        'SELECT n.id, n.topic_id, n.reply_id, n.created_at, u.username, t.title
         FROM notifications n
         JOIN users u ON u.id = n.actor_id
         JOIN topics t ON t.id = n.topic_id
         WHERE n.user_id = :u AND n.is_read = 0
         ORDER BY n.created_at DESC
         LIMIT 50'
    );
    $notificationsStmt->execute(array(':u' => (int)$user_id));
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo $profile ? htmlspecialchars($profile['username']) : 'Профиль'; ?></title>
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
    <?php if ($uploadError): ?>
        <div class="error"><?= htmlspecialchars($uploadError) ?></div>
    <?php elseif ($uploadMessage): ?>
        <div class="empty"><?= htmlspecialchars($uploadMessage) ?></div>
    <?php endif; ?>

    <?php if ($profileError): ?>
        <div class="error"><?= htmlspecialchars($profileError) ?></div>
    <?php elseif ($profileMessage): ?>
        <div class="empty"><?= htmlspecialchars($profileMessage) ?></div>
    <?php endif; ?>

    <?php if ($profile): ?>
        <article class="topic-full">
            <div class="meta">
                <img width="10%" height="10%" src="<?= htmlspecialchars(avatar_url($profile)) ?>">
                <?php
                $color_username = '';
                if (is_admin($profile)) { $color_username = 'red'; }
                $displayName = isset($profile['display_name']) && $profile['display_name'] !== '' ? $profile['display_name'] : $profile['username'];
                $createdAt = isset($profile['created_at']) ? $profile['created_at'] : '';
                ?>
                <span style="color:<?= $color_username ?>; font-weight: bold; font-size: 22px;"><?= htmlspecialchars($displayName) ?></span>
                <span>Имя пользователя: <?= htmlspecialchars($profile['username']) ?></span>
                <span>Аккаунт создан: <?= htmlspecialchars($createdAt) ?></span>
            </div>
            <?php if (!empty($profile['bio'])): ?>
                <div class="body"><?= nl2br(htmlspecialchars($profile['bio'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($profile['website'])): ?>
                <div class="meta">
                    <span>Сайт: <a href="<?= htmlspecialchars($profile['website']) ?>" rel="nofollow"><?= htmlspecialchars($profile['website']) ?></a></span>
                </div>
            <?php endif; ?>
        </article>

        <?php if ($get_id === $user_id): ?>
            <section class="replies">
                <h2>Профиль</h2>
                <form method="post" class="form">
                    <label>
                        Имя
                        <input type="text" name="display_name" value="<?= htmlspecialchars($profile['display_name']) ?>">
                    </label>
                    <label>
                        Описание
                        <textarea name="bio" rows="5"><?= htmlspecialchars($profile['bio']) ?></textarea>
                    </label>
                    <label>
                        Сайт
                        <input type="url" name="website" value="<?= htmlspecialchars($profile['website']) ?>">
                    </label>
                    <input type="hidden" name="user_id" value="<?= (int)$user_id ?>">
                    <input type="hidden" name="action" value="profile">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit">Сохранить</button>
                </form>
            </section>

            <section class="replies">
                <h2>Аватар</h2>
                <form method="post" enctype="multipart/form-data" class="form">
                    <label>
                        Аватар
                        <input type="file" name="avatar" accept="image/*" required>
                    </label>
                    <input type="hidden" name="user_id" value="<?= (int)$user_id ?>">
                    <input type="hidden" name="action" value="avatar">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit">Загрузить</button>
                </form>
            </section>

            <section class="replies">
                <h2>Уведомления</h2>
                <?php if (count($notifications) === 0): ?>
                    <div class="empty">Новых уведомлений нет.</div>
                <?php else: ?>
                    <ul class="topic-list">
                        <?php foreach ($notifications as $n): ?>
                            <li class="topic">
                                <a class="topic-title" href="topic.php?id=<?= (int)$n['topic_id'] ?>#reply-<?= (int)$n['reply_id'] ?>">
                                    <?= htmlspecialchars($n['title']) ?>
                                </a>
                                <div class="meta">
                                    <span>Ответил: <?= htmlspecialchars($n['username']) ?></span>
                                    <span><?= htmlspecialchars($n['created_at']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="post" class="form">
                        <input type="hidden" name="action" value="notifications_read">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <button type="submit">Отметить все прочитанными</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php elseif ($profile): ?>
            <div class="empty">Как тебе форумчанин?))))</div>
        <?php endif; ?>
    <?php else: ?>
        <?php die('Не, ну ты что-то дохуя ахуеваешь'); ?>
    <?php endif; ?>
</main>
</body>
</html>
