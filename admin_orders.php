<?php
header('Content-Type: text/html; charset=UTF-8');

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
            die("Ошибка подключения к БД: " . $e->getMessage());
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

// Фильтр
$filter = $_GET['filter'] ?? 'all';
$today = date('Y-m-d');
$where = $filter === 'today' ? "WHERE DATE(o.created_at) = '$today'" : '';

$messages = [];
$edit_errors = [];

// Удаление заказа
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
        $messages[] = '<div class="error-message">Ошибка удаления: ' . $e->getMessage() . '</div>';
    }
}

// Редактирование заказа
$edit_id = 0;
$edit_order = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_order) {
        $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt_items->execute([$edit_id]);
        $edit_order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Обработка сохранения редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $status = $_POST['status'] ?? 'new';
    $delivery_cost = (int)($_POST['delivery_cost'] ?? 0);

    $has_error = false;
    $edit_errors = [];

    if (empty($full_name)) {
        $edit_errors['full_name'] = 'Имя обязательно.';
        $has_error = true;
    }
    if (empty($phone)) {
        $edit_errors['phone'] = 'Телефон обязателен.';
        $has_error = true;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $edit_errors['email'] = 'Корректный email обязателен.';
        $has_error = true;
    }
    if (empty($address)) {
        $edit_errors['address'] = 'Адрес обязателен.';
        $has_error = true;
    }
    $allowed_statuses = ['new', 'processed', 'completed', 'cancelled'];
    if (!in_array($status, $allowed_statuses)) {
        $edit_errors['status'] = 'Недопустимый статус.';
        $has_error = true;
    }

    if ($has_error) {
        $edit_order = [
            'id' => $id,
            'full_name' => $full_name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'message' => $message,
            'status' => $status,
            'delivery_cost' => $delivery_cost,
            'total_price' => 0
        ];
        $edit_id = $id;
        $messages[] = '<div class="error-message">Исправьте ошибки в форме.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET full_name = ?, phone = ?, email = ?, address = ?, message = ?, status = ?, delivery_cost = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $phone, $email, $address, $message, $status, $delivery_cost, $id])) {
                $messages[] = '<div class="success-message">Заказ №' . $id . ' успешно обновлён</div>';
                $edit_id = 0;
                // Перенаправляем, чтобы убрать POST из истории
                header("Location: admin_orders.php?filter=" . $filter);
                exit;
            } else {
                $messages[] = '<div class="error-message">Ошибка при обновлении</div>';
            }
        } catch (Exception $e) {
            $messages[] = '<div class="error-message">Ошибка: ' . $e->getMessage() . '</div>';
        }
    }
}

// Загрузка списка заказов
$orders = [];
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
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .filter-bar { margin: 20px 0; }
        .filter-bar a { margin-right: 15px; padding: 8px 16px; background: #2c2c2c; color: #fff; text-decoration: none; border-radius: 8px; }
        .filter-bar a.active { background: #2e7d32; }
        .admin-table { width: 100%; border-collapse: collapse; background: #1e1e1e; }
        .admin-table th, .admin-table td { padding: 12px; border: 1px solid #333; text-align: left; }
        .admin-table th { background: #0a2e0a; color: white; }
        .actions a { margin-right: 10px; color: #ff9800; }
        .edit-form { background: #2c2c2c; padding: 20px; border-radius: 12px; margin-bottom: 30px; }
        .edit-form .form-group { margin-bottom: 15px; }
        .edit-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .edit-form input, .edit-form select, .edit-form textarea { width: 100%; padding: 8px; background: #1e1e1e; border: 1px solid #555; color: #fff; border-radius: 4px; }
        .btn { padding: 8px 16px; background: #2e7d32; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .success-message { background: #2e7d32; color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .error-message { background: #c62828; color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .field-error { color: #ff9800; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>🔧 Админ-панель заказов</h1>
    <p>Авторизован как <strong><?= htmlspecialchars($auth_login) ?></strong></p>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg) echo $msg; ?>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">Все заказы</a>
        <a href="?filter=today" class="<?= $filter === 'today' ? 'active' : '' ?>">За сегодня (<?= $today_orders ?>)</a>
    </div>

    <p>Всего заказов: <?= $total_orders ?></p>

    <!-- Редактирование заказа -->
    <?php if ($edit_id > 0 && $edit_order): ?>
        <div class="edit-form">
            <h2>Редактирование заказа №<?= $edit_id ?></h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($edit_order['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($edit_order['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_order['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Адрес *</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($edit_order['address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Пожелания</label>
                    <textarea name="message"><?= htmlspecialchars($edit_order['message'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="new" <?= ($edit_order['status'] ?? '') === 'new' ? 'selected' : '' ?>>Новый</option>
                        <option value="processed" <?= ($edit_order['status'] ?? '') === 'processed' ? 'selected' : '' ?>>В обработке</option>
                        <option value="completed" <?= ($edit_order['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Выполнен</option>
                        <option value="cancelled" <?= ($edit_order['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Стоимость доставки</label>
                    <input type="number" name="delivery_cost" value="<?= $edit_order['delivery_cost'] ?? 0 ?>" step="10">
                </div>
                <button type="submit" class="btn">Сохранить изменения</button>
                <a href="?filter=<?= $filter ?>" class="btn" style="background: #555;">Отмена</a>
            </form>
        </div>
    <?php endif; ?>

    <!-- Таблица заказов -->
    <h2>Список заказов</h2>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Клиент</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Адрес</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
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
                        <a href="?delete=<?= $order['id'] ?>&filter=<?= $filter ?>" onclick="return confirm('Удалить заказ №<?= $order['id'] ?>?')">🗑 Удалить</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="text-align:center; margin-top:30px;">
        <a href="index.php">← Вернуться на главную</a>
    </div>
</div>
</body>
</html>