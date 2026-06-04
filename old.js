document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('orderForm');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = {
            full_name: document.getElementById('fullName').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            email: document.getElementById('email').value.trim(),
            address: document.getElementById('address').value.trim(),
            message: document.getElementById('message').value.trim(),
            delivery_cost: parseInt(document.getElementById('delivery').value, 10),
            items: [{
                product_id: parseInt(document.getElementById('product').value, 10),
                quantity: parseInt(document.getElementById('quantity').value, 10),
                options: {
                    cheese: document.getElementById('cheese').checked,
                    sauce: document.getElementById('sauce').checked,
                    meat: document.getElementById('meat').checked,
                    set: document.getElementById('set').checked
                }
            }]
        };

        const statusDiv = document.getElementById('formStatus');
        statusDiv.innerHTML = '⏳ Отправка...';
        statusDiv.className = 'form-message sending';
        statusDiv.style.display = 'block';

        try {
            const response = await fetch('./index.php?route=order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (response.ok && result.status === 'ok') {
                statusDiv.innerHTML = '✅ Заказ принят!';
                statusDiv.className = 'form-message success';
                
                if (result.login && result.password) {
                    const credBlock = document.getElementById('credentialsBlock');
                    if (credBlock) {
                        credBlock.innerHTML = `
                            <h3>Ваши данные для входа</h3>
                            <p><strong>Логин:</strong> ${escapeHtml(result.login)}</p>
                            <p><strong>Пароль:</strong> ${escapeHtml(result.password)}</p>
                            <p><a href="login.php">Войти</a> для редактирования.</p>
                        `;
                        credBlock.style.display = 'block';
                    }
                }
                form.reset();
                if (typeof calculateTotal === 'function') calculateTotal();
                if (typeof loadOrders === 'function') loadOrders();
            } else {
                let errMsg = 'Ошибка: ';
                if (result.errors) errMsg += Object.values(result.errors).join(', ');
                else errMsg += result.error || 'Неизвестная ошибка';
                statusDiv.innerHTML = '❌ ' + errMsg;
                statusDiv.className = 'form-message error';
            }
        } catch (err) {
            statusDiv.innerHTML = '❌ Ошибка сети: ' + err.message;
            statusDiv.className = 'form-message error';
        } finally {
            setTimeout(() => {
                if (statusDiv.className !== 'form-message error') statusDiv.style.display = 'none';
            }, 5000);
        }
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
});