<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Нет данных']);
    exit;
}

$is_logged_in = isset($_SESSION['application_id']);
$user_id = $is_logged_in ? $_SESSION['application_id'] : null;
$pdo = getDB();
$pdo->beginTransaction();

$full_name = trim($data['full_name'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');
$address = trim($data['address'] ?? '');
$message = trim($data['message'] ?? '');
$delivery_cost = (int)($data['delivery_cost'] ?? 0);
$items = $data['items'] ?? [];

if (empty($full_name) || empty($phone) || empty($email) || empty($address) || empty($items)) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => 'Заполните все обязательные поля']);
    exit;
}

$application_id = null;
$generated_login = null;
$generated_password = null;

if (!$is_logged_in) {
    $login = generate_unique_login($pdo);
    $plain_password = generate_password();
    $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO application (full_name, phone, email, login, password_hash) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $phone, $email, $login, $password_hash]);
    $application_id = $pdo->lastInsertId();
    $generated_login = $login;
    $generated_password = $plain_password;
    $_SESSION['application_id'] = $application_id;
} else {
    $application_id = $user_id;
    $stmt = $pdo->prepare("UPDATE application SET full_name = ?, phone = ?, email = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $email, $application_id]);
}

$total = 0;
foreach ($items as $item) {
    $product_id = (int)($item['product_id'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 0);
    $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) continue;
    $options = $item['options'] ?? [];
    $extra = 0;
    if (!empty($options['cheese'])) $extra += 50;
    if (!empty($options['sauce'])) $extra += 30;
    if (!empty($options['meat'])) $extra += 100;
    if (!empty($options['set'])) $extra += 150;
    $price = ($product['base_price'] + $extra) * $quantity;
    $total += $price;
}
$total += $delivery_cost;

$session_token = getSessionToken();
$stmt = $pdo->prepare("INSERT INTO orders (application_id, session_token, full_name, phone, email, address, message, delivery_cost, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')");
$stmt->execute([$application_id, $session_token, $full_name, $phone, $email, $address, $message, $delivery_cost, $total]);
$order_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, options_json, price_per_unit) VALUES (?, ?, ?, ?, ?)");
foreach ($items as $item) {
    $product_id = (int)($item['product_id'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 0);
    $options = $item['options'] ?? [];
    $stmt->execute([$order_id, $product_id, $quantity, json_encode($options), 0]);
}

$pdo->commit();

http_response_code(201);
echo json_encode([
    'status' => 'ok',
    'order_id' => $order_id,
    'total' => $total,
    'login' => $generated_login,
    'password' => $generated_password
]);
?>