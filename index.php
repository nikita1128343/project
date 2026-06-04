<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Дёнерная "Вкусный Дёнер"</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Дополнительные стили для страницы заказа */
        .order-success {
            background: #2e7d32;
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        .order-success .credentials {
            background: #1b5e20;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 16px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: #2c2c2c;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-content h3 {
            margin-top: 0;
            color: #ff9800;
        }
        .close-modal {
            float: right;
            cursor: pointer;
            font-size: 24px;
            color: #ff9800;
        }
        .order-item {
            border-bottom: 1px solid #444;
            padding: 10px 0;
        }
        .delivery-option {
            margin: 10px 0;
            padding: 10px;
            background: #3c3c3c;
            border-radius: 8px;
            cursor: pointer;
        }
        .delivery-option.selected {
            background: #2e7d32;
        }
        .error-message {
            color: #f44336;
            font-size: 12px;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            background: #3c3c3c;
            border: 1px solid #555;
            color: white;
            border-radius: 6px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ff9800;
        }
        .btn-order {
            background: #ff9800;
            color: #1e1e1e;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            width: 100%;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-order:hover {
            background: #f57c00;
        }
        .cart-summary {
            background: #0a2e0a;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            position: sticky;
            bottom: 0;
        }
        .menu-tab {
            display: inline-block;
            padding: 10px 20px;
            background: #2c2c2c;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
        }
        .menu-tab.active {
            background: #ff9800;
            color: #1e1e1e;
        }
        .menu-section {
            display: none;
            padding: 20px;
            background: #2c2c2c;
            border-radius: 0 8px 8px 8px;
        }
        .menu-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <h1>🥙 Вкусный Дёнер</h1>
            <p>Дёнеры и шаурма с доставкой</p>
        </div>
        <nav>
            <ul>
                <li><a href="#home">Главная</a></li>
                <li><a href="#menu">Меню</a></li>
                <li><a href="#gallery">Галерея</a></li>
                <li><a href="#contacts">Контакты</a></li>
                <?php if (isset($_SESSION['application_id'])): ?>
                    <li><a href="logout.php">Выйти</a></li>
                <?php else: ?>
                    <li><a href="login.php">Войти</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="cart-icon" onclick="openCartModal()">
            🛒 <span id="cart-count">0</span>
        </div>
        <div class="mobile-menu-btn">☰</div>
    </header>

    <main>
        <section id="home">
            <div class="hero">
                <h2>Самые вкусные дёнеры в городе!</h2>
                <p>Сочное мясо, свежие овощи, авторские соусы</p>
                <button onclick="scrollToMenu()">Заказать сейчас</button>
            </div>
        </section>

        <section id="menu">
            <h2>Наше меню</h2>
            <div class="menu-tabs">
                <div class="menu-tab active" data-tab="doner">Дёнеры</div>
                <div class="menu-tab" data-tab="rolls">Роллы</div>
                <div class="menu-tab" data-tab="drinks">Напитки</div>
            </div>
            <div id="doner-section" class="menu-section active">
                <div class="products" id="doner-products"></div>
            </div>
            <div id="rolls-section" class="menu-section">
                <div class="products" id="rolls-products"></div>
            </div>
            <div id="drinks-section" class="menu-section">
                <div class="products" id="drinks-products"></div>
            </div>
        </section>

        <section id="gallery">
            <h2>Галерея</h2>
            <div class="gallery-container"></div>
        </section>

        <section id="contacts">
            <h2>Контакты</h2>
            <p>📍 Адрес: г. Москва, ул. Вкусная, д. 15</p>
            <p>📞 Телефон: +7 (999) 123-45-67</p>
            <p>⏰ Режим работы: Ежедневно 10:00 - 23:00</p>
            <p>📧 Email: info@doner.ru</p>
        </section>
    </main>

    <!-- Модальное окно корзины -->
    <div id="cartModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeCartModal()">&times;</span>
            <h3>🛒 Ваш заказ</h3>
            <div id="cart-items"></div>
            <div id="delivery-options">
                <h4>Доставка</h4>
                <div class="delivery-option" data-cost="0" onclick="selectDelivery(this, 0)">
                    🚶 Самовывоз (бесплатно)
                </div>
                <div class="delivery-option" data-cost="150" onclick="selectDelivery(this, 150)">
                    🚗 Доставка (150 ₽)
                </div>
                <div class="delivery-option" data-cost="250" onclick="selectDelivery(this, 250)">
                    🛵 Экспресс-доставка (250 ₽)
                </div>
            </div>
            <div class="cart-summary">
                <strong>Итого: <span id="cart-total">0</span> ₽</strong>
                <button class="btn-order" onclick="openOrderForm()">Оформить заказ</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно формы заказа -->
    <div id="orderFormModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeOrderForm()">&times;</span>
            <h3>📝 Оформление заказа</h3>
            <form id="orderForm">
                <div class="form-group">
                    <label>Ваше имя и фамилия *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" required placeholder="+7 (999) 123-45-67">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Адрес доставки *</label>
                    <input type="text" name="address" required>
                </div>
                <div class="form-group">
                    <label>Комментарий к заказу</label>
                    <textarea name="message" rows="3"></textarea>
                </div>
                <input type="hidden" name="delivery_cost" id="delivery_cost" value="0">
                <button type="submit" class="btn-order">Подтвердить заказ</button>
            </form>
        </div>
    </div>

    <!-- Модальное окно успеха -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeSuccessModal()">&times;</span>
            <div class="order-success">
                <h3>✅ Заказ успешно оформлен!</h3>
                <p>Номер заказа: <strong id="order-id"></strong></p>
                <p>Сумма: <strong id="order-total"></strong> ₽</p>
                <div id="credentials-block" style="display:none;">
                    <p>🔑 Для отслеживания заказов создан аккаунт:</p>
                    <div class="credentials">
                        Логин: <span id="generated-login"></span><br>
                        Пароль: <span id="generated-password"></span>
                    </div>
                    <p>⚠️ Сохраните эти данные!</p>
                </div>
                <button onclick="closeSuccessModalAndReset()" class="btn-order">Продолжить покупки</button>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 Дёнерная "Вкусный Дёнер". Все права защищены.</p>
    </footer>

    <script>
        // ========== ДАННЫЕ ТОВАРОВ ==========
        const products = {
            doner: [
                { id: 1, name: "Дёнер классический", price: 250, image: "https://via.placeholder.com/200x150?text=Doner+Classic", description: "Классический дёнер с курицей" },
                { id: 2, name: "Дёнер острый", price: 280, image: "https://via.placeholder.com/200x150?text=Doner+Spicy", description: "Острый дёнер с халапеньо" },
                { id: 3, name: "Дёнер вегетарианский", price: 220, image: "https://via.placeholder.com/200x150?text=Doner+Veg", description: "С фалафелем и овощами" },
                { id: 4, name: "Дёнер премиум", price: 350, image: "https://via.placeholder.com/200x150?text=Doner+Premium", description: "С говядиной и двойным сыром" },
                { id: 5, name: "Дёнер в лаваше", price: 300, image: "https://via.placeholder.com/200x150?text=Doner+Lavash", description: "Традиционный в тонком лаваше" }
            ],
            rolls: [
                { id: 6, name: "Ролл с курицей", price: 200, image: "https://via.placeholder.com/200x150?text=Roll+Chicken", description: "Сыр, курица, соус" },
                { id: 7, name: "Ролл острый", price: 220, image: "https://via.placeholder.com/200x150?text=Roll+Spicy", description: "С острым соусом" },
                { id: 8, name: "Ролл с говядиной", price: 280, image: "https://via.placeholder.com/200x150?text=Roll+Beef", description: "Говядина, сыр, овощи" }
            ],
            drinks: [
                { id: 9, name: "Кола 0.5л", price: 80, image: "https://via.placeholder.com/200x150?text=Coca-Cola", description: "Coca-Cola 0.5л" },
                { id: 10, name: "Сок 0.33л", price: 90, image: "https://via.placeholder.com/200x150?text=Juice", description: "Апельсиновый сок" },
                { id: 11, name: "Вода 0.5л", price: 50, image: "https://via.placeholder.com/200x150?text=Water", description: "Питьевая вода" }
            ]
        };

        // Корзина
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        let selectedDeliveryCost = 0;

        // Функции корзины
        function saveCart() {
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartDisplay();
        }

        function updateCartDisplay() {
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) + selectedDeliveryCost;
            const totalElement = document.getElementById('cart-total');
            if (totalElement) totalElement.textContent = total;
        }

        function addToCart(product, category) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                existing.quantity++;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    quantity: 1,
                    category: category
                });
            }
            saveCart();
            showNotification('Добавлено в корзину!');
        }

        function showNotification(msg) {
            const notif = document.createElement('div');
            notif.textContent = msg;
            notif.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#2e7d32;color:white;padding:12px 20px;border-radius:8px;z-index:9999;';
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 2000);
        }

        function openCartModal() {
            const modal = document.getElementById('cartModal');
            renderCartItems();
            modal.style.display = 'flex';
        }

        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function renderCartItems() {
            const container = document.getElementById('cart-items');
            if (!container) return;
            
            if (cart.length === 0) {
                container.innerHTML = '<p>Корзина пуста</p>';
                return;
            }
            
            let html = '';
            cart.forEach((item, index) => {
                html += `
                    <div class="order-item">
                        <strong>${item.name}</strong><br>
                        ${item.price} ₽ × 
                        <button onclick="changeQuantity(${index}, -1)">-</button>
                        ${item.quantity}
                        <button onclick="changeQuantity(${index}, 1)">+</button>
                        <button onclick="removeFromCart(${index})" style="background:#c62828;color:white;border:none;border-radius:4px;padding:2px 8px;margin-left:10px;">✕</button>
                        = ${item.price * item.quantity} ₽
                    </div>
                `;
            });
            container.innerHTML = html;
            updateCartDisplay();
        }

        function changeQuantity(index, delta) {
            cart[index].quantity += delta;
            if (cart[index].quantity <= 0) {
                cart.splice(index, 1);
            }
            saveCart();
            renderCartItems();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            saveCart();
            renderCartItems();
        }

        function selectDelivery(element, cost) {
            document.querySelectorAll('.delivery-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            selectedDeliveryCost = cost;
            document.getElementById('delivery_cost').value = cost;
            updateCartDisplay();
        }

        function openOrderForm() {
            if (cart.length === 0) {
                alert('Корзина пуста');
                return;
            }
            closeCartModal();
            document.getElementById('orderFormModal').style.display = 'flex';
        }

        function closeOrderForm() {
            document.getElementById('orderFormModal').style.display = 'none';
        }

        // Отправка заказа
        document.getElementById('orderForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const items = cart.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                options: {}
            }));
            
            const orderData = {
                full_name: formData.get('full_name'),
                phone: formData.get('phone'),
                email: formData.get('email'),
                address: formData.get('address'),
                message: formData.get('message') || '',
                delivery_cost: parseInt(formData.get('delivery_cost')) || 0,
                items: items
            };
            
            try {
                const response = await fetch('./api.php?route=order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });
                
                const result = await response.json();
                
                if (response.ok && result.status === 'ok') {
                    document.getElementById('order-id').textContent = result.order_id;
                    document.getElementById('order-total').textContent = result.total;
                    
                    if (result.login && result.password) {
                        document.getElementById('generated-login').textContent = result.login;
                        document.getElementById('generated-password').textContent = result.password;
                        document.getElementById('credentials-block').style.display = 'block';
                    } else {
                        document.getElementById('credentials-block').style.display = 'none';
                    }
                    
                    closeOrderForm();
                    document.getElementById('successModal').style.display = 'flex';
                    cart = [];
                    saveCart();
                } else {
                    let errorMsg = 'Ошибка при оформлении заказа\n';
                    if (result.errors) {
                        errorMsg += Object.values(result.errors).join('\n');
                    }
                    alert(errorMsg);
                }
            } catch (error) {
                alert('Ошибка сети: ' + error.message);
            }
        });
        
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
        }
        
        function closeSuccessModalAndReset() {
            closeSuccessModal();
            location.reload();
        }
        
        // Рендер товаров
        function renderProducts() {
            const donerContainer = document.getElementById('doner-products');
            const rollsContainer = document.getElementById('rolls-products');
            const drinksContainer = document.getElementById('drinks-products');
            
            if (donerContainer) {
                donerContainer.innerHTML = products.doner.map(p => `
                    <div class="product-card">
                        <img src="${p.image}" alt="${p.name}">
                        <h3>${p.name}</h3>
                        <p>${p.description}</p>
                        <p class="price">${p.price} ₽</p>
                        <button onclick='addToCart(${JSON.stringify(p)},"doner")'>В корзину</button>
                    </div>
                `).join('');
            }
            
            if (rollsContainer) {
                rollsContainer.innerHTML = products.rolls.map(p => `
                    <div class="product-card">
                        <img src="${p.image}" alt="${p.name}">
                        <h3>${p.name}</h3>
                        <p>${p.description}</p>
                        <p class="price">${p.price} ₽</p>
                        <button onclick='addToCart(${JSON.stringify(p)},"rolls")'>В корзину</button>
                    </div>
                `).join('');
            }
            
            if (drinksContainer) {
                drinksContainer.innerHTML = products.drinks.map(p => `
                    <div class="product-card">
                        <img src="${p.image}" alt="${p.name}">
                        <h3>${p.name}</h3>
                        <p>${p.description}</p>
                        <p class="price">${p.price} ₽</p>
                        <button onclick='addToCart(${JSON.stringify(p)},"drinks")'>В корзину</button>
                    </div>
                `).join('');
            }
        }
        
        // Табы меню
        function initTabs() {
            const tabs = document.querySelectorAll('.menu-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    document.querySelectorAll('.menu-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    const sectionId = tab.dataset.tab + '-section';
                    document.getElementById(sectionId).classList.add('active');
                });
            });
        }
        
        function scrollToMenu() {
            document.getElementById('menu').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Галерея
        function initGallery() {
            const galleryContainer = document.querySelector('.gallery-container');
            if (galleryContainer) {
                const images = [
                    'https://via.placeholder.com/300x200?text=Doner+1',
                    'https://via.placeholder.com/300x200?text=Doner+2',
                    'https://via.placeholder.com/300x200?text=Doner+3',
                    'https://via.placeholder.com/300x200?text=Doner+4'
                ];
                galleryContainer.innerHTML = images.map(img => `
                    <div class="gallery-item">
                        <img src="${img}" alt="Дёнер">
                    </div>
                `).join('');
            }
        }
        
        // Мобильное меню
        function initMobileMenu() {
            const btn = document.querySelector('.mobile-menu-btn');
            const nav = document.querySelector('nav ul');
            if (btn && nav) {
                btn.addEventListener('click', () => {
                    nav.classList.toggle('show');
                });
            }
        }
        
        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            renderProducts();
            initTabs();
            initGallery();
            initMobileMenu();
            updateCartDisplay();
            
            // Выбрать доставку по умолчанию
            const defaultDelivery = document.querySelector('.delivery-option');
            if (defaultDelivery) {
                defaultDelivery.classList.add('selected');
                selectedDeliveryCost = 0;
                document.getElementById('delivery_cost').value = 0;
            }
        });
    </script>
</body>
</html>