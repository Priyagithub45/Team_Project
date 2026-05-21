// CFO — Customer JS v2.2

// ── Toast system (global) ────────────────────────────────────────────────────
var CFO_TOAST = (function () {
    'use strict';
    var BASE_DURATION = 6000;

    function getContainer() {
        var c = document.getElementById('cfo-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'cfo-toast-container';
            c.className = 'cfo-toast-container';
            c.setAttribute('aria-live', 'polite');
            c.setAttribute('aria-atomic', 'false');
            document.body.appendChild(c);
        }
        return c;
    }

    function dismiss(toast) {
        if (toast.dataset.dismissed) return;
        toast.dataset.dismissed = '1';
        toast.classList.add('cfo-toast-hiding');
        setTimeout(function () { toast.remove(); }, 300);
    }

    function show(text, type, extraHtml) {
        var isSuccess  = type === 'success';
        var icon       = isSuccess ? 'check_circle' : 'error';
        var typeClass  = isSuccess ? 'cfo-toast-success' : 'cfo-toast-error';
        var duration   = Math.max(BASE_DURATION, text.length * 65);

        var toast = document.createElement('div');
        toast.className = 'cfo-toast ' + typeClass;
        toast.setAttribute('role', 'status');
        toast.innerHTML =
            '<span class="material-icons cfo-toast-icon">' + icon + '</span>' +
            '<div class="cfo-toast-body">' + text + (extraHtml || '') + '</div>' +
            '<button class="cfo-toast-close" aria-label="Dismiss">&times;</button>' +
            '<div class="cfo-toast-bar"></div>';

        toast.querySelector('.cfo-toast-bar').style.animation =
            'cfoBarShrink ' + (duration / 1000) + 's linear forwards';

        toast.querySelector('.cfo-toast-close').addEventListener('click', function () {
            dismiss(toast);
        });

        getContainer().appendChild(toast);
        setTimeout(function () { dismiss(toast); }, duration);
    }

    function initFromFlash() {
        document.querySelectorAll('.cfo-flash').forEach(function (el) {
            var type  = el.classList.contains('cfo-flash-success') ? 'success' : 'error';
            var extra = el.dataset.extraHtml || null;
            show(el.textContent.trim(), type, extra);
            el.remove();
        });
    }

    return { show: show, initFromFlash: initFromFlash };
}());

// ── Cart badge (global) ──────────────────────────────────────────────────────
function updateCartBadge(count) {
    var badge = document.getElementById('cart-badge');
    if (!badge) return;
    count = parseInt(count, 10) || 0;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('cart-badge-hidden');
        badge.style.display = '';
        badge.classList.remove('cart-badge-pulse');
        void badge.offsetWidth; // reflow to restart animation
        badge.classList.add('cart-badge-pulse');
    } else {
        badge.classList.add('cart-badge-hidden');
        badge.style.display = 'none';
    }
}

function updateWishlistBadge(count) {
    var badge = document.getElementById('wishlist-badge');
    if (!badge) return;
    count = parseInt(count, 10) || 0;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('cart-badge-hidden');
        badge.style.display = '';
        badge.classList.remove('cart-badge-pulse');
        void badge.offsetWidth;
        badge.classList.add('cart-badge-pulse');
    } else {
        badge.classList.add('cart-badge-hidden');
        badge.style.display = 'none';
    }
}

function setWishlistButtonState(btn, saved) {
    var icon = btn.querySelector('.material-icons');
    var label = btn.querySelector('.wishlist-label');
    btn.classList.toggle('is-saved', !!saved);
    btn.setAttribute('aria-label', saved ? 'Saved to wishlist' : 'Save to wishlist');
    btn.setAttribute('title', saved ? 'Saved to wishlist' : 'Save to wishlist');
    if (icon) {
        icon.textContent = saved ? 'favorite' : 'favorite_border';
    }
    if (label) {
        label.textContent = saved ? 'Saved' : 'Save';
    }
}

// ── Slot selection ───────────────────────────────────────────────────────────
function initSlotSelection() {
    var slotTabs = document.querySelectorAll('.slot-tab');
    if (slotTabs.length > 0) {
        slotTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                slotTabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
            });
        });
    }
    var slotCards = document.querySelectorAll('.slot-card');
    if (slotCards.length > 0) {
        slotCards.forEach(function (card) {
            card.addEventListener('click', function () {
                slotCards.forEach(function (c) { c.classList.remove('selected'); });
                card.classList.add('selected');
            });
        });
    }
}

// ── Quantity selector (product page) ─────────────────────────────────────────
function initQuantitySelector() {
    document.querySelectorAll('.quantity-selector').forEach(function (wrapper) {
        var minusBtn = wrapper.querySelector('.qty-btn.minus');
        var plusBtn  = wrapper.querySelector('.qty-btn.plus');
        var input    = wrapper.querySelector('.qty-input');
        if (!minusBtn || !plusBtn || !input) return;

        function clamp(v) {
            var mn = parseInt(input.min, 10) || 1;
            var mx = parseInt(input.max, 10) || 999;
            return Math.max(mn, Math.min(mx, v));
        }

        minusBtn.addEventListener('click', function () {
            input.value = clamp(parseInt(input.value, 10) - 1);
        });
        plusBtn.addEventListener('click', function () {
            input.value = clamp(parseInt(input.value, 10) + 1);
        });
        input.addEventListener('change', function () {
            var v = parseInt(input.value, 10);
            input.value = isNaN(v) ? (parseInt(input.min, 10) || 1) : clamp(v);
        });
    });
}

