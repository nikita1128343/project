<?php
session_start();
if (isset($_SESSION['application_id'])) {
    header('Location: index.php');
    exit();
}

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
            die("Внутренняя ошибка сервера");
        }
    }
    return $pdo;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($login && $password) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM application WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['application_id'] = $user['id'];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните оба поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 480px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--card-bg, #1e1e1e);
            border-radius: var(--border-radius, 16px);
            border: 1px solid var(--border-color, #37474f);
            box-shadow: var(--shadow, 0 6px 16px rgba(0,0,0,0.5));
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--dark-color, #e0e0e0);
        }
        .login-container .form-group {
            margin-bottom: 20px;
        }
        .login-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color, #e0e0e0);
        }
        .login-container input {
            width: 100%;
            padding: 14px;
            background-color: #2c2c2c;
            border: 2px solid var(--border-color, #37474f);
            border-radius: 10px;
            font-family: 'Nunito', sans-serif;
            font-size: 1rem;
            color: var(--text-color, #e0e0e0);
            transition: var(--transition, all 0.3s ease);
        }
        .login-container input:focus {
            border-color: var(--accent-color, #2e7d32);
            outline: none;
        }
        .login-container .btn {
            width: 100%;
            padding: 14px;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        .login-container .filter-bar {
            margin-top: 25px;
            text-align: center;
        }
        .login-container .filter-bar a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2c2c2c;
            border-radius: 30px;
            color: var(--text-color, #e0e0e0);
            text-decoration: none;
            transition: var(--transition, all 0.3s ease);
        }
        .login-container .filter-bar a:hover {
            background-color: var(--accent-color, #2e7d32);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Вход в систему</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Войти</button>
        </form>
        <div class="filter-bar" style="margin-top: 30px; text-align: center;">
            <a href="index.php">← Вернуться на главную</a>
        </div>
    </div>
</body>
</html>