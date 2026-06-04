 // ========== ВЫБОР ИНГРЕДИЕНТОВ ==========
    const ingredientOptions = document.querySelectorAll('.ingredient-option');
    
    ingredientOptions.forEach(option => {
        option.addEventListener('click', function() {
            const parent = this.closest('.ingredients-options');
            if (parent) {
                const text = this.textContent;
                if (text.includes('Курица') || text.includes('Говядина') || 
                    text.includes('Свинина') || text.includes('Овощная') ||
                    text.includes('С грибами') || text.includes('Средняя') ||
                    text.includes('Острая') || text.includes('Очень острая')) {
                    
                    parent.querySelectorAll('.ingredient-option').forEach(opt => {
                        if (opt.textContent.includes('Курица') || opt.textContent.includes('Говядина') || 
                            opt.textContent.includes('Свинина') || opt.textContent.includes('Овощная') ||
                            opt.textContent.includes('С грибами') || opt.textContent.includes('Средняя') ||
                            opt.textContent.includes('Острая') || opt.textContent.includes('Очень острая')) {
                            opt.classList.remove('active');
                        }
                    });
                }
                
                this.classList.toggle('active');
                updateCalculator();
            }
        });
    });