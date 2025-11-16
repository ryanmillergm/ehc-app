// resources/js/navbar-scroll.js

console.log('navbar-scroll.js loaded');

document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('main-navbar');
    if (!navbar) {
        console.warn('navbar-scroll: #main-navbar not found');
        return;
    }

    initScrollNavbar(navbar);
    initMobileMenu(navbar);
});

function initScrollNavbar(navbar) {
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

        const isMenuOpen = navbar.dataset.menuOpen === 'true';
        if (isMenuOpen) {
            // Keep navbar visible while mobile menu is open
            setNavbarHidden(false);
            lastScrollY = currentScrollY;
            ticking = false;
            return;
        }

        const scrollingDown = diff > SCROLL_DELTA;
        const scrollingUp   = diff < -SCROLL_DELTA;

        // Comment this out once you're happy with the behavior
        // console.log({ lastScrollY, currentScrollY, diff, scrollingDown, scrollingUp });

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
}

function initMobileMenu(navbar) {
    const toggle  = document.getElementById('mobile-menu-toggle');
    const overlay = document.getElementById('mobile-menu-overlay');
    const panel   = document.getElementById('mobile-menu-panel');
    const close   = document.getElementById('mobile-menu-close');

    if (!toggle || !overlay || !panel) {
        // Nothing to do on desktop-only layouts
        return;
    }

    let isOpen = false;

    const openMenu = () => {
        if (isOpen) return;
        isOpen = true;

        navbar.dataset.menuOpen = 'true';
        toggle.classList.add('navbar-hamburger-open');

        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100', 'pointer-events-auto', 'min-h-screen');

        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');

        document.body.classList.add('overflow-hidden');
    };

    const closeMenu = () => {
        if (!isOpen) return;
        isOpen = false;

        navbar.dataset.menuOpen = 'false';
        toggle.classList.remove('navbar-hamburger-open');

        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100', 'pointer-events-auto');

        panel.classList.add('translate-x-full');
        panel.classList.remove('translate-x-0');

        document.body.classList.remove('overflow-hidden');
    };

    toggle.addEventListener('click', () => {
        isOpen ? closeMenu() : openMenu();
    });

    if (close) {
        close.addEventListener('click', closeMenu);
    }

    // Click on the gray overlay (but not the panel) closes
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeMenu();
        }
    });

    // ESC key closes
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            closeMenu();
        }
    });

    // If window resized to desktop, ensure menu is closed
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768 && isOpen) {
            closeMenu();
        }
    });
}
