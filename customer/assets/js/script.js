// JavaScript for interactivity

function initSlotSelection() {
    console.log('Initializing slot selection...');
    // Collection Slot Page - Tabs Selection
    const slotTabs = document.querySelectorAll('.slot-tab');
    if (slotTabs.length > 0) {
        slotTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                slotTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
            });
        });
    }

    // Collection Slot Page - Cards Selection
    const slotCards = document.querySelectorAll('.slot-card');
    if (slotCards.length > 0) {
        slotCards.forEach(card => {
            card.addEventListener('click', () => {
                slotCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            });
        });
    }
}

function initQuantitySelector() {
    const qtyWrappers = document.querySelectorAll('.quantity-selector');
    
    qtyWrappers.forEach(wrapper => {
        const minusBtn = wrapper.querySelector('.qty-btn.minus');
        const plusBtn = wrapper.querySelector('.qty-btn.plus');
        const input = wrapper.querySelector('.qty-input');
        
        if (minusBtn && plusBtn && input) {
            minusBtn.addEventListener('click', () => {
                let currentValue = parseInt(input.value) || 1;
                if (currentValue > 1) {
                    input.value = currentValue - 1;
                }
            });
            
            plusBtn.addEventListener('click', () => {
                let currentValue = parseInt(input.value) || 1;
                input.value = currentValue + 1;
            });
            
            input.addEventListener('change', () => {
                let currentValue = parseInt(input.value);
                if (isNaN(currentValue) || currentValue < 1) {
                    input.value = 1;
                }
            });
        }
    });
}

function initAuthTabs() {
    const tabCustomer = document.getElementById('tab-customer');
    const tabTrader = document.getElementById('tab-trader');
    const roleInput = document.getElementById('role-input');
    const registerBtn = document.getElementById('register-btn');

    if (tabCustomer && tabTrader && roleInput) {
        tabCustomer.addEventListener('click', () => {
            tabCustomer.classList.add('active');
            tabTrader.classList.remove('active');
            roleInput.value = 'customer';
        });

        tabTrader.addEventListener('click', () => {
            tabTrader.classList.add('active');
            tabCustomer.classList.remove('active');
            roleInput.value = 'trader';
        });
    }
}

function initStarRating() {
    const starContainers = document.querySelectorAll('.stars-outline');
    
    starContainers.forEach(container => {
        const stars = container.querySelectorAll('.material-icons');
        let currentRating = -1; // -1 means no rating selected
        
        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                if (currentRating === index) {
                    // If the user clicks the same star again, clear the rating
                    currentRating = -1;
                    stars.forEach(s => s.textContent = 'star_border');
                } else {
                    // Set the new rating
                    currentRating = index;
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.textContent = 'star';
                        } else {
                            s.textContent = 'star_border';
                        }
                    });
                }
            });
        });
    });
}

function initUiPolish() {
    document.body.classList.add('ui-enhanced');

    const header = document.querySelector('.site-header');
    const updateHeader = () => {
        if (!header) return;
        header.classList.toggle('header-scrolled', window.scrollY > 8);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    document
        .querySelectorAll(
            '.hero-content, .hero-image-wrapper, .trader-card, .process-card, .category-card, .product-list-card, .cart-trader-block, .slot-card, .invoice-box, .profile-form, .order-table-wrap, .review-form-card, .review-item, .search-empty-state'
        )
        .forEach((el) => el.classList.add('fade-in-up'));

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        document.querySelectorAll('.fade-in-up').forEach((el) => observer.observe(el));
    } else {
        document.querySelectorAll('.fade-in-up').forEach((el) => el.classList.add('is-visible'));
    }

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.classList.add('is-submitting');
                submitButton.dataset.originalText = submitButton.textContent;
                submitButton.textContent = 'Please wait...';
            }
        });
    });

    document.querySelectorAll('[style*="background:#efe"], [style*="background:#fee"], [style*="background:#d1fae5"], [style*="background:#fee2e2"]').forEach((el) => {
        el.classList.add('toast-message');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initSlotSelection();
        initQuantitySelector();
        initAuthTabs();
        initStarRating();
        initUiPolish();
    });
} else {
    initSlotSelection();
    initQuantitySelector();
    initAuthTabs();
    initStarRating();
    initUiPolish();
}
