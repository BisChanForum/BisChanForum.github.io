<?php

if (!defined('ERROR_LOGGER_READY')) {
    define('ERROR_LOGGER_READY', true);

    function error_log_db()
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        try {
            $host = 'localhost';
            $dbname = 'izekbibm_forum_e';
            $user = 'izekbibm_forum_e';
            $pass = '68LUqj0YFm*A';
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } catch (Throwable $e) {
            $pdo = null;
        }

        return $pdo;
    }

    function log_error_to_db($level, $message, $file, $line, $trace)
    {
        $pdo = error_log_db();
        if (!$pdo) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO error_logs (level, message, file, line, trace, created_at)
                 VALUES (:level, :message, :file, :line, :trace, :created_at)'
            );
            $stmt->execute(array(
                ':level' => $level,
                ':message' => $message,
                ':file' => $file,
                ':line' => (int)$line,
                ':trace' => $trace,
                ':created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            return;
        }
    }

    function error_level_name($severity)
    {
        $map = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );
        return isset($map[$severity]) ? $map[$severity] : 'E_UNKNOWN';
    }

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        log_error_to_db(error_level_name($severity), $message, $file, $line, null);
        return false;
    });

    set_exception_handler(function ($exception) {
        $trace = $exception->getTraceAsString();
        log_error_to_db('EXCEPTION', $exception->getMessage(), $exception->getFile(), $exception->getLine(), $trace);
    });

    register_shutdown_function(function () {
        $error = error_get_last();
        if (!$error) {
            return;
        }
        $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }
        log_error_to_db(error_level_name($error['type']), $error['message'], $error['file'], $error['line'], null);
    });
}
