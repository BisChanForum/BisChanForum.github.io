<?php
require_once __DIR__ . '/error_log.php';

if (version_compare(PHP_VERSION, '5.6.40', '<')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'This application requires PHP 5.6.40 or newer.';
    exit;
}

function db()
{
    static $pdo = null;
    static $shutdownRegistered = false;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $mysqlConfig = __DIR__ . '/../MySQL';
    if (is_file($mysqlConfig)) {
        $lines = file($mysqlConfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $values = array();
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            $values[] = trim(isset($parts[1]) ? $parts[1] : '');
        }

        $host = isset($values[0]) ? $values[0] : 'localhost';
        $dbname = isset($values[1]) ? $values[1] : 'izekbibm_forum2';
        $user = isset($values[2]) ? $values[2] : 'izekbibm_forum2';
        $pass = isset($values[3]) ? $values[3] : 'T69XZR6lFxR&';
    } else {
        $host = 'localhost';
        $dbname = 'izekbibm_forum2';
        $user = 'izekbibm_forum2';
        $pass = 'T69XZR6lFxR&';
    }

    $port = 3306;
    if (strpos($host, ':') !== false) {
        list($host, $portStr) = explode(':', $host, 2);
        $port = (int)$portStr;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));

    if (!$shutdownRegistered) {
        $shutdownRegistered = true;
        register_shutdown_function(function () use (&$pdo) {
            $pdo = null;
        });
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(191) UNIQUE NOT NULL,
        display_name VARCHAR(191) NOT NULL DEFAULT \'\',
        bio TEXT,
        website VARCHAR(255) NOT NULL DEFAULT \'\',
        password_hash VARCHAR(255) NOT NULL,
        avatar VARCHAR(191) NOT NULL DEFAULT \'Default.jpg\',
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        is_banned TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('display_name', $userColumns, true) && !in_array('DisplayName', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN display_name VARCHAR(191) NOT NULL DEFAULT \'\'');
    }
    if (!in_array('avatar', $userColumns, true) && !in_array('Avatar', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(191) NOT NULL DEFAULT \'Default.jpg\'');
    }
    if (!in_array('bio', $userColumns, true) && !in_array('Bio', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN bio TEXT');
    }
    if (!in_array('website', $userColumns, true) && !in_array('Website', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN website VARCHAR(255) NOT NULL DEFAULT \'\'');
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS topics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        is_pinned TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT NULL,
        body TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX (topic_id),
        INDEX (user_id),
        INDEX (parent_id),
        FOREIGN KEY (topic_id) REFERENCES topics(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (parent_id) REFERENCES replies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        actor_id INT NOT NULL,
        topic_id INT NOT NULL,
        reply_id INT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX (user_id),
        INDEX (user_id, is_read),
        INDEX (topic_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (actor_id) REFERENCES users(id),
        FOREIGN KEY (topic_id) REFERENCES topics(id),
        FOREIGN KEY (reply_id) REFERENCES replies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    return $pdo;
}
