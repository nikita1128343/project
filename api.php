<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once 'db.php';
require_once 'order_functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';

$input_json = null;
if ($method === 'POST' && empty($_POST)) {
    $input_json = json_decode(file_get_contents('php://input'), true);
}

if ($route === 'order') {
    if ($method === 'POST') {
        $data = $input_json ?? $_POST;
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Нет данных']);
            exit;
        }
        $is_logged = isset($_SESSION['application_id']);
        $user_id = $is_logged ? $_SESSION['application_id'] : null;
        $result = createOrder($data, $is_logged, $user_id);
        if ($result['success']) {
            http_response_code(201);
            echo json_encode([
                'status' => 'ok',
                'order_id' => $result['order_id'],
                'total' => $result['total'],
                'login' => $result['generated_login'] ?? null,
                'password' => $result['generated_password'] ?? null
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['errors' => $result['errors']]);
        }
        exit;
    }
    else {
        http_response_code(405);
        echo json_encode(['error' => 'Метод не разрешён']);
        exit;
    }
}
elseif ($route === 'orders' && $method === 'GET') {
    if (!isset($_SESSION['application_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Требуется авторизация']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'orders' => getUserOrders($_SESSION['application_id'])]);
    exit;
}
else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint не найден']);
    exit;
}
?>