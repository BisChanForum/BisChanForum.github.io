<?php
function log_db()
{
    static $pdo = null;
    static $shutdownRegistered = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = 'localhost';
    $dbname = 'izekbibm_logsfor';
    $user = 'izekbibm_logsfor';
    $pass = 'sR*mr6Q6bDhX';
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS action_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_user_id INT NULL,
        actor_username VARCHAR(191) NULL,
        action VARCHAR(64) NOT NULL,
        entity_type VARCHAR(64) NULL,
        entity_id INT NULL,
        details TEXT NULL,
        ip VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        INDEX (actor_user_id),
        INDEX (action),
        INDEX (entity_type),
        INDEX (entity_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    return $pdo;
}

function log_action($action, $actorUserId = null, $actorUsername = null, $entityType = null, $entityId = null, $details = null)
{
    try {
        $db = log_db();
        $stmt = $db->prepare('INSERT INTO action_logs (actor_user_id, actor_username, action, entity_type, entity_id, details, ip, user_agent, created_at)
                              VALUES (:actor_id, :actor_name, :action, :entity_type, :entity_id, :details, :ip, :user_agent, :created_at)');
        $stmt->execute(array(
            ':actor_id' => $actorUserId,
            ':actor_name' => $actorUsername,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
            ':ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            ':user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            ':created_at' => date('Y-m-d H:i:s'),
        ));
    } catch (Throwable $e) {
        // Keep main flow working even if logging fails.
    }
}
