document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burgerBtn');
    if (burgerBtn) {
        const mobileMenu = document.createElement('div');
        const mobileOverlay = document.createElement('div');
        
        mobileMenu.className = 'mobile-menu';
        mobileMenu.innerHTML = `
            <button class="menu-close" id="menuClose"><i class="fas fa-times"></i></button>
            <ul>
                <li><a href="#"><i class="fas fa-home"></i> Главная</a></li>
                <li><a href="#menu"><i class="fas fa-hamburger"></i> Меню</a></li>
                <li><a href="#calculator"><i class="fas fa-calculator"></i> Калькулятор</a></li>
                <li><a href="#gallery"><i class="fas fa-images"></i> Галерея</a></li>
                <li><a href="#contact"><i class="fas fa-address-book"></i> Заказ</a></li>
            </ul>
        `;
        
        mobileOverlay.className = 'mobile-overlay';
        
        document.body.appendChild(mobileMenu);
        document.body.appendChild(mobileOverlay);
        
        const menuClose = document.getElementById('menuClose');
        
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
        
        if (menuClose) menuClose.addEventListener('click', closeMobileMenu);
        mobileOverlay.addEventListener('click', closeMobileMenu);
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });
    }
});