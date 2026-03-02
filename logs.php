<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/activity_log.php';

$db = db();
$admin = require_admin($db);

$logDb = log_db();
$stmt = $logDb->query('SELECT id, actor_user_id, actor_username, action, entity_type, entity_id, details, ip, user_agent, created_at
                       FROM action_logs
                       ORDER BY created_at DESC
                       LIMIT 200');
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Логи действий</title>
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

<main class="container">
    <h1>Логи действий (последние 200)</h1>

    <?php if (!$logs): ?>
        <div class="empty">Логи пока отсутствуют.</div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Действие</th>
                <th>Кто</th>
                <th>Объект</th>
                <th>Детали</th>
                <th>IP</th>
                <th>User-Agent</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= (int)$log['id'] ?></td>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td>
                        <?php if ($log['actor_user_id']): ?>
                            #<?= (int)$log['actor_user_id'] ?>
                        <?php endif; ?>
                        <?= $log['actor_username'] ? htmlspecialchars($log['actor_username']) : '' ?>
                    </td>
                    <td>
                        <?= $log['entity_type'] ? htmlspecialchars($log['entity_type']) : '' ?>
                        <?= $log['entity_id'] ? ('#' . (int)$log['entity_id']) : '' ?>
                    </td>
                    <td><?= $log['details'] ? htmlspecialchars($log['details']) : '' ?></td>
                    <td><?= $log['ip'] ? htmlspecialchars($log['ip']) : '' ?></td>
                    <td><?= $log['user_agent'] ? htmlspecialchars($log['user_agent']) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>
