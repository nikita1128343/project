<?php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $db_host = 'localhost';
        $db_user = 'u82460';
        $db_pass = '1450175';
        $db_name = 'u82460';
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            die(json_encode(['error' => 'Ошибка подключения к БД']));
        }
    }
    return $pdo;
}

function generate_unique_login($pdo) {
    do {
        $login = 'user_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $stmt = $pdo->prepare("SELECT id FROM application WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

function generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

function getSessionToken() {
    if (!isset($_COOKIE['order_session'])) {
        $token = bin2hex(random_bytes(16));
        setcookie('order_session', $token, time() + 86400 * 30, '/');
        return $token;
    }
    return $_COOKIE['order_session'];
}
?>