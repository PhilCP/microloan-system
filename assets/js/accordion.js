document.addEventListener('DOMContentLoaded', function() {
    // 1. FAQ Accordion Logic
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        item.addEventListener('click', function() {
            // Check if this item is already active
            const isActive = this.classList.contains('active');
            
            // Close all other FAQ items for a clean look
            faqItems.forEach(el => el.classList.remove('active'));
            
            // If the clicked item wasn't active, open it
            if (!isActive) {
                this.classList.add('active');
            }
        });
    });

    // 2. Scroll Reveal Animation
    const observerOptions = { threshold: 0.1 };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = "1";
                entry.target.style.transform = "translateY(0)";
            }
        });
    }, observerOptions);

    document.querySelectorAll('.feature-card, .stat-item, .faq-item').forEach(el => {
        el.style.opacity = "0";
        el.style.transform = "translateY(40px)";
        el.style.transition = "all 0.8s cubic-bezier(0.16, 1, 0.3, 1)";
        observer.observe(el);
    });
});