<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once 'db.php';
require_once 'order_functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';

// Эмуляция PUT/DELETE через POST + _method
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}
// Для JSON-запросов
$input_json = null;
if ($method === 'POST' && empty($_POST)) {
    $input_json = json_decode(file_get_contents('php://input'), true);
    if (isset($input_json['_method'])) {
        $method = strtoupper($input_json['_method']);
        unset($input_json['_method']);
    }
}

if ($route === 'order') {
    // Создание заказа
    if ($method === 'POST' && !isset($_GET['id'])) {
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
    // Обновление заказа
    elseif (($method === 'PUT' || ($method === 'POST' && isset($_GET['_method']))) && isset($_GET['id'])) {
        if (!isset($_SESSION['application_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        $order_id = (int)$_GET['id'];
        $data = $input_json ?? $_POST;
        $result = updateOrder($order_id, $data, $_SESSION['application_id']);
        if ($result['success']) {
            echo json_encode(['status' => 'updated', 'order_id' => $result['order_id'], 'total' => $result['total']]);
        } else {
            http_response_code(400);
            echo json_encode(['errors' => $result['errors']]);
        }
        exit;
    }
    // Получение одного заказа
    elseif ($method === 'GET' && isset($_GET['id'])) {
        if (!isset($_SESSION['application_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        $order = getOrderById((int)$_GET['id'], $_SESSION['application_id']);
        if ($order) {
            echo json_encode(['status' => 'ok', 'order' => $order]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Заказ не найден']);
        }
        exit;
    }
    // Удаление заказа
    elseif (($method === 'DELETE' || ($method === 'POST' && isset($_GET['_method']) && $_GET['_method'] === 'DELETE')) && isset($_GET['id'])) {
        if (!isset($_SESSION['application_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        $result = deleteOrder((int)$_GET['id'], $_SESSION['application_id']);
        if ($result['success']) {
            echo json_encode(['status' => 'deleted']);
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
    echo json_encode(['error' => 'Endpoint не найден. Используйте ?route=order или ?route=orders']);
    exit;
}
?>