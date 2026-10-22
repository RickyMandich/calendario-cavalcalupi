// Menu di navigazione a comparsa (sotto il breakpoint `md`, dove la barra in linea è nascosta).
// Markup in resources/views/layouts/app.blade.php: #nav-toggle (pulsante) e #mobile-menu (pannello).
const MD_BREAKPOINT = '(min-width: 768px)';

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('nav-toggle');
    const menu = document.getElementById('mobile-menu');
    if (!toggle || !menu) return;

    const iconOpen = toggle.querySelector('[data-nav-icon="open"]');
    const iconClose = toggle.querySelector('[data-nav-icon="close"]');

    function setOpen(open) {
        menu.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Chiudi il menu' : 'Apri il menu');
        if (iconOpen) iconOpen.classList.toggle('hidden', open);
        if (iconClose) iconClose.classList.toggle('hidden', !open);
    }

    toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));

    // Chiude dopo la scelta di una voce (utile per i link che restano nella stessa pagina).
    menu.addEventListener('click', (e) => {
        if (e.target.closest('a')) setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Passando a una larghezza in cui la barra in linea è visibile, riporta il menu allo stato chiuso.
    window.matchMedia(MD_BREAKPOINT).addEventListener('change', (e) => {
        if (e.matches) setOpen(false);
    });
});
