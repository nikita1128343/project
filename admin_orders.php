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
            error_log("Database error: " . $e->getMessage());
            die("Внутренняя ошибка сервера");
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

// Обработка действий
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
            $pdo->beginTransaction();
            
            // Пересчитываем общую сумму
            $total = 0;
            $stmt = $pdo->prepare("SELECT price_per_unit, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $total += $item['price_per_unit'] * $item['quantity'];
            }
            $total += $delivery_cost;
            
            $stmt = $pdo->prepare("UPDATE orders SET full_name = ?, phone = ?, email = ?, address = ?, message = ?, status = ?, delivery_cost = ?, total_price = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $email, $address, $message, $status, $delivery_cost, $total, $id]);
            $pdo->commit();
            $messages[] = '<div class="success-message">Заказ №' . $id . ' успешно обновлён</div>';
            $edit_id = 0;
        } catch (Exception $e) {
            $pdo->rollBack();
            $messages[] = '<div class="error-message">Ошибка при обновлении: ' . $e->getMessage() . '</div>';
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

// Статистика
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .btn-danger { background: #c62828; }
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
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
                <?= $msg ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">Все заказы</a>
        <a href="?filter=today" class="<?= $filter === 'today' ? 'active' : '' ?>">За сегодня (<?= $today_orders ?>)</a>
    </div>

    <p>Всего заказов: <?= $total_orders ?></p>

    <?php if ($edit_id > 0 && $edit_order): ?>
        <div class="edit-form">
            <h2>Редактирование заказа №<?= $edit_id ?></h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($edit_order['full_name'] ?? '') ?>" class="<?= isset($edit_errors['full_name']) ? 'error-field' : '' ?>">
                    <?php if (isset($edit_errors['full_name'])): ?>
                        <span class="field-error"><?= $edit_errors['full_name'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($edit_order['phone'] ?? '') ?>" class="<?= isset($edit_errors['phone']) ? 'error-field' : '' ?>">
                    <?php if (isset($edit_errors['phone'])): ?>
                        <span class="field-error"><?= $edit_errors['phone'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_order['email'] ?? '') ?>" class="<?= isset($edit_errors['email']) ? 'error-field' : '' ?>">
                    <?php if (isset($edit_errors['email'])): ?>
                        <span class="field-error"><?= $edit_errors['email'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Адрес *</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($edit_order['address'] ?? '') ?>" class="<?= isset($edit_errors['address']) ? 'error-field' : '' ?>">
                    <?php if (isset($edit_errors['address'])): ?>
                        <span class="field-error"><?= $edit_errors['address'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Пожелания</label>
                    <textarea name="message" rows="3"><?= htmlspecialchars($edit_order['message'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Стоимость доставки</label>
                    <input type="number" name="delivery_cost" value="<?= $edit_order['delivery_cost'] ?? 0 ?>" step="10">
                </div>
                <div class="form-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="new" <?= ($edit_order['status'] ?? '') === 'new' ? 'selected' : '' ?>>Новый</option>
                        <option value="processed" <?= ($edit_order['status'] ?? '') === 'processed' ? 'selected' : '' ?>>В обработке</option>
                        <option value="completed" <?= ($edit_order['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Выполнен</option>
                        <option value="cancelled" <?= ($edit_order['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                    </select>
                    <?php if (isset($edit_errors['status'])): ?>
                        <span class="field-error"><?= $edit_errors['status'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Состав заказа (для информации)</label>
                    <ul>
                        <?php if (!empty($edit_order['items'])): ?>
                            <?php foreach ($edit_order['items'] as $item): ?>
                                <li>Товар ID <?= $item['product_id'] ?>, кол-во: <?= $item['quantity'] ?>, цена: <?= $item['price_per_unit'] ?> ₽</li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>Нет данных о позициях</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <button type="submit" class="btn">Сохранить изменения</button>
                <a href="?filter=<?= $filter ?>" class="btn" style="background: #555;">Отмена</a>
            </form>
        </div>
    <?php endif; ?>

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
                    <td>
                        <span style="color: <?= 
                            $order['status'] === 'new' ? '#ff9800' : 
                            ($order['status'] === 'processed' ? '#2196f3' : 
                            ($order['status'] === 'completed' ? '#4caf50' : '#f44336')) ?>">
                            <?= $order['status'] === 'new' ? 'Новый' : ($order['status'] === 'processed' ? 'В обработке' : ($order['status'] === 'completed' ? 'Выполнен' : 'Отменён')) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="?edit=<?= $order['id'] ?>&filter=<?= $filter ?>">✏️ Ред.</a>
                        <a href="?delete=<?= $order['id'] ?>&filter=<?= $filter ?>" onclick="return confirm('Удалить заказ №<?= $order['id'] ?>?')">🗑️ Удалить</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="9" style="text-align: center;">Нет заказов</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="filter-bar" style="margin-top: 30px; text-align: center;">
        <a href="index.php">← Вернуться на главную</a>
    </div>
</div>
</body>
</html>