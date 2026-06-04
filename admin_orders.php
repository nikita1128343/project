<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

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
            die("Ошибка подключения к БД");
        }
    }
    return $pdo;
}

$pdo = getDB();

// HTTP-авторизация
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Админ-панель заказов"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<div style="font-family: sans-serif; text-align: center; margin-top: 50px;"><h1>Доступ запрещён</h1><p>Введите логин и пароль администратора.</p></div>';
    exit;
}

$auth_login = $_SERVER['PHP_AUTH_USER'];
$auth_pass  = $_SERVER['PHP_AUTH_PW'];

$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin_row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_row || !password_verify($auth_pass, $admin_row['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Админ-панель заказов"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<div style="font-family: sans-serif; text-align: center; margin-top: 50px;"><h1>Неверный логин или пароль!</h1><p>Попробуйте ещё раз.</p></div>';
    exit;
}

// Фильтрация
$filter = $_GET['filter'] ?? 'all';
$today = date('Y-m-d');
$where = '';
if ($filter === 'today') {
    $where = "WHERE DATE(o.created_at) = '$today'";
}

// Удаление заказа
$messages = [];
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $messages[] = '<div class="success-message">Заказ №' . $id . ' успешно удалён</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $messages[] = '<div class="error-message">Ошибка удаления</div>';
    }
}

// Загрузка заказов
$sql = "
    SELECT o.*, 
        (SELECT JSON_ARRAYAGG(
            JSON_OBJECT('product_id', oi.product_id, 'quantity', oi.quantity, 
                        'options', oi.options_json, 'price_per_unit', oi.price_per_unit)
        ) FROM order_items oi WHERE oi.order_id = o.id) as items_json
    FROM orders o
    $where
    ORDER BY o.created_at DESC
";
$stmt = $pdo->query($sql);
$orders = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['items'] = json_decode($row['items_json'] ?? '[]', true);
    unset($row['items_json']);
    $orders[] = $row;
}

$total_orders = count($orders);
$today_orders = 0;
if ($filter === 'all') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'");
    $today_orders = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель заказов</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1e1e1e; color: #fff; padding: 20px; }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .filter-bar { margin: 20px 0; }
        .filter-bar a { margin-right: 15px; padding: 8px 16px; background: #2c2c2c; color: #fff; text-decoration: none; border-radius: 8px; }
        .filter-bar a.active { background: #2e7d32; }
        table { width: 100%; border-collapse: collapse; background: #2c2c2c; }
        th, td { padding: 12px; border: 1px solid #444; text-align: left; }
        th { background: #0a2e0a; }
        .success-message { background: #2e7d32; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .error-message { background: #c62828; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .actions a { margin-right: 10px; color: #ff9800; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>🔧 Админ-панель заказов</h1>
    <p>Авторизован как <strong><?= htmlspecialchars($auth_login) ?></strong></p>

    <?php foreach ($messages as $msg) echo $msg; ?>

    <div class="filter-bar">
        <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">Все заказы</a>
        <a href="?filter=today" class="<?= $filter === 'today' ? 'active' : '' ?>">За сегодня (<?= $today_orders ?>)</a>
    </div>

    <p>Всего заказов: <?= $total_orders ?></p>

    <table>
        <thead><tr><th>ID</th><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Email</th><th>Адрес</th><th>Сумма</th><th>Статус</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td><?= $order['id'] ?></td>
            <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
            <td><?= htmlspecialchars($order['full_name']) ?></td>
            <td><?= htmlspecialchars($order['phone']) ?></td>
            <td><?= htmlspecialchars($order['email']) ?></td>
            <td><?= htmlspecialchars($order['address']) ?></td>
            <td><?= $order['total_price'] ?> ₽</td>
            <td><?= $order['status'] ?></td>
            <td class="actions">
                <a href="?edit=<?= $order['id'] ?>&filter=<?= $filter ?>">✏️ Ред.</a>
                <a href="?delete=<?= $order['id'] ?>&filter=<?= $filter ?>" onclick="return confirm('Удалить?')">🗑 Удалить</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:30px; text-align:center;"><a href="index.php">← Вернуться на главную</a></div>
</div>
</body>
</html>