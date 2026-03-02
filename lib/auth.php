<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function secure_random_bytes($length)
{
    $length = (int)$length;
    if ($length <= 0) {
        return '';
    }
    if (function_exists('random_bytes')) {
        return random_bytes($length);
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return openssl_random_pseudo_bytes($length);
    }
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }
    return $bytes;
}

function user_columns(PDO $db)
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }
    $columns = $db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
    return $columns;
}

function resolve_user_column(PDO $db, array $candidates, $fallback)
{
    $columns = user_columns($db);
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return $fallback;
}

function user_display_name_column(PDO $db)
{
    return resolve_user_column($db, array('display_name', 'DisplayName'), 'display_name');
}

function user_avatar_column(PDO $db)
{
    return resolve_user_column($db, array('avatar', 'Avatar'), 'avatar');
}

function user_bio_column(PDO $db)
{
    return resolve_user_column($db, array('bio', 'Bio'), 'bio');
}

function user_website_column(PDO $db)
{
    return resolve_user_column($db, array('website', 'Website'), 'website');
}

function user_select_fields(PDO $db)
{
    $displayColumn = user_display_name_column($db);
    $avatarColumn = user_avatar_column($db);
    $columns = user_columns($db);
    $bioColumn = user_bio_column($db);
    $websiteColumn = user_website_column($db);
    $bioField = in_array($bioColumn, $columns, true) ? "{$bioColumn} AS bio" : "'' AS bio";
    $websiteField = in_array($websiteColumn, $columns, true) ? "{$websiteColumn} AS website" : "'' AS website";
    return "id, username, is_admin, is_banned, {$avatarColumn} AS avatar, {$displayColumn} AS display_name, {$bioField}, {$websiteField}, created_at";
}

function current_user(PDO $db)
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $db->prepare('SELECT ' . user_select_fields($db) . ' FROM users WHERE id = :id');
    $stmt->execute(array(':id' => $_SESSION['user_id']));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }
    if ((int)$user['is_banned'] === 1) {
        unset($_SESSION['user_id']);
        $_SESSION['banned_notice'] = 1;
        return null;
    }
    return $user;
}

function not_current_user_by_id(PDO $db, $id)
{
    $stmt = $db->prepare('SELECT ' . user_select_fields($db) . ' FROM users WHERE id = :id');
    $stmt->execute(array(':id' => $id));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }
    return $user;
}

function not_current_user_by_username(PDO $db, $username)
{
    $stmt = $db->prepare('SELECT ' . user_select_fields($db) . ' FROM users WHERE username = :username');
    $stmt->execute(array(':username' => $username));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }
    return $user;
}

function require_login(PDO $db)
{
    $user = current_user($db);
    if (!$user) {
        if (isset($_SESSION['banned_notice'])) {
            header('Location: login.php?banned=1');
            exit;
        }
        header('Location: login.php');
        exit;
    }
    return $user;
}

function is_admin($user)
{
    return $user && isset($user['is_admin']) && (int)$user['is_admin'] === 1;
}

function require_admin(PDO $db)
{
    $user = require_login($db);
    if (!is_admin($user)) {
        http_response_code(403);
        echo 'Доступ запрещен.';
        exit;
    }
    return $user;
}

function csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(secure_random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf()
{
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $sessionToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (!$token || !hash_equals($sessionToken, $token)) {
        http_response_code(400);
        echo 'Некорректный CSRF токен.';
        exit;
    }
}

function avatar_url($user)
{
    $filename = '';
    if ($user && isset($user['avatar'])) {
        $filename = (string)$user['avatar'];
    }
    if ($filename === '') {
        $filename = 'Default.jpg';
    }
    $filename = basename($filename);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        $filename = 'Default.jpg';
    }
    return 'avatars/' . $filename;
}

function can_edit_owned($currentUser, $ownerId, $createdAt, $minutes)
{
    if (!$currentUser) {
        return false;
    }
    if (is_admin($currentUser)) {
        return true;
    }
    if ((int)$currentUser['id'] !== (int)$ownerId) {
        return false;
    }
    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
        return false;
    }
    $deadline = $timestamp + ((int)$minutes * 60);
    return time() <= $deadline;
}

