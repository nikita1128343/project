<?php
session_start();
$is_logged_in = isset($_SESSION['application_id']);
$user_id = $is_logged_in ? $_SESSION['application_id'] : null;

// === ОБРАБОТКА API-ЗАПРОСОВ ===
if (isset($_GET['route'])) {
    error_reporting(0);
    header('Content-Type: application/json; charset=UTF-8');

    try {
        require_once __DIR__ . '/db.php';
        require_once __DIR__ . '/order_functions.php';
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка загрузки модулей: ' . $e->getMessage()]);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $route = $_GET['route'];

    // Эмуляция PUT/DELETE через POST + _method
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }
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
            $result = createOrder($data, $is_logged_in, $user_id);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode([
                    'status' => 'ok',
                    'order_id' => $result['order_id'],
                    'total' => $result['total'],
                    'login' => $result['generated_login'] ?? null,
                    'password' => $result['generated_password'] ?? null,
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['errors' => $result['errors']]);
            }
            exit;
        }
        // Обновление заказа (PUT)
        elseif (($method === 'PUT' || ($method === 'POST' && isset($_GET['_method']))) && isset($_GET['id'])) {
            if (!$is_logged_in) {
                http_response_code(401);
                echo json_encode(['error' => 'Требуется авторизация']);
                exit;
            }
            $order_id = (int)$_GET['id'];
            $data = $input_json ?? $_POST;
            $result = updateOrder($order_id, $data, $user_id);
            if ($result['success']) {
                echo json_encode(['status' => 'updated', 'order_id' => $result['order_id'], 'total' => $result['total']]);
            } else {
                http_response_code(400);
                echo json_encode(['errors' => $result['errors']]);
            }
            exit;
        }
        // Получение одного заказа (GET)
        elseif ($method === 'GET' && isset($_GET['id'])) {
            if (!$is_logged_in) {
                http_response_code(401);
                echo json_encode(['error' => 'Требуется авторизация']);
                exit;
            }
            $order = getOrderById((int)$_GET['id'], $user_id);
            if ($order) {
                echo json_encode(['status' => 'ok', 'order' => $order]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Заказ не найден']);
            }
            exit;
        }
        // Удаление заказа (DELETE)
        elseif (($method === 'DELETE' || ($method === 'POST' && isset($_GET['_method']) && $_GET['_method'] === 'DELETE')) && isset($_GET['id'])) {
            if (!$is_logged_in) {
                http_response_code(401);
                echo json_encode(['error' => 'Требуется авторизация']);
                exit;
            }
            $order_id = (int)$_GET['id'];
            $result = deleteOrder($order_id, $user_id);
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
        if (!$is_logged_in) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        echo json_encode(['status' => 'ok', 'orders' => getUserOrders($user_id)]);
        exit;
    }
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint не найден']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="icon" href="https://img.icons8.com/color/96/000000/kebab.png" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дёнер "Королевский" | Лучшая шаурма в городе</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
    /* Кнопка входа/выхода */
.login-btn, .logout-btn {
    background: linear-gradient(135deg, #2c2c2c, #1a1a1a);
    box-shadow: none;
}
.login-btn:hover, .logout-btn:hover {
    background: linear-gradient(135deg, #3e3e3e, #2a2a2a);
}
</style>
</head>
<body>
<!-- ========== HEADER ========== -->
<header>
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="video.mp4" type="video/mp4">
            Ваш браузер не поддерживает видео.
        </video>
        <div class="overlay"></div>
    </div>
    
    <nav>
        <a href="#" class="logo"><i class="fas fa-utensils"></i> Дёнер<span>Королевский</span></a>
        <ul class="nav-links">
            <li><a href="#"><i class="fas fa-home"></i> Главная</a></li>
            <li><a href="#menu"><i class="fas fa-hamburger"></i> Меню</a></li>
            <li><a href="#calculator"><i class="fas fa-calculator"></i> Калькулятор</a></li>
            <li><a href="#gallery"><i class="fas fa-images"></i> Галерея</a></li>
            <li><a href="#contact"><i class="fas fa-address-book"></i> Заказ</a></li>
          <?php if ($is_logged_in): ?>
        <li><a href="logout.php" class="btn logout-btn"><i class="fas fa-sign-out-alt"></i> Выйти</a></li>
    <?php else: ?>
        <li><a href="login.php" class="btn login-btn"><i class="fas fa-sign-in-alt"></i> Войти</a></li>
    <?php endif; ?>
            <!--<li><a href="#" class="btn contact-btn"><i class="fas fa-phone-alt"></i> Заказать</a></li> -->
        </ul>
        <div class="burger" id="burgerBtn">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </nav>
    
    <div class="hero">
        <h1>Дёнер "Королевский"</h1>
        <p>Настоящая шаурма по королевскому рецепту! Сочное мясо, свежие овощи и фирменные соусы. Приготовлено на открытом огне.</p>
        <a href="#menu" class="btn">Выбрать шаурму</a>
    </div>
</header>

<!-- ========== МЕНЮ ========== -->
<section id="menu" class="section">
    <div class="section-title">
        <h2>Королевское меню</h2>
        <p>Выберите свою идеальную шаурму с нашими свежими ингредиентами</p>
    </div>
    
    <div class="models-grid">
        <div class="model-card">
            <div class="model-img">
                <img src="https://i.pinimg.com/originals/cf/dd/2e/cfdd2e941e766c51fa6113c1c17f3b81.jpg" alt="Классическая шаурма">
            </div>
            <div class="model-info">
                <h3>Классическая шаурма</h3>
                <p>Сочная курица, свежие овощи, лаваш и фирменный соус. Классика жанра!</p>
                <div class="model-price">от 250 ₽</div>
                <div class="ingredients-picker">
                    <h4>Добавить ингредиенты:</h4>
                    <div class="ingredients-options">
                        <div class="ingredient-option active" data-product="1">Курица</div>
                        <div class="ingredient-option" data-product="1">Говядина</div>
                        <div class="ingredient-option" data-product="1">Свинина</div>
                        <div class="ingredient-option" data-product="1">Сыр +50₽</div>
                        <div class="ingredient-option" data-product="1">Грибы +30₽</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="model-card">
            <div class="model-img">
                <img src="https://static.tildacdn.com/stor6336-3463-4565-b063-653566633463/38836220.jpg" alt="Острая шаурма">
            </div>
            <div class="model-info">
                <h3>Острая шаурма</h3>
                <p>Для любителей поострее! Специи, острый перец и аджика по-кавказски.</p>
                <div class="model-price">от 280 ₽</div>
                <div class="ingredients-picker">
                    <h4>Выберите остроту:</h4>
                    <div class="ingredients-options">
                        <div class="ingredient-option active" data-product="2">Средняя 🌶️</div>
                        <div class="ingredient-option" data-product="2">Острая 🌶️🌶️</div>
                        <div class="ingredient-option" data-product="2">Очень острая 🌶️🌶️🌶️</div>
                        <div class="ingredient-option" data-product="2">Двойное мясо +100₽</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="model-card">
            <div class="model-img">
                <img src="https://cafehabibi.ru/d/vegan.jpg" alt="Вегетарианская шаурма">
            </div>
            <div class="model-info">
                <h3>Вегетарианская шаурма</h3>
                <p>Свежие овощи, грибы, сыр и соус песто. Без мяса, но очень вкусно!</p>
                <div class="model-price">от 220 ₽</div>
                <div class="ingredients-picker">
                    <h4>Выберите основу:</h4>
                    <div class="ingredients-options">
                        <div class="ingredient-option active" data-product="3">Овощная</div>
                        <div class="ingredient-option" data-product="3">С грибами</div>
                        <div class="ingredient-option" data-product="3">С сыром +50₽</div>
                        <div class="ingredient-option" data-product="3">Фалафель +70₽</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========== ТАБЛИЦА СРАВНЕНИЯ ========== -->
<section class="performance-models section">
    <div class="section-title">
        <h2>Сравнение наших позиций</h2>
        <p>Калорийность и состав наших самых популярных позиций</p>
    </div>
    
    <div class="table-container">
        <table class="performance-table">
            <thead>
                <tr>
                    <th>Позиция</th>
                    <th>Вес</th>
                    <th>Калории</th>
                    <th>Основной ингредиент</th>
                    <th>Время приготовления</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Классическая шаурма</td><td>350 г</td><td>450 ккал</td><td>Курица</td><td>5-7 мин</td></tr>
                <tr><td>Острая шаурма</td><td>380 г</td><td>520 ккал</td><td>Говядина</td><td>6-8 мин</td></tr>
                <tr><td>Вегетарианская</td><td>320 г</td><td>380 ккал</td><td>Овощи</td><td>4-6 мин</td></tr>
                <tr><td>Дёнер в лаваше</td><td>400 г</td><td>550 ккал</td><td>Смешанное мясо</td><td>7-9 мин</td></tr>
                <tr><td>Дёнер в лепёшке</td><td>420 г</td><td>580 ккал</td><td>Баранина</td><td>8-10 мин</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- ========== ГАЛЕРЕЯ ========== -->
<section id="gallery" class="gallery-section section">
    <div class="section-title">
        <h2>Наша шаурмечная</h2>
        <p>Загляните на нашу кухню и почувствуйте атмосферу вкуса</p>
    </div>
    
    <div class="gallery-container">
        <div class="gallery-slider" id="gallerySlider">
            <div class="gallery-slide active">
                <img src="https://avatars.mds.yandex.net/i?id=d202eda8eaa2900ea519fb7ca66a8007_l-5276461-images-thumbs&n=13" alt="Кухня">
                <div class="slide-content">
                    <h3>Наша чистая кухня</h3>
                    <p>Всегда свежие ингредиенты и строгое соблюдение санитарных норм.</p>
                </div>
            </div>
            <div class="gallery-slide">
                <img src="https://cast.kz/img/Post/_%D0%B2%D0%BA%20%D0%BF%D0%BE%D0%B2%D0%B0%D1%80.jpg" alt="Приготовление">
                <div class="slide-content">
                    <h3>Мастер-шаурмист за работой</h3>
                    <p>Наши повара готовят каждую шаурму с любовью и вниманием к деталям.</p>
                </div>
            </div>
            <div class="gallery-slide">
                <img src="https://arh-predmet.by/wp-content/uploads/2024/12/7.webp" alt="Интерьер">
                <div class="slide-content">
                    <h3>Уютный интерьер</h3>
                    <p>Комфортная атмосфера для тех, кто предпочитает есть на месте.</p>
                </div>
            </div>
        </div>
        
        <div class="gallery-controls">
            <button class="gallery-btn prev-btn" id="prevBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="gallery-btn next-btn" id="nextBtn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="gallery-dots" id="galleryDots">
            <span class="gallery-dot active" data-slide="0"></span>
            <span class="gallery-dot" data-slide="1"></span>
            <span class="gallery-dot" data-slide="2"></span>
        </div>
    </div>
</section>

<!-- ========== КАЛЬКУЛЯТОР СТОИМОСТИ ========== -->
<section id="calculator" class="section">
    <div class="section-title">
        <h2>Калькулятор заказа</h2>
        <p>Рассчитайте стоимость вашего заказа с учётом всех дополнений</p>
    </div>
    <div class="calculator">
        <form class="calculator-form" id="price-calculator">
            <div class="form-group">
                <label for="product">Тип шаурмы</label>
                <select id="product" name="product">
                    <option value="1" data-price="250">Классическая (250 ₽)</option>
                    <option value="2" data-price="280">Острая (280 ₽)</option>
                    <option value="3" data-price="220">Вегетарианская (220 ₽)</option>
                    <option value="4" data-price="350">Дёнер премиум (350 ₽)</option>
                    <option value="5" data-price="300">Дёнер в лепёшке (300 ₽)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">Количество: <span id="quantityValue">1</span> шт.</label>
                <input type="range" id="quantity" name="quantity" min="1" max="10" value="1">
            </div>
            <div class="form-group">
                <label for="delivery">Доставка</label>
                <select id="delivery" name="delivery">
                    <option value="0">Самовывоз (бесплатно)</option>
                    <option value="150">По району (150 ₽)</option>
                    <option value="250">По городу (250 ₽)</option>
                    <option value="400">Срочная доставка (400 ₽)</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label>Дополнительно</label>
                <div class="options-group">
                    <div class="option-checkbox">
                        <input type="checkbox" id="cheese" name="cheese" value="50">
                        <label for="cheese">Доп. сыр (+50 ₽)</label>
                    </div>
                    <div class="option-checkbox">
                        <input type="checkbox" id="sauce" name="sauce" value="30">
                        <label for="sauce">Доп. соус (+30 ₽)</label>
                    </div>
                    <div class="option-checkbox">
                        <input type="checkbox" id="meat" name="meat" value="100">
                        <label for="meat">Двойное мясо (+100 ₽)</label>
                    </div>
                    <div class="option-checkbox">
                        <input type="checkbox" id="set" name="set" value="150">
                        <label for="set">Комбо (напиток+картошка) (+150 ₽)</label>
                    </div>
                </div>
            </div>
            <div class="calculator-result">
                <h3>Итоговая стоимость</h3>
                <div class="total-price" id="total-price">250 ₽</div>
                <p class="hint" id="total-hint">(1 шт. классической × 250 ₽ + доставка 0 ₽)</p>
            </div>
        </form>
    </div>
</section>

<!-- ФОРМА ЗАКАЗА -->
 <section id="contact">
        <h2>Оформить заказ</h2>
        <div id="credentialsBlock" class="credentials-block" style="display:none;"></div>
        <form id="orderForm" class="contact-form">
            <div class="form-group"><label>Ваше имя *</label><input type="text" id="fullName" required></div>
            <div class="form-group"><label>Телефон *</label><input type="tel" id="phone" required></div>
            <div class="form-group"><label>Email *</label><input type="email" id="email" required></div>
            <div class="form-group"><label>Адрес доставки *</label><input type="text" id="address" required></div>
            <div class="form-group"><label>Пожелания</label><textarea id="message" rows="3"></textarea></div>
            <button type="submit" class="btn">Отправить заказ</button>
            <div id="formStatus" class="form-message"></div>
        </form>
    </section>

    <!-- Блок "Мои заказы" (виден только авторизованным) -->
    <div class="my-orders" id="myOrdersBlock" style="display: <?= $is_logged_in ? 'block' : 'none' ?>;">
        <h3>Мои заказы</h3>
        <div id="ordersList">Загрузка...</div>
    </div>

<!-- ========== FOOTER ========== -->
<footer>
    <div class="footer-content">
        <div class="footer-logo"><i class="fas fa-utensils"></i> Дёнер<span>Королевский</span></div>
        <ul class="footer-links">
            <li><a href="#">Главная</a></li>
            <li><a href="#menu">Меню</a></li>
            <li><a href="#calculator">Калькулятор</a></li>
            <li><a href="#gallery">Галерея</a></li>
            <li><a href="#contact">Заказ</a></li>
            <p><a href="admin_orders.php">Управление заказами (АДМИН)</a></p>
        </ul>
        <div class="quote-section">
            <p class="inspiration-quote">
                "Лучшая шаурма в городе! Сочное мясо, свежие овощи и идеальные соусы. Рекомендую!"
            </p>
        </div>
        <div class="social-links">
            <a href="#"><i class="fab fa-vk"></i></a>
            <a href="#"><i class="fab fa-telegram"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
        </div>
        <div class="copyright">
            <p><i class="fas fa-clock"></i> Ежедневно с 10:00 до 23:00</p>
            <p>© 2024 Дёнер "Королевский". Все права защищены.</p>
        </div>
    </div>
</footer>

<!-- ========== MODAL ========== -->
<div class="modal-overlay" id="modalOverlay"></div>
<div class="modal" id="contact-modal">
    <div class="modal-content">
        <span class="close-modal" id="closeModal">&times;</span>
        <h2>Быстрый заказ</h2>
        <!-- ФОРМА ИЗ ПЕРВОГО КОДА ДЛЯ МОДАЛЬНОГО ОКНА -->
        <form id="modal-contact-form" action="https://formcarry.com/s/YhinEAy4WWS" method="POST" accept-charset="UTF-8">
            <input type="hidden" name="_gotcha" style="display:none !important">
            <div class="form-group">
                <label for="modal-name">Ваше имя *</label>
                <input type="text" id="modal-name" name="name" required minlength="2">
            </div>
            <div class="form-group">
                <label for="modal-email">Электронная почта *</label>
                <input type="email" id="modal-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="modal-message">Ваш заказ *</label>
                <textarea id="modal-message" name="message" rows="4" required minlength="10" placeholder="Что вы хотите заказать?"></textarea>
            </div>
            <button type="submit" class="btn">Заказать сейчас</button>
            <div class="form-message" id="modal-form-message"></div>
        </form>
    </div>
</div>

<script>


// КАЛЬКУЛЯТОР 
    const productSelect = document.getElementById('product');
    const quantitySlider = document.getElementById('quantity');
    const quantityValue = document.getElementById('quantityValue');
    const deliverySelect = document.getElementById('delivery');
    const totalPriceSpan = document.getElementById('total-price');
    const hintSpan = document.getElementById('total-hint');
    const cheeseChk = document.getElementById('cheese');
    const sauceChk = document.getElementById('sauce');
    const meatChk = document.getElementById('meat');
    const setChk = document.getElementById('set');

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function calculateTotal() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const productPrice = parseInt(selectedOption.dataset.price);
        const quantity = parseInt(quantitySlider.value);
        const deliveryCost = parseInt(deliverySelect.value);
        let extraCost = 0;
        if (cheeseChk.checked) extraCost += 50;
        if (sauceChk.checked) extraCost += 30;
        if (meatChk.checked) extraCost += 100;
        if (setChk.checked) extraCost += 150;
        const total = (productPrice + extraCost) * quantity + deliveryCost;
        totalPriceSpan.innerText = formatNumber(total) + ' ₽';
        quantityValue.innerText = quantity;
        const productName = selectedOption.text.split(' (')[0];
        let hintText = `(${quantity} шт. ${productName.toLowerCase()} × ${formatNumber(productPrice)} ₽`;
        if (extraCost > 0) hintText += ` + дополнения ${formatNumber(extraCost)} ₽`;
        if (deliveryCost > 0) hintText += ` + доставка ${formatNumber(deliveryCost)} ₽`;
        hintText += `)`;
        hintSpan.innerText = hintText;
    }

    productSelect.addEventListener('change', calculateTotal);
    quantitySlider.addEventListener('input', calculateTotal);
    deliverySelect.addEventListener('change', calculateTotal);
    cheeseChk.addEventListener('change', calculateTotal);
    sauceChk.addEventListener('change', calculateTotal);
    meatChk.addEventListener('change', calculateTotal);
    setChk.addEventListener('change', calculateTotal);
    calculateTotal();

    //  ОБЩИЕ ФУНКЦИИ 
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    let originalSubmitHandler = null; // для восстановления после редактирования

    //  ЗАГРУЗКА СПИСКА ЗАКАЗОВ 
    async function loadOrders() {
        const container = document.getElementById('ordersList');
        if (!container) return;
        try {
            const resp = await fetch('./index.php?route=orders');
            const data = await resp.json();
            if (data.status === 'ok' && data.orders.length) {
                let html = '<ul style="list-style:none; padding:0;">';
                data.orders.forEach(order => {
                    html += `<li class="order-item" data-id="${order.id}">
                        <span>Заказ №${order.id} от ${new Date(order.created_at).toLocaleString()} — ${order.total_price} ₽ (статус: ${order.status})</span>
                        <div>
                            <button class="edit-order-btn" data-id="${order.id}">✏️ Редактировать</button>
                            <button class="delete-order-btn" data-id="${order.id}">🗑️ Удалить</button>
                        </div>
                    </li>`;
                });
                html += '</ul>';
                container.innerHTML = html;

                document.querySelectorAll('.edit-order-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const orderId = btn.dataset.id;
                        loadOrderForEdit(orderId);
                    });
                });
                document.querySelectorAll('.delete-order-btn').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const orderId = btn.dataset.id;
                        if (confirm(`Удалить заказ №${orderId}? Это действие нельзя отменить.`)) {
                            try {
                                const resp = await fetch(`./index.php?route=order&id=${orderId}&_method=DELETE`, {
                                    method: 'POST'
                                });
                                const result = await resp.json();
                                if (resp.ok && result.status === 'deleted') {
                                    loadOrders();
                                } else {
                                    let errMsg = 'Ошибка удаления: ';
                                    if (result.errors) errMsg += Object.values(result.errors).join(' ');
                                    else errMsg += result.error || 'неизвестная ошибка';
                                    alert(errMsg);
                                }
                            } catch (err) {
                                alert('Ошибка сети. Попробуйте позже.');
                                console.error(err);
                            }
                        }
                    });
                });
            } else {
                container.innerHTML = '<p>У вас пока нет заказов.</p>';
            }
        } catch(e) {
            container.innerHTML = '<p>Ошибка загрузки заказов.</p>';
            console.error(e);
        }
    }

    //  РЕДАКТИРОВАНИЕ ЗАКАЗА 
    async function loadOrderForEdit(orderId) {
        try {
            const resp = await fetch(`./index.php?route=order&id=${orderId}`);
            const data = await resp.json();
            if (data.status === 'ok') {
                const order = data.order;
                document.getElementById('fullName').value = order.full_name;
                document.getElementById('phone').value = order.phone;
                document.getElementById('email').value = order.email;
                document.getElementById('address').value = order.address;
                document.getElementById('message').value = order.message || '';
                if (order.items && order.items.length) {
                    const item = order.items[0];
                    productSelect.value = item.product_id;
                    quantitySlider.value = item.quantity;
                    const opts = item.options || {};
                    cheeseChk.checked = !!opts.cheese;
                    sauceChk.checked = !!opts.sauce;
                    meatChk.checked = !!opts.meat;
                    setChk.checked = !!opts.set;
                    if (order.delivery_cost !== undefined) {
                        for (let i = 0; i < deliverySelect.options.length; i++) {
                            if (parseInt(deliverySelect.options[i].value) === order.delivery_cost) {
                                deliverySelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                    calculateTotal();
                }
                // Переключаем форму в режим редактирования
                const submitBtn = orderForm.querySelector('button');
                submitBtn.innerText = 'Обновить заказ';
                if (!document.getElementById('editOrderId')) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.id = 'editOrderId';
                    hidden.value = orderId;
                    orderForm.appendChild(hidden);
                } else {
                    document.getElementById('editOrderId').value = orderId;
                }
                // Сохраняем исходный обработчик, если ещё не сохранён
                if (!originalSubmitHandler) {
                    originalSubmitHandler = orderForm.onsubmit;
                }
                // Устанавливаем обработчик обновления
                orderForm.onsubmit = async (e) => {
                    e.preventDefault();
                    const editId = document.getElementById('editOrderId').value;
                    const full_name = document.getElementById('fullName').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const address = document.getElementById('address').value.trim();
                    const message = document.getElementById('message').value.trim();
                    const product_id = parseInt(productSelect.value);
                    const quantity = parseInt(quantitySlider.value);
                    const delivery_cost = parseInt(deliverySelect.value);
                    const options = {};
                    if (cheeseChk.checked) options.cheese = true;
                    if (sauceChk.checked) options.sauce = true;
                    if (meatChk.checked) options.meat = true;
                    if (setChk.checked) options.set = true;
                    const updateData = {
                        full_name, phone, email, address, message, delivery_cost,
                        items: [{ product_id, quantity, options }],
                        _method: 'PUT'
                    };
                    statusDiv.innerHTML = '⏳ Обновление...';
                    try {
                        const resp = await fetch(`./index.php?route=order&id=${editId}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(updateData)
                        });
                        const res = await resp.json();
                        if (resp.ok && res.status === 'updated') {
                            statusDiv.innerHTML = '✅ Заказ обновлён!';
                            statusDiv.className = 'form-message success';
                            // Возвращаем форму в исходное состояние
                            orderForm.reset();
                            document.getElementById('editOrderId').remove();
                            submitBtn.innerText = 'Отправить заказ';
                            orderForm.onsubmit = originalSubmitHandler;
                            calculateTotal(); // сброс калькулятора
                            loadOrders(); // обновляем список заказов
                        } else {
                            let errMsg = 'Ошибка обновления: ';
                            if (res.errors) errMsg += Object.values(res.errors).join(' ');
                            else errMsg += res.error || 'неизвестная ошибка';
                            statusDiv.innerHTML = '❌ ' + errMsg;
                            statusDiv.className = 'form-message error';
                        }
                    } catch (err) {
                        statusDiv.innerHTML = '❌ Ошибка сети';
                        statusDiv.className = 'form-message error';
                    } finally {
                        setTimeout(() => statusDiv.innerHTML = '', 3000);
                    }
                };
                window.scrollTo({ top: document.getElementById('contact').offsetTop - 80, behavior: 'smooth' });
            }
        } catch (e) { console.error(e); }
    }

    //ОТПРАВКА НОВОГО ЗАКАЗА 
    const orderForm = document.getElementById('orderForm');
    const statusDiv = document.getElementById('formStatus');
    const credBlock = document.getElementById('credentialsBlock');
    const myOrdersBlock = document.getElementById('myOrdersBlock');

    async function submitNewOrder(orderData) {
        try {
            const response = await fetch('./index.php?route=order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            });
            const text = await response.text();
            console.log('Ответ сервера:', text);
            let result;
            try {
                result = JSON.parse(text);
            } catch (jsonError) {
                statusDiv.innerHTML = '❌ Сервер вернул не JSON: ' + text.substring(0, 100);
                statusDiv.className = 'form-message error';
                return false;
            }
            if (response.ok && result.status === 'ok') {
                statusDiv.innerHTML = '✅ Заказ принят!';
                statusDiv.className = 'form-message success';
                if (result.login && result.password) {
                    credBlock.innerHTML = `<h3>Ваши данные для входа</h3>
                        <p><strong>Логин:</strong> ${escapeHtml(result.login)}</p>
                        <p><strong>Пароль:</strong> ${escapeHtml(result.password)}</p>
                        <p><a href="login.php">Войти</a> для редактирования.</p>`;
                    credBlock.style.display = 'block';
                    if (myOrdersBlock) myOrdersBlock.style.display = 'block';
                    await loadOrders();
                } else {
                    credBlock.style.display = 'none';
                    if (myOrdersBlock && myOrdersBlock.style.display !== 'none') {
                        await loadOrders();
                    }
                }
                return true;
            } else {
                let errMsg = 'Ошибка: ';
                if (result.errors) errMsg += Object.values(result.errors).join(' ');
                else errMsg += result.error || 'Неизвестная ошибка';
                statusDiv.innerHTML = '❌ ' + errMsg;
                statusDiv.className = 'form-message error';
                return false;
            }
        } catch (err) {
            console.error('Fetch error:', err);
            statusDiv.innerHTML = '❌ Ошибка сети. Проверьте соединение.';
            statusDiv.className = 'form-message error';
            return false;
        }
    }

    // Основной обработчик формы (создание нового заказа)
    originalSubmitHandler = async (e) => {
        e.preventDefault();
        const full_name = document.getElementById('fullName').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const email = document.getElementById('email').value.trim();
        const address = document.getElementById('address').value.trim();
        const message = document.getElementById('message').value.trim();
        const product_id = parseInt(productSelect.value);
        const quantity = parseInt(quantitySlider.value);
        const delivery_cost = parseInt(deliverySelect.value);
        const options = {};
        if (cheeseChk.checked) options.cheese = true;
        if (sauceChk.checked) options.sauce = true;
        if (meatChk.checked) options.meat = true;
        if (setChk.checked) options.set = true;

        const orderData = {
            full_name, phone, email, address, message, delivery_cost,
            items: [{ product_id, quantity, options }]
        };

        statusDiv.innerHTML = '⏳ Отправка...';
        statusDiv.className = 'form-message sending';
        const submitBtn = orderForm.querySelector('button');
        submitBtn.disabled = true;

        const success = await submitNewOrder(orderData);
        if (success) {
            orderForm.reset();
            quantitySlider.value = 1;
            calculateTotal();
        }
        submitBtn.disabled = false;
        setTimeout(() => {
            if (statusDiv.className !== 'form-message error') statusDiv.innerHTML = '';
        }, 5000);
    };

    orderForm.onsubmit = originalSubmitHandler;

    // Если пользователь авторизован, загружаем заказы
    <?php if ($is_logged_in): ?>
        loadOrders();
    <?php endif; ?>




 document.addEventListener('DOMContentLoaded', function() {
 const isLoggedIn = <?= json_encode($is_logged_in) ?>;
  // ========== МОБИЛЬНОЕ МЕНЮ ==========
    const burgerBtn = document.getElementById('burgerBtn');
    const mobileMenu = document.createElement('div');
    const mobileOverlay = document.createElement('div');
    
    if (burgerBtn) {
        mobileMenu.className = 'mobile-menu';
        let menuItems = `
            <button class="menu-close" id="menuClose"><i class="fas fa-times"></i></button>
            <ul>
                <li><a href="#"><i class="fas fa-home"></i> Главная</a></li>
                <li><a href="#menu"><i class="fas fa-hamburger"></i> Меню</a></li>
                <li><a href="#calculator"><i class="fas fa-calculator"></i> Калькулятор</a></li>
                <li><a href="#gallery"><i class="fas fa-images"></i> Галерея</a></li>
                <li><a href="#contact"><i class="fas fa-address-book"></i> Заказ</a></li>
        `;
        if (isLoggedIn) {
            menuItems += `<li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a></li>`;
        } else {
            menuItems += `<li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Войти</a></li>`;
        }
        menuItems += `<li><a href="#contact" id="mobile-contact-btn" class="btn"><i class="fas fa-phone-alt"></i> Заказать</a></li>
            </ul>
        `;
        mobileMenu.innerHTML = menuItems;
        
        mobileOverlay.className = 'mobile-overlay';
        
        document.body.appendChild(mobileMenu);
        document.body.appendChild(mobileOverlay);
        
        const menuCloseBtn = document.getElementById('menuClose');
        const mobileContactBtn = document.getElementById('mobile-contact-btn');
        
        burgerBtn.addEventListener('click', function() {
            mobileMenu.classList.add('active');
            mobileOverlay.classList.add('active');
            burgerBtn.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        function closeMobileMenu() {
            mobileMenu.classList.remove('active');
            mobileOverlay.classList.remove('active');
            burgerBtn.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        menuCloseBtn.addEventListener('click', closeMobileMenu);
        mobileOverlay.addEventListener('click', closeMobileMenu);
        
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });
        
        if (mobileContactBtn) {
            mobileContactBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeMobileMenu();
                openModal();
            });
        }
    }
    });


</script>
<script src="old.js"></script>
<script src="gallery.js"></script>
<script src="ingridient.js"></script>
<script src="mobileMenu.js"></script>
</body>
</html>