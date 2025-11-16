// resources/js/navbar-menu.js

console.log('navbar-menu.js loaded');

document.addEventListener('DOMContentLoaded', () => {
    const toggle   = document.getElementById('mobile-menu-toggle');
    const overlay  = document.getElementById('mobile-menu-overlay');
    const panel    = document.getElementById('mobile-menu-panel');
    const closeBtn = document.getElementById('mobile-menu-close');

    if (!toggle || !overlay || !panel) {
        console.warn('navbar-menu: required elements not found', {
            toggle: !!toggle,
            overlay: !!overlay,
            panel: !!panel,
        });
        return;
    }

    const isOpen = () => toggle.dataset.state === 'open';

    const openMenu = () => {
        toggle.dataset.state = 'open';

        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100');

        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');

        // Optional: prevent body scroll while menu open
        document.body.style.overflow = 'hidden';
    };

    const closeMenu = () => {
        toggle.dataset.state = 'closed';

        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100');

        panel.classList.add('translate-x-full');
        panel.classList.remove('translate-x-0');

        document.body.style.overflow = '';
    };

    // Toggle button (hamburger / X)
    toggle.addEventListener('click', () => {
        if (isOpen()) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Close button inside panel
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            if (isOpen()) closeMenu();
        });
    }

    // Click on dark overlay (but not on panel)
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay && isOpen()) {
            closeMenu();
        }
    });

    // ESC closes menu
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            closeMenu();
        }
    });
});
