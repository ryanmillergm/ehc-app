console.log('navbar-scroll.js loaded');

document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('main-navbar');
    if (!navbar) {
        console.warn('navbar-scroll: #main-navbar not found');
        return;
    }

    let lastScrollY = window.scrollY || window.pageYOffset;
    let ticking = false;

    const HIDE_OFFSET = 10;  // start hiding after 10px
    const SCROLL_DELTA = 5;  // minimum difference to count as up/down

    const setNavbarHidden = (hidden) => {
        navbar.style.transform = hidden ? 'translateY(-100%)' : 'translateY(0)';
    };

    const updateNavbar = () => {
        const currentScrollY = window.scrollY || window.pageYOffset;
        const diff = currentScrollY - lastScrollY;

        const scrollingDown = diff > SCROLL_DELTA;
        const scrollingUp   = diff < -SCROLL_DELTA;

        if (scrollingDown && currentScrollY > HIDE_OFFSET) {
            setNavbarHidden(true);
        } else if (scrollingUp || currentScrollY <= 0) {
            setNavbarHidden(false);
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
