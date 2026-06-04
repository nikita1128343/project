// ========== КАЛЬКУЛЯТОР СТОИМОСТИ ==========
    function initCalculator() {
        const productSelect = document.getElementById('product');
        const quantitySlider = document.getElementById('quantity');
        const quantityValue = document.getElementById('quantityValue');
        const deliverySelect = document.getElementById('delivery');
        const totalPriceElement = document.getElementById('total-price');
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }
        
        function calculateTotal() {
            const productPrice = parseInt(productSelect.value);
            const quantity = parseInt(quantitySlider.value);
            const deliveryCost = parseInt(deliverySelect.value);
            
            let extraCost = 0;
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    extraCost += parseInt(checkbox.value);
                }
            });
            
            const activeIngredients = document.querySelectorAll('.ingredient-option.active');
            activeIngredients.forEach(ingredient => {
                const priceMatch = ingredient.textContent.match(/\+(\d+)/);
                if (priceMatch) {
                    extraCost += parseInt(priceMatch[1]);
                }
            });
            
            const totalPrice = (productPrice * quantity) + deliveryCost + extraCost;
            
            if (quantityValue) quantityValue.textContent = quantity;
            if (totalPriceElement) totalPriceElement.textContent = formatNumber(totalPrice) + ' ₽';
            
            const productText = productSelect.options[productSelect.selectedIndex].text.split(' (')[0];
            const deliveryText = deliverySelect.options[deliverySelect.selectedIndex].text.split(' (')[0];
            
            let hintText = `(${quantity} шт. ${productText.toLowerCase()} × ${formatNumber(productPrice)} ₽`;
            if (deliveryCost > 0) hintText += ` + доставка ${formatNumber(deliveryCost)} ₽`;
            if (extraCost > 0) hintText += ` + дополнения ${formatNumber(extraCost)} ₽`;
            hintText += `)`;
            
            const hintElement = document.querySelector('.hint');
            if (hintElement) {
                hintElement.textContent = hintText;
            }
        }
        
        if (productSelect && quantitySlider && deliverySelect && totalPriceElement) {
            productSelect.addEventListener('change', calculateTotal);
            quantitySlider.addEventListener('input', calculateTotal);
            deliverySelect.addEventListener('change', calculateTotal);
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', calculateTotal);
            });
            
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('ingredient-option')) {
                    setTimeout(calculateTotal, 100);
                }
            });
            
            calculateTotal();
        }
    }
    
    initCalculator();
    