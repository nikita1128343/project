<?php
require_once 'db.php';

function validateOrderData($data, &$clean_data) {
    $errors = [];

    $full_name = trim($data['full_name'] ?? '');
    if (empty($full_name)) {
        $errors['full_name'] = 'Введите ваше имя и фамилию.';
    } elseif (!preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name)) {
        $errors['full_name'] = 'Имя должно содержать только буквы и пробелы.';
    } elseif (strlen($full_name) > 150) {
        $errors['full_name'] = 'Имя не должно превышать 150 символов.';
    } else {
        $clean_data['full_name'] = $full_name;
    }

    $phone = trim($data['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = 'Введите номер телефона.';
    } elseif (!preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone)) {
        $errors['phone'] = 'Телефон должен содержать от 6 до 20 символов (допустимы +, -, (, ), пробел).';
    } else {
        $clean_data['phone'] = $phone;
    }

    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Введите email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email.';
    } else {
        $clean_data['email'] = $email;
    }

    $address = trim($data['address'] ?? '');
    if (empty($address)) {
        $errors['address'] = 'Введите адрес доставки.';
    } elseif (strlen($address) > 200) {
        $errors['address'] = 'Адрес не должен превышать 200 символов.';
    } else {
        $clean_data['address'] = $address;
    }

    $message = trim($data['message'] ?? '');
    if (strlen($message) > 1000) {
        $errors['message'] = 'Сообщение не должно превышать 1000 символов.';
    } else {
        $clean_data['message'] = $message;
    }

    $delivery_cost = (int)($data['delivery_cost'] ?? 0);
    if ($delivery_cost < 0 || $delivery_cost > 1000) {
        $errors['delivery_cost'] = 'Неверная стоимость доставки';
    } else {
        $clean_data['delivery_cost'] = $delivery_cost;
    }

    if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        $errors['items'] = 'Заказ не может быть пустым.';
    } else {
        $clean_items = [];
        foreach ($data['items'] as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            $options = $item['options'] ?? [];

            if ($product_id <= 0 || $quantity <= 0) continue;

            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                $errors['items'] = 'Неверный товар.';
                break;
            }

            $base_price = $product['base_price'];
            $extra = 0;
            if (isset($options['cheese'])) $extra += 50;
            if (isset($options['sauce'])) $extra += 30;
            if (isset($options['meat'])) $extra += 100;
            if (isset($options['set'])) $extra += 150;

            $price_per_unit = $base_price + $extra;
            $clean_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'options' => $options,
                'price_per_unit' => $price_per_unit
            ];
        }
        if (empty($clean_items)) {
            $errors['items'] = 'Добавьте хотя бы одну позицию.';
        } else {
            $clean_data['items'] = $clean_items;
        }
    }
    return $errors;
}

function createOrder($data, $is_logged_in, $user_id = null) {
    $pdo = getDB();
    $pdo->beginTransaction();

    $errors = validateOrderData($data, $clean);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $session_token = getSessionToken();
    $application_id = null;
    $generated_login = null;
    $generated_password = null;

    if (!$is_logged_in) {
        $login = generate_unique_login($pdo);
        $plain_password = generate_password();
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO application (full_name, phone, email, login, password_hash)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $clean['full_name'], $clean['phone'], $clean['email'],
            $login, $password_hash
        ]);
        $application_id = $pdo->lastInsertId();
        $generated_login = $login;
        $generated_password = $plain_password;

        $_SESSION['application_id'] = $application_id;
    } else {
        $application_id = $user_id;
        $stmt = $pdo->prepare("
            UPDATE application SET full_name = ?, phone = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([$clean['full_name'], $clean['phone'], $clean['email'], $application_id]);
    }

    $total = 0;
    foreach ($clean['items'] as $item) {
        $total += $item['quantity'] * $item['price_per_unit'];
    }
    $total += $clean['delivery_cost'];

    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (application_id, session_token, full_name, phone, email, address, message, delivery_cost, total_price, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute([
        $application_id, $session_token,
        $clean['full_name'], $clean['phone'], $clean['email'],
        $clean['address'], $clean['message'], $clean['delivery_cost'], $total
    ]);
    $order_id = $pdo->lastInsertId();

    $stmt_item = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, options_json, price_per_unit)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($clean['items'] as $item) {
        $options_json = json_encode($item['options']);
        $stmt_item->execute([
            $order_id, $item['product_id'], $item['quantity'],
            $options_json, $item['price_per_unit']
        ]);
    }

    $pdo->commit();
    return [
        'success' => true,
        'order_id' => $order_id,
        'total' => $total,
        'generated_login' => $generated_login,
        'generated_password' => $generated_password
    ];
}

function updateOrder($order_id, $data, $user_id) {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT application_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || $order['application_id'] != $user_id) {
            return ['success' => false, 'errors' => ['auth' => 'Доступ запрещён']];
        }

        $errors = validateOrderData($data, $clean);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $total = 0;
        foreach ($clean['items'] as $item) {
            $total += $item['quantity'] * $item['price_per_unit'];
        }
        $total += $clean['delivery_cost'];

        $stmt = $pdo->prepare("
            UPDATE orders 
            SET full_name = ?, phone = ?, email = ?, address = ?, message = ?, delivery_cost = ?, total_price = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $clean['full_name'], $clean['phone'], $clean['email'],
            $clean['address'], $clean['message'], $clean['delivery_cost'], $total, $order_id
        ]);

        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
        $stmt_item = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, options_json, price_per_unit)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($clean['items'] as $item) {
            $stmt_item->execute([
                $order_id, $item['product_id'], $item['quantity'],
                json_encode($item['options']), $item['price_per_unit']
            ]);
        }

        $pdo->commit();
        return ['success' => true, 'order_id' => $order_id, 'total' => $total];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'errors' => ['db' => $e->getMessage()]];
    }
}

function getUserOrders($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT o.*, 
            (SELECT JSON_ARRAYAGG(
                JSON_OBJECT('product_id', oi.product_id, 'quantity', oi.quantity, 
                            'options', oi.options_json, 'price_per_unit', oi.price_per_unit)
            ) FROM order_items oi WHERE oi.order_id = o.id) as items_json
        FROM orders o
        WHERE o.application_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as &$order) {
        $order['items'] = json_decode($order['items_json'] ?? '[]', true);
        unset($order['items_json']);
    }
    return $orders;
}

function getOrderById($order_id, $user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT o.*, 
            (SELECT JSON_ARRAYAGG(
                JSON_OBJECT('product_id', oi.product_id, 'quantity', oi.quantity, 
                            'options', oi.options_json, 'price_per_unit', oi.price_per_unit)
            ) FROM order_items oi WHERE oi.order_id = o.id) as items_json
        FROM orders o
        WHERE o.id = ? AND o.application_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        $order['items'] = json_decode($order['items_json'] ?? '[]', true);
        unset($order['items_json']);
    }
    return $order;
}

function deleteOrder($order_id, $user_id) {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND application_id = ?");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return ['success' => false, 'errors' => ['auth' => 'Заказ не найден']];
        }
        if ($order['status'] !== 'new') {
            return ['success' => false, 'errors' => ['status' => 'Можно удалять только заказы в статусе "new"']];
        }
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['db' => $e->getMessage()]];
    }
}
?>