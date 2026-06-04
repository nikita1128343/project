function initCalculator() {
    const productSelect = document.getElementById('product');
    const quantitySlider = document.getElementById('quantity');
    const quantityValue = document.getElementById('quantityValue');
    const deliverySelect = document.getElementById('delivery');
    const totalPriceElement = document.getElementById('total-price');
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    const hintElement = document.querySelector('.hint');
    
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    
    function calculateTotal() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const productPrice = parseInt(selectedOption.dataset.price);
        const quantity = parseInt(quantitySlider.value);
        const deliveryCost = parseInt(deliverySelect.value);
        
        let extraCost = 0;
        checkboxes.forEach(checkbox => {
            if (checkbox.checked) extraCost += parseInt(checkbox.value);
        });
        
        const activeIngredients = document.querySelectorAll('.ingredient-option.active');
        activeIngredients.forEach(ingredient => {
            const priceMatch = ingredient.textContent.match(/\+(\d+)/);
            if (priceMatch) extraCost += parseInt(priceMatch[1]);
        });
        
        const totalPrice = (productPrice + extraCost) * quantity + deliveryCost;
        
        if (quantityValue) quantityValue.textContent = quantity;
        if (totalPriceElement) totalPriceElement.textContent = formatNumber(totalPrice) + ' ₽';
        
        const productText = selectedOption.text.split(' (')[0];
        let hintText = `(${quantity} шт. ${productText.toLowerCase()} × ${formatNumber(productPrice)} ₽`;
        if (extraCost > 0) hintText += ` + дополнения ${formatNumber(extraCost)} ₽`;
        if (deliveryCost > 0) hintText += ` + доставка ${formatNumber(deliveryCost)} ₽`;
        hintText += `)`;
        if (hintElement) hintElement.textContent = hintText;
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