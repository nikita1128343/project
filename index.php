<?php
// Обработка AJAX запросов от формы заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['error' => 'Нет данных']);
        exit;
    }
    
    // Просто сохраняем заказ в файл для начала
    $order_data = [
        'time' => date('Y-m-d H:i:s'),
        'data' => $input
    ];
    
    $log_file = 'orders.json';
    $existing = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
    $existing[] = $order_data;
    file_put_contents($log_file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'status' => 'ok',
        'order_id' => time(),
        'message' => 'Заказ принят',
        'login' => 'user_' . rand(1000, 9999),
        'password' => substr(md5(rand()), 0, 8)
    ]);
    exit;
}
session_start();
$is_logged_in = isset($_SESSION['application_id']);
$user_id = $is_logged_in ? $_SESSION['application_id'] : null;

// ========== ОБРАБОТКА API-ЗАПРОСОВ ==========
if (isset($_GET['route']) && $_GET['route'] === 'order') {
    header('Content-Type: application/json; charset=UTF-8');
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/order_functions.php';

    $method = $_SERVER['REQUEST_METHOD'];
    
    // Читаем JSON-вход (данные из формы)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST; // fallback для обычных POST-запросов
    }

    if ($method === 'POST') {
        $is_logged = isset($_SESSION['application_id']);
        $user_id = $is_logged ? $_SESSION['application_id'] : null;
        $result = createOrder($input, $is_logged, $user_id);
        
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
                <div class="slide-content"><h3>Наша чистая кухня</h3><p>Всегда свежие ингредиенты и строгое соблюдение санитарных норм.</p></div>
            </div>
            <div class="gallery-slide">
                <img src="https://cast.kz/img/Post/_%D0%B2%D0%BA%20%D0%BF%D0%BE%D0%B2%D0%B0%D1%80.jpg" alt="Приготовление">
                <div class="slide-content"><h3>Мастер-шаурмист за работой</h3><p>Наши повара готовят каждую шаурму с любовью и вниманием к деталям.</p></div>
            </div>
            <div class="gallery-slide">
                <img src="https://arh-predmet.by/wp-content/uploads/2024/12/7.webp" alt="Интерьер">
                <div class="slide-content"><h3>Уютный интерьер</h3><p>Комфортная атмосфера для тех, кто предпочитает есть на месте.</p></div>
            </div>
        </div>
        <div class="gallery-controls">
            <button class="gallery-btn prev-btn" id="prevBtn"><i class="fas fa-chevron-left"></i></button>
            <button class="gallery-btn next-btn" id="nextBtn"><i class="fas fa-chevron-right"></i></button>
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
                    <div class="option-checkbox"><input type="checkbox" id="cheese" value="50"><label for="cheese">Доп. сыр (+50 ₽)</label></div>
                    <div class="option-checkbox"><input type="checkbox" id="sauce" value="30"><label for="sauce">Доп. соус (+30 ₽)</label></div>
                    <div class="option-checkbox"><input type="checkbox" id="meat" value="100"><label for="meat">Двойное мясо (+100 ₽)</label></div>
                    <div class="option-checkbox"><input type="checkbox" id="set" value="150"><label for="set">Комбо (напиток+картошка) (+150 ₽)</label></div>
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

<!-- ========== ФОРМА ЗАКАЗА ========== -->
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

<!-- ========== МОИ ЗАКАЗЫ (для авторизованных) ========== -->
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
            <li><a href="admin_orders.php">Управление заказами (АДМИН)</a></li>
        </ul>
        <div class="quote-section">
            <p class="inspiration-quote">"Лучшая шаурма в городе! Сочное мясо, свежие овощи и идеальные соусы. Рекомендую!"</p>
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

<script src="old.js"></script>
<script src="gallery.js"></script>
<script src="ingridient.js"></script>
<script src="calculator.js"></script>
<script src="mobileMenu.js"></script>
</body>
</html>