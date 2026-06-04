 document.addEventListener('DOMContentLoaded', function() {
   const contactBtn = document.querySelector('.contact-btn');
const modalOverlay = document.getElementById('modalOverlay');
const contactModal = document.getElementById('contact-modal');
const closeModalBtn = document.getElementById('closeModal');

if (contactBtn && modalOverlay && contactModal) {
    contactBtn.addEventListener('click', function(e) {
        e.preventDefault();
        modalOverlay.classList.add('active');
        contactModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    });

    const closeModal = () => {
        modalOverlay.classList.remove('active');
        contactModal.classList.remove('active');
        document.body.style.overflow = '';
    };

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && contactModal.classList.contains('active')) closeModal();
    });
}
    
    // ========== ПЛАВНАЯ ПРОКРУТКА ==========
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if (href === '#' || href.includes('javascript')) return;
            
            const targetElement = document.querySelector(href);
            if (targetElement) {
                e.preventDefault();
                
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // ========== ОБНОВЛЕНИЕ ГОДА ==========
    const yearElements = document.querySelectorAll('.copyright');
    yearElements.forEach(el => {
        if (el.textContent.includes('2024')) {
            const currentYear = new Date().getFullYear();
            el.textContent = el.textContent.replace('2024', currentYear);
        }
    });
    
    // ========== АВТОСЛАЙДЕР ==========
    let autoSlideInterval = setInterval(nextSlide, 5000);
    
    if (slider) {
        slider.addEventListener('mouseenter', () => {
            clearInterval(autoSlideInterval);
        });
        
        slider.addEventListener('mouseleave', () => {
            autoSlideInterval = setInterval(nextSlide, 5000);
        });
    }
    
    updateSlider();
});