// ── Auth tabs ────────────────────────────────────────────────────────────────
function initAuthTabs() {
    var tabCustomer = document.getElementById('tab-customer');
    var tabTrader   = document.getElementById('tab-trader');
    var roleInput   = document.getElementById('role-input');
    if (!tabCustomer || !tabTrader || !roleInput) return;

    tabCustomer.addEventListener('click', function () {
        tabCustomer.classList.add('active');
        tabTrader.classList.remove('active');
        roleInput.value = 'customer';
    });
    tabTrader.addEventListener('click', function () {
        tabTrader.classList.add('active');
        tabCustomer.classList.remove('active');
        roleInput.value = 'trader';
    });
}

// ── Star rating ──────────────────────────────────────────────────────────────
function initStarRating() {
    document.querySelectorAll('.stars-outline').forEach(function (container) {
        var stars         = container.querySelectorAll('.material-icons');
        var currentRating = -1;
        stars.forEach(function (star, index) {
            star.addEventListener('click', function () {
                if (currentRating === index) {
                    currentRating = -1;
                    stars.forEach(function (s) { s.textContent = 'star_border'; });
                } else {
                    currentRating = index;
                    stars.forEach(function (s, i) {
                        s.textContent = i <= index ? 'star' : 'star_border';
                    });
                }
            });
        });
    });
}

// ── Hamburger nav toggle ─────────────────────────────────────────────────────
function initHamburgerNav() {
    var toggle = document.getElementById('nav-toggle');
    var nav    = document.getElementById('main-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('nav-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('.nav-toggle-icon').textContent = open ? 'close' : 'menu';
    });

    // Close when a nav link is followed
    nav.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            nav.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.querySelector('.nav-toggle-icon').textContent = 'menu';
        }
    });
}

// ── AJAX add-to-cart ─────────────────────────────────────────────────────────
function initAjaxAddToCart() {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ajax-cart]');
        if (!btn || btn.disabled) return;
        var form = btn.closest('form');
        if (!form) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML =
            '<span class="material-icons" ' +
            'style="font-size:1.1rem;vertical-align:middle;' +
            'animation:cfoSpin 0.7s linear infinite;display:inline-block">' +
            'refresh</span> ADDING...';

        fetch('add_to_cart.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    new FormData(form)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (data.cart_count !== undefined) { updateCartBadge(data.cart_count); }
                CFO_TOAST.show(
                    data.message || 'Added to cart.',
                    'success',
                    '<a href="cart.php" class="cfo-flash-link">View cart &rarr;</a>'
                );
            } else {
                CFO_TOAST.show(data.error || 'Could not add to cart.', 'error');
            }
        })
        .catch(function () {
            CFO_TOAST.show('Network error — please try again.', 'error');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });
}

// ── UI polish (scroll effects, form submit feedback) ─────────────────────────
function initAjaxWishlist() {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ajax-wishlist]');
        if (!btn || btn.disabled) return;
        var form = btn.closest('form');
        if (!form) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        btn.disabled = true;

        fetch(form.getAttribute('action') || 'toggle_wishlist.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    new FormData(form)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (data.wishlist_count !== undefined) {
                    updateWishlistBadge(data.wishlist_count);
                }
                if (data.wishlisted !== undefined) {
                    setWishlistButtonState(btn, data.wishlisted);
                }
                CFO_TOAST.show(
                    data.message || 'Wishlist updated.',
                    'success',
                    '<a href="wishlist.php" class="cfo-flash-link">View wishlist &rarr;</a>'
                );

                if (data.wishlisted === false && form.classList.contains('wishlist-remove-form')) {
                    var card = form.closest('[data-wishlist-item]');
                    if (card) {
                        card.classList.add('wishlist-card-removing');
                        setTimeout(function () { card.remove(); }, 220);
                    }
                }
            } else if (data.login_required && data.login_url) {
                CFO_TOAST.show(data.error || 'Please log in to use your wishlist.', 'error');
                setTimeout(function () { window.location.href = data.login_url; }, 650);
            } else {
                CFO_TOAST.show(data.error || 'Could not update wishlist.', 'error');
            }
        })
        .catch(function () {
            CFO_TOAST.show('Network error â€” please try again.', 'error');
        })
        .finally(function () {
            btn.disabled = false;
        });
    });
}

function initUiPolish() {
    document.body.classList.add('ui-enhanced');

    var header = document.querySelector('.site-header');
    function updateHeader() {
        if (!header) return;
        header.classList.toggle('header-scrolled', window.scrollY > 8);
    }
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    document.querySelectorAll(
        '.hero-content, .hero-image-wrapper, .trader-card, .process-card, ' +
        '.category-card, .product-list-card, .cart-trader-block, .slot-card, ' +
        '.invoice-box, .profile-form, .order-table-wrap, .review-form-card, ' +
        '.review-item, .search-empty-state'
    ).forEach(function (el) { el.classList.add('fade-in-up'); });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('.fade-in-up').forEach(function (el) {
            observer.observe(el);
        });
    } else {
        document.querySelectorAll('.fade-in-up').forEach(function (el) {
            el.classList.add('is-visible');
        });
    }

    // Submit feedback — skip AJAX-handled forms/buttons
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.hasAttribute('data-ajax-cart') || btn.hasAttribute('data-ajax-wishlist')) return;
            btn.classList.add('is-submitting');
            btn.disabled = true;
        });
    });
}

// ── Init ─────────────────────────────────────────────────────────────────────
function initAll() {
    initSlotSelection();
    initQuantitySelector();
    initAuthTabs();
    initStarRating();
    initHamburgerNav();
    initAjaxAddToCart();
    initAjaxWishlist();
    initUiPolish();
    CFO_TOAST.initFromFlash();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}
