document.addEventListener('DOMContentLoaded', function () {

// faq accordion
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(function (item) {
        item.addEventListener('click', function () {
            const isActive = this.classList.contains('active');

            // Close all
            faqItems.forEach(function (el) {
                el.classList.remove('active');
            });

            // Open clicked one (unless it was already open)
            if (!isActive) {
                this.classList.add('active');
            }
        });
    });

    //Scroll Reveal
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -40px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const revealElements = document.querySelectorAll('.feature-card, .stat-item, .faq-item');

    revealElements.forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(28px)';
        el.style.transition = 'opacity 0.7s ease-out, transform 0.7s cubic-bezier(0.16, 1, 0.3, 1)';
        observer.observe(el);
    });

});