// resources/js/navbar-scroll.js

console.log('navbar-scroll.js loaded');

document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('main-navbar');
    if (!navbar) {
        console.warn('navbar-scroll: #main-navbar not found');
        return;
    }

    let lastScrollY = window.pageYOffset || document.documentElement.scrollTop;
    let ticking = false;

    const SCROLL_DOWN_HIDE_OFFSET = 20; // how far down before we allow hiding
    const SCROLL_DELTA = 5;             // minimum scroll delta to react

    const updateNavbar = () => {
        const currentScrollY = window.pageYOffset || document.documentElement.scrollTop;

        const scrollingDown = currentScrollY > lastScrollY + SCROLL_DELTA;
        const scrollingUp   = currentScrollY < lastScrollY - SCROLL_DELTA;

        if (scrollingDown && currentScrollY > SCROLL_DOWN_HIDE_OFFSET) {
            // Slide navbar up out of view
            navbar.classList.add('-translate-y-full');
        } else if (scrollingUp) {
            // Slide navbar back in
            navbar.classList.remove('-translate-y-full');
        }

        lastScrollY = currentScrollY;
        ticking = false;
    };

    window.addEventListener(
        'scroll',
        () => {
            if (!ticking) {
                window.requestAnimationFrame(updateNavbar);
                ticking = true;
            }
        },
        { passive: true }
    );
});
